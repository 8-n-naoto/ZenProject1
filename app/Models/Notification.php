<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * アプリ内通知。Laravel標準の通知とは別の、このアプリ専用のテーブル。
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'event_id', 'type', 'payload', 'notified_at', 'read_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'notified_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * 画面に出す本文。
     */
    public function message(): string
    {
        $payload = $this->payload ?? [];
        $actionLabel = isset($payload['action_type'])
            ? (\App\Enums\ApprovalActionType::tryFrom($payload['action_type'])?->label() ?? $payload['action_type'])
            : '';

        return match ($this->type) {
            'invitation.received' => ($payload['group'] ?? 'グループ').' に招待されました。',
            'event.created' => 'イベント「'.($payload['event'] ?? '').'」が作成されました。',
            'event.accepting' => 'イベント「'.($payload['event'] ?? '').'」の受付が始まりました。',
            'event.fixed' => 'イベント「'.($payload['event'] ?? '').'」の内容が確定しました。',
            'event.ongoing' => 'イベント「'.($payload['event'] ?? '').'」が始まりました。買い物リストを開いて記録しましょう。',
            'event.updated' => 'イベント「'.($payload['event'] ?? '').'」の状態が変わりました。',
            'event.settling' => 'イベント「'.($payload['event'] ?? '').'」の精算が始まりました。',
            'event.completed' => 'イベント「'.($payload['event'] ?? '').'」が完了しました。',
            'assignee.confirmed' => ($payload['circle'] ?? 'サークル').' の購入担当に決まりました。',
            'product_assignee.assigned' => '「'.($payload['product'] ?? '').'」の購入担当に割り当てられました。',
            'approval.requested' => ($payload['applicant'] ?? '責任者').' さんが「'.$actionLabel.'」の承認を申請しました。',
            'approval.approved' => '「'.$actionLabel.'」が可決されました。',
            'approval.rejected' => '「'.$actionLabel.'」が否決されました。',
            'approval.withdrawn' => '「'.$actionLabel.'」の申請が取り下げられました。',
            'payment.reported' => ($payload['payer'] ?? '').' さんから ¥'.number_format((int) ($payload['amount'] ?? 0)).' の支払い報告がありました。',
            'payment.confirmed' => ($payload['payee'] ?? '').' さんが受取を確認しました。',
            'settlement.generated' => '精算リストができました。支払い・受取の内容を確認してください。',
            'result.reminder' => '購入結果が'.($payload['pending'] ?? 0).'件未登録です。当日の実績を登録してください。',
            'wish.reminder' => 'まもなく開催です。買いたいものをまだ登録していません。',
            default => '新しいお知らせがあります。',
        };
    }

    /**
     * 通知から遷移する先。
     */
    public function url(): ?string
    {
        if ($this->type === 'invitation.received') {
            return route('invitations.index');
        }

        if ($this->event_id === null) {
            return null;
        }

        return match ($this->type) {
            'payment.reported', 'payment.confirmed', 'settlement.generated' => route('settlements.index', $this->event_id),
            default => route('events.show', $this->event_id),
        };
    }
}
