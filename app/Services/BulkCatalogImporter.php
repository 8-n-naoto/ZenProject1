<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Event;
use App\Models\EventCircle;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * サークル・商品をテキストからまとめて登録する。
 *
 * 1行に「サークル名, 配置, 商品名, 価格」をカンマ／タブ区切りで書く。
 * 配置・商品名・価格は省略できる。同じサークル名の行は1つのサークルにまとめる。
 *
 *   夏空スタジオ, 東1 ア-12a, 新刊イラスト集, 1500
 *   夏空スタジオ, 東1 ア-12a, アクリルスタンド, 800
 *   ねこまた工房, 東2 ウ-05b
 */
class BulkCatalogImporter
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * テキストを解析する。
     *
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parse(string $text): array
    {
        $rows = [];
        $errors = [];

        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 「，」（全角カンマ）と「，」区切りの入力も受け付ける
            $fields = array_map('trim', preg_split('/[,\t、，]/u', $line) ?: []);
            $name = $fields[0] ?? '';

            if ($name === '') {
                $errors[] = $number.'行目: サークル名がありません。';

                continue;
            }

            if (mb_strlen($name) > 100) {
                $errors[] = $number.'行目: サークル名が長すぎます（100文字まで）。';

                continue;
            }

            $booth = $fields[1] ?? '';
            $productName = $fields[2] ?? '';
            // 「¥1,200」のようにカンマを含む価格でも読めるよう、4つ目以降は連結して扱う
            $priceText = implode('', array_slice($fields, 3));

            if ($booth !== '' && mb_strlen($booth) > 50) {
                $errors[] = $number.'行目: 配置が長すぎます（50文字まで）。';

                continue;
            }

            if ($productName !== '' && mb_strlen($productName) > 100) {
                $errors[] = $number.'行目: 商品名が長すぎます（100文字まで）。';

                continue;
            }

            $price = null;

            if ($productName !== '') {
                $priceSource = mb_convert_kana($priceText, 'n', 'UTF-8');

                // マイナス記号を落として正の価格として登録しないようにする
                if (preg_match('/[-ー－‐−–]\s*\d/u', $priceSource) === 1) {
                    $errors[] = $number.'行目: 「'.$productName.'」の価格がマイナスになっています。';

                    continue;
                }

                $normalized = preg_replace('/[^\d]/u', '', $priceSource) ?? '';

                if ($normalized === '') {
                    $errors[] = $number.'行目: 「'.$productName.'」の価格がありません。';

                    continue;
                }

                $price = (int) $normalized;

                if ($price > 10000000) {
                    $errors[] = $number.'行目: 価格が大きすぎます。';

                    continue;
                }
            }

            if ($productName === '' && $priceText !== '') {
                $errors[] = $number.'行目: 価格だけが書かれています。商品名も入力してください。';

                continue;
            }

            $rows[] = [
                'line' => $number,
                'circle' => $name,
                'booth' => $booth !== '' ? $booth : null,
                'product' => $productName !== '' ? $productName : null,
                'price' => $price,
            ];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = '登録する行がありません。';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * 解析済みの行をイベントに登録する。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{circles:int, products:int, reused:int}
     */
    public function import(Event $event, array $rows): array
    {
        $created = 0;
        $products = 0;
        $reused = 0;

        DB::transaction(function () use ($event, $rows, &$created, &$products, &$reused) {
            /** @var array<string, EventCircle> $known */
            $known = [];
            /** @var array<int, array<string, true>> $productKeys サークルID => 商品名キー */
            $productKeys = [];

            foreach ($event->eventCircles()->with('eventProducts')->get() as $circle) {
                $known[TextNormalizer::key($circle->display_name)] = $circle;
                $productKeys[$circle->id] = $circle->eventProducts
                    ->mapWithKeys(fn ($product) => [TextNormalizer::key($product->name) => true])
                    ->all();
            }

            foreach ($rows as $row) {
                $key = TextNormalizer::key($row['circle']);
                $circle = $known[$key] ?? null;

                if ($circle === null) {
                    $circle = $this->catalog->createCircle($event, [
                        'display_name' => $row['circle'],
                        'booth' => $row['booth'],
                        'force' => true,
                    ]);

                    $known[$key] = $circle;
                    $productKeys[$circle->id] = [];
                    $created++;
                } else {
                    $reused++;

                    // 配置が未設定なら補完する
                    if ($circle->booth === null && $row['booth'] !== null) {
                        $circle->update(['booth' => $row['booth']]);
                    }
                }

                if ($row['product'] === null) {
                    continue;
                }

                // 行ごとに商品を再取得しないよう、登録済みの商品名をメモリで持つ
                $productKey = TextNormalizer::key($row['product']);

                if (isset($productKeys[$circle->id][$productKey])) {
                    continue;
                }

                $this->catalog->createProduct($circle, [
                    'name' => $row['product'],
                    'price' => $row['price'],
                    'status' => ProductStatus::Selling->value,
                ]);

                $productKeys[$circle->id][$productKey] = true;
                $products++;
            }
        });

        return ['circles' => $created, 'products' => $products, 'reused' => $reused];
    }
}
