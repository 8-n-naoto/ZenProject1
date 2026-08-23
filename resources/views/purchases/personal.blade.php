<x-app-layout title="購入希望" heading="購入希望" :back="route('events.show', $event)">
    <form method="POST" action="{{ route('purchases.personal.update', $event) }}" class="space-y-4" id="wish-form"
          data-offline-guard="wishes:{{ $event->id }}">
        @csrf
        @method('PATCH')

        {{-- 合計と検索 --}}
        <div class="sticky top-14 z-30 -mx-3 space-y-2 border-b border-slate-200 bg-white px-3 py-3">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-semibold">予定金額</span>
                <span id="wish-total" class="text-lg font-bold tabular-nums">¥{{ number_format($totalAmount) }}</span>
            </div>

            @if ($budget['budget'] !== null)
                <div class="flex items-baseline justify-between">
                    <span class="text-xs {{ $budget['isOver'] ? 'text-rose-700' : 'text-slate-500' }}">
                        残り（予算 ¥{{ number_format($budget['budget']) }}）
                    </span>
                    <span class="text-sm font-bold tabular-nums {{ $budget['isOver'] ? 'text-rose-700' : 'text-emerald-600' }}">
                        @if ($budget['isOver'])
                            −¥{{ number_format(abs($budget['remaining'])) }}
                        @else
                            ¥{{ number_format($budget['remaining']) }}
                        @endif
                    </span>
                </div>
            @endif

            @if ($circles->isNotEmpty())
                <label for="wish-filter" class="sr-only">サークル名・商品名でしぼり込む</label>
                <input type="search" id="wish-filter" placeholder="サークル名・商品名でしぼり込む"
                       class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @endif
        </div>

        @unless ($canEdit)
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                受付中のイベントに参加している場合のみ、購入希望を変更できます。
            </div>
        @endunless

        @php $hasProducts = $circles->contains(fn ($c) => $c->eventProducts->isNotEmpty()); @endphp

        @unless ($hasProducts)
            <x-empty-state message="登録された商品がまだありません"
                           hint="責任者がサークルと商品を登録すると、ここで購入希望を入力できます。" />
        @endunless

        @forelse ($circles as $circle)
            @continue ($circle->eventProducts->isEmpty())
            <x-card class="wish-circle"
                    data-search="{{ $circle->display_name }} {{ $circle->booth }} {{ $circle->eventProducts->pluck('name')->implode(' ') }}"
                    :title="$circle->display_name" :subtitle="$circle->locationLabel()">
                <div class="space-y-3">
                    @foreach ($circle->eventProducts as $product)
                        @php $current = $mine->get($product->id); @endphp
                        <div class="flex items-center gap-3">
                            @if ($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm">{{ $product->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $product->priceLabel() }}
                                    @unless ($product->status->isPurchasable())
                                        ・<span class="text-rose-600">{{ $product->status->label() }}</span>
                                    @endunless
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" class="wish-step h-9 w-9 rounded-lg bg-slate-100 text-lg font-bold text-slate-600"
                                        data-step="-1" aria-label="減らす" @disabled(! $canEdit)>−</button>
                                <input type="number" min="0" max="999" inputmode="numeric"
                                       aria-label="{{ $product->name }} の希望数"
                                       name="quantities[{{ $product->id }}]"
                                       value="{{ old('quantities.' . $product->id, $current?->planned_quantity ?? 0) }}"
                                       data-price="{{ $product->price }}"
                                       @disabled(! $canEdit)
                                       class="wish-input w-14 rounded-xl border border-slate-300 px-1 py-2 text-center text-base tabular-nums">
                                <button type="button" class="wish-step h-9 w-9 rounded-lg bg-slate-100 text-lg font-bold text-slate-600"
                                        data-step="1" aria-label="増やす" @disabled(! $canEdit)>＋</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @empty
            <x-empty-state message="商品が登録されていません" hint="先にサークルと商品を登録してください。">
                <x-button :href="route('circles.index', $event)" size="sm">サークル・商品へ</x-button>
            </x-empty-state>
        @endforelse

        <p id="wish-empty" class="hidden rounded-2xl bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm">
            該当するサークル・商品がありません
        </p>

        @if ($canEdit && $circles->isNotEmpty())
            <x-button class="w-full" size="lg">購入希望を保存</x-button>
        @endif
    </form>

    <div class="mt-4">
        <x-budget-bar :event="$event" :budget="$budget" />
    </div>

    @if ($canEdit && $sourceEvents->isNotEmpty())
        <details class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">前回の購入希望を取り込む</summary>
            <p class="mt-2 text-xs text-slate-500">
                サークル名と商品名が一致する商品にだけ反映します。すでに数量を入れた商品は変更しません。
            </p>
            <form method="POST" action="{{ route('purchases.personal.copy', $event) }}" class="mt-3 space-y-2">
                @csrf
                <label for="source_event_id" class="sr-only">取り込み元イベント</label>
                <select name="source_event_id" id="source_event_id"
                        class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($sourceEvents as $sourceEvent)
                        <option value="{{ $sourceEvent->id }}">{{ $sourceEvent->name }}</option>
                    @endforeach
                </select>
                <x-button variant="secondary" class="w-full">取り込む</x-button>
            </form>
        </details>
    @endif

    <script>
    (function () {
        var form = document.getElementById('wish-form');
        if (!form) { return; }

        var totalLabel = document.getElementById('wish-total');
        var inputs = Array.prototype.slice.call(form.querySelectorAll('.wish-input'));

        function format(value) {
            return '¥' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function updateTotal() {
            var total = inputs.reduce(function (sum, input) {
                var quantity = parseInt(input.value || '0', 10);
                var price = parseInt(input.dataset.price || '0', 10);
                return sum + (isNaN(quantity) ? 0 : Math.max(0, quantity)) * price;
            }, 0);
            totalLabel.textContent = format(total);
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', updateTotal);
        });

        form.addEventListener('click', function (event) {
            var button = event.target.closest('.wish-step');
            if (!button) { return; }

            var wrapper = button.parentElement;
            var input = wrapper.querySelector('.wish-input');
            if (!input || input.disabled) { return; }

            var step = parseInt(button.dataset.step, 10);
            var next = Math.min(999, Math.max(0, parseInt(input.value || '0', 10) + step));
            input.value = next;
            updateTotal();
        });

        // しぼり込み
        var filter = document.getElementById('wish-filter');
        var empty = document.getElementById('wish-empty');

        if (filter) {
            filter.addEventListener('input', function () {
                var keyword = filter.value.trim().toLowerCase();
                var visible = 0;

                form.querySelectorAll('.wish-circle').forEach(function (card) {
                    var haystack = (card.dataset.search || '').toLowerCase();
                    var match = keyword === '' || haystack.indexOf(keyword) !== -1;
                    card.classList.toggle('hidden', !match);
                    if (match) { visible++; }
                });

                empty.classList.toggle('hidden', visible > 0);
            });
        }

        updateTotal();
    })();
    </script>
</x-app-layout>
