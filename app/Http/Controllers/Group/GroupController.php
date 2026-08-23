<?php

namespace App\Http\Controllers\Group;

use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Requests\Group\UpdateMemberRoleRequest;
use App\Models\Group;
use App\Models\User;
use App\Services\ChangeHistoryService;
use App\Services\GroupMemberService;
use App\Services\NotificationService;
use App\Support\SearchKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupMemberService $members,
        private readonly NotificationService $notifications,
        private readonly ChangeHistoryService $history,
    ) {}

    public function index(): View
    {
        $groups = auth()->user()
            ->activeGroups()
            ->withCount(['activeMembers as active_members_count'])
            ->latest('groups.created_at')
            ->get();

        return view('groups.index', compact('groups'));
    }

    public function create(): View
    {
        $this->authorize('create', Group::class);

        return view('groups.create');
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $group = DB::transaction(function () use ($request) {
            $group = Group::create($request->safe()->only(['name', 'description']));

            // 作成者は最高責任者として自動参加する。
            $group->members()->attach($request->user()->id, [
                'role' => GroupRole::HighestResponsible->value,
                'joined_at' => now(),
            ]);

            return $group;
        });

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'グループを作成しました。メンバーを招待して責任者を任命してください。');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);

        $group->load([
            'activeMembers' => fn ($query) => $query->orderByPivot('joined_at'),
        ]);

        // 最高責任者 → 責任者 → 一般メンバー の順に並べる
        $group->setRelation('activeMembers', $group->activeMembers->sortByDesc(
            fn ($member) => GroupRole::tryFrom((string) $member->pivot->role)?->rank() ?? 0
        )->values());

        $myRole = $group->roleOf(auth()->user());
        $pendingInvitations = $group->pendingInvitations()->with('invitedUser')->latest()->get();

        $events = $group->events()
            ->active()
            ->with('days')
            ->withCount('participants')
            ->orderBy('starts_at')
            ->limit(3)
            ->get();

        return view('groups.show', [
            'group' => $group,
            'myRole' => $myRole,
            'events' => $events,
            'pendingInvitations' => $pendingInvitations,
            'needsResponsible' => $group->countActiveWithRole(GroupRole::Responsible) === 0,
            'inviteLink' => app(\App\Services\GroupInviteLinkService::class)->currentFor($group),
        ]);
    }

    public function edit(Group $group): View
    {
        $this->authorize('update', $group);

        return view('groups.edit', compact('group'));
    }

    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $attributes = $request->safe()->only(['name', 'description']);

        if ($request->boolean('remove_image')) {
            $this->deleteImage($group);
            $attributes['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($group);
            $attributes['image_path'] = $request->file('image')->store('groups', 'public');
        }

        $group->update($attributes);

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'グループ情報を更新しました。');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        if ($group->events()->withTrashed()->exists()) {
            return back()->withErrors([
                'group' => 'イベントが登録されているグループは削除できません。先にイベントを整理してください。',
            ]);
        }

        DB::transaction(function () use ($group) {
            $group->members()->updateExistingPivot(
                $group->activeMembers()->pluck('users.id')->all(),
                ['left_at' => now()]
            );
            $group->pendingInvitations()->update([
                'status' => \App\Enums\InvitationStatus::Cancelled->value,
                'responded_at' => now(),
            ]);
            // 画像ファイルが残り続けないよう、レコードと一緒に消す
            $this->deleteImage($group);
            $group->delete();
        });

        return redirect()
            ->route('groups.index')
            ->with('status', 'グループを削除しました。');
    }

    private function deleteImage(Group $group): void
    {
        if ($group->image_path !== null && Storage::disk('public')->exists($group->image_path)) {
            Storage::disk('public')->delete($group->image_path);
        }
    }

    public function searchUsers(Request $request, Group $group): View
    {
        $this->authorize('invite', $group);

        $keyword = SearchKeyword::normalize($request->input('q'));
        $users = collect();

        // 1文字だと総当たりで登録者を一覧できてしまうため、2文字以上を求める
        if (mb_strlen($keyword) >= 2) {
            $activeMemberIds = $group->activeMembers()->pluck('users.id');
            $pendingInvitedIds = $group->pendingInvitations()->pluck('invited_user_id');

            $users = User::query()
                ->where('user_id', 'like', SearchKeyword::startsWith($keyword))
                ->whereNotIn('id', $activeMemberIds)
                ->whereNotIn('id', $pendingInvitedIds)
                ->orderBy('user_id')
                ->limit(20)
                ->get();
        }

        return view('groups.search-users', compact('group', 'keyword', 'users'));
    }

    public function invite(Group $group, User $user): RedirectResponse
    {
        $this->authorize('invite', $group);

        if ($group->isActiveMember($user)) {
            return back()->withErrors(['user' => 'このユーザーはすでにグループのメンバーです。']);
        }

        $pendingExists = $group->pendingInvitations()
            ->where('invited_user_id', $user->id)
            ->exists();

        if ($pendingExists) {
            return back()->withErrors(['user' => 'このユーザーにはすでに招待を送信しています。']);
        }

        $invitation = $group->invitations()->create([
            'invited_user_id' => $user->id,
            'invited_by' => auth()->id(),
            'status' => InvitationStatus::Pending,
        ]);

        $this->notifications->notify(
            [$user->id],
            'invitation.received',
            null,
            ['group' => $group->name, 'inviter' => auth()->user()->displayName()]
        );

        $this->history->record(auth()->user(), $invitation, 'invitation.sent', ['target' => $user->user_id], $group);

        return redirect()
            ->route('groups.search-users', ['group' => $group, 'q' => $user->user_id])
            ->with('status', $user->user_id.' さんに招待を送信しました。');
    }

    public function updateMemberRole(
        UpdateMemberRoleRequest $request,
        Group $group,
        User $user
    ): RedirectResponse {
        $this->authorize('manageRoles', $group);

        $newRole = GroupRole::from($request->validated('role'));

        try {
            $changed = $this->members->changeRole($group, $user, $newRole);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        if (! $changed) {
            return back()->with('status', '役割に変更はありません。');
        }

        $this->history->record(auth()->user(), $group, 'member.role_changed', [
            'target' => $user->displayName(),
            'to' => $newRole->label(),
        ], $group);

        return back()->with(
            'status',
            $user->user_id.' さんの役割を「'.$newRole->label().'」に変更しました。'
        );
    }

    public function leave(Group $group): RedirectResponse
    {
        $this->authorize('leave', $group);

        try {
            $this->members->leave($group, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $group, 'member.left', [], $group);

        return redirect()
            ->route('groups.index')
            ->with('status', 'グループから脱退しました。');
    }

    public function removeMember(Group $group, User $user): RedirectResponse
    {
        $this->authorize('removeMember', [$group, $user]);

        try {
            $this->members->remove($group, $user);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $group, 'member.removed', ['target' => $user->displayName()], $group);

        return back()->with('status', $user->user_id.' さんをグループから除名しました。');
    }
}
