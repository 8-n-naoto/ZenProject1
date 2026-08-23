@props(['user' => null, 'size' => 'md'])

@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-12 w-12 text-base'];
    $trashed = $user?->hasLeftService() ?? true;
    $tone = $trashed ? 'bg-slate-100 text-slate-400' : 'bg-sky-100 text-sky-700';
@endphp

<span class="flex shrink-0 items-center justify-center rounded-full font-bold {{ $tone }} {{ $sizes[$size] ?? $sizes['md'] }}">
    {{ $user?->initial() ?? '?' }}
</span>
