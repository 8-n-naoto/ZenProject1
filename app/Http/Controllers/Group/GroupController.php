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
        $group->load([
            'members' => function ($query) {
                $query->withPivot(['role', 'joined_at', 'left_at']);
            },
        ]);

        return view('groups.show', compact('group'));
    }
}
