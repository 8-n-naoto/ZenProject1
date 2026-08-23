<x-app-layout :title="$circle->display_name" :heading="$circle->display_name" :back="route('circles.index', $event)">
    <x-slot:actions>
        @if ($canEdit)
            <a href="{{ route('circles.edit', $circle) }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="サークルを編集">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-9 1l8.5-8.5a2.1 2.1 0 013 3L12 15l-4 1 1-4z"/></svg>
            </a>
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @if ($circle->mapImageUrl())
            @php $pin = $circle->mapPin(); @endphp
            <a href="{{ $circle->mapImageUrl() }}" target="_blank" rel="noopener" class="relative block overflow-hidden rounded-2xl bg-white shadow-sm">
                <img src="{{ $circle->mapImageUrl() }}" alt="配置マップ" class="w-full object-cover">
                @if ($pin)
                    <span class="absolute h-6 w-6 rounded-full border-2 border-white bg-rose-500 shadow"
                          style="left: {{ $pin['x'] }}%; top: {{ $pin['y'] }}%; transform: translate(-50%, -50%);"
                          aria-label="このサークルの位置"></span>
                @endif
            </a>
        @endif

        <x-card>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-xs text-slate-500">配置</dt>
                    <dd class="text-right">{{ $circle->locationLabel() }}</dd>
                </div>
                @if ($circle->circle?->website_url)
                    <div class="flex justify-between gap-3">
                        <dt class="text-xs text-slate-500">Webサイト</dt>
                        <dd class="min-w-0 truncate text-right">
                            <a href="{{ $circle->circle->website_url }}" target="_blank" rel="noopener" class="text-sky-600 underline">{{ $circle->circle->website_url }}</a>
                        </dd>
                    </div>
                @endif
                @if ($circle->circle?->description)
                    <div>
                        <dt class="text-xs text-slate-500">メモ</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-slate-600">{{ $circle->circle->description }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <section>
            <div class="mb-2 flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-slate-700">商品（{{ $circle->eventProducts->count() }}件）</h2>
                @if ($canEdit)
                    <a href="{{ route('products.create', $circle) }}" class="-mx-2 inline-flex min-h-10 items-center px-2 text-xs font-semibold text-sky-600">＋ 商品を追加</a>
                @endif
            </div>

            @forelse ($circle->eventProducts as $product)
                <div class="mb-2 rounded-2xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="h-14 w-14 shrink-0 rounded-xl object-cover">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $product->product?->description }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold tabular-nums">{{ $product->priceLabel() }}</p>
                            <x-badge :class="$product->status->badgeClass()">{{ $product->status->label() }}</x-badge>
                        </div>
                    </div>
                    @if ($canEdit)
                        <div class="mt-3 flex justify-end">
                            <a href="{{ route('products.edit', $product) }}" class="-mx-2 inline-flex min-h-10 items-center px-2 text-xs font-semibold text-sky-600">編集</a>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state message="商品が登録されていません">
                    @if ($canEdit)
                        <x-button :href="route('products.create', $circle)" size="sm">商品を追加する</x-button>
                    @endif
                </x-empty-state>
            @endforelse
        </section>
    </div>
</x-app-layout>
