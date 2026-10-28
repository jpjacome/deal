// Livewire's built-in "page expired" prompt is in English; show ours instead.
document.addEventListener('livewire:init', () => {
    let warned = false;

    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response.status !== 419) {
                return;
            }

            preventDefault();

            if (! warned) {
                warned = true;
                if (window.confirm('La página estuvo inactiva mucho tiempo. ¿Quieres recargarla para continuar?')) {
                    window.location.reload();
                }
            }
        });
    });
});

const CONTACT_KEY = 'deal.contact';
const CONTACT_FIELDS = ['name', 'email', 'countryCode', 'phone', 'company'];

// Common email domain typos → correct domain.
const DOMAIN_FIXES = {
    'gmial.com': 'gmail.com', 'gmal.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gmail.co': 'gmail.com', 'gmail.con': 'gmail.com', 'gnail.com': 'gmail.com',
    'hotmial.com': 'hotmail.com', 'hotmal.com': 'hotmail.com', 'hotmail.co': 'hotmail.com', 'hotmail.con': 'hotmail.com', 'hotmil.com': 'hotmail.com',
    'yaho.com': 'yahoo.com', 'yahoo.co': 'yahoo.com', 'yahoo.con': 'yahoo.com', 'yhoo.com': 'yahoo.com',
    'outlok.com': 'outlook.com', 'outlook.co': 'outlook.com', 'outloo.com': 'outlook.com',
    'iclod.com': 'icloud.com', 'icloud.co': 'icloud.com',
};

document.addEventListener('alpine:init', () => {
    // Contact step of the booking form: optional "remember me" (this device only) and typo hints.
    window.Alpine.data('bookingDetails', () => ({
        remember: true,
        suggestion: '',

        init() {
            try {
                const saved = JSON.parse(window.localStorage.getItem(CONTACT_KEY) || 'null');
                if (! saved) {
                    return;
                }
                CONTACT_FIELDS.forEach((field) => {
                    if (saved[field] && ! this.$wire.get(field)) {
                        this.$wire.set(field, saved[field], false);
                    }
                });
            } catch (e) {
                // Storage unavailable (private mode): the form still works.
            }
        },

        save() {
            try {
                if (this.remember) {
                    const data = {};
                    CONTACT_FIELDS.forEach((field) => { data[field] = this.$wire.get(field); });
                    window.localStorage.setItem(CONTACT_KEY, JSON.stringify(data));
                } else {
                    window.localStorage.removeItem(CONTACT_KEY);
                }
            } catch (e) {
                // Ignore storage errors.
            }
        },

        checkEmail(value) {
            const [user, domain] = String(value).trim().toLowerCase().split('@');
            this.suggestion = user && DOMAIN_FIXES[domain] ? `${user}@${DOMAIN_FIXES[domain]}` : '';
        },

        applySuggestion() {
            this.$wire.set('email', this.suggestion, false);
            this.suggestion = '';
        },
    }));
});
