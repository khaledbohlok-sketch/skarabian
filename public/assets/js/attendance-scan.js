/* SK Arabians — the gate scanner (/portal/attendance/scan). Reads the QR on staff ID cards with the camera and records
 * the check-in / check-out on the server, with the server's time. Uses the browser's BarcodeDetector where there is one
 * (Android Chrome), otherwise jsQR (vendor/jsqr, loaded only then — iPhone Safari). Without internet, scans wait on the
 * phone (IndexedDB) with the time and are sent when it is back; the server marks them for the manager. While the page is
 * open it keeps the login alive and the screen on. */
(function () {
  'use strict';
  var cfgEl = document.getElementById('scan-cfg');
  if (!cfgEl) return;
  var CFG = JSON.parse(cfgEl.textContent);
  var T = CFG.i18n;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var $ = function (id) { return document.getElementById(id); };
  var video = $('scanVideo'), startBtn = $('scanStart'), hint = $('scanHint'), net = $('scanNet'), clock = $('scanClock');
  var box = $('scanResult'), title = $('srTitle'), nameEn = $('srName'), nameAr = $('srNameAr'), photo = $('srPhoto'), initials = $('srInitials');
  var serverOffset = CFG.serverTime * 1000 - Date.now();
  var busy = false, lastCode = '', lastAt = 0, detector = null, canvas = null, ctx2d = null, stream = null, audio = null, online = navigator.onLine, signedOut = false;
  var memQueue = [], memId = 0;

  // ------------------------------------------------------------ clock (the server's time, as the scans use it)
  var fmt = null;
  try { fmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: CFG.tz || 'Asia/Qatar' }); } catch (e) {}
  function tick() {
    var d = new Date(Date.now() + serverOffset);
    if (clock) clock.textContent = fmt ? fmt.format(d) : (('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2));
  }
  tick(); setInterval(tick, 10000);

  // ------------------------------------------------------------ server calls
  function post(url, data) {
    var fd = new FormData();
    fd.append('_csrf', csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) { j._status = r.status; return j; });
      });
  }

  // ------------------------------------------------------------ scans waiting for the internet (IndexedDB; memory if not possible)
  var dbp = null;
  function db() {
    if (!dbp) {
      dbp = new Promise(function (resolve, reject) {
        if (!window.indexedDB) { reject(new Error('no indexedDB')); return; }
        var req = indexedDB.open('ska-gate', 1);
        req.onupgradeneeded = function () { req.result.createObjectStore('queue', { keyPath: 'id', autoIncrement: true }); };
        req.onsuccess = function () { resolve(req.result); };
        req.onerror = function () { reject(req.error); };
      });
      dbp.catch(function () {});
    }
    return dbp;
  }
  function store(mode, fn) {
    return db().then(function (d) {
      return new Promise(function (resolve) {
        var tx = d.transaction('queue', mode), st = tx.objectStore('queue'), out = fn(st);
        tx.oncomplete = function () { resolve(out && 'result' in out ? out.result : undefined); };
        tx.onerror = tx.onabort = function () { resolve(undefined); };
      });
    });
  }
  function qAdd(item) {
    return store('readwrite', function (st) { return st.add(item); }).catch(function () { item.id = ++memId; memQueue.push(item); });
  }
  function qAll() {
    return store('readonly', function (st) { return st.getAll(); }).then(function (all) { return (all || []).concat(memQueue); }).catch(function () { return memQueue.slice(); });
  }
  function qDel(item) {
    var i = memQueue.indexOf(item);
    if (i >= 0) { memQueue.splice(i, 1); return Promise.resolve(); }
    return store('readwrite', function (st) { return st.delete(item.id); }).catch(function () {});
  }

  var syncing = false;
  function sync() {
    if (syncing || signedOut || !CFG.ready) return Promise.resolve();
    syncing = true;
    return qAll().then(function (items) {
      items.sort(function (a, b) { return a.at < b.at ? -1 : a.at > b.at ? 1 : 0; });
      var chain = Promise.resolve();
      items.forEach(function (it) {
        chain = chain.then(function () {
          return post(CFG.scanUrl, { qr: it.qr, offline: '1', at: it.at }).then(function (j) {
            if (j._status === 401) { setSignedOut(); throw new Error('signed out'); }
            if (j._status === 419) { expired(); throw new Error('page expired'); }
            online = true;
            // only a scan the server read (accepted or refused: its scans log has it) leaves the phone; any other
            // answer (away from the gate's internet, the database update missing, a server error) keeps it, in order
            if (j._status !== 200 || !j.ok) { throw new Error('not taken: ' + j._status); }
            return qDel(it);
          });
        });
      });
      return chain;
    }).catch(function () {}).then(function () { syncing = false; showNet(); });
  }

  function showNet() {
    qAll().then(function (items) {
      if (!net) return;
      var n = items.length;
      net.className = 'scan-net ' + (online && !signedOut ? 'on' : 'off');
      net.textContent = (online && !signedOut ? T.online : T.offline) + (n ? ' · ' + T.queued.replace(':n', String(n)) : '');
    });
  }
  window.addEventListener('online', function () { online = true; showNet(); sync(); });
  window.addEventListener('offline', function () { online = false; showNet(); });

  // keep the login alive while the scanner is open; the server's clock; send what is waiting
  function ping() {
    post(CFG.pingUrl, {}).then(function (j) {
      if (j._status === 401) { setSignedOut(); return; }
      if (j._status === 419) { expired(); return; }
      if (j.serverTime) serverOffset = j.serverTime * 1000 - Date.now();
      online = true; showNet(); sync();
    }).catch(function () { online = false; showNet(); });
  }
  if (CFG.ready) { setInterval(ping, 240000); showNet(); sync(); }

  function setSignedOut() {
    signedOut = true;
    showNet();
    show({ result: 'rejected', message: T.signedOut }, 0);
  }
  // The login timed out while the phone had no internet (more than half an hour): the page is opened again — to the
  // login page if needed — and the scans waiting on the phone are sent once it is back (they stay saved on the phone).
  var reloading = false;
  function expired() {
    if (reloading) return;
    reloading = true;
    show({ result: 'rejected', message: T.expired }, 0);
    setTimeout(function () { location.reload(); }, 2500);
  }

  // ------------------------------------------------------------ the result card, sound and vibration
  var hideTimer = null;
  function show(r, ms) {
    if (!box) return;
    var e = r.employee || null;
    box.className = 'scan-result r-' + (r.result || 'rejected') + (e ? '' : ' no-emp');
    box.hidden = false;
    document.body.classList.add('has-result');
    title.textContent = r.message || '';
    nameEn.textContent = e ? e.name_en : '';
    if (e && e.emp_no) { var no = document.createElement('span'); no.className = 'sr-no'; no.textContent = ' · ' + e.emp_no; nameEn.appendChild(no); }
    nameAr.textContent = e && e.name_ar ? e.name_ar : '';
    initials.textContent = e ? (e.name_en || '?').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toUpperCase() : '';
    photo.hidden = true;
    if (e && e.photo) {
      photo.onload = function () { photo.hidden = false; };
      photo.src = CFG.photoUrl + e.id;
    }
    var kind = { 'in': 'ok', late: 'warn', out: 'out', warning: 'warn', ignored: 'out', queued: 'warn' }[r.result] || 'bad';
    beep(kind);
    clearTimeout(hideTimer);
    if (ms === 0) return; // stays (signed out)
    hideTimer = setTimeout(function () { box.hidden = true; document.body.classList.remove('has-result'); busy = false; }, ms || (kind === 'ok' || kind === 'out' ? 2000 : 3500));
  }
  function beep(kind) {
    try { if (navigator.vibrate) navigator.vibrate(kind === 'bad' ? [120, 80, 120] : kind === 'warn' ? [60, 60, 60] : 80); } catch (e) {}
    if (!audio) return;
    try {
      var tones = { ok: [[880, 0.12]], out: [[660, 0.12]], warn: [[660, 0.1], [520, 0.16]], bad: [[220, 0.35]] }[kind] || [[440, 0.1]];
      var t = audio.currentTime;
      tones.forEach(function (tn) {
        var o = audio.createOscillator(), g = audio.createGain();
        o.frequency.value = tn[0]; g.gain.value = 0.15;
        o.connect(g); g.connect(audio.destination);
        o.start(t); o.stop(t + tn[1]); t += tn[1] + 0.04;
      });
    } catch (e) {}
  }

  // ------------------------------------------------------------ one card read
  function onCode(code) {
    var now = Date.now();
    if (busy || signedOut || (code === lastCode && now - lastAt < 4000)) return;
    lastCode = code; lastAt = now; busy = true;
    qAll().then(function (waiting) {
      if (waiting.length || !navigator.onLine) return keep(code); // scans waiting go first, in order
      return post(CFG.scanUrl, { qr: code }).then(handle, function () { online = false; return keep(code); });
    });
  }
  function keep(code) {
    return qAdd({ qr: code, at: new Date(Date.now() + serverOffset).toISOString() }).then(function () {
      show({ result: 'queued', message: T.saved });
      showNet();
      if (navigator.onLine) sync();
    });
  }
  function handle(j) {
    if (j._status === 401) { setSignedOut(); return; }
    if (j._status === 419) { expired(); return; }
    online = true; showNet();
    if (!j.ok) { show({ result: 'rejected', message: j.error || T.failed }); return; }
    show(j);
  }

  // ------------------------------------------------------------ camera and QR reading
  function message(text) {
    if (hint) { hint.textContent = text; hint.hidden = false; hint.classList.add('bad'); }
  }
  function start() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { message(T.noCamera); return; }
    try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); if (audio.state === 'suspended') audio.resume(); } catch (e) {}
    startBtn.disabled = true;
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false })
      .then(function (s) { stream = s; video.srcObject = s; return video.play(); })
      .then(function () { startBtn.hidden = true; if (hint) { hint.textContent = T.hint; hint.classList.remove('bad'); } screenOn(); return reader(); })
      .then(loop)
      .catch(function (e) { startBtn.disabled = false; message(e && e.name === 'NotAllowedError' ? T.camera : T.noCamera); });
  }
  function reader() {
    if ('BarcodeDetector' in window && window.BarcodeDetector.getSupportedFormats) {
      return window.BarcodeDetector.getSupportedFormats().then(function (f) {
        if (f.indexOf('qr_code') >= 0) { detector = new window.BarcodeDetector({ formats: ['qr_code'] }); return; }
        return loadJsQR();
      }).catch(loadJsQR);
    }
    return loadJsQR();
  }
  function loadJsQR() {
    if (window.jsQR) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = CFG.jsqr; s.onload = resolve; s.onerror = reject;
      document.head.appendChild(s);
    });
  }
  function read() {
    if (detector) return detector.detect(video).then(function (r) { return r && r[0] ? r[0].rawValue : null; });
    var w = video.videoWidth, h = video.videoHeight;
    if (!w || !h || !window.jsQR) return Promise.resolve(null);
    var k = Math.min(1, 720 / Math.max(w, h)), cw = Math.round(w * k), ch = Math.round(h * k);
    if (!canvas) { canvas = document.createElement('canvas'); ctx2d = canvas.getContext('2d', { willReadFrequently: true }); }
    if (canvas.width !== cw) canvas.width = cw;
    if (canvas.height !== ch) canvas.height = ch;
    ctx2d.drawImage(video, 0, 0, cw, ch);
    var found = window.jsQR(ctx2d.getImageData(0, 0, cw, ch).data, cw, ch, { inversionAttempts: 'dontInvert' });
    return Promise.resolve(found ? found.data : null);
  }
  function loop() {
    if (busy || video.readyState < 2) { setTimeout(loop, 150); return; }
    read().then(function (code) { if (code) onCode(code); }).catch(function () {}).then(function () { setTimeout(loop, 140); });
  }
  function screenOn() {
    if ('wakeLock' in navigator) navigator.wakeLock.request('screen').catch(function () {});
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible' && stream) screenOn(); });
  if (startBtn && CFG.ready) startBtn.addEventListener('click', start);

  // ------------------------------------------------------------ a card that won't scan: staff number + manager PIN
  var dlg = $('typedDlg'), typeBtn = $('scanType'), form = $('typedForm'), err = $('typedErr');
  if (dlg && typeBtn && form) {
    typeBtn.addEventListener('click', function () { err.textContent = ''; form.reset(); busy = true; if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); $('typedNo').focus(); });
    $('typedCancel').addEventListener('click', function () { dlg.close(); });
    dlg.addEventListener('close', function () { if (box.hidden) busy = false; });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      err.textContent = '';
      post(CFG.typedUrl, { emp_no: $('typedNo').value, pin: $('typedPin').value }).then(function (j) {
        if (j._status === 401) { dlg.close(); setSignedOut(); return; }
        if (!j.ok) { err.textContent = j.error || T.failed; $('typedPin').value = ''; return; }
        dlg.close(); busy = true; show(j);
      }).catch(function () { err.textContent = T.offline; });
    });
  }
})();
