@props(['class' => 'bg-slate-100 text-slate-600'])

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ' . $class]) }}>
    {{ $slot }}
</span>
