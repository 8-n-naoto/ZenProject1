@props(['message', 'hint' => null])

<div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
    <p class="text-sm font-medium text-slate-600">{{ $message }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
