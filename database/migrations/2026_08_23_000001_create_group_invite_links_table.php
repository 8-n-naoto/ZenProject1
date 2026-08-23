<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 招待リンク（合い言葉）でグループに参加できるようにする。
 *
 * ユーザーを検索して個別に招待する方法だけだと、
 * 「まだアカウントを持っていない人」を呼べないため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_invite_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('token', 64)->unique();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invite_links');
    }
};
