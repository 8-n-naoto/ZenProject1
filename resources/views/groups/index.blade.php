<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>グループ一覧</title>
</head>
<body>
    <h1>グループ一覧</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('groups.create') }}">グループを作成</a></p>

    <ul>
        @forelse ($groups as $group)
            <li>
                <a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a>
            </li>
        @empty
            <li>所属しているグループはありません。</li>
        @endforelse
    </ul>

    <p><a href="{{ route('top') }}">ダッシュボードへ戻る</a></p>
</body>
</html>
