<x-card>
    <div class="space-y-4">
        <x-input name="display_name" label="サークル名" :value="$circle?->display_name" required maxlength="100"
                 placeholder="例: 夏空スタジオ" hint="同じイベント内に同名のサークルがある場合は警告します。" />
        <x-input name="booth" label="配置" :value="$circle?->booth" maxlength="50" placeholder="例: 東1ホール ア-12a" />
        <div class="space-y-1.5">
            <label for="sellout_risk" class="block text-sm font-medium text-slate-700">完売リスク</label>
            <select name="sellout_risk" id="sellout_risk"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">指定しない</option>
                @foreach (\App\Enums\SelloutRisk::cases() as $risk)
                    <option value="{{ $risk->value }}"
                            @selected(old('sellout_risk', $circle?->sellout_risk?->value) === $risk->value)>
                        {{ $risk->label() }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-slate-500">当日の巡回ルートで、完売しやすいサークルを先に回るようにします。</p>
            @error('sellout_risk')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <x-input name="website_url" label="WebサイトURL" type="url" :value="$circle?->circle?->website_url" maxlength="255" placeholder="任意" />
        <x-textarea name="description" label="メモ" :value="$circle?->circle?->description" rows="3" maxlength="500" placeholder="任意" />
        <x-image-field name="map_image" label="配置マップの画像"
                       :current-url="$circle?->mapImageUrl()"
                       hint="カタログの配置図を切り取って登録しておくと、当日すぐ確認できます。"
                       preview="h-24 w-32" />

        @if ($circle?->mapImageUrl())
            @php $pin = $circle->mapPin(); @endphp
            <div class="space-y-2">
                <span class="block text-sm font-medium text-slate-700">マップ上の位置</span>
                <p class="text-xs text-slate-500">画像をタップすると、その場所に目印を置きます。</p>
                <div id="map-pin-area" class="relative w-full overflow-hidden rounded-xl border border-slate-200">
                    <img id="map-pin-image" src="{{ $circle->mapImageUrl() }}" alt="配置マップ" class="block w-full">
                    <span id="map-pin-marker"
                          class="pointer-events-none absolute h-5 w-5 rounded-full border-2 border-white bg-rose-500 shadow {{ $pin ? '' : 'hidden' }}"
                          style="left: {{ $pin['x'] ?? 50 }}%; top: {{ $pin['y'] ?? 50 }}%; transform: translate(-50%, -50%);"></span>
                </div>
                <input type="hidden" name="map_x" id="map-pin-x" value="{{ old('map_x', $pin['x'] ?? '') }}">
                <input type="hidden" name="map_y" id="map-pin-y" value="{{ old('map_y', $pin['y'] ?? '') }}">
                <button type="button" id="map-pin-clear" class="-mx-2 inline-flex min-h-10 items-center px-2 text-xs font-semibold text-slate-500 {{ $pin ? '' : 'hidden' }}">
                    目印を消す
                </button>
            </div>

            <script>
            (function () {
                var area = document.getElementById('map-pin-area');
                var marker = document.getElementById('map-pin-marker');
                var fieldX = document.getElementById('map-pin-x');
                var fieldY = document.getElementById('map-pin-y');
                var clear = document.getElementById('map-pin-clear');
                if (!area || !marker || !fieldX || !fieldY) { return; }

                area.addEventListener('click', function (event) {
                    var rect = area.getBoundingClientRect();
                    if (!rect.width || !rect.height) { return; }
                    var x = Math.round(((event.clientX - rect.left) / rect.width) * 100);
                    var y = Math.round(((event.clientY - rect.top) / rect.height) * 100);
                    x = Math.min(100, Math.max(0, x));
                    y = Math.min(100, Math.max(0, y));
                    fieldX.value = x;
                    fieldY.value = y;
                    marker.style.left = x + '%';
                    marker.style.top = y + '%';
                    marker.classList.remove('hidden');
                    if (clear) { clear.classList.remove('hidden'); }
                });

                // 画像を差し替える／削除するとピンの位置は無効になるので、入力も消す
                var fileField = document.querySelector('input[name="map_image"]');
                var removeField = document.querySelector('input[name="remove_map_image"]');

                function forgetPin() {
                    fieldX.value = '';
                    fieldY.value = '';
                    marker.classList.add('hidden');
                    if (clear) { clear.classList.add('hidden'); }
                }

                if (fileField) {
                    fileField.addEventListener('change', function () {
                        if (fileField.files && fileField.files.length > 0) { forgetPin(); }
                    });
                }

                if (removeField) {
                    removeField.addEventListener('change', function () {
                        if (removeField.checked) { forgetPin(); }
                    });
                }

                if (clear) {
                    clear.addEventListener('click', forgetPin);
                }
            })();
            </script>
        @endif
    </div>
</x-card>

@if (session('duplicate_warning'))
    <x-card class="border border-amber-200 bg-amber-50">
        <label class="flex items-start gap-2 text-sm text-amber-900">
            <input type="checkbox" name="force" value="1" class="mt-0.5 h-4 w-4 rounded border-amber-300">
            <span>同名のサークルがありますが、<span class="font-semibold">別のサークルとして登録する</span></span>
        </label>
    </x-card>
@endif
