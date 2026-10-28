<form wire:submit="book" class="booking" novalidate>
    {{-- 1. Space --}}
    <fieldset class="step">
        <legend><span class="step-number">1</span> Elige tu espacio</legend>

        <div class="space-grid" role="radiogroup" aria-label="Espacio">
            @forelse ($this->spaces as $option)
                <label @class(['space-card', 'is-selected' => $spaceSlug === $option->slug]) wire:key="space-{{ $option->id }}">
                    <input type="radio" wire:model.live="spaceSlug" value="{{ $option->slug }}">
                    @if ($option->photoUrl())
                        <img src="{{ $option->photoUrl() }}" alt="" loading="lazy">
                    @endif
                    <span class="space-card-body">
                        <strong>{{ $option->name }}</strong>
                        <span class="muted">{{ $option->booking_mode->getLabel() }} · hasta {{ $option->capacity }} {{ $option->capacity === 1 ? 'persona' : 'personas' }}</span>
                        @if ($option->price !== null)
                            <span>${{ rtrim(rtrim(number_format((float) $option->price, 2), '0'), '.') }} {{ $option->priceUnitLabel() }}</span>
                        @elseif ($option->price_label)
                            <span>{{ $option->price_label }}</span>
                        @endif
                        @if ($option->amenities)
                            <span class="amenities">
                                @foreach (array_slice($option->amenities, 0, 4) as $amenity)
                                    <span>{{ $amenity }}</span>
                                @endforeach
                            </span>
                        @endif
                    </span>
                </label>
            @empty
                <p>No hay espacios disponibles para reservar en línea por ahora.</p>
            @endforelse
        </div>

        @if ($this->space?->description)
            <p class="muted space-description">{{ $this->space->description }}</p>
        @endif
    </fieldset>

    @if ($this->space)
        @include('livewire.partials.schedule-picker', ['stepOffset' => 1, 'allowWaitlist' => true])

        {{-- 4. Contact details --}}
        @if ($this->selection)
            <fieldset class="step" x-data="bookingDetails">
                <legend><span class="step-number">4</span> Tus datos</legend>

                <div class="summary">
                    <strong>{{ $this->space->name }}</strong><br>
                    {{ ucfirst($this->selection[0]->translatedFormat('l j \d\e F')) }},
                    {{ $this->selection[0]->format('H:i') }} – {{ $this->selection[1]->format('H:i') }}
                    @if ($attendees > 1) · {{ $attendees }} personas @endif
                    @if ($this->estimatedPrice !== null)
                        <br>Total estimado: <strong>${{ number_format($this->estimatedPrice, 2) }}</strong> <span class="muted">· se paga en el local</span>
                    @endif
                </div>

                <div class="field-row">
                    <label class="field">
                        <span>Nombre y apellido</span>
                        <input type="text" wire:model="name" autocomplete="name" required>
                        @error('name') <small class="error">{{ $message }}</small> @enderror
                    </label>
                    <label class="field">
                        <span>Correo electrónico</span>
                        <input type="email" wire:model="email" autocomplete="email" required x-on:blur="checkEmail($event.target.value)">
                        <small x-show="suggestion" x-cloak>
                            ¿Quisiste decir <button type="button" class="link-button" x-on:click="applySuggestion()" x-text="suggestion"></button>?
                        </small>
                        @error('email') <small class="error">{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="field-row">
                    <div class="field">
                        <span>Celular (WhatsApp)</span>
                        <div class="phone-input">
                            <select wire:model="countryCode" aria-label="Código de país" autocomplete="tel-country-code">
                                @foreach ($countries as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="tel" wire:model="phone" autocomplete="tel-national" placeholder="099 123 4567" required aria-label="Número de celular">
                        </div>
                        @error('phone') <small class="error">{{ $message }}</small> @enderror
                    </div>
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
                    <span>Quiero recibir la confirmación y recordatorios por WhatsApp. <span class="muted">(Opcional)</span></span>
                </label>
                <label class="check">
                    <input type="checkbox" wire:model="privacy">
                    <span>Acepto la <a href="{{ route('privacy') }}" target="_blank">política de privacidad</a> y las <a href="{{ route('terms') }}" target="_blank">condiciones de reserva</a>.</span>
                </label>
                <label class="check">
                    <input type="checkbox" x-model="remember">
                    <span class="muted">Recordar mis datos en este dispositivo.</span>
                </label>
                @error('privacy') <p class="error">{{ $message }}</p> @enderror
                @error('form') <p class="error">{{ $message }}</p> @enderror

                <ul class="policy muted">
                    @if ($this->space->requires_approval)
                        <li>Tu reserva quedará pendiente hasta que la confirmemos; te avisaremos por correo.</li>
                    @endif
                    @if ($this->space->cancellation_hours > 0)
                        <li>Puedes cambiarla o cancelarla en línea hasta {{ $this->space->cancellation_hours }} horas antes.</li>
                    @endif
                </ul>

                <button type="submit" wire:loading.attr="disabled" x-on:click="save()">
                    <span wire:loading.remove wire:target="book">Confirmar reserva</span>
                    <span wire:loading wire:target="book">Enviando…</span>
                </button>
            </fieldset>
        @endif
    @endif
</form>
