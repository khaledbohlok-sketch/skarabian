/* SK Arabians public website (version 3). Sections: setup · preloader · header and menu · hero slideshow · filter tabs ·
   counters · headlines, reveals and images · motion (parallax, pointer, cursor, drag rows) · scroll scenes (GSAP,
   homepage) · photo viewer · profile gallery · sharing · local time.
   Everything works without JavaScript (the tabs are links, photos link to the full image, sections are visible), and
   with reduced motion nothing moves except plain fades. Animations use transform, clip-path and opacity only. */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var coarse = window.matchMedia('(hover: none), (pointer: coarse)').matches;
  var rtl = root.dir === 'rtl';
  var cfg = {};
  try { cfg = JSON.parse((document.getElementById('sk-site-cfg') || {}).textContent || '{}'); } catch (e) {}
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';
  var each = function (sel, fn, scope) { Array.prototype.forEach.call((scope || document).querySelectorAll(sel), fn); };
  function visible(list) { return Array.prototype.filter.call(list, function (el) { return el.offsetWidth || el.offsetHeight || el.getClientRects().length; }); }
  function trap(box, e) {
    if (e.key !== 'Tab') return;
    var f = visible(box.querySelectorAll(FOCUSABLE)); if (!f.length) return;
    e.preventDefault();
    var i = f.indexOf(document.activeElement);
    if (i < 0) { (e.shiftKey ? f[f.length - 1] : f[0]).focus(); return; }
    f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
  }
  function lock(on, cls) {
    var b = document.body;
    if (on) { var w = window.innerWidth - root.clientWidth; if (w > 0) b.style.paddingInlineEnd = w + 'px'; b.classList.add(cls); }
    else { b.classList.remove(cls); if (!b.classList.contains('menu-open') && !b.classList.contains('lb-open')) b.style.paddingInlineEnd = ''; }
  }
  var onFrame = function (fn) { var queued = false; return function () { if (!queued) { queued = true; requestAnimationFrame(function () { queued = false; fn(); }); } }; };

  // ---- First-visit preloader (site-head.js decided it plays: html.intro): the mark, the name, a gold line, then the
  // curtain lifts. ~1.4s, or at once on a click or any key.
  var pre = document.querySelector('[data-preloader]');
  if (pre && root.classList.contains('intro')) {
    var done = false;
    var finish = function () {
      if (done) return; done = true;
      root.classList.add('intro-out');
      setTimeout(function () { pre.hidden = true; }, 950);
      document.removeEventListener('keydown', finish);
    };
    setTimeout(finish, 1400);
    pre.addEventListener('click', finish);
    document.addEventListener('keydown', finish);
  }

  // ---- Header: frosted once the page scrolls; hides on the way down, returns on the way up
  var header = document.querySelector('[data-header]');
  if (header) {
    var lastY = window.scrollY;
    var onScroll = function () {
      var y = window.scrollY, dy = y - lastY;
      header.classList.toggle('scrolled', y > 24);
      if (y < 160 || document.body.classList.contains('menu-open')) header.classList.remove('away');
      else if (Math.abs(dy) > 6) header.classList.toggle('away', dy > 0);
      lastY = y;
    };
    window.addEventListener('scroll', onFrame(onScroll), { passive: true });
    onScroll();
  }

  // ---- Phone / tablet menu
  var openBtn = document.querySelector('[data-menu-open]'), panel = document.querySelector('[data-menu-panel]'), overlay = document.querySelector('[data-menu-overlay]');
  if (openBtn && panel && overlay) {
    var closeBtn = panel.querySelector('[data-menu-close]'), hideTimer = null;
    var setOpen = function (on, back) {
      clearTimeout(hideTimer);
      openBtn.setAttribute('aria-expanded', String(on));
      if (on) {
        var b = openBtn.getBoundingClientRect(); // the room opens from the thumb
        panel.style.setProperty('--ox', (b.left + b.width / 2).toFixed(0) + 'px'); panel.style.setProperty('--oy', (b.top + b.height / 2).toFixed(0) + 'px');
        panel.hidden = false; overlay.hidden = false;
        void panel.offsetWidth;
        panel.classList.add('open'); overlay.classList.add('open');
        lock(true, 'menu-open');
        (closeBtn || panel).focus();
      } else {
        panel.classList.remove('open'); overlay.classList.remove('open');
        lock(false, 'menu-open');
        hideTimer = setTimeout(function () { panel.hidden = true; overlay.hidden = true; }, reduce ? 0 : 460);
        if (back !== false) openBtn.focus();
      }
    };
    openBtn.addEventListener('click', function () { setOpen(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    overlay.addEventListener('click', function () { setOpen(false); });
    panel.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false, false); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); setOpen(false); } else trap(panel, e); });
    window.matchMedia('(min-width: 1200px)').addEventListener('change', function (m) { if (m.matches && !panel.hidden) setOpen(false, false); });
  }

  // ---- Hero slideshow: the first photo is in the page; the others load just before they are shown. The caption
  // names the horse on screen and links to its page.
  var slides = document.querySelectorAll('.hero-slide');
  if (slides.length > 1 && !reduce) {
    var cur = 0, caption = document.querySelector('[data-hero-caption]');
    var dots = document.querySelectorAll('.hero-dots i');
    var load = function (n) { if (n.dataset.src && !n.getAttribute('src')) n.src = n.dataset.src; };
    var label = function (s) {
      if (!caption || !s.dataset.href) return;
      caption.href = s.dataset.href;
      caption.querySelector('.hero-caption-name').textContent = s.dataset.name || '';
      caption.querySelector('.hero-caption-cat').textContent = s.dataset.cat || '';
      Array.prototype.forEach.call(dots, function (d, i) { d.classList.toggle('on', i === cur); });
      caption.classList.remove('swap'); void caption.offsetWidth; caption.classList.add('swap');
    };
    load(slides[1]);
    var progress = document.querySelector('[data-hero-progress]'), timer = null;
    var run = function () { if (!progress) return; progress.classList.remove('run'); void progress.offsetWidth; progress.classList.add('run'); };
    var go = function (dir) {
      var next = (cur + dir + slides.length) % slides.length;
      load(slides[next]); load(slides[(next + 1) % slides.length]);
      slides[cur].classList.remove('on'); slides[next].classList.add('on'); cur = next;
      label(slides[cur]); run(); arm();
    };
    var arm = function () { clearTimeout(timer); timer = setTimeout(function () { if (document.hidden) { arm(); return; } go(1); }, 7000); };
    run(); arm();
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { run(); arm(); } else if (progress) progress.classList.remove('run'); });
    // a swipe on the photograph brings the next horse (phones); a short press follows the horse
    var media = document.querySelector('.hero-media'), x0 = 0, y0 = 0, t0 = 0;
    if (media) {
      media.addEventListener('pointerdown', function (e) { if (e.pointerType === 'mouse') return; x0 = e.clientX; y0 = e.clientY; t0 = Date.now(); });
      media.addEventListener('pointerup', function (e) {
        if (e.pointerType === 'mouse') return;
        var dx = e.clientX - x0, dy = e.clientY - y0;
        if (Math.abs(dx) > 40 && Math.abs(dx) > 1.5 * Math.abs(dy)) go((dx < 0) !== rtl ? 1 : -1);
        else if (Math.abs(dx) < 8 && Math.abs(dy) < 8 && Date.now() - t0 < 400 && slides[cur].dataset.href) location.href = slides[cur].dataset.href;
      });
    }
  }

  // ---- The parade (phones): the centred card of a swipe row is marked, the counter and the gold line follow
  if (coarse) {
    document.body.addEventListener('touchstart', function () {}, { passive: true }); // iOS fires :active only with a touch listener
    each('[data-parade]', function (row) {
      var foot = row.nextElementSibling && row.nextElementSibling.classList.contains('row-foot') ? row.nextElementSibling : null;
      var cards = Array.prototype.slice.call(row.querySelectorAll('.hcard'));
      if (cards.length < 2 || !('IntersectionObserver' in window)) return;
      var n = foot && foot.querySelector('.row-n b'), bar = foot && foot.querySelector('.row-progress i');
      var io = new IntersectionObserver(function (en) {
        en.forEach(function (x) {
          if (!x.isIntersecting) { x.target.classList.remove('is-centre'); return; }
          cards.forEach(function (c) { c.classList.toggle('is-centre', c === x.target); });
          var i = cards.indexOf(x.target); if (n) n.textContent = String(i + 1);
          if (bar) bar.style.transform = 'scaleX(' + ((i + 1) / cards.length).toFixed(3) + ')';
          row.closest('.group, .chapter') && row.closest('.group, .chapter').classList.add('moved');
        });
      }, { root: row, threshold: .75 });
      cards.forEach(function (c) { io.observe(c); });
    });
  }

  // ---- The dock (phones): rises when the title band has gone, sinks over the form, the closing band and the footer
  var dock = document.querySelector('[data-dock]');
  if (dock && 'IntersectionObserver' in window && window.matchMedia('(max-width: 899px)').matches) {
    dock.hidden = false;
    var dockChapter = dock.querySelector('[data-dock-chapter]'); if (dockChapter) dockChapter.textContent = dock.dataset.dockTitle || '';
    var top = document.querySelector('.hero, .page-hero'), topGone = !top, overEnd = false;
    var show = function () { dock.classList.toggle('on', topGone && !overEnd); };
    if (top) new IntersectionObserver(function (en) { topGone = !en[0].isIntersecting && en[0].boundingClientRect.bottom < 0; show(); }, { threshold: 0 }).observe(top);
    var dockSeen = [], eio = new IntersectionObserver(function (en) {
      en.forEach(function (x) { var i = dockSeen.indexOf(x.target); if (x.isIntersecting && i < 0) dockSeen.push(x.target); else if (!x.isIntersecting && i >= 0) dockSeen.splice(i, 1); });
      overEnd = dockSeen.length > 0; show();
    }, { threshold: .05 });
    each('.contact-form, .cta-band, .site-footer, #inquire', function (el) { eio.observe(el); });
  }

  // ---- Filter tabs (horse categories, seasons): real links; in place with JavaScript. A hairline slides under the
  // chosen tab and the cards glide to their new places (FLIP).
  each('[data-cat-tabs]', function (tabs) {
    var scope = tabs.parentElement, param = tabs.dataset.param || 'category', def = tabs.dataset.default || 'all';
    var pill = document.createElement('span'); pill.className = 'tab-pill'; pill.setAttribute('aria-hidden', 'true');
    tabs.prepend(pill); tabs.classList.add('has-pill');
    var place = function () {
      var on = tabs.querySelector('.tab.on'); if (!on) return;
      pill.style.width = on.offsetWidth + 'px';
      pill.style.transform = 'translate(' + on.offsetLeft + 'px,' + (on.offsetTop + on.offsetHeight - tabs.clientHeight + tabs.offsetHeight) + 'px)';
    };
    var placeAt = function () {
      var on = tabs.querySelector('.tab.on'); if (!on) return;
      pill.style.width = on.offsetWidth + 'px';
      pill.style.transform = 'translateX(' + on.offsetLeft + 'px) translateY(' + (on.offsetTop + on.offsetHeight - (tabs.offsetHeight - 1)) + 'px)';
    };
    place = placeAt;
    place(); window.addEventListener('resize', place);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
    tabs.addEventListener('click', function (e) {
      var t = e.target.closest('[data-cat]'); if (!t) return;
      e.preventDefault();
      var cat = t.dataset.cat;
      var cards = !reduce ? Array.prototype.slice.call(scope.querySelectorAll('.hcard, .result-row')) : [];
      var first = new Map(); cards.forEach(function (c) { if (c.offsetParent) first.set(c, c.getBoundingClientRect()); });
      each('[data-cat]', function (x) { var on = x === t; x.classList.toggle('on', on); x.setAttribute('aria-selected', String(on)); }, tabs);
      each('[data-group]', function (g) { g.hidden = cat !== 'all' && g.dataset.group !== cat; }, scope);
      var wrap = scope.querySelector('.horse-groups');
      if (wrap) wrap.classList.toggle('filtered', cat !== 'all');
      if (history.replaceState && (tabs.dataset.param || /\/horses\/?$/.test(location.pathname))) history.replaceState(null, '', cat === def ? location.pathname : '?' + param + '=' + cat);
      place();
      cards.forEach(function (c, i) {
        if (!c.offsetParent || !c.animate) return;
        var a = first.get(c), b = c.getBoundingClientRect();
        if (a) c.animate([{ transform: 'translate(' + (a.left - b.left) + 'px,' + (a.top - b.top) + 'px)' }, { transform: 'none' }], { duration: 560, easing: 'cubic-bezier(.16,1,.3,1)' });
        else c.animate([{ opacity: 0, transform: 'translateY(18px)' }, { opacity: 1, transform: 'none' }], { duration: 560, delay: Math.min(i, 8) * 40, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'backwards' });
      });
    });
  });

  // ---- Counters count up once (eased): under the hero and in the title bands on load; in Achievements when seen
  var count = function (el) {
    var isFloat = el.dataset.countFloat !== undefined, end = isFloat ? parseFloat(el.dataset.countFloat) || 0 : parseInt(el.dataset.count, 10) || 0, t0 = null, dur = 1800;
    var dec = isFloat ? ((el.dataset.countFloat.split('.')[1] || '').length) : 0;
    var step = function (t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur), v = end * (1 - Math.pow(1 - p, 4)); el.textContent = isFloat ? v.toFixed(dec) : Math.round(v).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };
  if (!reduce) {
    each('.hero-ledger [data-count], .tally [data-count]', function (el) { setTimeout(function () { count(el); }, root.classList.contains('intro') ? 1500 : 400); });
    var later = document.querySelectorAll('.counter [data-count], .cabinet [data-count], [data-count-float]');
    if (later.length && 'IntersectionObserver' in window) {
      var cio = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { count(x.target); cio.unobserve(x.target); } }); }, { threshold: 0.6 });
      later.forEach(function (el) { cio.observe(el); });
    }
  }
  var wa = document.querySelector('.wa-float'), ledger = document.querySelector('.hero-ledger');
  if (wa && ledger && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { wa.classList.toggle('over-stats', en[0].isIntersecting); }).observe(ledger);
  }

  // ---- Headlines rise line by line: the words of a masked headline that sit on one line share their line's number
  var lines = function () {
    each('.split', function (h) {
      var top = null, line = -1;
      each('.w > span', function (s) {
        var t = s.parentNode.offsetTop;
        if (top === null || Math.abs(t - top) > 4) { top = t; line++; }
        s.style.setProperty('--i', String(line));
      }, h);
    });
  };
  lines();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(lines);

  // ---- Reveal: blocks below the screen at load fade up once in view; a group's children follow one another (--i);
  // masked headlines rise; framed photos are unveiled. Nothing on screen ever blinks; never with reduced motion; never
  // for automated browsers (page checks, screenshots).
  each('.stagger', function (g) {
    Array.prototype.forEach.call(g.children, function (c, i) { if (!c.style.getPropertyValue('--i')) c.style.setProperty('--i', String(Math.min(i, 12))); });
  });
  if ('IntersectionObserver' in window && !reduce && !navigator.webdriver) {
    var proxied = []; // [watched element, the wiped elements it stands for]
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
        // clip-path: inset(0 0 100%) leaves the element with no visible box, so the observer watches its parent
        var host = el.parentElement, rec = proxied.filter(function (p) { return p[0] === host; })[0];
        if (rec) rec[1].push(el); else { proxied.push([host, [el]]); io.observe(host); }
      } else io.observe(el);
    });
  } else {
    each('.reveal, .split:not(.split-now), .wipe', function (el) { el.classList.add('in'); });
  }

  // ---- Motion (not with reduced motion)
  if (!reduce && 'IntersectionObserver' in window) {
    var par = document.querySelectorAll('[data-parallax]'), seen = new Set();
    if (par.length) {
      var pio = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) seen.add(x.target); else seen.delete(x.target); }); });
      Array.prototype.forEach.call(par, function (p) { pio.observe(p); });
      var paint = function () {
        var h = window.innerHeight;
        seen.forEach(function (p) {
          var r = p.getBoundingClientRect(), k = parseFloat(p.dataset.parallax) || 0;
          p.style.transform = 'translate3d(0,' + ((r.top + r.height / 2 - h / 2) * k).toFixed(1) + 'px,0)';
        });
      };
      window.addEventListener('scroll', onFrame(paint), { passive: true });
      paint();
    }
  }

  // rows that scroll sideways (the foals, the horse rows on a phone) can be dragged with a mouse; a drag is not a click
  each('.strip, .rows-on-phone .hgrid', function (s) {
    var x0 = 0, left0 = 0, moved = false, down = false;
    s.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse' || e.button !== 0 || s.scrollWidth <= s.clientWidth) return; down = true; moved = false; x0 = e.clientX; left0 = s.scrollLeft; });
    s.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - x0;
      if (!moved && Math.abs(dx) > 6) { moved = true; s.classList.add('dragging'); s.setPointerCapture(e.pointerId); }
      if (moved) s.scrollLeft = left0 - dx;
    });
    var up = function () { down = false; if (moved) setTimeout(function () { s.classList.remove('dragging'); }, 0); };
    s.addEventListener('pointerup', up); s.addEventListener('pointercancel', up);
    s.addEventListener('click', function (e) { if (moved) { e.preventDefault(); moved = false; } }, true);
  });

  // pointer effects: computers with a mouse only — the hero's photograph and copy lean with the pointer, horse cards
  // tilt toward it and carry a soft light (--px/--py/--rx/--ry), gold buttons lean (--mx/--my), a ring trails it
  var hero = document.querySelector('[data-hero]');
  if (!reduce && fine) {
    var heroMedia = hero && hero.querySelector('.hero-media'), heroCopy = hero && hero.querySelector('[data-hero-parallax]');
    if (heroMedia) {
      var hx = 0, hy = 0, lean = onFrame(function () {
        heroMedia.style.transform = 'translate3d(' + (hx * -14).toFixed(1) + 'px,' + (hy * -10).toFixed(1) + 'px,0) scale(1.04)';
        if (heroCopy) heroCopy.style.transform = 'translate3d(' + (hx * 8).toFixed(1) + 'px,' + (hy * 6).toFixed(1) + 'px,0)';
      });
      hero.addEventListener('pointermove', function (e) { var r = hero.getBoundingClientRect(); hx = (e.clientX - r.left) / r.width - .5; hy = (e.clientY - r.top) / r.height - .5; lean(); });
      hero.addEventListener('pointerleave', function () { hx = hy = 0; lean(); });
    }
    document.addEventListener('pointermove', function (e) {
      var c = e.target.closest && e.target.closest('.hcard');
      if (c) {
        var r = c.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
        c.style.setProperty('--px', (px * 100).toFixed(1) + '%'); c.style.setProperty('--py', (py * 100).toFixed(1) + '%');
        c.style.setProperty('--ry', ((px - .5) * 7).toFixed(2) + 'deg'); c.style.setProperty('--rx', ((.5 - py) * 7).toFixed(2) + 'deg');
      }
      var m = e.target.closest && e.target.closest('.btn-gold, [data-magnetic]');
      if (m) {
        var q = m.getBoundingClientRect();
        m.style.setProperty('--mx', (((e.clientX - q.left) / q.width - .5) * 10).toFixed(1) + 'px');
        m.style.setProperty('--my', (((e.clientY - q.top) / q.height - .5) * 8).toFixed(1) + 'px');
      }
    }, { passive: true });
    document.addEventListener('pointerout', function (e) {
      var c = e.target.closest && e.target.closest('.hcard, .btn-gold, [data-magnetic]');
      if (c && !c.contains(e.relatedTarget)) ['--px', '--py', '--rx', '--ry', '--mx', '--my'].forEach(function (p) { c.style.removeProperty(p); });
    });
    // gold dust drifting up through the hero's light (a small canvas; it rests when the hero leaves the screen or the
    // tab is hidden)
    var cv = hero && hero.querySelector('[data-dust]');
    if (cv && cv.getContext && 'IntersectionObserver' in window) {
      var ctx = cv.getContext('2d'), motes = [], run = false, raf = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
      var gold = getComputedStyle(root).getPropertyValue('--c-gold-soft').trim() || '#e3cfa8';
      var size = function () {
        cv.width = cv.offsetWidth * dpr; cv.height = cv.offsetHeight * dpr;
        motes = []; var n = Math.round(cv.offsetWidth / 22);
        for (var i = 0; i < n; i++) motes.push({ x: Math.random() * cv.width, y: Math.random() * cv.height, r: (Math.random() * 1.5 + .4) * dpr, v: (Math.random() * .28 + .08) * dpr, a: Math.random() * .55 + .15, w: Math.random() * 6.28 });
      };
      var frame = function () {
        ctx.clearRect(0, 0, cv.width, cv.height); ctx.fillStyle = gold;
        motes.forEach(function (m) {
          m.y -= m.v; m.w += .01; m.x += Math.sin(m.w) * .22 * dpr;
          if (m.y < -4) { m.y = cv.height + 4; m.x = Math.random() * cv.width; }
          ctx.globalAlpha = m.a * (.6 + .4 * Math.sin(m.w * 3)); ctx.beginPath(); ctx.arc(m.x, m.y, m.r, 0, 6.283); ctx.fill();
        });
        raf = run ? requestAnimationFrame(frame) : 0;
      };
      var go = function (on) { run = on && !document.hidden; if (run && !raf) raf = requestAnimationFrame(frame); };
      size(); window.addEventListener('resize', size);
      new IntersectionObserver(function (en) { go(en[0].isIntersecting); }).observe(hero);
      document.addEventListener('visibilitychange', function () { go(!document.hidden && hero.getBoundingClientRect().bottom > 0); });
    }
    var ring = document.createElement('div'); ring.className = 'cursor'; ring.setAttribute('aria-hidden', 'true'); document.body.appendChild(ring);
    var cx = -100, cy = -100, rx = -100, ry = -100, running = false;
    var tick = function () {
      rx += (cx - rx) * .2; ry += (cy - ry) * .2;
      ring.style.transform = 'translate3d(' + rx.toFixed(1) + 'px,' + ry.toFixed(1) + 'px,0)';
      if (Math.abs(cx - rx) > .3 || Math.abs(cy - ry) > .3) requestAnimationFrame(tick); else running = false;
    };
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') return;
      cx = e.clientX; cy = e.clientY; ring.classList.add('on');
      ring.classList.toggle('hot', !!(e.target.closest && e.target.closest('a, button, [role="tab"], label')));
      if (!running) { running = true; requestAnimationFrame(tick); }
    }, { passive: true });
    document.addEventListener('pointerdown', function () { ring.classList.add('down'); });
    document.addEventListener('pointerup', function () { ring.classList.remove('down'); });
    root.addEventListener('pointerleave', function () { ring.classList.remove('on'); });
    // the ring says what is under it: a horse's name and "View", "Open" over a photograph, "Drag" over a row
    var cLabel = document.createElement('span'); cLabel.className = 'cursor-label'; ring.appendChild(cLabel);
    document.addEventListener('pointerover', function (e) {
      var t = e.target.closest && e.target.closest('.hcard, [data-cursor]');
      if (!t) { ring.classList.remove('say'); return; }
      var text = '';
      if (t.classList.contains('hcard')) { var nm = t.querySelector('.hcard-name'); text = (nm ? nm.textContent.trim() + ' · ' : '') + (cfg.view || 'View'); }
      else text = cfg[t.dataset.cursor] || t.dataset.cursor;
      cLabel.textContent = text; ring.classList.add('say');
    });
  }

  // ---- Scroll scenes (homepage, GSAP + ScrollTrigger, self-hosted; computers, motion allowed). Without them the page
  // is complete: the gallery stays a grid and the threads are simply drawn.
  var gsap = window.gsap, ST = window.ScrollTrigger;
  var home = document.querySelector('.home');
  if (home && gsap && ST && !reduce && !navigator.webdriver) {
    gsap.registerPlugin(ST);
    // the hero sinks under the ledger: the photograph grows and darkens, the copy drifts up and fades
    var sink = home.querySelector('[data-hero-sink]');
    if (sink && window.matchMedia('(min-width: 900px)').matches) {
      gsap.timeline({ scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: .6 } })
        .to(sink, { opacity: .72, ease: 'none' }, 0)
        .to('.hero-copy', { yPercent: -24, opacity: 0, ease: 'none' }, 0)
        .to('.hero-foot', { opacity: 0, ease: 'none' }, 0);
    }
    each('.home > .chapter', function (s) {
      var t = document.createElement('span'); t.className = 'thread'; t.setAttribute('aria-hidden', 'true'); s.prepend(t);
      gsap.fromTo(t, { scaleY: 0 }, { scaleY: 1, ease: 'none', scrollTrigger: { trigger: s, start: 'top 95%', end: 'top 35%', scrub: .6 } });
    });
    // the khatam watermarks turn with the scroll instead of on their own clock; the gate rises a little slower than the photograph
    each('[data-khatam]', function (el) {
      el.style.animation = 'none';
      gsap.fromTo(el, { rotation: rtl ? 6 : -6 }, { rotation: rtl ? -6 : 6, ease: 'none', scrollTrigger: { trigger: el.parentNode, start: 'top bottom', end: 'bottom top', scrub: 1 } });
    });
    var wall = home.querySelector('[data-wall]');
    if (wall && window.matchMedia('(min-width: 1200px)').matches) {
      var wImg = wall.querySelector('img'), wGhost = home.querySelector('.feature-ghost');
      if (wImg) gsap.to(wImg, { yPercent: -9, ease: 'none', scrollTrigger: { trigger: '.feature', start: 'top bottom', end: 'bottom top', scrub: true } });
      if (wGhost) gsap.to(wGhost, { xPercent: rtl ? -5 : 5, ease: 'none', scrollTrigger: { trigger: '.feature', start: 'top bottom', end: 'bottom top', scrub: true } });
    }
    var arch = home.querySelector('.hero-arch');
    if (arch && window.matchMedia('(min-width: 1024px)').matches) {
      gsap.to(arch, { yPercent: -8, opacity: .25, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: .6 } });
    }
    var film = home.querySelector('[data-film]');
    var strip = film && film.querySelector('.gallery');
    if (strip && fine && strip.children.length >= 4) {
      var mm = gsap.matchMedia();
      mm.add('(min-width: 1024px)', function () {
        film.classList.add('film');
        each('a[data-lb] img', function (img) { var a = img.closest('a'); if (a && a.dataset.lb) img.src = a.dataset.lb; }, strip);
        var dist = function () { return Math.max(0, strip.scrollWidth - film.querySelector('.container').clientWidth); };
        var tw = gsap.to(strip, { x: function () { return (rtl ? 1 : -1) * dist(); }, ease: 'none',
          scrollTrigger: { trigger: film, start: 'top top', end: function () { return '+=' + dist(); }, pin: true, scrub: .8, invalidateOnRefresh: true, anticipatePin: 1 } });
        return function () { tw.scrollTrigger && tw.scrollTrigger.kill(); tw.kill(); gsap.set(strip, { clearProps: 'transform' }); film.classList.remove('film'); };
      });
    }
    window.addEventListener('load', function () { ST.refresh(); });
  }

  // ---- Photo viewer: one viewer for the gallery, a horse's photos and a news photo
  var lb = null;
  function viewer() {
    if (lb) return lb;
    var box = document.createElement('div');
    box.className = 'lb'; box.hidden = true;
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', cfg.viewer || 'Photo viewer');
    box.innerHTML = '<div class="lb-bar"><span class="lb-count" aria-live="polite"></span><button type="button" class="lb-btn lb-close"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>'
      + '<div class="lb-stage"><img alt=""><button type="button" class="lb-btn lb-prev"><span aria-hidden="true"></span></button><button type="button" class="lb-btn lb-next"><span aria-hidden="true"></span></button></div>'
      + '<p class="lb-cap"></p>';
    document.body.appendChild(box);
    var img = box.querySelector('img'), cap = box.querySelector('.lb-cap'), count = box.querySelector('.lb-count');
    var bClose = box.querySelector('.lb-close'), bPrev = box.querySelector('.lb-prev'), bNext = box.querySelector('.lb-next');
    bClose.setAttribute('aria-label', cfg.close || 'Close');
    bPrev.setAttribute('aria-label', cfg.prev || 'Previous'); bNext.setAttribute('aria-label', cfg.next || 'Next');
    bPrev.firstChild.textContent = rtl ? '›' : '‹'; bNext.firstChild.textContent = rtl ? '‹' : '›';
    var items = [], at = 0, opener = null, timer = null;
    function show(i) {
      at = (i + items.length) % items.length;
      var it = items[at];
      img.src = it.src; img.alt = it.caption || '';
      cap.textContent = it.caption || '';
      if (it.link) { cap.textContent = ''; var a = document.createElement('a'); a.href = it.link; a.textContent = (it.caption || '') + ' ' + (rtl ? '←' : '→'); cap.appendChild(a); }
      count.textContent = items.length > 1 ? (cfg.of || ':n / :total').replace(':n', at + 1).replace(':total', items.length) : '';
      bPrev.hidden = bNext.hidden = items.length < 2;
      if (items.length > 1) { var pre = new Image(); pre.src = items[(at + 1) % items.length].src; }
    }
    function close() {
      box.classList.remove('open'); lock(false, 'lb-open');
      clearTimeout(timer); timer = setTimeout(function () { box.hidden = true; img.removeAttribute('src'); }, reduce ? 0 : 210);
      if (opener) opener.focus();
    }
    bClose.addEventListener('click', close);
    bPrev.addEventListener('click', function () { show(at - 1); });
    bNext.addEventListener('click', function () { show(at + 1); });
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('lb-stage')) close(); });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); close(); }
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { if (items.length > 1) { e.preventDefault(); show(at + ((e.key === 'ArrowRight') !== rtl ? 1 : -1)); } }
      else trap(box, e);
    });
    var x0 = null;
    box.addEventListener('touchstart', function (e) { x0 = e.touches.length === 1 ? e.touches[0].clientX : null; }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (x0 === null || items.length < 2) return;
      var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 45) show(at + ((dx < 0) !== rtl ? 1 : -1));
    });
    lb = {
      open: function (list, i, from) {
        clearTimeout(timer);
        items = list; opener = from || document.activeElement;
        box.hidden = false; void box.offsetWidth; box.classList.add('open'); lock(true, 'lb-open');
        show(i || 0);
        bClose.focus();
      }
    };
    return lb;
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-lb]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;
    e.preventDefault();
    var group = a.closest('[data-lb-group]');
    var links = group ? Array.prototype.slice.call(group.querySelectorAll('a[data-lb]')) : [a];
    viewer().open(links.map(function (l) { return { src: l.dataset.lb, caption: l.dataset.caption || '', link: l.dataset.link || '' }; }), links.indexOf(a), a);
  });

  // ---- Horse profile: thumbnails change the large photo; the large photo opens the viewer at that photo
  each('[data-gallery]', function (g) {
    var main = g.querySelector('[data-gallery-main]'), openLink = g.querySelector('[data-gallery-open]');
    var thumbs = Array.prototype.slice.call(g.querySelectorAll('[data-src]'));
    var at = 0;
    g.addEventListener('click', function (e) {
      var b = e.target.closest('[data-src]'); if (!b || !main) return;
      at = thumbs.indexOf(b);
      main.src = b.dataset.src;
      if (openLink) openLink.href = b.dataset.src;
      thumbs.forEach(function (x) { var on = x === b; x.classList.toggle('on', on); if (on) x.setAttribute('aria-current', 'true'); else x.removeAttribute('aria-current'); });
    });
    if (openLink && main) openLink.addEventListener('click', function (e) {
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      var list = thumbs.length ? thumbs.map(function (t) { return { src: t.dataset.src, caption: t.dataset.caption || main.alt }; }) : [{ src: openLink.href, caption: main.alt }];
      viewer().open(list, Math.max(0, at), openLink);
    });
  });

  // ---- Share a horse: the phone's own share sheet; on a computer a small menu (WhatsApp, copy link)
  each('[data-share]', function (box) {
    var btn = box.querySelector('[data-share-btn]'), menu = box.querySelector('[data-share-menu]'), copy = box.querySelector('[data-share-copy]');
    if (!btn || !menu) return;
    var open = function (on) {
      menu.hidden = !on; btn.setAttribute('aria-expanded', String(on));
      if (!on) return;
      menu.classList.remove('flip');
      var r = menu.getBoundingClientRect(), vw = document.documentElement.clientWidth;
      if (r.right > vw - 8 || r.left < 8) menu.classList.add('flip');
    };
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
        navigator.share({ title: btn.dataset.title, url: btn.dataset.url }).catch(function () {});
        return;
      }
      open(menu.hidden);
      if (!menu.hidden) { var first = menu.querySelector('a, button'); if (first) first.focus(); }
    });
    if (copy) copy.addEventListener('click', function () {
      var url = btn.dataset.url, label = copy.querySelector('span'), was = label.textContent;
      var done = function () { label.textContent = copy.dataset.done; copy.classList.add('done'); setTimeout(function () { label.textContent = was; copy.classList.remove('done'); open(false); btn.focus(); }, 1600); };
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); }); return; }
      window.prompt('', url);
    });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) open(false); });
    document.addEventListener('click', function (e) { if (!menu.hidden && !box.contains(e.target)) open(false); });
    box.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { open(false); btn.focus(); } });
  });

  // ---- The chapter rail (computers, homepage): 01–10 in the gutter; the current chapter turns gold
  var chapters = home ? home.querySelectorAll('.chapter[id] .chapter-index, .chapter[id] .contact-info .chapter-index') : [];
  if (chapters.length > 2 && 'IntersectionObserver' in window) {
    var rail = document.createElement('ol'); rail.className = 'chapter-rail'; rail.setAttribute('aria-label', 'Chapters');
    var items = [];
    Array.prototype.forEach.call(chapters, function (ix) {
      var sec = ix.closest('.chapter'); if (!sec || !sec.id) return;
      var li = document.createElement('li'), a = document.createElement('a');
      a.href = '#' + sec.id; a.textContent = (ix.querySelector('b') || ix).textContent.trim(); a.title = ix.textContent.replace(a.textContent, '').trim();
      li.appendChild(a); rail.appendChild(li); items.push({ sec: sec, a: a });
    });
    document.body.appendChild(rail);
    var rio = new IntersectionObserver(function (en) {
      en.forEach(function (x) { var it = items.filter(function (i) { return i.sec === x.target; })[0]; if (!it) return;
        if (x.isIntersecting) { items.forEach(function (i) { i.a.classList.toggle('on', i === it); }); rail.classList.toggle('on-night', it.sec.classList.contains('night') || it.sec.classList.contains('deep'));
          var dc = document.querySelector('[data-dock-chapter]'); if (dc) dc.textContent = it.a.textContent + ' · ' + it.a.title; } });
    }, { rootMargin: '-45% 0px -45% 0px' });
    items.forEach(function (i) { rio.observe(i.sec); });
  }

  // ---- The pedigree lights the line back to the horse: hovering an ancestor lights every box on the way
  each('[data-pedigree]', function (ped) {
    var boxes = Array.prototype.slice.call(ped.querySelectorAll('.ped-box[data-path]'));
    var light = function (path) {
      ped.classList.toggle('has-lit', !!path);
      boxes.forEach(function (b) { var on = !!path && path.indexOf(b.dataset.path) === 0; b.classList.toggle('lit', on); b.parentElement.classList.toggle('lit-td', on); });
    };
    ped.addEventListener('pointerover', function (e) { var b = e.target.closest('.ped-box'); if (b) light(b.dataset.path); });
    ped.addEventListener('pointerleave', function () { light(''); });
    ped.addEventListener('focusin', function (e) { var b = e.target.closest('.ped-box'); if (b) light(b.dataset.path); });
    ped.addEventListener('focusout', function () { light(''); });
  });

  // ---- The photograph travels to its page (cross-document view transitions): the card names its photo and name
  // just before the browser leaves, the profile claims the same names when it arrives
  if ('startViewTransition' in document && !reduce) {
    var nameCard = function (card) {
      each('[style*="view-transition-name"]', function (el) { el.style.viewTransitionName = ''; });
      var media = card.querySelector('.hcard-media'), nm = card.querySelector('.hcard-name');
      if (media) media.style.viewTransitionName = 'horse-photo';
      if (nm) nm.style.viewTransitionName = 'horse-name';
      try { sessionStorage.setItem('sk-vt', card.getAttribute('href')); } catch (err) {}
    };
    document.addEventListener('click', function (e) {
      var card = e.target.closest && e.target.closest('a.hcard');
      if (card && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) nameCard(card);
    }, true);
    var heroBg = document.querySelector('.page-hero-bg img'), h1 = document.querySelector('.page-hero h1');
    if (heroBg) heroBg.style.viewTransitionName = 'horse-photo';
    if (heroBg && h1) h1.style.viewTransitionName = 'horse-name';
    try { // back on a page of cards: the card the visitor left from takes the names, so the photo returns to it
      var from = sessionStorage.getItem('sk-vt');
      if (from && !heroBg) { var back = document.querySelector('a.hcard[href="' + from.replace(/"/g, '') + '"]'); if (back) nameCard(back); }
    } catch (err) {}
  }

  // ---- The footer's local time in Doha (Asia/Qatar), to the minute
  var clock = document.querySelector('[data-local-time]');
  if (clock && window.Intl && Intl.DateTimeFormat) {
    try {
      var fmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Qatar' });
      var tickClock = function () { clock.textContent = fmt.format(new Date()) + ' GMT+3'; };
      tickClock(); setInterval(tickClock, 30000);
    } catch (e) {}
  }
})();
