@php
    $renderCircle = function ($sharedPurchase, bool $canRecord) {
        return view('results._circle', compact('sharedPurchase', 'canRecord'));
    };
@endphp

<x-app-layout title="購入結果" heading="購入結果" :back="route('events.show', $event)">
    <div class="space-y-4">
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">自分が担当するサークル</h2>
            @forelse ($mine as $sharedPurchase)
                @include('results._circle', ['sharedPurchase' => $sharedPurchase, 'canRecord' => $canRecord])
            @empty
                <x-empty-state message="担当しているサークルはありません" />
            @endforelse
        </section>

        @if ($personal->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">自分で買うもの</h2>
                <form method="POST" action="{{ route('results.personal.store', $event) }}">
                    @csrf
                    @method('PATCH')
                    <x-card>
                        <div class="space-y-3">
                            @foreach ($personal as $purchase)
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm">{{ $purchase->eventProduct->name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $purchase->eventProduct->eventCircle->display_name }}・希望 {{ $purchase->planned_quantity }}点
                                        </p>
                                    </div>
                                    <input type="number" min="0" max="999" inputmode="numeric"
                                           aria-label="{{ $purchase->eventProduct->name }} の購入できた数"
                                           name="purchased[{{ $purchase->id }}]"
                                           value="{{ $purchase->purchaseResult?->purchased_quantity ?? $purchase->planned_quantity }}"
                                           @disabled(! $canRecord)
                                           class="w-20 shrink-0 rounded-xl border border-slate-300 px-2 py-2 text-center text-base tabular-nums">
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                    @if ($canRecord)
                        <x-button class="mt-3 w-full">購入結果を保存</x-button>
                    @endif
                </form>
            </section>
        @endif

        @if ($others->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">ほかのサークル</h2>
                @foreach ($others as $sharedPurchase)
                    @include('results._circle', ['sharedPurchase' => $sharedPurchase, 'canRecord' => $canRecord])
                @endforeach
            </section>
        @endif

        <x-card title="記録をダウンロード">
            <p class="mb-3 text-xs text-slate-500">購入結果の一覧をCSV（Excelで開けます）で保存できます。</p>
            <a href="{{ route('events.export.results', $event) }}"
               class="block w-full rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">
                購入結果をCSVで保存
            </a>
        </x-card>
    </div>
</x-app-layout>
