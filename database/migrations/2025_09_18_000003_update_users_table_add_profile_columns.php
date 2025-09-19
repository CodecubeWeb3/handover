<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['parent', 'operative', 'admin', 'moderator'])
                    ->default('parent')
                    ->after('id');
                $table->index('role');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)
                    ->nullable()
                    ->unique()
                    ->after('email');
            }

            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')
                    ->nullable()
                    ->after('phone');
            }

            if (! Schema::hasColumn('users', 'country')) {
                $table->char('country', 2)
                    ->default('GB')
                    ->after('phone_verified_at');
                $table->index('country');
            }

            if (! Schema::hasColumn('users', 'dob')) {
                $table->date('dob')
                    ->nullable()
                    ->after('country');
            }

            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')
                    ->nullable()
                    ->after('password');
            }

            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')
                    ->nullable()
                    ->after('two_factor_secret');
            }

            if (! Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id', 64)
                    ->nullable()
                    ->after('two_factor_recovery_codes');
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }

            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->dropColumn('two_factor_recovery_codes');
            }

            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $table->dropColumn('two_factor_secret');
            }

            if (Schema::hasColumn('users', 'dob')) {
                $table->dropColumn('dob');
            }

            if (Schema::hasColumn('users', 'country')) {
                $table->dropIndex(['country']);
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }

            if (Schema::hasColumn('users', 'phone')) {
                $table->dropUnique(['phone']);
                $table->dropColumn('phone');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }
        });
    }
};