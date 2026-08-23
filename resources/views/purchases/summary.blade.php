<x-app-layout title="希望の集計" heading="希望の集計" :back="route('events.show', $event)">
    <div class="space-y-4">
        <x-card title="サークル別">
            @forelse ($circles as $circle)
                @php $purchases = $byCircle->get($circle->id, collect()); @endphp
                <div class="border-b border-slate-100 py-3 last:border-b-0">
                    <p class="text-sm font-semibold">{{ $circle->display_name }}</p>
                    @if ($purchases->isEmpty())
                        <p class="mt-1 text-xs text-slate-400">希望なし</p>
                    @else
                        @foreach ($purchases->groupBy('event_product_id') as $rows)
                            <div class="mt-1 flex items-baseline justify-between gap-2 text-xs">
                                <span class="min-w-0 truncate text-slate-600">{{ $rows->first()->eventProduct->name }}</span>
                                <span class="shrink-0 tabular-nums text-slate-500">
                                    {{ $rows->sum('planned_quantity') }}点 / {{ $rows->count() }}人
                                </span>
                            </div>
                        @endforeach
                    @endif
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">サークルが登録されていません。</p>
            @endforelse
        </x-card>

        <x-card title="参加者別">
            @forelse ($event->participants as $participant)
                @php $rows = $byUser->get($participant->id, collect()); @endphp
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <x-avatar :user="$participant" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $participant->displayName() }}</p>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-slate-500">
                        {{ $rows->sum('planned_quantity') }}点 / ¥{{ number_format($rows->sum(fn ($p) => $p->plannedAmount())) }}
                    </span>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">参加者がいません。</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
