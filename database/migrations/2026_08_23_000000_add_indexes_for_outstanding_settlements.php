<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 「未精算のまとめ」（グループ横断で自分の未精算を集める）用の索引。
 *
 * 既存の索引は event_id + status のみで、
 * 「status = pending かつ 自分が payer / payee」を引くのに使えなかった。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->index(['payer_user_id', 'status'], 'settlements_payer_status_idx');
            $table->index(['payee_user_id', 'status'], 'settlements_payee_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex('settlements_payer_status_idx');
            $table->dropIndex('settlements_payee_status_idx');
        });
    }
};
