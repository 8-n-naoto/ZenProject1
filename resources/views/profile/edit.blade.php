<x-app-layout title="アカウント" heading="アカウント">
    <div class="space-y-4">
        <x-card>
            <div class="flex items-center gap-3">
                <x-avatar :user="$user" size="lg" />
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold">{{ $user->displayName() }}</p>
                    <p class="truncate text-xs text-slate-500">&#64;{{ $user->user_id }}</p>
                    <p class="mt-1 text-xs text-slate-400">ログインIDは変更できません。</p>
                </div>
            </div>
        </x-card>

        <x-card title="これまでの活動">
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-slate-50 py-3">
                    <p class="text-lg font-bold tabular-nums">{{ $stats['groups'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">グループ</p>
                </div>
                <div class="rounded-xl bg-slate-50 py-3">
                    <p class="text-lg font-bold tabular-nums">{{ $stats['events'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">参加イベント</p>
                </div>
                <div class="rounded-xl bg-slate-50 py-3">
                    <p class="text-lg font-bold tabular-nums">{{ $stats['purchased'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">購入した点数</p>
                </div>
            </div>

            @if ($events->isNotEmpty())
                <div class="mt-3 space-y-1">
                    @foreach ($events as $event)
                        <a href="{{ route('events.show', $event) }}" class="flex items-center justify-between gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-50">
                            <span class="min-w-0">
                                <span class="block truncate">{{ $event->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $event->group?->name }}・{{ $event->dateRangeLabel() }}</span>
                            </span>
                            <x-badge :class="$event->status->badgeClass()">{{ $event->status->label() }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card title="デザイン" subtitle="画面の配色と書体を選べます。機能や操作は変わりません。">
            <form method="POST" action="{{ route('profile.theme.update') }}" class="space-y-3">
                @csrf
                @method('PUT')

                <fieldset class="space-y-2">
                    <legend class="sr-only">画面の見た目</legend>

                    @foreach ($themes as $theme)
                        @php $swatch = $theme->swatch(); @endphp
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border px-3 py-3 hover:bg-slate-50 {{ $theme === $currentTheme ? 'border-sky-600 bg-sky-50' : 'border-slate-300' }}">
                            <input type="radio" name="theme" value="{{ $theme->value }}"
                                   class="h-5 w-5 shrink-0"
                                   @checked($theme === $currentTheme)>

                            <span class="flex h-11 w-11 shrink-0 flex-col justify-between rounded-lg border border-slate-200 p-1.5"
                                  style="background-color: {{ $swatch['bg'] }}" aria-hidden="true">
                                <span class="block h-3 rounded-sm" style="background-color: {{ $swatch['surface'] }}"></span>
                                <span class="flex items-center gap-1">
                                    <span class="block h-2 w-2 rounded-full" style="background-color: {{ $swatch['accent'] }}"></span>
                                    <span class="block h-1 flex-1 rounded-full" style="background-color: {{ $swatch['ink'] }}"></span>
                                </span>
                            </span>

                            <span class="min-w-0">
                                <span class="block text-sm font-semibold">{{ $theme->label() }}</span>
                                <span class="block text-xs text-slate-500">{{ $theme->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @error('theme')
                    <p class="text-xs text-rose-600">{{ $message }}</p>
                @enderror

                <x-button class="w-full">この見た目にする</x-button>

                <p class="text-xs text-slate-500">
                    書体は通信できないときだけ、端末の標準の書体で表示されます。
                </p>
            </form>
        </x-card>

        <x-card title="プロフィール">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-input name="name" label="表示名" :value="$user->name" required />
                <x-input name="email" label="メールアドレス" type="email" :value="$user->email" required
                         hint="変更すると、新しいアドレスでの認証が必要になります。" />

                <x-input name="email_current_password" label="現在のパスワード" type="password"
                         autocomplete="current-password"
                         hint="メールアドレスを変更する場合のみ入力してください。" />

                <x-button class="w-full">変更を保存</x-button>
            </form>
        </x-card>

        <x-card title="パスワードの変更">
            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-input name="current_password" label="現在のパスワード" type="password" required autocomplete="current-password" />
                <x-input name="password" label="新しいパスワード" type="password" required autocomplete="new-password" hint="8文字以上。" />
                <x-input name="password_confirmation" label="新しいパスワード（確認）" type="password" required autocomplete="new-password" />

                <x-button class="w-full">パスワードを変更する</x-button>
            </form>
        </x-card>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="secondary" class="w-full">ログアウト</x-button>
        </form>

        <x-card title="退会">
            @if ($deletionReasons !== [])
                <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    <p class="mb-1 font-semibold">現在は退会できません。</p>
                    <ul class="space-y-1">
                        @foreach ($deletionReasons as $reason)
                            <li>・{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="mb-3 text-xs text-slate-500">
                    退会すると、このアカウントでログインできなくなります。過去の購入・精算の記録は、グループの記録として残ります。
                </p>
                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-3"
                      onsubmit="return confirm('本当に退会しますか？この操作は取り消せません。');">
                    @csrf
                    @method('DELETE')
                    <x-input name="deletion_password" label="確認のためパスワードを入力" type="password" required autocomplete="current-password" />
                    <x-button variant="danger" class="w-full">退会する</x-button>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
