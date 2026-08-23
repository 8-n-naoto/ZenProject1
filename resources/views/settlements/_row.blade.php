@php
    $other = $direction === 'pay' ? $settlement->payee : $settlement->payer;
    $reported = $settlement->reportedPayment();
@endphp

<div class="mb-2 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <x-avatar :user="$other" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">
                {{ $other?->displayName() ?? '不明なユーザー' }} {{ $direction === 'pay' ? 'さんへ' : 'さんから' }}
            </p>
            <p class="text-xs text-slate-500">&#64;{{ $other?->user_id ?? '-' }}</p>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-lg font-bold tabular-nums">{{ $settlement->amountLabel() }}</p>
            <x-badge :class="$settlement->status->badgeClass()">{{ $settlement->status->label() }}</x-badge>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <a href="{{ route('settlements.show', $settlement) }}" class="-mx-2 inline-flex min-h-10 items-center px-2 text-xs font-semibold text-sky-600">内訳を見る</a>
    </div>

    @unless ($settlement->isCompleted())
        <div class="mt-3 space-y-2">
            @if ($direction === 'pay' && ! $reported)
                @can('report', $settlement)
                    <form method="POST" action="{{ route('settlements.report', $settlement) }}"
                          onsubmit="return confirm('{{ $other?->displayName() }} さんに {{ $settlement->amountLabel() }} を支払ったことを報告します。よろしいですか？');">
                        @csrf
                        <x-button class="w-full">支払ったことを報告する</x-button>
                    </form>
                @endcan
            @endif

            @if ($reported)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ $reported->paid_at?->format('Y/m/d H:i') }} に支払いが報告されています。
                </div>

                @if ($direction === 'receive' && auth()->user()->can('confirm', $reported))
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('payments.confirm', $reported) }}" class="flex-1"
                              onsubmit="return confirm('{{ $other?->displayName() }} さんから {{ $settlement->amountLabel() }} を受け取ったことを確定します。よろしいですか？');">
                            @csrf
                            <x-button class="w-full" size="sm">受け取った</x-button>
                        </form>
                        <form method="POST" action="{{ route('payments.reject', $reported) }}" class="flex-1"
                              onsubmit="return confirm('支払いの報告を取り消して、{{ $other?->displayName() }} さんに再度支払いを求めます。よろしいですか？');">
                            @csrf
                            <x-button variant="secondary" class="w-full" size="sm">まだ受け取っていない</x-button>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    @endunless
</div>
