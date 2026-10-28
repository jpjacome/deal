<x-mail::message>
# {{ $heading }}

{{ $intro }}

<x-mail::table>
| Horario | Espacio | Cliente | Estado |
|:--------|:--------|:--------|:-------|
@foreach ($bookings as $booking)
| [{{ $booking->starts_at->format('d/m H:i') }}–{{ $booking->ends_at->format('H:i') }}]({{ route('filament.admin.resources.bookings.view', $booking) }}) | {{ $booking->space->name }} | {{ $booking->customer->name }}@if ($booking->attendees > 1) ({{ $booking->attendees }})@endif | {{ $booking->status->getLabel() }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('filament.admin.pages.agenda')">
Abrir la agenda
</x-mail::button>
</x-mail::message>
