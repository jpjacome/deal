<x-filament-panels::page>
    @php
        $report = $this->report;
        $totals = $report->totals();
        $heatmap = $report->heatmap();
        $weekdays = \App\Models\OpeningHour::WEEKDAYS;
        $upcomingDays = \App\Services\BookingReports::UPCOMING_DAYS;
        $stat = 'padding: 1rem; border-radius: 1.25rem; border: 1px solid color-mix(in srgb, currentColor 15%, transparent);';
        $label = 'opacity: .7; font-size: .85rem;';
        $value = 'font-size: 1.8rem; font-weight: 700;';
        $note = 'font-size: .8rem; opacity: .7;';
        $lead = $report->averageLeadHours();
    @endphp

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem;">
        <span style="opacity: .7;">Periodo: {{ $report->from->translatedFormat('j M Y') }} – {{ $report->to->translatedFormat('j M Y') }}</span>
        <x-filament::tabs>
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'] as $option => $text)
                <x-filament::tabs.item :active="$days === $option" wire:click="$set('days', {{ $option }})">{{ $text }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr)); gap: .75rem;">
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Reservas recibidas</div>
            <div style="{{ $value }}">{{ $totals['received'] }}</div>
            <div style="{{ $note }}">hechas en el periodo · {{ $totals['web'] }} web, {{ $totals['admin'] }} panel</div>
        </div>
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Por venir</div>
            <div style="{{ $value }}">{{ $totals['upcoming'] }}</div>
            <div style="{{ $note }}">próximos {{ $upcomingDays }} días · {{ $totals['upcoming_pending'] }} por confirmar</div>
        </div>
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Visitas completadas</div>
            <div style="{{ $value }}">{{ $totals['completed'] }}</div>
            <div style="{{ $note }}">en el periodo</div>
        </div>
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Inasistencias</div>
            <div style="{{ $value }}">{{ $report->noShowRate() !== null ? $report->noShowRate().'%' : '—' }}</div>
            <div style="{{ $note }}">{{ $totals['no_show'] }} de las visitas del periodo</div>
        </div>
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Cancelaciones</div>
            <div style="{{ $value }}">{{ $report->cancellationRate() !== null ? $report->cancellationRate().'%' : '—' }}</div>
            <div style="{{ $note }}">{{ $totals['cancelled_by_customer'] }} por el cliente · {{ $totals['expired'] }} expiradas, {{ $totals['rejected'] }} rechazadas</div>
        </div>
        <div style="{{ $stat }}">
            <div style="{{ $label }}">Anticipación media</div>
            <div style="{{ $value }}">{{ $lead !== null ? ($lead >= 48 ? round($lead / 24, 1).' d' : $lead.' h') : '—' }}</div>
            <div style="{{ $note }}">entre la reserva web y la visita</div>
        </div>
    </div>

    <x-filament::section heading="Ocupación por espacio" description="Tiempo reservado sobre el horario de atención (puestos compartidos: sobre la capacidad). A la izquierda lo que ya pasó en el periodo; a la derecha lo reservado para los próximos {{ $upcomingDays }} días.">
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @foreach ($report->occupancy() as $row)
                <div>
                    <div style="font-size: .9rem; font-weight: 600; margin-bottom: .35rem;">{{ $row['space']->name }}</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .5rem 1.5rem;">
                        @foreach ([['Periodo', $row['past_percent'], $row['past_hours']], ["Próximos {$upcomingDays} días", $row['upcoming_percent'], $row['upcoming_hours']]] as [$title, $percent, $hours])
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: .8rem; opacity: .85;">
                                    <span>{{ $title }}</span>
                                    <span><strong>{{ $percent }}%</strong> · {{ $hours }} h</span>
                                </div>
                                <div style="height: .6rem; border-radius: 1rem; background: color-mix(in srgb, currentColor 12%, transparent); overflow: hidden;">
                                    <div class="deal-bar" style="width: {{ $percent }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Horas más pedidas" description="Día y hora de inicio de las reservas recibidas en el periodo.">
        <div style="overflow-x: auto;">
            <table style="border-collapse: separate; border-spacing: 3px; font-size: .75rem;">
                <tr>
                    <th></th>
                    @foreach ($heatmap['hours'] as $hour)
                        <th style="font-weight: 400; opacity: .6; padding: 0 .2rem;">{{ $hour }}h</th>
                    @endforeach
                </tr>
                @foreach ($heatmap['rows'] as $weekday => $cells)
                    <tr>
                        <th style="text-align: right; font-weight: 400; padding-right: .4rem;">{{ mb_substr($weekdays[$weekday], 0, 3) }}</th>
                        @foreach ($cells as $count)
                            <td title="{{ $count }} reservas" style="width: 2rem; height: 1.6rem; text-align: center; border-radius: .3rem; background: color-mix(in srgb, var(--accent) {{ $count ? round(12 + 88 * $count / $heatmap['max']) : 5 }}%, transparent); color: {{ $count / $heatmap['max'] > .55 ? 'var(--white)' : 'inherit' }};">
                                {{ $count ?: '' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Clientes más frecuentes" description="Por reservas recibidas en el periodo.">
        @forelse ($report->topCustomers() as $customer)
            <div style="display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid color-mix(in srgb, currentColor 12%, transparent);">
                <span>{{ $customer['name'] }}@if ($customer['company']) <span style="opacity: .6;">· {{ $customer['company'] }}</span>@endif</span>
                <span>{{ $customer['bookings'] }} {{ $customer['bookings'] === 1 ? 'reserva' : 'reservas' }} @if ($customer['no_shows']) · <span class="deal-warning-text">{{ $customer['no_shows'] }} inasist.</span>@endif</span>
            </div>
        @empty
            <p style="opacity: .6;">Todavía no hay reservas en este periodo.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
