<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>top page</title>
</head>
<body>
 <p>メモ一覧</p>
 <ul>
    {{-- @for ($i = 0; $i < count($memos); $i++)
        <li><a href="{{route("show", $i + 1)}}">memo{{$i + 1}}</a></li>
    @endfor --}}

    @foreach ($memos as $memo)

    <li><a href="{{route("show", $memo->id)}}">memo{{ $memo->id }}:{{ $memo->memo }}</a></li>
    @endforeach
 </ul>
 <a href="{{route("store")}}">新規作成</a>
</body>
</html>
