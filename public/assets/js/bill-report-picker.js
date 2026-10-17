/* Two related, independent pieces that only act where their own markup exists (both are harmless no-ops
 * otherwise, like every other shared script in this app):
 *  - BillReportPicker: the "Add bills" modal used by the Studio Financial Report editor (ED.fin in
 *    studio-editor.js) — a filterable, checkbox-selectable table of bills, built on the shared Modal shell.
 *  - the Bills list page's bulk-select bar ("N selected — add to financial report"), gated on #bulk-bar.
 */
(function () {
  'use strict';
  var isAr = document.documentElement.dir === 'rtl';
  var base = (document.body && document.body.dataset.base) || '/';
  if (base.slice(-1) !== '/') base += '/';
  // amounts as money() prints them: English "4,500" / "4,500.50" (the QR label is in the total line), Arabic two decimals
  var money = function (v) { var n = parseFloat(v); n = isNaN(n) ? 0 : n; var d = !isAr && Math.round(Math.abs(n) * 100) % 100 === 0 ? 0 : 2; return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: 2 }); };
  // dates as fmt_date() prints them: "16 Oct 2026" / "16 أكتوبر 2026"
  var MON = isAr ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
    : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var day = function (s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + ' ' + MON[+m[2] - 1] + ' ' + m[1] : (s || ''); };

  var T = isAr
    ? { search: 'بحث…', type: 'النوع', status: 'الحالة', from: 'من', to: 'إلى', showCancelled: 'إظهار الفواتير الملغاة', selectAll: 'تحديد الكل (المُصفّى)',
        clear: 'مسح', addSelected: 'إضافة المحدد', already: 'مضافة بالفعل', noBills: 'لا توجد فواتير مطابقة.', loading: 'جارٍ التحميل…',
        all: 'الكل', income: 'إيراد', expense: 'مصروف', liability: 'التزام', unpaid: 'معتمدة وغير مسددة بالكامل', pendingAll: 'مسودة أو بانتظار الاعتماد',
        number: 'رقم الفاتورة', date: 'التاريخ', party: 'المورد / الجهة', amount: 'المبلغ', paid: 'المسدد', remaining: 'المتبقي', due: 'الاستحقاق',
        selectedLabel: ':n محدد — :amount ر.ق' }
    : { search: 'Search…', type: 'Type', status: 'Status', from: 'From', to: 'To', showCancelled: 'Show cancelled bills', selectAll: 'Select all (filtered)',
        clear: 'Clear', addSelected: 'Add selected', already: 'Already added', noBills: 'No matching bills.', loading: 'Loading…',
        all: 'All', income: 'Income', expense: 'Expense', liability: 'Liability', unpaid: 'Approved, not fully paid', pendingAll: 'Draft or pending approval',
        number: 'Bill No.', date: 'Date', party: 'Supplier / payee', amount: 'Amount', paid: 'Paid', remaining: 'Remaining', due: 'Due',
        selectedLabel: ':n selected — :amount QR' };

  // ------------------------------------------------------------------ the "Add bills" picker
  function open(opts) {
    opts = opts || {};
    var already = opts.already || {}; // { billId: true }
    var selected = {}; // newly-ticked ids this session, billId => {amount, paid}
    var rows = [];
    var entry = window.Modal.custom({ title: opts.title || T.addSelected, size: 'lg', footer: true });
    var body = entry.el.querySelector('.sk-modal-body');
    body.classList.add('fin-picker-body');
    body.innerHTML =
      '<div class="fin-picker-filters">' +
        '<input type="text" class="fp-q" placeholder="' + T.search + '">' +
        '<select class="fp-type"><option value="">' + T.type + ': ' + T.all + '</option><option value="expense">' + T.expense + '</option><option value="income">' + T.income + '</option><option value="liability">' + T.liability + '</option></select>' +
        '<select class="fp-status"><option value="">' + T.status + ': ' + T.all + '</option><option value="unpaid">' + T.unpaid + '</option><option value="pending_all">' + T.pendingAll + '</option></select>' +
        '<input type="date" class="fp-from" title="' + T.from + '">' +
        '<input type="date" class="fp-to" title="' + T.to + '">' +
        '<label class="fp-cancelled"><input type="checkbox" class="fp-show-cancelled">' + T.showCancelled + '</label>' +
      '</div>' +
      '<div class="fin-picker-table-wrap"><table class="fin-picker-table"><thead><tr>' +
        '<th></th><th>' + T.number + '</th><th>' + T.date + '</th><th>' + T.party + '</th><th class="num">' + T.amount + '</th><th class="num">' + T.paid + '</th><th class="num">' + T.remaining + '</th><th>' + T.due + '</th>' +
      '</tr></thead><tbody class="fp-rows"><tr><td colspan="8" class="fp-empty">' + T.loading + '</td></tr></tbody></table></div>';
    var footer = entry.el.querySelector('.sk-modal-footer');
    footer.innerHTML =
      '<button type="button" class="btn" data-act="selectAll">' + T.selectAll + '</button>' +
      '<button type="button" class="btn" data-act="clear">' + T.clear + '</button>' +
      '<span class="fp-count"></span>' +
      '<button type="button" class="btn btn-primary" data-act="add" disabled>' + T.addSelected + '</button>';
    var tbody = body.querySelector('.fp-rows');
    var countEl = footer.querySelector('.fp-count');
    var addBtn = footer.querySelector('[data-act="add"]');

    if (opts.presetFilters) {
      if (opts.presetFilters.from) body.querySelector('.fp-from').value = opts.presetFilters.from;
      if (opts.presetFilters.to) body.querySelector('.fp-to').value = opts.presetFilters.to;
      if (opts.presetFilters.type) body.querySelector('.fp-type').value = opts.presetFilters.type;
      if (opts.presetFilters.status) body.querySelector('.fp-status').value = opts.presetFilters.status;
    }
    if (window.DD && window.DD.scan) window.DD.scan(body);

    function updateCount() {
      var ids = Object.keys(selected), total = 0;
      ids.forEach(function (id) { total += selected[id].amount - selected[id].paid; });
      countEl.textContent = ids.length ? T.selectedLabel.replace(':n', ids.length).replace(':amount', money(total)) : '';
      addBtn.disabled = !ids.length;
    }
    function rowHtml(r) {
      var inReport = !!already[r.id];
      var remaining = (parseFloat(r.amount) || 0) - (parseFloat(r.paid) || 0);
      var checked = inReport || !!selected[r.id];
      return '<tr data-id="' + r.id + '"' + (inReport ? ' class="fp-already"' : '') + '>' +
        '<td><input type="checkbox" class="fp-pick"' + (checked ? ' checked' : '') + (inReport ? ' disabled title="' + T.already + '"' : '') + '></td>' +
        '<td class="nowrap">' + esc(r.number) + '</td><td class="nowrap">' + esc(day(r.date)) + '</td><td>' + esc(r.party || '') + '</td>' +
        '<td class="num nowrap">' + money(r.amount) + '</td><td class="num nowrap">' + money(r.paid) + '</td><td class="num nowrap">' + money(remaining) + '</td><td class="nowrap">' + esc(day(r.due)) + '</td></tr>';
    }
    function esc(v) { var d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; }
    function render() {
      tbody.innerHTML = rows.length ? rows.map(rowHtml).join('') : '<tr><td colspan="8" class="fp-empty">' + T.noBills + '</td></tr>';
      if (opts.selectAllOnOpen) {
        rows.forEach(function (r) { if (!already[r.id]) selected[r.id] = { amount: +r.amount || 0, paid: +r.paid || 0 }; });
        opts.selectAllOnOpen = false; // only the very first load of a "Monthly report" shortcut auto-ticks
        tbody.querySelectorAll('tr:not(.fp-already) .fp-pick').forEach(function (c) { c.checked = true; });
      }
      updateCount();
    }
    function query() {
      var p = new URLSearchParams();
      var qv = body.querySelector('.fp-q').value.trim(); if (qv) p.set('q', qv);
      var tv = body.querySelector('.fp-type').value; if (tv) p.set('type', tv);
      var sv = body.querySelector('.fp-status').value; if (sv) p.set('status', sv);
      var fv = body.querySelector('.fp-from').value; if (fv) p.set('from', fv);
      var tov = body.querySelector('.fp-to').value; if (tov) p.set('to', tov);
      if (body.querySelector('.fp-show-cancelled').checked) p.set('show_cancelled', '1');
      return p.toString();
    }
    var reqSeq = 0;
    function load() {
      tbody.innerHTML = '<tr><td colspan="8" class="fp-empty">' + T.loading + '</td></tr>';
      var seq = ++reqSeq;
      fetch(opts.apiUrl + '?' + query(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { rows: [] }; })
        .then(function (d) { if (seq !== reqSeq) return; rows = d.rows || []; render(); })
        .catch(function () { if (seq !== reqSeq) return; rows = []; render(); });
    }
    var debounce; function soon() { clearTimeout(debounce); debounce = setTimeout(load, 220); }
    body.querySelectorAll('.fp-q').forEach(function (i) { i.addEventListener('input', soon); });
    body.querySelectorAll('.fp-type, .fp-status, .fp-from, .fp-to, .fp-show-cancelled').forEach(function (i) { i.addEventListener('change', load); });
    tbody.addEventListener('change', function (e) {
      var c = e.target.closest('.fp-pick'); if (!c) return;
      var tr = c.closest('tr'), id = tr.dataset.id, r = rows.find(function (x) { return String(x.id) === id; });
      if (!r) return;
      if (c.checked) selected[id] = { amount: +r.amount || 0, paid: +r.paid || 0 }; else delete selected[id];
      updateCount();
    });
    footer.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var act = b.dataset.act;
      if (act === 'selectAll') {
        rows.forEach(function (r) { if (!already[r.id]) selected[r.id] = { amount: +r.amount || 0, paid: +r.paid || 0 }; });
        tbody.querySelectorAll('tr:not(.fp-already) .fp-pick').forEach(function (c) { c.checked = true; });
        updateCount();
      } else if (act === 'clear') {
        selected = {};
        tbody.querySelectorAll('tr:not(.fp-already) .fp-pick').forEach(function (c) { c.checked = false; });
        updateCount();
      } else if (act === 'add') {
        var ids = Object.keys(selected).map(Number);
        if (!ids.length) return;
        b.disabled = true;
        Promise.resolve(opts.onAdd(ids)).then(function () { window.Modal.close(entry, true); }).catch(function () { b.disabled = false; });
      }
    });
    load();
    return entry;
  }

  window.BillReportPicker = { open: open };

  // ------------------------------------------------------------------ Bills list: bulk-select bar
  var bar = document.getElementById('bulk-bar');
  if (bar) {
    var countText = document.getElementById('bulk-count-text');
    var countTpl = bar.dataset.countTpl || ':n';
    // the list's own words for "tick at least one" (bills, payments...)
    var needSelection = bar.dataset.needSelection || (isAr ? 'اختر سطراً واحداً على الأقل.' : 'Select at least one row.');
    var selectAll = document.getElementById('bulk-all');
    function chks() { return Array.prototype.slice.call(document.querySelectorAll('.bulk-chk')); }
    function refresh() {
      var n = chks().filter(function (c) { return c.checked; }).length;
      bar.hidden = n === 0;
      if (countText) countText.textContent = countTpl.replace(':n', n);
      if (selectAll) selectAll.checked = n > 0 && n === chks().length;
    }
    document.addEventListener('change', function (e) {
      if (e.target.classList && e.target.classList.contains('bulk-chk')) refresh();
      else if (e.target === selectAll) { chks().forEach(function (c) { c.checked = selectAll.checked; }); refresh(); }
    });

    var targetSel = document.getElementById('bulk-fin-target');
    var newFields = document.getElementById('bulk-fin-new-fields');
    var existingSel = document.getElementById('bulk-fin-existing');
    // the shared dropdown replaces the <select> with its own button, so the wrapping span is what gets shown/hidden
    var existingWrap = document.getElementById('bulk-fin-existing-wrap') || existingSel;
    var draftsLoaded = false;
    function loadDrafts() {
      if (draftsLoaded) return; draftsLoaded = true;
      fetch(base + 'portal/bills/draft-reports', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { rows: [] }; })
        .then(function (d) {
          var rows = d.rows || [];
          existingSel.innerHTML = '';
          // Report titles are typed by users: set as text, never parsed as HTML
          (rows.length ? rows : [null]).forEach(function (r) {
            var o = document.createElement('option');
            o.value = r ? r.id : '';
            o.textContent = r ? (r.title || ('#' + r.id)) + ' (' + r.line_count + ')' : (isAr ? 'لا توجد مسودات' : 'No drafts yet');
            existingSel.appendChild(o);
          });
          if (window.DD && window.DD.scan) window.DD.scan(existingSel.parentNode);
        });
    }
    if (targetSel) {
      targetSel.addEventListener('change', function () {
        var existing = targetSel.value === 'existing';
        newFields.hidden = existing; existingWrap.hidden = !existing;
        existingSel.disabled = !existing; // "New report": the hidden draft choice is not sent
        if (existing) loadDrafts();
      });
    }
    // Pay / Delete: the ticked bills go along as ids[]. Both forms ask first (data-confirm, modal.js), which cancels
    // this first submit and sends the form again once confirmed — the ids are added on that second one.
    var payForm = document.getElementById('bulk-pay-form');
    var payOpen = document.getElementById('bulk-pay-open');
    if (payForm && payOpen) {
      payOpen.addEventListener('click', function () { payForm.hidden = !payForm.hidden; });
      var payCancel = document.getElementById('bulk-pay-cancel');
      if (payCancel) payCancel.addEventListener('click', function () { payForm.hidden = true; });
      // no account chosen: say so before the "are you sure" question (the click comes before the submit)
      var paySubmit = payForm.querySelector('button[type="submit"]');
      if (paySubmit) paySubmit.addEventListener('click', function (e) {
        if (payForm.elements.account_id && !payForm.elements.account_id.value) {
          e.preventDefault();
          if (window.Modal) window.Modal.toast(payForm.dataset.needAccount, 'error');
        }
      });
    }
    bar.querySelectorAll('form.bulk-ids').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var ids = chks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) { e.preventDefault(); if (window.Modal) window.Modal.toast(needSelection, 'error'); return; }
        f.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
        ids.forEach(function (id) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; f.appendChild(i); });
      });
    });

    var finForm = document.getElementById('bulk-fin-form');
    if (finForm) {
      finForm.addEventListener('submit', function (e) {
        var ids = chks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
        if (!ids.length) { e.preventDefault(); if (window.Modal) window.Modal.toast(needSelection, 'error'); return; }
        if (targetSel && targetSel.value === 'existing' && !existingSel.value) {
          e.preventDefault();
          if (window.Modal) window.Modal.toast(isAr ? 'لا توجد مسودة لاختيارها.' : 'No draft to add to.', 'error');
          return;
        }
        if (existingSel) existingSel.disabled = !(targetSel && targetSel.value === 'existing');
        ids.forEach(function (id) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; finForm.appendChild(i); });
      });
    }
  }
})();
