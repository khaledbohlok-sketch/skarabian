/* Print pages: toolbar buttons and optional automatic print dialog. */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) window.print();
    if (e.target.closest('[data-back]')) { if (history.length > 1) history.back(); else window.close(); }
  });
  if (/[?&]autoprint=1/.test(location.search)) window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
})();
