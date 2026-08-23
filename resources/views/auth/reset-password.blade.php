<x-guest-layout title="新しいパスワードの設定">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-input name="email" label="メールアドレス" type="email" :value="$email" required autocomplete="email" />
        <x-input name="password" label="新しいパスワード" type="password" required autocomplete="new-password" hint="8文字以上。" />
        <x-input name="password_confirmation" label="新しいパスワード（確認）" type="password" required autocomplete="new-password" />

        <x-button class="w-full" size="lg">パスワードを再設定</x-button>
    </form>
</x-guest-layout>
