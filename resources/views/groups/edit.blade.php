<x-app-layout title="グループ設定" heading="グループ設定" :back="route('groups.show', $group)">
    <div class="space-y-4">
        <form method="POST" action="{{ route('groups.update', $group) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PATCH')

            <x-card>
                <div class="space-y-4">
                    <x-input name="name" label="グループ名" :value="$group->name" required maxlength="100" />
                    <x-textarea name="description" label="説明" :value="$group->description" rows="4" maxlength="500" />

                    <div class="space-y-2">
                        <span class="block text-sm font-medium text-slate-700">グループ画像</span>
                        <div class="flex items-center gap-3">
                            <x-group-icon :group="$group" size="lg" />
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-sm text-slate-600">
                        </div>
                        <p class="text-xs text-slate-500">JPEG / PNG / WebP、2MBまで。</p>
                        @error('image')
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror

                        @if ($group->imageUrl())
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-slate-300">
                                画像を削除する
                            </label>
                        @endif
                    </div>
                </div>
            </x-card>

            <x-button class="w-full" size="lg">変更を保存</x-button>
        </form>

        @can('delete', $group)
            <x-card title="危険な操作">
                <p class="mb-3 text-xs text-slate-500">
                    グループを削除すると、メンバー全員が閲覧できなくなります。イベントが登録されている場合は削除できません。
                </p>
                <form method="POST" action="{{ route('groups.destroy', $group) }}"
                      onsubmit="return confirm('グループ「{{ $group->name }}」を削除します。よろしいですか？');">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" class="w-full">グループを削除する</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-app-layout>
