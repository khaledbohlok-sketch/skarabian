/* Shared modal component — one engine for every popup/dialog in the system: the iframe quick-add
 * ("+ إضافة جديد"), plain confirm, type-to-confirm delete, image/PDF preview, and toasts. Loaded on
 * every layout (portal.php AND popup.php) so it works identically at every nesting level — a quick-add
 * form opened from inside another quick-add form gets its own independent, fully-working modal stack,
 * no special-casing needed. Markup is built entirely in JS; the PHP side only provides one empty
 * mount point (#modal-root, see partials/modal.php) so there is a single source of truth for the UI. */
(function () {
  'use strict';
  var isAr = document.documentElement.dir === 'rtl';
  var T = isAr
    ? { loading: 'جارٍ التحميل…', close: 'إغلاق', cancel: 'إلغاء', confirm: 'تأكيد', ok: 'موافق', confirmDelete: 'تأكيد الحذف', delete: 'حذف', saved: 'تم الحفظ بنجاح', error: 'حدث خطأ' }
    : { loading: 'Loading…', close: 'Close', cancel: 'Cancel', confirm: 'Confirm', ok: 'OK', confirmDelete: 'Confirm delete', delete: 'Delete', saved: 'Saved successfully', error: 'An error occurred' };

  var XICON = '<svg class="icon" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>';

  var root, backdrop, stack = [], seq = 0, toastWrap;
  // Safari does not focus a button when it is clicked: remember what was pressed, so focus can go back to it on close
  var lastPressed = null;
  document.addEventListener('pointerdown', function (e) { lastPressed = e.target.closest && e.target.closest('button, a, [tabindex]'); }, true);

  function ensureRoot() {
    if (root) return;
    root = document.getElementById('modal-root');
    if (!root) { root = document.createElement('div'); root.id = 'modal-root'; document.body.appendChild(root); }
    backdrop = document.createElement('div');
    backdrop.className = 'sk-backdrop'; backdrop.hidden = true;
    root.appendChild(backdrop);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(top()); });
  }

  function top() { return stack[stack.length - 1]; }
  function findBySource(win) {
    for (var i = stack.length - 1; i >= 0; i--) {
      if (stack[i].iframeEl && stack[i].iframeEl.contentWindow === win) return stack[i];
    }
    return null;
  }

  function lockScroll(lock) {
    if (lock) {
      if (!document.body.classList.contains('modal-open')) { document.body.dataset.scrollY = String(window.scrollY || 0); }
      document.body.classList.add('modal-open');
    } else if (document.body.classList.contains('modal-open')) {
      document.body.classList.remove('modal-open');
      var y = +(document.body.dataset.scrollY || 0);
      window.scrollTo(0, y);
    }
  }

  function trapFocus(entry, e) {
    if (e.key !== 'Tab') return;
    var els = Array.prototype.filter.call(
      entry.el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'),
      function (el) { return el.offsetParent !== null || el.tagName === 'IFRAME'; }
    );
    if (!els.length) return;
    var first = els[0], last = els[els.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function restyle() {
    backdrop.hidden = !stack.length;
    if (stack.length) backdrop.style.zIndex = String(100 + (stack.length - 1) * 2); // tokens.css --z-modal
    stack.forEach(function (en, i) {
      en.el.style.zIndex = String(101 + i * 2);
      en.el.classList.toggle('stacked-behind', en !== top());
    });
  }

  function actuallyClose(entry) {
    entry.el.classList.remove('in'); entry.el.classList.add('out');
    var trigger = entry.trigger;
    setTimeout(function () {
      entry.el.remove();
      var i = stack.indexOf(entry); if (i > -1) stack.splice(i, 1);
      if (!stack.length) { lockScroll(false); }
      restyle();
      if (entry.opts.onClose) entry.opts.onClose();
      if (trigger && typeof trigger.focus === 'function' && document.contains(trigger)) trigger.focus();
    }, 160);
  }

  // Closing never asks about unsaved changes (no "discard changes?" anywhere): a half-filled form is kept as a
  // draft by portal.js and offered back when it is opened again. (Callers may still pass a "force" flag; it changes nothing.)
  function close(entry) {
    if (!entry) return;
    actuallyClose(entry);
  }

  /** Builds the generic header/body/footer shell. Caller fills .sk-modal-body / .sk-modal-footer. */
  function build(opts) {
    ensureRoot();
    var trigger = (opts.trigger !== undefined) ? opts.trigger : (lastPressed && document.contains(lastPressed) && (!document.activeElement || document.activeElement === document.body || (document.activeElement !== lastPressed && document.activeElement.contains(lastPressed))) ? lastPressed : document.activeElement);
    var box = document.createElement('div');
    box.className = 'sk-modal sk-modal-' + (opts.size || 'md');
    box.setAttribute('role', opts.role || 'dialog');
    box.setAttribute('aria-modal', 'true');
    var titleId = 'sk-modal-title-' + (++seq);
    box.innerHTML =
      '<div class="sk-modal-header">' +
        '<div class="sk-modal-headtext"><h2 id="' + titleId + '"></h2></div>' +
        '<button type="button" class="sk-modal-x icon-btn" aria-label="' + T.close + '">' + XICON + '</button>' +
      '</div>' +
      '<div class="sk-modal-body"></div>' +
      (opts.footer === false ? '' : '<div class="sk-modal-footer"></div>');
    box.querySelector('h2').textContent = opts.title || '';
    if (opts.subtitle) {
      var p = document.createElement('p'); p.className = 'sk-modal-subtitle'; p.textContent = opts.subtitle;
      box.querySelector('.sk-modal-headtext').appendChild(p);
    }
    box.setAttribute('aria-labelledby', titleId);
    root.appendChild(box);
    var entry = { el: box, opts: opts, trigger: trigger, id: seq };
    stack.push(entry);
    lockScroll(true);
    restyle();
    requestAnimationFrame(function () { box.classList.add('in'); });
    box.querySelector('.sk-modal-x').addEventListener('click', function () { close(entry); });
    box.addEventListener('keydown', function (e) { trapFocus(entry, e); });
    setTimeout(function () {
      var focusable = box.querySelector('[autofocus]') || box.querySelector('input, select, textarea, button:not(.sk-modal-x)');
      if (focusable) focusable.focus();
    }, 20);
    return entry;
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && stack.length) { e.preventDefault(); close(top()); }
  });

  // ------------------------------------------------------------------ confirm / confirm-type
  // tone: 'primary' (navy, default — positive/neutral actions), 'danger' (red — destructive), 'warning'
  // (amber — archive/deactivate/discard). `danger: true` is kept as a back-compat alias for tone:'danger'.
  // Title and OK label NEVER guess "Confirm delete"/"Delete" from the tone — every caller is expected to
  // pass its own title/okLabel; the only fallback is a neutral "Confirm"/"OK", regardless of tone, so a
  // forgotten label can never show the wrong (and historically, always the *delete*) wording.
  function confirmBox(opts) {
    return new Promise(function (resolve) {
      var resolved = false;
      function settle(v) { if (resolved) return; resolved = true; resolve(v); }
      var tone = opts.tone || (opts.danger ? 'danger' : 'primary');
      var entry = build({
        size: 'sm', role: 'alertdialog', footer: true,
        title: opts.title || T.confirm,
        onClose: function () { settle(false); },
      });
      var body = entry.el.querySelector('.sk-modal-body');
      var p = document.createElement('p'); p.className = 'sk-modal-message'; p.textContent = opts.message || '';
      body.appendChild(p);
      if (opts.summary && opts.summary.length) {
        var dl = document.createElement('dl'); dl.className = 'sk-modal-summary';
        opts.summary.forEach(function (row) {
          var dt = document.createElement('dt'); dt.textContent = row[0];
          var dd = document.createElement('dd'); dd.textContent = row[1];
          dl.appendChild(dt); dl.appendChild(dd);
        });
        body.appendChild(dl);
      }
      if (opts.warning) {
        var w = document.createElement('p'); w.className = 'sk-modal-warning'; w.textContent = opts.warning;
        body.appendChild(w);
      }
      var footer = entry.el.querySelector('.sk-modal-footer');
      var cancelLabel = opts.cancelLabel || T.cancel;
      var cancelBtn = document.createElement('button'); cancelBtn.type = 'button'; cancelBtn.className = 'btn';
      cancelBtn.textContent = cancelLabel;
      var okBtn = document.createElement('button'); okBtn.type = 'button';
      okBtn.className = 'btn ' + (tone === 'danger' ? 'btn-danger' : tone === 'warning' ? 'btn-warning' : 'btn-primary');
      // A trigger button named e.g. "Cancel" (cancel THIS leave request) would otherwise produce two
      // buttons both reading "Cancel" — fall back to the neutral label if the trigger's own text happens
      // to collide with the dialog's own Cancel button.
      var okLabel = opts.okLabel && opts.okLabel !== cancelLabel ? opts.okLabel : T.ok;
      if (opts.icon) okBtn.innerHTML = opts.icon + ' ';
      okBtn.appendChild(document.createTextNode(okLabel));
      footer.appendChild(cancelBtn); footer.appendChild(okBtn);
      cancelBtn.addEventListener('click', function () { settle(false); close(entry, true); });
      okBtn.addEventListener('click', function () { settle(true); close(entry, true); });
      setTimeout(function () { okBtn.focus(); }, 20);
    });
  }

  function confirmTypeBox(opts) {
    return new Promise(function (resolve) {
      var resolved = false;
      function settle(v) { if (resolved) return; resolved = true; resolve(v); }
      var entry = build({ size: 'sm', role: 'alertdialog', footer: true, title: opts.title || T.confirmDelete, onClose: function () { settle(false); } });
      var body = entry.el.querySelector('.sk-modal-body');
      body.innerHTML = '<p class="sk-modal-message"></p><input type="text" class="sk-modal-type-input" autocomplete="off" dir="ltr">';
      body.querySelector('.sk-modal-message').textContent = opts.message || '';
      var input = body.querySelector('.sk-modal-type-input');
      var footer = entry.el.querySelector('.sk-modal-footer');
      var cancelBtn = document.createElement('button'); cancelBtn.type = 'button'; cancelBtn.className = 'btn';
      cancelBtn.textContent = opts.cancelLabel || T.cancel;
      var okBtn = document.createElement('button'); okBtn.type = 'button'; okBtn.className = 'btn btn-danger'; okBtn.disabled = true;
      okBtn.textContent = opts.okLabel || T.delete;
      footer.appendChild(cancelBtn); footer.appendChild(okBtn);
      function sync() { okBtn.disabled = input.value !== opts.expected; }
      input.addEventListener('input', sync);
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !okBtn.disabled) { e.preventDefault(); settle(true); close(entry, true); } });
      cancelBtn.addEventListener('click', function () { settle(false); close(entry, true); });
      okBtn.addEventListener('click', function () { if (!okBtn.disabled) { settle(true); close(entry, true); } });
      setTimeout(function () { input.focus(); }, 20);
    });
  }

  // ------------------------------------------------------------------ iframe-hosted form (quick add)
  function openForm(opts) {
    var entry = build({ size: opts.size || 'lg', title: opts.title || '', footer: false, trigger: opts.trigger });
    var body = entry.el.querySelector('.sk-modal-body');
    body.classList.add('sk-modal-body-iframe'); entry.el.classList.add('sk-modal-frame');
    var loading = document.createElement('div');
    loading.className = 'sk-modal-loading'; loading.setAttribute('aria-label', T.loading); loading.innerHTML = '<span class="skel w-40"></span><span class="skel w-80"></span><span class="skel h-lg"></span><span class="skel w-60"></span><span class="skel h-lg"></span>';
    body.appendChild(loading);
    var iframe = document.createElement('iframe');
    iframe.title = opts.title || 'form';
    iframe.style.display = 'none'; // hidden (not the [hidden] attribute: our own CSS forces iframe to display:block)
    iframe.addEventListener('load', function () { loading.remove(); iframe.style.display = ''; }, { once: true });
    body.appendChild(iframe); // must be connected to the DOM before setting src, or some browsers never navigate it
    iframe.src = opts.url;
    entry.iframeEl = iframe;
    entry.pendingTarget = opts.pendingTarget || null;
    entry.pendingReload = !!opts.pendingReload;
    return entry;
  }

  // ------------------------------------------------------------------ image/PDF preview (lightbox)
  function openImage(src, alt) {
    var entry = build({ size: 'lg', footer: false, title: alt || '' });
    entry.el.classList.add('sk-lightbox');
    var body = entry.el.querySelector('.sk-modal-body');
    var img = document.createElement('img'); img.src = src; img.alt = alt || '';
    body.appendChild(img);
    return entry;
  }

  // ------------------------------------------------------------------ toast
  // type: success | error | warning | info. Errors and warnings stay until closed (opts.sticky); the others leave after 5 s.
  var TOAST_ICON = { success: 'check-circle', error: 'alert-circle', warning: 'alert', info: 'info' };
  function spriteUrl() { var u = document.querySelector('svg.icon use'); return u ? (u.getAttribute('href') || '').split('#')[0] : ''; }
  function toast(message, type, opts) {
    opts = opts || {};
    type = type || 'info';
    if (!toastWrap) { toastWrap = document.createElement('div'); toastWrap.className = 'sk-toast-wrap'; document.body.appendChild(toastWrap); }
    var el = document.createElement('div'); el.className = 'sk-toast ' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var sp = spriteUrl();
    if (sp) el.innerHTML = '<svg class="icon t-icon" aria-hidden="true"><use href="' + sp + '#' + (TOAST_ICON[type] || 'info') + '"></use></svg>';
    var msg = document.createElement('div'); msg.className = 't-msg'; msg.textContent = message; el.appendChild(msg);
    var x = document.createElement('button'); x.type = 'button'; x.className = 'icon-btn t-x'; x.setAttribute('aria-label', T.close); x.innerHTML = XICON; el.appendChild(x);
    function dismiss() { el.classList.remove('in'); setTimeout(function () { el.remove(); }, 220); }
    x.addEventListener('click', dismiss);
    toastWrap.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('in'); });
    if (!opts.sticky) setTimeout(dismiss, opts.duration || 5000);
    return el;
  }
  /** A button's waiting state while its action runs: disabled, spinner (the same .is-busy as every submit button). */
  function busy(btn, on) { if (!btn) return; btn.disabled = !!on; btn.classList.toggle('is-busy', !!on); btn.setAttribute('aria-busy', on ? 'true' : 'false'); }

  window.Modal = {
    confirm: confirmBox,
    confirmType: confirmTypeBox,
    openForm: openForm,
    openImage: openImage,
    toast: toast,
    busy: busy,
    closeTop: function () { close(top()); },
    get isInIframe() { return window.parent !== window; },
    /** A generic modal shell for fully custom content (e.g. the bill-report picker): caller fills
     * .sk-modal-body / .sk-modal-footer itself. Returns the same entry build() does; close it with
     * Modal.close(entry) once the caller is done (e.g. after its own "Add selected" action). */
    custom: function (opts) { return build(opts); },
    close: function (entry) { close(entry); },
  };

  // ------------------------------------------------------------------ generic attribute wiring
  // (same data-* attributes used across ~20 views already — no view changes needed)
  // The OK button's label and color always echo the element that triggered it (its own visible text and
  // btn-danger/btn-warning class) — never a guessed-at "Delete", so a void/purge/logout-everyone/etc.
  // confirmation always shows ITS OWN action name, not someone else's.
  function toneOf(el) { return el.classList.contains('btn-danger') ? 'danger' : el.classList.contains('btn-warning') ? 'warning' : 'primary'; }
  // An icon-only button (e.g. a bare "✕") has no useful visible text — prefer its aria-label when present.
  function labelOf(el) { var aria = el.getAttribute('aria-label'); var text = el.textContent.trim(); return (aria && (!text || text.length <= 2)) ? aria : text; }
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]'); if (!f || f.dataset.confirmBypass) return;
    e.preventDefault();
    var trigger = e.submitter || f.querySelector('button[type="submit"]');
    confirmBox({ message: f.dataset.confirm, tone: trigger ? toneOf(trigger) : 'primary', okLabel: trigger ? labelOf(trigger) : undefined }).then(function (ok) {
      if (ok) { f.dataset.confirmBypass = '1'; f.requestSubmit(e.submitter || undefined); delete f.dataset.confirmBypass; }
    });
  }, true);
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm-type]'); if (!f || f.dataset.confirmBypass) return;
    e.preventDefault();
    confirmTypeBox({ message: f.dataset.confirmTypeMessage || '', expected: f.dataset.confirmType }).then(function (ok) {
      if (ok) { f.dataset.confirmBypass = '1'; f.requestSubmit(); delete f.dataset.confirmBypass; }
    });
  }, true);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-confirm-click]'); if (!b || b.dataset.confirmBypass) return;
    e.preventDefault();
    confirmBox({ message: b.dataset.confirmClick, tone: toneOf(b), okLabel: labelOf(b) }).then(function (ok) {
      if (ok) { b.dataset.confirmBypass = '1'; b.click(); delete b.dataset.confirmBypass; }
    });
  }, true);

  // "+ Add new" / "Quick add" — opens the create form in a modal; works at any nesting depth since
  // every layout (including popup.php) runs its own independent copy of this script.
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add-new]'); if (!b) return;
    var u = b.dataset.addNew;
    var full = u + (u.indexOf('?') >= 0 ? '&' : '?') + 'popup=1';
    openForm({
      url: full,
      title: (b.dataset.modalTitle || b.textContent || '').trim(),
      trigger: b,
      pendingTarget: document.getElementById(b.dataset.target),
      pendingReload: !!b.dataset.reloadOnCreate,
    });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-modal-cancel]'); if (!b) return;
    e.preventDefault();
    if (window.parent !== window) { window.parent.postMessage({ type: 'sk-modal-close-self' }, window.location.origin); }
    else { close(top()); }
  });
  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin || !e.data) return;
    if (e.data.type === 'sk-created') {
      var entry = findBySource(e.source);
      var target = entry ? entry.pendingTarget : null;
      var reload = entry ? entry.pendingReload : false;
      if (target && target._pickerSet) target._pickerSet(String(e.data.id), e.data.label);
      if (entry) close(entry, true);
      toast(T.saved, 'success');
      if (reload) location.reload();
    } else if (e.data.type === 'sk-modal-close-self') {
      var en = findBySource(e.source);
      if (en) close(en);
    }
  });
  var done = document.querySelector('[data-popup-done]');
  if (done && window.parent !== window) {
    window.parent.postMessage({ type: 'sk-created', id: done.dataset.id, label: done.dataset.label }, window.location.origin);
  }
})();
