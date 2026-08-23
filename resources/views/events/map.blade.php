@php
    $mapUrl = $event->mapImageUrl();
@endphp

<x-app-layout title="会場マップ" heading="会場マップ" :back="route('events.show', $event)">
    <div class="space-y-4">
        @if ($mapUrl)
            {{-- 凡例 --}}
            <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-white px-4 py-3 text-xs">
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full border-2 border-white bg-rose-500 shadow"></span>未購入
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full border-2 border-white bg-emerald-500 shadow"></span>購入済
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full border-2 border-sky-600 bg-white shadow"></span>自分の希望あり
                </span>
            </div>

            {{-- 会場図 --}}
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div id="map-viewport" class="relative max-h-[70vh] overflow-auto bg-slate-100">
                    <div id="map-canvas" class="relative origin-top-left" style="width: 100%;">
                        <img src="{{ $mapUrl }}" alt="会場図" class="block w-full select-none" draggable="false">

                        @foreach ($pins as $pin)
                            @php
                                $tone = $pin['isDone'] ? 'bg-emerald-500' : 'bg-rose-500';
                                $ring = $pin['isMine'] ? 'ring-2 ring-sky-600' : '';
                            @endphp
                            <button type="button"
                                    class="map-pin absolute flex h-6 w-6 items-center justify-center rounded-full border-2 border-white text-[10px] font-bold text-white shadow {{ $tone }} {{ $ring }}"
                                    style="left: {{ $pin['x'] }}%; top: {{ $pin['y'] }}%; transform: translate(-50%, -50%);"
                                    data-name="{{ $pin['circle']->display_name }}"
                                    data-booth="{{ $pin['circle']->locationLabel() }}"
                                    data-progress="{{ $pin['total'] > 0 ? $pin['done'].' / '.$pin['total'].' 件' : '' }}"
                                    data-url="{{ route('circles.show', $pin['circle']) }}"
                                    aria-label="{{ $pin['circle']->display_name }}（{{ $pin['circle']->locationLabel() }}）">
                                {{ $loop->iteration }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- 拡大縮小 --}}
                <div class="flex items-center gap-2 border-t border-slate-100 px-3 py-2">
                    <button type="button" id="map-zoom-out"
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-600"
                            aria-label="縮小">−</button>
                    <span id="map-zoom-label" class="min-w-0 flex-1 text-center text-xs tabular-nums text-slate-500">100%</span>
                    <button type="button" id="map-zoom-in"
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-bold text-slate-600"
                            aria-label="拡大">＋</button>
                    <button type="button" id="map-zoom-reset"
                            class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600">戻す</button>
                </div>
            </div>

            {{-- 選択したサークルの情報 --}}
            <div id="map-detail" class="hidden rounded-2xl bg-white px-4 py-3 shadow-sm">
                <p id="map-detail-name" class="text-sm font-semibold"></p>
                <p id="map-detail-booth" class="mt-0.5 text-xs text-slate-500"></p>
                <p id="map-detail-progress" class="mt-0.5 text-xs text-slate-500"></p>
                <a id="map-detail-link" href="#" class="mt-2 inline-block text-xs font-semibold text-sky-600">サークルの詳細を見る</a>
            </div>

            <script>
            (function () {
                var canvas = document.getElementById('map-canvas');
                var viewport = document.getElementById('map-viewport');
                var label = document.getElementById('map-zoom-label');
                if (!canvas || !viewport) { return; }

                var scale = 1;
                var MIN = 1;
                var MAX = 4;

                function apply() {
                    canvas.style.width = (scale * 100) + '%';
                    if (label) { label.textContent = Math.round(scale * 100) + '%'; }
                }

                function zoom(delta) {
                    var next = Math.min(MAX, Math.max(MIN, Math.round((scale + delta) * 10) / 10));
                    if (next === scale) { return; }

                    // 表示中の中心を保ったまま拡大縮小する
                    var ratio = next / scale;
                    var centerX = viewport.scrollLeft + viewport.clientWidth / 2;
                    var centerY = viewport.scrollTop + viewport.clientHeight / 2;

                    scale = next;
                    apply();

                    viewport.scrollLeft = centerX * ratio - viewport.clientWidth / 2;
                    viewport.scrollTop = centerY * ratio - viewport.clientHeight / 2;
                }

                var zoomIn = document.getElementById('map-zoom-in');
                var zoomOut = document.getElementById('map-zoom-out');
                var reset = document.getElementById('map-zoom-reset');

                if (zoomIn) { zoomIn.addEventListener('click', function () { zoom(0.5); }); }
                if (zoomOut) { zoomOut.addEventListener('click', function () { zoom(-0.5); }); }
                if (reset) {
                    reset.addEventListener('click', function () {
                        scale = 1; apply();
                        viewport.scrollLeft = 0; viewport.scrollTop = 0;
                    });
                }

                // ピンチ操作
                var startDistance = null;
                var startScale = 1;

                viewport.addEventListener('touchstart', function (event) {
                    if (event.touches.length !== 2) { return; }
                    startDistance = Math.hypot(
                        event.touches[0].clientX - event.touches[1].clientX,
                        event.touches[0].clientY - event.touches[1].clientY
                    );
                    startScale = scale;
                }, { passive: true });

                viewport.addEventListener('touchmove', function (event) {
                    if (event.touches.length !== 2 || startDistance === null) { return; }
                    var distance = Math.hypot(
                        event.touches[0].clientX - event.touches[1].clientX,
                        event.touches[0].clientY - event.touches[1].clientY
                    );
                    var next = Math.min(MAX, Math.max(MIN, startScale * (distance / startDistance)));
                    scale = Math.round(next * 10) / 10;
                    apply();
                }, { passive: true });

                viewport.addEventListener('touchend', function () { startDistance = null; }, { passive: true });

                // ピンをタップしたら情報を出す
                var detail = document.getElementById('map-detail');

                canvas.addEventListener('click', function (event) {
                    var pin = event.target.closest('.map-pin');
                    if (!pin || !detail) { return; }

                    document.getElementById('map-detail-name').textContent = pin.dataset.name || '';
                    document.getElementById('map-detail-booth').textContent = pin.dataset.booth || '';
                    document.getElementById('map-detail-progress').textContent = pin.dataset.progress || '';
                    document.getElementById('map-detail-link').setAttribute('href', pin.dataset.url || '#');
                    detail.classList.remove('hidden');
                });

                apply();
            })();
            </script>
        @else
            <x-empty-state message="会場図がまだ登録されていません"
                           hint="会場図を登録すると、サークルの位置を地図の上で確認できます。" />
        @endif

        @if ($canEdit)
            <x-card title="会場図の登録">
                <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    会場図には配布元の権利があります。転載が許可されているものか確認してから登録してください。
                    グループ内での利用に限る場合が多いため、外部への共有は避けてください。
                </div>

                <form method="POST" action="{{ route('events.map.image', $event) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-image-field name="map_image" label="会場図の画像"
                                   :current-url="$mapUrl"
                                   hint="JPEG / PNG / WebP、4MBまで。差し替えると、置いた位置は消えます。"
                                   preview="h-24 w-32" />
                    <x-button variant="secondary" class="w-full">会場図を保存</x-button>
                </form>
            </x-card>

            @if ($mapUrl)
                <x-card title="サークルの位置を置く" subtitle="会場図をタップすると、その場所に置きます。">
                    <label for="place-target" class="block text-sm font-medium text-slate-700">サークル</label>
                    <select id="place-target" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @foreach ($event->eventCircles as $circle)
                            <option value="{{ $circle->id }}"
                                    data-x="{{ $circle->venue_map_x }}"
                                    data-y="{{ $circle->venue_map_y }}"
                                    data-action="{{ route('events.map.place', [$event, $circle]) }}">
                                {{ $circle->locationLabel() }} {{ $circle->display_name }}{{ $circle->venueMapPin() ? '（配置済）' : '' }}
                            </option>
                        @endforeach
                    </select>

                    <div id="place-area" class="relative mt-3 w-full overflow-hidden rounded-xl border border-slate-200">
                        <img src="{{ $mapUrl }}" alt="会場図" class="block w-full">
                        <span id="place-marker" class="pointer-events-none absolute hidden h-5 w-5 rounded-full border-2 border-white bg-sky-600 shadow"
                              style="transform: translate(-50%, -50%);"></span>
                    </div>

                    <form method="POST" id="place-form" action="{{ route('events.map.place', [$event, $event->eventCircles->first() ?? 0]) }}" class="mt-3 space-y-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="venue_map_x" id="place-x">
                        <input type="hidden" name="venue_map_y" id="place-y">
                        <x-button class="w-full">この位置で保存</x-button>
                        <button type="button" id="place-clear" class="block w-full text-center text-xs font-semibold text-slate-500">
                            このサークルの位置を消す
                        </button>
                    </form>

                    <script>
                    (function () {
                        var select = document.getElementById('place-target');
                        var area = document.getElementById('place-area');
                        var marker = document.getElementById('place-marker');
                        var form = document.getElementById('place-form');
                        var fieldX = document.getElementById('place-x');
                        var fieldY = document.getElementById('place-y');
                        var clear = document.getElementById('place-clear');
                        if (!select || !area || !marker || !form) { return; }

                        function syncFromSelection() {
                            var option = select.options[select.selectedIndex];
                            if (!option) { return; }

                            form.setAttribute('action', option.dataset.action || '#');

                            var x = option.dataset.x;
                            var y = option.dataset.y;

                            if (x && y) {
                                fieldX.value = x;
                                fieldY.value = y;
                                marker.style.left = x + '%';
                                marker.style.top = y + '%';
                                marker.classList.remove('hidden');
                            } else {
                                fieldX.value = '';
                                fieldY.value = '';
                                marker.classList.add('hidden');
                            }
                        }

                        select.addEventListener('change', syncFromSelection);

                        area.addEventListener('click', function (event) {
                            var rect = area.getBoundingClientRect();
                            if (!rect.width || !rect.height) { return; }

                            var x = Math.min(100, Math.max(0, Math.round(((event.clientX - rect.left) / rect.width) * 100)));
                            var y = Math.min(100, Math.max(0, Math.round(((event.clientY - rect.top) / rect.height) * 100)));

                            fieldX.value = x;
                            fieldY.value = y;
                            marker.style.left = x + '%';
                            marker.style.top = y + '%';
                            marker.classList.remove('hidden');
                        });

                        if (clear) {
                            clear.addEventListener('click', function () {
                                fieldX.value = '';
                                fieldY.value = '';
                                marker.classList.add('hidden');
                                form.submit();
                            });
                        }

                        syncFromSelection();
                    })();
                    </script>
                </x-card>
            @endif
        @endif

        @if ($unplaced->isNotEmpty())
            <x-card title="まだ会場図に置いていないサークル（{{ $unplaced->count() }}件）">
                @foreach ($unplaced as $circle)
                    <div class="flex items-center gap-2 border-b border-slate-100 py-2 text-sm last:border-b-0">
                        <span class="min-w-0 flex-1 truncate">{{ $circle->display_name }}</span>
                        <span class="shrink-0 text-xs text-slate-500">{{ $circle->locationLabel() }}</span>
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>
</x-app-layout>
