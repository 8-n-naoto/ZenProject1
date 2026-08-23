<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 主要な操作の変更履歴。
 */
class ChangeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'event_id', 'actor_user_id', 'subject_type', 'subject_id',
        'action', 'changes', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    /**
     * 画面に出す説明文。
     */
    public function description(): string
    {
        // Eloquent の Model::$changes プロパティと衝突するため getAttribute で取得する
        $changes = $this->getAttribute('changes') ?? [];

        return match ($this->action) {
            'event.created' => 'イベントを作成しました。',
            'event.updated' => 'イベント情報を変更しました。',
            'event.duplicated' => '「'.($changes['source'] ?? '').'」からサークル'.($changes['circles'] ?? 0).'件を引き継いで作成しました。',
            'event.status_changed' => 'イベントの状態を「'.($changes['to'] ?? '').'」に変更しました。',
            'event.relocked' => '内容変更の解禁を終了しました。',
            'member.role_changed' => ($changes['target'] ?? '').' さんの役割を「'.($changes['to'] ?? '').'」に変更しました。',
            'member.removed' => ($changes['target'] ?? '').' さんを除名しました。',
            'member.left' => 'グループを脱退しました。',
            'catalog.imported' => 'サークル'.($changes['circles'] ?? 0).'件・商品'.($changes['products'] ?? 0).'件をまとめて登録しました。',
            'circle.created' => 'サークル「'.($changes['name'] ?? '').'」を登録しました。',
            'circle.updated' => 'サークル「'.($changes['name'] ?? '').'」を変更しました。',
            'circle.deleted' => 'サークル「'.($changes['name'] ?? '').'」を削除しました。',
            'product.created' => '商品「'.($changes['name'] ?? '').'」を登録しました。',
            'product.updated' => '商品「'.($changes['name'] ?? '').'」を変更しました。',
            'product.deleted' => '商品「'.($changes['name'] ?? '').'」を削除しました。',
            'assignee.assigned' => ($changes['target'] ?? '').' さんを購入担当に確定しました。',
            'assignee.unassigned' => ($changes['target'] ?? '').' さんを購入担当から外しました。',
            'invitation.sent' => ($changes['target'] ?? '').' さんをグループに招待しました。',
            'invite_link.issued' => '招待リンクを発行しました。',
            'invite_link.revoked' => '招待リンクを無効にしました。',
            'member.joined_by_link' => ($changes['target'] ?? '').' さんが招待リンクから参加しました。',
            'product_assignee.updated' => '「'.($changes['product'] ?? '').'」の担当を'.($changes['assignees'] ?? 0).'人に割り当てました。',
            'result.recorded' => '「'.($changes['product'] ?? '').'」の購入結果を登録しました（'.($changes['purchased'] ?? 0).'点）。',
            'settlement.generated' => '精算リストを作成しました（'.($changes['count'] ?? 0).'件）。',
            'payment.reported' => '¥'.number_format((int) ($changes['amount'] ?? 0)).' の支払いを報告しました。',
            'payment.confirmed' => '¥'.number_format((int) ($changes['amount'] ?? 0)).' の受取を確認しました。',
            'approval.requested' => '承認を申請しました。',
            'approval.approved' => '承認が可決されました。',
            'approval.withdrawn' => '承認申請が取り下げられました。',
            default => $this->action,
        };
    }
}
