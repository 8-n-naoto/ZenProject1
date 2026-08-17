<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'role']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'status']);
            $table->index(['invited_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
