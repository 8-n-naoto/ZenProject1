<?php

namespace App\Enums;

/**
 * 承認フローの対象となる操作。
 *
 * 完成定義書「承認フローの対象操作」に対応する。
 */
enum ApprovalActionType: string
{
    /** 受付中 → 確定済（購入リスト・担当者・参加者をロックする） */
    case FixEvent = 'event.fix';

    /** 精算中 → 完了 */
    case CompleteEvent = 'event.complete';

    /** 完了したイベントを精算中に戻す */
    case ReopenEvent = 'event.reopen';

    /** 確定後のサークル・商品・価格・共同購入リストの変更を解禁する */
    case UnlockContents = 'event.unlock';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::FixEvent => 'イベントの確定',
            self::CompleteEvent => '精算の完了',
            self::ReopenEvent => '完了イベントの再オープン',
            self::UnlockContents => '確定後の内容変更',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FixEvent => '購入リスト・購入担当者・参加者を確定してロックします。',
            self::CompleteEvent => 'イベントを完了し、最高責任者以外は閲覧のみになります。',
            self::ReopenEvent => '完了したイベントを精算中に戻して修正できるようにします。',
            self::UnlockContents => '確定後のサークル・商品・共同購入リストの変更を許可します。',
        };
    }
}
