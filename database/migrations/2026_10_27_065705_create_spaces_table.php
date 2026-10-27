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
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type_label')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('booking_mode')->default('hourly');
            $table->boolean('requires_approval')->default(true);
            $table->unsignedSmallInteger('slot_minutes')->default(60);
            $table->unsignedSmallInteger('min_minutes')->default(60);
            $table->unsignedSmallInteger('max_minutes')->default(480);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('lead_time_minutes')->default(120);
            $table->unsignedSmallInteger('horizon_days')->default(60);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->string('price_label')->nullable();
            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
