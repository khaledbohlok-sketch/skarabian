/* SK Arabians Management System — portal behaviour (no inline scripts: CSP script-src 'self'). */
(function () {
  'use strict';
  var base = (document.body && document.body.dataset.base) || '/';
  if (base.slice(-1) !== '/') base += '/';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var isAr = document.documentElement.dir === 'rtl';

  // Mobile navigation
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-toggle="nav"]')) { document.body.classList.toggle('nav-open'); return; }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) document.body.classList.remove('nav-open');
  });

  // Confirmation for dangerous forms
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.dataset.confirm)) e.preventDefault();
  }, true);
  // Confirmation for one button of a form (e.g. "Pay all")
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-confirm-click]');
    if (b && !window.confirm(b.dataset.confirmClick)) e.preventDefault();
  }, true);
  // Report period: choosing dates switches the preset to "Custom dates"
  document.addEventListener('change', function (e) {
    var f = e.target.closest('.report-filters'); if (!f || !e.target.matches('input[type="date"]')) return;
    var sel = f.querySelector('select[name="period"]'); if (sel) sel.value = 'custom';
  });
  // Filters fold away on phones; the button opens them
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toggle-filters]'); if (!b) return;
    var f = b.closest('form'); f.classList.toggle('open'); b.setAttribute('aria-expanded', f.classList.contains('open') ? 'true' : 'false');
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
    out.textContent = isFinite(a) && isFinite(r) ? (Math.round(a * r * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' QAR' : '—';
  });

  // ---------------------------------------------------------------- Searchable pickers
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
    var clear = document.createElement('button'); clear.type = 'button'; clear.className = 'picker-clear'; clear.textContent = '×'; clear.setAttribute('aria-label', 'Clear');
    sel.hidden = true; sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(input); wrap.appendChild(clear); wrap.appendChild(list); wrap.appendChild(sel);
    var timer, active = -1, items = [];
    function setValue(id, label) {
      var o = sel.querySelector('option[value="' + CSS.escape(String(id)) + '"]');
      if (!o) { o = document.createElement('option'); o.value = id; sel.appendChild(o); }
      o.text = label; sel.value = id; input.value = id === '' ? '' : label; close();
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
    function render() {
      list.innerHTML = '';
      if (!items.length) { var li = document.createElement('li'); li.className = 'empty-opt'; li.textContent = isAr ? 'لا توجد نتائج' : 'No matches'; list.appendChild(li); }
      items.forEach(function (it, i) {
        var li = document.createElement('li'); li.setAttribute('role', 'option'); li.textContent = it.label; li.dataset.i = i;
        if (i === active) li.setAttribute('aria-selected', 'true');
        list.appendChild(li);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true');
    }
    function search() {
      var url = base + 'portal/api/picker/' + encodeURIComponent(source) + '?q=' + encodeURIComponent(input.value) + (params ? '&' + params : '') + depParams();
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { results: [] }; })
        .then(function (d) { items = d.results || []; active = -1; render(); }).catch(function () {});
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 180); });
    input.addEventListener('focus', function () { if (!input.disabled) { clearTimeout(timer); timer = setTimeout(search, 50); } });
    input.addEventListener('keydown', function (e) {
      if (list.hidden && (e.key === 'ArrowDown')) { search(); return; }
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter') { if (!list.hidden && items[active]) { setValue(items[active].id, items[active].label); e.preventDefault(); } }
      else if (e.key === 'Escape') { close(); }
    });
    list.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); var it = items[+li.dataset.i]; setValue(it.id, it.label); } });
    input.addEventListener('blur', function () { setTimeout(function () { close(); var c = sel.selectedOptions[0]; input.value = c && c.value ? c.text : ''; }, 150); });
    clear.addEventListener('click', function () { setValue('', ''); input.focus(); });
    sel._pickerSet = setValue;
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
        if (other) other.addEventListener('change', function () { if (sel.value) setValue('', ''); });
      });
    }
  }
  document.querySelectorAll('select[data-picker]').forEach(enhancePicker);

  // "+ Add new" opens the create form in a modal; the new record is selected on save
  var modal = document.getElementById('modal'), pendingTarget = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add-new]'); if (!b || !modal) return;
    pendingTarget = document.getElementById(b.dataset.target);
    var u = b.dataset.addNew; modal.querySelector('iframe').src = u + (u.indexOf('?') >= 0 ? '&' : '?') + 'popup=1';
    modal.hidden = false;
  });
  document.addEventListener('click', function (e) { if (e.target.closest('[data-close-modal]') && modal) { modal.hidden = true; modal.querySelector('iframe').src = 'about:blank'; } });
  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin || !e.data || e.data.type !== 'sk-created') return;
    if (pendingTarget && pendingTarget._pickerSet) pendingTarget._pickerSet(String(e.data.id), e.data.label);
    if (modal) { modal.hidden = true; modal.querySelector('iframe').src = 'about:blank'; }
  });
  var done = document.querySelector('[data-popup-done]');
  if (done && window.parent !== window) window.parent.postMessage({ type: 'sk-created', id: done.dataset.id, label: done.dataset.label }, window.location.origin);

  // One-tap approvals on mobile: post via fetch and remove the card
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-ajax-approval]'); if (!f || e.defaultPrevented) return;
    e.preventDefault();
    var card = f.closest('.approval-card'); var fd = new FormData(f);
    fetch(f.action, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok && card) { card.style.opacity = '.4'; card.querySelector('.approval-buttons').innerHTML = '<span class="badge badge-' + (d.status === 'approved' ? 'approved' : 'rejected') + '">' + d.label + '</span>'; } else { alert(d.message || 'Error'); } })
      .catch(function () { f.submit(); });
  });

  // Copy to clipboard helper
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(function () { b.textContent = '✓'; });
  });

  // PWA: offline-capable shell for staff in the stable
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register(base + 'sw.js', { scope: base + 'portal' }).catch(function () {});
  }
})();
