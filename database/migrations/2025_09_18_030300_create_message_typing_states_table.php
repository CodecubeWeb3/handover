<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_typing_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('state', ['started', 'stopped'])->default('started');
            $table->timestamp('updated_at', 3)->useCurrent();
            $table->unique(['thread_id', 'user_id'], 'uq_typing_thread_user');
            $table->index('updated_at', 'idx_typing_updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_typing_states');
    }
};