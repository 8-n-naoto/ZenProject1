@php $event = null; @endphp

<x-app-layout title="イベントを複製" heading="イベントを複製" :back="route('events.show', $source)">
    <form method="POST" action="{{ route('events.duplicate', $source) }}" class="space-y-4">
        @csrf

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
            「{{ $source->name }}」のサークル {{ $source->eventCircles->count() }}件・商品
            {{ $source->eventCircles->sum(fn ($c) => $c->eventProducts->count()) }}件 を引き継いで、新しいイベントを作成します。
            購入希望・担当者・購入結果は引き継ぎません。
        </div>

        @include('events._form', ['event' => null])

        <x-button class="w-full" size="lg">この内容で複製する</x-button>
    </form>
</x-app-layout>
