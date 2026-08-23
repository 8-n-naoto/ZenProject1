<?php

namespace App\Services;

use App\Enums\GroupRole;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * グループメンバーの追加・脱退・除名・役割変更。
 *
 * 「責任者・最高責任者は最低1人必要」というルールを、
 * 脱退・除名・降格の全経路でここに集約して強制する。
 */
class GroupMemberService
{
    /**
     * メンバーを参加させる。過去に脱退している場合は復帰させる。
     */
    public function join(Group $group, User $user, GroupRole $role = GroupRole::Member): void
    {
        if ($group->isActiveMember($user)) {
            throw new BusinessRuleException('このユーザーはすでにグループのメンバーです。', 'member');
        }

        DB::transaction(function () use ($group, $user, $role) {
            $existing = $group->members()->where('users.id', $user->id)->first();

            if ($existing !== null) {
                // 過去に脱退・除名されている場合は復帰させる（履歴は保持する）。
                $group->members()->updateExistingPivot($user->id, [
                    'role' => $role->value,
                    'joined_at' => now(),
                    'left_at' => null,
                ]);

                return;
            }

            $group->members()->attach($user->id, [
                'role' => $role->value,
                'joined_at' => now(),
            ]);
        });
    }

    /**
     * 自分でグループを脱退する。
     */
    public function leave(Group $group, User $user): void
    {
        $role = $group->roleOf($user);

        if ($role === null) {
            throw new BusinessRuleException('このグループのメンバーではありません。', 'member');
        }

        $this->assertNotLastOfRequiredRole($group, $role);
        $this->assertNoOutstandingSettlements($group, $user, 'あなた');

        $group->members()->updateExistingPivot($user->id, ['left_at' => now()]);

        $this->reevaluateApprovals($group, $user);
    }

    /**
     * メンバーを除名する。権限判定は GroupPolicy 側で行う。
     */
    public function remove(Group $group, User $target): void
    {
        $role = $group->roleOf($target);

        if ($role === null) {
            throw new BusinessRuleException('指定されたユーザーはこのグループのメンバーではありません。', 'member');
        }

        $this->assertNotLastOfRequiredRole($group, $role);
        $this->assertNoOutstandingSettlements($group, $target, $target->displayName());

        $group->members()->updateExistingPivot($target->id, ['left_at' => now()]);

        $this->reevaluateApprovals($group, $target);
    }

    /**
     * 責任者が増減したら、承認待ちの申請を判定し直す。
     *
     * 「過半数の分母」が変わるため、放置すると誰も投票できないまま止まることがある。
     */
    private function reevaluateApprovals(Group $group, User $actor): void
    {
        app(ApprovalService::class)->reevaluatePending($group->fresh(), $actor);
    }

    /**
     * 未精算が残っている場合は脱退・除名できない。
     *
     * 脱退すると本人が支払い報告も受取確認もできなくなり、
     * イベントを完了させられなくなるため。
     */
    private function assertNoOutstandingSettlements(Group $group, User $user, string $subject): void
    {
        $count = Settlement::query()
            ->where('status', SettlementStatus::Pending->value)
            ->whereHas('event', fn ($query) => $query->where('group_id', $group->id))
            ->where(fn ($query) => $query->where('payer_user_id', $user->id)->orWhere('payee_user_id', $user->id))
            ->count();

        if ($count > 0) {
            throw new BusinessRuleException(
                $subject.'には未精算が'.$count.'件あります。精算を終えてから手続きしてください。',
                'member'
            );
        }
    }

    /**
     * 役割を変更する。権限判定は GroupPolicy 側で行う。
     *
     * @return bool 実際に変更があったか
     */
    public function changeRole(Group $group, User $target, GroupRole $newRole): bool
    {
        $currentRole = $group->roleOf($target);

        if ($currentRole === null) {
            throw new BusinessRuleException('指定されたユーザーはこのグループのメンバーではありません。', 'member');
        }

        if ($currentRole === $newRole) {
            return false;
        }

        // 昇格・降格のどちらでも、責任者・最高責任者が0人になる変更は認めない。
        // （権限が減らない昇格でも「最後の責任者」を動かすと責任者が不在になる）
        $this->assertNotLastOfRequiredRole($group, $currentRole);

        $group->members()->updateExistingPivot($target->id, ['role' => $newRole->value]);

        $this->reevaluateApprovals($group, $target);

        return true;
    }

    /**
     * その役割の在籍者が本人だけの場合に例外を投げる。
     */
    private function assertNotLastOfRequiredRole(Group $group, GroupRole $role): void
    {
        if (! $role->requiresAtLeastOne()) {
            return;
        }

        if ($group->countActiveWithRole($role) <= 1) {
            throw new BusinessRuleException(
                $role->label().'は最低1人必要です。先に後任を任命してください。',
                'member'
            );
        }
    }
}
