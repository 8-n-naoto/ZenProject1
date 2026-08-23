<x-app-layout title="収支の内訳" heading="収支の内訳" :back="route('settlements.index', $event)">
    <div class="space-y-4">
        <x-card>
            <div class="flex items-center gap-3">
                <x-avatar :user="$member" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $member->displayName() }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $event->name }}</p>
                </div>
                <span class="shrink-0 text-xl font-bold tabular-nums {{ $breakdown['net'] > 0 ? 'text-emerald-600' : ($breakdown['net'] < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                    {{ $breakdown['net'] > 0 ? '+' : ($breakdown['net'] < 0 ? '-' : '') }}¥{{ number_format(abs($breakdown['net'])) }}
                </span>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-center">
                <div>
                    <dt class="text-xs text-slate-500">立て替えた額</dt>
                    <dd class="text-sm font-semibold tabular-nums">¥{{ number_format($breakdown['spentTotal']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">自分の購入分</dt>
                    <dd class="text-sm font-semibold tabular-nums">¥{{ number_format($breakdown['owedTotal']) }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="立て替えたもの"
                subtitle="{{ $member->displayName() }}さんが他のメンバーの分を購入した明細です。">
            @forelse ($breakdown['spent'] as $row)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm">{{ $row['result']?->eventProduct?->name ?? '不明な商品' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $row['result']?->eventProduct?->eventCircle?->display_name ?? '不明なサークル' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $row['counterparty']?->displayName() ?? '不明なユーザー' }} さんの分・{{ $row['quantity'] }}点
                        </p>
                    </div>
                    <span class="shrink-0 text-sm tabular-nums">¥{{ number_format($row['amount']) }}</span>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">他のメンバーの分を立て替えた購入はありません。</p>
            @endforelse
            @if ($breakdown['spent']->isNotEmpty())
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-sm font-semibold">
                    <span>合計</span>
                    <span class="tabular-nums">¥{{ number_format($breakdown['spentTotal']) }}</span>
                </div>
            @endif
        </x-card>

        <x-card title="購入したもの"
                subtitle="他のメンバーが立て替えた、{{ $member->displayName() }}さんの購入分の明細です。">
            @forelse ($breakdown['owed'] as $row)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm">{{ $row['result']?->eventProduct?->name ?? '不明な商品' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $row['result']?->eventProduct?->eventCircle?->display_name ?? '不明なサークル' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $row['counterparty']?->displayName() ?? '不明なユーザー' }} さんが立替・{{ $row['quantity'] }}点
                        </p>
                    </div>
                    <span class="shrink-0 text-sm tabular-nums">¥{{ number_format($row['amount']) }}</span>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">他のメンバーに立て替えてもらった購入はありません。</p>
            @endforelse
            @if ($breakdown['owed']->isNotEmpty())
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-sm font-semibold">
                    <span>合計</span>
                    <span class="tabular-nums">¥{{ number_format($breakdown['owedTotal']) }}</span>
                </div>
            @endif
        </x-card>

        <p class="px-1 text-xs text-slate-500">
            自分で購入した自分の分（個人購入や、担当サークルでの自分の希望分）は、
            支払いが発生しないためこの内訳には含まれません。
        </p>
    </div>
</x-app-layout>
