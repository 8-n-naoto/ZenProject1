@php use App\Enums\ApprovalActionType; @endphp

<x-app-layout title="承認" heading="承認" :back="route('events.show', $event)">
    <div class="space-y-4">
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">承認待ち（{{ $pending->count() }}件）</h2>

            @forelse ($pending as $approval)
                <x-card class="mb-2">
                    <p class="text-sm font-semibold">{{ $approval->action_type->label() }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $approval->action_type->description() }}</p>
                    <p class="mt-2 text-xs text-slate-500">
                        申請: {{ $approval->applicant?->displayName() }}
                        ・{{ $approval->submitted_at?->format('Y/m/d H:i') }}
                    </p>

                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">賛成 {{ $approval->approvalCount() }}</span>
                        <span class="rounded-full bg-rose-50 px-2 py-0.5 text-rose-700">反対 {{ $approval->rejectionCount() }}</span>
                        <span class="text-slate-400">可決に必要: {{ intdiv($approverCount, 2) + 1 }}票</span>
                    </div>

                    @if ($isApprover && ! $approval->hasVoted(auth()->user()))
                        <div class="mt-3 flex gap-2">
                            <form method="POST" action="{{ route('approvals.vote', [$approval, 'approve']) }}" class="flex-1"
                                  onsubmit="return confirm('「{{ $approval->action_type->label() }}」に賛成します。投票はあとから変更できません。よろしいですか？');">
                                @csrf
                                <x-button class="w-full" size="sm">承認する</x-button>
                            </form>
                            <form method="POST" action="{{ route('approvals.vote', [$approval, 'reject']) }}" class="flex-1"
                                  onsubmit="return confirm('「{{ $approval->action_type->label() }}」に反対します。投票はあとから変更できません。よろしいですか？');">
                                @csrf
                                <x-button variant="secondary" class="w-full" size="sm">否決する</x-button>
                            </form>
                        </div>
                    @elseif ($isApprover)
                        <p class="mt-3 text-xs text-slate-400">投票済みです。</p>
                    @endif

                    @if ($canWithdraw($approval))
                        <form method="POST" action="{{ route('approvals.withdraw', $approval) }}" class="mt-3"
                              onsubmit="return confirm('この申請を取り下げます。あとから申請し直せます。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" size="sm" class="w-full text-slate-500">申請を取り下げる</x-button>
                        </form>
                        <p class="mt-1 text-center text-xs text-slate-400">
                            賛否が分かれて決まらないときは、取り下げてから話し合ってください。
                        </p>
                    @endif
                </x-card>
            @empty
                <x-empty-state message="承認待ちの申請はありません" />
            @endforelse
        </section>

        @if ($isApprover)
            <x-card title="確定後の内容変更">
                @if ($contentsUnlocked)
                    <p class="mb-3 text-xs text-emerald-700">
                        現在、確定後の内容変更が解禁されています。変更が終わったら再ロックしてください。
                    </p>
                    <form method="POST" action="{{ route('approvals.relock', $event) }}">
                        @csrf
                        <x-button variant="secondary" class="w-full">変更を終了して再ロックする</x-button>
                    </form>
                @else
                    <p class="mb-3 text-xs text-slate-500">
                        確定済み・開催中のイベントで、サークル・商品・共同購入リストを変更したい場合に申請します。
                    </p>
                    <form method="POST" action="{{ route('approvals.unlock', $event) }}">
                        @csrf
                        <x-button class="w-full">内容変更の解禁を申請する</x-button>
                    </form>
                @endif
            </x-card>
        @endif

        @if ($history->isNotEmpty())
            <x-card title="これまでの申請">
                @foreach ($history as $approval)
                    <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $approval->action_type->label() }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $approval->applicant?->displayName() }}
                                ・{{ $approval->resolved_at?->format('Y/m/d H:i') }}
                            </p>
                        </div>
                        <x-badge :class="$approval->status->badgeClass()">{{ $approval->status->label() }}</x-badge>
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>
</x-app-layout>
