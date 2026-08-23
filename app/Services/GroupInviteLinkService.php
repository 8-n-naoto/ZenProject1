<?php

namespace App\Services;

use App\Enums\GroupRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Group;
use App\Models\GroupInviteLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 招待リンク（合い言葉）の発行・利用。
 *
 * 個別招待と違い「まだアカウントを持っていない人」も呼べる。
 */
class GroupInviteLinkService
{
    public function __construct(
        private readonly GroupMemberService $members,
        private readonly ChangeHistoryService $history,
    ) {}

    /**
     * 有効な招待リンクを取り出す。無ければ作る。
     */
    public function currentFor(Group $group): ?GroupInviteLink
    {
        return $group->inviteLinks()
            ->whereNull('revoked_at')
            ->latest('id')
            ->get()
            ->first(fn (GroupInviteLink $link) => $link->isUsable());
    }

    /**
     * 招待リンクを発行する（既存の有効なリンクは無効にする）。
     */
    public function issue(Group $group, User $actor, ?int $maxUses = null, ?int $expiresInDays = 7): GroupInviteLink
    {
        if ($maxUses !== null && ($maxUses < 1 || $maxUses > 100)) {
            throw new BusinessRuleException('使用回数は1〜100の範囲で指定してください。', 'max_uses');
        }

        if ($expiresInDays !== null && ($expiresInDays < 1 || $expiresInDays > 90)) {
            throw new BusinessRuleException('有効期間は1〜90日の範囲で指定してください。', 'expires_in_days');
        }

        return DB::transaction(function () use ($group, $actor, $maxUses, $expiresInDays) {
            $group->inviteLinks()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $link = $group->inviteLinks()->create([
                'created_by' => $actor->id,
                'token' => GroupInviteLink::generateToken(),
                'max_uses' => $maxUses,
                'expires_at' => $expiresInDays === null ? null : now()->addDays($expiresInDays),
            ]);

            $this->history->record($actor, $link, 'invite_link.issued', [
                'max_uses' => $maxUses,
                'expires_in_days' => $expiresInDays,
            ], $group);

            return $link;
        });
    }

    /**
     * 招待リンクを無効にする。
     */
    public function revoke(GroupInviteLink $link, User $actor): void
    {
        if ($link->isRevoked()) {
            throw new BusinessRuleException('この招待リンクはすでに無効です。', 'link');
        }

        $link->update(['revoked_at' => now()]);

        $this->history->record($actor, $link, 'invite_link.revoked', [], $link->group);
    }

    /**
     * トークンから有効な招待リンクを探す。
     */
    public function findUsable(string $token): ?GroupInviteLink
    {
        $link = GroupInviteLink::query()->with('group')->where('token', $token)->first();

        if ($link === null || $link->group === null || $link->group->trashed()) {
            return null;
        }

        return $link;
    }

    /**
     * 招待リンクを使ってグループに参加する。
     */
    public function join(GroupInviteLink $link, User $user): Group
    {
        $reason = $link->unusableReason();

        if ($reason !== null) {
            throw new BusinessRuleException($reason, 'link');
        }

        $group = $link->group;

        if ($group === null || $group->trashed()) {
            throw new BusinessRuleException('このグループは削除されています。', 'link');
        }

        if ($group->isActiveMember($user)) {
            throw new BusinessRuleException('すでにこのグループのメンバーです。', 'link');
        }

        DB::transaction(function () use ($link, $group, $user) {
            $this->members->join($group, $user, GroupRole::Member);

            // 招待中の個別招待が残っていたら、二重に返答を求めないよう受諾済みにする
            $group->pendingInvitations()
                ->where('invited_user_id', $user->id)
                ->update(['status' => \App\Enums\InvitationStatus::Accepted->value, 'responded_at' => now()]);

            $link->increment('used_count');

            $this->history->record($user, $group, 'member.joined_by_link', [
                'target' => $user->displayName(),
            ], $group);
        });

        return $group->fresh();
    }
}
