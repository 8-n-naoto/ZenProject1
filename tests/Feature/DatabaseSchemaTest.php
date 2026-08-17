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

    public function test_user_id_is_unique_and_existing_users_can_remain_null(): void
    {
        User::factory()->create(['user_id' => 'codex001']);
        User::factory()->create(['user_id' => null]);

        $this->expectException(QueryException::class);
        User::factory()->create(['user_id' => 'codex001']);
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
}
