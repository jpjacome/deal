// Homepage motion: split-text reveal, scroll-linked effects, pinned horizontal gallery,
// counters, magnetic buttons, pointer glow and a custom cursor. Everything degrades to a
// static page with prefers-reduced-motion or without JavaScript.

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(pointer: fine)').matches;
const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));

/** Progress of an element through the viewport: 0 when its top reaches the bottom, 1 when its bottom leaves the top. */
function progressOf(el, startOffset = 0, endOffset = 0) {
    const rect = el.getBoundingClientRect();
    const vh = window.innerHeight;
    return clamp((vh - rect.top - startOffset) / (rect.height + vh - startOffset - endOffset));
}

/* Split the hero headline into words for the staggered rise. */
function splitWords(el, baseIndex = 1) {
    const words = el.textContent.trim().split(/\s+/);
    el.setAttribute('aria-label', el.textContent.trim());
    el.innerHTML = words
        .map((word, i) => `<span class="w" aria-hidden="true"><span class="wi" style="--i:${baseIndex + i}">${word}</span></span>`)
        .join(' ');
}

document.querySelectorAll('[data-split]').forEach((el) => splitWords(el));

/* Header: glass over the hero, solid once scrolled. */
const body = document.body;
const onScrollHeader = () => body.classList.toggle('is-scrolled', window.scrollY > 40);
onScrollHeader();
window.addEventListener('scroll', onScrollHeader, { passive: true });

/* Reveal on enter (clip wipes, fades, counters). */
const counters = new WeakSet();
function countUp(el) {
    if (counters.has(el)) {
        return;
    }
    counters.add(el);
    const target = Number(el.dataset.count);
    if (reduced || target === 0) {
        el.textContent = String(target);
        return;
    }
    const start = performance.now();
    const duration = 1400;
    const tick = (now) => {
        const t = clamp((now - start) / duration);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - t, 3))));
        if (t < 1) {
            requestAnimationFrame(tick);
        }
    };
    el.textContent = '0';
    requestAnimationFrame(tick);
}

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (!entry.isIntersecting) {
            return;
        }
        entry.target.classList.add('is-in');
        entry.target.querySelectorAll('[data-count]').forEach(countUp);
        revealObserver.unobserve(entry.target);
    });
}, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });

document.querySelectorAll('[data-reveal]').forEach((el) => revealObserver.observe(el));

/* Manifesto: wrap words so they can light up with scroll progress. */
const manifesto = document.querySelector('[data-words]');
let manifestoWords = [];
if (manifesto) {
    const words = manifesto.textContent.trim().split(/\s+/);
    manifesto.innerHTML = words.map((word) => `<span>${word}</span>`).join(' ');
    manifestoWords = [...manifesto.children];
}

/* Pinned horizontal gallery (desktop only). */
const hscroll = document.querySelector('[data-hscroll]');
const track = hscroll?.querySelector('[data-hscroll-track]');
const bar = hscroll?.querySelector('[data-hscroll-progress]');
const indexLabel = hscroll?.querySelector('[data-hscroll-index]');
let hscrollDistance = 0;
const hscrollEnabled = () => !reduced && window.innerWidth >= 900;

function layoutHscroll() {
    if (!hscroll || !track) {
        return;
    }
    if (!hscrollEnabled()) {
        hscroll.style.height = '';
        hscroll.classList.remove('is-pinned');
        track.style.transform = '';
        return;
    }
    hscroll.classList.add('is-pinned');
    hscrollDistance = Math.max(0, track.scrollWidth - window.innerWidth);
    hscroll.style.height = `${window.innerHeight + hscrollDistance}px`;
}

const city = document.querySelector('[data-city]');
const steps = document.querySelector('[data-steps]');
const heroMedia = document.querySelector('[data-hero-media]');
const drifting = [...document.querySelectorAll('[data-drift]')];

/* One rAF-throttled scroll handler drives every scroll-linked effect. */
let ticking = false;
function onScroll() {
    if (ticking) {
        return;
    }
    ticking = true;
    requestAnimationFrame(() => {
        ticking = false;
        const vh = window.innerHeight;

        if (heroMedia && !reduced) {
            const y = Math.min(window.scrollY, vh);
            heroMedia.style.transform = `translate3d(0, ${y * 0.3}px, 0) scale(${1.08 + y / vh * 0.08})`;
        }

        if (manifesto && manifestoWords.length) {
            const p = reduced ? 1 : progressOf(manifesto, vh * 0.15, vh * 0.35);
            const lit = Math.round(p * 1.25 * manifestoWords.length);
            manifestoWords.forEach((word, i) => word.classList.toggle('lit', i < lit));
        }

        if (hscroll && track && hscrollEnabled()) {
            const rect = hscroll.getBoundingClientRect();
            const p = clamp(-rect.top / Math.max(1, hscrollDistance));
            track.style.transform = `translate3d(${-p * hscrollDistance}px, 0, 0)`;
            if (bar) {
                bar.style.transform = `scaleX(${p})`;
            }
            if (indexLabel) {
                const cards = track.children.length;
                indexLabel.textContent = String(Math.min(cards, Math.floor(p * (cards - 0.01)) + 1)).padStart(2, '0');
            }
        }

        if (city) {
            city.style.setProperty('--p', reduced ? 1 : clamp(progressOf(city) * 2.2).toFixed(3));
        }

        if (steps) {
            steps.style.setProperty('--p', reduced ? 1 : clamp(progressOf(steps, vh * 0.2, vh * 0.3) * 1.6).toFixed(3));
        }

        if (!reduced) {
            drifting.forEach((img) => {
                const p = progressOf(img.parentElement);
                img.style.transform = `translate3d(0, ${(p - 0.5) * -12}%, 0) scale(1.18)`;
            });
        }
    });
}

layoutHscroll();
onScroll();
window.addEventListener('scroll', onScroll, { passive: true });
window.addEventListener('resize', () => { layoutHscroll(); onScroll(); });
window.addEventListener('load', () => { layoutHscroll(); onScroll(); });

/* Magnetic buttons and pointer glow. */
if (finePointer && !reduced) {
    document.querySelectorAll('[data-magnetic]').forEach((btn) => {
        btn.addEventListener('pointermove', (e) => {
            const r = btn.getBoundingClientRect();
            const x = (e.clientX - r.left - r.width / 2) * 0.3;
            const y = (e.clientY - r.top - r.height / 2) * 0.4;
            btn.style.transform = `translate(${x}px, ${y}px)`;
        });
        btn.addEventListener('pointerleave', () => { btn.style.transform = ''; });
    });

    document.querySelectorAll('[data-glow]').forEach((el) => {
        el.addEventListener('pointermove', (e) => {
            const r = el.getBoundingClientRect();
            el.style.setProperty('--mx', `${e.clientX - r.left}px`);
            el.style.setProperty('--my', `${e.clientY - r.top}px`);
        });
    });

    /* Custom cursor: a dot plus a trailing ring that grows over links. */
    const dot = Object.assign(document.createElement('div'), { className: 'cursor-dot' });
    const ring = Object.assign(document.createElement('div'), { className: 'cursor-ring' });
    document.body.append(dot, ring);
    body.classList.add('has-cursor');

    let mx = -100;
    let my = -100;
    let rx = mx;
    let ry = my;
    window.addEventListener('pointermove', (e) => {
        mx = e.clientX;
        my = e.clientY;
        dot.style.transform = `translate(${mx}px, ${my}px)`;
    }, { passive: true });
    document.addEventListener('pointerover', (e) => {
        ring.classList.toggle('is-hover', Boolean(e.target.closest('a, button, label, input, select, textarea')));
    });
    document.addEventListener('pointerleave', () => { mx = my = -100; });

    const follow = () => {
        rx += (mx - rx) * 0.18;
        ry += (my - ry) * 0.18;
        ring.style.transform = `translate(${rx}px, ${ry}px)`;
        requestAnimationFrame(follow);
    };
    requestAnimationFrame(follow);
}
