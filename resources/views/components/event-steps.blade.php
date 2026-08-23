@props(['event'])

@php use App\Enums\EventStatus; @endphp

<div class="flex items-center overflow-x-auto rounded-2xl bg-white px-2 py-3 shadow-sm">
    @foreach (EventStatus::cases() as $status)
        @php
            $done = $status->order() < $event->status->order();
            $current = $status === $event->status;
        @endphp
        <div class="flex shrink-0 items-center gap-1">
            <span class="whitespace-nowrap rounded-full px-1.5 py-1 text-xs font-semibold
                {{ $current ? 'bg-sky-600 text-white' : ($done ? 'bg-sky-50 text-sky-600' : 'bg-slate-100 text-slate-400') }}">
                {{ $status->label() }}
            </span>
            @unless ($loop->last)
                <span class="px-0.5 text-xs {{ $done ? 'text-sky-400' : 'text-slate-300' }}">›</span>
            @endunless
        </div>
    @endforeach
</div>
