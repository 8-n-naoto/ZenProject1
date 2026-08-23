@php
    $toPay = $outstanding['toPay'];
    $toReceive = $outstanding['toReceive'];
    $net = $outstanding['net'];
@endphp

<x-app-layout title="未精算のまとめ" heading="未精算のまとめ" :back="route('dashboard')">
    <div class="space-y-4">
        <x-card>
            <div class="grid grid-cols-2 gap-3 text-center">
                <div class="rounded-xl bg-rose-50 px-3 py-3">
                    <p class="text-xs text-rose-700">支払う</p>
                    <p class="text-lg font-bold tabular-nums text-rose-700">¥{{ number_format($outstanding['payTotal']) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 px-3 py-3">
                    <p class="text-xs text-emerald-700">受け取る</p>
                    <p class="text-lg font-bold tabular-nums text-emerald-700">¥{{ number_format($outstanding['receiveTotal']) }}</p>
                </div>
            </div>

            <p class="mt-3 text-center text-sm">
                差引
                <span class="ml-1 text-lg font-bold tabular-nums {{ $net > 0 ? 'text-emerald-600' : ($net < 0 ? 'text-rose-600' : 'text-slate-500') }}">
                    {{ $net > 0 ? '+' : ($net < 0 ? '-' : '') }}¥{{ number_format(abs($net)) }}
                </span>
            </p>
            <p class="mt-1 text-center text-xs text-slate-500">参加中のすべてのグループの未精算を合計しています。</p>
        </x-card>

        @if ($toPay->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">あなたが支払う</h2>
                @foreach ($toPay as $settlement)
                    <div class="mb-1 px-1 text-xs text-slate-500">
                        {{ $settlement->event->group->name }}・{{ $settlement->event->name }}
                    </div>
                    @include('settlements._row', ['settlement' => $settlement, 'direction' => 'pay'])
                @endforeach
            </section>
        @endif

        @if ($toReceive->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">あなたが受け取る</h2>
                @foreach ($toReceive as $settlement)
                    <div class="mb-1 px-1 text-xs text-slate-500">
                        {{ $settlement->event->group->name }}・{{ $settlement->event->name }}
                    </div>
                    @include('settlements._row', ['settlement' => $settlement, 'direction' => 'receive'])
                @endforeach
            </section>
        @endif

        @if ($toPay->isEmpty() && $toReceive->isEmpty())
            <x-empty-state message="未精算はありません"
                           hint="支払い・受け取りが残っている精算がここにまとまります。" />
        @endif
    </div>
</x-app-layout>
