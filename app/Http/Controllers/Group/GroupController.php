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
                    'role' => '最高責任者',
                    'joined_at' => now(),
                ],
                $responsible->id => [
                    'role' => '責任者',
                    'joined_at' => now(),
                ],
            ]);
        });

        return redirect()->route('groups.index')->with('status', 'グループを作成しました。');
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
            return back()->withErrors(['user' => '自分自身を招待することはできません。']);
        }

        if ($group->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['user' => 'このユーザーはすでにグループのメンバーです。']);
        }

        $pendingExists = $group->invitations()
            ->where('invited_user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return back()->withErrors(['user' => 'このユーザーにはすでに招待を送信しています。']);
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
            in_array($role, ['最高責任者', '責任者'], true),
            403
        );
    }
}