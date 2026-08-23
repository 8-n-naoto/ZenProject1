<?php

namespace App\Http\Controllers\Event;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventMemberController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    /**
     * 参加者の管理画面（責任者向け）。
     */
    public function index(Event $event): View
    {
        $this->authorize('manageParticipants', $event);

        $event->load('participants');
        $participantIds = $event->participants->pluck('id');

        $candidates = $event->group->activeMembers()
            ->whereNotIn('users.id', $participantIds)
            ->get();

        return view('events.members', [
            'event' => $event,
            'candidates' => $candidates,
        ]);
    }

    public function join(Event $event): RedirectResponse
    {
        $this->authorize('join', $event);

        try {
            $this->events->join($event, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', 'イベントに参加しました。');
    }

    public function leave(Event $event): RedirectResponse
    {
        $this->authorize('leave', $event);

        try {
            $this->events->leave($event, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', 'イベントの参加を取りやめました。');
    }

    /**
     * 責任者による代理追加。
     */
    public function add(Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageParticipants', $event);

        abort_unless($event->group->isActiveMember($user), 403);

        try {
            $this->events->join($event, $user);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $user->user_id.' さんを参加者に追加しました。');
    }

    /**
     * 責任者による参加者の削除。
     */
    public function remove(Event $event, User $user): RedirectResponse
    {
        $this->authorize('manageParticipants', $event);

        try {
            $this->events->leave($event, $user);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', $user->user_id.' さんを参加者から外しました。');
    }
}
