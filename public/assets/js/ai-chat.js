/* SK Arabians website: the "Ask about our horses" chat box. Answers come from the server (AI that only uses what the
 * website shows); serious questions get WhatsApp and inquiry-form buttons. On only when the Owner switched it on
 * (Administration → AI assistant), with its settings in #sk-chat-cfg. The conversation itself is kept in the visitor's
 * session on the server; this tab keeps a copy of what is on screen, so the box keeps its messages from page to page.
 * Everything typed or answered is shown as text, never as HTML. */
(function () {
  'use strict';
  var cfgEl = document.getElementById('sk-chat-cfg');
  if (!cfgEl || !window.fetch) return;
  var cfg;
  try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
  var T = cfg.t, KEY = 'sk-chat', MAX_KEEP = 40;
  var ICON = {
    spark: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9L12 2zm7 12l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9L19 14zM5 15l.7 1.8 1.8.7-1.8.7L5 20l-.7-1.8-1.8-.7 1.8-.7L5 15z"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>',
    fresh: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 0 1 13.7-5.6L20 8.5M20 4v4.5h-4.5M20 12a8 8 0 0 1-13.7 5.6L4 15.5M4 20v-4.5h4.5"/></svg>',
    send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4l17.5-7.5a1 1 0 0 0 0-1.8L3.4 3.6a.9.9 0 0 0-1.3 1l1.8 6.4 9.1 1-9.1 1-1.8 6.4a.9.9 0 0 0 1.3 1z"/></svg>',
    wa: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4a.5.5 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.5-.3z"/></svg>'
  };

  // ---- what this tab remembers (sessionStorage may be unavailable: the box then simply starts empty on each page)
  var state = { open: false, items: [] };
  try { var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null'); if (saved && Array.isArray(saved.items)) state = saved; } catch (e) {}
  function keep() { try { state.items = state.items.slice(-MAX_KEEP); sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }

  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function safeUrl(u) {
    try { var x = new URL(String(u), location.href); return (x.protocol === 'https:' || x.protocol === 'http:') ? x.href : null; } catch (e) { return null; }
  }

  // ---- the launcher and the panel
  var hasWa = !!document.querySelector('.wa-float');
  var launch = el('button', 'skc-launch' + (hasWa ? '' : ' no-wa'));
  launch.type = 'button';
  launch.setAttribute('aria-controls', 'skc-panel');
  launch.setAttribute('aria-expanded', 'false');
  launch.innerHTML = ICON.spark;
  launch.appendChild(el('span', null, T.open));

  var panel = el('section', 'skc-panel');
  panel.id = 'skc-panel';
  panel.hidden = true;
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-labelledby', 'skc-title');
  var head = el('header', 'skc-head'), titles = el('div');
  var h2 = el('h2', null, T.title); h2.id = 'skc-title';
  titles.appendChild(h2); titles.appendChild(el('p', null, T.sub));
  var btnNew = el('button'); btnNew.type = 'button'; btnNew.innerHTML = ICON.fresh; btnNew.title = T.fresh; btnNew.setAttribute('aria-label', T.fresh);
  var btnClose = el('button'); btnClose.type = 'button'; btnClose.innerHTML = ICON.close; btnClose.title = T.close; btnClose.setAttribute('aria-label', T.close);
  head.appendChild(titles); head.appendChild(btnNew); head.appendChild(btnClose);
  var log = el('div', 'skc-log'); log.setAttribute('role', 'log'); log.setAttribute('aria-live', 'polite');
  var form = el('form', 'skc-form');
  var label = el('label', 'skc-sr', T.input); label.htmlFor = 'skc-input';
  var input = el('textarea'); input.id = 'skc-input'; input.rows = 1; input.maxLength = cfg.max || 500; input.placeholder = T.placeholder; input.setAttribute('enterkeyhint', 'send');
  var send = el('button', 'skc-send'); send.type = 'submit'; send.innerHTML = ICON.send; send.setAttribute('aria-label', T.send);
  form.appendChild(label); form.appendChild(input); form.appendChild(send);
  panel.appendChild(head); panel.appendChild(log); panel.appendChild(form); panel.appendChild(el('p', 'skc-note', T.note));
  document.body.appendChild(launch);
  document.body.appendChild(panel);

  // ---- messages
  function chip(cls, text, href, icon) {
    var a = el('a', 'skc-chip' + (cls ? ' ' + cls : ''));
    a.href = href;
    if (/^https:\/\/wa\.me\//.test(href)) { a.target = '_blank'; a.rel = 'noopener'; }
    // a page of the website: the box is closed there, so the visitor sees the page (the launcher brings it back)
    else a.addEventListener('click', function () { state.open = false; keep(); });
    if (icon) a.innerHTML = icon;
    a.appendChild(document.createTextNode(text));
    return a;
  }
  function draw(item) {
    if (item.who === 'me') { log.appendChild(el('div', 'skc-msg skc-me', item.text)); return; }
    log.appendChild(el('div', 'skc-msg skc-bot' + (item.err ? ' skc-err' : ''), item.text));
    // one button per page (the answer's own link to the horse and "See …" are the same page)
    var extra = el('div', 'skc-extra'), seen = {};
    function once(u) { var p = new URL(u).pathname.replace(/\/$/, ''); if (seen[p]) return false; seen[p] = 1; return true; }
    (item.links || []).forEach(function (l) { var u = safeUrl(l.url); if (u && once(u)) extra.appendChild(chip('', l.title, u)); });
    if (item.horse) { var hu = safeUrl(item.horse.url); if (hu && once(hu)) extra.appendChild(chip('', T.horse.replace(':horse', item.horse.name), hu)); }
    if (item.handoff || item.err) {
      var w = item.whatsapp && safeUrl(item.whatsapp), q = item.inquiry && safeUrl(item.inquiry);
      if (q) extra.appendChild(chip('skc-go', T.inquiry, q));
      if (w) extra.appendChild(chip('skc-wa', T.whatsapp, w, ICON.wa));
    }
    if (extra.childNodes.length) log.appendChild(extra);
  }
  function hello() {
    log.appendChild(el('div', 'skc-msg skc-bot', T.hello));
    var extra = el('div', 'skc-extra');
    (cfg.suggest || []).forEach(function (q) {
      var b = el('button', 'skc-chip', q); b.type = 'button';
      b.addEventListener('click', function () { ask(q); });
      extra.appendChild(b);
    });
    log.appendChild(extra);
  }
  function redraw() {
    log.textContent = '';
    hello();
    state.items.forEach(draw);
    log.scrollTop = log.scrollHeight;
  }
  function add(item) { state.items.push(item); keep(); draw(item); log.scrollTop = log.scrollHeight; }

  // ---- asking
  var busy = false;
  function post(fields) {
    var body = new URLSearchParams(fields);
    return fetch(cfg.url, { method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.token } });
  }
  function ask(text) {
    text = String(text || '').trim();
    if (!text || busy) return;
    busy = true; send.disabled = true;
    add({ who: 'me', text: text });
    input.value = ''; grow();
    var dots = el('div', 'skc-typing'); dots.setAttribute('aria-label', T.thinking);
    dots.appendChild(el('i')); dots.appendChild(el('i')); dots.appendChild(el('i'));
    log.appendChild(dots); log.scrollTop = log.scrollHeight;
    post({ message: text })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (d) {
          if (r.status === 419) return { who: 'bot', err: true, text: T.expired };
          if (r.ok && typeof d.reply === 'string') {
            return { who: 'bot', text: d.reply, links: d.links || [], handoff: !!d.handoff, horse: d.horse || null, whatsapp: d.whatsapp || null, inquiry: d.inquiry || null };
          }
          return { who: 'bot', err: true, text: (d && d.error) || T.error, whatsapp: d.whatsapp || null, inquiry: d.inquiry || null };
        });
      })
      .catch(function () { return { who: 'bot', err: true, text: T.offline }; })
      .then(function (item) {
        dots.remove();
        add(item);
        busy = false; send.disabled = false;
        if (!panel.hidden) input.focus();
      });
  }

  // ---- opening and closing
  function open(focus) {
    panel.hidden = false; launch.hidden = true; launch.setAttribute('aria-expanded', 'true');
    state.open = true; keep();
    redraw();
    if (focus) input.focus();
  }
  function close() {
    panel.hidden = true; launch.hidden = false; launch.setAttribute('aria-expanded', 'false');
    state.open = false; keep();
    launch.focus();
  }
  launch.addEventListener('click', function () { open(true); });
  btnClose.addEventListener('click', close);
  // Esc closes the box: typing in it, or with nothing else focused (the box opened again on a new page)
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || panel.hidden) return;
    var a = document.activeElement;
    if (panel.contains(a) || !a || a === document.body) { e.preventDefault(); close(); }
  });
  btnNew.addEventListener('click', function () {
    if (busy) return;
    post({ reset: '1' }).catch(function () {});
    state.items = []; keep(); redraw(); input.focus();
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
  // Enter sends, Shift+Enter starts a new line (not while an Arabic/Asian keyboard is still composing a word)
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); ask(input.value); } });
  function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight + 2, 120) + 'px'; }
  input.addEventListener('input', grow);

  // phones: like the WhatsApp button, the launcher steps aside while the homepage counters are on screen
  var stats = document.querySelector('.hero-stats, .stat-cards');
  if (stats && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { launch.classList.toggle('over-stats', en[0].isIntersecting); }).observe(stats);
  }
  // open on the previous page: open again here too, except on a phone, where it would cover the page just opened
  if (state.open && window.matchMedia('(min-width: 641px)').matches) open(false);
})();
