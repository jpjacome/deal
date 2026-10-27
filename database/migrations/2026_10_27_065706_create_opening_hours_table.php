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
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            // Null = venue-wide hours; a space with its own rows ignores the venue-wide ones.
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = Monday … 7 = Sunday
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['space_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
