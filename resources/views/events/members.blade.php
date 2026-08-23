<x-app-layout title="参加者の管理" heading="参加者の管理" :back="route('events.show', $event)">
    <div class="space-y-4">
        <x-card title="参加者" :subtitle="$event->participants->count() . '人'">
            @forelse ($event->participants as $participant)
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <x-avatar :user="$participant" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $participant->displayName() }}</p>
                        <p class="truncate text-xs text-slate-500">&#64;{{ $participant->user_id }}</p>
                    </div>
                    <form method="POST" action="{{ route('events.members.remove', [$event, $participant]) }}"
                          onsubmit="return confirm('{{ $participant->displayName() }} さんをこのイベントの参加者から外します。よろしいですか？');">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" size="sm" class="text-rose-600">参加を外す</x-button>
                    </form>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">まだ参加者がいません。</p>
            @endforelse
        </x-card>

        <x-card title="未参加のメンバー" subtitle="責任者が代理で追加できます。">
            @forelse ($candidates as $candidate)
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <x-avatar :user="$candidate" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $candidate->displayName() }}</p>
                        <p class="truncate text-xs text-slate-500">&#64;{{ $candidate->user_id }}</p>
                    </div>
                    <form method="POST" action="{{ route('events.members.add', [$event, $candidate]) }}">
                        @csrf
                        <x-button size="sm">追加</x-button>
                    </form>
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">全員が参加しています。</p>
            @endforelse
        </x-card>
    </div>
</x-app-layout>
