<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_read_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('read_at', 3)->useCurrent();
            $table->unique(['thread_id', 'user_id'], 'uq_read_thread_user');
            $table->index('read_at', 'idx_read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_read_states');
    }
};