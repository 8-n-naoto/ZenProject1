<?php

namespace App\Policies;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

/**
 * グループに対する権限判定。
 *
 * 全ての判定は「在籍中（left_at が NULL）のメンバー」であることを前提とする。
 * 脱退・除名済みのユーザーは非メンバーとして扱う。
 */
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return $group->isActiveMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * グループ情報（名称・説明・画像）の編集。責任者以上。
     */
    public function update(User $user, Group $group): bool
    {
        return $this->roleIsAtLeast($user, $group, GroupRole::Responsible);
    }

    /**
     * グループの削除。最高責任者のみ。
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->roleIsAtLeast($user, $group, GroupRole::HighestResponsible);
    }

    /**
     * メンバーの招待・招待の取消。責任者以上。
     */
    public function invite(User $user, Group $group): bool
    {
        return $this->roleIsAtLeast($user, $group, GroupRole::Responsible);
    }

    /**
     * 役割の変更。最高責任者のみ。
     */
    public function manageRoles(User $user, Group $group): bool
    {
        return $this->roleIsAtLeast($user, $group, GroupRole::HighestResponsible);
    }

    /**
     * 除名。責任者は一般メンバーのみ、最高責任者は全ての役割を除名できる。
     * 「最低1人」ルールは GroupMemberService で別途強制する。
     */
    public function removeMember(User $user, Group $group, User $target): bool
    {
        $operatorRole = $group->roleOf($user);
        $targetRole = $group->roleOf($target);

        if ($operatorRole === null || $targetRole === null) {
            return false;
        }

        if ($user->is($target)) {
            return false;
        }

        return match ($operatorRole) {
            GroupRole::HighestResponsible => true,
            GroupRole::Responsible => $targetRole === GroupRole::Member,
            GroupRole::Member => false,
        };
    }

    /**
     * 脱退。在籍中であること。「最低1人」ルールは別途強制する。
     */
    public function leave(User $user, Group $group): bool
    {
        return $group->isActiveMember($user);
    }

    private function roleIsAtLeast(User $user, Group $group, GroupRole $required): bool
    {
        $role = $group->roleOf($user);

        return $role !== null && $role->isAtLeast($required);
    }
}
