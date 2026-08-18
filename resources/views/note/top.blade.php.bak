<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メモ一覧</title>
</head>
<body>
    <h1>メモ一覧</h1>
    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <ul>
        @forelse ($memos as $memo)
            <li><a href="{{ route('show', $memo) }}">{{ $memo->memo }}</a></li>
        @empty
            <li>メモはまだありません。</li>
        @endforelse
    </ul>

    <a href="{{ route('store') }}">メモを作成する</a>
</body>
</html>
