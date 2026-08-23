@php
    $days = old('days', $event?->days->map(fn ($d) => [
        'event_date' => $d->event_date->toDateString(),
        'starts_at' => $d->starts_at?->format('H:i'),
        'ends_at' => $d->ends_at?->format('H:i'),
    ])->all() ?? [['event_date' => '', 'starts_at' => '10:00', 'ends_at' => '16:00']]);
@endphp

<x-card>
    <div class="space-y-4">
        <x-input name="name" label="イベント名" :value="$event?->name" required maxlength="100" placeholder="例: コミックマーケット105" />
        <x-input name="venue_name" label="会場名" :value="$event?->venue_name" required maxlength="100" placeholder="例: 東京ビッグサイト" />
        <x-input name="venue_address" label="会場の住所" :value="$event?->venue_address" maxlength="255" placeholder="任意" />
        <x-textarea name="description" label="説明" :value="$event?->description" rows="3" maxlength="1000" placeholder="集合場所や当日の連絡事項など（任意）" />
    </div>
</x-card>

<x-card title="開催日" subtitle="最大10日まで登録できます。">
    <div id="day-rows" class="space-y-3">
        @foreach ($days as $index => $day)
            <div class="day-row rounded-xl border border-slate-200 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">{{ $loop->iteration }}日目</span>
                    <button type="button" class="remove-day -my-2 inline-flex min-h-10 items-center px-2 text-xs text-rose-600">削除</button>
                </div>
                <div class="mt-2 space-y-2">
                    <input type="date" name="days[{{ $index }}][event_date]" value="{{ $day['event_date'] ?? '' }}"
                           aria-label="開催日"
                           class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base" required>
                    <div class="flex items-center gap-2">
                        <input type="time" name="days[{{ $index }}][starts_at]" value="{{ $day['starts_at'] ?? '' }}"
                               aria-label="開始時刻"
                               class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                        <span class="text-sm text-slate-400">〜</span>
                        <input type="time" name="days[{{ $index }}][ends_at]" value="{{ $day['ends_at'] ?? '' }}"
                               aria-label="終了時刻"
                               class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" id="add-day" class="mt-3 w-full rounded-xl border border-dashed border-slate-300 py-2.5 text-sm font-semibold text-sky-600">
        ＋ 開催日を追加
    </button>

    @error('days')
        <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</x-card>

<script>
(function () {
    const rows = document.getElementById('day-rows');
    const addButton = document.getElementById('add-day');

    function renumber() {
        rows.querySelectorAll('.day-row').forEach(function (row, index) {
            row.querySelector('span').textContent = (index + 1) + '日目';
            row.querySelectorAll('input').forEach(function (input) {
                input.name = input.name.replace(/days\[\d+\]/, 'days[' + index + ']');
            });
        });
    }

    addButton.addEventListener('click', function () {
        const template = rows.querySelector('.day-row');
        const clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'date') {
                input.value = '';
            }
        });
        rows.appendChild(clone);
        renumber();
    });

    rows.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-day')) {
            return;
        }
        if (rows.querySelectorAll('.day-row').length <= 1) {
            return;
        }
        event.target.closest('.day-row').remove();
        renumber();
    });
})();
</script>
