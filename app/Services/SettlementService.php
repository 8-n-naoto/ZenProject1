<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PurchaseResult;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 精算の計算と、支払いの記録。
 *
 * 計算の流れ:
 *  1. 購入結果から「受益者 → 立替者」の債務（debt）を1件ずつ組み立てる
 *  2. ユーザーごとに純額（受け取るべき額 − 支払うべき額）を求める
 *  3. 純額の大きい順に突き合わせ、送金回数が最小になる送金リストを作る
 *
 * 金額は全て円単位の整数で扱い、割り算による端数は発生させない。
 */
class SettlementService
{
    public function __construct(
        private readonly PurchaseResultService $results,
        private readonly NotificationService $notifications,
        private readonly ChangeHistoryService $history,
    ) {}

    /**
     * 購入結果から債務の明細を組み立てる。
     *
     * @return Collection<int, array{debtor_id:int, creditor_id:int, purchase_result_id:int, quantity:int, amount:int}>
     */
    public function debts(Event $event): Collection
    {
        $results = PurchaseResult::query()
            ->where(function ($query) use ($event) {
                $query->whereHas('sharedPurchaseItem.sharedPurchase', fn ($q) => $q->where('event_id', $event->id))
                    ->orWhereHas('personalPurchase', fn ($q) => $q->where('event_id', $event->id));
            })
            ->with(['eventProduct', 'shortageUsers', 'excessTakeover', 'sharedPurchaseItem'])
            ->orderBy('id')
            ->get();

        $debts = collect();

        foreach ($results as $result) {
            $unitPrice = $result->effectiveUnitPrice();
            $payerId = $result->purchase_assignee_user_id;

            foreach ($this->results->allocationFor($result) as $userId => $quantity) {
                if ((int) $userId === (int) $payerId || $quantity <= 0) {
                    continue;
                }

                $debts->push([
                    'debtor_id' => (int) $userId,
                    'creditor_id' => (int) $payerId,
                    'purchase_result_id' => $result->id,
                    'quantity' => (int) $quantity,
                    'amount' => (int) $quantity * $unitPrice,
                ]);
            }
        }

        return $debts;
    }

    /**
     * ユーザーごとの純額。正なら受け取る側、負なら支払う側。
     *
     * @return array<int, int>
     */
    public function balances(Event $event): array
    {
        $balances = [];

        foreach ($this->debts($event) as $debt) {
            $balances[$debt['debtor_id']] = ($balances[$debt['debtor_id']] ?? 0) - $debt['amount'];
            $balances[$debt['creditor_id']] = ($balances[$debt['creditor_id']] ?? 0) + $debt['amount'];
        }

        return array_filter($balances, fn (int $amount) => $amount !== 0);
    }

    /**
     * 純額から、送金回数が最小になる送金リストを求める。
     *
     * @param  array<int, int>  $balances
     * @return array<int, array{payer_id:int, payee_id:int, amount:int}>
     */
    public function minimalTransfers(array $balances): array
    {
        $debtors = [];   // 支払う側（負）
        $creditors = []; // 受け取る側（正）

        foreach ($balances as $userId => $amount) {
            if ($amount < 0) {
                $debtors[] = ['user_id' => (int) $userId, 'amount' => -$amount];
            } elseif ($amount > 0) {
                $creditors[] = ['user_id' => (int) $userId, 'amount' => $amount];
            }
        }

        // 金額が大きい順に突き合わせると送金回数が少なくなる
        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount'] ?: $a['user_id'] <=> $b['user_id']);
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount'] ?: $a['user_id'] <=> $b['user_id']);

        $transfers = [];

        // 金額がぴったり一致する組は先に1回の送金で相殺してしまう（送金回数を減らす）
        foreach ($debtors as $di => $debtor) {
            if ($debtor['amount'] === 0) {
                continue;
            }

            foreach ($creditors as $ci => $creditor) {
                if ($creditor['amount'] !== $debtor['amount']) {
                    continue;
                }

                $transfers[] = [
                    'payer_id' => $debtor['user_id'],
                    'payee_id' => $creditor['user_id'],
                    'amount' => $debtor['amount'],
                ];

                $debtors[$di]['amount'] = 0;
                $creditors[$ci]['amount'] = 0;
                break;
            }
        }

        $debtors = array_values(array_filter($debtors, fn ($d) => $d['amount'] > 0));
        $creditors = array_values(array_filter($creditors, fn ($c) => $c['amount'] > 0));
        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = min($debtors[$i]['amount'], $creditors[$j]['amount']);

            if ($amount > 0) {
                $transfers[] = [
                    'payer_id' => $debtors[$i]['user_id'],
                    'payee_id' => $creditors[$j]['user_id'],
                    'amount' => $amount,
                ];
            }

            $debtors[$i]['amount'] -= $amount;
            $creditors[$j]['amount'] -= $amount;

            if ($debtors[$i]['amount'] === 0) {
                $i++;
            }

            if ($creditors[$j]['amount'] === 0) {
                $j++;
            }
        }

        return $transfers;
    }

    /**
     * イベントの精算リストを生成する（未精算のものは作り直す）。
     *
     * @return Collection<int, Settlement>
     */
    public function generate(Event $event): Collection
    {
        $transfers = $this->minimalTransfers($this->balances($event));

        return DB::transaction(function () use ($event, $transfers) {
            $completed = $event->settlements()
                ->where('status', SettlementStatus::Completed->value)
                ->get();

            if ($completed->isNotEmpty()) {
                throw new BusinessRuleException(
                    '精算済みの記録があるため、精算リストを作り直せません。',
                    'settlement'
                );
            }

            // 未精算の精算リストと、それに紐づく支払い報告を作り直す
            foreach ($event->settlements()->with('payments.items')->get() as $settlement) {
                foreach ($settlement->payments as $payment) {
                    $payment->items()->delete();
                    $payment->delete();
                }

                $settlement->delete();
            }

            foreach ($transfers as $transfer) {
                $event->settlements()->create([
                    'payer_user_id' => $transfer['payer_id'],
                    'payee_user_id' => $transfer['payee_id'],
                    'amount' => $transfer['amount'],
                    'status' => SettlementStatus::Pending,
                ]);
            }

            return $event->settlements()->with(['payer', 'payee'])->get();
        });
    }

    /**
     * 支払いを報告する。
     */
    public function reportPayment(Settlement $settlement, User $actor): Payment
    {
        if ($settlement->isCompleted()) {
            throw new BusinessRuleException('この精算はすでに完了しています。', 'settlement');
        }

        if ($settlement->reportedPayment() !== null) {
            throw new BusinessRuleException('すでに支払いを報告しています。受取確認をお待ちください。', 'settlement');
        }

        return DB::transaction(function () use ($settlement, $actor) {
            $payment = $settlement->payments()->create([
                'event_id' => $settlement->event_id,
                'payer_user_id' => $settlement->payer_user_id,
                'payee_user_id' => $settlement->payee_user_id,
                'confirmed_by' => null,
                'total_amount' => $settlement->amount,
                'status' => PaymentStatus::Reported,
                'paid_at' => now(),
            ]);

            foreach ($this->componentsFor($settlement) as $component) {
                $payment->items()->create([
                    'purchase_result_id' => $component['purchase_result_id'],
                    'quantity' => $component['quantity'],
                    'amount' => $component['amount'],
                ]);
            }

            $this->notifications->notify(
                [$settlement->payee_user_id],
                'payment.reported',
                $settlement->event,
                ['payer' => $actor->displayName(), 'amount' => $settlement->amount]
            );

            $this->history->record(
                $actor,
                $payment,
                'payment.reported',
                ['amount' => $settlement->amount],
                $settlement->event->group,
                $settlement->event
            );

            return $payment->fresh('items');
        });
    }

    /**
     * 受取を確認して精算を完了する。
     */
    public function confirmPayment(Payment $payment, User $actor): void
    {
        if ($payment->status !== PaymentStatus::Reported) {
            throw new BusinessRuleException('この支払いはすでに処理されています。', 'payment');
        }

        DB::transaction(function () use ($payment, $actor) {
            $payment->update([
                'status' => PaymentStatus::Confirmed,
                'confirmed_by' => $actor->id,
            ]);

            $payment->settlement?->update([
                'status' => SettlementStatus::Completed,
                'settled_at' => now(),
                'completed_by' => $actor->id,
            ]);

            $this->notifications->notify(
                [$payment->payer_user_id],
                'payment.confirmed',
                $payment->event,
                ['payee' => $actor->displayName(), 'amount' => $payment->total_amount]
            );

            $this->history->record(
                $actor,
                $payment,
                'payment.confirmed',
                ['amount' => $payment->total_amount],
                $payment->event->group,
                $payment->event
            );
        });
    }

    /**
     * 支払い報告を取り消す（受取側が「受け取っていない」と判断した場合）。
     */
    public function rejectPayment(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Reported) {
            throw new BusinessRuleException('この支払いはすでに処理されています。', 'payment');
        }

        DB::transaction(function () use ($payment) {
            $payment->items()->delete();
            $payment->update(['status' => PaymentStatus::Cancelled]);
        });
    }

    /**
     * 精算1件が「どの購入のどの分」をカバーしているかを求める。
     *
     * 相殺により支払先が変わることがあるため、支払う人自身の債務を
     * 金額の大きい順に、その人の精算リストの順番で切り出していく。
     *
     * @return array<int, array{purchase_result_id:int, quantity:int, amount:int}>
     */
    public function componentsFor(Settlement $settlement): array
    {
        $event = $settlement->loadMissing('event')->event;

        // その支払い者の債務を、金額の大きい順（同額は購入結果ID順）に並べる
        $debts = $this->debts($event)
            ->where('debtor_id', $settlement->payer_user_id)
            ->sortBy([['amount', 'desc'], ['purchase_result_id', 'asc']])
            ->values()
            ->all();

        // 同じ支払い者の全ての送金に対して、債務を順番に切り出していく
        $settlements = $event->settlements()
            ->where('payer_user_id', $settlement->payer_user_id)
            ->orderBy('id')
            ->get();

        $index = 0;
        $remainingAmount = $debts === [] ? 0 : $debts[0]['amount'];
        $remainingQuantity = $debts === [] ? 0 : $debts[0]['quantity'];

        foreach ($settlements as $current) {
            $need = $current->amount;
            $components = [];

            while ($need > 0 && $index < count($debts)) {
                $debt = $debts[$index];
                $take = min($need, $remainingAmount);

                if ($take > 0) {
                    $unitPrice = $debt['quantity'] > 0 ? intdiv($debt['amount'], $debt['quantity']) : 0;

                    // 債務を使い切る場合は残数量をそのまま、途中で切る場合は単価で割った分だけ計上する
                    $quantity = $take === $remainingAmount
                        ? $remainingQuantity
                        : ($unitPrice > 0 ? intdiv($take, $unitPrice) : 0);

                    $components[] = [
                        'purchase_result_id' => $debt['purchase_result_id'],
                        'quantity' => $quantity,
                        'amount' => $take,
                    ];

                    $remainingAmount -= $take;
                    $remainingQuantity -= $quantity;
                    $need -= $take;
                }

                if ($remainingAmount <= 0) {
                    $index++;

                    if ($index < count($debts)) {
                        $remainingAmount = $debts[$index]['amount'];
                        $remainingQuantity = $debts[$index]['quantity'];
                    }
                }
            }

            if ($current->id === $settlement->id) {
                return $components;
            }
        }

        return [];
    }

    /**
     * チャットに貼り付けられる精算のまとめテキストを作る。
     */
    /**
     * グループをまたいだ自分の未精算をまとめる。
     *
     * @return array{toPay: Collection<int, Settlement>, toReceive: Collection<int, Settlement>, payTotal: int, receiveTotal: int, net: int}
     */
    public function outstandingFor(User $user): array
    {
        $groupIds = $user->activeGroups()->pluck('groups.id');

        $settlements = Settlement::query()
            ->where('status', SettlementStatus::Pending->value)
            ->whereHas('event', fn ($q) => $q->whereIn('group_id', $groupIds))
            ->where(fn ($q) => $q->where('payer_user_id', $user->id)->orWhere('payee_user_id', $user->id))
            ->with(['payer', 'payee', 'event.group.activeMembers', 'payments'])
            ->get()
            ->sortByDesc('amount')
            ->values();

        $toPay = $settlements->where('payer_user_id', $user->id)->values();
        $toReceive = $settlements->where('payee_user_id', $user->id)->values();

        $payTotal = (int) $toPay->sum('amount');
        $receiveTotal = (int) $toReceive->sum('amount');

        return [
            'toPay' => $toPay,
            'toReceive' => $toReceive,
            'payTotal' => $payTotal,
            'receiveTotal' => $receiveTotal,
            'net' => $receiveTotal - $payTotal,
        ];
    }

    public function shareText(Event $event): string
    {
        $settlements = $event->settlements()->with(['payer', 'payee'])->get();

        if ($settlements->isEmpty()) {
            return '【'.$event->name."】\n精算はありません。";
        }

        $lines = ['【'.$event->name.'】精算のご案内', ''];

        foreach ($settlements->sortByDesc('amount') as $settlement) {
            $lines[] = sprintf(
                '%s %s さん → %s さん %s',
                $settlement->isCompleted() ? '[済]' : '[未]',
                $settlement->payer?->displayName() ?? '不明',
                $settlement->payee?->displayName() ?? '不明',
                $settlement->amountLabel()
            );
        }

        $pending = $settlements->reject(fn (Settlement $settlement) => $settlement->isCompleted());

        $lines[] = '';
        $lines[] = sprintf(
            '合計 %d件 / %s（未精算 %d件 / ¥%s）',
            $settlements->count(),
            '¥'.number_format((int) $settlements->sum('amount')),
            $pending->count(),
            number_format((int) $pending->sum('amount'))
        );

        return implode("\n", $lines);
    }

    /**
     * 参加者ごとの精算サマリ。
     *
     * @return array<int, array{user:User, spent:int, owed:int, net:int}>
     */
    public function summary(Event $event): array
    {
        $summary = [];
        $balances = $this->balances($event);

        foreach ($this->debts($event) as $debt) {
            $summary[$debt['debtor_id']]['owed'] = ($summary[$debt['debtor_id']]['owed'] ?? 0) + $debt['amount'];
            $summary[$debt['creditor_id']]['spent'] = ($summary[$debt['creditor_id']]['spent'] ?? 0) + $debt['amount'];
        }

        $users = User::query()->whereIn('id', array_keys($summary + $balances))->get()->keyBy('id');

        $rows = [];

        foreach ($users as $id => $user) {
            $rows[] = [
                'user' => $user,
                'spent' => $summary[$id]['spent'] ?? 0,
                'owed' => $summary[$id]['owed'] ?? 0,
                'net' => $balances[$id] ?? 0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['net'] <=> $a['net']);

        return $rows;
    }

    /**
     * 1人分の収支の内訳。立替（他の人の分を購入した明細）と
     * 購入（他の人に立て替えてもらった明細）に分けて返す。
     *
     * 自分で立て替えた自分の購入分は債務にならないため含まれない
     * （summary() の立替・購入と同じ集計基準）。
     *
     * @return array{
     *     spent: Collection<int, array{result:?PurchaseResult, counterparty:?User, quantity:int, amount:int}>,
     *     owed: Collection<int, array{result:?PurchaseResult, counterparty:?User, quantity:int, amount:int}>,
     *     spentTotal: int,
     *     owedTotal: int,
     *     net: int
     * }
     */
    public function breakdownFor(Event $event, User $user): array
    {
        $debts = $this->debts($event);

        $spentDebts = $debts->where('creditor_id', $user->id)->values();
        $owedDebts = $debts->where('debtor_id', $user->id)->values();

        $results = PurchaseResult::query()
            ->with('eventProduct.eventCircle')
            ->whereIn('id', $spentDebts->merge($owedDebts)->pluck('purchase_result_id')->unique())
            ->get()
            ->keyBy('id');

        $counterparties = User::withTrashed()
            ->whereIn('id', $spentDebts->pluck('debtor_id')->merge($owedDebts->pluck('creditor_id'))->unique())
            ->get()
            ->keyBy('id');

        $row = fn (array $debt, int $counterpartyId): array => [
            'result' => $results->get($debt['purchase_result_id']),
            'counterparty' => $counterparties->get($counterpartyId),
            'quantity' => $debt['quantity'],
            'amount' => $debt['amount'],
        ];

        $spentTotal = (int) $spentDebts->sum('amount');
        $owedTotal = (int) $owedDebts->sum('amount');

        return [
            'spent' => $spentDebts->map(fn (array $debt) => $row($debt, $debt['debtor_id'])),
            'owed' => $owedDebts->map(fn (array $debt) => $row($debt, $debt['creditor_id'])),
            'spentTotal' => $spentTotal,
            'owedTotal' => $owedTotal,
            'net' => $spentTotal - $owedTotal,
        ];
    }
}
