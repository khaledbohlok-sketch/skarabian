/* Babel Stud — the page's behaviour. Everything here adds to a page that already works: the menu, the header, the
   reveals, the hero's lean, the counters, the gallery viewer and its counter, the phone dock, the chapter line. */
(function () {
  'use strict';
  var root = document.documentElement, body = document.body;
  var rtl = root.dir === 'rtl';
  var reduce = root.classList.contains('reduce');
  var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var coarse = window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var each = function (sel, fn, scope) { Array.prototype.forEach.call((scope || document).querySelectorAll(sel), fn); };

  // ---- Header: frosted when scrolled, away when scrolling down
  var header = document.querySelector('[data-header]');
  if (header) {
    var last = window.scrollY, ticking = false;
    var onScroll = function () {
      var y = window.scrollY, dy = y - last; last = y;
      header.classList.toggle('scrolled', y > 24);
      if (y < 160 || body.classList.contains('menu-open')) header.classList.remove('away');
      else if (Math.abs(dy) > 6) header.classList.toggle('away', dy > 0);
      ticking = false;
    };
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
    onScroll();
  }

  // ---- Menu (phones): opens from the thumb, closes on a link, Escape or the close button
  var menu = document.querySelector('[data-menu]'), openBtn = document.querySelector('[data-menu-open]');
  if (menu && openBtn) {
    var hideTimer = null, lastFocus = null;
    var open = function () {
      clearTimeout(hideTimer); lastFocus = document.activeElement;
      var b = openBtn.getBoundingClientRect();
      menu.style.setProperty('--ox', (b.left + b.width / 2).toFixed(0) + 'px'); menu.style.setProperty('--oy', (b.top + b.height / 2).toFixed(0) + 'px');
      menu.hidden = false; void menu.offsetWidth; menu.classList.add('open'); body.classList.add('menu-open');
      openBtn.setAttribute('aria-expanded', 'true');
      var first = menu.querySelector('a, button'); if (first) first.focus();
    };
    var close = function () {
      menu.classList.remove('open'); body.classList.remove('menu-open'); openBtn.setAttribute('aria-expanded', 'false');
      hideTimer = setTimeout(function () { menu.hidden = true; }, reduce ? 0 : 660);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    };
    openBtn.addEventListener('click', open);
    each('[data-menu-close]', function (b) { b.addEventListener('click', close); });
    each('.menu-links a', function (a) { a.addEventListener('click', close); }, menu);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) close(); });
  }

  // ---- Reveals: blocks below the screen wait until they are seen (wiped photographs are watched through their parent)
  var proxied = [];
  if ('IntersectionObserver' in window && !reduce && !navigator.webdriver) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        proxied.forEach(function (p) { if (p[0] === en.target) p[1].forEach(function (w) { w.classList.add('in'); }); });
        io.unobserve(en.target);
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    var vh = window.innerHeight;
    each('.reveal, .split:not(.split-now), .wipe', function (el) {
      if (el.getBoundingClientRect().top <= vh) { el.classList.add('in'); return; }
      if (el.classList.contains('reveal')) el.classList.add('reveal-pre');
      if (el.classList.contains('wipe') && el.parentElement) {
        var host = el.parentElement, rec = proxied.filter(function (p) { return p[0] === host; })[0];
        if (rec) rec[1].push(el); else { proxied.push([host, [el]]); io.observe(host); }
      } else io.observe(el);
    });
  } else {
    each('.reveal, .split:not(.split-now), .wipe', function (el) { el.classList.add('in'); });
  }
  each('.stagger', function (g) { Array.prototype.forEach.call(g.children, function (c, i) { if (!c.style.getPropertyValue('--i')) c.style.setProperty('--i', String(Math.min(i, 12))); }); });

  // ---- Counters count up once when seen
  var count = function (el) {
    var end = parseInt(el.dataset.count, 10) || 0, t0 = null, dur = 1400;
    var step = function (t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); el.textContent = String(Math.round(end * (1 - Math.pow(1 - p, 4)))); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };
  if (!reduce && 'IntersectionObserver' in window) {
    var cio = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { count(x.target); cio.unobserve(x.target); } }); }, { threshold: .4 });
    each('[data-count]', function (el) { cio.observe(el); });
  }

  // ---- The hero leans toward the pointer (computers); gold buttons lean too
  var hero = document.querySelector('[data-hero]');
  if (hero && fine && !reduce) {
    var media = hero.querySelector('.hero-img'), copy = hero.querySelector('[data-hero-parallax]');
    hero.addEventListener('pointermove', function (e) {
      var r = hero.getBoundingClientRect(), hx = (e.clientX - r.left) / r.width - .5, hy = (e.clientY - r.top) / r.height - .5;
      if (media) media.style.transform = 'translate3d(' + (hx * -14).toFixed(1) + 'px,' + (hy * -10).toFixed(1) + 'px,0) scale(1.06)';
      if (copy) copy.style.transform = 'translate3d(' + (hx * 8).toFixed(1) + 'px,' + (hy * 6).toFixed(1) + 'px,0)';
    });
    hero.addEventListener('pointerleave', function () { if (media) media.style.transform = ''; if (copy) copy.style.transform = ''; });
    each('[data-magnetic]', function (m) {
      m.addEventListener('pointermove', function (e) { var q = m.getBoundingClientRect(); m.style.transform = 'translate(' + (((e.clientX - q.left) / q.width - .5) * 10).toFixed(1) + 'px,' + (((e.clientY - q.top) / q.height - .5) * 8).toFixed(1) + 'px)'; });
      m.addEventListener('pointerleave', function () { m.style.transform = ''; });
    });
  }

  // ---- The gallery viewer: one photograph at a time, arrows, swipe, Escape
  var lb = null, items = [], cur = 0;
  var viewer = function () {
    if (lb) return lb;
    var box = document.createElement('div'); box.className = 'lb'; box.hidden = true;
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', rtl ? 'عارض الصور' : 'Photo viewer');
    box.innerHTML = '<div class="lb-bar"><span class="lb-count" aria-live="polite"></span><button type="button" class="lb-btn lb-close" aria-label="' + (rtl ? 'إغلاق' : 'Close') + '">×</button></div>'
      + '<div class="lb-stage"><img alt=""><button type="button" class="lb-btn lb-prev" aria-label="' + (rtl ? 'السابق' : 'Previous') + '"><span aria-hidden="true"></span></button><button type="button" class="lb-btn lb-next" aria-label="' + (rtl ? 'التالي' : 'Next') + '"><span aria-hidden="true"></span></button></div><p class="lb-cap"></p>';
    document.body.appendChild(box);
    var img = box.querySelector('img'), cap = box.querySelector('.lb-cap'), n = box.querySelector('.lb-count');
    var show = function (i) { cur = (i + items.length) % items.length; img.src = items[cur].dataset.lb; cap.textContent = items[cur].dataset.caption || ''; n.textContent = (cur + 1) + ' / ' + items.length; };
    var closeV = function () { box.hidden = true; body.style.overflow = ''; };
    box.querySelector('.lb-close').addEventListener('click', closeV);
    box.querySelector('.lb-prev').addEventListener('click', function () { show(cur + (rtl ? 1 : -1)); });
    box.querySelector('.lb-next').addEventListener('click', function () { show(cur + (rtl ? -1 : 1)); });
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('lb-stage')) closeV(); });
    document.addEventListener('keydown', function (e) { if (box.hidden) return; if (e.key === 'Escape') closeV(); if (e.key === 'ArrowRight') show(cur + 1); if (e.key === 'ArrowLeft') show(cur - 1); });
    var x0 = 0; box.addEventListener('pointerdown', function (e) { x0 = e.clientX; }); box.addEventListener('pointerup', function (e) { var dx = e.clientX - x0; if (Math.abs(dx) > 40) show(cur + (dx < 0 ? 1 : -1)); });
    lb = { open: function (list, i) { items = list; box.hidden = false; body.style.overflow = 'hidden'; show(i); box.querySelector('.lb-close').focus(); } };
    return lb;
  };
  each('[data-lb-group]', function (g) {
    var links = Array.prototype.slice.call(g.querySelectorAll('a[data-lb]'));
    links.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); viewer().open(links, i); }); });
  });

  // ---- The swipe row's counter and line (phones)
  if (coarse) {
    document.body.addEventListener('touchstart', function () {}, { passive: true });
    each('.sheet', function (row) {
      var foot = row.nextElementSibling && row.nextElementSibling.classList.contains('row-foot') ? row.nextElementSibling : null;
      var cards = Array.prototype.slice.call(row.children);
      if (cards.length < 2 || !('IntersectionObserver' in window)) return;
      var n = foot && foot.querySelector('.row-n b'), bar = foot && foot.querySelector('.row-progress i');
      var rio = new IntersectionObserver(function (en) {
        en.forEach(function (x) { if (!x.isIntersecting) return; var i = cards.indexOf(x.target); if (n) n.textContent = String(i + 1); if (bar) bar.style.transform = 'scaleX(' + ((i + 1) / cards.length).toFixed(3) + ')'; });
      }, { root: row, threshold: .75 });
      cards.forEach(function (c) { rio.observe(c); });
    });
  }

  // ---- The chapter line: the current chapter's name in the nav and the dock
  var chapters = Array.prototype.slice.call(document.querySelectorAll('main section[id]'));
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.main-nav a'));
  var dock = document.querySelector('[data-dock]'), dockChapter = dock && dock.querySelector('[data-dock-chapter]');
  if (chapters.length && 'IntersectionObserver' in window) {
    var mark = function (sec) {
      navLinks.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + sec.id); });
      var ix = sec.querySelector('.index');
      if (dockChapter && ix) dockChapter.textContent = ix.textContent.replace(/\s+/g, ' ').trim();
    };
    var sio = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) mark(x.target); }); }, { rootMargin: '-45% 0px -45% 0px' });
    chapters.forEach(function (s) { sio.observe(s); });
  }
  // ---- The dock (phones): rises once the hero has gone, sinks over the contact and the footer
  if (dock && 'IntersectionObserver' in window && window.matchMedia('(max-width: 899px)').matches) {
    dock.hidden = false;
    var heroGone = false, overEnd = false, seen = [];
    var show = function () { dock.classList.toggle('on', heroGone && !overEnd); };
    if (hero) new IntersectionObserver(function (en) { heroGone = !en[0].isIntersecting && en[0].boundingClientRect.bottom < 0; show(); }, { threshold: 0 }).observe(hero);
    var eio = new IntersectionObserver(function (en) { en.forEach(function (x) { var i = seen.indexOf(x.target); if (x.isIntersecting && i < 0) seen.push(x.target); else if (!x.isIntersecting && i >= 0) seen.splice(i, 1); }); overEnd = seen.length > 0; show(); }, { threshold: .05 });
    each('#contact, .foot', function (el) { eio.observe(el); });
  }
})();
