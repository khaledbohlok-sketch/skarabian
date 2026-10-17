/* SK Arabian Studio: letterhead settings for the renderer (read from the page, no inline script). */
(function () {
  var el = document.getElementById('studio-data');
  var c = el ? JSON.parse(el.textContent) : {};
  window.SK_STUDIO_CFG = { logo: c.logo || '', mark: c.mark || '', lh: c.lh };
})();
