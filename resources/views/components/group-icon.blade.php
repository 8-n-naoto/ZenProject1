@props(['group', 'size' => 'md'])

@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-14 w-14 text-lg'];
    $classes = $sizes[$size] ?? $sizes['md'];
    $url = $group->imageUrl();
@endphp

@if ($url)
    <img src="{{ $url }}" alt="" class="shrink-0 rounded-xl object-cover {{ $classes }}">
@else
    <span class="flex shrink-0 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-500 {{ $classes }}">{{ $group->initial() }}</span>
@endif
