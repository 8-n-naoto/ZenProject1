<x-app-layout title="商品を登録" heading="商品を登録" :back="route('circles.show', $circle)">
    <form method="POST" action="{{ route('products.store', $circle) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @include('products._form', ['product' => null])
        <x-button class="w-full" size="lg">登録する</x-button>
    </form>
</x-app-layout>
