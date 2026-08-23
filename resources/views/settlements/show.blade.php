<x-app-layout title="精算の内訳" heading="精算の内訳" :back="route('settlements.index', $event)">
    <div class="space-y-4">
        <x-card>
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm">
                        {{ $settlement->payer?->displayName() ?? '不明なユーザー' }}
                        <span class="text-slate-400">→</span>
                        {{ $settlement->payee?->displayName() ?? '不明なユーザー' }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $settlement->isCompleted() ? $settlement->settled_at?->format('Y/m/d H:i') . ' に完了' : '未精算' }}
                    </p>
                </div>
                <p class="shrink-0 text-xl font-bold tabular-nums">{{ $settlement->amountLabel() }}</p>
            </div>
        </x-card>

        <x-card title="この支払いに含まれるもの"
                subtitle="相殺により、実際の立替者とは異なる相手に支払う場合があります。">
            @forelse ($components as $component)
                @php $result = $componentResults->get($component['purchase_result_id']); @endphp
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm">{{ $result?->eventProduct?->name ?? '不明な商品' }}</p>
                        <p class="text-xs text-slate-500">
                            {{-- 相殺で1点未満の端数だけを支払う場合は点数を出さない --}}
                            {{ $component['quantity'] > 0 ? $component['quantity'].'点' : '相殺分の一部' }}
                        </p>
                    </div>
                    <span class="shrink-0 text-sm tabular-nums">¥{{ number_format($component['amount']) }}</span>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">内訳がありません。</p>
            @endforelse
        </x-card>

        @if ($settlement->payments->isNotEmpty())
            <x-card title="支払いの記録">
                @foreach ($settlement->payments as $payment)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <p class="text-sm tabular-nums">{{ $payment->amountLabel() }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $payment->paid_at?->format('Y/m/d H:i') }}
                                @if ($payment->confirmedBy)
                                    ・{{ $payment->confirmedBy?->displayName() ?? '不明なユーザー' }} が確認
                                @endif
                            </p>
                        </div>
                        <x-badge :class="$payment->status->badgeClass()">{{ $payment->status->label() }}</x-badge>
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>
</x-app-layout>
