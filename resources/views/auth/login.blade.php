<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
</head>
<body>
    <h1>ログイン</h1>

    @if (session('message'))
        <div>
            {{ session('message') }}
        </div>
    @endif

    @if ($errors->any())
        <div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div>
            <label for="user_id">ユーザーID</label>
            <input
                type="text"
                id="user_id"
                name="user_id"
                value="{{ old('user_id') }}"
                required
                autofocus
            >
        </div>

        <div>
            <label for="password">パスワード</label>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <div>
            <label>
                <input type="checkbox" name="remember" value="1">
                ログイン状態を保持する
            </label>
        </div>

        <button type="submit">ログイン</button>
    </form>

    <p>
        <a href="{{ route('register') }}">新規登録はこちら</a>
    </p>
</body>
</html>
