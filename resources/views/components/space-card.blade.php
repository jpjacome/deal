@props(['space', 'link' => null])

{{-- Space tile: photo (or the brand gradient with a cropped wordmark until there is one), name, details, price. --}}
<div class="space-card-art {{ $space->photoUrl() ? '' : 'gradient' }}">
    @if ($space->photoUrl())
        <img src="{{ $space->photoUrl() }}" alt="{{ $space->name }}" loading="lazy">
    @else
        <span class="supergraphic" aria-hidden="true"><x-logo /></span>
    @endif
</div>
<div class="space-card-body">
    <span class="eyebrow" style="margin: 0;">{{ $space->type_label ?: $space->booking_mode->getLabel() }}</span>
    <h3>{{ $space->name }}</h3>
    <span class="muted">Hasta {{ $space->capacity }} {{ $space->capacity === 1 ? 'persona' : 'personas' }} · {{ $space->booking_mode->getLabel() }}</span>
    @if ($space->amenities)
        <span class="pills">
            @foreach (array_slice($space->amenities, 0, 4) as $amenity)
                <span>{{ $amenity }}</span>
            @endforeach
        </span>
    @endif
    <span class="space-card-meta">
        @if ($space->price !== null)
            <span class="price">${{ rtrim(rtrim(number_format((float) $space->price, 2), '0'), '.') }} <small>{{ $space->priceUnitLabel() }}</small></span>
        @elseif ($space->price_label)
            <span class="price">{{ $space->price_label }}</span>
        @else
            <span></span>
        @endif
        @if ($link)
            <a href="{{ $link }}" class="btn">Reservar</a>
        @endif
    </span>
</div>
