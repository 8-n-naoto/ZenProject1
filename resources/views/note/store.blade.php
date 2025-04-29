<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>create:memo</title>
</head>
<body>
    <p>新規作成</p>
    <p>作成内容</p>
    <form action="{{ route('create') }}" method="post">
        @csrf
        <label for="memo"><input type="text" name="memo" id="memo" placeholder="入力してください"></label>
        <button>追加する</button>
    </form>
</body>
</html>
