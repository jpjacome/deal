<x-filament-panels::page>
    @php
        $current = $this->current();
        $bookingUrl = fn ($booking) => \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking]);
    @endphp

    <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="previous" label="Anterior" />
            <x-filament::button color="gray" size="sm" wire:click="today">Hoy</x-filament::button>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="next" label="Siguiente" />
            <strong style="margin-left: .5rem;">
                @if ($mode === 'semana')
                    {{ $this->weekStart()->translatedFormat('j M') }} – {{ $this->weekStart()->addDays(6)->translatedFormat('j M Y') }}
                @else
                    {{ ucfirst($current->translatedFormat('l j \d\e F Y')) }}
                @endif
            </strong>
        </div>

        <div style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
            <x-filament::tabs>
                <x-filament::tabs.item :active="$mode === 'dia'" wire:click="$set('mode', 'dia')">Día</x-filament::tabs.item>
                <x-filament::tabs.item :active="$mode === 'semana'" wire:click="$set('mode', 'semana')">Semana</x-filament::tabs.item>
            </x-filament::tabs>

            <x-filament::input.wrapper style="min-width: 13rem;">
                <x-filament::input.select wire:model.live="spaceId">
                    <option value="">Todos los espacios</option>
                    @foreach ($this->spaces as $space)
                        <option value="{{ $space->id }}">{{ $space->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if ($mode === 'dia')
        @php
            $timeline = $this->timeline;
            $hourHeight = \App\Filament\Pages\Agenda::HOUR_HEIGHT;
            $height = ($timeline['to'] - $timeline['from']) * $hourHeight;
        @endphp
        <x-filament::section>
            <div style="overflow-x: auto;">
                <div style="display: grid; grid-template-columns: 3.5rem repeat({{ max(1, $timeline['columns']->count()) }}, minmax(9rem, 1fr)); min-width: {{ 3.5 + 9 * $timeline['columns']->count() }}rem;">
                    <div></div>
                    @foreach ($timeline['columns'] as $column)
                        <div style="padding: .25rem .5rem .5rem; font-weight: 600; text-align: center;">{{ $column['space']->name }}</div>
                    @endforeach

                    {{-- Hour labels --}}
                    <div style="position: relative; height: {{ $height }}px;">
                        @for ($h = $timeline['from']; $h < $timeline['to']; $h++)
                            <span style="position: absolute; top: {{ ($h - $timeline['from']) * $hourHeight - 7 }}px; right: .5rem; font-size: .75rem; opacity: .6;">{{ sprintf('%02d:00', $h) }}</span>
                        @endfor
                    </div>

                    @foreach ($timeline['columns'] as $column)
                        <div style="position: relative; height: {{ $height }}px; border-left: 1px solid color-mix(in srgb, currentColor 15%, transparent);">
                            {{-- Empty hours: click to book --}}
                            @for ($h = $timeline['from']; $h < $timeline['to']; $h++)
                                <a href="{{ route('filament.admin.resources.bookings.create', ['space' => $column['space']->id, 'date' => $current->toDateString(), 'start' => sprintf('%02d:00', $h)]) }}"
                                   title="Reservar {{ $column['space']->name }} a las {{ sprintf('%02d:00', $h) }}"
                                   style="position: absolute; left: 0; right: 0; top: {{ ($h - $timeline['from']) * $hourHeight }}px; height: {{ $hourHeight }}px; border-top: 1px solid color-mix(in srgb, currentColor 10%, transparent);"></a>
                            @endfor

                            @foreach ($column['bookings'] as $booking)
                                @php
                                    $top = max(0, ($booking->starts_at->hour * 60 + $booking->starts_at->minute - $timeline['from'] * 60) / 60 * $hourHeight);
                                    $blockHeight = max(22, $booking->starts_at->diffInMinutes($booking->ends_at) / 60 * $hourHeight - 2);
                                @endphp
                                <a href="{{ $bookingUrl($booking) }}"
                                   class="deal-slot deal-slot-{{ $booking->status->value }}"
                                   style="top: {{ $top }}px; height: {{ $blockHeight }}px;">
                                    <strong>{{ $booking->starts_at->format('H:i') }}–{{ $booking->ends_at->format('H:i') }}</strong>
                                    @if ($booking->status->value === 'pending') · <em>Pendiente</em> @endif
                                    <br>{{ $booking->customer->name }}@if ($booking->attendees > 1) · {{ $booking->attendees }} pers.@endif
                                    @if ($booking->checked_in_at) · ✓ llegó @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <p style="margin-top: .75rem; font-size: .8rem; opacity: .65;">Haz clic en una hora libre para crear una reserva en ese espacio.</p>
        </x-filament::section>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .75rem;">
            @foreach ($this->weekDays as $day)
                <x-filament::section compact wire:key="day-{{ $day['date']->toDateString() }}">
                    <x-slot name="heading">
                        <button type="button" wire:click="showDay('{{ $day['date']->toDateString() }}')" @style(['font-weight: 700' => $day['date']->isToday()])>
                            {{ ucfirst($day['date']->translatedFormat('l j')) }}
                        </button>
                    </x-slot>

                    <div style="display: flex; flex-direction: column; gap: .5rem;">
                        @forelse ($day['bookings'] as $booking)
                            <a href="{{ $bookingUrl($booking) }}"
                               style="display: block; padding: .5rem; border-radius: .5rem; border: 1px solid color-mix(in srgb, currentColor 18%, transparent); text-decoration: none;">
                                <div style="display: flex; justify-content: space-between; gap: .25rem; align-items: center;">
                                    <strong>{{ $booking->starts_at->format('H:i') }}–{{ $booking->ends_at->format('H:i') }}</strong>
                                    <x-filament::badge :color="$booking->status->getColor()" size="sm">
                                        {{ $booking->status->getLabel() }}
                                    </x-filament::badge>
                                </div>
                                <div>{{ $booking->space->name }}</div>
                                <div style="opacity: .7; font-size: .875em;">
                                    {{ $booking->customer->name }}@if ($booking->attendees > 1) · {{ $booking->attendees }} pers.@endif
                                </div>
                            </a>
                        @empty
                            <span style="opacity: .5; font-size: .875em;">Sin reservas</span>
                        @endforelse
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
