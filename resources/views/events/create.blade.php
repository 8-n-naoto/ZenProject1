@php $event = null; @endphp

<x-app-layout title="イベントを作成" heading="イベントを作成" :back="route('events.index', $group)">
    <form method="POST" action="{{ route('events.store', $group) }}" class="space-y-4">
        @csrf

        @include('events._form', ['event' => null])

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
            作成直後は <span class="font-semibold">準備中</span> です。内容を整えてから「受付を開始」すると、メンバーが参加表明と購入希望の登録をできるようになります。
        </div>

        <x-button class="w-full" size="lg">イベントを作成</x-button>
    </form>
</x-app-layout>
