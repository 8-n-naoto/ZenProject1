<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 支払いを「支払い報告 → 受取確認」の2段階にするための調整。
 *
 * - settlement_id: どの精算（送金）に対する支払いかを保持する
 * - confirmed_by : 支払い報告の時点では受取確認者が未定のため NULL を許可する
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->after('event_id')
                ->constrained()->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmed_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settlement_id');
        });
    }
};
