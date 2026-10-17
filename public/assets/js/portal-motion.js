/* SK Arabians Management System — brand motion and the command palette (no inline scripts: CSP script-src 'self').
   Loaded after portal.js on every staff page and on the sign-in pages. Sections: setup · indicators (sidebar, tabs) ·
   headlines and figures · pointer effects (spotlight, magnetic, cursor) · lists (density) · forms (unsaved changes) ·
   settings (reduce animations) · command palette · sign-in (slideshow, 2FA boxes, curtain).
   Rich on first impressions, quiet for daily work (150–300ms); nothing ever waits for an animation to type or click.
   Off with reduced motion: the system setting or Account → Reduce animations (html[data-motion="reduce"]). */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches || root.getAttribute('data-motion') === 'reduce';
  var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var rtl = root.dir === 'rtl';
  var cfg = { t: {} };
  try { cfg = JSON.parse((document.getElementById('sk-portal-cfg') || {}).textContent || '{"t":{}}'); } catch (e) {}
  var T = cfg.t || {};
  var each = function (sel, fn, scope) { Array.prototype.forEach.call((scope || document).querySelectorAll(sel), fn); };
  var store = { get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} } };
  function setCookie(name, value) { document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var spriteUse = document.querySelector('svg.icon use');
  var SPRITE = spriteUse ? (spriteUse.getAttribute('href') || '').split('#')[0] : '';
  var svgIcon = function (name) { return SPRITE ? '<svg class="icon" aria-hidden="true"><use href="' + SPRITE + '#' + name + '"></use></svg>' : ''; };

  // ---------------------------------------------------------------- Indicators that glide (sidebar item, tab)
  // Each remembers where it was on the previous page and slides from there to the current item, so moving through the
  // app feels continuous. Without JavaScript the gold rail / underline of the current item stays.
  function glide(box, target, el, key, vertical) {
    if (!box || !target) return;
    // the item's place inside the box from the layout (offsetTop / offsetLeft), not from the screen: the menu items rise
    // in on the first page, and a measure taken mid-animation would leave the plate off its item
    var at = function () {
      var x = 0, y = 0, n = target;
      while (n && n !== box) { x += n.offsetLeft; y += n.offsetTop; n = n.offsetParent; }
      return { x: x, y: y, w: target.offsetWidth, h: target.offsetHeight };
    };
    var place = function () {
      var p = at();
      if (vertical) { el.style.transform = 'translateY(' + p.y + 'px)'; el.style.height = p.h + 'px'; el.style.left = p.x + 'px'; el.style.right = 'auto'; el.style.width = p.w + 'px'; }
      else { el.style.transform = 'translateX(' + p.x + 'px)'; el.style.width = p.w + 'px'; }
    };
    var last = store.get(key);
    if (last && !reduce) {
      var p = JSON.parse(last);
      el.style.transition = 'none';
      if (vertical) { el.style.transform = 'translateY(' + p.a + 'px)'; el.style.height = p.b + 'px'; } else { el.style.transform = 'translateX(' + p.a + 'px)'; el.style.width = p.b + 'px'; }
      el.classList.add('on');
      void el.offsetWidth;
      el.style.transition = '';
      requestAnimationFrame(place);
    } else { place(); el.classList.add('on'); }
    var remember = function () {
      var p = at();
      store.set(key, JSON.stringify(vertical ? { a: p.y, b: p.h } : { a: p.x, b: p.w }));
    };
    window.addEventListener('pagehide', remember);
    // follow the item whenever the layout moves it: the menu collapsed or opened (the headings go, every item moves up
    // and narrows), the window resized, the fonts arrived
    if (window.ResizeObserver) new ResizeObserver(place).observe(target);
    window.addEventListener('resize', place);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
  }
  var side = document.querySelector('.sidebar .side-scroll');
  var activeLink = side && side.querySelector('.nav-list > li.active > a');
  if (side && activeLink) {
    var g = document.createElement('span'); g.className = 'nav-glider'; g.setAttribute('aria-hidden', 'true');
    side.prepend(g); side.closest('.sidebar').classList.add('has-glider');
    glide(side, activeLink, g, 'sk-glide-nav', true);
  }
  each('.tabs', function (tabs) {
    var on = tabs.querySelector('a.on'); if (!on) return;
    var line = document.createElement('span'); line.className = 'tab-glider'; line.setAttribute('aria-hidden', 'true');
    tabs.appendChild(line); tabs.classList.add('has-glider');
    glide(tabs, on, line, 'sk-glide-tab:' + location.pathname.replace(/\/\d+.*$/, ''), false);
  });

  // ---------------------------------------------------------------- Headlines and figures
  // masked headlines: words on one line share its number (--i), so a whole line lifts together, then the next
  var lines = function () {
    each('.split', function (h) {
      var top = null, line = -1;
      each('.w > span', function (s) { var t = s.parentNode.offsetTop; if (top === null || Math.abs(t - top) > 4) { top = t; line++; } s.style.setProperty('--i', String(line)); }, h);
    });
  };
  lines();
  // figures on screen at load count up once (eased); the text keeps its own format (separators, decimals, currency)
  if (!reduce) {
    var vh = window.innerHeight;
    each('.kpi-value, .stat .v', function (el) {
      if (el.getBoundingClientRect().top > vh) return;
      var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), node;
      while ((node = walker.nextNode())) { if (/\d/.test(node.nodeValue)) break; }
      if (!node) return;
      var m = node.nodeValue.match(/^(\D*?)(\d{1,3}(?:,\d{3})+|\d+)(\.\d+)?(.*)$/); if (!m) return;
      var end = parseFloat(m[2].replace(/,/g, '') + (m[3] || '')), dec = m[3] ? m[3].length - 1 : 0, grouped = m[2].indexOf(',') > -1, t0 = null, dur = 1100;
      if (!isFinite(end) || end === 0) return;
      var fmt = function (v) { var s = v.toFixed(dec); if (grouped) { var p = s.split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = p.join('.'); } return m[1] + s + m[4]; };
      var step = function (t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / dur); node.nodeValue = fmt(end * (1 - Math.pow(1 - p, 4))); if (p < 1) requestAnimationFrame(step); };
      node.nodeValue = fmt(0);
      setTimeout(function () { requestAnimationFrame(step); }, root.classList.contains('is-curtain') ? 900 : 150);
    });
  }

  // ---------------------------------------------------------------- Pointer effects (computers with a mouse; motion allowed)
  if (fine && !reduce) {
    // spotlight under the pointer (--px/--py), gold buttons lean toward it (--mx/--my)
    document.addEventListener('pointermove', function (e) {
      var s = e.target.closest && e.target.closest('.kpi-grid .kpi, .stat-grid, .spot');
      if (s) { var r = s.getBoundingClientRect(); s.style.setProperty('--px', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%'); s.style.setProperty('--py', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%'); }
      var m = e.target.closest && e.target.closest('.btn-primary:not(.btn-block):not(.btn-sm), .btn-gold');
      if (m) { var q = m.getBoundingClientRect(); m.style.setProperty('--mx', (((e.clientX - q.left) / q.width - .5) * 6).toFixed(1) + 'px'); m.style.setProperty('--my', (((e.clientY - q.top) / q.height - .5) * 5).toFixed(1) + 'px'); }
    }, { passive: true });
    document.addEventListener('pointerout', function (e) {
      var m = e.target.closest && e.target.closest('.btn-primary, .btn-gold');
      if (m && !m.contains(e.relatedTarget)) { m.style.removeProperty('--mx'); m.style.removeProperty('--my'); }
    });
    // no trailing cursor ring here (the website has one): in daily work it reads as a stray circle left on the page
  }

  // ---------------------------------------------------------------- Lists: row density (comfortable / compact), remembered
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-density]'); if (!b) return;
    var on = b.getAttribute('aria-pressed') !== 'true';
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
    var tip = b.getAttribute(on ? 'data-tip-on' : 'data-tip-off'); if (tip) b.setAttribute('data-tip', tip);
    each('.content .table', function (t) { t.classList.toggle('compact', on); });
    setCookie('sk_density', on ? 'compact' : 'comfortable');
  });

  // ---------------------------------------------------------------- Forms: once something changed, the save bar says so
  each('form.record-form, form:has(.sticky-actions)', function (f) {
    var bar = f.querySelector('.sticky-actions, .form-actions'); if (!bar || f.dataset.dirtyWatch) return;
    f.dataset.dirtyWatch = '1';
    var note = document.createElement('span'); note.className = 'unsaved-note'; note.setAttribute('role', 'status'); note.textContent = T.unsaved || 'Unsaved changes';
    bar.prepend(note);
    var mark = function (e) { if (e.target.closest('[data-no-dirty]')) return; if (!f.classList.contains('is-dirty')) f.classList.add('is-dirty'); };
    f.addEventListener('input', mark); f.addEventListener('change', mark);
    f.addEventListener('submit', function () { f.classList.remove('is-dirty'); });
  });

  // ---------------------------------------------------------------- Settings: Reduce animations (Account), remembered per browser
  document.addEventListener('change', function (e) {
    var t = e.target.closest('[data-motion-toggle]'); if (!t) return;
    var on = t.checked;
    setCookie('sk_motion', on ? 'reduce' : 'full');
    root.setAttribute('data-motion', on ? 'reduce' : 'full');
    reduce = on || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  });

  // ---------------------------------------------------------------- Command palette (Ctrl / Cmd + K): pages, actions and records
  // Pages and actions come from this page's own menus (so only what this user may open); records come from the global
  // search (/portal/search, the same permissions), read as it is. Keyboard first: ↑ ↓ to move, Enter to open, Esc to close.
  var mac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
  if (mac) each('[data-mod]', function (k) { k.textContent = '⌘'; });
  var pal = null, palInput, palList, items = [], active = 0, remote = [], remoteFor = '', timer = 0, opener = null, ctl = null;
  function norm(s) { return String(s || '').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه').normalize('NFKD').replace(/[̀-ًͯ-ٟ]/g, ''); }
  function collect() {
    var list = [], seen = {};
    var add = function (o) { var k = o.group + '|' + o.label + '|' + (o.href || ''); if (seen[k]) return; seen[k] = 1; list.push(o); };
    each('.sidebar .nav-list a[href]', function (a) {
      var grp = a.closest('.nav-group'), heading = grp && grp.querySelector('.nav-heading');
      var parent = a.closest('.sub') ? a.closest('.sub').parentNode.querySelector(':scope > a span') : null;
      var label = (a.querySelector('span') || a).textContent.trim();
      var iconUse = a.querySelector('svg use'); var icon = iconUse ? (iconUse.getAttribute('href') || '').split('#')[1] : 'arrow-right';
      add({ group: T.pages || 'Pages', label: label, hint: parent ? parent.textContent.trim() : (heading ? heading.textContent.trim() : ''), href: a.href, icon: a.closest('.sub') ? 'corner-down-right' : icon });
    });
    each('#new-menu [role="menuitem"]', function (a) {
      var iconUse = a.querySelector('svg use');
      add({ group: T.actions || 'Actions', label: a.textContent.trim(), href: a.getAttribute('href') ? a.href : null, el: a.getAttribute('href') ? null : a, icon: iconUse ? (iconUse.getAttribute('href') || '').split('#')[1] : 'plus' });
    });
    each('#user-menu a[role="menuitem"], .side-foot a.side-link', function (a) {
      var iconUse = a.querySelector('svg use');
      add({ group: T.actions || 'Actions', label: a.textContent.trim(), href: a.href, icon: iconUse ? (iconUse.getAttribute('href') || '').split('#')[1] : 'arrow-right' });
    });
    each('[data-theme-set]', function (b) { add({ group: T.actions || 'Actions', label: (T.theme ? T.theme + ': ' : '') + b.textContent.trim(), el: b, icon: (b.querySelector('svg use') || { getAttribute: function () { return '#sun'; } }).getAttribute('href').split('#')[1] }); });
    return list;
  }
  function build() {
    pal = document.createElement('div'); pal.className = 'cmdk'; pal.hidden = true;
    pal.setAttribute('role', 'dialog'); pal.setAttribute('aria-modal', 'true'); pal.setAttribute('aria-label', T.cmdk || 'Command palette');
    pal.innerHTML = '<div class="cmdk-panel"><div class="cmdk-search">' + svgIcon('search') +
      '<input type="text" role="combobox" aria-expanded="true" aria-controls="cmdk-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="' + esc(T.placeholder || 'Search…') + '">' +
      '<kbd>Esc</kbd></div><ul class="cmdk-list" id="cmdk-list" role="listbox" aria-label="' + esc(T.cmdk || '') + '"></ul>' +
      '<div class="cmdk-foot"><span><kbd>↑</kbd><kbd>↓</kbd> ' + esc(T.nav || '') + '</span><span><kbd>↵</kbd> ' + esc(T.open || '') + '</span><span><kbd>Esc</kbd> ' + esc(T.close || '') + '</span></div></div>';
    document.body.appendChild(pal);
    palInput = pal.querySelector('input'); palList = pal.querySelector('.cmdk-list');
    palInput.addEventListener('input', function () { active = 0; render(); fetchRemote(); });
    palInput.addEventListener('keydown', function (e) {
      var opts = palList.querySelectorAll('[role="option"]');
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!opts.length) return; active = (active + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length; mark(); }
      else if (e.key === 'Enter') { e.preventDefault(); var o = opts[active]; if (o) choose(+o.dataset.idx); }
      else if (e.key === 'Escape') { e.preventDefault(); close(); }
      else if (e.key === 'Tab') { e.preventDefault(); } // the keyboard stays in the palette
    });
    palList.addEventListener('mousemove', function (e) { var o = e.target.closest('[role="option"]'); if (o && +o.dataset.pos !== active) { active = +o.dataset.pos; mark(); } });
    palList.addEventListener('click', function (e) { var o = e.target.closest('[role="option"]'); if (o) choose(+o.dataset.idx); });
    pal.addEventListener('click', function (e) { if (e.target === pal) close(); });
  }
  function highlight(text, q) {
    if (!q) return esc(text);
    var i = norm(text).indexOf(norm(q)); if (i < 0) return esc(text);
    return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
  }
  function render() {
    var q = palInput.value.trim(), nq = norm(q);
    var shown = items.filter(function (o) { return !nq || norm(o.label + ' ' + (o.hint || '')).indexOf(nq) > -1; }).slice(0, 40);
    if (q.length >= 2) {
      shown = shown.concat(remote);
      shown.push({ group: T.records || 'Records', label: (T.searchAll || 'Search everything for “:q”').replace(':q', q), href: (cfg.search || '') + '?q=' + encodeURIComponent(q), icon: 'search', all: true });
    }
    var html = '', grp = null, pos = 0, all = [];
    shown.forEach(function (o) {
      if (o.group !== grp) { grp = o.group; html += '<li class="cmdk-group" role="presentation">' + esc(grp) + '</li>'; }
      all.push(o);
      html += '<li class="cmdk-item" role="option" id="cmdk-o' + pos + '" data-pos="' + pos + '" data-idx="' + (all.length - 1) + '" aria-selected="false">' + svgIcon(o.icon || 'arrow-right') +
        '<span class="ci-text">' + highlight(o.label, o.all ? '' : q) + '</span>' + (o.hint ? '<span class="ci-hint">' + esc(o.hint) + '</span>' : '') + '</li>';
      pos++;
    });
    if (!pos) html = '<li class="cmdk-empty" role="presentation">' + esc(q.length >= 2 && remoteFor !== q ? (T.loading || '…') : (T.empty || '')) + '</li>';
    palList.innerHTML = html; palList._all = all;
    if (active >= pos) active = Math.max(0, pos - 1);
    mark();
  }
  function mark() {
    var opts = palList.querySelectorAll('[role="option"]');
    Array.prototype.forEach.call(opts, function (o, i) { var on = i === active; o.classList.toggle('active', on); o.setAttribute('aria-selected', on ? 'true' : 'false'); if (on) { palInput.setAttribute('aria-activedescendant', o.id); o.scrollIntoView({ block: 'nearest' }); } });
  }
  function fetchRemote() {
    clearTimeout(timer);
    var q = palInput.value.trim();
    if (q.length < 2 || !cfg.search) { remote = []; remoteFor = ''; return; }
    timer = setTimeout(function () {
      if (ctl && ctl.abort) ctl.abort();
      ctl = window.AbortController ? new AbortController() : null;
      fetch(cfg.search + '?go=0&q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: ctl ? ctl.signal : undefined, headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.ok ? r.text() : ''; })
        .then(function (html) {
          if (palInput.value.trim() !== q) return;
          var doc = new DOMParser().parseFromString(html, 'text/html'), out = [];
          Array.prototype.forEach.call(doc.querySelectorAll('#main section.card'), function (sec) {
            var h = sec.querySelector('h2'); var name = h ? (h.firstChild ? h.firstChild.textContent : h.textContent).trim() : (T.records || '');
            Array.prototype.forEach.call(sec.querySelectorAll('.alert-list li'), function (li) {
              var a = li.querySelector('a'); if (!a) return;
              out.push({ group: name, label: a.textContent.trim(), hint: (li.querySelector('.small') || { textContent: '' }).textContent.trim(), href: a.getAttribute('href'), icon: 'file-text' });
            });
          });
          remote = out.slice(0, 30); remoteFor = q; render();
        }).catch(function () {});
    }, 220);
  }
  function choose(i) {
    var o = (palList._all || [])[i]; if (!o) return;
    close(true);
    if (o.el) { o.el.click(); return; }
    if (o.href) location.href = o.href;
  }
  function open() {
    if (!pal) build();
    items = collect(); remote = []; remoteFor = '';
    opener = document.activeElement;
    pal.hidden = false; void pal.offsetWidth; pal.classList.add('open');
    palInput.value = ''; active = 0; render();
    palInput.focus();
    document.body.style.overflow = 'hidden';
  }
  function close(silent) {
    if (!pal || pal.hidden) return;
    pal.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(function () { pal.hidden = true; }, reduce ? 0 : 160);
    if (!silent && opener && opener.focus) opener.focus();
  }
  if (document.body.classList.contains('portal')) {
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); if (pal && !pal.hidden) close(); else open(); }
    });
    document.addEventListener('click', function (e) { if (e.target.closest('[data-cmdk]')) { e.preventDefault(); open(); } });
  }

  // ---------------------------------------------------------------- Sign-in pages
  if (document.body.classList.contains('auth-page')) {
    // the photos behind the card cross-fade slowly (the first is in the page; the next loads before its turn)
    var slides = document.querySelectorAll('.auth-slide');
    if (slides.length > 1 && !reduce) {
      var cur = 0;
      var load = function (n) { if (n.dataset.src && !n.getAttribute('src')) n.src = n.dataset.src; };
      load(slides[1]);
      setInterval(function () {
        if (document.hidden) return;
        var next = (cur + 1) % slides.length; load(slides[next]); load(slides[(next + 1) % slides.length]);
        slides[cur].classList.remove('on'); slides[next].classList.add('on'); cur = next;
      }, 8000);
    }
    // a failed attempt comes back here: the curtain note is only for the page after a successful sign-in
    store.set('sk-curtain', '');
    each('form[action$="/portal/login"], form[action$="/portal/login/2fa"]', function (f) { f.addEventListener('submit', function () { store.set('sk-curtain', '1'); }); });
    // the 2FA code: six boxes drawn over the one real field (screen readers, paste and the phone's code suggestion all
    // use the field itself; the boxes only show what is typed and where the next digit goes)
    each('input.code-input', function (inp) {
      if (inp.dataset.boxes) return; inp.dataset.boxes = '1';
      var wrap = document.createElement('span'); wrap.className = 'code-boxes';
      inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
      var boxes = document.createElement('span'); boxes.className = 'code-cells'; boxes.setAttribute('aria-hidden', 'true');
      for (var i = 0; i < 6; i++) boxes.appendChild(document.createElement('i'));
      wrap.appendChild(boxes);
      var cells = boxes.children;
      var paint = function () {
        var v = inp.value.replace(/\D/g, '').slice(0, 6), focus = document.activeElement === inp;
        for (var j = 0; j < 6; j++) { cells[j].textContent = v[j] || ''; cells[j].classList.toggle('filled', !!v[j]); cells[j].classList.toggle('next', focus && j === Math.min(v.length, 5)); }
        wrap.classList.toggle('done', v.length === 6);
      };
      inp.addEventListener('input', function () { var d = inp.value.replace(/\D/g, '').slice(0, 6); if (d !== inp.value) inp.value = d; paint(); });
      inp.addEventListener('focus', paint); inp.addEventListener('blur', paint);
      inp.setAttribute('maxlength', '6');
      paint();
    });
  }
})();
