<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('venue_name');
            $table->string('venue_address')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('fixed_at')->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['group_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->date('event_date');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'event_date']);
        });

        Schema::create('event_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->string('map_image_path')->nullable();
            $table->integer('map_x')->nullable();
            $table->integer('map_y')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('event_circles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('circle_id')->constrained()->restrictOnDelete();
            $table->string('display_name');
            $table->string('booth')->nullable();
            $table->string('map_image_path')->nullable();
            $table->integer('map_x')->nullable();
            $table->integer('map_y')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'circle_id']);
            $table->index(['event_id', 'display_name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('event_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_circle_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('image_path')->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['event_circle_id', 'product_id']);
            $table->index(['event_id', 'status']);
        });

        $this->addNonNegativeCheck('event_products', 'event_products_price_nonnegative_check', 'price >= 0', 'NEW.price >= 0');
    }

    public function down(): void
    {
        $this->dropSqliteNonNegativeChecks('event_products_price_nonnegative_check');
        Schema::dropIfExists('event_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('event_circles');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('event_members');
        Schema::dropIfExists('event_days');
        Schema::dropIfExists('events');
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
