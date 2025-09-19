<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->string('client_secret', 255)->nullable()->after('stripe_pi_id');
            $table->string('last_status', 50)->nullable()->after('status');
            $table->text('last_error')->nullable()->after('last_status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn(['client_secret', 'last_status', 'last_error']);
        });
    }
};