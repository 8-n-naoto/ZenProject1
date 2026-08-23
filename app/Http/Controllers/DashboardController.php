<?php

namespace App\Http\Controllers;

use App\Enums\GroupRole;
use App\Models\Event;
use App\Services\SettlementService;
use App\Services\UserTaskService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserTaskService $tasks,
        private readonly SettlementService $settlements,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        $groups = $user->activeGroups()
            ->withCount(['activeMembers as active_members_count'])
            ->latest('groups.created_at')
            ->get();

        // 確定済以降のイベントは参加者と責任者以上しか閲覧できない（EventPolicy::view）。
        // 開けないイベントをホームに並べないよう、同じ条件で絞り込む。
        $events = Event::query()
            ->whereIn('group_id', $groups->pluck('id'))
            ->active()
            ->with(['days', 'group.activeMembers'])
            ->withCount('participants')
            ->orderBy('starts_at')
            ->limit(15)
            ->get()
            ->filter(fn (Event $event) => $user->can('view', $event))
            ->take(5)
            ->values();

        return view('dashboard', [
            'groups' => $groups,
            'events' => $events,
            'tasks' => $this->tasks->pendingFor($user),
            'pendingInvitationCount' => $user->pendingReceivedInvitations()->count(),
            'outstanding' => $this->settlements->outstandingFor($user),
            'roleOf' => fn ($group) => GroupRole::tryFrom((string) $group->pivot->role),
        ]);
    }
}
