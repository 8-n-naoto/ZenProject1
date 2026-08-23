<x-app-layout title="イベントを編集" heading="イベントを編集" :back="route('events.show', $event)">
    <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-4">
        @csrf
        @method('PATCH')

        @include('events._form', ['event' => $event])

        <x-button class="w-full" size="lg">変更を保存</x-button>
    </form>

    @can('delete', $event)
        <x-card title="危険な操作" class="mt-4">
            <p class="mb-3 text-xs text-slate-500">準備中のイベントのみ削除できます。</p>
            <form method="POST" action="{{ route('events.destroy', $event) }}"
                  onsubmit="return confirm('イベント「{{ $event->name }}」を削除します。よろしいですか？');">
                @csrf
                @method('DELETE')
                <x-button variant="danger" class="w-full">イベントを削除する</x-button>
            </form>
        </x-card>
    @endcan
</x-app-layout>
