<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('muted_until', 3)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['thread_id', 'user_id'], 'uq_mute_thread_user');
            $table->index('muted_until', 'idx_mute_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_mutes');
    }
};