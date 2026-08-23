<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $unreadOnly = $request->boolean('unread');

        $notifications = Notification::query()
            ->where('user_id', auth()->id())
            ->when($unreadOnly, fn ($query) => $query->unread())
            ->latest('notified_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $this->notifications->unreadCount(auth()->user()),
        ]);
    }

    public function markAllAsRead(): RedirectResponse
    {
        $this->notifications->markAllAsRead(auth()->user());

        return back()->with('status', 'すべて既読にしました。');
    }
}
