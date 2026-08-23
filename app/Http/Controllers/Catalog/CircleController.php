<?php

namespace App\Http\Controllers\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCircleRequest;
use App\Http\Requests\Catalog\UpdateCircleRequest;
use App\Models\Event;
use App\Models\EventCircle;
use App\Services\BulkCatalogImporter;
use App\Services\CatalogService;
use App\Services\ChangeHistoryService;
use App\Support\BoothSorter;
use App\Support\SearchKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CircleController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ChangeHistoryService $history,
    ) {}

    public function index(Request $request, Event $event): View
    {
        $this->authorize('viewAny', [EventCircle::class, $event]);

        $keyword = SearchKeyword::normalize($request->input('q'));
        $sort = in_array($request->input('sort'), ['booth', 'name', 'newest'], true)
            ? $request->input('sort')
            : 'booth';

        $circles = $event->eventCircles()
            ->with(['eventProducts' => fn ($query) => $query->orderBy('name')])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $pattern = SearchKeyword::contains($keyword);

                $query->where(function ($query) use ($pattern) {
                    $query->where('display_name', 'like', $pattern)
                        ->orWhere('booth', 'like', $pattern)
                        ->orWhereHas('eventProducts', fn ($q) => $q->where('name', 'like', $pattern));
                });
            })
            ->get();

        $circles = match ($sort) {
            'name' => $circles->sortBy('display_name', SORT_STRING),
            'newest' => $circles->sortByDesc('id'),
            default => $circles->sortBy(fn (EventCircle $circle) => BoothSorter::key($circle->booth)),
        };

        return view('circles.index', [
            'event' => $event,
            'circles' => $circles->values(),
            'keyword' => $keyword,
            'sort' => $sort,
            'canEdit' => auth()->user()->can('create', [EventCircle::class, $event]),
        ]);
    }

    /**
     * まとめて登録する入力画面。
     */
    public function bulkForm(Event $event): View
    {
        $this->authorize('create', [EventCircle::class, $event]);

        return view('circles.bulk', ['event' => $event]);
    }

    /**
     * テキストからサークル・商品をまとめて登録する。
     */
    public function bulkStore(Request $request, Event $event, BulkCatalogImporter $importer): RedirectResponse
    {
        $this->authorize('create', [EventCircle::class, $event]);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
        ], [], ['text' => '入力内容']);

        $parsed = $importer->parse($validated['text']);

        if ($parsed['errors'] !== []) {
            return back()->withInput()->withErrors(['text' => $parsed['errors']]);
        }

        $result = $importer->import($event, $parsed['rows']);

        $this->history->record(auth()->user(), $event, 'catalog.imported', [
            'circles' => $result['circles'],
            'products' => $result['products'],
        ], $event->group, $event);

        return redirect()
            ->route('circles.index', $event)
            ->with('status', sprintf(
                'サークル %d件・商品 %d件 を登録しました。%s',
                $result['circles'],
                $result['products'],
                $result['reused'] > 0 ? '（既存のサークル '.$result['reused'].'件に追加）' : ''
            ));
    }

    public function create(Event $event): View
    {
        $this->authorize('create', [EventCircle::class, $event]);

        return view('circles.create', ['event' => $event]);
    }

    public function store(StoreCircleRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('create', [EventCircle::class, $event]);

        try {
            $circle = $this->catalog->createCircle($event, $request->payload());
        } catch (BusinessRuleException $e) {
            return back()
                ->withInput()
                ->with('duplicate_warning', true)
                ->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $circle, 'circle.created', ['name' => $circle->display_name], $event->group, $event);

        return redirect()
            ->route('circles.show', $circle)
            ->with('status', 'サークルを登録しました。続けて商品を登録できます。');
    }

    public function show(EventCircle $circle): View
    {
        $this->authorize('view', $circle);

        $circle->load(['event', 'circle', 'eventProducts' => fn ($query) => $query->with('product')->orderBy('name')]);

        return view('circles.show', [
            'circle' => $circle,
            'event' => $circle->event,
            'canEdit' => auth()->user()->can('update', $circle),
        ]);
    }

    public function edit(EventCircle $circle): View
    {
        $this->authorize('update', $circle);

        $circle->load('circle');

        return view('circles.edit', ['circle' => $circle, 'event' => $circle->event]);
    }

    public function update(UpdateCircleRequest $request, EventCircle $circle): RedirectResponse
    {
        $this->authorize('update', $circle);

        try {
            $this->catalog->updateCircle($circle, $request->payload());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('duplicate_warning', true)->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $circle, 'circle.updated', ['name' => $circle->fresh()->display_name], $circle->event->group, $circle->event);

        return redirect()->route('circles.show', $circle)->with('status', 'サークル情報を更新しました。');
    }

    public function destroy(EventCircle $circle): RedirectResponse
    {
        $this->authorize('delete', $circle);

        $event = $circle->event;

        try {
            $this->catalog->deleteCircle($circle);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $event, 'circle.deleted', ['name' => $circle->display_name], $event->group, $event);

        return redirect()->route('circles.index', $event)->with('status', 'サークルを削除しました。');
    }
}
