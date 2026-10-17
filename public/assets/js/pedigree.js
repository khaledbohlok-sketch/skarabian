/* Full pedigree tree — 5-generation (62-ancestor) editor (horse create/edit wizard, the standalone /pedigree-edit page)
 * plus the small helpers that are specific to pedigree data: auto-filling the next generation when an
 * ancestor who already has recorded parents is picked, flagging the same horse picked in two boxes,
 * keeping the "subject" box's name in sync with the name typed earlier in the form, and mirroring the
 * tree into the wizard's Review step. Everything generic (click-to-edit boxes, searchable pickers, the
 * pick/new toggle, autosave, keyboard shortcuts...) is handled by public/assets/js/portal.js. */
(function () {
  'use strict';
  var root = document.querySelector('[data-pedigree-editor]');
  if (!root) return;
  var base = (document.body && document.body.dataset.base) || '/';
  if (base.slice(-1) !== '/') base += '/';

  function nodeFor(path) { return root.querySelector('[data-ped-path="' + path + '"]'); }
  function pickOf(node) { return node && node.querySelector('[data-ped-pick]'); }

  // What a box holds: a picked horse's id, 'new' once a new ancestor's name is typed (the server takes the name
  // first too), or ''.
  function boxKey(node) {
    var en = node.querySelector('input[name^="new_"][name$="_en"]');
    if (en && en.value.trim()) return 'new';
    var sel = pickOf(node);
    return sel ? sel.value : '';
  }

  // A closed box's name (portal.js refreshes only the box being edited)
  function showName(node) {
    var nameEl = node.querySelector('.ped-display .ped-name'); if (!nameEl) return;
    var sel = pickOf(node), opt = sel && sel.selectedOptions[0];
    var text = opt && opt.value ? opt.text : '';
    nameEl.textContent = text || node.dataset.pedEmptyText || '';
    nameEl.classList.toggle('empty', !text);
  }

  // The boxes behind a box hold that horse's ancestors: when another horse goes there they are emptied and left
  // "untouched" (saving before they are filled again changes nothing on file), then filled with the new horse's own.
  function resetBehind(path) {
    root.querySelectorAll('[data-ped-path]').forEach(function (n) {
      var p = n.dataset.pedPath;
      if (p.length <= path.length || p.indexOf(path) !== 0) return;
      n.dataset.pedKey = '';
      var sel = pickOf(n);
      if (sel && sel.value) { if (sel._pickerSet) sel._pickerSet('', ''); else sel.value = ''; }
      n.querySelectorAll('input[name^="new_"]').forEach(function (inp) { inp.value = ''; });
      var mode = n.querySelector('select[data-section-toggle]');
      if (mode && mode.value !== 'pick') { mode.value = 'pick'; mode.dispatchEvent(new Event('change', { bubbles: true })); }
      var t = n.querySelector('[data-ped-touched]'); if (t) t.value = '0'; // after the events above, which mark it touched
      n.classList.remove('ped-autofilled', 'ped-dup');
      showName(n);
    });
  }

  // A picked ancestor's own recorded parents fill the empty boxes behind it; each box filled that way fires the same
  // change, so the whole line is filled as deep as the chart goes (the last generation's boxes have no boxes behind).
  function autofill(path, horseId) {
    var sireNode = nodeFor(path + 's'), damNode = nodeFor(path + 'd');
    if (!sireNode && !damNode) return;
    fetch(base + 'portal/horses/' + encodeURIComponent(horseId) + '/ancestors', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        var node = nodeFor(path);
        if (!d || !node || boxKey(node) !== String(horseId)) return; // another horse was put in that box meanwhile
        [[sireNode, d.sire], [damNode, d.dam]].forEach(function (pair) {
          var n = pair[0], h = pair[1], sel = pickOf(n);
          if (!h || !sel || !sel._pickerSet || boxKey(n)) return; // don't overwrite what's already filled in
          sel._pickerSet(String(h.id), h.name);
          n.classList.add('ped-autofilled');
          showName(n);
        });
        checkDuplicates();
        refreshReview();
      }).catch(function () {});
  }

  // A box now holding another horse: the old one's line behind it goes (behind a box that was empty, what is there
  // was entered for whoever goes in it and stays), and the new horse's known ancestors come in.
  function boxChanged(node) {
    var key = boxKey(node), was = node.dataset.pedKey || '';
    if (key === was) return;
    node.dataset.pedKey = key;
    if (was) resetBehind(node.dataset.pedPath);
    if (key && key !== 'new') autofill(node.dataset.pedPath, key);
  }
  root.querySelectorAll('[data-ped-path]').forEach(function (n) { n.dataset.pedKey = boxKey(n); });

  // Same horse picked in more than one box — not necessarily invalid (half-siblings share a parent on
  // purpose), but worth a visible nudge; the server still hard-blocks an actual ancestor loop.
  function checkDuplicates() {
    var picks = Array.prototype.map.call(root.querySelectorAll('[data-ped-pick]'), function (s) { return s.value; }).filter(Boolean);
    var counts = {};
    picks.forEach(function (v) { counts[v] = (counts[v] || 0) + 1; });
    var hasDup = Object.keys(counts).some(function (k) { return counts[k] > 1; });
    root.querySelectorAll('[data-ped-pick]').forEach(function (sel) {
      var node = sel.closest('[data-ped-path]');
      if (node) node.classList.toggle('ped-dup', !!sel.value && counts[sel.value] > 1);
    });
    var note = root.querySelector('[data-ped-dup-note]');
    if (note) note.hidden = !hasDup;
  }

  root.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-ped-pick]'); if (!sel) return;
    var node = e.target.closest('[data-ped-path]'); if (node) node.classList.remove('ped-autofilled');
    checkDuplicates();
    if (node) boxChanged(node);
    refreshReview();
  });
  root.addEventListener('input', function (e) {
    if (!e.target.closest('.ped-edit')) return;
    var node = e.target.closest('[data-ped-path]');
    if (node && e.target.matches('input[name^="new_"][name$="_en"]')) boxChanged(node);
    refreshReview();
  });
  checkDuplicates();

  // The subject box (new-horse create wizard) shows whatever's typed in the Identity step's name field.
  var nameInput = document.querySelector('[name="name_en"]');
  var subjectName = document.querySelector('[data-ped-subject-name]');
  if (nameInput && subjectName) {
    var placeholder = subjectName.dataset.placeholder || subjectName.textContent;
    nameInput.addEventListener('input', function () { subjectName.textContent = nameInput.value.trim() || placeholder; });
  }

  // Review step: a plain-text mirror of the tree, kept in sync as the Pedigree step is filled in.
  var reviewList = document.querySelector('[data-ped-review] .ped-review-list');
  function refreshReview() {
    if (!reviewList) return;
    reviewList.innerHTML = '';
    root.querySelectorAll('.ped-node:not(.subject)[data-ped-path]').forEach(function (n) {
      var roleEl = n.querySelector('.ped-display small');
      var nameEl = n.querySelector('.ped-name');
      var row = document.createElement('div'); row.className = 'ped-review-row';
      // the full relation ("Sire's mother's father"): "Father"/"Mother" alone says little four generations back
      var roleSpan = document.createElement('span'); roleSpan.className = 'ped-review-role'; roleSpan.textContent = n.dataset.pedRelation || (roleEl ? roleEl.textContent : '');
      var nameSpan = document.createElement('span'); nameSpan.className = 'ped-review-name'; nameSpan.textContent = nameEl && !nameEl.classList.contains('empty') ? nameEl.textContent : '—';
      row.appendChild(roleSpan); row.appendChild(nameSpan); reviewList.appendChild(row);
    });
  }
  document.addEventListener('click', function (e) { if (e.target.closest('[data-wizard-step]') || e.target.closest('[data-ped-done]')) refreshReview(); });
  refreshReview();
})();
