<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>グループ一覧</title>
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
        }

        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
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
        }

        .btn-icon:hover {
            background: #f1f2f3;
        }

        .container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
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

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .btn-new {
            background: #0062cc;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-new:active {
            background: #0051a8;
        }

        .groups-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .group-item {
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .group-item:active {
            background: #f8f9fa;
            border-color: #0062cc;
        }

        .group-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #0062cc, #00b4d8);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .group-info {
            flex: 1;
            min-width: 0;
        }

        .group-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
            word-break: break-word;
        }

        .group-meta {
            font-size: 0.85rem;
            color: #576471;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        .empty-state-text {
            color: #576471;
            margin-bottom: 1.5rem;
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

            .header-title {
                font-size: 1.25rem;
            }

            .container {
                padding: 2rem;
                max-width: 800px;
            }

            .section-header {
                margin-bottom: 2rem;
            }

            .section-title {
                font-size: 1.75rem;
            }

            .group-item {
                padding: 1.25rem;
            }

            .group-item:hover {
                background: #f8f9fa;
                border-color: #0062cc;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .empty-state {
                padding: 4rem 2rem;
            }
        }

        /* Tablet layout */
        @media (min-width: 480px) and (max-width: 767px) {
            .container {
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar"></div>

    <div class="main-content">
        <header class="header">
            <div class="header-left">
                <h1 class="header-title">グループ</h1>
            </div>
            <div class="header-actions">
                <a href="{{ route('groups.create') }}" class="btn-icon" title="新規グループ作成">
                    +
                </a>
            </div>
        </header>

        <div class="container">
            @if (session('status'))
                <div class="status-message" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="section-header">
                <h2 class="section-title">所属グループ</h2>
                <a href="{{ route('groups.create') }}" class="btn-new">
                    + グループ作成
                </a>
            </div>

            @forelse ($groups as $group)
                <ul class="groups-list">
                    <a href="{{ route('groups.show', $group) }}" class="group-item">
                        <div class="group-icon">
                            {{ substr($group->name, 0, 1) }}
                        </div>
                        <div class="group-info">
                            <div class="group-name">{{ $group->name }}</div>
                            @if ($group->description)
                                <div class="group-meta">{{ Str::limit($group->description, 60) }}</div>
                            @else
                                <div class="group-meta">説明なし</div>
                            @endif
                        </div>
                    </a>
                </ul>
            @empty
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3 class="empty-state-title">グループに所属していません</h3>
                    <p class="empty-state-text">グループを作成するか、他のユーザーから招待されるのをお待ちください。</p>
                    <a href="{{ route('groups.create') }}" class="btn-new">
                        + グループを作成
                    </a>
                </div>
            @endforelse

            <div class="footer">
                <a href="{{ route('top') }}" class="footer-link">← ダッシュボードへ戻る</a>
            </div>
        </div>
    </div>
</body>
</html>
