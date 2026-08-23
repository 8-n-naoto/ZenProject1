<x-app-layout title="商品を編集" heading="商品を編集" :back="route('circles.show', $circle)">
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')
        @include('products._form', ['product' => $product])
        <x-button class="w-full" size="lg">変更を保存</x-button>
    </form>

    <x-card title="商品の削除" class="mt-4">
        <p class="mb-3 text-xs text-slate-500">購入希望が登録されている場合は削除できません。</p>
        <form method="POST" action="{{ route('products.destroy', $product) }}"
              onsubmit="return confirm('「{{ $product->name }}」を削除します。よろしいですか？');">
            @csrf
            @method('DELETE')
            <x-button variant="danger" class="w-full">この商品を削除する</x-button>
        </form>
    </x-card>
</x-app-layout>
