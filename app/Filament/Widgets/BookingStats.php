<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $pending = Booking::where('status', BookingStatus::Pending)->count();

        $today = Booking::holdingSlot()->whereDate('starts_at', today())->count();

        $week = Booking::holdingSlot()
            ->whereBetween('starts_at', [today(), today()->addDays(7)])
            ->count();

        $month = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed])
            ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return [
            Stat::make('Pendientes de confirmar', $pending)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->description($pending > 0 ? 'Revisar ahora' : 'Todo al día')
                ->url(BookingResource::getUrl('index', ['tab' => 'pending'])),
            Stat::make('Reservas hoy', $today)
                ->url(BookingResource::getUrl('index', ['tab' => 'today'])),
            Stat::make('Próximos 7 días', $week),
            Stat::make('Visitas este mes', $month)
                ->description('Confirmadas y completadas')
                ->url(Reports::getUrl()),
        ];
    }
}
