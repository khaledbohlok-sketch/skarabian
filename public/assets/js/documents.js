/* Documents & Photos: table/grid view toggle (remembered per browser) and the image lightbox.
 * The lightbox also serves any other page that loads this file (a horse's Photos tab): a link with
 * data-lightbox="1" opens its image in the shared modal instead of a new tab. */
(function () {
  // Lightbox for image links; PDFs keep their normal target="_blank" new-tab link (embedding PDFs is off on purpose).
  // Uses the shared modal engine (window.Modal, from modal.js) so it gets the same backdrop, animation,
  // Escape/outside-click handling and focus trap as every other popup in the system.
  if (!window.__skLightbox) {
    window.__skLightbox = true;
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('[data-lightbox="1"]');
      if (!a || !window.Modal || !window.Modal.openImage || e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      var img = a.querySelector('img');
      window.Modal.openImage(a.href, a.getAttribute('data-title') || (img ? img.alt : ''));
    });
  }

  var KEY = 'sk-documents-view';
  var toggle = document.querySelector('[data-view-toggle]');
  var panels = document.querySelectorAll('[data-view-panel]');
  if (!toggle || !panels.length) return;

  function apply(view) {
    panels.forEach(function (p) { p.hidden = p.getAttribute('data-view-panel') !== view; });
    toggle.querySelectorAll('[data-view]').forEach(function (b) {
      var on = b.getAttribute('data-view') === view;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    try { localStorage.setItem(KEY, view); } catch (e) { /* private mode: ignore */ }
  }

  toggle.addEventListener('click', function (e) {
    var b = e.target.closest('[data-view]');
    if (b) apply(b.getAttribute('data-view'));
  });

  var saved = 'table';
  try { saved = localStorage.getItem(KEY) || 'table'; } catch (e) { /* ignore */ }
  apply(saved === 'grid' ? 'grid' : 'table');
})();
