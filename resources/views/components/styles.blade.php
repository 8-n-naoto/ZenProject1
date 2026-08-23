@php
    $theme = \App\Enums\Theme::current();
@endphp

{{-- Node が使える環境では npm run build の成果物を、無い環境では同梱CSSを読み込む --}}
@if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
@endif

{{-- 利用者が選んだ見た目。必ずユーティリティCSSより後に読み込む --}}
<link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ @filemtime(public_path('css/theme.css')) }}">

{{-- 書体は読み込めなくても端末の書体で表示されるよう、フォールバックを theme.css 側に持たせている --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $theme->fontHref() }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ $theme->fontHref() }}"></noscript>
