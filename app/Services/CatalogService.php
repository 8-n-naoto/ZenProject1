<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Circle;
use App\Models\Event;
use App\Models\EventCircle;
use App\Models\EventProduct;
use App\Models\Product;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * イベント内のサークル・商品カタログの登録。
 *
 * サークル・商品はイベント単位で完結する。マスタ（circles / products）には
 * イベントごとにレコードを作成し、重複検知は「同一イベント内の同名サークル」で行う。
 */
class CatalogService
{
    public function __construct(private readonly ImageStorageService $images) {}

    /**
     * 同一イベント内に同名のサークルが登録済みかどうか。
     */
    public function findDuplicateCircle(Event $event, string $displayName, ?int $exceptId = null): ?EventCircle
    {
        $key = TextNormalizer::key($displayName);

        if ($key === '') {
            return null;
        }

        return $event->eventCircles()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->first(fn (EventCircle $circle) => TextNormalizer::key($circle->display_name) === $key);
    }

    /**
     * サークルを登録する。
     *
     * @param  array{display_name:string,booth?:?string,website_url?:?string,description?:?string,force?:bool}  $data
     */
    public function createCircle(Event $event, array $data): EventCircle
    {
        // force が立っている場合は重複を許容するので、重複検索そのものを省く
        // （一括登録では呼び出し側が重複をまとめて判定している）
        $duplicate = ($data['force'] ?? false)
            ? null
            : $this->findDuplicateCircle($event, $data['display_name']);

        if ($duplicate !== null && ! ($data['force'] ?? false)) {
            throw new BusinessRuleException(
                '同じ名前のサークル「'.$duplicate->display_name.'」がすでに登録されています。'
                .'同じサークルであれば既存の登録に商品を追加してください。',
                'display_name'
            );
        }

        return DB::transaction(function () use ($event, $data) {
            $circle = Circle::create([
                'name' => $data['display_name'],
                'website_url' => $data['website_url'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            return $event->eventCircles()->create([
                'circle_id' => $circle->id,
                'display_name' => $data['display_name'],
                'booth' => $data['booth'] ?? null,
                'sellout_risk' => $data['sellout_risk'] ?? null,
                'map_image_path' => ($data['map_image'] ?? null) instanceof UploadedFile
                    ? $this->images->store($data['map_image'], 'circles')
                    : null,
                'map_x' => $data['map_x'] ?? null,
                'map_y' => $data['map_y'] ?? null,
            ]);
        });
    }

    /**
     * サークル情報を更新する。
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCircle(EventCircle $eventCircle, array $data): EventCircle
    {
        $duplicate = $this->findDuplicateCircle($eventCircle->event, $data['display_name'], $eventCircle->id);

        if ($duplicate !== null && ! ($data['force'] ?? false)) {
            throw new BusinessRuleException(
                '同じ名前のサークル「'.$duplicate->display_name.'」がすでに登録されています。',
                'display_name'
            );
        }

        return DB::transaction(function () use ($eventCircle, $data) {
            $eventCircle->circle?->update([
                'name' => $data['display_name'],
                'website_url' => $data['website_url'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $image = $this->images->sync(
                ($data['map_image'] ?? null) instanceof UploadedFile ? $data['map_image'] : null,
                $eventCircle->map_image_path,
                'circles',
                (bool) ($data['remove_map_image'] ?? false)
            );

            $attributes = [
                'display_name' => $data['display_name'],
                'booth' => $data['booth'] ?? null,
                'sellout_risk' => $data['sellout_risk'] ?? null,
            ];

            if ($image['changed']) {
                // 画像を差し替えた／消した場合は、前の画像に対するピンの位置が意味を失うので必ず消す
                $attributes['map_image_path'] = $image['path'];
                $attributes['map_x'] = null;
                $attributes['map_y'] = null;
            } elseif ($image['path'] !== null && array_key_exists('map_x', $data) && array_key_exists('map_y', $data)) {
                $attributes['map_x'] = $data['map_x'];
                $attributes['map_y'] = $data['map_y'];
            }

            $eventCircle->update($attributes);

            return $eventCircle->fresh();
        });
    }

    /**
     * サークルを削除する。購入希望が紐づいている場合は削除できない。
     */
    public function deleteCircle(EventCircle $eventCircle): void
    {
        $hasPurchases = $eventCircle->eventProducts()
            ->withTrashed()
            ->where(function ($query) {
                $query->whereHas('personalPurchases')->orWhereHas('sharedPurchaseItems');
            })
            ->exists();

        if ($hasPurchases) {
            throw new BusinessRuleException('購入希望が登録されている商品があるため削除できません。', 'circle');
        }

        if ($eventCircle->sharedPurchase()->exists()) {
            throw new BusinessRuleException('共同購入リストが作成されているため削除できません。', 'circle');
        }

        DB::transaction(function () use ($eventCircle) {
            // 購入希望が無いことを確認済みなので、履歴を残す必要はない。
            // event_circles は外部キーが RESTRICT のため、商品は完全削除する。
            $eventCircle->eventProducts()->withTrashed()->get()
                ->each(function (EventProduct $product) {
                    $this->images->delete($product->image_path);
                    $product->forceDelete();
                });

            $this->images->delete($eventCircle->map_image_path);
            $eventCircle->delete();
        });
    }

    /**
     * 商品を登録する。
     *
     * @param  array{name:string,price:int,description?:?string,status?:string}  $data
     */
    public function createProduct(EventCircle $eventCircle, array $data): EventProduct
    {
        return DB::transaction(function () use ($eventCircle, $data) {
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            return $eventCircle->eventProducts()->create([
                'event_id' => $eventCircle->event_id,
                'product_id' => $product->id,
                'name' => $data['name'],
                'price' => $data['price'],
                'status' => $data['status'] ?? ProductStatus::Selling->value,
                'image_path' => ($data['image'] ?? null) instanceof UploadedFile
                    ? $this->images->store($data['image'], 'products')
                    : null,
            ]);
        });
    }

    /**
     * 商品を更新する。
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(EventProduct $eventProduct, array $data): EventProduct
    {
        return DB::transaction(function () use ($eventProduct, $data) {
            $eventProduct->product?->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $image = $this->images->sync(
                ($data['image'] ?? null) instanceof UploadedFile ? $data['image'] : null,
                $eventProduct->image_path,
                'products',
                (bool) ($data['remove_image'] ?? false)
            );

            $attributes = [
                'name' => $data['name'],
                'price' => $data['price'],
                'status' => $data['status'] ?? $eventProduct->status->value,
            ];

            if ($image['changed']) {
                $attributes['image_path'] = $image['path'];
            }

            $eventProduct->update($attributes);

            return $eventProduct->fresh();
        });
    }

    /**
     * 商品を削除する。購入希望が紐づいている場合は削除できない。
     */
    public function deleteProduct(EventProduct $eventProduct): void
    {
        if ($eventProduct->personalPurchases()->exists() || $eventProduct->sharedPurchaseItems()->exists()) {
            throw new BusinessRuleException('購入希望が登録されているため削除できません。', 'product');
        }

        // 画像ファイルが残り続けないよう、レコードと一緒に消す
        $this->images->delete($eventProduct->image_path);

        $eventProduct->delete();
    }
}
