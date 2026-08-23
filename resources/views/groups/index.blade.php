<x-app-layout title="グループ" heading="グループ">
    <x-slot:actions>
        <a href="{{ route('groups.create') }}" class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-sky-600 px-3 text-xs font-semibold text-white hover:bg-sky-700">
            ＋ 作成
        </a>
    </x-slot:actions>

    @forelse ($groups as $group)
        @php $role = \App\Enums\GroupRole::tryFrom((string) $group->pivot->role); @endphp
        <a href="{{ route('groups.show', $group) }}" class="mb-2 block rounded-2xl bg-white p-4 shadow-sm hover:bg-slate-50">
            <div class="flex items-center gap-3">
                <x-group-icon :group="$group" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $group->name }}</p>
                    <p class="text-xs text-slate-500">メンバー {{ $group->active_members_count }}人</p>
                </div>
                @if ($role)
                    <x-badge :class="$role->badgeClass()">{{ $role->label() }}</x-badge>
                @endif
            </div>
            @if ($group->description)
                <p class="mt-2 text-xs text-slate-500">{{ Str::limit($group->description, 60) }}</p>
            @endif
        </a>
    @empty
        <x-empty-state message="参加中のグループはありません" hint="グループを作成して、共同購入の準備を始めましょう。">
            <x-button :href="route('groups.create')" size="sm">グループを作成する</x-button>
        </x-empty-state>
    @endforelse
</x-app-layout>
