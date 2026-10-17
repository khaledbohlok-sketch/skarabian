/*
 * SK Arabian Studio — several documents at once (no inline scripts: CSP script-src 'self').
 *  - "Print several horses" form (form[data-profiles-batch]): the tick buttons, the counter, and the editor's own empty
 *    Horse Profile ("base", built exactly as studio-editor.js initial() builds a new one) sent with the form, so each
 *    profile issued here is stored complete and prints like one issued by hand.
 *  - Batch print view (#pages[data-batch]): draws every document on its own pages, logs the print, opens the dialog.
 */
(function () {
  'use strict';
  var SK = window.SKStudio, DATA = document.getElementById('studio-data');
  if (!SK || !DATA) return;
  var CFG = JSON.parse(DATA.textContent), clone = SK.helpers.clone;

  // ---- the form
  var form = document.querySelector('form[data-profiles-batch]');
  if (form) {
    // an empty Horse Profile as the editor starts one: the defaults cleaned of their sample values, the template's
    // own wording kept, and the few settings the cleaning empties put back (studio-editor.js initial())
    var D = SK.defaults().profile || {}, tpl = Object.assign(clone(D), CFG.tpl || {});
    var base = SK.clean('profile', tpl);
    if (base === tpl || !Object.keys(base).length) base = tpl;
    ['no', 'ref', 'rno'].forEach(function (k) { if (k in base) base[k] = ''; });
    ['window', 'filter', 'output', 'showQid', 'title', 'titleAr', 'status', 'salCur', 'checkResult'].forEach(function (k) {
      if (k in D && (base[k] === '' || base[k] == null) && D[k] !== '') base[k] = clone(D[k]);
    });
    form.querySelector('input[name="base"]').value = JSON.stringify(base);

    var boxes = [].slice.call(form.querySelectorAll('input[name="horses[]"]'));
    var parts = [].slice.call(form.querySelectorAll('input[name="parts[]"]'));
    var counter = form.querySelector('[data-pb-count]'), submit = form.querySelector('[data-pb-submit]');
    var max = +form.dataset.max || 100;
    var sync = function () {
      var n = boxes.filter(function (b) { return b.checked; }).length;
      if (counter) counter.textContent = '(' + (counter.dataset.tpl || '{n}').replace('{n}', n) + ')';
      if (form.dataset.confirmTpl) form.dataset.confirm = form.dataset.confirmTpl.replace('{n}', n);
      if (submit) submit.disabled = n === 0 || n > max || !parts.some(function (p) { return p.checked; });
      boxes.forEach(function (b) { b.closest('.pb-horse').classList.toggle('on', b.checked); });
    };
    form.addEventListener('change', sync);
    form.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pb]'); if (!b) return;
      var a = b.dataset.pb;
      if (a === 'all') boxes.forEach(function (x) { x.checked = true; });
      if (a === 'none') boxes.forEach(function (x) { x.checked = false; });
      if (a === 'fav') boxes.forEach(function (x) { x.checked = x.hasAttribute('data-fav'); });
      if (a === 'cat') boxes.forEach(function (x) { if (x.dataset.cat === b.dataset.cat) x.checked = true; });
      if (a === 'parts-all') parts.forEach(function (x) { x.checked = true; });
      if (a === 'parts-basic') parts.forEach(function (x) { x.checked = ['details', 'photo', 'pedigree'].indexOf(x.value) >= 0; });
      sync();
    });
    sync();
  }

  // ---- the batch print view
  var root = document.querySelector('#pages[data-batch]');
  if (root && Array.isArray(CFG.docs)) {
    var csrf = CFG.csrf || '';
    CFG.docs.forEach(function (c) {
      var host = document.createElement('div');
      host.className = 'batch-doc';
      root.appendChild(host);
      SK.setContext(c.ctx || {});
      var data = Object.assign(clone(SK.defaults()[c.type] || {}), c.saved || {});
      var lang = data.lang === 'ar' ? 'ar' : 'en';
      SK.render(host, c.type, data, { letterhead: c.letterhead, ref: c.ref, issued: c.issued ? SK.helpers.fmtDate(c.issued, lang) : '', issuedIso: c.issued || '', verifyUrl: c.verifyUrl });
      if (c.void) host.classList.add('doc-void');
    });
    var logAll = function () {
      CFG.docs.forEach(function (c) {
        var fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', 'print'); fd.append('via', 'batch');
        fetch(c.logUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
      });
    };
    window.addEventListener('load', function () { setTimeout(function () { logAll(); window.print(); }, 500); });
  }
})();
