<x-app-layout title="お知らせ" heading="お知らせ">
    <x-slot:actions>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="-my-2 inline-flex min-h-10 shrink-0 items-center px-2 text-xs font-semibold text-sky-600">すべて既読</button>
            </form>
        @endif
    </x-slot:actions>

    <div class="mb-3 flex gap-1">
        <a href="{{ route('notifications.index') }}"
           class="flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold {{ $unreadOnly ? 'bg-slate-100 text-slate-600' : 'bg-sky-600 text-white' }}">
            すべて
        </a>
        <a href="{{ route('notifications.index', ['unread' => 1]) }}"
           class="flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold {{ $unreadOnly ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600' }}">
            未読のみ（{{ $unreadCount }}）
        </a>
    </div>

    @if (auth()->user()->pendingReceivedInvitations()->exists())
        <a href="{{ route('invitations.index') }}" class="mb-3 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="font-semibold">未返答のグループ招待があります</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    @endif

    @forelse ($notifications as $notification)
        @php $url = $notification->url(); @endphp
        {{-- リンク先が無いお知らせは、押せそうに見えないよう div で描画する --}}
        <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif
           class="mb-2 block rounded-2xl p-4 shadow-sm {{ $notification->isUnread() ? 'bg-sky-50' : 'bg-white' }}">
            <div class="flex items-start gap-2">
                @if ($notification->isUnread())
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-sky-500"></span>
                @else
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-transparent"></span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm">{{ $notification->message() }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $notification->notified_at?->format('Y/m/d H:i') }}</p>
                </div>
            </div>
        </{{ $url ? 'a' : 'div' }}>
    @empty
        <x-empty-state :message="$unreadOnly ? '未読のお知らせはありません' : 'お知らせはありません'" />
    @endforelse

    <x-paginator :paginator="$notifications" />
</x-app-layout>
