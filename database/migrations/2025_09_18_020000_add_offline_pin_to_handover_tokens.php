<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handover_tokens', function (Blueprint $table) {
            $table->binary('offline_pin_encrypted')->nullable()->after('totp_secret_hash');
            $table->index('rotated_at', 'idx_tokens_rotated_at');
        });
    }

    public function down(): void
    {
        Schema::table('handover_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_tokens_rotated_at');
            $table->dropColumn('offline_pin_encrypted');
        });
    }
};