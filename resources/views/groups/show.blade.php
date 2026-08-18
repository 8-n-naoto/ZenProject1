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

    <p><a href="{{ route('groups.index') }}">グループ一覧へ戻る</a></p>
</body>
</html>
