<?php

namespace Database\Seeders;

use App\Enums\BookingMode;
use App\Models\OpeningHour;
use App\Models\Space;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Placeholder venue setup until Deal confirms its spaces and hours (PLAN.md §11).
 * Safe to re-run: existing rows are updated, not duplicated. Admin users are created
 * with `php artisan make:filament-user`.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Venue-wide hours: Mon–Fri 08:00–19:00, Sat 09:00–13:00.
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::updateOrCreate(['space_id' => null, 'weekday' => $weekday], ['opens_at' => '08:00', 'closes_at' => '19:00']);
        }
        OpeningHour::updateOrCreate(['space_id' => null, 'weekday' => 6], ['opens_at' => '09:00', 'closes_at' => '13:00']);

        $spaces = [
            ['slug' => 'sala-de-reuniones', 'name' => 'Sala de reuniones', 'type_label' => 'Sala', 'capacity' => 8, 'booking_mode' => BookingMode::Hourly, 'requires_approval' => true, 'price' => 10, 'amenities' => ['Pantalla', 'Pizarra', 'Videollamadas', 'Café'], 'sort' => 1],
            ['slug' => 'cabina-privada', 'name' => 'Cabina privada', 'type_label' => 'Cabina', 'capacity' => 1, 'booking_mode' => BookingMode::Hourly, 'requires_approval' => false, 'price' => 4, 'amenities' => ['Insonorizada', 'Videollamadas'], 'sort' => 2],
            ['slug' => 'oficina-privada', 'name' => 'Oficina privada', 'type_label' => 'Oficina', 'capacity' => 4, 'booking_mode' => BookingMode::Daily, 'requires_approval' => true, 'price' => 35, 'allows_half_day' => true, 'half_day_price' => 20, 'amenities' => ['Luz natural', 'Llave propia', 'Wifi'], 'sort' => 3],
            ['slug' => 'puesto-flexible', 'name' => 'Puesto flexible', 'type_label' => 'Coworking', 'capacity' => 20, 'booking_mode' => BookingMode::Capacity, 'requires_approval' => false, 'price' => 12, 'allows_half_day' => true, 'half_day_price' => 7, 'amenities' => ['Wifi', 'Café', 'Locker'], 'sort' => 4],
        ];

        // Stand-in Unsplash photos (public/images/home) until Deal uploads its own; never overwrites an upload.
        $photos = ['sala-de-reuniones' => 'meeting', 'cabina-privada' => 'focus', 'oficina-privada' => 'office', 'puesto-flexible' => 'flex'];

        foreach ($spaces as $data) {
            $space = Space::updateOrCreate(['slug' => $data['slug']], $data);
            $source = public_path('images/home/'.($photos[$space->slug] ?? '').'-900.webp');

            if (! $space->photo && isset($photos[$space->slug]) && is_file($source)) {
                Storage::disk('public')->put("spaces/{$space->slug}.webp", file_get_contents($source));
                $space->update(['photo' => "spaces/{$space->slug}.webp"]);
            }
        }
    }
}
