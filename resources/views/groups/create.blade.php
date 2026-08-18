<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>グループ作成</title>
</head>
<body>
    <h1>グループ作成</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('groups.store') }}">
        @csrf

        <div>
            <label for="name">グループ名</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required>
        </div>

        <div>
            <label for="description">説明</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="highest_responsible_user_id">最高責任者のユーザーID</label>
            <input id="highest_responsible_user_id" name="highest_responsible_user_id" type="number" value="{{ old('highest_responsible_user_id') }}" required>
        </div>

        <div>
            <label for="responsible_user_id">責任者のユーザーID</label>
            <input id="responsible_user_id" name="responsible_user_id" type="number" value="{{ old('responsible_user_id') }}" required>
        </div>

        <button type="submit">作成する</button>
    </form>

    <p><a href="{{ route('groups.index') }}">グループ一覧へ戻る</a></p>
</body>
</html>
