<?php

namespace App\Services;

use App\Enums\GroupRole;
use App\Enums\SettlementStatus;
use App\Models\Settlement;
use App\Models\User;

/**
 * 退会できるかどうかの判定。
 */
class AccountDeletionGuard
{
    /**
     * 退会をブロックする理由の一覧。空なら退会できる。
     *
     * @return array<int, string>
     */
    public function reasons(User $user): array
    {
        $reasons = [];

        foreach ($user->activeGroups()->get() as $group) {
            $role = GroupRole::tryFrom((string) $group->pivot->role);

            if ($role === null || ! $role->requiresAtLeastOne()) {
                continue;
            }

            if ($group->countActiveWithRole($role) <= 1) {
                $reasons[] = 'グループ「'.$group->name.'」の'.$role->label().'があなただけです。後任を任命してから退会してください。';
            }
        }

        // 未精算が残っていると、退会後に支払い報告も受取確認もできなくなる
        $outstanding = Settlement::query()
            ->where('status', SettlementStatus::Pending->value)
            ->where(fn ($query) => $query->where('payer_user_id', $user->id)->orWhere('payee_user_id', $user->id))
            ->count();

        if ($outstanding > 0) {
            $reasons[] = '未精算が'.$outstanding.'件あります。精算を終えてから退会してください。';
        }

        return $reasons;
    }

    public function canDelete(User $user): bool
    {
        return $this->reasons($user) === [];
    }
}
