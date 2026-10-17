/* Print pages: toolbar buttons, letterhead pages for printouts, optional automatic print dialog, and paper shrunk to
 * fit narrow screens. */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) window.print();
    if (e.target.closest('[data-back]')) { if (history.length > 1) history.back(); else window.close(); }
  });

  // ---------------------------------------------------------------- letterhead pages
  // A printout (portal/print/doc.php) arrives as one long page (#print-src). Here it is laid out on A4 pages made
  // from #print-page-tpl — letterhead, reference + QR code, "Page n of N", footer — the same way SK Arabian Studio
  // lays out its documents. A table continues on the next page with its headings repeated and the same column
  // widths; its total row stays with the last rows; a heading or note (data-keep-next) never ends a page alone.
  function paginate() {
    var src = document.getElementById('print-src');
    var tpl = document.getElementById('print-page-tpl');
    var host = document.getElementById('print-pages');
    if (!src || !tpl || !host || !tpl.content || !tpl.content.firstElementChild) return;
    var flowPage = src.closest('.page');
    var contLabel = tpl.getAttribute('data-cont-label') || '';
    var blocks = Array.prototype.slice.call(src.children);
    var page;

    function newPage() {
      var el = tpl.content.firstElementChild.cloneNode(true);
      host.appendChild(el);
      return { el: el, body: el.querySelector('.pbody'), flow: el.querySelector('.flow') };
    }
    function over(p) { return p.flow.offsetHeight > p.body.clientHeight + 1; }

    // Column widths, decided once for the whole table and given to every page's part of it (as shares of the text
    // width, so the paper zoom on phones doesn't matter). Each column first gets what its longest value needs on one
    // line (headings may wrap between words); when that is more than the page has, only the widest columns are
    // narrowed — so dates, numbers, codes and statuses stay on one line and long texts wrap. The first page is used
    // to measure in and is empty again afterwards; the blocks keep their order in `blocks`.
    function fitWidths(need, avail) {
      var n = need.length, total = 0, res = [], i;
      for (i = 0; i < n; i++) total += need[i];
      if (total <= avail) { for (i = 0; i < n; i++) res.push(need[i] / total * avail); return res; }
      var order = need.map(function (w, k) { return k; }).sort(function (a, b) { return need[a] - need[b]; });
      var left = avail, count = n;
      for (var k = 0; k < n; k++) {
        var c = order[k], share = left / count;
        if (need[c] <= share) { res[c] = need[c]; left -= need[c]; count--; continue; }
        for (var m = k; m < n; m++) res[order[m]] = share; // the widest share what is left
        break;
      }
      return res;
    }
    page = newPage();
    blocks.forEach(function (b) {
      if (b.tagName !== 'TABLE' || !b.hasAttribute('data-split') || !b.tHead) return;
      page.flow.appendChild(b);
      b.classList.add('measure');
      var cells = b.tHead.rows[0].cells, need = [];
      for (var i = 0; i < cells.length; i++) need.push(cells[i].getBoundingClientRect().width);
      b.classList.remove('measure');
      var avail = page.flow.clientWidth, w = fitWidths(need, avail), cols = b.querySelectorAll('col');
      if (avail > 0 && cols.length === w.length) for (var j = 0; j < w.length; j++) cols[j].style.width = (w[j] / avail * 100).toFixed(3) + '%';
      b.classList.add('fixed');
      page.flow.removeChild(b);
    });

    function startPart(t, continued) {
      if (continued) {
        var c = document.createElement('div');
        c.className = 'cont';
        c.setAttribute('data-keep-next', '');
        c.textContent = (t.getAttribute('data-title') || '') + ' ' + contLabel;
        page.flow.appendChild(c);
      }
      var part = document.createElement('table');
      part.className = t.className;
      var cg = t.querySelector('colgroup');
      if (cg) part.appendChild(cg.cloneNode(true));
      if (t.tHead) part.appendChild(t.tHead.cloneNode(true));
      part.appendChild(document.createElement('tbody'));
      page.flow.appendChild(part);
      return part;
    }

    function placeTable(t) {
      var foot = t.tFoot;
      if (foot) foot.parentNode.removeChild(foot);
      var rows = Array.prototype.slice.call(t.tBodies[0].rows);
      var part = startPart(t, false);
      rows.forEach(function (r) {
        var body = part.tBodies[0];
        body.appendChild(r);
        if (!over(page)) return;
        if (body.rows.length === 1) {
          // Not even one row fits after what is already on this page: the table starts on the next page, with the
          // heading or note just before it. (At the top of a page already, a row taller than the page stays.)
          var carry = [], prev = part.previousElementSibling;
          while (prev && prev.hasAttribute('data-keep-next')) { carry.unshift(prev); prev = prev.previousElementSibling; }
          if (!prev) return;
          part.parentNode.removeChild(part);
          page = newPage();
          carry.forEach(function (c) { page.flow.appendChild(c); });
          part = startPart(t, false);
          part.tBodies[0].appendChild(r);
          return;
        }
        body.removeChild(r);
        page = newPage();
        part = startPart(t, true);
        part.tBodies[0].appendChild(r);
      });
      if (foot) {
        part.appendChild(foot);
        if (over(page) && part.tBodies[0].rows.length > 1) {
          // the total goes to the next page together with the last row, never on its own
          var last = part.tBodies[0].lastElementChild;
          part.removeChild(foot);
          part.tBodies[0].removeChild(last);
          page = newPage();
          part = startPart(t, true);
          part.tBodies[0].appendChild(last);
          part.appendChild(foot);
        }
      }
      if (t.parentNode) t.parentNode.removeChild(t);
    }

    function placeBlock(b) {
      page.flow.appendChild(b);
      if (over(page) && page.flow.children.length > 1) {
        page.flow.removeChild(b);
        page = newPage();
        page.flow.appendChild(b);
      }
    }

    blocks.forEach(function (b) {
      if (b.tagName === 'TABLE' && b.hasAttribute('data-split') && b.tBodies.length) placeTable(b);
      else placeBlock(b);
    });

    var pages = host.querySelectorAll('.page.listpage');
    var label = tpl.getAttribute('data-page-label') || ':n / :total';
    for (var k = 0; k < pages.length; k++) {
      var pn = pages[k].querySelector('.pnum');
      if (pn) pn.textContent = label.replace(':n', k + 1).replace(':total', pages.length);
    }
    var count = document.getElementById('print-npages');
    if (count) count.textContent = '· ' + (tpl.getAttribute('data-pages-label') || ':n').replace(':n', pages.length);
    if (flowPage && flowPage.parentNode) flowPage.parentNode.removeChild(flowPage);
    document.documentElement.classList.add('paginated');
  }

  // ---------------------------------------------------------------- fit the paper to a phone screen
  // An A4 page (or a 90mm QR tag) is wider than a phone. Instead of a sideways-scrolling page with the
  // toolbar out of view, the paper is zoomed out to the screen width; printing always uses full size.
  // screen.width as well as innerWidth: phones widen innerWidth to fit content that is too wide.
  // (print.css resets the zoom for printing; no print events are relied on, since not every browser sends "afterprint")
  var box = document.querySelector('.print-fit');
  var timer = 0;
  function fit() {
    if (!box) return;
    box.style.zoom = '';
    box.style.width = 'min-content'; // the widest single piece of paper, however many there are
    var need = box.getBoundingClientRect().width;
    box.style.width = '';
    var avail = Math.min(window.innerWidth, screen.width || window.innerWidth) - 12;
    if (need > avail && avail > 0) box.style.zoom = Math.max(avail / need, 0.25).toFixed(3);
  }
  function later() { clearTimeout(timer); timer = setTimeout(fit, 80); }

  // Pages are made once the fonts are in (they decide how much fits on a page), before any zoom.
  var started = false;
  function start() {
    if (started) return;
    started = true;
    try { paginate(); } catch (err) { if (window.console) console.error(err); }
    fit();
    window.addEventListener('resize', later);
    // Studio documents are drawn after load: refit when pages are added
    if (box && window.MutationObserver) new MutationObserver(later).observe(box, { childList: true, subtree: true });
    if (/[?&]autoprint=1/.test(location.search)) setTimeout(function () { window.print(); }, 400);
  }
  var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
  var loaded = new Promise(function (res) { if (document.readyState === 'complete') res(); else window.addEventListener('load', res); });
  Promise.all([fontsReady, loaded]).then(start, start);
  setTimeout(start, 4000); // never wait for ever (a font that doesn't arrive)
})();
