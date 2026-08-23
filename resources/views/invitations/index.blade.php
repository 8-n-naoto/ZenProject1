<x-app-layout title="招待" heading="招待">
    <div class="space-y-4">
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">返答待ち（{{ $pending->count() }}件）</h2>

            @forelse ($pending as $invitation)
                <div class="mb-2 rounded-2xl bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold">{{ $invitation->group->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $invitation->inviter?->displayName() ?? '不明なユーザー' }} さんからの招待
                        ・{{ $invitation->created_at->format('Y/m/d H:i') }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="{{ route('invitations.accept', $invitation) }}" class="flex-1">
                            @csrf
                            <x-button class="w-full" size="sm">参加する</x-button>
                        </form>
                        <form method="POST" action="{{ route('invitations.decline', $invitation) }}" class="flex-1"
                              onsubmit="return confirm('この招待を辞退します。参加するには招待し直してもらう必要があります。よろしいですか？');">
                            @csrf
                            <x-button variant="secondary" class="w-full" size="sm">辞退する</x-button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state message="返答待ちの招待はありません" />
            @endforelse
        </section>

        @if ($history->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">これまでの招待</h2>
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    @foreach ($history as $invitation)
                        <div class="flex items-center gap-3 border-b border-slate-100 p-4 last:border-b-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm">{{ $invitation->group->name }}</p>
                                <p class="text-xs text-slate-500">{{ optional($invitation->responded_at)->format('Y/m/d H:i') }}</p>
                            </div>
                            <x-badge :class="$invitation->status->badgeClass()">{{ $invitation->status->label() }}</x-badge>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
