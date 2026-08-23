<x-app-layout :title="$sharedPurchase->eventCircle->display_name" :heading="$sharedPurchase->eventCircle->display_name"
              :back="route('purchases.shared.index', $event)">
    <div class="space-y-4">
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-slate-500">{{ $sharedPurchase->eventCircle->locationLabel() }}</p>
                    <p class="mt-1 text-sm">合計 {{ $sharedPurchase->plannedQuantity() }}点</p>
                </div>
                <p class="text-lg font-bold tabular-nums">¥{{ number_format($sharedPurchase->plannedAmount()) }}</p>
            </div>
        </x-card>

        <x-card title="購入する商品">
            @forelse ($sharedPurchase->items as $item)
                <div class="border-b border-slate-100 py-3 last:border-b-0">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm">{{ $item->eventProduct->name }}</p>
                            <p class="text-xs text-slate-500">{{ $item->eventProduct->priceLabel() }}</p>
                        </div>

                        @if ($canManage)
                            <form method="POST" action="{{ route('purchases.shared.items.update', $item) }}" class="flex shrink-0 items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="planned_quantity" min="0" max="999" inputmode="numeric"
                                       aria-label="{{ $item->eventProduct?->name }} の購入予定数"
                                       value="{{ $item->planned_quantity }}"
                                       class="w-20 rounded-xl border border-slate-300 px-2 py-1.5 text-center text-sm tabular-nums">
                                <x-button variant="subtle" size="sm">保存</x-button>
                            </form>
                        @else
                            <span class="shrink-0 text-sm font-semibold tabular-nums">{{ $item->planned_quantity }}点</span>
                        @endif
                    </div>

                    @if ($item->assignees->isNotEmpty())
                        <p class="mt-2 text-xs text-slate-500">
                            担当:
                            @foreach ($item->assignees as $assignee)
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5">
                                    {{ $assignee->user?->displayName() }} {{ $assignee->assigned_quantity }}点
                                </span>
                            @endforeach
                        </p>
                    @endif

                    @if ($canSplit && $participants->isNotEmpty())
                        <details class="mt-2">
                            <summary class="text-xs font-semibold text-sky-600">この商品の担当を分ける</summary>
                            <form method="POST" action="{{ route('purchases.shared.items.assignees', $item) }}" class="mt-2 space-y-2">
                                @csrf
                                @method('PATCH')

                                @foreach ($participants as $participant)
                                    @php $assigned = $item->assignees->firstWhere('user_id', $participant->id); @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1 truncate text-sm">{{ $participant->displayName() }}</span>
                                        <input type="number" min="0" max="{{ $item->planned_quantity }}" inputmode="numeric"
                                               aria-label="{{ $participant->displayName() }} の担当数量"
                                               name="assignees[{{ $participant->id }}]"
                                               value="{{ $assigned?->assigned_quantity ?? 0 }}"
                                               class="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-center text-sm tabular-nums">
                                    </div>
                                @endforeach

                                <p class="text-xs text-slate-500">
                                    合計が購入予定数（{{ $item->planned_quantity }}点）を超えないように入力してください。
                                    残りはサークル担当者が購入します。
                                </p>

                                <x-button variant="subtle" size="sm" class="w-full">担当を保存</x-button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="py-3 text-center text-xs text-slate-500">購入する商品がありません。</p>
            @endforelse
        </x-card>

        <x-card title="購入担当者" subtitle="このサークルに並んで購入する人">
            @forelse ($sharedPurchase->assignees as $assignee)
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <x-avatar :user="$assignee->user" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">{{ $assignee->user?->displayName() ?? '不明なユーザー' }}</p>
                        <p class="text-xs {{ $assignee->isConfirmed() ? 'text-sky-600' : 'text-slate-500' }}">
                            {{ $assignee->isConfirmed() ? '確定' : '立候補中' }}
                        </p>
                    </div>

                    @if ($canManageAssignees)
                        <div class="flex shrink-0 gap-1">
                            @unless ($assignee->isConfirmed())
                                <form method="POST" action="{{ route('purchases.assignees.assign', [$sharedPurchase, $assignee->user]) }}">
                                    @csrf
                                    <x-button size="sm">確定</x-button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('purchases.assignees.unassign', [$sharedPurchase, $assignee->user]) }}"
                                  onsubmit="return confirm('{{ $assignee->user?->displayName() }} さんを購入担当から外します。よろしいですか？');">
                                @csrf
                                @method('DELETE')
                                <x-button variant="ghost" size="sm" class="text-rose-600">担当を外す</x-button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <p class="py-3 text-center text-xs text-rose-600">購入担当者が決まっていません。</p>
            @endforelse

            <div class="mt-3 space-y-2">
                @if ($canVolunteer)
                    <form method="POST" action="{{ route('purchases.assignees.volunteer', $sharedPurchase) }}">
                        @csrf
                        <x-button class="w-full">このサークルの購入を担当する</x-button>
                    </form>
                @endif

                @if ($canWithdraw)
                    <form method="POST" action="{{ route('purchases.assignees.withdraw', $sharedPurchase) }}"
                          onsubmit="return confirm('このサークルの購入担当をやめます。よろしいですか？');">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" class="w-full">立候補を取り下げる</x-button>
                    </form>
                @endif
            </div>
        </x-card>

        @if ($canManageAssignees && $candidates->isNotEmpty())
            <x-card title="担当者を指名する" subtitle="立候補がいない場合はここから指名できます。">
                @foreach ($candidates as $candidate)
                    <div class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                        <x-avatar :user="$candidate" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $candidate->displayName() }}</p>
                        </div>
                        <form method="POST" action="{{ route('purchases.assignees.assign', [$sharedPurchase, $candidate]) }}">
                            @csrf
                            <x-button size="sm">指名</x-button>
                        </form>
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>
</x-app-layout>
