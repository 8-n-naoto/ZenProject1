<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PersonalPurchase;
use App\Models\SharedPurchaseItem;
use App\Models\User;

/**
 * 記録用のCSV出力。
 *
 * Excelで開いても文字化けしないよう UTF-8 BOM 付き・CRLF で出力する。
 */
class ExportService
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * 購入結果一覧のCSV。
     *
     * 共同購入はイベント参加者の共有情報なのですべて出力する。
     * 個人購入は本人しか見られない情報なので、$viewer の分だけを出力する。
     */
    public function purchaseResultsCsv(Event $event, User $viewer): string
    {
        $event->loadMissing([
            'sharedPurchases.eventCircle',
            'sharedPurchases.assignees.user',
            'sharedPurchases.items.eventProduct',
            'sharedPurchases.items.purchaseResult',
        ]);

        $rows = [[
            '区分', 'サークル', '配置', '商品', '単価', '予定数', '購入数', '状態', '担当者', '金額',
        ]];

        foreach ($event->sharedPurchases as $sharedPurchase) {
            $circle = $sharedPurchase->eventCircle;
            $assignees = $sharedPurchase->assignees
                ->filter(fn ($a) => $a->isConfirmed())
                ->map(fn ($a) => $a->user?->name ?? '')
                ->filter()
                ->implode(' / ');

            foreach ($sharedPurchase->items as $item) {
                $rows[] = $this->itemRow('共同', $circle?->display_name, $circle?->booth, $item, $assignees);
            }
        }

        $personal = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $viewer->id)
            ->with(['user', 'eventProduct.eventCircle', 'purchaseResult'])
            ->get();

        foreach ($personal as $purchase) {
            $product = $purchase->eventProduct;
            $circle = $product?->eventCircle;
            $result = $purchase->purchaseResult;
            $quantity = $result?->purchased_quantity ?? 0;
            $unitPrice = $result?->unit_price ?? $product?->price ?? 0;

            $rows[] = [
                '個人',
                $circle?->display_name ?? '',
                $circle?->booth ?? '',
                $product?->name ?? '',
                $unitPrice,
                $purchase->planned_quantity,
                $result === null ? '' : $quantity,
                $result?->status->value ?? '未登録',
                $purchase->user?->name ?? '',
                $result === null ? '' : $unitPrice * $quantity,
            ];
        }

        return $this->toCsv($rows);
    }

    /**
     * 精算リストのCSV。
     */
    public function settlementsCsv(Event $event): string
    {
        $settlements = $event->settlements()
            ->with(['payer', 'payee', 'payments.confirmedBy'])
            ->get()
            ->sortByDesc('amount')
            ->values();

        $rows = [[
            '支払う人', '受け取る人', '金額', '状態', '報告日時', '確認日時', '確認者',
        ]];

        foreach ($settlements as $settlement) {
            $latest = $settlement->payments->sortByDesc('created_at')->first();

            $rows[] = [
                $settlement->payer?->name ?? '',
                $settlement->payee?->name ?? '',
                $settlement->amount,
                $settlement->status->value,
                $this->dateTime($latest?->paid_at),
                $this->dateTime($settlement->settled_at),
                $latest?->confirmedBy?->name ?? '',
            ];
        }

        return $this->toCsv($rows);
    }

    /**
     * ダウンロード時のファイル名（イベント名を安全な形にする）。
     */
    public function fileName(Event $event, string $suffix): string
    {
        $base = preg_replace('/[^\p{L}\p{N}ー_-]+/u', '_', $event->name) ?? 'event';
        $base = trim((string) $base, '_');

        if ($base === '') {
            $base = 'event';
        }

        return $base.'_'.$suffix.'.csv';
    }

    /**
     * @return array<int, mixed>
     */
    private function itemRow(string $kind, ?string $circleName, ?string $booth, SharedPurchaseItem $item, string $assignees): array
    {
        $product = $item->eventProduct;
        $result = $item->purchaseResult;
        $quantity = $result?->purchased_quantity ?? 0;
        $unitPrice = $result?->unit_price ?? $product?->price ?? 0;

        return [
            $kind,
            $circleName ?? '',
            $booth ?? '',
            $product?->name ?? '',
            $unitPrice,
            $item->planned_quantity,
            $result === null ? '' : $quantity,
            $result?->status->value ?? '未登録',
            $assignees,
            $result === null ? '' : $unitPrice * $quantity,
        ];
    }

    private function dateTime(?\DateTimeInterface $value): string
    {
        return $value === null ? '' : $value->format('Y/m/d H:i');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map($this->escape(...), $row));
        }

        return self::BOM.implode("\r\n", $lines)."\r\n";
    }

    private function escape(mixed $value): string
    {
        $text = (string) $value;

        // 数式として解釈される先頭文字を無害化する（CSVインジェクション対策）
        if ($text !== '' && str_contains("=+-@\t\r", $text[0]) && ! is_numeric($text)) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }
}
