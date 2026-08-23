<x-app-layout title="メンバーを招待" heading="メンバーを招待" :back="route('groups.show', $group)">
    <div class="space-y-4">
        <x-card>
            <form method="GET" action="{{ route('groups.search-users', $group) }}" class="space-y-3">
                <x-input name="q" label="ログインIDで検索" :value="$keyword" placeholder="例: taro123" hint="ログインIDの前方一致で検索します。" />
                <x-button class="w-full">検索</x-button>
            </form>
        </x-card>

        @if ($keyword !== '')
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">検索結果（{{ $users->count() }}件）</h2>

                @forelse ($users as $user)
                    <div class="mb-2 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm">
                        <x-avatar :user="$user" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $user->displayName() }}</p>
                            <p class="truncate text-xs text-slate-500">&#64;{{ $user->user_id }}</p>
                        </div>
                        <form method="POST" action="{{ route('groups.invite', [$group, $user]) }}">
                            @csrf
                            <x-button size="sm">招待</x-button>
                        </form>
                    </div>
                @empty
                    <x-empty-state message="該当するユーザーが見つかりません" hint="すでにメンバーの人・招待済みの人は表示されません。" />
                @endforelse
            </section>
        @endif
    </div>
</x-app-layout>
