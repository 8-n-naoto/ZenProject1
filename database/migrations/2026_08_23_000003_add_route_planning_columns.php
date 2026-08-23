<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 当日の購入ルート。
 *
 * - sellout_risk: 完売リスク。高いサークルを先に回れるようにする
 * - shopping_routes: 手動で並べ替えた順番を人ごとに保存する
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_circles', function (Blueprint $table) {
            $table->string('sellout_risk', 10)->nullable()->after('booth');
        });

        Schema::create('shopping_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('circle_order');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_routes');

        Schema::table('event_circles', function (Blueprint $table) {
            $table->dropColumn('sellout_risk');
        });
    }
};
