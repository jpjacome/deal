<?php

namespace App\Filament\Pages;

use App\Services\BookingReports;
use App\Support\CsvExport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Occupancy, no-shows, cancellations, busy hours and top customers, plus CSV exports.
 */
class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reportes';

    #[Url(as: 'dias')]
    public int $days = 30;

    #[Computed]
    public function report(): BookingReports
    {
        return BookingReports::lastDays(in_array($this->days, [7, 30, 90, 365], true) ? $this->days : 30);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportBookings')
                ->label('Exportar reservas')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([
                    DatePicker::make('from')->label('Desde')->default(today()->subDays(30))->required(),
                    DatePicker::make('until')->label('Hasta')->default(today()->addDays(30))->required()->afterOrEqual('from'),
                ])
                ->action(fn (array $data) => CsvExport::bookings(CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['until']))),
            Action::make('exportCustomers')
                ->label('Exportar clientes')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => CsvExport::customers()),
        ];
    }
}
