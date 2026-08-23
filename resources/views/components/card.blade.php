@props(['title' => null, 'subtitle' => null, 'padding' => 'p-4'])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm']) }}>
    @if ($title)
        <header class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-700">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </header>
    @endif
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</section>
