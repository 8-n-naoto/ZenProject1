<x-app-layout title="共同購入リスト" heading="共同購入リスト" :back="route('events.show', $event)">
    <div class="space-y-4">
        @if ($canManage)
            <x-card>
                <p class="mb-3 text-xs text-slate-500">
                    参加者が登録した購入希望を集計して、サークルごとの共同購入リストを作成します。
                    希望が変わったら再集計してください。
                </p>
                <p class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    再集計すると、希望が無くなったサークル・商品は共同購入リストから消えます。
                    そのサークルの購入担当も一緒に取り消されるので注意してください。
                </p>
                <form method="POST" action="{{ route('purchases.shared.sync', $event) }}"
                      onsubmit="return confirm('購入希望から共同購入リストを作り直します。希望が無くなったサークルの購入担当は取り消されます。よろしいですか？');">
                    @csrf
                    <x-button class="w-full">購入希望から再集計する</x-button>
                </form>
            </x-card>
        @endif

        @if ($unassignedCount > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-semibold text-amber-900">担当者が決まっていないサークルが {{ $unassignedCount }}件 あります</p>
                <p class="mt-1 text-xs text-amber-800">全てのサークルに担当者が確定しないと、イベントを確定できません。</p>

                @if ($canVolunteerAll)
                    <form method="POST" action="{{ route('purchases.shared.volunteer-all', $event) }}" class="mt-3"
                          onsubmit="return confirm('担当者がいない{{ $unassignedCount }}サークルすべてに立候補します。よろしいですか？');">
                        @csrf
                        <x-button class="w-full" size="sm">まとめて担当に立候補する</x-button>
                    </form>
                @endif
            </div>
        @endif

        @forelse ($sharedPurchases as $sharedPurchase)
            <a href="{{ route('purchases.shared.show', $sharedPurchase) }}" class="block rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $sharedPurchase->eventCircle->display_name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $sharedPurchase->eventCircle->locationLabel() }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold tabular-nums">¥{{ number_format($sharedPurchase->plannedAmount()) }}</p>
                        <p class="text-xs text-slate-500">{{ $sharedPurchase->plannedQuantity() }}点</p>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1">
                    @forelse ($sharedPurchase->assignees as $assignee)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                            {{ $assignee->isConfirmed() ? 'bg-sky-100 text-sky-800 font-semibold' : 'bg-slate-100 text-slate-500' }}">
                            {{ $assignee->user?->displayName() ?? '不明なユーザー' }}{{ $assignee->isConfirmed() ? '' : '（立候補）' }}
                        </span>
                    @empty
                        <span class="text-xs text-rose-600">購入担当者が未定です</span>
                    @endforelse
                </div>
            </a>
        @empty
            <x-empty-state message="共同購入リストがありません"
                           hint="参加者が購入希望を登録したあと、責任者が集計するとここに表示されます。" />
        @endforelse
    </div>
</x-app-layout>
