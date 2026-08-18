<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー検索・招待</title>
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
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #576471;
            font-size: 0.95rem;
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

        .search-form {
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .search-input-group {
            display: flex;
            gap: 0.75rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #0062cc;
            box-shadow: 0 0 0 3px rgba(0, 98, 204, 0.1);
        }

        .search-button {
            padding: 0.75rem 1.5rem;
            background: #0062cc;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .search-button:active {
            background: #0051a8;
        }

        .results-section {
            margin-bottom: 2rem;
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a1a1a;
        }

        .users-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .user-card {
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transition: all 0.2s;
        }

        .user-card:active {
            border-color: #0062cc;
            background: #f8f9fa;
        }

        .user-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .user-avatar {
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

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.125rem;
            word-break: break-word;
        }

        .user-id {
            font-size: 0.85rem;
            color: #576471;
        }

        .invite-button {
            padding: 0.65rem 1.25rem;
            background: #0062cc;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            font-size: 0.9rem;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .invite-button:active {
            background: #0051a8;
        }

        .invite-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .empty-results {
            text-align: center;
            padding: 2rem;
            background: #fff;
            border: 1px dashed #e1e8ed;
            border-radius: 8px;
            color: #576471;
        }

        .empty-results-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }

        .footer {
            padding: 1rem;
            text-align: center;
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
                max-width: 800px;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .search-form {
                padding: 2rem;
            }

            .user-card {
                padding: 1.25rem;
            }

            .user-card:hover {
                border-color: #0062cc;
                background: #f8f9fa;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .results-title {
                font-size: 1.5rem;
            }

            .empty-results {
                padding: 3rem 2rem;
            }
        }

        /* Tablet layout */
        @media (min-width: 480px) and (max-width: 767px) {
            .container {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .search-input-group {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
            }
        }

        /* Mobile layout */
        @media (max-width: 479px) {
            .search-input-group {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
            }

            .user-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .invite-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar"></div>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <a href="{{ route('groups.show', $group) }}" class="back-button" title="戻る">
                    ←
                </a>
                <h1 class="header-title">{{ $group->name }}</h1>
            </div>
        </header>

        <div class="container">
            <div class="page-header">
                <h1>ユーザーを招待</h1>
                <p>{{ $group->name }}にメンバーを追加します</p>
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

            <form method="GET" action="{{ route('groups.search-users', $group) }}" class="search-form">
                <div class="search-input-group">
                    <input
                        id="q"
                        name="q"
                        type="text"
                        class="search-input"
                        placeholder="ユーザーIDで検索"
                        value="{{ $keyword }}"
                        required
                    >
                    <button type="submit" class="search-button">検索</button>
                </div>
            </form>

            @if ($keyword !== '')
                <div class="results-section">
                    <h2 class="results-title">検索結果</h2>

                    @forelse ($users as $user)
                        <ul class="users-list">
                            <li class="user-card">
                                <div class="user-left">
                                    <div class="user-avatar">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name">{{ $user->name }}</div>
                                        <div class="user-id">ID: {{ $user->user_id }}</div>
                                    </div>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('groups.invite', [$group, $user]) }}"
                                    style="margin: 0;"
                                >
                                    @csrf
                                    <button type="submit" class="invite-button">
                                        + 招待
                                    </button>
                                </form>
                            </li>
                        </ul>
                    @empty
                        <div class="empty-results">
                            <div class="empty-results-icon">🔍</div>
                            <p>招待可能なユーザーが見つかりません</p>
                        </div>
                    @endforelse
                </div>
            @else
                <div class="empty-results">
                    <div class="empty-results-icon">🔎</div>
                    <p>ユーザーIDを入力して検索してください</p>
                </div>
            @endif

            <div class="footer">
                <a href="{{ route('groups.show', $group) }}" class="footer-link">← グループ詳細へ戻る</a>
            </div>
        </div>
    </div>
</body>
</html>
