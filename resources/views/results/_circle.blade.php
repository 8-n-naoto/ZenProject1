<x-card class="mb-2" :title="$sharedPurchase->eventCircle->display_name" :subtitle="$sharedPurchase->eventCircle->locationLabel()">
    @forelse ($sharedPurchase->items as $item)
        @php $result = $item->purchaseResult; @endphp
        <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm">{{ $item->eventProduct->name }}</p>
                <p class="text-xs text-slate-500">
                    予定 {{ $item->planned_quantity }}点
                    @if ($result)
                        ・購入 {{ $result->purchased_quantity }}点
                    @endif
                </p>
            </div>

            @if ($result)
                <x-badge :class="$result->status->badgeClass()">{{ $result->status->label() }}</x-badge>
            @else
                <x-badge class="bg-slate-100 text-slate-500">未登録</x-badge>
            @endif

            @can('recordSharedResult', $item)
                <a href="{{ route('results.edit', $item) }}" class="-my-2 inline-flex min-h-10 shrink-0 items-center px-2 text-xs font-semibold text-sky-600">
                    {{ $result ? '修正' : '登録' }}
                </a>
            @endcan
        </div>
    @empty
        <p class="py-3 text-center text-xs text-slate-500">購入する商品がありません。</p>
    @endforelse
</x-card>
