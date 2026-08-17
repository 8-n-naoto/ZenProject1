<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メモ編集</title>
</head>
<body>
    <h1>メモを編集する</h1>
    <form action="{{ route('update', $memo) }}" method="post">
        @csrf
        @method('PATCH')
        <label for="memo">メモ</label>
        <textarea name="memo" id="memo" rows="5" required maxlength="1000">{{ old('memo', $memo->memo) }}</textarea>
        @error('memo')
            <p role="alert">{{ $message }}</p>
        @enderror
        <button type="submit">更新する</button>
    </form>
    <a href="{{ route('show', $memo) }}">詳細に戻る</a>
</body>
</html>
