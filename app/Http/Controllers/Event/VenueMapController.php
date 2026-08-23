<?php

namespace App\Http\Controllers\Event;

use App\Enums\SelloutRisk;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\PersonalPurchase;
use App\Policies\PurchasePolicy;
use App\Services\ImageStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 会場図の上にサークルをプロットして見せる画面。
 */
class VenueMapController extends Controller
{
    public function __construct(private readonly ImageStorageService $images) {}

    public function show(Event $event): View
    {
        abort_unless(app(PurchasePolicy::class)->view(auth()->user(), $event), 403);

        $event->loadMissing(['eventCircles.eventProducts', 'eventCircles.sharedPurchase.items.purchaseResult']);

        $userId = auth()->id();

        // 自分の購入希望があるサークル（＝自分に関係するサークル）
        $myCircleIds = PersonalPurchase::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->with('eventProduct')
            ->get()
            ->map(fn (PersonalPurchase $purchase) => $purchase->eventProduct?->event_circle_id)
            ->filter()
            ->unique()
            ->all();

        $pins = [];

        foreach ($event->eventCircles as $circle) {
            $pin = $circle->venueMapPin();

            if ($pin === null) {
                continue;
            }

            $items = $circle->sharedPurchase?->items ?? collect();
            $total = $items->count();
            $done = $items->filter(fn ($item) => $item->purchaseResult !== null)->count();

            $pins[] = [
                'circle' => $circle,
                'x' => $pin['x'],
                'y' => $pin['y'],
                'isMine' => in_array($circle->id, $myCircleIds, true),
                'total' => $total,
                'done' => $done,
                // 明細があって全部登録済みなら「購入済」
                'isDone' => $total > 0 && $done === $total,
            ];
        }

        return view('events.map', [
            'event' => $event,
            'pins' => $pins,
            'unplaced' => $event->eventCircles->filter(fn (EventCircle $c) => $c->venueMapPin() === null)->values(),
            'canEdit' => auth()->user()->can('update', $event),
            'risks' => SelloutRisk::cases(),
        ]);
    }

    /**
     * 会場図の画像を差し替える。
     */
    public function updateImage(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'map_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_map_image' => ['nullable', 'boolean'],
        ], [], ['map_image' => '会場図']);

        $result = $this->images->sync(
            $request->file('map_image'),
            $event->map_image_path,
            'venue-maps',
            $request->boolean('remove_map_image'),
        );

        if ($result['changed']) {
            $event->update(['map_image_path' => $result['path']]);

            // 画像が変わると座標の意味が失われるので、置いた位置も消す
            $event->eventCircles()->update(['venue_map_x' => null, 'venue_map_y' => null]);
        }

        return back()->with('status', $result['path'] === null ? '会場図を削除しました。' : '会場図を更新しました。');
    }

    /**
     * サークルの位置を会場図上に置く。
     */
    public function placeCircle(Request $request, Event $event, EventCircle $circle): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($circle->event_id === $event->id, 404);

        $validated = $request->validate([
            'venue_map_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'venue_map_y' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], [
            'venue_map_x' => '会場図上の位置',
            'venue_map_y' => '会場図上の位置',
        ]);

        $x = $validated['venue_map_x'] ?? null;
        $y = $validated['venue_map_y'] ?? null;

        $circle->update([
            'venue_map_x' => $x === null || $y === null ? null : $x,
            'venue_map_y' => $x === null || $y === null ? null : $y,
        ]);

        return back()->with(
            'status',
            $x === null ? $circle->display_name.' の位置を消しました。' : $circle->display_name.' の位置を置きました。'
        );
    }
}
