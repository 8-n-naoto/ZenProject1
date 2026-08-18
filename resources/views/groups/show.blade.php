<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }}</title>
</head>
<body>
    <h1>{{ $group->name }}</h1>

    @if ($group->description)
        <p>{{ $group->description }}</p>
    @endif

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

    <h2>メンバー</h2>

    <ul>
        @foreach ($group->members as $member)
            <li>
                <strong>{{ $member->name }}</strong>
                <span>（{{ $member->user_id }}）</span>
                <span>- {{ $member->pivot->role }}</span>

                @if ($myRole === \App\Models\Group::ROLE_HIGHEST_RESPONSIBLE)
                    <form
                        method="POST"
                        action="{{ route('groups.members.role.update', [$group, $member]) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <select name="role">
                            @foreach (\App\Models\Group::ROLES as $role)
                                <option
                                    value="{{ $role }}"
                                    @selected($member->pivot->role === $role)
                                >
                                    {{ $role }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit">役割を変更</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    @if (in_array($myRole, [
        \App\Models\Group::ROLE_HIGHEST_RESPONSIBLE,
        \App\Models\Group::ROLE_RESPONSIBLE,
    ], true))
        <p>
            <a href="{{ route('groups.search-users', $group) }}">ユーザーを検索して招待</a>
        </p>
    @endif

    <p>
        <a href="{{ route('invitations.index') }}">招待一覧</a>
    </p>

    <p>
        <a href="{{ route('groups.index') }}">グループ一覧へ戻る</a>
    </p>
</body>
</html>
