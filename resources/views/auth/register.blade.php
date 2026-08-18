<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録</title>
</head>
<body>
    <h1>ユーザー登録</h1>

    @if ($errors->any())
        <div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <div>
            <label for="user_id">UID</label>
            <input type="text" id="user_id" name="user_id" value="{{ old('user_id') }}" required minlength="5" maxlength="15" pattern="[a-z0-9]+">
        </div>

        <div>
            <label for="name">名前</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
        </div>

        <div>
            <label for="email">メールアドレス</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255">
        </div>

        <div>
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <label for="password_confirmation">パスワード（確認）</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <button type="submit">登録</button>
    </form>
</body>
</html>