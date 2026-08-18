<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }}</title>
</head>
<body>
    <h1>{{ $group->name }}</h1>

    @if ($group->description)
        <p>{{ $group->description }}</p>
    @endif

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <h2>メンバー</h2>

    <ul>
        @foreach ($group->members as $member)
            <li>
                {{ $member->name }}
                （{{ $member->user_id }}）
                - {{ $member->pivot->role }}
            </li>
        @endforeach
    </ul>

    @if (in_array($myRole, ['最高責任者', '責任者'], true))
        <p>
            <a href="{{ route('groups.search-users', $group) }}">ユーザーを検索して招待</a>
        </p>
    @endif

    <p><a href="{{ route('invitations.index') }}">招待一覧</a></p>
    <p><a href="{{ route('groups.index') }}">グループ一覧へ戻る</a></p>
</body>
</html>