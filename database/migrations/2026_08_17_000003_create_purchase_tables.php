<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('planned_quantity');
            $table->timestamps();
            $table->unique(['event_id', 'event_product_id', 'user_id']);
            $table->index(['user_id', 'event_id']);
        });

        Schema::create('shared_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_circle_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'event_circle_id']);
        });

        Schema::create('shared_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('planned_quantity');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['shared_purchase_id', 'event_product_id']);
        });

        Schema::create('circle_purchase_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('assigned_quantity');
            $table->timestamps();
            $table->unique(['shared_purchase_id', 'user_id']);
        });

        Schema::create('product_purchase_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('assigned_quantity');
            $table->timestamps();
            $table->unique(['shared_purchase_item_id', 'user_id']);
        });

        Schema::create('purchase_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shared_purchase_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('event_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_assignee_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('planned_quantity');
            $table->unsignedInteger('purchased_quantity');
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->unique('personal_purchase_id');
            $table->unique('shared_purchase_item_id');
            $table->index(['event_product_id', 'status']);
        });

        $this->addPurchaseResultExclusivityConstraint();
        $this->addNonNegativeCheck('purchase_results', 'purchase_results_quantity_nonnegative_check', 'planned_quantity >= 0 AND purchased_quantity >= 0 AND (unit_price IS NULL OR unit_price >= 0)', 'NEW.planned_quantity >= 0 AND NEW.purchased_quantity >= 0 AND (NEW.unit_price IS NULL OR NEW.unit_price >= 0)');

        Schema::create('purchase_result_shortage_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_result_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('shortage_quantity');
            $table->timestamps();
            $table->unique(['purchase_result_id', 'user_id']);
        });

        Schema::create('excess_takeovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_result_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('takeover_quantity');
            $table->timestamps();
            $table->unique('purchase_result_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS purchase_results_source_insert_check');
            DB::statement('DROP TRIGGER IF EXISTS purchase_results_source_update_check');
        }

        $this->dropSqliteNonNegativeChecks('purchase_results_quantity_nonnegative_check');

        Schema::dropIfExists('excess_takeovers');
        Schema::dropIfExists('purchase_result_shortage_users');
        Schema::dropIfExists('purchase_results');
        Schema::dropIfExists('product_purchase_assignees');
        Schema::dropIfExists('circle_purchase_assignees');
        Schema::dropIfExists('shared_purchase_items');
        Schema::dropIfExists('shared_purchases');
        Schema::dropIfExists('personal_purchases');
    }

    private function addPurchaseResultExclusivityConstraint(): void
    {
        $expression = '((personal_purchase_id IS NOT NULL AND shared_purchase_item_id IS NULL) OR (personal_purchase_id IS NULL AND shared_purchase_item_id IS NOT NULL))';

        if (DB::getDriverName() === 'sqlite') {
            $sqliteExpression = '((NEW.personal_purchase_id IS NOT NULL AND NEW.shared_purchase_item_id IS NULL) OR (NEW.personal_purchase_id IS NULL AND NEW.shared_purchase_item_id IS NOT NULL))';
            DB::statement("CREATE TRIGGER purchase_results_source_insert_check BEFORE INSERT ON purchase_results WHEN NOT {$sqliteExpression} BEGIN SELECT RAISE(ABORT, 'purchase result must have exactly one source'); END");
            DB::statement("CREATE TRIGGER purchase_results_source_update_check BEFORE UPDATE ON purchase_results WHEN NOT {$sqliteExpression} BEGIN SELECT RAISE(ABORT, 'purchase result must have exactly one source'); END");

            return;
        }

        DB::statement("ALTER TABLE purchase_results ADD CONSTRAINT purchase_results_one_source_check CHECK {$expression}");
    }

    private function addNonNegativeCheck(string $table, string $constraint, string $expression, string $sqliteExpression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER {$constraint}_insert BEFORE INSERT ON {$table} WHEN NOT ({$sqliteExpression}) BEGIN SELECT RAISE(ABORT, 'negative value is not allowed'); END");
            DB::statement("CREATE TRIGGER {$constraint}_update BEFORE UPDATE ON {$table} WHEN NOT ({$sqliteExpression}) BEGIN SELECT RAISE(ABORT, 'negative value is not allowed'); END");

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
    }

    private function dropSqliteNonNegativeChecks(string $constraint): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP TRIGGER IF EXISTS {$constraint}_insert");
            DB::statement("DROP TRIGGER IF EXISTS {$constraint}_update");
        }
    }
};
