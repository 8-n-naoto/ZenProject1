@props([
    'title' => 'コミケ共同購入管理',
    'heading' => null,
    'back' => null,
    'wide' => false,
])

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0284c7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="共同購入">
    <title>{{ $title }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <x-styles />
</head>
<body class="bg-slate-100 text-slate-900">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:rounded-xl focus:bg-sky-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        本文へスキップ
    </a>

    <header class="fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-200">
        <div class="mx-auto flex h-14 max-w-3xl items-center gap-2 px-3">
            @if ($back)
                <a href="{{ $back }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="戻る">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white font-bold">共</span>
            @endif

            <h1 class="min-w-0 flex-1 truncate text-base font-semibold">{{ $heading ?? $title }}</h1>

            {{ $actions ?? '' }}
        </div>
    </header>

    <main id="main" class="mx-auto {{ $wide ? 'max-w-5xl' : 'max-w-3xl' }} px-3 pb-24 pt-20">
        <x-alert />
        {{ $slot }}
    </main>

    <x-bottom-nav />
    <x-behaviour />
    <x-offline-guard />

    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* 未対応の環境では何もしない */ });
        });
    }
    </script>
</body>
</html>
