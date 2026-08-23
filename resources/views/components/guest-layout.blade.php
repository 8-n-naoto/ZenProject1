@props(['title' => 'コミケ共同購入管理'])

@php
    $theme = \App\Enums\Theme::current();
@endphp

<!DOCTYPE html>
<html lang="ja" data-theme="{{ $theme->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $theme->themeColor() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="共同購入">
    <title>{{ $title }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <x-styles />
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-600 text-2xl font-bold text-white">共</div>
            <p class="text-lg font-semibold">コミケ共同購入管理</p>
            <p class="mt-1 text-sm text-slate-500">グループでの買い物を、もれなく精算まで。</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <x-alert />
            {{ $slot }}
        </div>

        {{ $footer ?? '' }}
    </div>
    <x-behaviour />
</body>
</html>
