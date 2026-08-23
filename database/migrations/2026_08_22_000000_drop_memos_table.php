<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * メモ機能の廃止に伴い memos テーブルを削除する。
 * 共同購入管理と無関係で、所有者・認可の概念も持たないため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('memos');
    }

    public function down(): void
    {
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->text('memo');
            $table->timestamps();
        });
    }
};
