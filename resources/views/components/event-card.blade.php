@props(['event'])

<a href="{{ route('events.show', $event) }}" {{ $attributes->merge(['class' => 'block rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50']) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold">{{ $event->name }}</p>
            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $event->venue_name }}</p>
        </div>
        <x-badge :class="$event->status->badgeClass()">{{ $event->status->label() }}</x-badge>
    </div>
    <div class="mt-2 flex items-center gap-3 text-xs text-slate-500">
        <span>{{ $event->dateRangeLabel() }}</span>
        @isset($event->participants_count)
            <span>参加 {{ $event->participants_count }}人</span>
        @endisset
    </div>
</a>
