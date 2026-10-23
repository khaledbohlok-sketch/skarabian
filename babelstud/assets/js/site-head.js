/* Babel Stud website — runs in <head>, before the first paint (a file, not inline: CSP script-src 'self').
   html.js (and no .no-js): JavaScript is on (blocks below the screen may wait for their reveal). html.reduce: the
   visitor asked for less motion. html.intro: play the first-visit preloader — once per session, never with reduced
   motion, never for automated browsers (page checks, screenshots). */
(function (h) {
  'use strict';
  h.classList.remove('no-js');
  h.classList.add('js');
  var reduce = false;
  try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
  if (reduce) h.classList.add('reduce');
  try {
    if (!reduce && !navigator.webdriver && !sessionStorage.getItem('babel-intro')) {
      h.classList.add('intro');
      sessionStorage.setItem('babel-intro', '1');
    }
  } catch (e) { /* storage blocked: no preloader */ }
})(document.documentElement);
