<x-filament-panels::page>
    @php
        $report = $this->report;
        $totals = $report->totals();
        $heatmap = $report->heatmap();
        $weekdays = \App\Models\OpeningHour::WEEKDAYS;
        $stat = 'padding: 1rem; border-radius: 1.25rem; border: 1px solid color-mix(in srgb, currentColor 15%, transparent);';
    @endphp

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem;">
        <span style="opacity: .7;">{{ $report->from->translatedFormat('j M Y') }} – {{ $report->to->translatedFormat('j M Y') }}</span>
        <x-filament::tabs>
            @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'] as $value => $label)
                <x-filament::tabs.item :active="$days === $value" wire:click="$set('days', {{ $value }})">{{ $label }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .75rem;">
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Reservas</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $totals['total'] }}</div><div style="font-size: .8rem; opacity: .7;">{{ $totals['web'] }} web · {{ $totals['admin'] }} panel</div></div>
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Completadas</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $totals['completed'] }}</div><div style="font-size: .8rem; opacity: .7;">{{ $totals['upcoming'] }} por venir</div></div>
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Inasistencias</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $report->noShowRate() !== null ? $report->noShowRate().'%' : '—' }}</div><div style="font-size: .8rem; opacity: .7;">{{ $totals['no_show'] }} reservas</div></div>
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Cancelaciones</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $report->cancellationRate() !== null ? $report->cancellationRate().'%' : '—' }}</div><div style="font-size: .8rem; opacity: .7;">{{ $totals['cancelled_by_customer'] }} por el cliente</div></div>
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Sin respuesta / rechazadas</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $totals['expired'] + $totals['rejected'] }}</div><div style="font-size: .8rem; opacity: .7;">{{ $totals['expired'] }} expiradas</div></div>
        <div style="{{ $stat }}"><div style="opacity: .7; font-size: .85rem;">Anticipación media</div><div style="font-size: 1.8rem; font-weight: 700;">{{ $report->averageLeadHours() !== null ? ($report->averageLeadHours() >= 48 ? round($report->averageLeadHours() / 24, 1).' d' : $report->averageLeadHours().' h') : '—' }}</div><div style="font-size: .8rem; opacity: .7;">reservas web</div></div>
    </div>

    <x-filament::section heading="Ocupación por espacio" description="Tiempo reservado sobre el horario de atención (puestos compartidos: sobre la capacidad).">
        <div style="display: flex; flex-direction: column; gap: .75rem;">
            @foreach ($report->occupancy() as $row)
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: .9rem;">
                        <span>{{ $row['space']->name }}</span>
                        <span><strong>{{ $row['percent'] }}%</strong> · {{ $row['booked_hours'] }} h</span>
                    </div>
                    <div style="height: .6rem; border-radius: 1rem; background: color-mix(in srgb, currentColor 12%, transparent); overflow: hidden;">
                        <div class="deal-bar" style="width: {{ $row['percent'] }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Horas más pedidas" description="Reservas por día y hora de inicio.">
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

    <x-filament::section heading="Clientes más frecuentes">
        @forelse ($report->topCustomers() as $customer)
            <div style="display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid color-mix(in srgb, currentColor 12%, transparent);">
                <span>{{ $customer['name'] }}@if ($customer['company']) <span style="opacity: .6;">· {{ $customer['company'] }}</span>@endif</span>
                <span>{{ $customer['visits'] }} visitas @if ($customer['no_shows']) · <span class="deal-warning-text">{{ $customer['no_shows'] }} inasist.</span>@endif</span>
            </div>
        @empty
            <p style="opacity: .6;">Todavía no hay datos en este periodo.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
