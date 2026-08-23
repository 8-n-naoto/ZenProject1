@php
    $tones = [
        'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-900',
    ];
@endphp

<x-app-layout title="ホーム" heading="ホーム">
    <div class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <x-avatar :user="auth()->user()" size="lg" />
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold">{{ auth()->user()->displayName() }}</p>
                    <p class="truncate text-xs text-slate-500">&#64;{{ auth()->user()->user_id }}</p>
                </div>
            </div>
        </section>

        {{-- やること --}}
        <section>
            <div class="mb-2 flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-slate-700">やること</h2>
                @if ($tasks->isNotEmpty())
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $tasks->count() }}</span>
                @endif
            </div>

            @forelse ($tasks as $task)
                <a href="{{ $task['url'] }}"
                   class="mb-2 flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 {{ $tones[$task['tone']] ?? $tones['sky'] }}">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">{{ $task['title'] }}</span>
                        <span class="mt-0.5 block truncate text-xs opacity-80">{{ $task['detail'] }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @empty
                <div class="rounded-2xl bg-white px-4 py-6 text-center shadow-sm">
                    <p class="text-sm text-slate-500">対応が必要なことはありません</p>
                </div>
            @endforelse
        </section>

        {{-- 未精算のまとめ --}}
        @if ($outstanding['toPay']->isNotEmpty() || $outstanding['toReceive']->isNotEmpty())
            <a href="{{ route('settlements.mine') }}" class="block rounded-2xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">未精算のまとめ</h2>
                    <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-xl bg-rose-50 px-3 py-2">
                        <p class="text-xs text-rose-700">支払う</p>
                        <p class="text-base font-bold tabular-nums text-rose-700">¥{{ number_format($outstanding['payTotal']) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2">
                        <p class="text-xs text-emerald-700">受け取る</p>
                        <p class="text-base font-bold tabular-nums text-emerald-700">¥{{ number_format($outstanding['receiveTotal']) }}</p>
                    </div>
                </div>
            </a>
        @endif

        {{-- 進行中のイベント --}}
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">進行中のイベント</h2>

            @forelse ($events as $event)
                <x-event-card :event="$event" class="mb-2" />
            @empty
                <x-empty-state message="進行中のイベントはありません" hint="グループの画面からイベントを作成できます。" />
            @endforelse
        </section>

        {{-- 参加中のグループ --}}
        <section>
            <div class="mb-2 flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-slate-700">参加中のグループ</h2>
                <a href="{{ route('groups.create') }}" class="text-xs font-semibold text-sky-600">＋ 新規作成</a>
            </div>

            @forelse ($groups as $group)
                @php $role = $roleOf($group); @endphp
                <a href="{{ route('groups.show', $group) }}" class="mb-2 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
                    <x-group-icon :group="$group" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ $group->name }}</span>
                        <span class="block text-xs text-slate-500">メンバー {{ $group->active_members_count }}人</span>
                    </span>
                    @if ($role)
                        <x-badge :class="$role->badgeClass()">{{ $role->label() }}</x-badge>
                    @endif
                </a>
            @empty
                <x-empty-state message="まだグループに参加していません" hint="グループを作成するか、招待を待ちましょう。">
                    <x-button :href="route('groups.create')" size="sm">グループを作成する</x-button>
                </x-empty-state>
            @endforelse
        </section>
    </div>
</x-app-layout>
