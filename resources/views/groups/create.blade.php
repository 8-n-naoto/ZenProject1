<x-app-layout title="グループを作成" heading="グループを作成" :back="route('groups.index')">
    <form method="POST" action="{{ route('groups.store') }}" class="space-y-4">
        @csrf

        <x-card>
            <div class="space-y-4">
                <x-input name="name" label="グループ名" required maxlength="100" placeholder="例: 冬コミ有志の会" />
                <x-textarea name="description" label="説明" rows="4" maxlength="500" placeholder="活動の目的やルールなど（任意）" />
            </div>
        </x-card>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
            作成すると、あなたが <span class="font-semibold">最高責任者</span> として自動的に参加します。
            作成後にメンバーを招待し、<span class="font-semibold">責任者</span> を任命してください。
            （イベントを作成するには責任者が1人以上必要です）
        </div>

        <x-button class="w-full" size="lg">グループを作成</x-button>
    </form>
</x-app-layout>
