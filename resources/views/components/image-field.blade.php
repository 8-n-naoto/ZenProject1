@props([
    'name',
    'label',
    'currentUrl' => null,
    'hint' => 'JPEG / PNG / WebP、4MBまで。',
    'removeName' => null,
    'preview' => 'h-24 w-24',
])

@php $removeName = $removeName ?? 'remove_' . $name; @endphp

<div class="space-y-2">
    <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>

    <div class="flex items-start gap-3">
        @if ($currentUrl)
            <img src="{{ $currentUrl }}" alt="" class="shrink-0 rounded-xl border border-slate-200 object-cover {{ $preview }}">
        @else
            <span class="flex shrink-0 items-center justify-center rounded-xl border border-dashed border-slate-300 text-xs text-slate-400 {{ $preview }}">
                なし
            </span>
        @endif

        <div class="min-w-0 flex-1 space-y-2">
            <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-slate-600">
            <p class="text-xs text-slate-500">{{ $hint }}</p>

            @if ($currentUrl)
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="{{ $removeName }}" value="1" class="h-4 w-4 rounded border-slate-300">
                    画像を削除する
                </label>
            @endif
        </div>
    </div>

    @error($name)
        <p class="text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
