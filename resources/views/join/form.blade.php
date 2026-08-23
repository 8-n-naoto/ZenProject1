<x-guest-layout title="招待から参加">
    <h1 class="mb-1 text-base font-semibold">招待から参加する</h1>
    <p class="mb-4 text-xs text-slate-500">
        グループの責任者から受け取った招待リンク、または合い言葉を入力してください。
    </p>

    <form method="POST" action="{{ route('join.lookup') }}" class="space-y-4">
        @csrf
        <x-input name="token" label="合い言葉" required maxlength="64"
                 placeholder="例: k3f9qm2xv8hb"
                 hint="招待リンクの末尾の文字列です。" />
        <x-button class="w-full" size="lg">確認する</x-button>
    </form>

    <x-slot:footer>
        <p class="mt-6 text-center text-sm text-slate-500">
            @auth
                <a href="{{ route('dashboard') }}" class="font-semibold text-sky-600">ホームに戻る</a>
            @else
                <a href="{{ route('login') }}" class="font-semibold text-sky-600">ログイン</a>
                ・
                <a href="{{ route('register') }}" class="font-semibold text-sky-600">新規登録</a>
            @endauth
        </p>
    </x-slot:footer>
</x-guest-layout>
