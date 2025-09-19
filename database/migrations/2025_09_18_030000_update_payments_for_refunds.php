<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_total')->default(0)->after('amount_total');
            $table->string('refund_reason', 191)->nullable()->after('refund_total');
            $table->timestamp('refunded_at', 3)->nullable()->after('refund_reason');
            $table->timestamp('payout_settled_at', 3)->nullable()->after('status');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->timestamp('settled_at', 3)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refund_total', 'refund_reason', 'refunded_at', 'payout_settled_at']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('settled_at');
        });
    }
};