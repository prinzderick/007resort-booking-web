// 007 Resort & Spa: small vanilla enhancement layer (no framework, no jQuery).
// The site works without JavaScript; every module feature-detects its markup and fails soft.
import { initHeader } from './modules/header.js';
import { initReveal } from './modules/reveal.js';
import { initParallax } from './modules/parallax.js';
import { initRails } from './modules/rail.js';
import { initHero } from './modules/hero.js';
import { initGallery } from './modules/gallery.js';
import { initCheckout } from './modules/checkout.js';
import { initOrders } from './modules/orders.js';
import { initForms } from './modules/forms.js';
import { initBooking } from './modules/booking.js';
import { initSubscribe } from './modules/subscribe.js';
import { initMisc } from './modules/misc.js';
import { initSocial } from './modules/social.js';

const safe = (fn) => {
    try {
        fn();
    } catch (e) {
        if (import.meta.env?.DEV) console.error(e);
    }
};

const boot = () => {
    [initHeader, initReveal, initParallax, initRails, initHero, initGallery, initCheckout, initForms, initBooking, initSubscribe, initMisc, initSocial, initOrders].forEach(safe);
    document.documentElement.dataset.ready = '1';
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
