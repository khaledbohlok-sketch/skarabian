/* SK Arabians public website: header, mobile menu, hero slideshow, filters, counters, reveal, gallery. */
(function () {
  'use strict';
  var header = document.querySelector('[data-header]');
  if (header && !header.classList.contains('solid')) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 40); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }
  var btn = document.querySelector('[data-mobile-nav]'), menu = document.querySelector('[data-mobile-menu]');
  if (btn && menu) btn.addEventListener('click', function () { var o = menu.classList.toggle('open'); btn.setAttribute('aria-expanded', String(o)); if (header) header.classList.add('scrolled'); });

  // Hero slideshow (lazy background images)
  var slides = document.querySelectorAll('.hero-slide');
  slides.forEach(function (s, i) { if (i === 0 && s.dataset.bg) s.style.backgroundImage = 'url("' + s.dataset.bg + '")'; });
  if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var cur = 0;
    setInterval(function () {
      var next = (cur + 1) % slides.length, n = slides[next];
      if (!n.style.backgroundImage && n.dataset.bg) n.style.backgroundImage = 'url("' + n.dataset.bg + '")';
      slides[cur].classList.remove('on'); n.classList.add('on'); cur = next;
    }, 6500);
  }

  // Category filter chips
  document.querySelectorAll('[data-filter-group]').forEach(function (grp) {
    var target = document.querySelector('[data-filter-target="' + grp.dataset.filterGroup + '"]');
    grp.addEventListener('click', function (e) {
      var b = e.target.closest('[data-filter]'); if (!b || !target) return;
      grp.querySelectorAll('[data-filter]').forEach(function (x) { x.classList.toggle('on', x === b); });
      target.querySelectorAll('[data-cat]').forEach(function (c) { c.hidden = b.dataset.filter !== 'all' && c.dataset.cat !== b.dataset.filter; });
      target.scrollLeft = 0;
    });
  });

  // Reveal on scroll + animated counters
  var animate = function (el) {
    var end = parseInt(el.dataset.count, 10) || 0, t0 = null, dur = 1400;
    var step = function (t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        en.target.querySelectorAll('[data-count]').forEach(animate);
        io.unobserve(en.target);
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
  }

  // Horse profile gallery
  document.querySelectorAll('[data-gallery]').forEach(function (g) {
    var main = g.querySelector('[data-gallery-main]');
    g.addEventListener('click', function (e) {
      var b = e.target.closest('[data-src]'); if (!b || !main) return;
      main.src = b.dataset.src; g.querySelectorAll('[data-src]').forEach(function (x) { x.classList.toggle('on', x === b); });
    });
  });
})();
