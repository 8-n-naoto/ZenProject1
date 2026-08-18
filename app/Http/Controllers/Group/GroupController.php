<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        $groups = Group::query()
            ->whereHas('members', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->latest()
            ->get();

        return view('groups.index', compact('groups'));
    }

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'highest_responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id', 'different:highest_responsible_user_id'],
        ]);

        DB::transaction(function () use ($validated) {
            $highestResponsible = User::findOrFail($validated['highest_responsible_user_id']);
            $responsible = User::findOrFail($validated['responsible_user_id']);

            $group = Group::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $group->members()->attach([
                $highestResponsible->id => [
                    'role' => Group::ROLE_HIGHEST_RESPONSIBLE,
                    'joined_at' => now(),
                ],
                $responsible->id => [
                    'role' => Group::ROLE_RESPONSIBLE,
                    'joined_at' => now(),
                ],
            ]);
        });

        return redirect()
            ->route('groups.index')
            ->with('status', 'グループを作成しました。');
    }

    public function show(Group $group): View
    {
        $this->ensureMember($group);

        $group->load([
            'members' => function ($query) {
                $query->withPivot(['role', 'joined_at', 'left_at']);
            },
        ]);

        $myRole = $group->members
            ->firstWhere('id', auth()->id())
            ?->pivot
            ?->role;

        return view('groups.show', compact('group', 'myRole'));
    }

    public function searchUsers(Request $request, Group $group): View
    {
        $this->ensureInviter($group);

        $keyword = trim((string) $request->input('q', ''));
        $users = collect();

        if ($keyword !== '') {
            $memberIds = $group->members()->pluck('users.id');

            $pendingInvitationIds = $group->invitations()
                ->where('status', 'pending')
                ->pluck('invited_user_id');

            $users = User::query()
                ->where('user_id', 'like', $keyword . '%')
                ->where('id', '!=', auth()->id())
                ->whereNotIn('id', $memberIds)
                ->whereNotIn('id', $pendingInvitationIds)
                ->orderBy('user_id')
                ->limit(20)
                ->get();
        }

        return view('groups.search-users', compact('group', 'keyword', 'users'));
    }

    public function invite(Request $request, Group $group, User $user): RedirectResponse
    {
        $this->ensureInviter($group);

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'user' => '自分自身を招待することはできません。',
            ]);
        }

        if ($group->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors([
                'user' => 'このユーザーはすでにグループのメンバーです。',
            ]);
        }

        $pendingExists = $group->invitations()
            ->where('invited_user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return back()->withErrors([
                'user' => 'このユーザーにはすでに招待を送信しています。',
            ]);
        }

        $group->invitations()->create([
            'invited_user_id' => $user->id,
            'invited_by' => auth()->id(),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('groups.search-users', $group)
            ->with('status', $user->user_id . ' さんに招待を送信しました。');
    }

    public function updateMemberRole(
        Request $request,
        Group $group,
        User $user
    ): RedirectResponse {
        $this->ensureHighestResponsible($group);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', Group::ROLES)],
        ]);

        $member = $group->members()
            ->where('users.id', $user->id)
            ->first();

        if (!$member) {
            return back()->withErrors([
                'member' => '指定されたユーザーはこのグループのメンバーではありません。',
            ]);
        }

        $currentRole = $member->pivot->role;
        $newRole = $validated['role'];

        if ($currentRole === $newRole) {
            return back()->with('status', '役割に変更はありません。');
        }

        if (
            $currentRole === Group::ROLE_HIGHEST_RESPONSIBLE &&
            $newRole !== Group::ROLE_HIGHEST_RESPONSIBLE
        ) {
            $highestResponsibleCount = $group->members()
                ->wherePivot('role', Group::ROLE_HIGHEST_RESPONSIBLE)
                ->count();

            if ($highestResponsibleCount <= 1) {
                return back()->withErrors([
                    'member' => '最高責任者は最低1人必要です。',
                ]);
            }
        }

        if (
            $currentRole === Group::ROLE_RESPONSIBLE &&
            $newRole !== Group::ROLE_RESPONSIBLE
        ) {
            $responsibleCount = $group->members()
                ->wherePivot('role', Group::ROLE_RESPONSIBLE)
                ->count();

            if ($responsibleCount <= 1) {
                return back()->withErrors([
                    'member' => '責任者は最低1人必要です。',
                ]);
            }
        }

        $group->members()->updateExistingPivot($user->id, [
            'role' => $newRole,
        ]);

        return back()->with(
            'status',
            $member->user_id . ' さんの役割を「' . $newRole . '」に変更しました。'
        );
    }

    public function leave(Group $group): RedirectResponse
    {
        $member = $group->members()
            ->where('users.id', auth()->id())
            ->first();

        abort_unless($member, 403);

        $currentRole = $member->pivot->role;

        if ($currentRole === Group::ROLE_HIGHEST_RESPONSIBLE) {
            $count = $group->members()
                ->wherePivot('role', Group::ROLE_HIGHEST_RESPONSIBLE)
                ->wherePivotNull('left_at')
                ->count();

            if ($count <= 1) {
                return back()->withErrors([
                    'member' => '最高責任者は最低1人必要です。',
                ]);
            }
        }

        if ($currentRole === Group::ROLE_RESPONSIBLE) {
            $count = $group->members()
                ->wherePivot('role', Group::ROLE_RESPONSIBLE)
                ->wherePivotNull('left_at')
                ->count();

            if ($count <= 1) {
                return back()->withErrors([
                    'member' => '責任者は最低1人必要です。',
                ]);
            }
        }

        $group->members()->updateExistingPivot(auth()->id(), [
            'left_at' => now(),
        ]);

        return redirect()
            ->route('groups.index')
            ->with('status', 'グループから脱退しました。');
    }

    public function removeMember(Group $group, User $user): RedirectResponse
    {
        $operator = $group->members()
            ->where('users.id', auth()->id())
            ->first();

        abort_unless($operator, 403);

        $operatorRole = $operator->pivot->role;

        abort_unless(
            in_array($operatorRole, [
                Group::ROLE_HIGHEST_RESPONSIBLE,
                Group::ROLE_RESPONSIBLE,
            ], true),
            403
        );

        $target = $group->members()
            ->where('users.id', $user->id)
            ->first();

        if (!$target) {
            return back()->withErrors([
                'member' => '指定されたユーザーはこのグループのメンバーではありません。',
            ]);
        }

        $targetRole = $target->pivot->role;

        if (
            $operatorRole === Group::ROLE_RESPONSIBLE &&
            $targetRole !== Group::ROLE_MEMBER
        ) {
            abort(403);
        }

        if ($targetRole === Group::ROLE_HIGHEST_RESPONSIBLE) {
            $count = $group->members()
                ->wherePivot('role', Group::ROLE_HIGHEST_RESPONSIBLE)
                ->wherePivotNull('left_at')
                ->count();

            if ($count <= 1) {
                return back()->withErrors([
                    'member' => '最高責任者は最低1人必要です。',
                ]);
            }
        }


        $group->members()->updateExistingPivot($user->id, [
            'left_at' => now(),
        ]);

        return back()->with(
            'status',
            $user->user_id . ' さんをグループから除名しました。'
        );
    }




    private function ensureMember(Group $group): void
    {
        abort_unless(
            $group->members()->where('users.id', auth()->id())->exists(),
            403
        );
    }

    private function ensureInviter(Group $group): void
    {
        $role = $group->members()
            ->where('users.id', auth()->id())
            ->first()
            ?->pivot
            ?->role;

        abort_unless(
            in_array($role, [
                Group::ROLE_HIGHEST_RESPONSIBLE,
                Group::ROLE_RESPONSIBLE,
            ], true),
            403
        );
    }

    private function ensureHighestResponsible(Group $group): void
    {
        $role = $group->members()
            ->where('users.id', auth()->id())
            ->first()
            ?->pivot
            ?->role;

        abort_unless(
            $role === Group::ROLE_HIGHEST_RESPONSIBLE,
            403
        );
    }
}
