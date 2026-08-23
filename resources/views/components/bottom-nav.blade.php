@php
    $unread = auth()->check()
        ? auth()->user()->appNotifications()->unread()->count()
              + auth()->user()->pendingReceivedInvitations()->count()
        : 0;

    $items = [
        ['route' => 'dashboard', 'label' => 'ホーム', 'icon' => 'home', 'active' => request()->routeIs('dashboard')],
        ['route' => 'groups.index', 'label' => 'グループ', 'icon' => 'group', 'active' => request()->routeIs('groups.*') || request()->routeIs('events.*') || request()->routeIs('circles.*') || request()->routeIs('products.*') || request()->routeIs('purchases.*') || request()->routeIs('results.*') || request()->routeIs('settlements.*')],
        ['route' => 'notifications.index', 'label' => 'お知らせ', 'icon' => 'bell', 'active' => request()->routeIs('notifications.*') || request()->routeIs('invitations.*'), 'badge' => $unread],
        ['route' => 'profile.edit', 'label' => 'アカウント', 'icon' => 'user', 'active' => request()->routeIs('profile.*')],
    ];
@endphp

<nav data-app-nav class="fixed bottom-0 left-0 right-0 z-40 border-t border-slate-200 bg-white pb-safe">
    <div class="mx-auto flex max-w-3xl">
        @foreach ($items as $item)
            <a href="{{ route($item['route']) }}"
               @if ($item['active']) aria-current="page" @endif
               class="relative flex flex-1 flex-col items-center gap-1 py-2 text-xs {{ $item['active'] ? 'text-sky-600 font-semibold' : 'text-slate-500' }}">
                <span class="relative">
                    @switch($item['icon'])
                        @case('home')
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5M5 9.5V20h14V9.5"/></svg>
                            @break
                        @case('group')
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-3-3.87M9 20H2v-1a4 4 0 013-3.87m10.5-4.13a3 3 0 11-6 0 3 3 0 016 0zM19 8a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 8a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM16 20H8v-1a4 4 0 018 0v1z"/></svg>
                            @break
                        @case('bell')
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @break
                        @default
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 20a6.5 6.5 0 0113 0M15 8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    @endswitch

                    @if (($item['badge'] ?? 0) > 0)
                        <span class="absolute -right-3 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white">{{ min($item['badge'], 9) }}</span>
                    @endif
                </span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
