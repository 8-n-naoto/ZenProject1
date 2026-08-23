@php
    use App\Enums\EventStatus;
    $user = auth()->user();
    $canManage = $user->can('manageParticipants', $event);
    $next = $event->status->next();
@endphp

<x-app-layout :title="$event->name" :heading="$event->name" :back="route('events.index', $event->group)">
    <x-slot:actions>
        @can('update', $event)
            <a href="{{ route('events.edit', $event) }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="イベントを編集">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-9 1l8.5-8.5a2.1 2.1 0 013 3L12 15l-4 1 1-4z"/></svg>
            </a>
        @endcan
    </x-slot:actions>

    <div class="space-y-4">
        <x-event-steps :event="$event" />

        @if ($event->isCompleted())
            <div class="rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                このイベントは完了しています。最高責任者以外は閲覧のみとなります。
            </div>
        @endif

        <x-card>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-500">会場</dt>
                    <dd class="mt-0.5">{{ $event->venue_name }}</dd>
                    @if ($event->venue_address)
                        <dd class="text-xs text-slate-500">{{ $event->venue_address }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs text-slate-500">開催日</dt>
                    <dd class="mt-0.5 space-y-0.5">
                        @foreach ($event->days as $day)
                            <div>{{ $day->event_date->translatedFormat('Y/m/d (D)') }} {{ $day->timeLabel() }}</div>
                        @endforeach
                    </dd>
                </div>
                @if ($event->description)
                    <div>
                        <dt class="text-xs text-slate-500">説明</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-slate-600">{{ $event->description }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-slate-500">作成者</dt>
                    <dd class="mt-0.5">{{ $event->creator?->displayName() ?? '不明' }}</dd>
                </div>
                @if ($event->fixed_at)
                    <div>
                        <dt class="text-xs text-slate-500">確定日時</dt>
                        <dd class="mt-0.5">{{ $event->fixed_at->format('Y/m/d H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        {{-- 自分の状況 --}}
        @if ($summary['isParticipant'] || $summary['wishCount'] > 0 || $summary['assignedCircles'] > 0)
            <x-card title="あなたの状況">
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-xl bg-slate-50 py-3">
                        <p class="text-lg font-bold tabular-nums">{{ $summary['wishCount'] }}<span class="text-xs font-normal text-slate-500">点</span></p>
                        <p class="mt-0.5 text-xs text-slate-500">購入希望</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 py-3">
                        <p class="text-lg font-bold tabular-nums">¥{{ number_format($summary['wishAmount']) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">予定金額</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 py-3">
                        <p class="text-lg font-bold tabular-nums">{{ $summary['assignedCircles'] }}<span class="text-xs font-normal text-slate-500">件</span></p>
                        <p class="mt-0.5 text-xs text-slate-500">担当サークル</p>
                    </div>
                    <div class="rounded-xl {{ $summary['pendingResults'] > 0 ? 'bg-amber-50' : 'bg-slate-50' }} py-3">
                        <p class="text-lg font-bold tabular-nums {{ $summary['pendingResults'] > 0 ? 'text-amber-700' : '' }}">
                            {{ $summary['pendingResults'] }}<span class="text-xs font-normal text-slate-500">件</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">結果が未登録</p>
                    </div>
                </div>

                @if ($summary['netAmount'] !== null && $summary['netAmount'] !== 0)
                    <div class="mt-3 rounded-xl px-4 py-3 text-center {{ $summary['netAmount'] > 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                        <p class="text-sm font-semibold">
                            精算: {{ $summary['netAmount'] > 0 ? '受け取り' : '支払い' }}
                            ¥{{ number_format(abs($summary['netAmount'])) }}
                        </p>
                    </div>
                @endif
            </x-card>
        @endif

        {{-- メニュー --}}
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('circles.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                <span class="block text-sm font-semibold">サークル・商品</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ $event->eventCircles()->count() }}サークル</span>
            </a>

            <a href="{{ route('events.map', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                <span class="block text-sm font-semibold">会場マップ</span>
                <span class="mt-0.5 block text-xs text-slate-500">
                    {{ $event->mapImageUrl() ? 'サークルの位置を地図で見る' : '会場図を登録する' }}
                </span>
            </a>

            @if ($isParticipant || $role?->isResponsibleOrAbove())
                <a href="{{ route('purchases.personal.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                    <span class="block text-sm font-semibold">購入希望</span>
                    <span class="mt-0.5 block text-xs text-slate-500">自分が買いたい商品を登録する</span>
                </a>

                <a href="{{ route('purchases.shared.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                    <span class="block text-sm font-semibold">共同購入リスト</span>
                    <span class="mt-0.5 block text-xs text-slate-500">担当者と買うもの</span>
                </a>

                <a href="{{ route('purchases.summary', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                    <span class="block text-sm font-semibold">希望の集計</span>
                    <span class="mt-0.5 block text-xs text-slate-500">全員分のまとめ</span>
                </a>

                @if ($event->status->isLocked() && ! $event->isCompleted())
                    <a href="{{ route('shopping.index', $event) }}" class="col-span-2 rounded-2xl bg-sky-600 p-4 text-center text-white shadow-sm hover:bg-sky-700">
                        <span class="block text-base font-bold">買い物リストを開く</span>
                        <span class="mt-0.5 block text-xs text-sky-100">担当サークルを配置順に表示・その場で記録</span>
                    </a>

                    <a href="{{ route('results.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                        <span class="block text-sm font-semibold">購入結果</span>
                        <span class="mt-0.5 block text-xs text-slate-500">全体の登録状況</span>
                    </a>
                @endif

                @if ($event->status->order() >= \App\Enums\EventStatus::Settling->order())
                    <a href="{{ route('settlements.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                        <span class="block text-sm font-semibold">精算</span>
                        <span class="mt-0.5 block text-xs text-slate-500">誰が誰にいくら払うか</span>
                    </a>
                @endif

                <a href="{{ route('approvals.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                    <span class="block text-sm font-semibold">承認</span>
                    <span class="mt-0.5 block text-xs text-slate-500">重要操作の申請状況</span>
                </a>

                <a href="{{ route('histories.index', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                    <span class="block text-sm font-semibold">変更履歴</span>
                    <span class="mt-0.5 block text-xs text-slate-500">誰が何を変えたか</span>
                </a>

                @can('create', [App\Models\Event::class, $event->group])
                    <a href="{{ route('events.duplicate.form', $event) }}" class="rounded-2xl bg-white p-4 text-center shadow-sm hover:bg-slate-50">
                        <span class="block text-sm font-semibold">複製して新規作成</span>
                        <span class="mt-0.5 block text-xs text-slate-500">サークルを引き継ぐ</span>
                    </a>
                @endcan
            @endif
        </div>

        {{-- 参加表明 --}}
        <x-card title="参加状況" :subtitle="$event->participants->count() . '人が参加予定'">
            @if ($event->participants->isEmpty())
                <p class="mb-3 text-xs text-slate-500">まだ参加者がいません。</p>
            @else
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach ($event->participants as $participant)
                        <span class="flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-1 pr-3">
                            <x-avatar :user="$participant" size="sm" />
                            <span class="text-xs">{{ $participant->displayName() }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            @can('join', $event)
                <form method="POST" action="{{ route('events.join', $event) }}">
                    @csrf
                    <x-button class="w-full">このイベントに参加する</x-button>
                </form>
            @endcan

            @can('leave', $event)
                <form method="POST" action="{{ route('events.leave', $event) }}"
                      onsubmit="return confirm('参加を取りやめます。よろしいですか？');">
                    @csrf
                    @method('DELETE')
                    <x-button variant="secondary" class="w-full">参加を取りやめる</x-button>
                </form>
            @endcan

            @if ($canManage)
                <a href="{{ route('events.members.index', $event) }}" class="mt-2 block text-center text-xs font-semibold text-sky-600">
                    参加者を管理する
                </a>
            @endif
        </x-card>

        {{-- 状態の操作 --}}
        @if ($user->can('advance', $event) || $user->can('revert', $event))
            <x-card title="イベントの進行">
                <div class="space-y-2">
                    @can('advance', $event)
                        <form method="POST" action="{{ route('events.advance', $event) }}"
                              onsubmit="return confirm('状態を「{{ $next?->label() }}」に進めます。よろしいですか？');">
                            @csrf
                            <x-button class="w-full">
                                @switch($event->status)
                                    @case(EventStatus::Preparation) 受付を開始する @break
                                    @case(EventStatus::Accepting) 内容を確定する @break
                                    @case(EventStatus::Fixed) 開催中にする @break
                                    @case(EventStatus::Ongoing) 精算を開始する @break
                                    @default 精算を完了する
                                @endswitch
                            </x-button>
                        </form>
                        @if ($event->status === EventStatus::Accepting)
                            <p class="text-xs text-slate-500">
                                確定すると参加者・購入リスト・担当者がロックされ、以降の変更は承認が必要になります。
                            </p>
                        @endif
                    @endcan

                    @can('revert', $event)
                        <form method="POST" action="{{ route('events.revert', $event) }}"
                              onsubmit="return confirm('状態をひとつ前に戻します。よろしいですか？');">
                            @csrf
                            <x-button variant="ghost" class="w-full text-slate-500">ひとつ前の状態に戻す</x-button>
                        </form>
                    @endcan
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
