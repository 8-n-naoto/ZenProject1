<x-guest-layout title="新規登録">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf

        <x-input name="user_id" label="ログインID" required autocomplete="username" autofocus
                 hint="英小文字と数字のみ、5〜15文字。他の人があなたを探すときに使います。" />
        <x-input name="name" label="表示名" required autocomplete="name" hint="グループ内で表示される名前です。" />
        <x-input name="email" label="メールアドレス" type="email" required autocomplete="email" />
        <x-input name="password" label="パスワード" type="password" required autocomplete="new-password" hint="8文字以上。" />
        <x-input name="password_confirmation" label="パスワード（確認）" type="password" required autocomplete="new-password" />

        <x-button class="w-full" size="lg">登録する</x-button>
    </form>

    <x-slot:footer>
        <p class="mt-4 text-center text-sm text-slate-600">
            すでにアカウントをお持ちの方は
            <a href="{{ route('login') }}" class="font-semibold text-sky-600 underline">ログイン</a>
        </p>
        <p class="mt-2 text-center text-sm text-slate-600">
            招待を受け取った方は
            <a href="{{ route('join.form') }}" class="font-semibold text-sky-600 underline">招待から参加</a>
        </p>
    </x-slot:footer>
</x-guest-layout>
