@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-sky-600 text-white hover:bg-sky-700',
        'secondary' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'ghost' => 'text-slate-600 hover:bg-slate-100',
        'subtle' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    ];
    // 指で押しやすいよう、最小の高さを確保する（sm でも 40px）
    $sizes = [
        'sm' => 'min-h-10 px-3 py-2 text-xs',
        'md' => 'min-h-11 px-4 py-2.5 text-sm',
        'lg' => 'min-h-12 px-5 py-3 text-base',
    ];
    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition-colors disabled:opacity-50 '
        . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
