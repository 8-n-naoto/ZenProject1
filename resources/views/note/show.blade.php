<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メモ詳細</title>
</head>
<body>
    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    <h1>メモ詳細</h1>
    <p>{{ $memo->memo }}</p>
    <a href="{{ route('edit', $memo) }}">編集する</a>
    <form action="{{ route('destroy', $memo) }}" method="post">
        @csrf
        @method('DELETE')
        <button type="submit">削除する</button>
    </form>
    <a href="{{ route('top') }}">一覧に戻る</a>
</body>
</html>
