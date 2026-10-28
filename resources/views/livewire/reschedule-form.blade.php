<form wire:submit="save" class="booking" novalidate>
    @include('livewire.partials.schedule-picker', ['stepOffset' => 0, 'allowWaitlist' => false, 'showAttendees' => false])

    @if ($this->selection)
        <div class="summary">
            Nuevo horario: <strong>{{ ucfirst($this->selection[0]->translatedFormat('l j \d\e F')) }}, {{ $this->selection[0]->format('H:i') }} – {{ $this->selection[1]->format('H:i') }}</strong>
            @if ($this->scheduleSpace()->requires_approval)
                <br><span class="muted">El cambio queda pendiente hasta que lo confirmemos.</span>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Confirmar cambio</span>
            <span wire:loading wire:target="save">Guardando…</span>
        </button>
    @endif
</form>
