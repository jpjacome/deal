# Deal Coworking — Platform Plan

Product and technical spec for the Deal website + reservations platform (Cuenca, Ecuador).
Source of scope: `Deal - Cotización Web.pdf` ($600, ½ week design + 1 week dev + 1 week review).

Public site language: **Spanish only**. Timezone: **America/Guayaquil** (UTC-5, no DST). Currency display: USD.

---

## 1. What a coworking platform does — and what Deal needs now

Full coworking suites (Nexudus, OfficeRnD, Optix) bundle six areas. We build one and a half of them and leave room for the rest.

| Area | What it is in the big suites | Deal v1 |
|---|---|---|
| Marketing site + CMS | Pages, spaces, plans, gallery | **Yes**, fully editable |
| Bookings | Rooms, desks, day passes, availability rules, calendar | **Yes**, the core |
| Notifications | Email/SMS/push confirmations, reminders | **Yes**, email + WhatsApp |
| Members & memberships | Accounts, plans, credits/hours included per plan | No. Guest bookings. Data model leaves room |
| Billing & payments | Invoices, cards, SRI e-invoicing | No (excluded in quote) |
| Access & community | Door access, wifi, member directory, events | No |

**Principle:** a visitor should go from landing page to confirmed booking in under a minute, without creating an account, and Deal should never have to answer a WhatsApp chat just to say whether a room is free.

---

## 2. Users and roles

| Who | How they get in | What they do |
|---|---|---|
| **Visitor / client** | No account. Gets a private signed link in every email/WhatsApp | Browse, book, view/cancel their booking via the link |
| **Staff** | Admin panel login | See and manage bookings, block times |
| **Admin (owner)** | Admin panel login | Everything staff does plus CMS, settings, users |

No client accounts in v1: the quote says "la persona deja sus datos", and accounts add friction plus password-reset support work. We save clients by email/phone (a `customers` table) so history exists and accounts can be added later.

---

## 3. Public site (from the quote)

1. **Inicio**: hero, value proposition, featured spaces, plans teaser, gallery strip, testimonials, CTA to book and to WhatsApp.
2. **Espacios**: list plus one detail page per space (photos, capacity, amenities, price shown as text, "Reservar" button opening the booking flow pre-filtered).
3. **Planes y precios**: comparable plan cards (day pass, weekly, monthly, private office…). Plans are *informational* in v1: the CTA is "Reservar" (for bookable ones) or "Consultar por WhatsApp".
4. **Galería**: grid + lightbox, categories optional.
5. **Ubicación y contacto**: map embed, address, opening hours (from settings), contact form, WhatsApp button.
6. **Reservar**: booking flow (section 4).
7. **Mi reserva** (signed URL): booking details, add to calendar, cancel.
8. Legal: **Política de privacidad** (required by Ecuador's LOPDP), booking terms/cancellation policy.

Global: floating WhatsApp button (`wa.me` link with a prefilled message), footer with hours/contact/social, cookie-free analytics if possible.

SEO: per-page meta/OG editable in CMS, `sitemap.xml`, schema.org `LocalBusiness` + opening hours, fast images (WebP, responsive sizes).

---

## 4. Reservations: the core

### 4.1 Bookable resource types

Coworking inventory falls into three booking patterns. Each **space** has a `booking_mode`:

| Mode | Example | Unit | Availability rule |
|---|---|---|---|
| `hourly` | Meeting room, phone booth, event room | Time slots (e.g. 30/60 min grid) | Exclusive: one booking at a time |
| `daily` | Private office, dedicated desk | Full day (optionally half-day AM/PM) | Exclusive |
| `capacity` | Hot desk / day pass in open area | Full day | Up to `capacity` bookings per day |

This covers everything Deal is likely to offer without building a seat-map. Per-seat floor plans are out of scope.

### 4.2 Booking flow (mobile first)

1. **Choose space** (cards with photo, capacity, price label). Skipped if arriving from a space page.
2. **Choose date**: calendar with unavailable days greyed out (closed days, fully booked, beyond max-advance window).
3. **Choose time** (hourly): available start times as chips, then duration (or start/end chips). Half-day/full-day for daily mode.
4. **Your details**: name, email, WhatsApp number (default country +593, normalized to E.164), company (optional), number of attendees (≤ capacity), notes. Checkboxes: accept privacy policy & booking terms (required); "Recibir notificaciones por WhatsApp" (opt-in, needed for Meta policy).
5. **Review & confirm** → success screen with booking code (e.g. `DL-7K3Q`), status, add-to-calendar links.

Built as one Livewire component; availability is recomputed server-side at submit (never trust the client).

### 4.3 Booking statuses

```
            ┌─ (auto-confirm space) ─────────────┐
 [new] ─────┤                                     ▼
            └─► pending ──admin confirms──► confirmed ──► completed
                   │                            │   └──► no_show
                   ├─admin rejects─► rejected   │
                   └─expires (opt.)─► expired   └─client/admin cancels─► cancelled
```

- **Per-space setting `requires_approval`**: the quote says Deal "confirma o cancela", so default is manual approval; spaces like day passes can be auto-confirmed.
- `pending` **holds the slot** (prevents double booking while Deal reviews). Optional auto-expiry if not reviewed in N hours (setting; off by default).
- `completed` / `no_show` marked by staff (no_show feeds a simple per-customer counter, useful later).

### 4.4 Availability rules (all editable in admin)

Global settings + per-space overrides:

- **Opening hours** per weekday (multiple ranges allowed, e.g. 08:00–13:00, 14:00–19:00).
- **Closures / holidays**: date ranges, whole venue.
- **Blocks**: admin blocks a space (or all) for a date/time range with a reason ("mantenimiento", "evento privado"). Shown in the admin calendar, invisible to clients.
- **Slot granularity**: 30 or 60 min.
- **Min / max duration** (e.g. 1h–8h).
- **Buffer** between bookings (e.g. 15 min for cleaning). Industry best practice, often forgotten.
- **Lead time**: minimum notice (e.g. 2 hours before start).
- **Booking horizon**: how far ahead (e.g. 60 days).
- **Cancellation window**: client can self-cancel up to X hours before; after that, the link shows "contáctanos por WhatsApp".
- **Max active bookings per customer** (anti-abuse, e.g. 3 upcoming).

### 4.5 Correctness requirements

- **No double booking under concurrency**: create bookings inside a DB transaction that locks the space row (`lockForUpdate`) and re-checks overlap (`start < other.end + buffer AND end + buffer > other.start`) against `pending|confirmed` bookings and blocks.
- App timezone is America/Guayaquil (set in `config/app.php`). Ecuador has no DST, so storing local time is unambiguous and keeps queries and Filament simple.
- Spam protection on booking + contact forms: honeypot + rate limiting per IP (+ Cloudflare Turnstile if spam appears).
- Signed, unguessable manage links (`URL::signedRoute` or a random 32-char token on the booking).

---

## 5. Notifications

### 5.1 Matrix

| Event | Client email | Client WhatsApp* | Deal email | Deal WhatsApp |
|---|---|---|---|---|
| Booking request received (pending) | ✓ "Recibimos tu solicitud" | ✓ | ✓ new booking alert, link to panel | ✓ |
| Booking confirmed (auto or by admin) | ✓ + `.ics` attachment | ✓ | – | – |
| Booking rejected | ✓ (with optional admin message) | ✓ | – | – |
| Cancelled by client | ✓ | ✓ | ✓ | ✓ |
| Cancelled by Deal | ✓ (with reason) | ✓ | – | – |
| Reminder (e.g. 24h before, configurable) | ✓ | ✓ | – | – |
| Daily agenda (07:30, today's bookings) | – | – | ✓ (optional) | optional |
| Contact form message | auto-reply ✓ | – | ✓ | – |

\*Only if the client opted in. Recipients for "Deal" alerts are a list in settings (emails + WhatsApp numbers).

Every client message includes: booking code, space, date/time, address/map link, manage/cancel link.

### 5.2 Email

- Laravel Mailables (Markdown, branded), Spanish copy editable later if needed.
- Send through a **transactional provider** (Resend, Brevo, Amazon SES or Postmark), not the shared-hosting SMTP, which tends to land in spam. Configure **SPF, DKIM, DMARC** on Deal's domain.
- `.ics` attachment + Google Calendar link on confirmation.
- All notifications **queued** so a slow provider never breaks the booking request.

### 5.3 WhatsApp: decision and facts

**Use the official WhatsApp Business Platform (Meta Cloud API) directly.** No middleman (BSP) fee, and Laravel calls it with a plain HTTP client.
Do **not** use unofficial gateways (WhatsApp Web automation: Baileys, WAHA, etc.): the number can be banned without warning, and that would also take down Deal's customer chat.

What it requires (Deal's side, can take a few days, so **start it in week 1**):
1. Meta Business account (Business Manager), ideally business-verified.
2. A phone number for the API. Either a dedicated number, or Deal's current number via WhatsApp Business app "coexistence" if available to them.
3. **Message templates** submitted and approved by Meta, in Spanish, category **Utility** (transactional: tied to a specific booking, no promo content, or Meta reclassifies them as Marketing at ~6× the price). Variables as `{{1}}`, `{{2}}`…
   - `reserva_recibida`, `reserva_confirmada`, `reserva_rechazada`, `reserva_cancelada`, `recordatorio_reserva`, `nueva_reserva_admin`
4. Permanent system-user access token + phone number ID stored in `.env`.

Cost (Meta rate card, Ecuador = "Rest of Latin America"): roughly **US$0.011 per utility message** (since Oct 1 2026, utility templates are charged even inside the 24h window). Example: 200 bookings/month × 3 messages ≈ **US$7/month**, billed by Meta to Deal's card. Rates change quarterly; confirm on Meta's official pricing page.

Implementation: a custom Laravel Notification channel `WhatsAppChannel` (≈80 lines over `Http::withToken()`), or one of the small Cloud API packages, but they are young (2026), so a thin in-house channel is safer. Log every send (`notification_logs`: channel, template, recipient, status, provider message id, error). Optional webhook for delivery/read status: phase 2.

Failure policy: WhatsApp failure never blocks a booking; email is the guaranteed channel; failures are retried 3× then visible in the admin panel.

---

## 6. Admin panel (Filament)

**Reservations** (the quote's "Panel de reservas")
- Dashboard: today's bookings, pending to review (badge), upcoming 7 days, quick stats (bookings this month, occupancy % per space, no-shows).
- **Calendar view** (day/week, per space, color by status), click to open booking.
- Bookings table: filters (status, space, date range), search by code/name/email/phone, actions: confirm, reject (with message), cancel (with reason), mark completed/no-show, edit time, resend notification.
- Create booking manually (walk-ins / bookings taken by phone) with the same availability engine; option to not notify.
- Blocks & closures management.
- Customers list (history of bookings per person, no-show count), CSV export.

**CMS** (the quote's "Gestor de contenidos")
- Spaces (name, slug, description, photos with ordering, capacity, amenities, price label, booking mode & rules, active/bookable flags).
- Plans (name, price, period, features list, highlight, order, CTA type).
- Amenities (icon + label, reused across spaces).
- Gallery (photos, categories, order).
- Home page sections (hero texts/image, value props, testimonials, FAQs).
- Settings: contact info, address, map, opening hours, WhatsApp number & prefilled message, social links, notification recipients, booking rules defaults, SEO defaults.
- Contact messages inbox.
- Users (admin/staff).

Image uploads auto-converted/resized (WebP, thumbnails).

---

## 7. Technical architecture

| Concern | Choice | Why |
|---|---|---|
| Framework | **Laravel 13**, PHP 8.4 | Current stable |
| Admin + CMS | **Filament v5** | Bookings panel, CMS, auth, tables, forms in days instead of weeks; essential for a 1-week dev budget |
| Public frontend | Blade + **Livewire 4** (booking widget) + Alpine + **one plain CSS file** (§7.2). **No Tailwind** | SEO-friendly, no SPA build complexity, styling stays readable |
| DB | MySQL 8 / MariaDB (whatever the hosting gives) | |
| Queue | `database` driver | Works on shared hosting via cron |
| Scheduler | `schedule:run` every minute (cron) | Reminders, daily agenda, queue processing on shared hosts (`queue:work --stop-when-empty`) |
| Media | spatie/laravel-medialibrary (or Filament FileUpload + Intervention Image) | Conversions/responsive images |
| Settings | spatie/laravel-settings | Typed settings editable in Filament |
| Phone numbers | propaganistas/laravel-phone | E.164 normalization, EC default |
| Spam | spatie/laravel-honeypot + rate limiter | |
| SEO | spatie/laravel-sitemap, meta fields per model | |
| Future mobile app | Business logic in **Action classes** (`CreateBooking`, `CancelBooking`, `GetAvailability`) used by both Livewire and a versioned `/api/v1` (Sanctum). v1 ships read-only API endpoints for spaces + availability | The quote promises "base preparada para conectar una futura aplicación móvil"; this delivers it cheaply |
| Tests | Pest: availability engine + overlap/concurrency + status transitions | The one part that must be bulletproof |

### 7.1 Data model (v1)

```
users               id, name, email, password, role(admin|staff)
spaces              id, name, slug, type_label, description, capacity, booking_mode(hourly|daily|capacity),
                    requires_approval, slot_minutes, min_minutes, max_minutes, buffer_minutes,
                    lead_time_minutes, horizon_days, price_label, is_bookable, is_active, sort, seo_*
amenities           id, name, icon        ↔  amenity_space (pivot)
opening_hours       id, space_id NULL(=global), weekday, opens_at, closes_at
blocked_periods     id, space_id NULL(=all), starts_at, ends_at, reason      ← holidays + admin blocks
customers           id, name, email, phone_e164, company, whatsapp_opt_in, no_show_count, timestamps
bookings            id, code, space_id, customer_id, starts_at, ends_at, attendees, notes,
                    status, status_reason, source(web|admin|api), manage_token,
                    confirmed_at, cancelled_at, cancelled_by, reminder_sent_at, timestamps
                    INDEX(space_id, starts_at, ends_at, status)
booking_events      id, booking_id, user_id NULL, from_status, to_status, note, created_at   ← audit trail
plans               id, name, price, period_label, description, features(json), is_featured, cta, sort
gallery_items       id, category, caption, sort  (+ media)
testimonials, faqs  simple content tables
contact_messages    id, name, email, phone, message, read_at
notification_logs   id, booking_id NULL, channel, template, recipient, status, provider_id, error, created_at
settings            (spatie/laravel-settings)
```

Reserved for later (don't build, don't block): `memberships`, `credits`, `payments`, `invoices` (with cédula/RUC on customers for SRI e-invoicing).

### 7.2 Styling

- **No Tailwind, no CSS framework.** The public site uses a single file, `resources/css/app.css`, as the one source of styling.
- Remove the Tailwind dependency and Vite plugin that new Laravel projects ship with. Vite only bundles `app.css` + `app.js` (for cache-busting).
- The file defines only **5 colors, 1 border radius and 2 fonts** as variables, plus base element styles. No utility classes and no spacing or size scales. Each page or component writes plain CSS below the base section, using those variables.
- Swap in Deal's brand colors and fonts once received (placeholders below).
- The Filament admin keeps its own built-in, precompiled styling. We don't customize it beyond setting the primary color to `--primary`.

```css
/* Deal — main stylesheet */

:root {
  --primary: #1a1a1a;   /* brand color: buttons, links, accents */
  --accent: #f2b705;    /* highlights, badges */
  --text: #222222;
  --muted: #6b6b6b;     /* secondary text, borders */
  --bg: #ffffff;

  --radius: 10px;

  --font-body: "Inter", system-ui, sans-serif;
  --font-heading: "Inter", system-ui, sans-serif;
}

*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  font-family: var(--font-body);
  line-height: 1.6;
  color: var(--text);
  background: var(--bg);
}

h1, h2, h3, h4 { font-family: var(--font-heading); line-height: 1.2; margin: 0 0 .5em; }

a { color: var(--primary); }

img { max-width: 100%; display: block; border-radius: var(--radius); }

.container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }

button, .btn {
  display: inline-block;
  padding: .75em 1.5em;
  border: 0;
  border-radius: var(--radius);
  background: var(--primary);
  color: var(--bg);
  font: inherit;
  text-decoration: none;
  cursor: pointer;
}

input, select, textarea {
  width: 100%;
  padding: .7em .9em;
  border: 1px solid var(--muted);
  border-radius: var(--radius);
  font: inherit;
  color: var(--text);
  background: var(--bg);
}

/* --- Sections and components below --- */
```

---

## 8. Legal / compliance (Ecuador)

- **LOPDP** (Ley Orgánica de Protección de Datos Personales) + Reglamento 2023: consent must be prior, informed and specific. The SPDP (2026) reads the Art. 12 information duty as requiring the full list of items. So:
  - Privacy policy page (controller = Deal's legal entity, purposes, retention, rights and how to exercise them, recipients incl. Meta/email provider as processors).
  - Unticked required checkbox linking the policy on booking and contact forms; separate optional WhatsApp opt-in checkbox.
  - Store consent timestamp + policy version on the customer/booking.
  - Admin ability to delete/anonymize a customer on request.
- Have Deal's lawyer review the policy text (content is Deal's responsibility per the quote).

---

## 9. Delivery plan (fits the quote's timeline)

**Week 0 (now, parallel): Deal's homework**: spaces & booking rules (fill the questionnaire in §11), texts, photos, logo files, domain access, Meta Business account + WhatsApp number, transactional email account.

**½ week: Design**: sitemap, booking flow wireframes (mobile first), page designs with Deal's brand.

**Week 1: Development**
1. Day 1: Laravel 13 + Filament 5 setup, auth, models/migrations, settings, seeders with placeholder content.
2. Day 2: Availability engine + `CreateBooking` action + tests (overlap, buffers, lead time, capacity, concurrency).
3. Day 3: Public booking flow (Livewire) + manage-booking page.
4. Day 4: Admin: bookings resource, calendar, blocks/closures, dashboard. Email notifications + `.ics`.
5. Day 5: CMS resources, public pages with real layout, WhatsApp channel + templates, scheduler (reminders), API v1 read endpoints.
6. Deploy to hosting, DNS, SSL, SPF/DKIM/DMARC, cron.

**Week 2: Review**: Deal tests on staging, adjustments, booking tests on mobile/desktop, training session (panel + CMS), launch.

---

## 10. Scope notes and risks

- ⚠️ **WhatsApp notifications are not in the signed quote.** The quote lists automatic **email** confirmations plus a WhatsApp **button**. Options: (a) include them as goodwill (~1 dev day), (b) quote them as an add-on, or (c) ship the channel disabled and enable it when Meta approval lands. Also tell Deal clearly that Meta bills per message (small but recurring) and that template approval depends on Meta.
- Meta onboarding / template approval can take days. Start immediately so it doesn't block launch; email works independently.
- Reminders, `.ics`, daily agenda, customers list and the read-only API are small additions beyond the literal quote but are what make a booking system feel professional. Each is hours, not days.
- Shared hosting: confirm it supports PHP 8.4, cron every minute, and outbound HTTPS (for Meta API). If not, a small VPS (e.g. Laravel Forge/Ploi + $6 droplet) is cleaner.
- Payments, memberships/credits, client accounts, recurring bookings, English version, mobile app: explicitly **out of scope** (matches quote's "No incluido").

---

## 11. Questions for Deal (needed before design ends)

1. Exact list of spaces: name, type, capacity, and which are **bookable online** vs "consultar".
2. For each bookable space: hourly / half-day / full-day? Minimum and maximum duration?
3. Do bookings need Deal's manual approval, or confirm instantly? (per space)
4. Opening hours per weekday, and holiday closures.
5. Cancellation policy (how many hours before can a client cancel themselves?).
6. Buffer between bookings for cleaning? How far ahead can people book?
7. Should prices appear on the site? Per hour/day/month?
8. Who receives new-booking alerts (emails + WhatsApp numbers)?
9. Which WhatsApp number: current one (coexistence) or a new dedicated number for notifications?
10. Do they want to collect cédula/RUC now (for future invoicing)?
11. Legal entity name/RUC for the privacy policy.
