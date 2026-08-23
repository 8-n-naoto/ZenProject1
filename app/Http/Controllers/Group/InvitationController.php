<?php

namespace App\Http\Controllers\Group;

use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Invitation;
use App\Services\GroupMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly GroupMemberService $members) {}

    public function index(): View
    {
        $invitations = Invitation::query()
            ->with(['group', 'inviter'])
            ->where('invited_user_id', auth()->id())
            ->latest()
            ->get();

        return view('invitations.index', [
            'pending' => $invitations->filter(fn (Invitation $i) => $i->isPending())->values(),
            'history' => $invitations->reject(fn (Invitation $i) => $i->isPending())->values(),
        ]);
    }

    public function accept(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->invited_user_id === auth()->id(), 403);

        if (! $invitation->isPending()) {
            return back()->withErrors(['invitation' => 'この招待はすでに処理されています。']);
        }

        try {
            $this->members->join($invitation->group, auth()->user(), GroupRole::Member);
        } catch (BusinessRuleException $e) {
            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'responded_at' => now(),
            ]);

            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        $invitation->update([
            'status' => InvitationStatus::Accepted,
            'responded_at' => now(),
        ]);

        return redirect()
            ->route('groups.show', $invitation->group)
            ->with('status', 'グループへの参加を承認しました。');
    }

    public function decline(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->invited_user_id === auth()->id(), 403);

        if (! $invitation->isPending()) {
            return back()->withErrors(['invitation' => 'この招待はすでに処理されています。']);
        }

        $invitation->update([
            'status' => InvitationStatus::Declined,
            'responded_at' => now(),
        ]);

        return back()->with('status', '招待を辞退しました。');
    }

    /**
     * 送信済みの招待を取り消す（責任者以上）。
     */
    public function cancel(Group $group, Invitation $invitation): RedirectResponse
    {
        $this->authorize('invite', $group);

        abort_unless($invitation->group_id === $group->id, 404);

        if (! $invitation->isPending()) {
            return back()->withErrors(['invitation' => 'この招待はすでに処理されています。']);
        }

        $invitation->update([
            'status' => InvitationStatus::Cancelled,
            'responded_at' => now(),
        ]);

        return back()->with('status', '招待を取り消しました。');
    }
}
