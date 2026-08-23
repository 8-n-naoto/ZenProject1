@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 4,
    'hint' => null,
    'required' => false,
    'srOnlyLabel' => false,
])

@php
    $errorId = $name . '-error';
    $hintId = $name . '-hint';
    $describedBy = collect([$hint ? $hintId : null, $errors->has($name) ? $errorId : null])
        ->filter()
        ->implode(' ');
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $name }}" class="{{ $srOnlyLabel ? 'sr-only' : 'block text-sm font-medium text-slate-700' }}">
            {{ $label }}
            @if ($required && ! $srOnlyLabel)
                <span class="text-rose-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'block w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 text-base placeholder-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500']) }}
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
