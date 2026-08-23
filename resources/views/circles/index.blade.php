<x-app-layout title="サークル・商品" heading="サークル・商品" :back="route('events.show', $event)">
    <x-slot:actions>
        @if ($canEdit)
            <a href="{{ route('circles.create', $event) }}" class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-sky-600 px-3 text-xs font-semibold text-white hover:bg-sky-700">
                ＋ 追加
            </a>
        @endif
    </x-slot:actions>

    <div class="space-y-3">
        <form method="GET" action="{{ route('circles.index', $event) }}" class="rounded-2xl bg-white p-3 shadow-sm">
            <div class="flex gap-2">
                <label for="circle-search" class="sr-only">サークルを検索</label>
                <input type="search" id="circle-search" name="q" value="{{ $keyword }}" placeholder="サークル名・配置・商品名で検索"
                       class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <x-button size="sm" class="shrink-0">検索</x-button>
            </div>
            <div class="mt-2 flex gap-1">
                @foreach (['booth' => '配置順', 'name' => '名前順', 'newest' => '登録が新しい順'] as $value => $label)
                    <button type="submit" name="sort" value="{{ $value }}"
                            class="flex-1 rounded-lg px-2 py-1.5 text-xs font-semibold {{ $sort === $value ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </form>

        @if ($canEdit)
            <a href="{{ route('circles.bulk.form', $event) }}"
               class="block rounded-2xl border border-dashed border-slate-300 bg-white py-3 text-center text-sm font-semibold text-sky-600 hover:bg-slate-50">
                テキストからまとめて登録する
            </a>
        @endif

        <div class="px-1 text-xs text-slate-500">
            {{ $event->name }}・{{ $circles->count() }}サークル / 商品 {{ $circles->sum(fn ($c) => $c->eventProducts->count()) }}件
            @if ($keyword !== '')
                <a href="{{ route('circles.index', $event) }}" class="-my-2 ml-1 inline-flex min-h-10 items-center px-2 font-semibold text-sky-600">検索を解除</a>
            @endif
        </div>

        @forelse ($circles as $circle)
            <a href="{{ route('circles.show', $circle) }}" class="block rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $circle->display_name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $circle->locationLabel() }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-slate-400">商品 {{ $circle->eventProducts->count() }}件</span>
                </div>

                @if ($circle->eventProducts->isNotEmpty())
                    <div class="mt-2 space-y-1">
                        @foreach ($circle->eventProducts->take(3) as $product)
                            <div class="flex items-center justify-between text-xs text-slate-600">
                                <span class="truncate">{{ $product->name }}</span>
                                <span class="shrink-0 tabular-nums">{{ $product->priceLabel() }}</span>
                            </div>
                        @endforeach
                        @if ($circle->eventProducts->count() > 3)
                            <p class="text-xs text-slate-400">ほか{{ $circle->eventProducts->count() - 3 }}件</p>
                        @endif
                    </div>
                @endif
            </a>
        @empty
            <x-empty-state :message="$keyword !== '' ? '該当するサークルがありません' : 'サークルがまだ登録されていません'" hint="購入したいサークルを登録して、商品を追加しましょう。">
                @if ($canEdit)
                    <x-button :href="route('circles.create', $event)" size="sm">サークルを登録する</x-button>
                @endif
            </x-empty-state>
        @endforelse
    </div>
</x-app-layout>
