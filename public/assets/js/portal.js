/* SK Arabians Management System — portal behaviour (no inline scripts: CSP script-src 'self'). */
(function () {
  'use strict';
  var base = (document.body && document.body.dataset.base) || '/';
  if (base.slice(-1) !== '/') base += '/';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var isAr = document.documentElement.dir === 'rtl';
  var T = isAr
    ? { showPw: 'إظهار كلمة المرور', hidePw: 'إخفاء كلمة المرور', loading: 'جارٍ التحميل…', close: 'إغلاق' }
    : { showPw: 'Show password', hidePw: 'Hide password', loading: 'Loading…', close: 'Close' };
  var desktop = window.matchMedia ? window.matchMedia('(min-width: 900px)') : { matches: true, addEventListener: function () {} };
  // the icon sprite this page uses (icon() in PHP), for icons drawn from here
  var spriteUse = document.querySelector('svg.icon use');
  var SPRITE = spriteUse ? (spriteUse.getAttribute('href') || '').split('#')[0] : base + 'assets/img/icons.svg';
  function svgIcon(name, cls) { return '<svg class="icon ' + (cls || '') + '" aria-hidden="true"><use href="' + SPRITE + '#' + name + '"></use></svg>'; }
  function setCookie(name, value) { document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
  function readCookie(name) { var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : null; }

  // ---------------------------------------------------------------- Theme: System / Light / Dark (user menu)
  // The server already renders the dark layer's media from the sk_theme cookie (no flash on load); switching here changes
  // the media of #theme-dark and every link[data-theme-dark] live, <html data-theme> and the browser bar colour.
  var THEME_MEDIA = { system: 'screen and (prefers-color-scheme: dark)', light: 'not all', dark: 'screen' };
  function applyTheme(theme) {
    if (!THEME_MEDIA[theme]) theme = 'dark';
    document.querySelectorAll('link#theme-dark, link[data-theme-dark]').forEach(function (l) { l.media = THEME_MEDIA[theme]; });
    document.documentElement.setAttribute('data-theme', theme);
    document.querySelectorAll('[data-theme-set]').forEach(function (b) { b.setAttribute('aria-checked', b.getAttribute('data-theme-set') === theme ? 'true' : 'false'); });
    if (!document.body.classList.contains('portal')) return; // sign-in pages stay night
    var head = document.head;
    head.querySelectorAll('meta[name="theme-color"]').forEach(function (m) { m.remove(); });
    var add = function (color, media) { var m = document.createElement('meta'); m.name = 'theme-color'; m.content = color; if (media) m.media = media; head.appendChild(m); };
    if (theme === 'system') { add('#ffffff', '(prefers-color-scheme: light)'); add('#0f1a33', '(prefers-color-scheme: dark)'); }
    else add(theme === 'light' ? '#ffffff' : '#0f1a33');
  }
  // a page kept from before (back button) follows the theme chosen since
  var savedTheme = readCookie('sk_theme');
  if (savedTheme && savedTheme !== document.documentElement.getAttribute('data-theme')) applyTheme(savedTheme);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-theme-set]'); if (!b) return;
    var theme = b.getAttribute('data-theme-set');
    setCookie('sk_theme', theme);
    // the new theme cross-fades in (View Transitions where the browser has them; instant with reduced motion)
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.getAttribute('data-motion') === 'reduce';
    if (document.startViewTransition && !calm) document.startViewTransition(function () { applyTheme(theme); });
    else applyTheme(theme);
  });

  // Generic form section toggle: <select data-section-toggle name="x"> shows only the
  // [data-section-for="x"][data-section-value=<value>] block matching its current value.
  document.querySelectorAll('select[data-section-toggle]').forEach(function (sel) {
    function sync() {
      document.querySelectorAll('[data-section-for="' + sel.name + '"]').forEach(function (el) {
        el.hidden = el.dataset.sectionValue !== sel.value;
      });
    }
    sel.addEventListener('change', sync);
    sync();
  });

  // ---------------------------------------------------------------- Sidebar: a drawer on phones and tablets, icons-only on desktop
  var sidebar = document.getElementById('sidebar');
  var navToggle = document.querySelector('[data-toggle="nav"]');
  window.addEventListener('load', function () { requestAnimationFrame(function () { document.body.classList.add('nav-ready'); }); });
  function drawerOpen() { return document.body.classList.contains('nav-open'); }
  function setDrawer(open) {
    if (!sidebar || open === drawerOpen()) return;
    document.body.classList.toggle('nav-open', open);
    if (navToggle) navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { var first = sidebar.querySelector('li.active > a') || sidebar.querySelector('a, button'); if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 60); }
    else if (navToggle && sidebar.contains(document.activeElement)) navToggle.focus();
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-toggle="nav"]')) { setDrawer(!drawerOpen()); return; }
    if (e.target.closest('[data-nav-close]')) { setDrawer(false); return; }
    var c = e.target.closest('[data-nav-collapse]');
    if (c) {
      var on = !document.body.classList.contains('nav-collapsed');
      document.body.classList.toggle('nav-collapsed', on);
      setCookie('sk_nav', on ? 'collapsed' : 'open');
      var label = c.getAttribute(on ? 'data-label-expand' : 'data-label-collapse') || '';
      c.setAttribute('aria-pressed', on ? 'true' : 'false'); c.setAttribute('aria-label', label); c.setAttribute('data-tip', label);
      hideTip();
      setTimeout(fitTables, 260);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (!drawerOpen() || desktop.matches) return;
    if (e.key === 'Escape') { setDrawer(false); return; }
    if (e.key === 'Tab' && sidebar) { // the open drawer keeps the keyboard inside it
      var f = Array.prototype.filter.call(sidebar.querySelectorAll('a[href], button:not([disabled])'), function (el) { return el.getClientRects().length > 0; });
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && (document.activeElement === f[f.length - 1] || !sidebar.contains(document.activeElement))) { e.preventDefault(); f[0].focus(); }
    }
  });
  if (desktop.addEventListener) desktop.addEventListener('change', function () { if (desktop.matches) setDrawer(false); });

  // ---------------------------------------------------------------- Tooltips: [data-tip] (the collapsed sidebar's labels, icon buttons, chart bars)
  var tipEl = null, tipFor = null;
  function hideTip() { if (tipEl) { tipEl.classList.remove('on'); tipFor = null; } }
  function showTip(el) {
    if (el.getAttribute('data-tip-when') === 'collapsed' && !(document.body.classList.contains('nav-collapsed') && desktop.matches)) return;
    var text = el.getAttribute('data-tip'); if (!text) return;
    if (!tipEl) { tipEl = document.createElement('div'); tipEl.className = 'tooltip'; tipEl.setAttribute('role', 'tooltip'); document.body.appendChild(tipEl); }
    tipEl.textContent = text; tipFor = el;
    var r = el.getBoundingClientRect(), vw = document.documentElement.clientWidth, w = tipEl.offsetWidth, h = tipEl.offsetHeight, x, y;
    if (el.closest('.sidebar')) { // beside the icon, on the page side
      x = isAr ? r.left - w - 10 : r.right + 10; y = r.top + (r.height - h) / 2;
    } else { x = r.left + (r.width - w) / 2; y = r.top - h - 8; if (y < 8) y = r.bottom + 8; }
    tipEl.style.left = Math.round(Math.min(Math.max(8, x), vw - w - 8)) + 'px'; tipEl.style.top = Math.round(Math.max(8, y)) + 'px';
    tipEl.classList.add('on');
  }
  document.addEventListener('mouseover', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el && el !== tipFor) showTip(el); else if (!el && tipFor) hideTip(); });
  document.addEventListener('focusin', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el) showTip(el); else hideTip(); });
  document.addEventListener('focusout', hideTip);
  window.addEventListener('scroll', hideTip, true);

  // ---------------------------------------------------------------- Flash messages: shown as toasts (the inline copy stays without JavaScript)
  var flash = document.querySelector('[data-flash]');
  if (flash && window.Modal && window.Modal.toast) {
    flash.querySelectorAll('.alert').forEach(function (a) {
      var type = a.getAttribute('data-flash-type') || 'info';
      var text = Array.prototype.filter.call(a.childNodes, function (n) { return n.nodeType === 3; }).map(function (n) { return n.textContent; }).join(' ').trim();
      window.Modal.toast(text, type, { sticky: type === 'error' || type === 'warning' });
    });
    flash.remove();
  }
  document.addEventListener('click', function (e) { var x = e.target.closest('[data-dismiss]'); if (x) { var box = x.closest('.alert, .sk-toast'); if (box) box.remove(); } });

  // ---------------------------------------------------------------- Tables: the header sticks under the top bar when the table fits
  // (a table wider than its box scrolls sideways inside it — then the header scrolls with it)
  function fitTables() {
    document.querySelectorAll('.table-wrap').forEach(function (w) {
      var tb = w.querySelector('table'); if (!tb) return;
      w.classList.remove('fits');
      w.classList.toggle('fits', tb.scrollWidth <= w.clientWidth + 1);
    });
  }
  fitTables();
  var fitTimer; window.addEventListener('resize', function () { clearTimeout(fitTimer); fitTimer = setTimeout(fitTables, 120); });
  window.addEventListener('load', fitTables);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitTables);

  // ---------------------------------------------------------------- Password fields: show / hide (the field stays a password field when sent)
  document.querySelectorAll('input[type="password"]').forEach(function (inp) {
    if (inp.hasAttribute('data-no-toggle') || inp.getAttribute('data-pw')) return;
    inp.setAttribute('data-pw', '1');
    var box = inp.parentElement && inp.parentElement.classList.contains('pw-field') ? inp.parentElement : null;
    if (!box) { box = document.createElement('span'); box.className = 'pw-field'; inp.parentNode.insertBefore(box, inp); box.appendChild(inp); }
    var b = document.createElement('button'); b.type = 'button'; b.className = 'pw-toggle';
    b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', T.showPw);
    b.innerHTML = svgIcon('eye', 'i-show') + svgIcon('eye-off', 'i-hide');
    box.appendChild(b);
    b.addEventListener('click', function () {
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false'); b.setAttribute('aria-label', show ? T.hidePw : T.showPw);
      inp.focus();
    });
    if (inp.form) inp.form.addEventListener('submit', function () { inp.type = 'password'; b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', T.showPw); });
  });

  // ---------------------------------------------------------------- Phone fields: a Qatar number reads "+974 5555 5555" once typed
  document.addEventListener('blur', function (e) {
    var el = e.target;
    if (!(el instanceof HTMLInputElement) || el.type !== 'tel' || el.readOnly) return;
    var d = el.value.replace(/[^\d]/g, '');
    if (d.length === 11 && d.indexOf('974') === 0) d = d.slice(3);
    if (d.length !== 8 || /[^\d\s+()-]/.test(el.value)) return;
    var v = '+974 ' + d.slice(0, 4) + ' ' + d.slice(4);
    if (v !== el.value) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }
  }, true);

  // Style guide (/portal/style-guide): the toast and modal samples
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sg-toast]'), m = e.target.closest('[data-sg-modal]');
    if (t && window.Modal) window.Modal.toast(t.getAttribute('data-sg-toast') === 'error' ? (isAr ? 'تعذّر الحفظ. حاول مرة أخرى.' : 'The record could not be saved. Try again.') : (isAr ? 'تم الحفظ بنجاح' : 'Saved successfully'), t.getAttribute('data-sg-toast'), { sticky: t.getAttribute('data-sg-toast') === 'error' });
    if (m && window.Modal) {
      var entry = window.Modal.custom({ size: m.getAttribute('data-sg-modal'), title: m.textContent.trim(), subtitle: 'Modal.custom({ size: \'' + m.getAttribute('data-sg-modal') + '\' })' });
      var body = entry.el.querySelector('.sk-modal-body'), foot = entry.el.querySelector('.sk-modal-footer');
      for (var i = 0; i < 30; i++) { var p = document.createElement('p'); p.textContent = (isAr ? 'سطر للتجربة رقم ' : 'Sample line ') + (i + 1) + (isAr ? ' — يتمرّر محتوى النافذة وتبقى الترويسة والأزرار ثابتة.' : ' — the body scrolls; the header and the buttons stay.'); body.appendChild(p); }
      var c = document.createElement('button'); c.type = 'button'; c.className = 'btn'; c.textContent = isAr ? 'إلغاء' : 'Cancel';
      var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'btn btn-primary'; ok.textContent = isAr ? 'حفظ' : 'Save';
      foot.appendChild(c); foot.appendChild(ok);
      c.addEventListener('click', function () { window.Modal.close(entry); });
      ok.addEventListener('click', function () { window.Modal.busy(ok, true); setTimeout(function () { window.Modal.close(entry); }, 900); });
    }
  });
  // Confirm dialogs, type-to-confirm delete, and "+ Add new" quick-add popups are all handled by the
  // shared modal engine in public/assets/js/modal.js (window.Modal), loaded just before this file.
  // Report period "switch to Custom dates" is handled by datepicker.js (it fires on the flatpickr onChange,
  // since the native input's type changes once Flatpickr takes it over).
  // Filters fold away on phones; the button opens them
  // (the list page's "Filters" button in the head shows / hides the filter panel it controls)
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toggle-filters]'); if (!b) return;
    var id = b.getAttribute('aria-controls'), f = id ? document.getElementById(id) : b.closest('form');
    if (!f) return;
    var open;
    if (f.hasAttribute('data-collapsible')) { f.classList.toggle('is-collapsed'); open = !f.classList.contains('is-collapsed'); f.classList.toggle('open', open); }
    else { f.classList.toggle('open'); open = f.classList.contains('open'); }
    b.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && id) { var first = f.querySelector('input:not([type=hidden]), .sel-btn, .picker-input'); if (first) first.focus({ preventScroll: true }); }
  });
  // Role form: "View only" switches off (and clears) every permission that changes something
  document.addEventListener('change', function (e) {
    var ro = e.target.closest('input[data-read-only]'); if (!ro) return;
    (ro.form || document).querySelectorAll('input[data-write]').forEach(function (c) { c.disabled = ro.checked; if (ro.checked) c.checked = false; });
  });
  // "Select all" checkbox for a list of checkboxes
  document.addEventListener('change', function (e) {
    var all = e.target.closest('input[data-check-all]'); if (!all) return;
    var form = all.form || document;
    form.querySelectorAll('input[type="checkbox"][name="' + all.dataset.checkAll + '"]').forEach(function (c) { c.checked = all.checked; });
  });

  // Auto-submit file pickers (photo upload from phone)
  document.addEventListener('change', function (e) {
    if (e.target.matches('input[type="file"][data-auto-submit]') && e.target.files.length) {
      var form = e.target.form; var lbl = e.target.closest('label'); if (lbl) lbl.classList.add('busy');
      form.submit();
    }
  });

  // Captcha refresh
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-captcha-refresh]'); if (!b) return;
    var img = document.querySelector('[data-captcha]'); if (img) img.src = img.src.split('?')[0] + '?t=' + Date.now();
  });

  // Currency → exchange rate autofill (rate saved on the transaction, editable)
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('select[data-currency]'); if (!sel) return;
    var opt = sel.selectedOptions[0]; var form = sel.form; if (!form) return;
    var rate = form.querySelector('[name="exchange_rate"]');
    if (rate && opt) { rate.value = opt.dataset.rate || '1'; rate.dispatchEvent(new Event('input', { bubbles: true })); }
  });
  // Live QAR preview for amount × rate
  document.addEventListener('input', function (e) {
    var form = e.target.form; if (!form) return;
    var amt = form.querySelector('[name="amount_original"]'), rate = form.querySelector('[name="exchange_rate"]'), out = form.querySelector('[data-qar-preview]');
    if (!amt || !rate || !out) return;
    var a = parseFloat(String(amt.value).replace(/,/g, '')), r = parseFloat(rate.value);
    // as money() prints it: "4,500 QR" in English (data-cur / data-trim from the page), "4,500.00 QAR" in Arabic
    var v = Math.round(a * r * 100) / 100, whole = out.getAttribute('data-trim') === '1' && Math.abs(v % 1) < 0.005;
    out.textContent = isFinite(a) && isFinite(r) ? v.toLocaleString('en-US', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 }) + ' ' + (out.getAttribute('data-cur') || 'QAR') : '—';
  });

  // ---------------------------------------------------------------- Searchable pickers (server-backed)
  // The shared "only one dropdown open" / viewport-flip engine (window.DD) lives in dropdown.js, loaded
  // just before this file — it also enhances every plain <select> (see dropdown.js for that half).
  function enhancePicker(sel) {
    if (sel.dataset.enhanced) return; sel.dataset.enhanced = '1';
    var source = sel.dataset.picker, params = sel.dataset.params || '';
    var wrap = document.createElement('div'); wrap.className = 'picker';
    var input = document.createElement('input'); input.type = 'text'; input.className = 'picker-input'; input.autocomplete = 'off';
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-expanded', 'false');
    input.placeholder = sel.options[0] ? sel.options[0].text : '';
    var cur = sel.selectedOptions[0]; if (cur && cur.value) input.value = cur.text;
    if (sel.disabled) input.disabled = true;
    var list = document.createElement('ul'); list.className = 'picker-list'; list.hidden = true; list.setAttribute('role', 'listbox');
    var clear = document.createElement('button'); clear.type = 'button'; clear.className = 'picker-clear'; clear.textContent = '×'; clear.setAttribute('aria-label', isAr ? 'مسح' : 'Clear');
    var backdrop = document.createElement('div'); backdrop.className = 'dd-sheet-backdrop'; backdrop.hidden = true;
    sel.hidden = true; sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(input); wrap.appendChild(clear); wrap.appendChild(sel);
    document.body.appendChild(list); document.body.appendChild(backdrop);
    var timer, active = -1, items = [], loading = false, reqSeq = 0;
    function setValue(id, label) {
      var o = sel.querySelector('option[value="' + CSS.escape(String(id)) + '"]');
      if (!o) { o = document.createElement('option'); o.value = id; sel.appendChild(o); }
      o.text = label; sel.value = id; input.value = id === '' ? '' : label; close();
      sel.classList.toggle('dd-has-value', id !== '');
      // Matches a real <select>'s own behavior (fires both) — some callers bind to input specifically.
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function close() {
      if (list.hidden) return;
      list.hidden = true; wrap.classList.remove('open'); list.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); active = -1;
      backdrop.hidden = true; document.body.classList.remove('dd-sheet-open');
    }
    function render() {
      list.innerHTML = '';
      if (loading) { var lo = document.createElement('li'); lo.className = 'empty-opt sr-only'; lo.textContent = T.loading; list.appendChild(lo); for (var s = 0; s < 3; s++) { var sk = document.createElement('li'); sk.className = 'skel-row'; sk.setAttribute('aria-hidden', 'true'); sk.innerHTML = '<span class="skel ' + (s === 1 ? 'w-60' : 'w-80') + '"></span>'; list.appendChild(sk); } }
      else if (!items.length) { var li = document.createElement('li'); li.className = 'empty-opt'; li.textContent = isAr ? 'لا توجد نتائج' : 'No results'; list.appendChild(li); }
      var prevGrp = null;
      items.forEach(function (it, i) {
        // Optional non-selectable group row (e.g. "Common" pinned to the top, source-defined via Pickers'
        // 'group' key) — a plain divider once the group changes to no-group, a labeled row otherwise.
        if (it.grp !== undefined && it.grp !== prevGrp) {
          if (i > 0 || it.grp) { // no leading blank divider when the very first row has no group
            var div = document.createElement('li');
            div.className = 'picker-group' + (it.grp ? '' : ' picker-divider');
            div.setAttribute('aria-hidden', 'true');
            if (it.grp) div.textContent = it.grp;
            list.appendChild(div);
          }
          prevGrp = it.grp;
        }
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = i;
        var isSel = String(it.id) === String(sel.value) && sel.value !== '';
        if (isSel) { li.setAttribute('aria-selected', 'true'); li.innerHTML = '<span class="dd-check">✓</span>'; li.appendChild(document.createTextNode(it.label)); }
        else li.textContent = it.label;
        if (i === active) li.classList.add('active');
        list.appendChild(li);
      });
      // .open goes on the list itself too, not just wrap: the list lives under <body> (see enhancePicker's
      // setup above), not under wrap, so a `.picker.open .picker-list` ancestor-combinator rule would
      // never match — this keeps it independent of where the list is actually parented.
      list.hidden = false; wrap.classList.add('open'); list.classList.add('open'); input.setAttribute('aria-expanded', 'true');
      window.DD.registerOpen(close, list, input);
      if (window.DD.isMobile()) { backdrop.hidden = false; document.body.classList.add('dd-sheet-open'); }
      window.DD.position(input, list);
      var activeLi = list.querySelector('li.active') || list.querySelector('li[aria-selected="true"]');
      if (activeLi && activeLi.scrollIntoView) activeLi.scrollIntoView({ block: 'nearest' });
    }
    function search(forceEmptyQuery) {
      loading = true; render();
      var q = forceEmptyQuery ? '' : input.value;
      var url = base + 'portal/api/picker/' + encodeURIComponent(source) + '?q=' + encodeURIComponent(q) + (params ? '&' + params : '') + depParams();
      var seq = ++reqSeq; // the focus-triggered (empty query) and input-triggered (typed query) fetches can
      // resolve out of order; only the response matching the most recently sent request is allowed to render.
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
        .then(function (d) { if (seq !== reqSeq) return; loading = false; items = d.results || []; active = -1; render(); })
        .catch(function () { if (seq !== reqSeq) return; loading = false; render(); });
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 180); });
    // Opening a picker that already has a value (any edit form) used to search using that value's own
    // label as the query — e.g. focusing a "Country" field already set to Qatar would list/filter by
    // "Qatar" instead of showing every country. Worse, since the label text stayed selected-but-not-
    // highlighted in the input, typing a fresh search (e.g. "u") inserted at the cursor instead of
    // replacing it — searching for "Qatару"-style garbage that never matches anything, which looked like
    // the search was simply broken. Selecting the text on focus makes the first keystroke replace it
    // (standard combobox behavior), and the opening search always starts from the full/common list.
    input.addEventListener('focus', function () { if (!input.disabled) { input.select(); clearTimeout(timer); timer = setTimeout(function () { search(true); }, 50); } });
    input.addEventListener('keydown', function (e) {
      if (list.hidden && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) { search(); return; }
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter') { if (items[active]) { setValue(items[active].id, items[active].label); e.preventDefault(); } }
      else if (e.key === 'Escape') { close(); e.preventDefault(); }
    });
    var touchMoved = false;
    list.addEventListener('touchstart', function () { touchMoved = false; }, { passive: true });
    list.addEventListener('touchmove', function () { touchMoved = true; }, { passive: true });
    list.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (touchMoved) { touchMoved = false; return; }
      var li = e.target.closest('li[data-i]'); if (li) { var it = items[+li.dataset.i]; setValue(it.id, it.label); }
    });
    backdrop.addEventListener('click', close);
    input.addEventListener('blur', function () {
      setTimeout(function () { close(); var c = sel.selectedOptions[0]; input.value = c && c.value ? c.text : ''; sel.dispatchEvent(new Event('blur')); }, 150);
    });
    clear.addEventListener('click', function () { setValue('', ''); input.focus(); });
    sel._pickerSet = setValue;
    sel.classList.toggle('dd-has-value', !!(cur && cur.value));
    // Dependent pickers (e.g. sub-category follows category): filter by the other field and clear when it changes
    function depParams() {
      if (!sel.dataset.depends || !sel.form) return '';
      return sel.dataset.depends.split(',').map(function (pair) {
        var p = pair.split(':'), other = sel.form.elements[p[1]];
        return other && other.value ? '&' + encodeURIComponent(p[0]) + '=' + encodeURIComponent(other.value) : '';
      }).join('');
    }
    if (sel.dataset.depends && sel.form) {
      sel.dataset.depends.split(',').forEach(function (pair) {
        var other = sel.form.elements[pair.split(':')[1]];
        if (!other) return;
        // A segmented-control field (type=segment) is a group of same-name radio inputs: form.elements
        // then returns a RadioNodeList, not a single element — it has no addEventListener of its own
        // (though, usefully, its .value already reflects whichever radio is checked), so each radio in
        // the group needs the listener individually.
        var targets = other.length && !other.tagName ? Array.prototype.slice.call(other) : [other];
        targets.forEach(function (t) { t.addEventListener('change', function () { if (sel.value) setValue('', ''); }); });
      });
    }
  }
  document.querySelectorAll('select[data-picker]').forEach(enhancePicker);

  // One-tap approvals on mobile: post via fetch and remove the card
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-ajax-approval]'); if (!f || e.defaultPrevented) return;
    e.preventDefault();
    var card = f.closest('.approval-card'); var fd = new FormData(f);
    fetch(f.action, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok && card) { card.style.opacity = '.4'; card.querySelector('.approval-buttons').innerHTML = '<span class="badge badge-' + (d.status === 'approved' ? 'approved' : 'rejected') + '">' + d.label + '</span>'; }
        else if (window.Modal) { window.Modal.toast(d.message || (isAr ? 'حدث خطأ' : 'Error'), 'error'); }
        else { alert(d.message || 'Error'); }
      })
      .catch(function () { f.submit(); });
  });

  // Copy to clipboard helper
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(function () { b.textContent = '✓'; });
  });

  // Date/time/datetime inputs are enhanced by public/assets/js/datepicker.js (Flatpickr-based), loaded
  // separately. It runs on its own and needs no wiring here.

  // Signing out (an office PC is shared): the form drafts kept in this browser (salaries, prices…) are removed, and
  // the page being left does not save its own again
  var signingOut = false;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || !/\/portal\/logout$/.test(f.getAttribute('action') || '')) return;
    signingOut = true;
    try {
      for (var i = localStorage.length - 1; i >= 0; i--) { var k = localStorage.key(i); if (k && k.indexOf('draft:') === 0) localStorage.removeItem(k); }
    } catch (err) {}
  });

  // ---------------------------------------------------------------- record-form: wizard nav, autosave draft,
  // keyboard shortcuts, remembered last values, inline validation, duplicate check
  // (No "Leave site? / Discard changes?" questions anywhere: pages and popups close at once. What was typed is kept
  // as a draft and offered back — "Restore" — when the form is opened again.)
  document.querySelectorAll('form.record-form').forEach(function (form) {
    var draftKey = 'draft:' + (form.dataset.autosave || location.pathname);

    // remember last value: prefill empty fields marked data-remember from localStorage (create forms only)
    form.querySelectorAll('[data-remember]').forEach(function (wrap) {
      var el = wrap.querySelector('select, input'); if (!el || el.value) return;
      try {
        var v = localStorage.getItem('remember:' + wrap.dataset.remember); if (!v) return;
        if (el._pickerSet) el._pickerSet(v, localStorage.getItem('remember-label:' + wrap.dataset.remember) || v); else el.value = v;
      } catch (e) {}
    });
    form.addEventListener('change', function (e) {
      var wrap = e.target.closest('[data-remember]'); if (!wrap) return;
      try {
        localStorage.setItem('remember:' + wrap.dataset.remember, e.target.value);
        var opt = e.target.selectedOptions && e.target.selectedOptions[0];
        if (opt) localStorage.setItem('remember-label:' + wrap.dataset.remember, opt.text);
      } catch (e2) {}
    });

    // autosave draft + restore banner (created on the fly for forms that don't already have one in their markup)
    var banner = form.querySelector('[data-draft-banner]');
    function ensureBanner() {
      if (banner) return banner;
      banner = document.createElement('div');
      banner.className = 'draft-banner';
      banner.hidden = true;
      banner.innerHTML = '<span>' + (isAr ? 'لديك مسودة غير محفوظة لهذا النموذج.' : 'You have an unsaved draft of this form.') + '</span>'
        + '<button type="button" class="btn btn-sm" data-draft-restore>' + (isAr ? 'استعادة' : 'Restore') + '</button>'
        + '<button type="button" class="btn btn-sm ghost" data-draft-dismiss>' + (isAr ? 'تجاهل' : 'Dismiss') + '</button>';
      form.insertBefore(banner, form.firstChild);
      return banner;
    }
    function collectDraft() {
      var data = {};
      form.querySelectorAll('[name]').forEach(function (el) {
        // never kept in the browser: files, passwords and encrypted fields (QID, IBAN … marked data-no-draft)
        if (el.type === 'file' || el.type === 'password' || el.hasAttribute('data-no-draft') || el.name === '_csrf' || el.name === 'again') return;
        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
        data[el.name] = el.value;
      });
      return data;
    }
    function applyDraft(data) {
      Object.keys(data).forEach(function (name) {
        form.querySelectorAll('[name="' + CSS.escape(name) + '"]').forEach(function (el) {
          if (el.type === 'checkbox' || el.type === 'radio') { el.checked = (el.value === data[name]); el.dispatchEvent(new Event('change', { bubbles: true })); }
          else { el.value = data[name]; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
        });
      });
    }
    try {
      var savedDraft = localStorage.getItem(draftKey);
      if (savedDraft) {
        var parsed = JSON.parse(savedDraft);
        if (JSON.stringify(parsed) !== JSON.stringify(collectDraft())) {
          ensureBanner();
          banner.hidden = false;
          var rb = banner.querySelector('[data-draft-restore]'); if (rb) rb.addEventListener('click', function () { applyDraft(parsed); banner.hidden = true; });
          var db = banner.querySelector('[data-draft-dismiss]'); if (db) db.addEventListener('click', function () { try { localStorage.removeItem(draftKey); } catch (e) {} banner.hidden = true; });
        }
      }
    } catch (e) {}
    // A draft is kept when the form's values differ from what it held just before the user's first click or key in it.
    // Values the page's own scripts set while loading, a search typed into a picker, or opening a list are not changes.
    var saveTimer, baseline = null, submitted = false;
    function snapshot() { return JSON.stringify(collectDraft()); }
    function isDirty() { return !submitted && baseline !== null && snapshot() !== baseline; }
    ['pointerdown', 'keydown'].forEach(function (t) { form.addEventListener(t, function () { if (baseline === null) baseline = snapshot(); }, true); });
    function queueSave() {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(function () { if (signingOut) return; try { if (isDirty()) localStorage.setItem(draftKey, snapshot()); else localStorage.removeItem(draftKey); } catch (e) {} }, 500);
    }
    form.addEventListener('input', queueSave);
    form.addEventListener('change', queueSave);
    form.addEventListener('submit', function () { submitted = true; clearTimeout(saveTimer); try { localStorage.removeItem(draftKey); } catch (e) {} });
    // leaving the page (no question asked): what was just typed is kept as the draft at once
    window.addEventListener('pagehide', function () { clearTimeout(saveTimer); if (signingOut) return; try { if (isDirty()) localStorage.setItem(draftKey, snapshot()); } catch (e) {} });

    // keyboard: Enter moves to the next field (not inside a textarea or a custom dropdown); Ctrl/Cmd+S saves
    form.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); form.requestSubmit(); return; }
      if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON' || e.target.type === 'submit') return;
      if (e.target.closest('.picker, .sel, .date-field')) return;
      var focusables = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea, button')).filter(function (el) { return !el.disabled && el.type !== 'hidden' && el.offsetParent !== null; });
      var i = focusables.indexOf(e.target);
      if (i > -1 && i < focusables.length - 1) { e.preventDefault(); focusables[i + 1].focus(); }
    });

    // inline live validation: reads the same required/pattern/maxlength attributes the server already enforces, no duplicated rules
    function showLiveError(el, msg) {
      var field = el.closest('.field'); if (!field) return;
      var ex = field.querySelector('[data-live-error]'); if (ex) ex.remove();
      field.classList.toggle('field-invalid', !!msg);
      if (!msg) return;
      var div = document.createElement('div'); div.className = 'field-error'; div.setAttribute('data-live-error', '1'); div.textContent = msg;
      field.appendChild(div);
    }
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
      el.addEventListener('blur', function () {
        if (!el.willValidate || el.value === '') { if (el.validity.valueMissing) showLiveError(el, isAr ? 'هذا الحقل مطلوب' : 'This field is required'); return; }
        if (el.validity.patternMismatch || el.validity.typeMismatch) showLiveError(el, isAr ? 'قيمة غير صالحة' : 'Invalid value');
        else if (el.validity.tooLong) showLiveError(el, isAr ? 'النص طويل جدًا' : 'Too long');
        else showLiveError(el, null);
      });
    });

    // duplicate-value check for fields marked data-unique (a soft warning, never blocks saving)
    form.querySelectorAll('[data-unique]').forEach(function (wrap) {
      var el = wrap.querySelector('input'); if (!el) return;
      var resourceKey = (form.dataset.autosave || '').split(':')[0];
      var idMatch = /\/(\d+)$/.exec(form.getAttribute('action') || '');
      var t;
      el.addEventListener('input', function () {
        clearTimeout(t);
        var v = el.value.trim(); if (!v) return;
        t = setTimeout(function () {
          var url = base + 'portal/api/unique?resource=' + encodeURIComponent(resourceKey) + '&field=' + encodeURIComponent(wrap.dataset.unique) + '&value=' + encodeURIComponent(v) + (idMatch ? '&exclude_id=' + idMatch[1] : '');
          fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.exists) showLiveError(el, isAr ? 'هذه القيمة مستخدمة من قبل' : 'This value already exists'); })
            .catch(function () {});
        }, 400);
      });
    });

  });

  // wizard step navigation: a presentation layer only. Scoped to the wizard-root wrapper (not just one
  // <form>) because a step's content — e.g. the full pedigree chart — can be its own independent <form>
  // sitting next to the main record form rather than nested inside it (HTML forms can't nest).
  document.querySelectorAll('[data-wizard-root]').forEach(function (wrap) {
    var steps = wrap.querySelectorAll('[data-wizard-step]');
    if (!steps.length) return;
    var wsections = wrap.querySelectorAll('[data-wizard-section]');
    function showStep(i) {
      steps.forEach(function (s, si) { s.setAttribute('aria-selected', si === i ? 'true' : 'false'); });
      wsections.forEach(function (s) {
        var match = (+s.dataset.wizardSection === i);
        s.hidden = !match;
        if (match && s.tagName === 'DETAILS') s.open = true;
      });
      var bar = wrap.querySelector('[data-wizard-progress]'); if (bar) bar.style.width = Math.round(((i + 1) / steps.length) * 100) + '%';
    }
    steps.forEach(function (s, i) { s.addEventListener('click', function () { showStep(i); }); });
    showStep(0);
  });

  // ---------------------------------------------------------------- pedigree node: click-to-edit box
  // (used both for the sire/dam fields on the horse form and the full pedigree chart's 14 boxes)
  function pedSyncDisplay(node) {
    var nameEl = node.querySelector('.ped-display .ped-name'); if (!nameEl) return;
    var modeSel = node.querySelector('select[data-section-toggle]');
    var text = '';
    if (modeSel && modeSel.value === 'new') {
      var en = node.querySelector('input[name$="_en"]'); text = en ? en.value.trim() : '';
    } else {
      var pick = node.querySelector('select[data-picker]'); var opt = pick && pick.selectedOptions[0];
      text = opt && opt.value ? opt.text : '';
    }
    nameEl.textContent = text || (node.dataset.pedEmptyText || '');
    nameEl.classList.toggle('empty', !text);
  }
  function pedCloseAll(except) {
    document.querySelectorAll('.ped-node.editing').forEach(function (n) { if (n !== except) { n.classList.remove('editing'); pedSyncDisplay(n); } });
  }
  // A box only submits as "explicitly cleared" (vs. simply never visited) if the user actually interacted
  // with it — otherwise an untouched, server-empty grandparent+ box would wipe out an existing ancestor's
  // recorded parents on save just because this particular form happened not to show them pre-filled.
  document.addEventListener('change', function (e) {
    var field = e.target.closest('.ped-node [data-ped-pick], .ped-node select[data-section-toggle]');
    if (!field) return;
    var node = e.target.closest('.ped-node'); var t = node && node.querySelector('[data-ped-touched]');
    if (t) t.value = '1';
  });
  document.addEventListener('input', function (e) {
    var field = e.target.closest('.ped-node input[name^="new_"]');
    if (!field) return;
    var node = e.target.closest('.ped-node'); var t = node && node.querySelector('[data-ped-touched]');
    if (t) t.value = '1';
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest('#modal-root')) return; // the "+ Add new" popup lives outside the node; don't interfere
    var disp = e.target.closest('.ped-node:not(.subject) > .ped-display');
    if (disp) {
      var node = disp.closest('.ped-node');
      var already = node.classList.contains('editing');
      pedCloseAll(null);
      if (!already) { node.classList.add('editing'); var f = node.querySelector('.ped-edit input, .ped-edit select'); if (f) f.focus(); }
      return;
    }
    if (e.target.closest('[data-ped-clear]')) {
      var clearNode = e.target.closest('.ped-node');
      if (clearNode) {
        var pick = clearNode.querySelector('select[data-picker]'); if (pick && pick._pickerSet) pick._pickerSet('', '');
        clearNode.querySelectorAll('input[name^="new_"]').forEach(function (inp) { inp.value = ''; });
        var modeSel = clearNode.querySelector('select[data-section-toggle]');
        if (modeSel) { modeSel.value = 'pick'; modeSel.dispatchEvent(new Event('change', { bubbles: true })); }
        clearNode.classList.remove('ped-autofilled', 'ped-dup');
      }
      pedCloseAll(null);
      return;
    }
    if (e.target.closest('[data-ped-done]')) { pedCloseAll(null); return; }
    if (!e.target.closest('.ped-node')) pedCloseAll(null);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pedCloseAll(null); });

  // Sidebar: keeps its scroll position from page to page (it opened at the top again, leaving the pointer over another
  // item, which then looked selected), and the current item is always in view
  var side = document.querySelector('#sidebar .side-scroll');
  if (side) {
    try { var sy = parseInt(sessionStorage.getItem('sk-sidebar-y') || '', 10); if (!isNaN(sy)) side.scrollTop = sy; } catch (e) {}
    // the whole current section (its item and its sub-menu) in view, its own item first; measured from the layout
    // (offsetTop), not the screen: the menu items rise in on the first page and would be measured mid-animation
    var curLi = side.querySelector('.nav-list > li.active');
    if (curLi) {
      var top = 0, n = curLi;
      while (n && n !== side) { top += n.offsetTop; n = n.offsetParent; }
      var bottom = top + curLi.offsetHeight, pad = 12;
      if (top - pad < side.scrollTop) side.scrollTop = top - pad;
      else if (bottom + pad > side.scrollTop + side.clientHeight) side.scrollTop = Math.min(top - pad, bottom + pad - side.clientHeight);
    }
    var saveSide = function () { try { sessionStorage.setItem('sk-sidebar-y', String(side.scrollTop)); } catch (e) {} };
    side.addEventListener('click', function (e) { if (e.target.closest('a')) saveSide(); });
    window.addEventListener('pagehide', saveSide);
  }

  // Record tabs (a horse has a dozen): the tabs that fit stay in the row and the rest go under "More", which takes its
  // own place at the end of the row (it never covers a tab). The current tab always stays in the row.
  document.querySelectorAll('nav.tabs').forEach(function (tabs) {
    var wrap = document.createElement('div');
    wrap.className = 'tabs-wrap';
    tabs.parentNode.insertBefore(wrap, tabs);
    wrap.appendChild(tabs);
    var links = [].slice.call(tabs.querySelectorAll('a'));
    var more = document.createElement('button');
    more.type = 'button'; more.className = 'tabs-more'; more.hidden = true;
    more.setAttribute('aria-haspopup', 'menu'); more.setAttribute('aria-expanded', 'false');
    more.innerHTML = '<span></span><span class="tabs-more-n"></span>' + svgIcon('chevron-down');
    more.firstChild.textContent = isAr ? 'المزيد' : 'More';
    var list = document.createElement('div');
    list.className = 'dd-menu tabs-menu'; list.hidden = true; list.setAttribute('role', 'menu'); list.setAttribute('data-align', 'end');
    list.id = 'tabs-more-' + Math.random().toString(36).slice(2, 8); more.setAttribute('aria-controls', list.id);
    wrap.appendChild(more); wrap.appendChild(list);
    if (window.DD && window.DD.menu) window.DD.menu(more, list); // the one dropdown component (dropdown.js)
    var close = function () { if (more.getAttribute('aria-expanded') === 'true') more.click(); };
    var sync = function () {
      links.forEach(function (a) { a.hidden = false; });
      more.hidden = true;
      if (tabs.scrollWidth <= wrap.clientWidth + 1) { list.innerHTML = ''; close(); return; }
      more.hidden = false; // "More" takes its own place, right after the tabs shown
      var avail = wrap.clientWidth - more.getBoundingClientRect().width - 1, gap = parseFloat(getComputedStyle(tabs).columnGap) || 0;
      var width = links.map(function (a) { return a.getBoundingClientRect().width + gap; });
      var cur = links.findIndex(function (a) { return a.classList.contains('on'); });
      var used = cur >= 0 ? width[cur] : 0, shown = [];
      for (var i = 0; i < links.length; i++) { // in their order, as many as fit (the current one is counted first)
        if (i === cur) continue;
        if (used + width[i] > avail) break;
        used += width[i]; shown.push(i);
      }
      if (cur >= 0) shown.push(cur);
      list.innerHTML = '';
      links.forEach(function (a, i) {
        a.hidden = shown.indexOf(i) < 0;
        if (!a.hidden) return;
        var item = a.cloneNode(true); item.hidden = false; item.className = ''; item.setAttribute('role', 'menuitem'); item.removeAttribute('aria-selected');
        list.appendChild(item);
      });
      more.querySelector('.tabs-more-n').textContent = list.children.length;
    };
    window.addEventListener('resize', sync);
    window.addEventListener('load', sync); // the web fonts change the tab widths; Safari may lay the page out after this ran
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(sync);
    if ('ResizeObserver' in window) new ResizeObserver(sync).observe(wrap);
    sync();
  });

  // PWA: offline-capable shell for staff in the stable
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register(base + 'sw.js', { scope: base + 'portal' }).catch(function () {});
  }

  // One click = one save. After a form really starts submitting (no confirm box or validation stopped it),
  // its submit buttons show a busy state and further submits are ignored, so a slow connection or a
  // double-click can't create two bills, payments or stock moves. Forms that open in a new tab (PDF) or
  // download a file are released again after a moment; data-allow-resubmit opts a form out.
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || f.hasAttribute('data-allow-resubmit') || (f.method || 'get').toLowerCase() !== 'post') return;
    if (f.dataset.submitting === '1') { e.preventDefault(); return; }
    var submitter = e.submitter;
    setTimeout(function () {
      if (e.defaultPrevented) return;
      f.dataset.submitting = '1';
      f.setAttribute('aria-busy', 'true');
      var btns = f.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');
      btns.forEach(function (b) { b.disabled = true; });
      if (submitter && submitter.classList) submitter.classList.add('is-busy');
      if (f.target === '_blank' || f.hasAttribute('data-download')) {
        setTimeout(function () { release(f); }, 2500);
      }
    }, 0);
  });
  function release(f) {
    delete f.dataset.submitting; f.removeAttribute('aria-busy');
    f.querySelectorAll('button, input[type="submit"]').forEach(function (b) { b.disabled = false; b.classList.remove('is-busy'); });
  }
  // Back button (page restored from cache): make the forms usable again
  window.addEventListener('pageshow', function (ev) { if (ev.persisted) document.querySelectorAll('form[data-submitting]').forEach(release); });

  // Favourite star (horses): one click marks or unmarks the horse in place; if the request fails, the form posts as usual
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!(f instanceof HTMLFormElement) || !f.hasAttribute('data-fav') || !window.fetch) return;
    e.preventDefault();
    var b = f.querySelector('.fav-star');
    if (!b || b.disabled) return;
    b.disabled = true;
    fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (j) {
        var on = !!j.on, label = b.getAttribute(on ? 'data-label-on' : 'data-label-off') || '';
        // every star of this horse on the page (list row and record header) shows the new state
        document.querySelectorAll('form[data-fav][action="' + f.getAttribute('action') + '"] .fav-star').forEach(function (s) {
          s.classList.toggle('on', on); s.setAttribute('aria-pressed', on ? 'true' : 'false'); s.textContent = on ? '★' : '☆';
          if (label) { s.title = label; s.setAttribute('aria-label', label); }
        });
        b.disabled = false;
      })
      .catch(function () { b.disabled = false; f.submit(); });
  });
})();
