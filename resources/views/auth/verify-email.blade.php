<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メールアドレス確認</title>
</head>
<body>
    <h1>メールアドレス確認</h1>

    <p>登録したメールアドレスに確認メールを送信しました。</p>
    <p>メールに記載されたリンクをクリックして、メールアドレスの確認を完了してください。</p>

    @if (session('message'))
        <div>
            {{ session('message') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">確認メールを再送信</button>
    </form>
</body>
</html>
