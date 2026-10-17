/* SK Arabian Studio home: search and group filter over the gallery of document types (no inline scripts).
 * Without JavaScript every card simply shows and the search bar stays hidden. */
(function () {
  'use strict';
  var root = document.querySelector('[data-studio-gallery]');
  if (!root) return;
  var tools = root.querySelector('[data-sg-tools]'), q = root.querySelector('[data-sg-q]'), none = root.querySelector('[data-sg-none]');
  var chips = [].slice.call(root.querySelectorAll('[data-sg-group]')), groups = [].slice.call(root.querySelectorAll('[data-sg-g]'));
  var group = '';
  if (tools) tools.hidden = false;
  function apply() {
    var words = (q && q.value || '').toLowerCase().trim().split(/\s+/).filter(Boolean), shown = 0;
    groups.forEach(function (g) {
      var n = 0, inGroup = !group || g.getAttribute('data-sg-g') === group;
      [].slice.call(g.querySelectorAll('.sg-card')).forEach(function (c) {
        var text = c.getAttribute('data-sg-text') || '';
        var ok = inGroup && words.every(function (w) { return text.indexOf(w) >= 0; });
        c.hidden = !ok; if (ok) n++;
      });
      g.hidden = n === 0; shown += n;
    });
    if (none) none.hidden = shown > 0;
  }
  chips.forEach(function (b) {
    b.addEventListener('click', function () {
      group = b.getAttribute('data-sg-group') || '';
      chips.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      apply();
    });
  });
  if (q) {
    q.addEventListener('input', apply);
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { q.value = ''; apply(); }
      if (e.key === 'Enter') { e.preventDefault(); var first = root.querySelector('.sg-card:not([hidden])'); if (first) first.focus(); }
    });
  }
})();
