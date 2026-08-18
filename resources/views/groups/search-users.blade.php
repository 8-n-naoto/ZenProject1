<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー検索・招待</title>
</head>
<body>
    <h1>ユーザー検索・招待</h1>

    <p>グループ：{{ $group->name }}</p>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="GET" action="{{ route('groups.search-users', $group) }}">
        <label for="q">ユーザーID</label>
        <input id="q" name="q" type="text" value="{{ $keyword }}" required>
        <button type="submit">検索</button>
    </form>

    @if ($keyword !== '')
        <h2>検索結果</h2>

        @forelse ($users as $user)
            <div>
                <strong>{{ $user->name }}</strong>
                <span>（{{ $user->user_id }}）</span>

                <form method="POST" action="{{ route('groups.invite', [$group, $user]) }}">
                    @csrf
                    <button type="submit">招待する</button>
                </form>
            </div>
        @empty
            <p>招待可能なユーザーが見つかりません。</p>
        @endforelse
    @endif

    <p><a href="{{ route('groups.show', $group) }}">グループ詳細へ戻る</a></p>
</body>
</html>