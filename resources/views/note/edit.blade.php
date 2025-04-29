<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>edit:memo{{ $memo->id }}</title>
</head>
<body>
    <p>更新内容</p>
    <p>更新前：{{ $memo->memo }}</p>
    <form action="{{ route('update',$memo->id) }}" method="post">
        @csrf
        @method("PATCH")
        <label for="memo"><input type="text" name="memo" id="memo" placeholder="更新内容を入力してください"></label>
        <button>更新する</button>
    </form>
</body>
</html>
