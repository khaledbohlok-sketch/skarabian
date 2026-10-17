/* SK Arabians Management System — runs in <head> before the first paint (a file, not inline: CSP script-src 'self').
   html.js: JavaScript is on. html.is-curtain (not "curtain": that is the overlay's own class, hidden by default): the page right after signing in (the login form left a note) opens with
   the curtain. html.first-load: the first page of this browser session (the menu arrives item by item). */
(function (h) {
  'use strict';
  h.classList.add('js');
  try {
    if (sessionStorage.getItem('sk-curtain') === '1' && document.querySelector('meta[name="csrf-token"]')) {
      sessionStorage.removeItem('sk-curtain');
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && h.getAttribute('data-motion') !== 'reduce') h.classList.add('is-curtain');
    }
    if (!sessionStorage.getItem('sk-first')) { h.classList.add('first-load'); sessionStorage.setItem('sk-first', '1'); }
  } catch (e) { /* storage blocked: no curtain */ }
})(document.documentElement);
