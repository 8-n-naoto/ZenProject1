@props(['paginator'])

@php
    // URLのページ番号を書き換えて範囲外に飛ばされても、表示は最終ページに丸める
    $current = min($paginator->currentPage(), max(1, $paginator->lastPage()));
    $outOfRange = $paginator->currentPage() > $paginator->lastPage();
@endphp

@if ($outOfRange)
    <p class="mt-4 rounded-2xl bg-white px-4 py-6 text-center text-sm text-slate-500">
        このページはありません。
        <a href="{{ $paginator->url($paginator->lastPage()) }}" class="ml-1 font-semibold text-sky-600">最後のページへ</a>
    </p>
@elseif ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="ページ送り">
        @if ($paginator->onFirstPage())
            <span class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm text-slate-300">前へ</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">前へ</a>
        @endif

        <span class="shrink-0 text-xs tabular-nums text-slate-500">
            {{ $current }} / {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">次へ</a>
        @else
            <span class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm text-slate-300">次へ</span>
        @endif
    </nav>
@endif
