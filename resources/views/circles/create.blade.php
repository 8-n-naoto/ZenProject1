<x-app-layout title="サークルを登録" heading="サークルを登録" :back="route('circles.index', $event)">
    <form method="POST" action="{{ route('circles.store', $event) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @include('circles._form', ['circle' => null])
        <x-button class="w-full" size="lg">登録する</x-button>
    </form>
</x-app-layout>
