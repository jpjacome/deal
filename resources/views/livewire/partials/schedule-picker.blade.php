@php
    use App\Services\Availability;

    $space = $this->scheduleSpace();
    $hours = fn (int $minutes) => intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
    $statusLabels = [
        Availability::DAY_AVAILABLE => 'Disponible',
        Availability::DAY_FEW => 'Pocos lugares',
        Availability::DAY_FULL => 'Lleno',
        Availability::DAY_CLOSED => 'Cerrado',
    ];
@endphp

{{-- Day --}}
<fieldset class="step tile">
    <legend><span class="step-number bubble">{{ $stepOffset + 1 }}</span> Elige el día</legend>

    @if ($space->booking_mode === App\Enums\BookingMode::Hourly && count($this->durations) > 1)
        <p class="muted">¿Cuánto tiempo?</p>
        <div class="chips" role="radiogroup" aria-label="Duración">
            @foreach ($this->durations as $minutes)
                <label @class(['chip', 'is-selected' => (int) $duration === $minutes]) wire:key="duration-{{ $minutes }}">
                    <input type="radio" wire:model.live="duration" value="{{ $minutes }}">
                    {{ $hours($minutes) }}
                </label>
            @endforeach
        </div>
    @endif

    @if (($showAttendees ?? true) && $space->capacity > 1)
        <label class="field field-inline">
            <span>Personas</span>
            <input type="number" wire:model.live.debounce.400ms="attendees" min="1" max="{{ $space->capacity }}">
        </label>
    @endif

    <div class="day-strip" role="radiogroup" aria-label="Día">
        @foreach ($this->dayStatuses as $iso => $status)
            @php($stripDay = \Carbon\CarbonImmutable::parse($iso))
            <button type="button"
                    wire:key="day-{{ $iso }}"
                    wire:click="pickDay('{{ $iso }}')"
                    @class(['day', "day-{$status}", 'is-selected' => $date === $iso])
                    @disabled($status === Availability::DAY_CLOSED)
                    aria-pressed="{{ $date === $iso ? 'true' : 'false' }}"
                    title="{{ $statusLabels[$status] }}">
                <span class="day-name">{{ $stripDay->isToday() ? 'Hoy' : $stripDay->translatedFormat('D') }}</span>
                <span class="day-number">{{ $stripDay->format('j') }}</span>
                <span class="day-status">{{ $statusLabels[$status] }}</span>
            </button>
        @endforeach
    </div>

    <div class="field-row day-extra">
        @if (! $date && $this->nextAvailableDay)
            <button type="button" class="btn-secondary" wire:click="pickDay('{{ $this->nextAvailableDay }}')">
                Próximo disponible: {{ \Carbon\CarbonImmutable::parse($this->nextAvailableDay)->translatedFormat('l j') }}
            </button>
        @endif
        <label class="field field-inline">
            <span class="muted">Otra fecha</span>
            <input type="date" wire:model.live="date" min="{{ today()->toDateString() }}" max="{{ $this->maxDate()->toDateString() }}">
        </label>
    </div>
    @error('date') <p class="error">{{ $message }}</p> @enderror
    @error('attendees') <p class="error">{{ $message }}</p> @enderror
</fieldset>

{{-- Time --}}
@if ($this->day)
    <fieldset class="step tile">
        <legend><span class="step-number bubble">{{ $stepOffset + 2 }}</span> Elige el horario</legend>

        @if ($space->booking_mode->isFullDay())
            @if ($this->dayOptions === [])
                <p class="notice">Ese día estamos cerrados.</p>
            @else
                <div class="chips" role="radiogroup" aria-label="Horario">
                    @foreach ($this->dayOptions as $key => $option)
                        <label @class(['chip', 'is-selected' => $period === $key && ! $option['reason'], 'is-disabled' => $option['reason']])
                               wire:key="period-{{ $key }}" title="{{ $option['reason'] }}">
                            <input type="radio" wire:model.live="period" value="{{ $key }}" @disabled($option['reason'])>
                            {{ $option['label'] }} · {{ $option['range'][0]->format('H:i') }}–{{ $option['range'][1]->format('H:i') }}
                        </label>
                    @endforeach
                </div>
                @if ($reason = $this->dayOptions[$period]['reason'] ?? null)
                    <p class="notice">{{ $reason }}</p>
                @endif
            @endif
        @else
            @if ($this->timeSlots === [])
                <p class="notice">No quedan horarios ese día.</p>
            @else
                <div class="chips" role="radiogroup" aria-label="Hora de inicio">
                    @foreach ($this->timeSlots as $slot)
                        @php($value = $slot['start']->format('H:i'))
                        <label @class(['chip', 'is-selected' => $time === $value, 'is-disabled' => ! $slot['available']])
                               wire:key="time-{{ $slot['start']->format('Hi') }}"
                               title="{{ $slot['available'] ? 'Disponible' : $slot['reason'] }}">
                            <input type="radio" wire:model.live="time" value="{{ $value }}" @disabled(! $slot['available'])>
                            {{ $value }}
                        </label>
                    @endforeach
                </div>
                @unless ($this->hasFreeSlot)
                    <p class="notice">Ese día ya no hay horarios libres para esta duración.</p>
                @endunless
            @endif
        @endif
        @error('time') <p class="error">{{ $message }}</p> @enderror

        {{-- Nothing free: offer alternatives instead of a dead end --}}
        @if ($this->alternatives->isNotEmpty())
            <div class="alternatives">
                <p><strong>Disponible ese día en:</strong></p>
                <div class="chips">
                    @foreach ($this->alternatives as $alternative)
                        <button type="button" class="chip" wire:click="switchSpace('{{ $alternative->slug }}')" wire:key="alt-{{ $alternative->id }}">
                            {{ $alternative->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($allowWaitlist && ! $this->selection && ($this->dayOptions !== [] || $this->timeSlots !== []) && ! ($space->booking_mode === App\Enums\BookingMode::Hourly && $this->hasFreeSlot))
            <div class="waitlist">
                @if ($joinedWaitlist)
                    <p><strong>¡Listo!</strong> Te escribiremos si se libera un espacio ese día.</p>
                @else
                    <p><strong>¿Te avisamos si se libera?</strong> Si alguien cancela, te escribimos enseguida.</p>
                    <div class="field-row">
                        <label class="field"><span>Nombre</span><input type="text" wire:model="name" autocomplete="name"></label>
                        <label class="field"><span>Correo</span><input type="email" wire:model="email" autocomplete="email"></label>
                    </div>
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                    <button type="button" class="btn-secondary" wire:click="joinWaitlist">Avísame</button>
                @endif
            </div>
        @endif
    </fieldset>
@endif
