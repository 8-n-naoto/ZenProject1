<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 参加者ごとの予算。
 *
 * 会場で「あといくら使えるか」を即座に確認できるようにするための値。
 * 未設定（null）なら残高の表示自体を出さない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_members', function (Blueprint $table) {
            $table->unsignedInteger('budget')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_members', function (Blueprint $table) {
            $table->dropColumn('budget');
        });
    }
};
