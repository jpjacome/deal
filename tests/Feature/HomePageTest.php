<?php

namespace Tests\Feature;

use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_links_each_space_to_the_booking_flow(): void
    {
        Space::factory()->create(['name' => 'Sala Andes', 'slug' => 'sala-andes']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sala Andes')
            ->assertSee(route('bookings.create', ['espacio' => 'sala-andes']), escape: false);
    }
}
