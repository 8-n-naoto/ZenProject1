@php
    $purchased = old('purchased_quantity', $result?->purchased_quantity ?? $item->planned_quantity);
@endphp

@php
    $backRoute = ($fromShoppingList ?? false) ? route('shopping.index', $event) : route('results.index', $event);
@endphp

<x-app-layout title="購入結果の登録" heading="購入結果の登録" :back="$backRoute">
    <form method="POST" action="{{ route('results.store', $item) }}" class="space-y-4" id="result-form"
          data-offline-guard="result:{{ $item->id }}">
        @csrf
        @if ($fromShoppingList ?? false)
            <input type="hidden" name="from" value="shopping">
        @endif

        <x-card :title="$item->eventProduct->name" :subtitle="$item->sharedPurchase->eventCircle->display_name">
            <dl class="mb-4 space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-xs text-slate-500">希望合計</dt>
                    <dd class="tabular-nums">{{ $totalDemand }}点</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-slate-500">共同購入リストの予定数</dt>
                    <dd class="tabular-nums">{{ $item->planned_quantity }}点</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-slate-500">設定価格</dt>
                    <dd class="tabular-nums">{{ $item->eventProduct->priceLabel() }}</dd>
                </div>
            </dl>

            <div class="space-y-4">
                <x-input name="purchased_quantity" label="実際に購入できた数" type="number" min="0" max="999"
                         inputmode="numeric" :value="$purchased" required id="purchased-quantity" />
                <x-input name="unit_price" label="実際の単価（円）" type="number" min="0" inputmode="numeric"
                         :value="$result?->unit_price" hint="値段が違った場合のみ入力してください。空欄なら設定価格を使います。" />
            </div>
        </x-card>

        <x-card title="希望した人" subtitle="不足した場合は、受け取れない数を入力してください。">
            <div class="space-y-3">
                @foreach ($demand as $wish)
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$wish->user" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $wish->user->displayName() }}</p>
                            <p class="text-xs text-slate-500">希望 {{ $wish->planned_quantity }}点</p>
                        </div>
                        <label class="shrink-0 text-right">
                            <span class="block text-xs text-slate-500">不足</span>
                            <input type="number" min="0" max="{{ $wish->planned_quantity }}" inputmode="numeric"
                                   aria-label="{{ $wish->user->displayName() }} の不足数"
                                   name="shortages[{{ $wish->user_id }}]"
                                   value="{{ old('shortages.' . $wish->user_id, $existingShortages[$wish->user_id] ?? 0) }}"
                                   class="shortage-input w-20 rounded-xl border border-slate-300 px-2 py-2 text-center text-base tabular-nums">
                        </label>
                    </div>
                @endforeach
            </div>

            <p id="shortage-hint" class="mt-3 text-xs text-slate-500"></p>

            @error('shortages')
                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </x-card>

        <x-card title="超過分の引取" subtitle="希望より多く買えた場合、引き取る人を選んでください。">
            <select name="excess_user_id" aria-label="超過分を引き取る人"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                <option value="">選択しない</option>
                @foreach ($demand as $wish)
                    <option value="{{ $wish->user_id }}" @selected(old('excess_user_id', $result?->excessTakeover?->user_id) == $wish->user_id)>
                        {{ $wish->user->displayName() }}
                    </option>
                @endforeach
            </select>
            @error('excess_user_id')
                <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </x-card>

        <x-button class="w-full" size="lg">購入結果を保存</x-button>
    </form>

    <script>
    (function () {
        const totalDemand = {{ $totalDemand }};
        const purchasedInput = document.getElementById('purchased-quantity');
        const hint = document.getElementById('shortage-hint');
        const shortageInputs = Array.from(document.querySelectorAll('.shortage-input'));

        function update() {
            const purchased = parseInt(purchasedInput.value || '0', 10);
            const shortage = Math.max(0, totalDemand - purchased);
            const excess = Math.max(0, purchased - totalDemand);
            const allocated = shortageInputs.reduce(function (sum, input) {
                return sum + parseInt(input.value || '0', 10);
            }, 0);

            if (excess > 0) {
                hint.textContent = '希望より ' + excess + '点 多く購入しています。引取者を選んでください。';
                return;
            }

            if (shortage === 0) {
                hint.textContent = '希望どおり購入できています。';
                return;
            }

            hint.textContent = '不足 ' + shortage + '点 のうち ' + allocated + '点 を割り当て済みです。';
        }

        purchasedInput.addEventListener('input', update);
        shortageInputs.forEach(function (input) { input.addEventListener('input', update); });
        update();
    })();
    </script>
</x-app-layout>
