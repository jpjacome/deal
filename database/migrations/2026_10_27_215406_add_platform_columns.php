<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns for notifications, reminders, check-in, recurring bookings, staff security and richer spaces.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->string('calendar_token', 64)->nullable()->unique();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change(); // walk-ins booked by staff may have no email
            $table->string('privacy_version')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('membership_nudged_at')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->index();
            $table->timestamp('pending_reminder_sent_at')->nullable();
            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('feedback_sent_at')->nullable();
        });

        Schema::table('spaces', function (Blueprint $table) {
            $table->string('photo')->nullable();
            $table->json('amenities')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->boolean('allows_half_day')->default(false);
            $table->decimal('half_day_price', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['photo', 'amenities', 'price', 'allows_half_day', 'half_day_price']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn(['series_id', 'pending_reminder_sent_at', 'attendance_confirmed_at', 'checked_in_at', 'feedback_sent_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['privacy_version', 'notes', 'tags', 'membership_nudged_at']);
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['calendar_token']);
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'calendar_token']);
        });
    }
};
