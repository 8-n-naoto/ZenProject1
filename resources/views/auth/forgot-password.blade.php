<x-guest-layout title="パスワードの再設定">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <p class="text-sm text-slate-600">
            登録したメールアドレスを入力してください。パスワード再設定用のリンクをお送りします。
        </p>

        <x-input name="email" label="メールアドレス" type="email" required autofocus autocomplete="email" />

        <x-button class="w-full" size="lg">再設定メールを送信</x-button>
    </form>

    <x-slot:footer>
        <p class="mt-4 text-center text-sm text-slate-600">
            <a href="{{ route('login') }}" class="font-semibold text-sky-600 underline">ログイン画面へ戻る</a>
        </p>
    </x-slot:footer>
</x-guest-layout>
