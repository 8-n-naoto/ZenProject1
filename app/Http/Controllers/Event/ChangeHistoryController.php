<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Policies\EventPolicy;
use Illuminate\View\View;

class ChangeHistoryController extends Controller
{
    public function index(Event $event): View
    {
        abort_unless(app(EventPolicy::class)->view(auth()->user(), $event), 403);

        $histories = $event->changeHistories()
            ->with('actor')
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(40);

        return view('histories.index', [
            'event' => $event,
            'histories' => $histories,
        ]);
    }
}
