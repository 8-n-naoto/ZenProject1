@props(['event', 'budget', 'editable' => true, 'showSummary' => true])

@php
    $set = $budget['budget'] !== null;
    $remaining = $budget['remaining'];
    $over = $budget['isOver'];
    $basisLabel = $budget['basis'] === 'actual' ? '購入済' : '予定';
@endphp

<div class="rounded-2xl border {{ $over && $showSummary ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white' }} px-4 py-3">
    @if ($set && $showSummary)
        <div class="flex items-baseline justify-between gap-2">
            <span class="text-xs {{ $over ? 'text-rose-700' : 'text-slate-500' }}">
                残り（予算 ¥{{ number_format($budget['budget']) }}）
            </span>
            <span class="text-lg font-bold tabular-nums {{ $over ? 'text-rose-700' : 'text-emerald-600' }}">
                @if ($over)
                    −¥{{ number_format(abs($remaining)) }}
                @else
                    ¥{{ number_format($remaining) }}
                @endif
            </span>
        </div>

        @php
            $ratio = $budget['budget'] > 0 ? min(100, (int) round($budget['used'] / $budget['budget'] * 100)) : 0;
        @endphp
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200">
            <div class="h-2 rounded-full {{ $over ? 'bg-rose-500' : ($ratio >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                 style="width: {{ $ratio }}%"></div>
        </div>

        <p class="mt-1 text-xs {{ $over ? 'text-rose-700' : 'text-slate-500' }}">
            {{ $basisLabel }} ¥{{ number_format($budget['used']) }}
            @if ($over)
                ・<span class="font-semibold">予算を超えています</span>
            @elseif ($ratio >= 80)
                ・残りわずかです
            @endif
        </p>
    @elseif ($set)
        <p class="text-xs text-slate-500">
            {{ $basisLabel }} ¥{{ number_format($budget['used']) }} ／ 予算 ¥{{ number_format($budget['budget']) }}
        </p>
    @else
        <p class="text-xs text-slate-500">
            {{ $basisLabel }} ¥{{ number_format($budget['used']) }}・予算は未設定です
        </p>
    @endif

    @if ($editable)
        <details class="mt-2">
            <summary class="cursor-pointer text-xs font-semibold text-sky-600">
                {{ $set ? '予算を変更する' : '予算を設定する' }}
            </summary>
            <form method="POST" action="{{ route('events.budget.update', $event) }}" class="mt-2 flex items-end gap-2">
                @csrf
                @method('PATCH')
                <div class="min-w-0 flex-1">
                    <label for="budget" class="sr-only">予算（円）</label>
                    <input type="number" name="budget" id="budget" inputmode="numeric"
                           min="0" max="10000000" step="100"
                           value="{{ $budget['budget'] }}" placeholder="例: 20000"
                           class="block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </div>
                <x-button size="sm">保存</x-button>
            </form>
            <p class="mt-1 text-xs text-slate-500">空欄にして保存すると設定を解除します。</p>
        </details>
    @endif
</div>
