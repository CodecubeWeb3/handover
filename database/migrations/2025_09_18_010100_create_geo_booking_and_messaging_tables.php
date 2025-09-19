<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps(3);
            $table->index('user_id', 'idx_areas_user');
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'partially_filled', 'closed', 'canceled'])->default('open');
            $table->timestamps(3);
            $table->index('parent_id', 'idx_requests_parent');
            $table->index('status', 'idx_requests_status');
        });

        Schema::create('time_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->timestamp('start_ts', 3);
            $table->timestamp('end_ts', 3);
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index('request_id', 'idx_windows_req');
            $table->index(['start_ts', 'end_ts'], 'idx_windows_span');
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->timestamp('slot_ts', 3);
            $table->enum('status', ['Open', 'Filled', 'Waitlist'])->default('Open');
            $table->timestamps(3);
            $table->unique(['request_id', 'slot_ts'], 'uq_slots_req_ts');
            $table->index('slot_ts', 'idx_slots_ts');
            $table->index('status', 'idx_slots_status');
        });

        Schema::create('operative_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operative_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('slot_ts', 3);
            $table->timestamp('expires_at', 3);
            $table->timestamps(3);
            $table->unique(['operative_id', 'slot_ts'], 'uq_holds_op_ts');
            $table->index('expires_at', 'idx_holds_exp');
            $table->index('slot_ts', 'idx_holds_ts');
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('booking_slots')->cascadeOnDelete();
            $table->foreignId('operative_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('slot_ts', 3);
            $table->enum('status', ['Pending', 'Accepted', 'Rejected', 'Withdrawn'])->default('Pending');
            $table->timestamps(3);
            $table->unique(['operative_id', 'slot_ts'], 'uq_apps_op_ts');
            $table->index('slot_id', 'idx_apps_slot');
            $table->index('status', 'idx_apps_status');
        });

        Schema::create('waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('booking_slots')->cascadeOnDelete();
            $table->foreignId('operative_id')->constrained('users')->cascadeOnDelete();
            $table->integer('position');
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['slot_id', 'operative_id'], 'uq_wait_slot_op');
            $table->unique(['slot_id', 'position'], 'uq_wait_slot_pos');
            $table->index('slot_id', 'idx_wait_slot');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('booking_slots')->cascadeOnDelete();
            $table->foreignId('operative_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('slot_ts', 3);
            $table->enum('status', [
                'Scheduled','A_WINDOW_OPEN','A_SCANNED','BUFFER','B_WINDOW_OPEN','B_SCANNED','COMPLETED','NO_SHOW_A','NO_SHOW_B','CANCELED','EXPIRED','FROZEN'
            ])->default('Scheduled');
            $table->string('meet_qr', 191)->nullable();
            $table->integer('buffer_minutes')->default(2);
            $table->integer('geofence_radius_m')->default(100);
            $table->timestamps(3);
            $table->unique(['operative_id', 'slot_ts'], 'uq_book_op_ts');
            $table->index('status', 'idx_book_status');
            $table->index('slot_id', 'idx_book_slot');
        });

        Schema::create('handover_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('leg', ['A', 'B']);
            $table->binary('totp_secret_hash');
            $table->timestamp('rotated_at', 3);
            $table->timestamp('used_at', 3)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['booking_id', 'leg'], 'uq_tokens_leg');
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('kind', ['A_SCAN','B_SCAN','PHOTO','AGENT_OUT_OF_ZONE','OVERRIDE','A_NOSHOW','B_NOSHOW']);
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->integer('accuracy_m')->nullable();
            $table->string('token_id', 64)->nullable();
            $table->boolean('device_attested')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index('booking_id', 'idx_checkins_booking');
            $table->index('kind', 'idx_checkins_kind');
            $table->index('created_at', 'idx_checkins_time');
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('event_type', [
                'STATE_CHANGE','CHECKIN_A','CHECKIN_B','LATE_A','LATE_B','NO_SHOW_A','NO_SHOW_B','CAPTURED','REFUNDED','DISPUTE_OPENED','DISPUTE_RESOLVED','GEO_FREEZE','OVERRIDE'
            ]);
            $table->json('payload_json');
            $table->integer('chain_index');
            $table->binary('prev_hash')->nullable();
            $table->binary('this_hash');
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['booking_id', 'chain_index'], 'uq_bkevt_chain');
            $table->index('event_type', 'idx_bkevt_type');
            $table->index('created_at', 'idx_bkevt_time');
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['parent_rates_operative','operative_rates_parent']);
            $table->tinyInteger('stars');
            $table->string('tag', 50)->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['booking_id', 'rater_id', 'role'], 'uq_ratings_once');
            $table->index('ratee_id', 'idx_ratings_ratee');
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->timestamps(3);
            $table->unique('booking_id', 'uq_thread_per_booking');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index(['thread_id', 'created_at'], 'idx_msgs_thread_time');
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('storage_disk', 50);
            $table->string('storage_path', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->timestamp('created_at', 3)->useCurrent();
            $table->index('message_id', 'idx_msgatt_msg');
        });

        Schema::create('message_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 191);
            $table->timestamp('created_at', 3)->useCurrent();
            $table->unique(['message_id', 'reporter_id'], 'uq_msg_flag_once');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE areas ADD geom POLYGON SRID 4326 NULL');
            DB::statement('ALTER TABLE areas ADD SPATIAL INDEX sidx_areas_geom (geom)');
            DB::statement('ALTER TABLE requests ADD meet_point POINT SRID 4326 NULL');
            DB::statement('ALTER TABLE requests ADD SPATIAL INDEX sidx_requests_meet (meet_point)');
            DB::statement('ALTER TABLE bookings ADD meet_point POINT SRID 4326 NULL');
            DB::statement('ALTER TABLE bookings ADD SPATIAL INDEX sidx_book_meet (meet_point)');
        } else {
            Schema::table('areas', function (Blueprint $table) {
                $table->text('geom')->nullable();
            });

            Schema::table('requests', function (Blueprint $table) {
                $table->text('meet_point')->nullable();
            });

            Schema::table('bookings', function (Blueprint $table) {
                $table->text('meet_point')->nullable();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ratings ADD CONSTRAINT chk_ratings_stars CHECK (stars BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE messages ADD FULLTEXT INDEX ftx_msgs_body (body)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_flags');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('handover_tokens');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('waitlist');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('operative_holds');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('time_windows');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('areas');
    }
};