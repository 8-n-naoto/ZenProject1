<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>memo{{ $memo->id }}</title>
</head>
<body>
    @php
        $is_updated = isset($is_update) && is_bool($is_update);
    @endphp
    @if ($is_updated)
    <p>更新が完了しました。</p>
    @elseif ($is_updated)
    <p>更新に失敗しました</p>
    @endif
    <p>内容</p>
    <p>{{ $memo->memo }}</p>
    <a href="{{ route("edit",$memo->id)}}">更新する</a>
    <form action="{{route("destroy",$memo->id)}}" method="post">
        @csrf
        @method("DELETE")
        <button>削除する</button>
    </form>
</body>
</html>
