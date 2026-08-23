<?php

namespace Tests\Feature;

use App\Models\EventProduct;
use App\Models\Group;
use App\Models\PersonalPurchase;
use App\Models\PurchaseResult;
use App\Models\SharedPurchaseItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MySQL 8.0 でそのまま動くことを、マイグレーションの記述から確認する。
     * （開発は SQLite だが、READMEでは本番に MySQL を想定している）
     */
    public function test_migrations_are_mysql_compatible(): void
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        $this->assertNotEmpty($files);

        $problems = [];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $name = basename($file);

            // テーブルごとに分けて、索引名の長さを確認する
            $blocks = preg_split('/Schema::create\(/', $source);
            array_shift($blocks);

            foreach ($blocks as $block) {
                if (preg_match("/^\s*'([a-z_]+)'/", $block, $m) !== 1) {
                    continue;
                }

                $table = $m[1];

                // 明示的に名前を付けた索引
                preg_match_all("/->(?:unique|index)\(\s*\[[^\]]*\]\s*,\s*'([^']+)'\s*\)/", $block, $named);

                foreach ($named[1] as $indexName) {
                    if (strlen($indexName) > 64) {
                        $problems[] = $name.': 索引名が64文字を超えています ('.$indexName.')';
                    }
                }

                // 名前を省略した索引（Laravel がテーブル名＋列名から自動生成する）
                preg_match_all("/->(unique|index)\(\s*\[([^\]]*)\]\s*\)/", $block, $auto, PREG_SET_ORDER);

                foreach ($auto as $match) {
                    $columns = str_replace([' ', "'", '"'], '', $match[2]);
                    $generated = $table.'_'.str_replace(',', '_', $columns).'_'.$match[1];

                    if (strlen($generated) > 64) {
                        $problems[] = $name.': 自動生成される索引名が64文字を超えます ('.$generated.')';
                    }
                }
            }

            // TEXT / JSON / BLOB にはデフォルト値を付けられない
            if (preg_match('/->(text|json|longText)\([^)]*\)->default\(/', $source) === 1) {
                $problems[] = $name.': text/json 列に default があります';
            }

            // 255文字を超える列への unique は行サイズ制限に触れる
            if (preg_match("/->string\(\s*'[a-z_]+'\s*,\s*(\d{3,})\s*\)->unique\(/", $source, $m) === 1
                && (int) $m[1] > 255) {
                $problems[] = $name.': 255文字を超える列に unique が付いています';
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_indexes_exist_for_the_hot_queries(): void
    {
        $expected = [
            'settlements' => ['settlements_payer_status_idx', 'settlements_payee_status_idx'],
        ];

        foreach ($expected as $table => $indexes) {
            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();

            foreach ($indexes as $index) {
                $this->assertContains($index, $existing, $table.' に索引 '.$index.' がありません');
            }
        }
    }

    public function test_community_tables_are_created(): void
    {
        foreach ([
            'groups', 'group_members', 'invitations', 'events', 'event_days', 'event_members',
            'circles', 'event_circles', 'products', 'event_products', 'personal_purchases',
            'shared_purchases', 'shared_purchase_items', 'circle_purchase_assignees',
            'product_purchase_assignees', 'purchase_results', 'purchase_result_shortage_users',
            'excess_takeovers', 'payments', 'payment_items', 'settlements', 'approvals',
            'approval_actions', 'notifications', 'change_histories',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', ['user_id', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('purchase_results', [
            'personal_purchase_id', 'shared_purchase_item_id', 'purchase_assignee_user_id',
            'planned_quantity', 'purchased_quantity', 'unit_price', 'status',
        ]));
    }

    public function test_user_id_is_required(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['user_id' => null]);
    }

    public function test_user_id_is_unique(): void
    {
        User::factory()->create(['user_id' => 'test001']);

        $this->expectException(QueryException::class);
        User::factory()->create(['user_id' => 'test001']);
    }

    public function test_group_membership_cannot_be_duplicated(): void
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        DB::table('group_members')->insert([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('group_members')->insert([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_foreign_keys_reject_unknown_event_references(): void
    {
        $this->expectException(QueryException::class);
        DB::table('event_days')->insert([
            'event_id' => 999999,
            'event_date' => '2026-12-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_personal_purchase_cannot_be_duplicated(): void
    {
        $purchase = PersonalPurchase::factory()->create();

        $this->expectException(QueryException::class);
        PersonalPurchase::factory()->create([
            'event_id' => $purchase->event_id,
            'event_product_id' => $purchase->event_product_id,
            'user_id' => $purchase->user_id,
        ]);
    }

    public function test_purchase_result_requires_exactly_one_source(): void
    {
        $result = PurchaseResult::factory()->create();
        $this->assertNotNull($result->personal_purchase_id);
        $this->assertNull($result->shared_purchase_item_id);

        $product = EventProduct::factory()->create();
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('purchase_results')->insert([
            'personal_purchase_id' => null,
            'shared_purchase_item_id' => null,
            'event_product_id' => $product->id,
            'purchase_assignee_user_id' => $user->id,
            'planned_quantity' => 1,
            'purchased_quantity' => 0,
            'unit_price' => null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_money_columns_reject_negative_values(): void
    {
        $product = EventProduct::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('event_products')->where('id', $product->id)->update(['price' => -1]);
    }

    public function test_required_soft_deletable_models_preserve_rows(): void
    {
        $group = Group::factory()->create();
        $group->delete();

        $this->assertSoftDeleted('groups', ['id' => $group->id]);

        $item = SharedPurchaseItem::factory()->create();
        $item->delete();

        $this->assertSoftDeleted('shared_purchase_items', ['id' => $item->id]);
    }

    public function test_columns_added_for_the_settlement_flow_exist(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('payments', 'settlement_id'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('notifications', 'read_at'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('circle_purchase_assignees', 'confirmed_at'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('circle_purchase_assignees', 'assigned_by'));
    }

    public function test_confirmed_by_is_nullable_on_payments(): void
    {
        $event = \App\Models\Event::factory()->create();
        $payer = \App\Models\User::factory()->create();
        $payee = \App\Models\User::factory()->create();

        $payment = \App\Models\Payment::create([
            'event_id' => $event->id,
            'payer_user_id' => $payer->id,
            'payee_user_id' => $payee->id,
            'confirmed_by' => null,
            'total_amount' => 1000,
            'status' => \App\Enums\PaymentStatus::Reported,
            'paid_at' => now(),
        ]);

        $this->assertNull($payment->fresh()->confirmed_by);
    }

    public function test_memos_table_is_dropped(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('memos'));
    }
}
