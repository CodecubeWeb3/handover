<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operatives', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->enum('kyc_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->decimal('reliability_score', 5, 2)->default(0);
            $table->string('stripe_connect_id', 64)->nullable();
            $table->json('languages')->nullable();
            $table->string('bio', 500)->nullable();
            $table->timestamps(3);
        });

        Schema::create('parents', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamps(3);
        });

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->binary('credential_id')->unique();
            $table->binary('public_key');
            $table->string('transports', 191)->nullable();
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->timestamp('last_used_at', 3)->nullable();
            $table->timestamps(3);
            $table->index('user_id', 'idx_webauthn_user');
        });

        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['stripe_identity', 'onfido', 'veriff']);
            $table->string('provider_ref', 100);
            $table->enum('result', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 3)->nullable();
            $table->timestamps(3);
            $table->index('user_id', 'idx_verif_user');
            $table->index(['provider', 'provider_ref'], 'idx_verif_provider');
        });

        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['warning', 'strike', 'suspension', 'ban']);
            $table->string('reason', 255)->nullable();
            $table->timestamp('expires_at', 3)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index('user_id', 'idx_sanctions_user');
            $table->index('expires_at', 'idx_sanctions_exp');
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 100);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index(['target_type', 'target_id'], 'idx_audits_target');
            $table->index('created_at', 'idx_audits_time');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['global', 'country', 'user'])->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key', 100);
            $table->json('value');
            $table->timestamp('created_at', 3)->useCurrent();
            $table->timestamp('updated_at', 3)->useCurrent()->useCurrentOnUpdate();
            $table->unique(['scope_type', 'scope_id', 'key'], 'uq_settings');
            $table->index('key', 'idx_settings_key');
        });

        Schema::create('country_profiles', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2)->unique();
            $table->tinyInteger('legal_age_min')->default(18);
            $table->enum('kyc_level', ['none', 'basic', 'enhanced'])->default('basic');
            $table->boolean('vat_on_platform')->default(true);
            $table->integer('retention_days')->default(365);
            $table->integer('grace_a_min')->default(5);
            $table->integer('grace_b_min')->default(5);
            $table->integer('wait_cap_a_min')->default(15);
            $table->integer('wait_cap_b_min')->default(15);
            $table->integer('geofence_m')->default(100);
            $table->integer('buffer_min')->default(2);
            $table->integer('slot_minutes')->default(10);
            $table->unsignedBigInteger('late_fee_base')->default(300);
            $table->unsignedBigInteger('late_fee_per_min')->default(50);
            $table->unsignedBigInteger('travel_stipend')->default(200);
            $table->integer('min_capture_pct_no_show')->default(50);
            $table->integer('platform_pct')->default(10);
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_profiles');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('webauthn_credentials');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('operatives');
    }
};