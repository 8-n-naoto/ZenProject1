@php
    $percent = $progress['total'] > 0 ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
@endphp

<x-app-layout title="買い物リスト" heading="買い物リスト" :back="route('events.show', $event)">
    <div class="space-y-4">
        {{-- 進捗と残高（スクロールしても残す） --}}
        <div class="sticky top-14 z-30 -mx-3 border-b border-slate-200 bg-white px-3 py-3">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-semibold">今日の進捗</span>
                <span class="text-sm tabular-nums text-slate-600">{{ $progress['done'] }} / {{ $progress['total'] }} 件</span>
            </div>
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-sky-500" style="width: {{ $percent }}%"></div>
            </div>

            @if ($budget['budget'] !== null)
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-xs {{ $budget['isOver'] ? 'text-rose-700' : 'text-slate-500' }}">
                        残り（予算 ¥{{ number_format($budget['budget']) }}）
                    </span>
                    <span class="text-base font-bold tabular-nums {{ $budget['isOver'] ? 'text-rose-700' : 'text-emerald-600' }}">
                        @if ($budget['isOver'])
                            −¥{{ number_format(abs($budget['remaining'])) }}
                        @else
                            ¥{{ number_format($budget['remaining']) }}
                        @endif
                    </span>
                </div>
                @if ($budget['isOver'])
                    <p class="mt-1 text-xs font-semibold text-rose-700">予算を超えています</p>
                @endif
            @endif
        </div>

        <x-budget-bar :event="$event" :budget="$budget" :show-summary="false" />

        @if ($circles->isEmpty() && $personal->isEmpty())
            <x-empty-state message="あなたが回るサークルはありません"
                           hint="共同購入の担当になるか、購入希望を登録するとここに表示されます。">
                <x-button :href="route('purchases.shared.index', $event)" size="sm">共同購入リストを見る</x-button>
            </x-empty-state>
        @endif

        {{-- 担当サークル --}}
        @foreach ($circles as $row)
            @php $circle = $row['circle']; @endphp
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm {{ $row['done'] ? 'opacity-60' : '' }}">
                <header class="flex items-start justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold">
                            <span class="mr-1 text-sm text-slate-400 tabular-nums">{{ $loop->iteration }}.</span>
                            {{ $circle?->display_name }}
                        </p>
                        <p class="mt-0.5 text-sm font-semibold text-sky-700">
                            {{ $circle?->locationLabel() }}
                            @if ($circle?->sellout_risk)
                                <x-badge :class="$circle->sellout_risk->badgeClass()">{{ $circle->sellout_risk->shortLabel() }}</x-badge>
                            @endif
                        </p>
                        @if ($circle?->mapImageUrl())
                            <a href="{{ route('circles.show', $circle) }}" class="mt-1 inline-block text-xs font-semibold text-sky-600 underline">
                                配置マップを見る{{ $circle->mapPin() ? '（目印あり）' : '' }}
                            </a>
                        @endif
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold tabular-nums">¥{{ number_format($row['amount']) }}</p>
                        @unless ($row['isCircleAssignee'])
                            <span class="block text-xs text-slate-500">担当商品のみ</span>
                        @endunless
                        @if ($row['done'])
                            <x-badge class="bg-emerald-100 text-emerald-800">完了</x-badge>
                        @else
                            <span class="text-xs text-slate-500">{{ $row['doneCount'] }}/{{ count($row['items']) }}</span>
                        @endif
                    </div>
                </header>

                <div class="divide-y divide-slate-100">
                    @foreach ($row['items'] as $line)
                        @php $item = $line['item']; $result = $line['result']; @endphp
                        <div class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $item->eventProduct?->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ $item->eventProduct?->priceLabel() }} × {{ $line['demand'] }}点
                                        @if ($line['split'])
                                            ・<span class="font-semibold text-sky-700">あなたの担当 {{ $line['myQuantity'] }}点</span>
                                        @endif
                                    </p>
                                </div>
                                @if ($result)
                                    <x-badge :class="$result->status->badgeClass()">
                                        {{ $result->status->label() }}（{{ $result->purchased_quantity }}点）
                                    </x-badge>
                                @endif
                            </div>

                            @if ($canRecord)
                                <div class="mt-3 space-y-2">
                                    @if (! $result)
                                        <div class="grid grid-cols-2 gap-2">
                                            <form method="POST" action="{{ route('shopping.items.planned', $item) }}">
                                                @csrf
                                                <x-button class="w-full whitespace-nowrap">買えた</x-button>
                                            </form>
                                            <form method="POST" action="{{ route('shopping.items.sold-out', $item) }}">
                                                @csrf
                                                <x-button variant="secondary" class="w-full whitespace-nowrap">買えなかった</x-button>
                                            </form>
                                        </div>
                                    @endif

                                    <a href="{{ route('results.edit', [$item, 'from' => 'shopping']) }}"
                                       class="block rounded-xl bg-slate-50 py-2 text-center text-xs font-semibold text-sky-600 hover:bg-sky-50">
                                        {{ $result ? '結果を修正する' : '一部だけ買えた（数量を入力）' }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($canRecord && ! $row['done'] && count($row['items']) > 1)
                    <form method="POST" action="{{ route('shopping.circles.planned', $row['sharedPurchase']) }}" class="border-t border-slate-100 p-3"
                          onsubmit="return confirm('このサークルの商品をすべて「予定どおり買えた」として登録します。よろしいですか？');">
                        @csrf
                        <x-button variant="subtle" class="w-full" size="sm">このサークルを全部「買えた」にする</x-button>
                    </form>
                @endif
            </section>
        @endforeach

        {{-- 自分で買う分 --}}
        @if ($personal->isNotEmpty())
            <h2 class="px-1 pt-2 text-sm font-semibold text-slate-700">自分で買う分</h2>

            @foreach ($personal as $row)
                @php $circle = $row['circle']; @endphp
                <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <header class="flex items-start justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold">{{ $circle?->display_name }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-sky-700">{{ $circle?->locationLabel() }}</p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold tabular-nums">¥{{ number_format($row['amount']) }}</p>
                    </header>

                    <div class="divide-y divide-slate-100">
                        @foreach ($row['items'] as $line)
                            @php $purchase = $line['purchase']; $result = $line['result']; @endphp
                            <div class="px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ $purchase->eventProduct?->name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ $purchase->eventProduct?->priceLabel() }} × {{ $purchase->planned_quantity }}点
                                        </p>
                                    </div>
                                    @if ($result)
                                        <x-badge :class="$result->status->badgeClass()">
                                            {{ $result->status->label() }}（{{ $result->purchased_quantity }}点）
                                        </x-badge>
                                    @endif
                                </div>

                                @if ($canRecord && ! $result)
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <form method="POST" action="{{ route('shopping.personal', [$purchase, 'bought']) }}">
                                            @csrf
                                            <x-button class="w-full whitespace-nowrap">買えた</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('shopping.personal', [$purchase, 'missed']) }}">
                                            @csrf
                                            <x-button variant="secondary" class="w-full whitespace-nowrap">買えなかった</x-button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
        @if ($circles->isNotEmpty())
            <x-card title="巡回ルート"
                    :subtitle="$isCustomRoute ? '自分で並べ替えた順で表示しています。' : '完売しやすいサークルを先に、あとは配置順に並べています。'">
                <details>
                    <summary class="cursor-pointer text-sm font-semibold text-sky-600">順番を並べ替える</summary>
                    <form method="POST" action="{{ route('shopping.route.save', $event) }}" class="mt-3" id="route-form">
                        @csrf
                        @method('PATCH')
                        <ul id="route-list" class="space-y-2">
                            @foreach ($circles as $row)
                                <li class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                                    <input type="hidden" name="circles[]" value="{{ $row['circle']?->id }}">
                                    <span class="min-w-0 flex-1 truncate text-sm">
                                        {{ $row['circle']?->locationLabel() }} {{ $row['circle']?->display_name }}
                                    </span>
                                    <button type="button" class="route-up flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                                            aria-label="{{ $row['circle']?->display_name }} を上へ">↑</button>
                                    <button type="button" class="route-down flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                                            aria-label="{{ $row['circle']?->display_name }} を下へ">↓</button>
                                </li>
                            @endforeach
                        </ul>
                        <x-button class="mt-3 w-full">この順番で保存</x-button>
                    </form>

                    @if ($isCustomRoute)
                        <form method="POST" action="{{ route('shopping.route.reset', $event) }}" class="mt-2"
                              onsubmit="return confirm('並べ替えを取り消して、おすすめの順に戻します。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" size="sm" class="w-full text-slate-500">おすすめの順に戻す</x-button>
                        </form>
                    @endif

                    <script>
                    (function () {
                        var list = document.getElementById('route-list');
                        if (!list) { return; }

                        list.addEventListener('click', function (event) {
                            var up = event.target.closest('.route-up');
                            var down = event.target.closest('.route-down');
                            if (!up && !down) { return; }

                            var item = (up || down).closest('li');
                            if (!item) { return; }

                            if (up && item.previousElementSibling) {
                                list.insertBefore(item, item.previousElementSibling);
                            } else if (down && item.nextElementSibling) {
                                list.insertBefore(item.nextElementSibling, item);
                            }
                        });
                    })();
                    </script>
                </details>

                <div class="mt-3 border-t border-slate-100 pt-3">
                    <label for="route-share" class="sr-only">巡回ルートの共有テキスト</label>
                    <textarea id="route-share" readonly rows="5"
                              class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-xs">{{ $routeShareText }}</textarea>
                    <button type="button" id="copy-route"
                            class="mt-2 w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700">
                        ルートをコピーする
                    </button>
                    <p class="mt-1 text-xs text-slate-500">チャットに貼り付けて、ほかの参加者と分担を確認できます。</p>
                    <script>
                    (function () {
                        var button = document.getElementById('copy-route');
                        var field = document.getElementById('route-share');
                        if (!button || !field) { return; }
                        button.addEventListener('click', function () {
                            field.select();
                            field.setSelectionRange(0, 99999);
                            var done = false;
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(field.value); done = true;
                            } else {
                                try { done = document.execCommand('copy'); } catch (e) { done = false; }
                            }
                            button.textContent = done ? 'コピーしました' : '長押しでコピーしてください';
                            window.setTimeout(function () { button.textContent = 'ルートをコピーする'; }, 2500);
                        });
                    })();
                    </script>
                </div>
            </x-card>
        @endif

        <div id="wake-lock-box" class="hidden rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" id="wake-lock-toggle" class="h-4 w-4 rounded border-slate-300">
                買い物中は画面を消さない
            </label>
            <p class="mt-1 text-xs text-slate-500">列に並んでいる間も画面が点いたままになります。電池の消費が増えます。</p>
        </div>
    </div>

    {{-- 圏外になったときに備えて、いま表示している内容を控えておく（タブを閉じると消える） --}}
    @php
        $snapshot = [
            'event' => $event->name,
            'savedAt' => now()->translatedFormat('Y/m/d (D) H:i'),
            'progress' => $progress,
            'budget' => $budget['budget'] === null ? null : [
                'remaining' => $budget['remaining'],
                'isOver' => $budget['isOver'],
            ],
            'circles' => $circles->map(function ($row) {
                return [
                    'name' => $row['circle']?->display_name,
                    'booth' => $row['circle']?->locationLabel(),
                    'done' => $row['done'],
                    'items' => collect($row['items'])->map(function ($item) {
                        return [
                            'name' => $item['item']->eventProduct?->name,
                            'quantity' => $item['myQuantity'],
                            'done' => $item['result'] !== null,
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    @endphp

    <script type="application/json" id="shopping-snapshot">{!! json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <script>
    (function () {
        var node = document.getElementById('shopping-snapshot');
        if (!node) { return; }

        try {
            window.sessionStorage.setItem('shopping-snapshot', node.textContent);
        } catch (e) { /* 保存できなくても表示には影響しない */ }
    })();
    </script>

    <script>
    (function () {
        if (!('wakeLock' in navigator)) { return; }

        var box = document.getElementById('wake-lock-box');
        var toggle = document.getElementById('wake-lock-toggle');
        if (!box || !toggle) { return; }

        box.classList.remove('hidden');
        var lock = null;

        function release() {
            if (lock) { lock.release().catch(function () {}); lock = null; }
        }

        function request() {
            navigator.wakeLock.request('screen').then(function (received) {
                lock = received;
                lock.addEventListener('release', function () { lock = null; });
            }).catch(function () {
                toggle.checked = false;
            });
        }

        toggle.addEventListener('change', function () {
            if (toggle.checked) { request(); } else { release(); }
        });

        // 画面を戻したときに取り直す
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && toggle.checked && !lock) { request(); }
        });
    })();
    </script>
</x-app-layout>
