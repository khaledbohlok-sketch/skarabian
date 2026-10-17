/* Shared date/time picker — Flatpickr, themed to match the portal, Arabic/English aware.
 * Every date / datetime-local / time input in the system is enhanced generically from here; new forms
 * get it automatically since it runs on page load against the standard input types, no per-form wiring.
 * Native values stay in yyyy-mm-dd (/ yyyy-mm-ddTHH:mm) so the backend and database are untouched;
 * a visible "alt" text input shows "5 Oct 2026" (typing dd/mm/yyyy works too) (Qatar week: Saturday first), Western digits throughout.
 * Dates are always "today" in Qatar (UTC+3, no DST), regardless of the visitor's own clock or timezone. */
(function () {
  'use strict';
  if (typeof flatpickr === 'undefined') return;
  var isAr = document.documentElement.dir === 'rtl';
  var base = (document.body && document.body.dataset.base) || '/';
  if (base.slice(-1) !== '/') base += '/';

  // Qatar week starts Saturday, for both languages (the Arabic locale file already has this; English doesn't)
  flatpickr.l10ns.default.firstDayOfWeek = 6;
  if (flatpickr.l10ns.ar) flatpickr.l10ns.ar.firstDayOfWeek = 6;
  var locale = isAr && flatpickr.l10ns.ar ? 'ar' : 'default';

  var T = isAr
    ? { phDate: 'يوم/شهر/سنة', phTime: 'س:د', phRange: 'أي تاريخ', today: 'اليوم', clear: 'مسح', yesterday: 'أمس', last7: 'آخر 7 أيام', thisMonth: 'هذا الشهر', lastMonth: 'الشهر الماضي', thisYear: 'هذه السنة' }
    : { phDate: 'dd/mm/yyyy', phTime: 'hh:mm', phRange: 'Any date', today: 'Today', clear: 'Clear', yesterday: 'Yesterday', last7: 'Last 7 days', thisMonth: 'This month', lastMonth: 'Last month', thisYear: 'This year' };

  var YEAR_MIN = 1980;
  // shown as "5 Oct 2026"; Arabic writes the month in full (flatpickr's Arabic short months are numbers): "5 أكتوبر 2026"
  var ALT = isAr ? 'j F Y' : 'j M Y';

  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d ? d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) : ''; }
  function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function dayStart(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }

  /** "Now" in Qatar, as a Date whose LOCAL getters (getFullYear/getMonth/getDate — what flatpickr's own
   * today-marking and default view read) report Qatar's wall-clock date, whatever the browser's timezone is. */
  function qatarNow() {
    var p = {};
    new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Qatar', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hourCycle: 'h23' })
      .formatToParts(new Date()).forEach(function (part) { p[part.type] = +part.value; });
    return new Date(p.year, p.month - 1, p.day, p.hour, p.minute, p.second);
  }

  var ICON = '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>';
  var CLOCK = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

  /** Wraps the original input in a .date-field box with a calendar icon and a clear (×) button, both wired to the flatpickr instance created on it. Call BEFORE flatpickr() so the generated altInput lands inside the wrapper. */
  function wrap(input) {
    var box = document.createElement('div'); box.className = 'date-field';
    if (input.getAttribute('style')) box.setAttribute('style', input.getAttribute('style'));
    input.parentNode.insertBefore(box, input);
    var icon = document.createElement('span'); icon.className = 'fp-icon'; icon.innerHTML = input.type === 'time' ? CLOCK : ICON;
    var clearBtn = document.createElement('button'); clearBtn.type = 'button'; clearBtn.className = 'fp-clear-btn'; clearBtn.textContent = '×'; clearBtn.setAttribute('aria-label', T.clear);
    box.appendChild(icon); box.appendChild(input); box.appendChild(clearBtn);
    return { box: box, icon: icon, clearBtn: clearBtn };
  }

  function syncHasValue(box, fp) { box.classList.toggle('fp-has-value', fp.selectedDates.length > 0); }

  /** The visible field is now fp.altInput, not the original (hidden) input — required/disabled must move
   * with it, or the browser would try to validate/block an element the user can no longer see or reach. */
  function carryState(input, fp) {
    // an empty field shows what to type instead of a bare icon
    // (flatpickr has already turned the original input into type="hidden": a time-only field is told by noCalendar)
    if (!fp.altInput.placeholder) { fp.altInput.placeholder = input.type === 'time' || fp.config.noCalendar ? T.phTime : (fp.config.mode === 'range' ? T.phRange : T.phDate); }
    if (input.required) { fp.altInput.required = true; }
    if (input.disabled) { fp.altInput.disabled = true; }
  }

  // ---------------------------------------------------------------- typed text
  /** Parses what the user typed into the visible field. Accepts d/m/yyyy and d/m/yy (7/10/26 = 7 Oct 2026,
   * 2-digit years read as 20xx), with / . - or spaces as separators; an optional " hh:mm" for date-times;
   * "hh:mm" alone for time-only fields. Returns null for anything that isn't a real, in-range date. */
  function parseTyped(txt, fp) {
    if (fp.config.noCalendar) {
      var t = txt.match(/^(\d{1,2}):(\d{2})$/);
      if (!t || +t[1] > 23 || +t[2] > 59) return null;
      // Keep the year flatpickr is showing: a different year makes setDate() rebuild a month dropdown that a
      // time-only picker doesn't have, which throws.
      return new Date(fp.currentYear, fp.currentMonth, 1, +t[1], +t[2]);
    }
    var m = txt.match(/^(\d{1,2})[\/.\-\s]+(\d{1,2})[\/.\-\s]+(\d{4}|\d{2})(?:[\sT,]+(\d{1,2}):(\d{2}))?$/);
    var named = null;
    if (!m) { // "5 Oct 2026" (the shown format) or "5 October 2026", in the page's language or English
      named = txt.match(/^(\d{1,2})\s+([^\d\s.,]+)\.?,?\s+(\d{4}|\d{2})(?:[\sT,]+(\d{1,2}):(\d{2}))?$/);
      if (!named) return null;
      var mi = monthIndex(named[2], fp);
      if (mi < 0) return null;
      m = [named[0], named[1], String(mi + 1), named[3], named[4], named[5]];
    }
    var day = +m[1], month = +m[2] - 1, year = m[3].length === 2 ? 2000 + +m[3] : +m[3];
    var hh = m[4] ? +m[4] : 0, mi = m[5] ? +m[5] : 0;
    if (hh > 23 || mi > 59) return null;
    var d = new Date(year, month, day, hh, mi);
    if (d.getFullYear() !== year || d.getMonth() !== month || d.getDate() !== day) return null;
    if (fp.config.minDate && dayStart(d) < dayStart(fp.config.minDate)) return null;
    if (fp.config.maxDate && dayStart(d) > dayStart(fp.config.maxDate)) return null;
    return d;
  }

  var EN_MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
  function monthIndex(word, fp) {
    var w = String(word).toLowerCase();
    var lists = [fp.l10n.months.shorthand, fp.l10n.months.longhand];
    for (var l = 0; l < lists.length; l++) for (var i = 0; i < 12; i++) if (String(lists[l][i]).toLowerCase() === w) return i;
    for (var j = 0; j < 12; j++) if (w.indexOf(EN_MONTHS[j]) === 0) return j;
    return -1;
  }

  function parseRange(txt, fp) {
    var parts = txt.split(/\s+(?:to|إلى|-)\s+/);
    var a = parseTyped(parts[0].trim(), fp);
    if (!a) return null;
    if (parts.length === 1) return [a];
    var b = parseTyped(parts[1].trim(), fp);
    return b ? [a, b] : null;
  }

  /** Runs when the calendar closes or the visible field loses focus. Valid typed text becomes the value
   * (reformatted to dd/mm/yyyy); anything else is cleared, so stray characters never sit in the field. */
  function settleTypedText(sd, ds, fp) {
    if (!fp.altInput) return;
    var txt = fp.altInput.value.trim(), current = fp.selectedDates;
    if (!txt) { if (current.length) fp.clear(); return; }
    // the text is what the picker itself wrote ("5 Oct 2026"): nothing to read
    if (current.length && txt === current.map(function (d) { return fp.formatDate(d, fp.config.altFormat); }).join(fp.l10n.rangeSeparator)) return;
    var parsed = fp.config.mode === 'range' ? parseRange(txt, fp) : (function () { var d = parseTyped(txt, fp); return d ? [d] : null; })();
    if (!parsed) { fp.clear(); return; }
    var same = parsed.length === current.length && parsed.every(function (d, i) { return d.getTime() === current[i].getTime(); });
    fp.setDate(parsed, !same);
  }

  /** Flatpickr parses typed text on every keystroke and again on blur, and it rolls impossible dates over
   * (31/02/2026 becomes 3 March) before anything else sees them. So typing, Enter and blur on the visible
   * field are stopped here, from a capture listener on the wrapper (which runs before the input's own
   * handlers) and read only by settleTypedText, which rejects what isn't a real date instead. */
  function guardTypedText(fp, boxEl) {
    if (!fp.altInput) return;
    boxEl.addEventListener('input', function (e) { if (e.target === fp.altInput) e.stopPropagation(); }, true);
    boxEl.addEventListener('keydown', function (e) {
      if (e.target !== fp.altInput || e.key !== 'Enter') return;
      e.preventDefault(); e.stopPropagation(); settleTypedText(null, null, fp);
    }, true);
    boxEl.addEventListener('blur', function (e) {
      if (e.target !== fp.altInput) return;
      e.stopPropagation(); settleTypedText(null, null, fp);
    }, true);
  }

  // ---------------------------------------------------------------- footer + config
  function addFooter(fp, opts) {
    var div = document.createElement('div'); div.className = 'fp-footer';
    var todayBtn = document.createElement('button'); todayBtn.type = 'button'; todayBtn.textContent = T.today;
    todayBtn.addEventListener('click', function () { fp.setDate(qatarNow(), true); if (!opts || !opts.range) fp.close(); });
    var clearBtn = document.createElement('button'); clearBtn.type = 'button'; clearBtn.className = 'fp-clear'; clearBtn.textContent = T.clear;
    clearBtn.addEventListener('click', function () { fp.clear(); fp.close(); });
    div.appendChild(todayBtn); div.appendChild(clearBtn);
    fp.calendarContainer.appendChild(div);
  }

  /** Keeps the custom month button's text in step with flatpickr's own month, however it changed (our list,
   * the arrows, the keyboard). Flatpickr calls these hooks as (selectedDates, dateStr, instance). */
  function syncMonthBtn(sd, ds, fp) {
    var btn = fp && fp.calendarContainer && fp.calendarContainer.querySelector('.fp-month-btn');
    if (btn) btn.textContent = fp.l10n.months.longhand[fp.currentMonth];
  }

  function baseConfig() {
    return {
      locale: locale, allowInput: true, altInput: true, altInputClass: 'fp-alt', disableMobile: true,
      position: isAr ? 'auto right' : 'auto left',
      now: qatarNow(),
      onMonthChange: [syncMonthBtn], onYearChange: [syncMonthBtn],
      onClose: [settleTypedText],
    };
  }

  // ---------------------------------------------------------------- month + year lists
  /** The month and year in flatpickr's header are kept (flatpickr drives them through its own
   * changeMonth()/changeYear(), which also honour minDate/maxDate). This adds the two things it doesn't
   * have: a styled month list and a scrollable, typeable year list. Both are floating panels from the
   * shared dropdown engine (window.DD), so they get the same look, single-open behaviour, scroll safety,
   * viewport flipping and phone bottom-sheet as every other dropdown in the system. */
  function monthDisabled(fp, i) {
    var first = new Date(fp.currentYear, i, 1), last = new Date(fp.currentYear, i + 1, 0);
    return !!((fp.config.minDate && last < dayStart(fp.config.minDate)) || (fp.config.maxDate && first > dayStart(fp.config.maxDate)));
  }

  function yearDisabled(fp, y) {
    return !!((fp.config.minDate && y < fp.config.minDate.getFullYear()) || (fp.config.maxDate && y > fp.config.maxDate.getFullYear()));
  }

  /** These panels sit under <body>, outside flatpickr's calendar, so flatpickr's own outside-click handler
   * would otherwise close the calendar the moment a month or year is picked. Keeping their pointer events
   * from bubbling up to the document stops that without changing what the panels themselves do. */
  function shield(el) {
    ['mousedown', 'click', 'touchstart', 'touchend'].forEach(function (t) { el.addEventListener(t, function (e) { e.stopPropagation(); }, { passive: true }); });
  }

  function makePanel(extraClass) {
    var panel = document.createElement('div'); panel.className = 'sel-panel fp-pop ' + extraClass; panel.hidden = true;
    var list = document.createElement('ul'); list.className = 'sel-list'; list.setAttribute('role', 'listbox');
    panel.appendChild(list); document.body.appendChild(panel);
    shield(panel);
    return { panel: panel, list: list };
  }

  function touchGuard(list) {
    var moved = false;
    list.addEventListener('touchstart', function () { moved = false; }, { passive: true });
    list.addEventListener('touchmove', function () { moved = true; }, { passive: true });
    return function () { if (moved) { moved = false; return true; } return false; };
  }

  function enhanceMonthYear(fp) {
    if (fp.config.noCalendar || fp.calendarContainer.querySelector('.fp-month-btn')) return;

    var backdrop = document.createElement('div'); backdrop.className = 'fp-pop-backdrop'; backdrop.hidden = true;
    document.body.appendChild(backdrop); shield(backdrop);

    function show(p, trigger, close) {
      p.panel.hidden = false; p.panel.classList.add('open'); trigger.setAttribute('aria-expanded', 'true');
      window.DD.registerOpen(close, p.panel, trigger);
      if (window.DD.isMobile()) { backdrop.hidden = false; document.body.classList.add('dd-sheet-open'); }
      window.DD.position(trigger, p.panel);
    }
    function hide(p, trigger) {
      if (p.panel.hidden) return;
      p.panel.hidden = true; p.panel.classList.remove('open'); trigger.setAttribute('aria-expanded', 'false');
      backdrop.hidden = true; document.body.classList.remove('dd-sheet-open');
    }

    // ---- month: the native month <select> is hidden by CSS; this button replaces it
    var monthSel = fp.monthsDropdownContainer;
    var monthBtn = document.createElement('button'); monthBtn.type = 'button'; monthBtn.className = 'fp-month-btn';
    monthBtn.setAttribute('aria-haspopup', 'listbox'); monthBtn.setAttribute('aria-expanded', 'false');
    monthSel.parentNode.insertBefore(monthBtn, monthSel);
    var mp = makePanel('fp-month-pop');
    var mTouch = touchGuard(mp.list);
    function renderMonths() {
      mp.list.innerHTML = '';
      fp.l10n.months.longhand.forEach(function (name, i) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = i;
        if (monthDisabled(fp, i)) { li.className = 'empty-opt'; li.setAttribute('aria-disabled', 'true'); }
        if (i === fp.currentMonth) { li.setAttribute('aria-selected', 'true'); li.innerHTML = '<span class="dd-check">✓</span>'; li.appendChild(document.createTextNode(name)); }
        else li.textContent = name;
        mp.list.appendChild(li);
      });
    }
    function closeMonth() { hide(mp, monthBtn); }
    monthBtn.addEventListener('click', function () {
      if (!mp.panel.hidden) return closeMonth();
      renderMonths(); show(mp, monthBtn, closeMonth);
      var cur = mp.list.querySelector('li[aria-selected="true"]'); if (cur) cur.scrollIntoView({ block: 'nearest' });
    });
    mp.list.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (mTouch()) return;
      var li = e.target.closest('li[data-i]'); if (!li || li.getAttribute('aria-disabled') === 'true') return;
      fp.changeMonth(+li.dataset.i, false); closeMonth();
    });
    syncMonthBtn(null, null, fp);

    // ---- year: flatpickr's own year input stays (typing and its arrows keep working); a click opens the list
    var yearInput = fp.currentYearElement;
    var yearMax = qatarNow().getFullYear() + 10;
    var yp = makePanel('fp-year-pop');
    var yearSearch = document.createElement('div'); yearSearch.className = 'sel-search';
    var yearType = document.createElement('input'); yearType.type = 'text'; yearType.inputMode = 'numeric'; yearType.autocomplete = 'off';
    yearType.placeholder = isAr ? 'اكتب السنة…' : 'Type a year…'; yearType.setAttribute('aria-label', isAr ? 'السنة' : 'Year');
    yearSearch.appendChild(yearType); yp.panel.insertBefore(yearSearch, yp.list);
    var yTouch = touchGuard(yp.list);
    function renderYears() {
      yp.list.innerHTML = '';
      for (var y = YEAR_MIN; y <= yearMax; y++) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.y = y;
        if (yearDisabled(fp, y)) { li.className = 'empty-opt'; li.setAttribute('aria-disabled', 'true'); }
        if (y === fp.currentYear) { li.setAttribute('aria-selected', 'true'); li.innerHTML = '<span class="dd-check">✓</span>'; li.appendChild(document.createTextNode(String(y))); }
        else li.textContent = String(y);
        yp.list.appendChild(li);
      }
    }
    function closeYear() { hide(yp, yearInput); yearType.value = ''; }
    yearInput.addEventListener('click', function () {
      if (!yp.panel.hidden) return closeYear();
      renderYears(); show(yp, yearInput, closeYear);
      var cur = yp.list.querySelector('li[aria-selected="true"]'); if (cur) cur.scrollIntoView({ block: 'center' });
      setTimeout(function () { yearType.focus(); }, 0); // flatpickr refocuses its own year input during this same click
    });
    yp.list.addEventListener('mousedown', function (e) {
      e.preventDefault();
      if (yTouch()) return;
      var li = e.target.closest('li[data-y]'); if (!li || li.getAttribute('aria-disabled') === 'true') return;
      fp.changeYear(+li.dataset.y); closeYear();
    });
    yearType.addEventListener('input', function () {
      var li = yp.list.querySelector('li[data-y="' + parseInt(yearType.value, 10) + '"]');
      if (li) li.scrollIntoView({ block: 'nearest' });
    });
    yearType.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var y = parseInt(yearType.value, 10);
        if (y >= YEAR_MIN && y <= yearMax && !yearDisabled(fp, y)) { fp.changeYear(y); closeYear(); }
      } else if (e.key === 'Escape') { closeYear(); }
    });

    backdrop.addEventListener('click', function () { closeMonth(); closeYear(); });
    // Escape closes the open list and nothing else: flatpickr would otherwise also close the calendar.
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || (mp.panel.hidden && yp.panel.hidden)) return;
      e.stopPropagation(); closeMonth(); closeYear();
    }, true);
    document.addEventListener('click', function (e) {
      if (!mp.panel.hidden && !mp.panel.contains(e.target) && !monthBtn.contains(e.target)) closeMonth();
      if (!yp.panel.hidden && !yp.panel.contains(e.target) && e.target !== yearInput) closeYear();
    });
  }

  // ---------------------------------------------------------------- plain date
  function enhanceDate(input) {
    if (input.dataset.fpDone) return; input.dataset.fpDone = '1';
    var w = wrap(input);
    var cfg = baseConfig();
    cfg.dateFormat = 'Y-m-d'; cfg.altFormat = ALT;
    if (input.getAttribute('min')) cfg.minDate = input.getAttribute('min');
    if (input.getAttribute('max')) cfg.maxDate = input.getAttribute('max');
    var inReport = !!input.closest('.report-filters');
    cfg.onReady = function (sd, ds, fp) { addFooter(fp); enhanceMonthYear(fp); guardTypedText(fp, w.box); syncHasValue(w.box, fp); };
    cfg.onChange = function (sd, ds, fp) {
      syncHasValue(w.box, fp);
      if (inReport) { var sel = input.closest('form').querySelector('select[name="period"]'); if (sel) sel.value = 'custom'; }
    };
    var fp = flatpickr(input, cfg);
    carryState(input, fp);
    w.icon.addEventListener('click', function () { fp.open(); });
    w.clearBtn.addEventListener('click', function () { fp.clear(); });
  }

  // ---------------------------------------------------------------- date + time
  function enhanceDateTime(input) {
    if (input.dataset.fpDone) return; input.dataset.fpDone = '1';
    var w = wrap(input);
    var cfg = baseConfig();
    cfg.enableTime = true; cfg.time_24hr = true;
    cfg.dateFormat = 'Y-m-d\\TH:i'; cfg.altFormat = ALT + ' H:i';
    if (input.getAttribute('min')) cfg.minDate = input.getAttribute('min');
    if (input.getAttribute('max')) cfg.maxDate = input.getAttribute('max');
    cfg.onReady = function (sd, ds, fp) { addFooter(fp); enhanceMonthYear(fp); guardTypedText(fp, w.box); syncHasValue(w.box, fp); };
    cfg.onChange = function (sd, ds, fp) { syncHasValue(w.box, fp); };
    var fp = flatpickr(input, cfg);
    carryState(input, fp);
    w.icon.addEventListener('click', function () { fp.open(); });
    w.clearBtn.addEventListener('click', function () { fp.clear(); });
  }

  // ---------------------------------------------------------------- time only
  function enhanceTime(input) {
    if (input.dataset.fpDone) return; input.dataset.fpDone = '1';
    var w = wrap(input);
    var cfg = baseConfig();
    cfg.enableTime = true; cfg.noCalendar = true; cfg.time_24hr = true;
    cfg.dateFormat = 'H:i'; cfg.altFormat = 'H:i';
    cfg.onReady = function (sd, ds, fp) { guardTypedText(fp, w.box); syncHasValue(w.box, fp); };
    cfg.onChange = function (sd, ds, fp) { syncHasValue(w.box, fp); };
    var fp = flatpickr(input, cfg);
    carryState(input, fp);
    w.icon.addEventListener('click', function () { fp.open(); });
    w.clearBtn.addEventListener('click', function () { fp.clear(); });
  }

  // ---------------------------------------------------------------- linked "from/to" range (filter bars & reports)
  function shortcuts(fp) {
    var today = qatarNow();
    var presets = [
      [T.today, [today, today]],
      [T.yesterday, [addDays(today, -1), addDays(today, -1)]],
      [T.last7, [addDays(today, -6), today]],
      [T.thisMonth, [new Date(today.getFullYear(), today.getMonth(), 1), today]],
      [T.lastMonth, [new Date(today.getFullYear(), today.getMonth() - 1, 1), new Date(today.getFullYear(), today.getMonth(), 0)]],
      [T.thisYear, [new Date(today.getFullYear(), 0, 1), today]],
    ];
    var panel = document.createElement('div'); panel.className = 'fp-shortcuts';
    presets.forEach(function (p) {
      var btn = document.createElement('button'); btn.type = 'button'; btn.textContent = p[0];
      btn.addEventListener('click', function () { fp.setDate(p[1], true); });
      panel.appendChild(btn);
    });
    fp.calendarContainer.classList.add('fp-with-shortcuts');
    fp.calendarContainer.insertBefore(panel, fp.calendarContainer.firstChild);
  }

  function enhanceRange(fromInput, toInput) {
    if (fromInput.dataset.fpDone) return; fromInput.dataset.fpDone = '1'; toInput.dataset.fpDone = '1';
    var w = wrap(fromInput);
    toInput.style.display = 'none'; // still submits with the form, just not shown — fromInput's altInput shows "d/m/Y to d/m/Y"
    // its own label goes too (a lone "To" looked like a missing box); the one box is labelled "From – To".
    // Filter bars wrap each box in its <label>; the report form puts the label beside it in a .field.
    var toBox = toInput.closest('label') || toInput.closest('.field'), fromBox = fromInput.closest('label') || fromInput.closest('.field');
    if (toBox && toBox !== fromBox && toBox.querySelectorAll('input, select, textarea').length === 1) {
      var caption = function (box) { return box && (box.tagName === 'LABEL' ? box.querySelector('span') : box.querySelector('label')); };
      var fromText = caption(fromBox), toText = caption(toBox);
      if (fromText && toText && toText.textContent.trim()) fromText.textContent = fromText.textContent.trim() + ' – ' + toText.textContent.trim();
      toBox.style.display = 'none';
      if (fromBox) fromBox.classList.add('f-range'); // wide enough for both dates
    }
    var fromVal = fromInput.value ? new Date(fromInput.value + 'T00:00:00') : null;
    var toVal = toInput.value ? new Date(toInput.value + 'T00:00:00') : null;
    var cfg = baseConfig();
    cfg.mode = 'range'; cfg.dateFormat = 'Y-m-d'; cfg.altFormat = ALT;
    if (fromVal) cfg.defaultDate = toVal ? [fromVal, toVal] : [fromVal];
    var inReport = !!fromInput.closest('.report-filters');
    cfg.onReady = function (sd, ds, fp) { addFooter(fp, { range: true }); shortcuts(fp); enhanceMonthYear(fp); guardTypedText(fp, w.box); syncHasValue(w.box, fp); };
    cfg.onChange = function (selectedDates, ds, fp) {
      fromInput.value = selectedDates[0] ? iso(selectedDates[0]) : '';
      toInput.value = selectedDates[1] ? iso(selectedDates[1]) : (selectedDates[0] ? iso(selectedDates[0]) : '');
      syncHasValue(w.box, fp);
      if (inReport) { var sel = fromInput.closest('form').querySelector('select[name="period"]'); if (sel) sel.value = 'custom'; }
    };
    var fp = flatpickr(fromInput, cfg);
    carryState(fromInput, fp);
    w.icon.addEventListener('click', function () { fp.open(); });
    w.clearBtn.addEventListener('click', function () { fp.clear(); fromInput.value = ''; toInput.value = ''; });
  }

  function run() {
    document.querySelectorAll('form.filters, form.report-filters').forEach(function (form) {
      var from = form.querySelector('input[type="date"][name="from"]');
      var to = form.querySelector('input[type="date"][name="to"]');
      if (from && to) enhanceRange(from, to);
    });
    document.querySelectorAll('input[type="date"]').forEach(enhanceDate);
    document.querySelectorAll('input[type="datetime-local"]').forEach(enhanceDateTime);
    document.querySelectorAll('input[type="time"]').forEach(enhanceTime);
  }
  run();

  // New rows added dynamically (e.g. "+ Add row" in a table) — re-scan only when a relevant input actually appears
  var SEL = 'input[type="date"], input[type="datetime-local"], input[type="time"]';
  if (window.MutationObserver) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var nodes = muts[i].addedNodes;
        for (var j = 0; j < nodes.length; j++) {
          var n = nodes[j];
          if (n.nodeType === 1 && (n.matches(SEL) || n.querySelector(SEL))) { run(); return; }
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();

