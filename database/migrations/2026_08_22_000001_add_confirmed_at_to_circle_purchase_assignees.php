<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 購入担当者の「立候補」と「確定」を区別するための列を追加する。
 *
 * confirmed_at が NULL のレコードは立候補中、値が入っていれば確定した担当者。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circle_purchase_assignees', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('assigned_quantity');
            $table->foreignId('assigned_by')->nullable()->after('confirmed_at')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('circle_purchase_assignees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
