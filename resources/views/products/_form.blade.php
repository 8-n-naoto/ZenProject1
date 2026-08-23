@php use App\Enums\ProductStatus; @endphp

<x-card :subtitle="$circle->display_name">
    <div class="space-y-4">
        <x-input name="name" label="商品名" :value="$product?->name" required maxlength="100" placeholder="例: 新刊イラスト集" />
        <x-input name="price" label="価格（円）" type="number" min="0" step="1" :value="$product?->price" required inputmode="numeric" />
        <x-textarea name="description" label="メモ" :value="$product?->product?->description" rows="2" maxlength="500" placeholder="任意" />

        <x-image-field name="image" label="商品画像" :current-url="$product?->imageUrl()" />

        <div class="space-y-1.5">
            <label for="status" class="block text-sm font-medium text-slate-700">状態</label>
            <select id="status" name="status" aria-label="状態"
                    class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-base">
                @foreach (ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $product?->status->value ?? ProductStatus::Selling->value) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</x-card>
