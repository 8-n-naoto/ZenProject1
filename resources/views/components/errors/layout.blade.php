@props(['code', 'title', 'message'])

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <x-styles />
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <p class="text-4xl font-bold text-slate-300 tabular-nums">{{ $code }}</p>
            <h1 class="mt-2 text-lg font-semibold">{{ $title }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $message }}</p>

            <div class="mt-6 space-y-2">
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                   class="block w-full rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    {{ auth()->check() ? 'ホームへ戻る' : 'ログイン画面へ' }}
                </a>
                <a href="javascript:history.back()"
                   class="block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    ひとつ前に戻る
                </a>
            </div>
        </div>
    </div>
</body>
</html>
