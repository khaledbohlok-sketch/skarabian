/* Shared custom dropdown — the ONE component used everywhere a <select> appears: the admin portal
 * (plain lists; the server-backed searchable "picker" widget lives in portal.js and reuses window.DD
 * below), the public website, and anything injected into the page later (e.g. the Studio document
 * editor's dynamically-built sidebar/grid selects — picked up automatically via the MutationObserver
 * at the bottom, no per-caller wiring needed). Loaded before portal.js / site.js. */
(function () {
  'use strict';
  var isAr = document.documentElement.dir === 'rtl';

  // ---------------------------------------------------------------- shared engine (exposed as window.DD)
  // Keeps exactly one dropdown panel open at a time, and flips a panel above its field when there isn't
  // room below. portal.js's AJAX "picker" widget reuses this same registry so both variants play nicely
  // together (opening a plain select closes an open picker, and vice versa).
  var openClose = null, openPanel = null, openTrigger = null, openPlace = null;
  function closeOpen() { if (openClose) { var c = openClose; openClose = null; openPanel = null; openTrigger = null; openPlace = null; c(); } }
  // Re-registering the SAME dropdown's own close callback must be a no-op, not a close-then-reopen: the
  // AJAX "picker" widget's render() calls this on every render (loading state, then again once results
  // arrive, then again on every keystroke/arrow-key) while it's already the open one. Without this guard,
  // that second call sees its own close function still registered from the first and immediately invokes
  // it — hiding the panel the instant real results come in, which looked like searching returned nothing.
  // panel/trigger are tracked alongside the close callback so the scroll handler below can tell a scroll
  // INSIDE the open menu apart from the page scrolling underneath it (see that handler for why).
  // placeFn: how to re-place this panel when the page scrolls (selects and pickers use position(), menus placeMenu()).
  function registerOpen(fn, panelEl, triggerEl, placeFn) {
    if (openClose === fn) { openPanel = panelEl || openPanel; openTrigger = triggerEl || openTrigger; return; }
    closeOpen(); openClose = fn; openPanel = panelEl || null; openTrigger = triggerEl || null; openPlace = placeFn || null;
  }
  function isMobile() { return window.matchMedia && window.matchMedia('(max-width: 640px)').matches; }
  // The panel is always a direct child of <body> (see enhanceSelect/enhanceMultiSelect/enhancePicker) —
  // never a DOM descendant of its trigger — so it can never inherit the trigger's own hover/active/focus
  // colors, never gets clipped by an ancestor's overflow, and never gets trapped under the mobile
  // backdrop by z-index/stacking quirks. Being detached means its position has to be computed in JS
  // instead of plain CSS `position:absolute` relative to a parent; this does that, viewport-relative
  // (position:fixed), flipping above the trigger when there isn't room below.
  function position(trigger, panel) {
    panel.classList.remove('dd-above');
    if (isMobile()) { // bottom sheet, full width, anchored to the viewport bottom — see CSS
      panel.style.position = ''; panel.style.top = ''; panel.style.bottom = ''; panel.style.left = ''; panel.style.right = ''; panel.style.minWidth = '';
      return;
    }
    panel.style.position = 'fixed';
    var r = trigger.getBoundingClientRect();
    var panelH = Math.min(panel.scrollHeight || 320, 320);
    var below = window.innerHeight - r.bottom, above = r.top;
    // Explicit inline 'auto' (not just clearing it) so it always wins over the stylesheet's old
    // .dd-above{bottom:...} rule below — that rule was written for a position:absolute-relative-to-trigger
    // layout and computes nonsense now that the panel is position:fixed off a viewport-relative top: with
    // both top and bottom set to real lengths and height:auto, the browser solves height = viewport - top
    // - bottom, which goes deeply negative whenever the trigger sits near the bottom of the viewport —
    // collapsing the whole panel (and its scrollable list) to a sliver a few px tall. Needing the open
    // menu's own list/search box to be genuinely scrollable (the fix above) is what surfaced this: the
    // 14-box pedigree editor's ancestor pickers sit low enough on the page to flip 'above', so their list
    // was rendering ~10px tall instead of ~280px, making every option below the first unclickable.
    panel.style.bottom = 'auto';
    if (below < panelH && above > below) { panel.classList.add('dd-above'); panel.style.top = Math.max(8, r.top - panelH - 4) + 'px'; }
    else { panel.style.top = (r.bottom + 4) + 'px'; }
    panel.style.minWidth = r.width + 'px';
    if (isAr) { panel.style.right = (window.innerWidth - r.right) + 'px'; panel.style.left = 'auto'; }
    else { panel.style.left = r.left + 'px'; panel.style.right = 'auto'; }
  }
  // Arabic letter variants a typed search should treat as equivalent (أ/إ/آ → ا, ة → ه, ى → ي) — mirrors
  // the same normalization Pickers::search() applies server-side, for local (static-option) search.
  function arNorm(s) { return String(s).replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }
  // A detached, position:fixed panel doesn't move with the page, so a scroll while it's open would leave
  // it floating away from its trigger unless repositioned. This listener uses capture so it also sees
  // scroll events fired deep in the page (scroll doesn't bubble, but capture reaches the target anyway on
  // the way down) — including the open menu's OWN list/search box. That used to be the bug: scrolling the
  // menu's own options (mouse wheel, dragging its scrollbar, or a touch drag) fires a native 'scroll'
  // event on the list, and this listener closed the menu on ANY scroll without checking where it came
  // from. Now: a scroll that originates inside the open panel is ignored outright (it's just the list's
  // own content moving); a scroll anywhere else (the page, a modal body, any scrollable ancestor of the
  // field) repositions the panel under its field instead of closing it, and only closes once the field has
  // actually scrolled out of the viewport, since there's nothing left to anchor to at that point.
  document.addEventListener('scroll', function (e) {
    if (!openClose) return;
    if (openPanel && (e.target === openPanel || (openPanel.contains && openPanel.contains(e.target)))) return;
    if (openTrigger && document.body.contains(openTrigger)) {
      var r = openTrigger.getBoundingClientRect();
      if (r.bottom > 0 && r.top < window.innerHeight && r.right > 0 && r.left < window.innerWidth) { (openPlace || position)(openTrigger, openPanel); return; }
    }
    closeOpen();
  }, true);
  // scan() is reassigned below, once it exists; callers that rebuild a chunk of the DOM themselves (e.g. the
  // Studio editor replacing #editor's innerHTML wholesale) can call window.DD.scan() right away instead of
  // relying only on the MutationObserver's own (correct, but less immediate) pass.
  window.DD = { registerOpen: registerOpen, closeOpen: closeOpen, isMobile: isMobile, position: position, arNorm: arNorm, scan: function (root) { scan(root); }, menu: function (t, p) { bindMenu(t, p); } };

  // ---------------------------------------------------------------- Menus: the user menu, "New…", the bell, tabs "More"
  // Markup: <button aria-haspopup="menu" aria-expanded="false" data-dd="ID"> + <div class="dd-menu" id="ID" role="menu" hidden
  // [data-align="end"]> with [role="menuitem"] links / buttons. The panel moves under <body> (never clipped, never inside the
  // top bar's blur layer) and is placed below its button — above it when there is no room — inside the screen, with a
  // max-height and its own scroll. Closes on outside click, Esc, choosing an item, Tab; arrows, Home/End, first letter.
  function placeMenu(trigger, panel) {
    var m = 8, gap = 6, r = trigger.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = window.innerHeight;
    panel.style.maxHeight = ''; panel.style.left = '0px'; panel.style.top = '0px';
    var pw = panel.offsetWidth, ph = panel.scrollHeight;
    var below = vh - r.bottom - gap - m, above = r.top - gap - m;
    var up = ph > below && above > below, room = Math.max(120, up ? above : below);
    if (ph > room) { panel.style.maxHeight = room + 'px'; ph = room; }
    var alignRight = (panel.getAttribute('data-align') === 'end') !== isAr; // the inline end is the left side in Arabic
    var left = alignRight ? r.right - pw : r.left;
    left = Math.min(Math.max(m, left), vw - pw - m);
    panel.style.left = Math.round(left) + 'px';
    panel.style.top = Math.round(Math.max(m, up ? r.top - gap - ph : r.bottom + gap)) + 'px';
    panel.classList.toggle('dd-above', up);
  }
  function bindMenu(trigger, panel) {
    if (!trigger || !panel || trigger.getAttribute('data-dd-bound')) return;
    trigger.setAttribute('data-dd-bound', '1');
    if (panel.parentNode !== document.body) document.body.appendChild(panel);
    var opened = false, hideTimer;
    function items() {
      return Array.prototype.filter.call(panel.querySelectorAll('[role^="menuitem"]'), function (el) { return !el.disabled && !el.hidden && el.getClientRects().length > 0; });
    }
    function close(returnFocus) {
      if (!opened) return;
      opened = false;
      panel.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false');
      if (openClose === close) { openClose = null; openPanel = null; openTrigger = null; openPlace = null; }
      clearTimeout(hideTimer); hideTimer = setTimeout(function () { if (!opened) panel.hidden = true; }, 160);
      if (returnFocus === true) trigger.focus();
    }
    function open(focusWhich) {
      if (opened) return;
      registerOpen(close, panel, trigger, placeMenu);
      opened = true; clearTimeout(hideTimer);
      panel.hidden = false; trigger.setAttribute('aria-expanded', 'true');
      placeMenu(trigger, panel);
      requestAnimationFrame(function () { if (opened) panel.classList.add('open'); });
      var list = items();
      if (focusWhich === 'first' && list[0]) list[0].focus();
      else if (focusWhich === 'last' && list.length) list[list.length - 1].focus();
    }
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      if (opened) close(); else open(e.detail === 0 ? 'first' : null); // e.detail 0: Enter / Space
    });
    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (opened) { var l = items(); if (l[0]) l[0].focus(); } else open('first'); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); open('last'); }
      else if (e.key === 'Escape' && opened) { e.preventDefault(); e.stopPropagation(); close(true); }
    });
    panel.addEventListener('keydown', function (e) {
      var list = items(), i = list.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (list[i + 1] || list[0]).focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); (list[i - 1] || list[list.length - 1]).focus(); }
      else if (e.key === 'Home') { e.preventDefault(); if (list[0]) list[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); if (list.length) list[list.length - 1].focus(); }
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); }
      else if (e.key === 'Tab') { close(); trigger.focus(); } // the browser then moves on from the button, as if the menu were in place
      else if (e.key.length === 1 && /\S/.test(e.key) && !e.ctrlKey && !e.metaKey) {
        var k = arNorm(e.key.toLowerCase());
        for (var j = 1; j <= list.length; j++) {
          var it = list[(i + j) % list.length];
          if (arNorm(it.textContent.trim().toLowerCase()).indexOf(k) === 0) { it.focus(); break; }
        }
      }
    });
    // choosing an item closes the menu (a theme choice keeps it open, to see the change)
    panel.addEventListener('click', function (e) {
      var it = e.target.closest('[role="menuitem"]');
      if (it && panel.contains(it)) close();
    });
    document.addEventListener('pointerdown', function (e) { if (opened && !panel.contains(e.target) && !trigger.contains(e.target)) close(); }, true);
    window.addEventListener('resize', function () { if (opened) placeMenu(trigger, panel); });
  }
  function scanMenus(root) {
    (root || document).querySelectorAll('[data-dd]').forEach(function (t) { bindMenu(t, document.getElementById(t.getAttribute('data-dd'))); });
  }
  scanMenus();

  // ---------------------------------------------------------------- Plain selects (static options, no server round-trip)
  // A custom-styled button + list replaces the OS-drawn dropdown. Lists longer than ~8 options grow a
  // local search box at the top of the panel (filtering the options already on the page — no network
  // call); shorter lists keep plain type-ahead (typing jumps to a match, like a native <select>).
  var SEARCH_THRESHOLD = 8;
  function enhanceSelect(sel) {
    if (sel.dataset.enhanced) return; sel.dataset.enhanced = '1';
    if (sel.multiple) { enhanceMultiSelect(sel); return; }
    var isChip = (' ' + sel.className + ' ').indexOf(' chip ') !== -1;
    var wrap = document.createElement('div'); wrap.className = ('sel ' + sel.className).trim(); // keeps e.g. the public site's "chip" filter-pill variant
    var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'sel-btn';
    btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('role', 'combobox');
    if (sel.disabled) btn.disabled = true;
    var label = document.createElement('span'); label.className = 'sel-btn-label'; btn.appendChild(label);
    // Chip pills show "Field: value" plus their own small clear (×) once a value is picked, so the filter
    // stays identifiable in a compact pill without a separate visible <label> next to it (regular, full-
    // width form selects already have one, so they just show the plain value).
    var clearBtn = null;
    if (isChip) {
      clearBtn = document.createElement('button'); clearBtn.type = 'button'; clearBtn.className = 'sel-chip-clear'; clearBtn.hidden = true;
      clearBtn.textContent = '×'; clearBtn.setAttribute('aria-label', isAr ? 'مسح' : 'Clear');
    }
    var panel = document.createElement('div'); panel.className = 'sel-panel'; panel.hidden = true;
    var searchBox = null, searchInput = null;
    var hasSearch = sel.options.length > SEARCH_THRESHOLD;
    if (hasSearch) {
      searchBox = document.createElement('div'); searchBox.className = 'sel-search';
      searchInput = document.createElement('input'); searchInput.type = 'text'; searchInput.autocomplete = 'off';
      searchInput.placeholder = isAr ? 'بحث…' : 'Search…'; searchInput.setAttribute('aria-label', isAr ? 'بحث' : 'Search');
      searchBox.appendChild(searchInput); panel.appendChild(searchBox);
    }
    var list = document.createElement('ul'); list.className = 'sel-list'; list.setAttribute('role', 'listbox'); panel.appendChild(list);
    var backdrop = document.createElement('div'); backdrop.className = 'dd-sheet-backdrop'; backdrop.hidden = true;
    sel.hidden = true; sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(btn); if (clearBtn) wrap.appendChild(clearBtn); wrap.appendChild(sel);
    document.body.appendChild(panel); document.body.appendChild(backdrop);
    var active = -1, typeBuf = '', typeTimer;
    function opts() { return Array.prototype.slice.call(sel.options); }
    // The placeholder ("Age", "Breed"...) is option[0] with value="" — it's the field's own resting
    // label, never a real choice, so it's excluded from what the panel offers; picking is done through
    // the list, going back to "no choice" is done through the clear (×) or (non-chip) a cleared <select>.
    function pickable() { return opts().filter(function (o) { return o.value !== ''; }); }
    function visible() {
      var q = hasSearch && searchInput ? arNorm(searchInput.value.trim().toLowerCase()) : '';
      if (!q) return pickable();
      return pickable().filter(function (o) { return arNorm(o.text.toLowerCase()).indexOf(q) !== -1; });
    }
    function sync() {
      var cur = sel.selectedOptions[0];
      var hasVal = !!(cur && cur.value);
      var placeholder = sel.options[0] ? sel.options[0].text : '';
      label.textContent = isChip ? (hasVal ? placeholder + ': ' + cur.text : placeholder) : (cur ? cur.text : placeholder);
      btn.classList.toggle('dd-placeholder', !hasVal);
      sel.classList.toggle('dd-has-value', hasVal);
      wrap.classList.toggle('dd-has-value', hasVal);
      if (clearBtn) clearBtn.hidden = !hasVal;
    }
    function setValue(v) {
      sel.value = v; sync();
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function close() {
      if (panel.hidden) return;
      panel.hidden = true; wrap.classList.remove('open'); panel.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); active = -1;
      backdrop.hidden = true; document.body.classList.remove('dd-sheet-open');
      if (openClose === close) openClose = null;
    }
    function render() {
      list.innerHTML = '';
      var vis = visible();
      if (!vis.length) { var e = document.createElement('li'); e.className = 'empty-opt'; e.textContent = isAr ? 'لا توجد نتائج' : 'No results'; list.appendChild(e); }
      vis.forEach(function (o) {
        var i = opts().indexOf(o);
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = i;
        if (o.selected && o.value !== '') { li.setAttribute('aria-selected', 'true'); li.innerHTML = '<span class="dd-check">✓</span>'; li.appendChild(document.createTextNode(o.text)); }
        else li.textContent = o.text;
        if (i === active) li.classList.add('active');
        list.appendChild(li);
      });
    }
    function open() {
      if (btn.disabled) return;
      active = opts().findIndex(function (o) { return o.selected; });
      render(); panel.hidden = false; wrap.classList.add('open'); panel.classList.add('open'); btn.setAttribute('aria-expanded', 'true');
      registerOpen(close, panel, btn);
      if (isMobile()) { backdrop.hidden = false; document.body.classList.add('dd-sheet-open'); }
      position(btn, panel);
      if (hasSearch && searchInput) searchInput.focus();
      var cur = list.querySelector('li[aria-selected="true"]'); if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'nearest' });
    }
    function choose(i) {
      var o = opts()[i]; if (!o) return;
      setValue(o.value); close(); btn.focus();
    }
    function moveActive(dir) {
      var vis = visible(); if (!vis.length) return;
      var idxInVis = Math.max(0, vis.findIndex(function (o) { return opts().indexOf(o) === active; }));
      idxInVis = Math.min(vis.length - 1, Math.max(0, idxInVis + dir));
      active = opts().indexOf(vis[idxInVis]); render();
      var li = list.querySelector('li.active'); if (li && li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
    }
    btn.addEventListener('click', function () { if (panel.hidden) open(); else close(); });
    btn.addEventListener('keydown', function (e) {
      if (panel.hidden && ['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) !== -1) { e.preventDefault(); open(); return; }
      if (panel.hidden) {
        // Short lists with no search box: typing jumps to the first matching option (classic <select> behavior).
        if (!hasSearch && e.key.length === 1 && e.key !== ' ') {
          clearTimeout(typeTimer); typeBuf += e.key.toLowerCase(); typeTimer = setTimeout(function () { typeBuf = ''; }, 600);
          var m = pickable().findIndex(function (o) { return o.text.toLowerCase().indexOf(typeBuf) === 0; });
          if (m !== -1) setValue(pickable()[m].value);
        }
        return;
      }
      if (e.key === 'ArrowDown') { moveActive(1); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { moveActive(-1); e.preventDefault(); }
      else if (e.key === 'Enter' || e.key === ' ') { choose(active); e.preventDefault(); }
      else if (e.key === 'Escape') { close(); e.preventDefault(); }
      else if (e.key === 'Tab') { close(); }
    });
    if (hasSearch && searchInput) {
      searchInput.addEventListener('input', function () { active = -1; render(); });
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { moveActive(1); e.preventDefault(); }
        else if (e.key === 'ArrowUp') { moveActive(-1); e.preventDefault(); }
        else if (e.key === 'Enter') { if (active !== -1) { choose(active); } else { var vis = visible(); if (vis.length === 1) choose(opts().indexOf(vis[0])); } e.preventDefault(); }
        else if (e.key === 'Escape') { close(); btn.focus(); e.preventDefault(); }
      });
    }
    // preventDefault unconditionally — not just when a li is hit — so dragging the native scrollbar, or
    // mousedown on blank padding between items, never steals focus from btn/searchInput as a side effect
    // (that focus-shift is mousedown's own default action; canceling it is what keeps the panel open). A
    // touch drag that ends over an option must scroll, not accidentally select it.
    var touchMoved = false;
    list.addEventListener('touchstart', function () { touchMoved = false; }, { passive: true });
    list.addEventListener('touchmove', function () { touchMoved = true; }, { passive: true });
    list.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (touchMoved) { touchMoved = false; return; }
      var li = e.target.closest('li[data-i]'); if (li) choose(+li.dataset.i);
    });
    if (clearBtn) clearBtn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); setValue(''); });
    backdrop.addEventListener('click', close);
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target) && !panel.contains(e.target)) close(); });
    btn.addEventListener('blur', function () { if (panel.hidden) sel.dispatchEvent(new Event('blur')); });
    sel.addEventListener('change', sync);
    sync();
  }

  // ---------------------------------------------------------------- Multi-select (chips in the field, same panel look)
  function enhanceMultiSelect(sel) {
    var wrap = document.createElement('div'); wrap.className = 'sel sel-multi';
    var field = document.createElement('div'); field.className = 'sel-btn sel-multi-field'; field.tabIndex = 0;
    field.setAttribute('role', 'combobox'); field.setAttribute('aria-haspopup', 'listbox'); field.setAttribute('aria-expanded', 'false');
    var chips = document.createElement('div'); chips.className = 'sel-chips'; field.appendChild(chips);
    if (sel.disabled) field.tabIndex = -1;
    var panel = document.createElement('div'); panel.className = 'sel-panel'; panel.hidden = true;
    var hasSearch = sel.options.length > SEARCH_THRESHOLD;
    var searchInput = null;
    if (hasSearch) {
      var searchBox = document.createElement('div'); searchBox.className = 'sel-search';
      searchInput = document.createElement('input'); searchInput.type = 'text'; searchInput.autocomplete = 'off';
      searchInput.placeholder = isAr ? 'بحث…' : 'Search…';
      searchBox.appendChild(searchInput); panel.appendChild(searchBox);
    }
    var list = document.createElement('ul'); list.className = 'sel-list'; list.setAttribute('role', 'listbox'); list.setAttribute('aria-multiselectable', 'true'); panel.appendChild(list);
    var backdrop = document.createElement('div'); backdrop.className = 'dd-sheet-backdrop'; backdrop.hidden = true;
    sel.hidden = true; sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(field); wrap.appendChild(sel);
    document.body.appendChild(panel); document.body.appendChild(backdrop);
    function opts() { return Array.prototype.slice.call(sel.options); }
    function selected() { return opts().filter(function (o) { return o.selected; }); }
    function syncChips() {
      chips.innerHTML = '';
      var sel2 = selected();
      if (!sel2.length) { var ph = document.createElement('span'); ph.className = 'sel-chip-placeholder'; ph.textContent = sel.dataset.placeholder || (isAr ? 'اختر…' : 'Choose…'); chips.appendChild(ph); }
      sel2.forEach(function (o) {
        var chip = document.createElement('span'); chip.className = 'sel-chip';
        var txt = document.createElement('span'); txt.textContent = o.text; chip.appendChild(txt);
        var x = document.createElement('button'); x.type = 'button'; x.className = 'sel-chip-x'; x.textContent = '×'; x.setAttribute('aria-label', (isAr ? 'إزالة ' : 'Remove ') + o.text);
        x.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); o.selected = false; syncChips(); render(); sel.dispatchEvent(new Event('input', { bubbles: true })); sel.dispatchEvent(new Event('change', { bubbles: true })); });
        chip.appendChild(x); chips.appendChild(chip);
      });
    }
    function close() {
      if (panel.hidden) return;
      panel.hidden = true; wrap.classList.remove('open'); panel.classList.remove('open'); field.setAttribute('aria-expanded', 'false');
      backdrop.hidden = true; document.body.classList.remove('dd-sheet-open');
      if (searchInput) searchInput.value = '';
      if (openClose === close) openClose = null;
    }
    function visible() {
      var q = hasSearch && searchInput ? arNorm(searchInput.value.trim().toLowerCase()) : '';
      if (!q) return opts();
      return opts().filter(function (o) { return arNorm(o.text.toLowerCase()).indexOf(q) !== -1; });
    }
    function render() {
      list.innerHTML = '';
      var vis = visible();
      if (!vis.length) { var e = document.createElement('li'); e.className = 'empty-opt'; e.textContent = isAr ? 'لا توجد نتائج' : 'No results'; list.appendChild(e); }
      vis.forEach(function (o) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = opts().indexOf(o);
        li.setAttribute('aria-selected', o.selected ? 'true' : 'false');
        li.innerHTML = '<span class="dd-check">' + (o.selected ? '✓' : '') + '</span>';
        li.appendChild(document.createTextNode(o.text));
        list.appendChild(li);
      });
    }
    function open() {
      if (sel.disabled) return;
      render(); panel.hidden = false; wrap.classList.add('open'); panel.classList.add('open'); field.setAttribute('aria-expanded', 'true');
      registerOpen(close, panel, field);
      if (isMobile()) { backdrop.hidden = false; document.body.classList.add('dd-sheet-open'); }
      position(field, panel);
      if (hasSearch && searchInput) searchInput.focus();
    }
    function toggle(i) {
      var o = opts()[i]; if (!o) return;
      o.selected = !o.selected; syncChips(); render();
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    field.addEventListener('click', function () { if (panel.hidden) open(); else close(); });
    field.addEventListener('keydown', function (e) {
      if (panel.hidden && ['ArrowDown', 'Enter', ' '].indexOf(e.key) !== -1) { e.preventDefault(); open(); }
      else if (e.key === 'Escape') { close(); }
    });
    if (searchInput) searchInput.addEventListener('input', render);
    var touchMoved = false;
    list.addEventListener('touchstart', function () { touchMoved = false; }, { passive: true });
    list.addEventListener('touchmove', function () { touchMoved = true; }, { passive: true });
    list.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (touchMoved) { touchMoved = false; return; }
      var li = e.target.closest('li[data-i]'); if (li) toggle(+li.dataset.i);
    });
    backdrop.addEventListener('click', close);
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target) && !panel.contains(e.target)) close(); });
    field.addEventListener('blur', function () { if (panel.hidden) sel.dispatchEvent(new Event('blur')); });
    sel.addEventListener('change', syncChips);
    syncChips();
  }

  function scan(root) {
    // Flatpickr builds its own month <select> inside .flatpickr-calendar (and may rebuild it on every
    // render) — converting that into this widget broke it outright (wrong z-index stacking relative to
    // the calendar, and its own change-sync no longer lines up). Flatpickr's month/year navigation has
    // its own purpose-built picker in datepicker.js; nothing in here is meant to touch its internals.
    (root || document).querySelectorAll('select').forEach(function (sel) { if (!sel.dataset.picker && !sel.closest('.flatpickr-calendar')) enhanceSelect(sel); });
  }
  scan();
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openClose) closeOpen(); });

  // Picks up <select>s added after page load (e.g. the Studio document editor's sidebar/grid fields,
  // built from JS templates when the user switches document type or adds a line) — no caller wiring needed.
  var SEL = 'select';
  if (window.MutationObserver) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var nodes = muts[i].addedNodes;
        for (var j = 0; j < nodes.length; j++) {
          var n = nodes[j];
          if (n.nodeType === 1 && ((n.matches && n.matches(SEL)) || (n.querySelector && n.querySelector(SEL)))) { scan(document); return; }
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
