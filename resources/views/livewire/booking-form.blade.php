@php
    $hours = fn (int $minutes) => intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
@endphp

<form wire:submit="book" class="booking" novalidate>
    {{-- 1. Space --}}
    <fieldset class="step">
        <legend><span class="step-number">1</span> Elige tu espacio</legend>

        <div class="choice-grid">
            @forelse ($this->spaces as $option)
                <label @class(['choice', 'is-selected' => $spaceSlug === $option->slug]) wire:key="space-{{ $option->id }}">
                    <input type="radio" wire:model.live="spaceSlug" value="{{ $option->slug }}">
                    <strong>{{ $option->name }}</strong>
                    <span class="muted">{{ $option->booking_mode->getLabel() }} · hasta {{ $option->capacity }} {{ $option->capacity === 1 ? 'persona' : 'personas' }}</span>
                    @if ($option->price_label)
                        <span>{{ $option->price_label }}</span>
                    @endif
                </label>
            @empty
                <p>No hay espacios disponibles para reservar en línea por ahora.</p>
            @endforelse
        </div>
    </fieldset>

    @if ($this->space)
        {{-- 2. Date --}}
        <fieldset class="step">
            <legend><span class="step-number">2</span> Elige la fecha</legend>

            <div class="field-row">
                <label class="field">
                    <span>Fecha</span>
                    <input type="date" wire:model.live="date" min="{{ today()->toDateString() }}" max="{{ $this->maxDate()->toDateString() }}">
                </label>

                @if ($this->space->capacity > 1)
                    <label class="field">
                        <span>Personas</span>
                        <input type="number" wire:model.live.debounce.400ms="attendees" min="1" max="{{ $this->space->capacity }}">
                    </label>
                @endif
            </div>
            @error('date') <p class="error">{{ $message }}</p> @enderror
            @error('attendees') <p class="error">{{ $message }}</p> @enderror
        </fieldset>

        {{-- 3. Time --}}
        @if ($this->day)
            <fieldset class="step">
                <legend><span class="step-number">3</span> Elige el horario</legend>

                @if ($this->space->booking_mode->isFullDay())
                    @if ($this->dayProblem)
                        <p class="notice">{{ $this->dayProblem }}</p>
                    @else
                        <p>Día completo: <strong>{{ $this->selection[0]->format('H:i') }} – {{ $this->selection[1]->format('H:i') }}</strong>. Hay disponibilidad.</p>
                    @endif
                @else
                    @if (count($this->durations) > 1)
                        <label class="field">
                            <span>Duración</span>
                            <select wire:model.live="duration">
                                @foreach ($this->durations as $minutes)
                                    <option value="{{ $minutes }}">{{ $hours($minutes) }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    @if ($this->startTimes)
                        <p class="muted">Hora de inicio</p>
                        <div class="chips">
                            @foreach ($this->startTimes as $start)
                                <label @class(['chip', 'is-selected' => $time === $start->format('H:i')]) wire:key="time-{{ $start->format('Hi') }}">
                                    <input type="radio" wire:model.live="time" value="{{ $start->format('H:i') }}">
                                    {{ $start->format('H:i') }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="notice">No hay horarios disponibles ese día. Prueba con otra fecha o duración.</p>
                    @endif
                @endif
                @error('time') <p class="error">{{ $message }}</p> @enderror
            </fieldset>
        @endif

        {{-- 4. Contact details --}}
        @if ($this->selection)
            <fieldset class="step">
                <legend><span class="step-number">4</span> Tus datos</legend>

                <div class="summary">
                    <strong>{{ $this->space->name }}</strong><br>
                    {{ ucfirst($this->selection[0]->translatedFormat('l j \d\e F')) }},
                    {{ $this->selection[0]->format('H:i') }} – {{ $this->selection[1]->format('H:i') }}
                    @if ($attendees > 1) · {{ $attendees }} personas @endif
                </div>

                <div class="field-row">
                    <label class="field">
                        <span>Nombre y apellido</span>
                        <input type="text" wire:model="name" autocomplete="name" required>
                        @error('name') <small class="error">{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Correo electrónico</span>
                        <input type="email" wire:model="email" autocomplete="email" required>
                        @error('email') <small class="error">{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="field-row">
                    <label class="field">
                        <span>Celular (WhatsApp)</span>
                        <input type="tel" wire:model="phone" autocomplete="tel" placeholder="099 123 4567" required>
                        @error('phone') <small class="error">{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Empresa <span class="muted">(opcional)</span></span>
                        <input type="text" wire:model="company" autocomplete="organization">
                    </label>
                </div>

                <label class="field">
                    <span>Comentarios <span class="muted">(opcional)</span></span>
                    <textarea wire:model="notes" rows="3"></textarea>
                </label>

                <div class="hp" aria-hidden="true">
                    <label>Sitio web <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <label class="check">
                    <input type="checkbox" wire:model="whatsapp">
                    <span>Quiero recibir la confirmación y recordatorios por WhatsApp.</span>
                </label>
                <label class="check">
                    <input type="checkbox" wire:model="privacy">
                    <span>Acepto la <a href="#" target="_blank">política de privacidad</a> y las condiciones de reserva.</span>
                </label>
                @error('privacy') <p class="error">{{ $message }}</p> @enderror
                @error('form') <p class="error">{{ $message }}</p> @enderror

                @if ($this->space->requires_approval)
                    <p class="muted">Tu reserva quedará pendiente hasta que la confirmemos. Te avisaremos por correo.</p>
                @endif

                <button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="book">Confirmar reserva</span>
                    <span wire:loading wire:target="book">Enviando…</span>
                </button>
            </fieldset>
        @endif
    @endif
</form>
