<x-guest-layout title="ログイン">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-input name="user_id" label="ログインID" required autocomplete="username" autofocus />
        <x-input name="password" label="パスワード" type="password" required autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300">
            ログイン状態を保持する
        </label>

        <x-button class="w-full" size="lg">ログイン</x-button>

        <p class="text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-slate-500 underline">パスワードをお忘れですか？</a>
        </p>
    </form>

    <x-slot:footer>
        <p class="mt-4 text-center text-sm text-slate-600">
            アカウントをお持ちでない方は
            <a href="{{ route('register') }}" class="font-semibold text-sky-600 underline">新規登録</a>
        </p>
        <p class="mt-2 text-center text-sm text-slate-600">
            招待を受け取った方は
            <a href="{{ route('join.form') }}" class="font-semibold text-sky-600 underline">招待から参加</a>
        </p>
    </x-slot:footer>
</x-guest-layout>
