<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>コミケ共同購入管理</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f7fa;
            color: #1f2937;
        }

        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px;
        }

        .header-inner {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .title {
            margin: 0;
            font-size: 18px;
        }

        .logout {
            border: 0;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            font-size: 14px;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }

        .welcome {
            margin-bottom: 20px;
        }

        .welcome h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .welcome p {
            margin: 0;
            color: #6b7280;
        }

        .menu {
            display: grid;
            gap: 12px;
        }

        .menu-item {
            display: block;
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            color: #111827;
            text-decoration: none;
        }

        .menu-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 16px;
        }

        .menu-item span {
            color: #6b7280;
            font-size: 13px;
        }

        .status {
            margin-bottom: 20px;
            padding: 12px 14px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            color: #065f46;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <h1 class="title">コミケ共同購入管理</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">ログアウト</button>
            </form>
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <div class="status" role="status">
                {{ session('status') }}
            </div>
        @endif

        <section class="welcome">
            <h1>ダッシュボード</h1>
            <p>{{ auth()->user()->name }} さん、ようこそ。</p>
        </section>

        <nav class="menu">
            <a href="#" class="menu-item">
                <strong>イベント</strong>
                <span>参加するコミケ・イベントを管理します。</span>
            </a>

            <a href="#" class="menu-item">
                <strong>個人購入リスト</strong>
                <span>自分が購入する商品の一覧を管理します。</span>
            </a>

            <a href="#" class="menu-item">
                <strong>共同購入リスト</strong>
                <span>グループで購入する商品の一覧を管理します。</span>
            </a>

            <a href="#" class="menu-item">
                <strong>グループ</strong>
                <span>共同購入グループを管理します。</span>
            </a>

            <a href="#" class="menu-item">
                <strong>支払い</strong>
                <span>購入後の支払い・精算を管理します。</span>
            </a>

            <a href="{{ route('store') }}" class="menu-item">
                <strong>メモ</strong>
                <span>既存のメモ機能を利用します。</span>
            </a>
        </nav>
    </main>
</body>
</html>
