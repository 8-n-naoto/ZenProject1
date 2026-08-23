<x-app-layout title="サークルを編集" heading="サークルを編集" :back="route('circles.show', $circle)">
    <form method="POST" action="{{ route('circles.update', $circle) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')
        @include('circles._form', ['circle' => $circle])
        <x-button class="w-full" size="lg">変更を保存</x-button>
    </form>

    <x-card title="サークルの削除" class="mt-4">
        <p class="mb-3 text-xs text-slate-500">購入希望が登録されている場合は削除できません。</p>
        <form method="POST" action="{{ route('circles.destroy', $circle) }}"
              onsubmit="return confirm('「{{ $circle->display_name }}」を削除します。登録済みの商品も削除されます。よろしいですか？');">
            @csrf
            @method('DELETE')
            <x-button variant="danger" class="w-full">このサークルを削除する</x-button>
        </form>
    </x-card>
</x-app-layout>
