<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background: #f8f9fa;
            color: #1a1a1a;
            line-height: 1.5;
        }

        .sidebar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e1e8ed;
            overflow-y: auto;
            z-index: 1000;
        }

        .main-content {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid #e1e8ed;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .back-button {
            width: 2.5rem;
            height: 2.5rem;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #576471;
            transition: background 0.2s;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .back-button:hover {
            background: #f1f2f3;
        }

        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .group-header {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e1e8ed;
        }

        .group-header-top {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .group-icon-large {
            width: 3rem;
            height: 3rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #0062cc, #00b4d8);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .group-details h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .group-description {
            color: #576471;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .status-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #155724;
            font-size: 0.95rem;
        }

        .status-message::before {
            content: "✓ ";
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert ul {
            list-style: none;
            padding: 0;
        }

        .alert li {
            color: #856404;
            font-size: 0.9rem;
            padding: 0.25rem 0;
        }

        .alert li::before {
            content: "⚠ ";
            margin-right: 0.5rem;
        }

        .section {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e1e8ed;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .section-action {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 2rem;
            height: 2rem;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #576471;
            transition: background 0.2s;
            font-size: 1rem;
        }

        .btn-icon:hover {
            background: #f1f2f3;
        }

        .section-content {
            padding: 1rem 1.5rem;
        }

        .members-list {
            list-style: none;
            display: flex;
            flex-direction: column;
        }

        .member-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .member-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .member-info {
            flex: 1;
            min-width: 0;
        }

        .member-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.125rem;
            word-break: break-word;
        }

        .member-meta {
            font-size: 0.85rem;
            color: #576471;
        }

        .member-role {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .member-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .role-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }

        .role-selector select {
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #fff;
            cursor: pointer;
        }

        .role-selector select:focus {
            outline: none;
            border-color: #0062cc;
        }

        .role-selector button {
            padding: 0.5rem 1rem;
            background: #0062cc;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .role-selector button:active {
            background: #0051a8;
        }

        .empty-members {
            text-align: center;
            padding: 2rem 1rem;
            color: #576471;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #0062cc;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.2s;
            cursor: pointer;
        }

        .action-link:active {
            background: #0051a8;
        }

        .action-link-secondary {
            background: #f1f2f3;
            color: #1a1a1a;
        }

        .action-link-secondary:active {
            background: #d5d6d9;
        }

        .footer {
            padding: 1rem;
            text-align: center;
            border-top: 1px solid #e1e8ed;
        }

        .footer-link {
            color: #0062cc;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        /* Desktop layout */
        @media (min-width: 768px) {
            .sidebar {
                display: block;
            }

            .main-content {
                margin-left: 260px;
            }

            .header {
                padding: 1.5rem 2rem;
            }

            .container {
                padding: 2rem;
                max-width: 900px;
            }

            .group-header {
                padding: 2rem;
            }

            .section {
                margin-bottom: 2rem;
            }

            .section-content {
                padding: 1.5rem;
            }

            .actions {
                flex-direction: row;
            }

            .action-link {
                flex: 1;
                justify-content: center;
            }

            .member-item {
                padding: 1.25rem 0;
            }
        }

        /* Tablet layout */
        @media (min-width: 480px) and (max-width: 767px) {
            .container {
                padding: 1.5rem;
            }

            .group-header {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar"></div>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <a href="{{ route('groups.index') }}" class="back-button" title="戻る">
                    ←
                </a>
                <h1 class="header-title">{{ $group->name }}</h1>
            </div>
        </header>

        <div class="container">
            <div class="group-header">
                <div class="group-header-top">
                    <div class="group-icon-large">
                        {{ substr($group->name, 0, 1) }}
                    </div>
                    <div class="group-details">
                        <h1>{{ $group->name }}</h1>
                        @if ($group->description)
                            <p class="group-description">{{ $group->description }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if (session('status'))
                <div class="status-message" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">メンバー（{{ count($group->members) }}人）</h2>
                </div>
                <div class="section-content">
                    @if ($group->members->isNotEmpty())
                        <ul class="members-list">
                            @foreach ($group->members as $member)
                                <li class="member-item">
                                    <div class="member-left">
                                        <div class="member-avatar">
                                            {{ substr($member->name, 0, 1) }}
                                        </div>
                                        <div class="member-info">
                                            <div class="member-name">{{ $member->name }}</div>
                                            <div class="member-meta">{{ $member->user_id }}</div>
                                        </div>
                                    </div>
                                    <div class="member-actions">
                                        <span class="member-role">{{ $member->pivot->role }}</span>

                                        @if ($myRole === \App\Models\Group::ROLE_HIGHEST_RESPONSIBLE)
                                            <form
                                                method="POST"
                                                action="{{ route('groups.members.role.update', [$group, $member]) }}"
                                                style="display: flex; gap: 0.5rem; align-items: center;"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <select name="role" style="padding: 0.5rem; border-radius: 4px; border: 1px solid #ccc;">
                                                    @foreach (\App\Models\Group::ROLES as $role)
                                                        <option
                                                            value="{{ $role }}"
                                                            @selected($member->pivot->role === $role)
                                                        >
                                                            {{ $role }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <button type="submit" style="padding: 0.5rem 1rem; background: #0062cc; color: #fff; border: none; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap;">
                                                    保存
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="empty-members">
                            <p>メンバーがまだいません</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="actions">
                @if (in_array($myRole, [
                    \App\Models\Group::ROLE_HIGHEST_RESPONSIBLE,
                    \App\Models\Group::ROLE_RESPONSIBLE,
                ], true))
                    <a href="{{ route('groups.search-users', $group) }}" class="action-link">
                        👥 ユーザーを招待
                    </a>
                @endif

                <a href="{{ route('invitations.index') }}" class="action-link action-link-secondary">
                    📧 招待一覧
                </a>
            </div>

            <div class="footer">
                <a href="{{ route('groups.index') }}" class="footer-link">← グループ一覧へ戻る</a>
            </div>
        </div>
    </div>
</body>
</html>
