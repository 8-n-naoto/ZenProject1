<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>グループ作成</title>
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

        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .back-button {
            background: none;
            border: none;
            color: #576471;
            cursor: pointer;
            font-size: 1.5rem;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .back-button:hover {
            background: #f1f2f3;
        }

        .container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
        }

        .page-header p {
            color: #576471;
            font-size: 0.95rem;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #1a1a1a;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0062cc;
            box-shadow: 0 0 0 3px rgba(0, 98, 204, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.875rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #0062cc;
            color: #fff;
        }

        .btn-primary:active {
            background: #0051a8;
        }

        .btn-secondary {
            background: #f1f2f3;
            color: #1a1a1a;
        }

        .btn-secondary:active {
            background: #d5d6d9;
        }

        .form-hint {
            font-size: 0.85rem;
            color: #576471;
            margin-top: 0.375rem;
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
                max-width: 700px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .form-actions {
                flex-direction: row-reverse;
            }

            .btn {
                flex: auto;
                padding: 0.875rem 1.5rem;
            }
        }

        /* Tablet layout */
        @media (min-width: 480px) and (max-width: 767px) {
            .container {
                padding: 1.5rem;
                max-width: 100%;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar"></div>

    <div class="main-content">
        <header class="header">
            <a href="{{ route('groups.index') }}" class="back-button" title="戻る">
                ←
            </a>
            <h1 class="header-title">グループ作成</h1>
            <div style="width: 2.5rem;"></div>
        </header>

        <div class="container">
            <div class="page-header">
                <h1>グループを作成</h1>
                <p>新しいグループを立ち上げて、チームをまとめましょう</p>
            </div>

            @if ($errors->any())
                <div class="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('groups.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name">グループ名</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        placeholder="例：マーケティング部"
                        required
                        maxlength="100"
                    >
                    <p class="form-hint">グループの名前を入力してください</p>
                </div>

                <div class="form-group">
                    <label for="description">説明（オプション）</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="グループの目的や説明を入力してください"
                        maxlength="500"
                    >{{ old('description') }}</textarea>
                    <p class="form-hint">グループの説明を入力してください</p>
                </div>

                <div class="form-group">
                    <label for="highest_responsible_user_id">最高責任者のユーザーID</label>
                    <input
                        id="highest_responsible_user_id"
                        name="highest_responsible_user_id"
                        type="number"
                        value="{{ old('highest_responsible_user_id') }}"
                        placeholder="ユーザーIDを入力"
                        required
                    >
                    <p class="form-hint">グループの意思決定権を持つユーザーを指定してください</p>
                </div>

                <div class="form-group">
                    <label for="responsible_user_id">責任者のユーザーID</label>
                    <input
                        id="responsible_user_id"
                        name="responsible_user_id"
                        type="number"
                        value="{{ old('responsible_user_id') }}"
                        placeholder="ユーザーIDを入力"
                        required
                    >
                    <p class="form-hint">グループを運営するユーザーを指定してください</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">グループを作成</button>
                    <a href="{{ route('groups.index') }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
