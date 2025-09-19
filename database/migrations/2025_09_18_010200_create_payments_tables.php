<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_total');
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->unsignedBigInteger('late_fee_a')->default(0);
            $table->unsignedBigInteger('late_fee_b')->default(0);
            $table->unsignedBigInteger('travel_stipend_a')->default(0);
            $table->unsignedBigInteger('travel_stipend_b')->default(0);
            $table->enum('status', ['preauthorized','captured','refunded','canceled','payout_pending','payout_settled','failed'])->default('preauthorized');
            $table->timestamps(3);
            $table->index('booking_id', 'idx_payments_booking');
            $table->index('status', 'idx_payments_status');
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['A','B']);
            $table->string('stripe_pi_id', 64)->unique();
            $table->unsignedBigInteger('amount_auth');
            $table->unsignedBigInteger('amount_captured')->default(0);
            $table->unsignedBigInteger('app_fee_piece')->default(0);
            $table->enum('status', ['requires_capture','captured','canceled','refunded','failed'])->default('requires_capture');
            $table->timestamps(3);
            $table->unique(['booking_id', 'role'], 'uq_pi_booking_role');
            $table->index('status', 'idx_pi_status');
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('operative_id')->constrained('users')->cascadeOnDelete();
            $table->string('stripe_transfer_id', 64)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['pending','paid','failed'])->default('pending');
            $table->timestamps(3);
            $table->unique('booking_id', 'uq_trans_booking');
            $table->index('status', 'idx_trans_status');
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('reason', 191);
            $table->string('evidence_uri', 255)->nullable();
            $table->enum('resolution', ['pending','partial_refund','refund','capture_upheld','ex_gratia','rejected'])->default('pending');
            $table->timestamps(3);
            $table->index('resolution', 'idx_disputes_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payments');
    }
};