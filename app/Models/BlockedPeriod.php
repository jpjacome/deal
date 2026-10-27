<?php

namespace App\Models;

use Database\Factories\BlockedPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['space_id', 'starts_at', 'ends_at', 'reason'])]
class BlockedPeriod extends Model
{
    /** @use HasFactory<BlockedPeriodFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
