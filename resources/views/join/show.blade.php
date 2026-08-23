<x-guest-layout title="グループへの招待">
    <h1 class="mb-1 text-base font-semibold">グループへの招待</h1>

    <div class="my-4 rounded-2xl border border-slate-200 p-4 text-center">
        <div class="flex justify-center"><x-group-icon :group="$group" size="lg" /></div>
        <p class="mt-2 text-base font-semibold">{{ $group->name }}</p>
        @if ($group->description)
            <p class="mt-1 text-xs text-slate-500">{{ $group->description }}</p>
        @endif
        <p class="mt-2 text-xs text-slate-500">メンバー {{ $group->activeMembers()->count() }}人</p>
    </div>

    @if ($reason)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
            {{ $reason }}
        </div>
        <p class="mt-3 text-center text-xs text-slate-500">
            グループの責任者に、新しい招待リンクを発行してもらってください。
        </p>
    @elseif ($alreadyMember)
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
            すでにこのグループのメンバーです。
        </div>
        <a href="{{ route('groups.show', $group) }}"
           class="mt-4 block w-full rounded-xl bg-sky-600 px-4 py-3 text-center text-base font-semibold text-white">
            グループを開く
        </a>
    @elseif (auth()->check())
        <form method="POST" action="{{ route('join.store', $link->token) }}">
            @csrf
            <x-button class="w-full" size="lg">このグループに参加する</x-button>
        </form>
        <p class="mt-3 text-center text-xs text-slate-500">
            参加すると、グループのイベントと購入リストを見られるようになります。
        </p>
    @else
        <p class="mb-4 text-center text-sm text-slate-600">
            参加するにはログインが必要です。<br>
            アカウントをお持ちでない場合は、新規登録するとそのまま参加できます。
        </p>
        <a href="{{ route('login') }}"
           class="block w-full rounded-xl bg-sky-600 px-4 py-3 text-center text-base font-semibold text-white">
            ログインして参加する
        </a>
        <a href="{{ route('register') }}"
           class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-base font-semibold text-slate-700">
            新規登録して参加する
        </a>
    @endif
</x-guest-layout>
