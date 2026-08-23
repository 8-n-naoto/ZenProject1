@php $userId = auth()->id(); @endphp

<x-app-layout title="精算" heading="精算" :back="route('events.show', $event)">
    <div class="space-y-4">
        @if ($toPay->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">あなたが支払う</h2>
                @foreach ($toPay as $settlement)
                    @include('settlements._row', ['settlement' => $settlement, 'direction' => 'pay'])
                @endforeach
            </section>
        @endif

        @if ($toReceive->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">あなたが受け取る</h2>
                @foreach ($toReceive as $settlement)
                    @include('settlements._row', ['settlement' => $settlement, 'direction' => 'receive'])
                @endforeach
            </section>
        @endif

        @if ($toPay->isEmpty() && $toReceive->isEmpty())
            <x-empty-state message="あなたに関係する精算はありません"
                           hint="購入結果がすべて登録され、精算が開始されるとここに表示されます。" />
        @endif

        <x-card title="全員の精算状況">
            @forelse ($settlements as $settlement)
                <div class="flex items-center gap-2 border-b border-slate-100 py-3 text-sm last:border-b-0">
                    <span class="min-w-0 flex-1 truncate">
                        {{ $settlement->payer?->displayName() ?? '不明なユーザー' }}
                        <span class="text-slate-400">→</span>
                        {{ $settlement->payee?->displayName() ?? '不明なユーザー' }}
                    </span>
                    <span class="shrink-0 tabular-nums">{{ $settlement->amountLabel() }}</span>
                    <x-badge :class="$settlement->status->badgeClass()">{{ $settlement->status->label() }}</x-badge>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">精算はありません。</p>
            @endforelse
        </x-card>

        <x-card title="収支の内訳" subtitle="立て替えた額と、自分の購入分の差額です。名前を押すと明細を確認できます。">
            @forelse ($summary as $row)
                <a href="{{ route('settlements.breakdown', [$event, $row['user']]) }}"
                   class="-mx-2 flex items-center gap-3 rounded-xl border-b border-slate-100 px-2 py-3 last:border-b-0 hover:bg-slate-50">
                    <x-avatar :user="$row['user']" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $row['user']?->displayName() ?? '不明なユーザー' }}</p>
                        <p class="text-xs text-slate-500 tabular-nums">
                            立替 ¥{{ number_format($row['spent']) }}・購入 ¥{{ number_format($row['owed']) }}
                        </p>
                    </div>
                    <span class="shrink-0 text-sm font-semibold tabular-nums {{ $row['net'] > 0 ? 'text-emerald-600' : ($row['net'] < 0 ? 'text-rose-600' : 'text-slate-400') }}">
                        {{ $row['net'] > 0 ? '+' : ($row['net'] < 0 ? '-' : '') }}¥{{ number_format(abs($row['net'])) }}
                    </span>
                    <span aria-hidden="true" class="shrink-0 text-slate-300">›</span>
                </a>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">収支の対象になる購入結果がまだありません。</p>
            @endforelse
        </x-card>

        <x-card title="まとめを共有する" subtitle="チャットに貼り付けられる形式です。">
            <label for="share-text" class="sr-only">精算のまとめ</label>
            <textarea id="share-text" readonly rows="6"
                      class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-xs">{{ $shareText }}</textarea>
            <button type="button" id="copy-share" class="mt-2 w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                コピーする
            </button>
            <script>
            (function () {
                var button = document.getElementById('copy-share');
                var area = document.getElementById('share-text');
                if (!button || !area) { return; }
                button.addEventListener('click', function () {
                    area.select();
                    area.setSelectionRange(0, 99999);
                    var done = false;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(area.value).then(function () {}, function () {});
                        done = true;
                    } else {
                        try { done = document.execCommand('copy'); } catch (e) { done = false; }
                    }
                    button.textContent = done ? 'コピーしました' : '長押しでコピーしてください';
                    window.setTimeout(function () { button.textContent = 'コピーする'; }, 2500);
                });
            })();
            </script>
        </x-card>

        <x-card title="記録をダウンロード">
            <p class="mb-3 text-xs text-slate-500">精算リストをCSV（Excelで開けます）で保存できます。</p>
            <a href="{{ route('events.export.settlements', $event) }}"
               class="block w-full rounded-xl border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700">
                精算リストをCSVで保存
            </a>
        </x-card>

        @if ($canRegenerate)
            <x-card title="精算リストの作り直し">
                <p class="mb-3 text-xs text-slate-500">
                    購入結果を修正した場合は、精算リストを作り直してください。
                    精算済みの記録がある場合は作り直せません。
                </p>
                <form method="POST" action="{{ route('settlements.regenerate', $event) }}"
                      onsubmit="return confirm('精算リストを作り直します。報告済みの支払いは取り消されます。よろしいですか？');">
                    @csrf
                    <x-button variant="secondary" class="w-full">精算リストを作り直す</x-button>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>
