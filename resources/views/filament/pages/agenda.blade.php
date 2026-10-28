<x-filament-panels::page>
    @php
        $start = $this->weekStart();
        $end = $start->addDays(6);
    @endphp

    <div style="display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: .5rem; align-items: center;">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="previousWeek" label="Semana anterior" />
            <x-filament::button color="gray" size="sm" wire:click="currentWeek">Hoy</x-filament::button>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="nextWeek" label="Semana siguiente" />
            <strong style="margin-left: .5rem;">
                {{ $start->translatedFormat('j M') }} – {{ $end->translatedFormat('j M Y') }}
            </strong>
        </div>

        <x-filament::input.wrapper style="min-width: 14rem;">
            <x-filament::input.select wire:model.live="spaceId">
                <option value="">Todos los espacios</option>
                @foreach ($this->spaces as $space)
                    <option value="{{ $space->id }}">{{ $space->name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .75rem;">
        @foreach ($this->days as $day)
            <x-filament::section compact wire:key="day-{{ $day['date']->toDateString() }}">
                <x-slot name="heading">
                    <span @style(['font-weight: 700' => $day['date']->isToday()])>
                        {{ ucfirst($day['date']->translatedFormat('l j')) }}
                    </span>
                </x-slot>

                <div style="display: flex; flex-direction: column; gap: .5rem;">
                    @forelse ($day['bookings'] as $booking)
                        <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking]) }}"
                           style="display: block; padding: .5rem; border-radius: .5rem; border: 1px solid rgba(127,127,127,.25); text-decoration: none;">
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
</x-filament-panels::page>
