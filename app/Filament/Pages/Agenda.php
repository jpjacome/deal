<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Space;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Week view of active bookings, one column per day.
 */
class Agenda extends Page
{
    protected string $view = 'filament.pages.agenda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Agenda';

    /** Monday of the displayed week (Y-m-d). */
    #[Url(as: 'semana')]
    public ?string $week = null;

    #[Url(as: 'espacio')]
    public ?string $spaceId = null;

    public function weekStart(): CarbonImmutable
    {
        try {
            $date = $this->week ? CarbonImmutable::createFromFormat('!Y-m-d', $this->week) : null;
        } catch (Throwable) {
            $date = null;
        }

        return ($date ?: today()->toImmutable())->startOfWeek();
    }

    public function previousWeek(): void
    {
        $this->week = $this->weekStart()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->weekStart()->addWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->week = null;
    }

    /**
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces(): Collection
    {
        return Space::orderBy('sort')->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return list<array{date: CarbonImmutable, bookings: Collection<int, Booking>}>
     */
    #[Computed]
    public function days(): array
    {
        $start = $this->weekStart();

        $bookings = Booking::query()
            ->with(['space:id,name', 'customer:id,name'])
            ->holdingSlot()
            ->when($this->spaceId, fn ($query) => $query->where('space_id', $this->spaceId))
            ->where('starts_at', '<', $start->addWeek())
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get();

        return collect(range(0, 6))
            ->map(fn (int $offset) => [
                'date' => $day = $start->addDays($offset),
                'bookings' => $bookings->filter(fn (Booking $booking) => $booking->starts_at->isSameDay($day))->values(),
            ])
            ->all();
    }
}
