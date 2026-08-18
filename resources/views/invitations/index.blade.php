<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>招待一覧</title>
</head>
<body>
    <h1>招待一覧</h1>

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

    @forelse ($invitations as $invitation)
        <article>
            <h2>{{ $invitation->group->name }}</h2>

            <p>
                招待者：
                {{ $invitation->inviter->name }}
                （{{ $invitation->inviter->user_id }}）
            </p>

            <p>状態：{{ $invitation->status }}</p>

            @if ($invitation->status === 'pending')
                <form method="POST" action="{{ route('invitations.accept', $invitation) }}">
                    @csrf
                    <button type="submit">承認する</button>
                </form>

                <form method="POST" action="{{ route('invitations.decline', $invitation) }}">
                    @csrf
                    <button type="submit">辞退する</button>
                </form>
            @endif
        </article>
    @empty
        <p>招待はありません。</p>
    @endforelse

    <p><a href="{{ route('top') }}">ダッシュボードへ戻る</a></p>
</body>
</html>