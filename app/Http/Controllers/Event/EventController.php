<?php

namespace App\Http\Controllers\Event;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Event;
use App\Models\Group;
use App\Services\ApprovalService;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly ApprovalService $approvals,
    ) {}

    public function index(Group $group): View
    {
        $this->authorize('viewAny', [Event::class, $group]);

        $events = $group->events()
            ->with('days')
            ->withCount('participants')
            ->orderByDesc('starts_at')
            ->get();

        return view('events.index', [
            'group' => $group,
            'upcoming' => $events->reject(fn (Event $e) => $e->status->isCompleted())->values(),
            'past' => $events->filter(fn (Event $e) => $e->status->isCompleted())->values(),
        ]);
    }

    public function create(Group $group): View
    {
        $this->authorize('create', [Event::class, $group]);

        return view('events.create', ['group' => $group]);
    }

    public function store(StoreEventRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('create', [Event::class, $group]);

        try {
            $event = $this->events->create($group, $request->user(), $request->validated());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors($e->toErrorBag());
        }

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'イベントを作成しました。内容を確認して「受付を開始」してください。');
    }

    /**
     * 既存イベントを複製する入力画面。
     */
    public function duplicateForm(Event $event): View
    {
        $this->authorize('view', $event);
        $this->authorize('create', [Event::class, $event->group]);

        $event->load(['days', 'eventCircles.eventProducts']);

        return view('events.duplicate', ['source' => $event]);
    }

    /**
     * 既存イベントのサークル・商品を引き継いで新しいイベントを作る。
     */
    public function duplicate(StoreEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);
        $this->authorize('create', [Event::class, $event->group]);

        try {
            $created = $this->events->duplicate($event, $request->user(), $request->validated());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors($e->toErrorBag());
        }

        return redirect()
            ->route('events.show', $created)
            ->with('status', 'サークルと商品を引き継いでイベントを作成しました。');
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        $event->load(['group', 'days', 'participants', 'creator']);

        return view('events.show', [
            'event' => $event,
            'isParticipant' => $event->isParticipant(auth()->user()),
            'role' => $event->group->roleOf(auth()->user()),
            'summary' => app(\App\Services\EventSummaryService::class)->forUser($event, auth()->user()),
        ]);
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        $event->load('days');

        return view('events.edit', ['event' => $event]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->events->update($event, $request->validated());

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'イベント情報を更新しました。');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $group = $event->group;
        $event->days()->delete();
        $event->participants()->detach();
        $event->delete();

        return redirect()
            ->route('events.index', $group)
            ->with('status', 'イベントを削除しました。');
    }

    /**
     * 状態をひとつ進める。
     */
    public function advance(Event $event): RedirectResponse
    {
        $this->authorize('advance', $event);

        // 「確定」と「精算の完了」は承認フローを経由する。
        $actionType = match ($event->status) {
            EventStatus::Accepting => ApprovalActionType::FixEvent,
            EventStatus::Settling => ApprovalActionType::CompleteEvent,
            default => null,
        };

        try {
            if ($actionType !== null) {
                $approval = $this->approvals->request($event, auth()->user(), $actionType);

                if ($approval->status === ApprovalStatus::Pending) {
                    return back()->with(
                        'status',
                        '「'.$actionType->label().'」の承認を申請しました。責任者の過半数の承認で実行されます。'
                    );
                }

                return back()->with('status', 'イベントの状態を「'.$event->fresh()->status->label().'」に変更しました。');
            }

            $next = $this->events->advance($event, auth()->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', 'イベントの状態を「'.$next->label().'」に変更しました。');
    }

    /**
     * 状態をひとつ戻す。
     */
    public function revert(Event $event): RedirectResponse
    {
        $this->authorize('revert', $event);

        try {
            // 完了イベントの再オープンは承認フローを経由する。
            if ($event->status === EventStatus::Completed) {
                $approval = $this->approvals->request($event, auth()->user(), ApprovalActionType::ReopenEvent);

                if ($approval->status === ApprovalStatus::Pending) {
                    return back()->with('status', '完了イベントの再オープンを申請しました。');
                }

                return back()->with('status', 'イベントを再オープンしました。');
            }

            $previous = $this->events->revert($event);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        return back()->with('status', 'イベントの状態を「'.$previous->label().'」に戻しました。');
    }
}
