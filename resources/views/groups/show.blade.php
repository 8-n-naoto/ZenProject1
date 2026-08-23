@php
    use App\Enums\GroupRole;
    $canInvite = auth()->user()->can('invite', $group);
    $canManageRoles = auth()->user()->can('manageRoles', $group);
    $canUpdate = auth()->user()->can('update', $group);
@endphp

<x-app-layout :title="$group->name" :heading="$group->name" :back="route('groups.index')">
    <x-slot:actions>
        @if ($canUpdate)
            <a href="{{ route('groups.edit', $group) }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="グループ設定">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 3.9a1 1 0 011.4 0l.9.9a1 1 0 00.9.3l1.2-.2a1 1 0 011.1.7l.4 1.2a1 1 0 00.6.6l1.2.4a1 1 0 01.7 1.1l-.2 1.2a1 1 0 00.3.9l.9.9a1 1 0 010 1.4l-.9.9a1 1 0 00-.3.9l.2 1.2a1 1 0 01-.7 1.1l-1.2.4a1 1 0 00-.6.6l-.4 1.2a1 1 0 01-1.1.7l-1.2-.2a1 1 0 00-.9.3l-.9.9a1 1 0 01-1.4 0l-.9-.9a1 1 0 00-.9-.3l-1.2.2a1 1 0 01-1.1-.7l-.4-1.2a1 1 0 00-.6-.6l-1.2-.4a1 1 0 01-.7-1.1l.2-1.2a1 1 0 00-.3-.9l-.9-.9a1 1 0 010-1.4l.9-.9a1 1 0 00.3-.9l-.2-1.2a1 1 0 01.7-1.1l1.2-.4a1 1 0 00.6-.6l.4-1.2a1 1 0 011.1-.7l1.2.2a1 1 0 00.9-.3l.9-.9z"/><circle cx="12" cy="12" r="2.5"/></svg>
            </a>
        @endif
    </x-slot:actions>

    <div class="space-y-4">
        @if ($group->description)
            <x-card>
                <p class="whitespace-pre-line text-sm text-slate-600">{{ $group->description }}</p>
            </x-card>
        @endif

        @if ($needsResponsible)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                このグループにはまだ <span class="font-semibold">責任者</span> がいません。
                メンバーを招待し、最高責任者が責任者を任命してください。イベントの作成には責任者が1人以上必要です。
            </div>
        @endif

        <section>
            <div class="mb-2 flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-slate-700">イベント</h2>
                <a href="{{ route('events.index', $group) }}" class="text-xs font-semibold text-sky-600">すべて見る</a>
            </div>

            @forelse ($events as $event)
                <x-event-card :event="$event" class="mb-2" />
            @empty
                <x-empty-state message="進行中のイベントはありません">
                    @can('create', [App\Models\Event::class, $group])
                        <x-button :href="route('events.create', $group)" size="sm">イベントを作成する</x-button>
                    @endcan
                </x-empty-state>
            @endforelse
        </section>

        @if ($canInvite)
            <x-card title="招待リンク（合い言葉）" subtitle="アカウントを持っていない人も、このリンクから登録して参加できます。">
                @if ($inviteLink)
                    <label for="invite-url" class="sr-only">招待リンク</label>
                    <input id="invite-url" type="text" readonly value="{{ $inviteLink->url() }}"
                           class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-xs">
                    <p class="mt-1 text-xs text-slate-500">
                        合い言葉: <span class="font-mono font-semibold text-slate-700">{{ $inviteLink->token }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($inviteLink->expires_at)
                            {{ $inviteLink->expires_at->translatedFormat('Y/m/d (D) H:i') }} まで有効
                        @else
                            期限なし
                        @endif
                        @if ($inviteLink->remainingUses() !== null)
                            ・残り {{ $inviteLink->remainingUses() }}回
                        @endif
                    </p>

                    <button type="button" id="copy-invite"
                            class="mt-3 w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700">
                        リンクをコピーする
                    </button>

                    <form method="POST" action="{{ route('groups.invite-link.revoke', [$group, $inviteLink]) }}" class="mt-2"
                          onsubmit="return confirm('この招待リンクを無効にします。すでに配った人は参加できなくなります。よろしいですか？');">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" size="sm" class="w-full text-slate-500">このリンクを無効にする</x-button>
                    </form>

                    <script>
                    (function () {
                        var button = document.getElementById('copy-invite');
                        var field = document.getElementById('invite-url');
                        if (!button || !field) { return; }
                        button.addEventListener('click', function () {
                            field.select();
                            field.setSelectionRange(0, 99999);
                            var done = false;
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(field.value); done = true;
                            } else {
                                try { done = document.execCommand('copy'); } catch (e) { done = false; }
                            }
                            button.textContent = done ? 'コピーしました' : '長押しでコピーしてください';
                            window.setTimeout(function () { button.textContent = 'リンクをコピーする'; }, 2500);
                        });
                    })();
                    </script>
                @else
                    <p class="mb-3 text-xs text-slate-500">
                        まだ招待リンクがありません。発行すると、リンクを知っている人がグループに参加できます。
                    </p>
                @endif

                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-semibold text-sky-600">
                        {{ $inviteLink ? '新しいリンクを発行する' : '招待リンクを発行する' }}
                    </summary>
                    <form method="POST" action="{{ route('groups.invite-link.issue', $group) }}" class="mt-3 space-y-3"
                          onsubmit="return confirm('新しい招待リンクを発行します。今までのリンクは使えなくなります。よろしいですか？');">
                        @csrf
                        <x-input name="expires_in_days" label="有効期間（日）" type="number" value="7"
                                 inputmode="numeric" min="1" max="90" hint="1〜90日。" />
                        <x-input name="max_uses" label="使用回数の上限" type="number"
                                 inputmode="numeric" min="1" max="100" hint="空欄なら無制限。" />
                        <x-button variant="secondary" class="w-full">発行する</x-button>
                    </form>
                </details>
            </x-card>
        @endif

        <section>
            <div class="mb-2 flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-slate-700">メンバー（{{ $group->activeMembers->count() }}人）</h2>
                @if ($canInvite)
                    <a href="{{ route('groups.search-users', $group) }}" class="text-xs font-semibold text-sky-600">＋ 招待する</a>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                @foreach ($group->activeMembers as $member)
                    @php
                        $memberRole = GroupRole::tryFrom((string) $member->pivot->role);
                        $isMe = $member->id === auth()->id();
                        $canRemove = auth()->user()->can('removeMember', [$group, $member]);
                    @endphp
                    <div class="border-b border-slate-100 p-4 last:border-b-0">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$member" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">
                                    {{ $member->displayName() }}
                                    @if ($isMe)
                                        <span class="text-xs font-normal text-slate-400">（あなた）</span>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-slate-500">&#64;{{ $member->user_id }}</p>
                            </div>
                            @if ($memberRole)
                                <x-badge :class="$memberRole->badgeClass()">{{ $memberRole->label() }}</x-badge>
                            @endif
                        </div>

                        @if ($canManageRoles || $canRemove)
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if ($canManageRoles)
                                    <form method="POST" action="{{ route('groups.members.role.update', [$group, $member]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="role-{{ $member->id }}" class="sr-only">{{ $member->displayName() }} の役割</label>
                                        <select id="role-{{ $member->id }}" name="role" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                            @foreach (GroupRole::cases() as $role)
                                                <option value="{{ $role->value }}" @selected($memberRole === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <x-button variant="subtle" size="sm">変更</x-button>
                                    </form>
                                @endif

                                @if ($canRemove)
                                    <form method="POST" action="{{ route('groups.members.remove', [$group, $member]) }}"
                                          onsubmit="return confirm('{{ $member->user_id }} さんを除名します。よろしいですか？');">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600">除名</x-button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($canInvite && $pendingInvitations->isNotEmpty())
            <section>
                <h2 class="mb-2 px-1 text-sm font-semibold text-slate-700">返答待ちの招待（{{ $pendingInvitations->count() }}件）</h2>
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    @foreach ($pendingInvitations as $invitation)
                        <div class="flex items-center gap-3 border-b border-slate-100 p-4 last:border-b-0">
                            <x-avatar :user="$invitation->invitedUser" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm">{{ $invitation->invitedUser->displayName() }}</p>
                                <p class="truncate text-xs text-slate-500">&#64;{{ $invitation->invitedUser->user_id }}</p>
                            </div>
                            <form method="POST" action="{{ route('groups.invitations.cancel', [$group, $invitation]) }}"
                                  onsubmit="return confirm('{{ $invitation->invitedUser->displayName() }} さんへの招待を取り消します。よろしいですか？');">
                                @csrf
                                @method('DELETE')
                                <x-button variant="ghost" size="sm" class="text-slate-500">招待を取り消す</x-button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <form method="POST" action="{{ route('groups.members.leave', $group) }}"
              onsubmit="return confirm('このグループから脱退します。よろしいですか？');">
            @csrf
            @method('DELETE')
            <x-button variant="secondary" class="w-full text-rose-600">グループを脱退する</x-button>
        </form>
    </div>
</x-app-layout>
