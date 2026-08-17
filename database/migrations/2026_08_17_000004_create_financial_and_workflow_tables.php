<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payee_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['payer_user_id', 'status']);
            $table->index(['payee_user_id', 'status']);
        });

        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_result_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['payment_id', 'purchase_result_id']);
            $table->index('purchase_result_id');
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payee_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20);
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('applicant_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('action_type');
            $table->string('status', 20);
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->unique(['approval_id', 'actor_user_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('notified_at');
            $table->timestamps();
            $table->index(['user_id', 'notified_at']);
            $table->index(['event_id', 'type']);
        });

        Schema::create('change_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['event_id', 'occurred_at']);
        });

        $this->addNonNegativeCheck('payments', 'payments_total_amount_nonnegative_check', 'total_amount >= 0', 'NEW.total_amount >= 0');
        $this->addNonNegativeCheck('payment_items', 'payment_items_amount_nonnegative_check', 'quantity >= 0 AND amount >= 0', 'NEW.quantity >= 0 AND NEW.amount >= 0');
        $this->addNonNegativeCheck('settlements', 'settlements_amount_nonnegative_check', 'amount >= 0', 'NEW.amount >= 0');
    }

    public function down(): void
    {
        $this->dropSqliteNonNegativeChecks('settlements_amount_nonnegative_check');
        $this->dropSqliteNonNegativeChecks('payment_items_amount_nonnegative_check');
        $this->dropSqliteNonNegativeChecks('payments_total_amount_nonnegative_check');
        Schema::dropIfExists('change_histories');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('payment_items');
        Schema::dropIfExists('payments');
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
