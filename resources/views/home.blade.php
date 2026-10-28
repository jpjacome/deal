@extends('layouts.app', ['bodyClass' => 'home'])

@php
    $img = fn (string $name) => asset("images/home/{$name}-900.webp");
    $srcset = fn (string $name) => asset("images/home/{$name}-900.webp").' 900w, '.asset("images/home/{$name}-2000.webp").' 2000w';
    // Stand-in photos for spaces without their own (until Deal uploads real ones).
    $fallbacks = ['meeting', 'focus', 'office', 'flex', 'community', 'tables'];
    $credits = ['Nimal Mathew', 'Justin Kauffman', 'Copernico', 'Pavel Gromov', 'Benjamin Child', 'CoWomen', 'Fernando Tapia', 'Jonathan Monck-Mason', 'Tim Schmidbauer'];
@endphp

@push('head')
    {{-- Skip the intro for returning visitors in this session and for reduced motion, before first paint. --}}
    <script>
        document.documentElement.classList.add('js');
        try {
            if (sessionStorage.getItem('deal-intro') || matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.classList.add('intro-seen');
            } else {
                sessionStorage.setItem('deal-intro', '1');
            }
        } catch (e) {
            document.documentElement.classList.add('intro-seen');
        }
    </script>
    <link rel="preload" as="image" href="{{ $img('lounge') }}" imagesrcset="{{ $srcset('lounge') }}" imagesizes="100vw" fetchpriority="high">
    @vite('resources/js/home.js')
@endpush

@section('content')
    {{-- Intro curtain: wordmark letters rise, then the curtain lifts (pure CSS, hides itself). --}}
    <div class="intro" aria-hidden="true"><span class="intro-logo"><x-logo /></span></div>

    {{-- Hero --}}
    <section class="h-hero" data-hero>
        <div class="h-hero-media" data-hero-media>
            <img src="{{ $img('lounge') }}" srcset="{{ $srcset('lounge') }}" sizes="100vw" alt="Lounge cálido con plantas y luz ámbar" fetchpriority="high">
        </div>
        <div class="h-hero-shade"></div>
        <div class="grain" aria-hidden="true"></div>

        <div class="container h-hero-inner">
            <p class="eyebrow h-fade" style="--i: 0">Meet Hub · Cuenca, Ecuador</p>
            <h1 class="h-display" data-split>Tu lugar para crear lo que viene.</h1>
            <p class="h-lead h-fade" style="--i: 6">Salas, oficinas y puestos flexibles en un espacio cálido. Elige el día y el horario: confirmas en un minuto, sin llamadas.</p>
            <div class="hero-actions h-fade" style="--i: 8">
                <a href="{{ route('bookings.create') }}" class="btn btn-light" data-magnetic>Reservar ahora</a>
                <a href="#espacios" class="btn btn-ghost-light">Ver espacios</a>
            </div>
        </div>

        <aside class="h-float" aria-label="Disponibilidad de hoy">
            @foreach ($freeToday->take(2) as $row)
                <a class="glass-bubble h-fade" style="--i: {{ 10 + $loop->index }}; --float: {{ $loop->index }}"
                   href="{{ route('bookings.create', ['espacio' => $row['space']->slug, 'fecha' => today()->toDateString(), 'hora' => $row['start']->format('H:i')]) }}">
                    <span class="live-dot"></span>
                    <span><small>Libre hoy</small>{{ $row['space']->name }} · desde {{ $row['start']->format('H:i') }}</span>
                </a>
            @endforeach
            @if ($todayHours)
                <span class="glass-bubble h-fade" style="--i: 12; --float: 2">
                    <span><small>Hoy abrimos</small>{{ $todayHours }}</span>
                </span>
            @endif
        </aside>

        <a href="#manifiesto" class="h-scroll" aria-label="Seguir bajando"><span>Desliza</span><i></i></a>
    </section>

    {{-- Marquee --}}
    <section class="marquee" aria-hidden="true">
        <div class="marquee-track">
            @for ($i = 0; $i < 2; $i++)
                <span>Meet</span><span>·</span><span>Work</span><span>·</span><span>Create</span><span>·</span><span>Hub</span><span>·</span>
            @endfor
        </div>
    </section>

    {{-- Manifesto: words light up as you scroll --}}
    <section class="container h-manifesto" id="manifiesto">
        <p class="eyebrow">Deal</p>
        <p class="manifesto" data-words>
            Un espacio cálido en el corazón de Cuenca para concentrarte, reunirte con tu equipo y conocer a otras personas que también están construyendo algo. Llegas, te sientes en casa y haces tu mejor trabajo.
        </p>
    </section>

    {{-- Spaces: pinned horizontal scroll on desktop, swipe on mobile --}}
    <section class="h-spaces" id="espacios" data-hscroll>
        <div class="h-spaces-sticky">
            <div class="container h-spaces-head">
                <div>
                    <p class="eyebrow">Espacios</p>
                    <h2>Elige cómo quieres trabajar</h2>
                </div>
                <span class="h-count"><b data-hscroll-index>01</b> / {{ str_pad((string) ($spaces->count() + 1), 2, '0', STR_PAD_LEFT) }}</span>
            </div>

            <div class="h-spaces-track" data-hscroll-track>
                @foreach ($spaces as $space)
                    @php($photo = $space->photoUrl() ?? $img($fallbacks[$loop->index % count($fallbacks)]))
                    <a class="h-space" href="{{ route('bookings.create', ['espacio' => $space->slug]) }}">
                        <img src="{{ $photo }}" alt="{{ $space->name }}" loading="lazy">
                        <span class="h-space-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="h-space-body">
                            <span class="eyebrow">{{ $space->type_label ?: $space->booking_mode->getLabel() }}</span>
                            <span class="h-space-name">{{ $space->name }}</span>
                            <span class="h-space-meta">
                                Hasta {{ $space->capacity }} {{ $space->capacity === 1 ? 'persona' : 'personas' }}
                                @if ($space->price !== null)
                                    · ${{ rtrim(rtrim(number_format((float) $space->price, 2), '0'), '.') }} {{ $space->priceUnitLabel() }}
                                @endif
                            </span>
                            <span class="h-space-cta">Reservar <i>→</i></span>
                        </span>
                    </a>
                @endforeach
                <a class="h-space h-space-end gradient" href="{{ route('bookings.create') }}">
                    <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
                    <span class="h-space-body">
                        <span class="eyebrow">¿No sabes cuál elegir?</span>
                        <span class="h-space-name">Mira la disponibilidad de todos</span>
                        <span class="h-space-cta">Ver calendario <i>→</i></span>
                    </span>
                </a>
            </div>

            <div class="container"><div class="h-progress"><i data-hscroll-progress></i></div></div>
        </div>
    </section>

    {{-- Mosaic + numbers --}}
    <section class="container h-mosaic">
        <div class="mosaic">
            <figure class="m1" data-reveal="clip"><img src="{{ $img('community') }}" srcset="{{ $srcset('community') }}" sizes="(min-width: 900px) 60vw, 100vw" alt="Equipo trabajando en una mesa de madera" loading="lazy" data-drift></figure>
            <figure class="m2" data-reveal="clip"><img src="{{ $img('tables') }}" alt="Mesas largas para trabajar" loading="lazy" data-drift></figure>
            <div class="m3 tile tile-dark stat" data-reveal>
                <span class="stat-number" data-count="{{ $spaces->count() }}">{{ $spaces->count() }}</span>
                <span>espacios para cada forma de trabajar</span>
            </div>
            <div class="m4 tile stat" data-reveal>
                <span class="stat-number"><span data-count="60">60</span><small>s</small></span>
                <span>para reservar en línea</span>
            </div>
            <figure class="m5" data-reveal="clip"><img src="{{ $img('focus') }}" alt="Escritorio con lámpara y laptop" loading="lazy" data-drift></figure>
            <div class="m6 tile gradient stat stat-quote" data-reveal>
                <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
                <span class="stat-number"><span data-count="0">0</span></span>
                <span>llamadas o mensajes para reservar. Eliges, confirmas y te llega todo por correo.</span>
            </div>
        </div>
    </section>

    {{-- Cuenca: the photo opens up to full bleed as you scroll --}}
    <section class="h-city" data-city>
        <div class="h-city-frame">
            <img src="{{ $img('cuenca') }}" srcset="{{ $srcset('cuenca') }}" sizes="100vw" alt="Cúpulas de la Catedral Nueva de Cuenca" loading="lazy">
            <div class="h-city-shade"></div>
            <div class="container h-city-inner">
                <p class="eyebrow">Ubicación</p>
                <h2>En el corazón de Cuenca</h2>
                <p>{{ $settings['venue_address'] }}@if ($todayHours) · Hoy {{ $todayHours }}@endif</p>
                <a href="{{ $settings['maps_url'] ?: route('contact') }}" class="btn btn-light" data-magnetic @if ($settings['maps_url']) target="_blank" rel="noopener" @endif>Cómo llegar</a>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="container h-steps">
        <p class="eyebrow">Cómo funciona</p>
        <h2>Reserva en un minuto</h2>
        <ol class="steps-line" data-steps>
            <li data-reveal><span class="bubble">1</span><h3>Elige tu espacio</h3><p class="muted">Sala, cabina, oficina o un puesto en el área compartida.</p></li>
            <li data-reveal><span class="bubble">2</span><h3>Elige el día y la hora</h3><p class="muted">Ves la disponibilidad real de las próximas dos semanas.</p></li>
            <li data-reveal><span class="bubble">3</span><h3>Listo, te esperamos</h3><p class="muted">Confirmación por correo y, si quieres, por WhatsApp.</p></li>
        </ol>
    </section>

    {{-- Final call to action --}}
    <section class="container h-cta">
        <div class="tile gradient h-cta-tile" data-glow>
            <span class="supergraphic supergraphic-hero" aria-hidden="true"><x-logo /></span>
            <p class="eyebrow">Meet Hub</p>
            <h2>¿Listo para tu próxima gran idea?</h2>
            <a href="{{ route('bookings.create') }}" class="btn btn-light btn-lg" data-magnetic>Reservar ahora</a>
        </div>
    </section>
@endsection

@push('before-footer')
    <p class="container photo-credits">Fotografías: Unsplash — {{ implode(', ', $credits) }}.</p>
@endpush
