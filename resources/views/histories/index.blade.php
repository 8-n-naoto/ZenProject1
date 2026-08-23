<x-app-layout title="変更履歴" heading="変更履歴" :back="route('events.show', $event)">
    <x-card>
        @forelse ($histories as $history)
            <div class="flex gap-3 border-b border-slate-100 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <p class="text-sm">{{ $history->description() }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $history->actor?->displayName() ?? 'システム' }}
                        ・{{ $history->occurred_at?->format('Y/m/d H:i') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="py-3 text-center text-xs text-slate-500">履歴はまだありません。</p>
        @endforelse
    </x-card>

    <x-paginator :paginator="$histories" />
</x-app-layout>
