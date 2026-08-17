<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メモ作成</title>
</head>
<body>
    <h1>メモを作成する</h1>
    <form action="{{ route('create') }}" method="post">
        @csrf
        <label for="memo">メモ</label>
        <textarea name="memo" id="memo" rows="5" required maxlength="1000">{{ old('memo') }}</textarea>
        @error('memo')
            <p role="alert">{{ $message }}</p>
        @enderror
        <button type="submit">作成する</button>
    </form>
    <a href="{{ route('top') }}">一覧に戻る</a>
</body>
</html>
