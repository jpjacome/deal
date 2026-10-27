<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('attendees')->default(1);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->text('status_reason')->nullable();
            $table->string('source')->default('web');
            $table->string('manage_token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            // Availability lookups: overlapping bookings of one space in a time window.
            $table->index(['space_id', 'status', 'starts_at']);
            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
