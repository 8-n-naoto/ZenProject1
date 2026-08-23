<?php

namespace App\Http\Controllers\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Services\CatalogService;
use App\Services\ChangeHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ChangeHistoryService $history,
    ) {}

    public function create(EventCircle $circle): View
    {
        $this->authorize('update', $circle);

        return view('products.create', ['circle' => $circle, 'event' => $circle->event]);
    }

    public function store(StoreProductRequest $request, EventCircle $circle): RedirectResponse
    {
        $this->authorize('update', $circle);

        $product = $this->catalog->createProduct($circle, array_merge($request->validated(), [
            'image' => $request->file('image'),
        ]));

        $this->history->record(auth()->user(), $product, 'product.created', ['name' => $product->name], $circle->event->group, $circle->event);

        return redirect()->route('circles.show', $circle)->with('status', '商品を登録しました。');
    }

    public function edit(EventProduct $product): View
    {
        $this->authorize('update', $product->eventCircle);

        return view('products.edit', [
            'product' => $product,
            'circle' => $product->eventCircle,
            'event' => $product->event,
        ]);
    }

    public function update(UpdateProductRequest $request, EventProduct $product): RedirectResponse
    {
        $this->authorize('update', $product->eventCircle);

        $this->catalog->updateProduct($product, array_merge($request->validated(), [
            'image' => $request->file('image'),
            'remove_image' => $request->boolean('remove_image'),
        ]));

        $this->history->record(auth()->user(), $product, 'product.updated', ['name' => $product->fresh()->name], $product->event->group, $product->event);

        return redirect()->route('circles.show', $product->eventCircle)->with('status', '商品情報を更新しました。');
    }

    public function destroy(EventProduct $product): RedirectResponse
    {
        $this->authorize('update', $product->eventCircle);

        $circle = $product->eventCircle;

        try {
            $this->catalog->deleteProduct($product);
        } catch (BusinessRuleException $e) {
            return back()->withErrors($e->toErrorBag());
        }

        $this->history->record(auth()->user(), $circle, 'product.deleted', ['name' => $product->name], $circle->event->group, $circle->event);

        return redirect()->route('circles.show', $circle)->with('status', '商品を削除しました。');
    }
}
