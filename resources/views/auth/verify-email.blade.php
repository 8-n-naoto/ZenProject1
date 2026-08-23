<x-guest-layout title="メールアドレスの確認">
    <div class="space-y-4">
        <p class="text-sm text-slate-600">
            登録したメールアドレス宛に認証リンクを送信しました。
            メール内のリンクを開くと、ご利用を開始できます。
        </p>
        <p class="text-sm text-slate-500">
            メールが届かない場合は、迷惑メールフォルダをご確認のうえ、下のボタンから再送信してください。
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button class="w-full">認証メールを再送信</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="ghost" class="w-full">ログアウト</x-button>
        </form>
    </div>
</x-guest-layout>
