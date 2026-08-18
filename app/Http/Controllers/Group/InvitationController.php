<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(): View
    {
        $invitations = Invitation::query()
            ->with(['group', 'inviter'])
            ->where('invited_user_id', auth()->id())
            ->latest()
            ->get();

        return view('invitations.index', compact('invitations'));
    }

    public function accept(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->invited_user_id === auth()->id(), 403);

        if ($invitation->status !== 'pending') {
            return back()->withErrors(['invitation' => 'この招待はすでに処理されています。']);
        }

        $group = $invitation->group;

        if ($group->members()->where('users.id', auth()->id())->exists()) {
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            return back()->withErrors(['invitation' => 'すでにこのグループのメンバーです。']);
        }

        $group->members()->attach(auth()->id(), [
            'role' => '一般メンバー',
            'joined_at' => now(),
        ]);

        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return back()->with('status', 'グループへの参加を承認しました。');
    }

    public function decline(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->invited_user_id === auth()->id(), 403);

        if ($invitation->status !== 'pending') {
            return back()->withErrors(['invitation' => 'この招待はすでに処理されています。']);
        }

        $invitation->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return back()->with('status', '招待を辞退しました。');
    }
}