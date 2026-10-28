@php($settings = \App\Models\Setting::values())
<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
<x-mail::panel>
**{{ $booking->space->name }}**<br>
{{ $booking->scheduleLabel() }}<br>
@if ($booking->attendees > 1)
{{ $booking->attendees }} personas<br>
@endif
Código de reserva: **{{ $booking->code }}**
</x-mail::panel>

@if ($showsLocation)
**Dirección:** {{ $settings['venue_address'] }}@if ($settings['maps_url']) · [Cómo llegar]({{ $settings['maps_url'] }})@endif


@if ($settings['arrival_info'])
{{ $settings['arrival_info'] }}

@endif
@endif
@if ($action)
<x-mail::button :url="$action[1]">
{{ $action[0] }}
</x-mail::button>
@endif

@if ($settings['whatsapp_number'])
¿Dudas? Escríbenos por [WhatsApp](https://wa.me/{{ \App\Models\Setting::whatsappDigits() }}?text={{ rawurlencode("Hola, tengo la reserva {$booking->code}") }}).
@endif
</x-mail::message>
