<x-app-layout :title="$group->name . ' のイベント'" heading="イベント" :back="route('groups.show', $group)">
    <x-slot:actions>
        @can('create', [App\Models\Event::class, $group])
            <a href="{{ route('events.create', $group) }}" class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-sky-600 px-3 text-xs font-semibold text-white hover:bg-sky-700">
                ＋ 作成
            </a>
        @endcan
    </x-slot:actions>

    <div class="space-y-4">
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">進行中のイベント</h2>

            @forelse ($upcoming as $event)
                <x-event-card :event="$event" class="mb-2" />
            @empty
                <x-empty-state message="進行中のイベントはありません">
                    @can('create', [App\Models\Event::class, $group])
                        <x-button :href="route('events.create', $group)" size="sm">イベントを作成する</x-button>
                    @endcan
                </x-empty-state>
            @endforelse
        </section>

        @if ($past->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">完了したイベント</h2>
                @foreach ($past as $event)
                    <x-event-card :event="$event" class="mb-2" />
                @endforeach
            </section>
        @endif
    </div>
</x-app-layout>
