<?php

namespace App\Http\Resources;

use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Space
 */
class SpaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type_label,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'booking_mode' => $this->booking_mode->value,
            'allows_half_day' => $this->allows_half_day,
            'price' => $this->price !== null ? (float) $this->price : null,
            'price_unit' => $this->priceUnitLabel(),
            'price_label' => $this->price_label,
            'photo_url' => $this->photoUrl(),
            'amenities' => $this->amenities ?? [],
            'requires_approval' => $this->requires_approval,
            'rules' => [
                'slot_minutes' => $this->slot_minutes,
                'min_minutes' => $this->min_minutes,
                'max_minutes' => $this->max_minutes,
                'lead_time_minutes' => $this->lead_time_minutes,
                'horizon_days' => $this->horizon_days,
                'cancellation_hours' => $this->cancellation_hours,
            ],
        ];
    }
}
