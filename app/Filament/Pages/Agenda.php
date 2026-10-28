<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\OpeningHour;
use App\Models\Space;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Day timeline (spaces × hours, click a free hour to book) and week view of active bookings.
 */
class Agenda extends Page
{
    protected string $view = 'filament.pages.agenda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Agenda';

    /** Pixel height of one hour in the day timeline. */
    public const HOUR_HEIGHT = 56;

    #[Url(as: 'vista')]
    public string $mode = 'dia';

    /** Reference date (Y-m-d): the day shown, or any day of the week shown. */
    #[Url(as: 'fecha')]
    public ?string $date = null;

    #[Url(as: 'espacio')]
    public ?string $spaceId = null;

    public function current(): CarbonImmutable
    {
        try {
            $date = $this->date ? CarbonImmutable::createFromFormat('!Y-m-d', $this->date) : null;
        } catch (Throwable) {
            $date = null;
        }

        return $date ?: today()->toImmutable();
    }

    public function weekStart(): CarbonImmutable
    {
        return $this->current()->startOfWeek();
    }

    public function previous(): void
    {
        $this->date = ($this->mode === 'semana' ? $this->current()->subWeek() : $this->current()->subDay())->toDateString();
    }

    public function next(): void
    {
        $this->date = ($this->mode === 'semana' ? $this->current()->addWeek() : $this->current()->addDay())->toDateString();
    }

    public function today(): void
    {
        $this->date = null;
    }

    public function showDay(string $date): void
    {
        $this->mode = 'dia';
        $this->date = $date;
    }

    /**
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces(): Collection
    {
        return Space::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name', 'slug']);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function bookingsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Booking::query()
            ->with(['space:id,name', 'customer:id,name,phone'])
            ->holdingSlot()
            ->when($this->spaceId, fn ($query) => $query->where('space_id', $this->spaceId))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return list<array{date: CarbonImmutable, bookings: Collection<int, Booking>}>
     */
    #[Computed]
    public function weekDays(): array
    {
        $start = $this->weekStart();
        $bookings = $this->bookingsBetween($start, $start->addWeek());

        return collect(range(0, 6))
            ->map(fn (int $offset) => [
                'date' => $day = $start->addDays($offset),
                'bookings' => $bookings->filter(fn (Booking $booking) => $booking->starts_at->isSameDay($day))->values(),
            ])
            ->all();
    }

    /**
     * Day timeline: hour range to draw and bookings grouped by space.
     *
     * @return array{from: int, to: int, columns: Collection<int, array{space: Space, bookings: Collection<int, Booking>}>}
     */
    #[Computed]
    public function timeline(): array
    {
        $day = $this->current();
        $hours = OpeningHour::where('weekday', $day->dayOfWeekIso)->get();

        $from = $hours->isEmpty() ? 8 : (int) substr((string) $hours->min('opens_at'), 0, 2);
        $to = $hours->isEmpty() ? 19 : (int) ceil(((int) substr((string) $hours->max('closes_at'), 0, 2) * 60 + (int) substr((string) $hours->max('closes_at'), 3, 2)) / 60);

        $bookings = $this->bookingsBetween($day->startOfDay(), $day->endOfDay())->groupBy('space_id');

        $spaces = $this->spaceId ? $this->spaces->where('id', (int) $this->spaceId) : $this->spaces;

        return [
            'from' => $from,
            'to' => max($to, $from + 1),
            'columns' => $spaces->map(fn (Space $space) => [
                'space' => $space,
                'bookings' => $bookings->get($space->id, collect()),
            ])->values(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('subscribe')
                ->label('Ver en mi calendario')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->modalHeading('Suscribirte a las reservas')
                ->modalDescription('Copia este enlace privado y agrégalo en Google Calendar ("Otros calendarios" → "Desde URL") o en el Calendario del iPhone ("Agregar suscripción"). Se actualiza solo. No lo compartas.')
                ->schema([
                    TextInput::make('url')
                        ->label('Enlace del calendario')
                        ->default(fn (): string => auth()->user()->calendarFeedUrl())
                        ->readOnly()
                        ->copyable(copyMessage: 'Enlace copiado'),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),
            Action::make('new')
                ->label('Nueva reserva')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => route('filament.admin.resources.bookings.create', ['date' => $this->current()->toDateString()])),
        ];
    }
}
