/* SK Arabians — AI assistant: the "✨ Translate" buttons under the English / Arabic fields of the website forms
 * (partials/ai_translate.php). Sends the partner field's text to /portal/ai/translate and fills this field; the user
 * checks it before saving. Works in pop-up forms too (one listener for the page). Loaded only while the AI is on. */
(function () {
  'use strict';
  var isAr = document.documentElement.dir === 'rtl';
  var T = isAr
    ? { working: 'جارٍ الترجمة…', done: 'تمت الترجمة — يرجى مراجعتها قبل الحفظ.', empty: 'اكتب النص باللغة الأخرى أولًا.', replace: 'هذا الحقل يحتوي على نص. هل تريد استبداله بالترجمة؟', title: 'استبدال النص؟', ok: 'استبدال', error: 'تعذّرت الترجمة. حاول مرة أخرى.' }
    : { working: 'Translating…', done: 'Translated — please check it before saving.', empty: 'Write the text in the other language first.', replace: 'This field already has text. Replace it with the translation?', title: 'Replace the text?', ok: 'Replace', error: 'The translation could not be made. Try again.' };

  function toast(msg, type) {
    if (window.Modal && window.Modal.toast) window.Modal.toast(msg, type); else window.alert(msg);
  }
  function ask(msg) {
    if (window.Modal && window.Modal.confirm) return window.Modal.confirm({ title: T.title, message: msg, okLabel: T.ok });
    return Promise.resolve(window.confirm(msg));
  }

  function run(btn) {
    var form = btn.closest('form');
    if (!form || btn.disabled) return;
    var target = form.elements[btn.getAttribute('data-ai-translate')];
    var source = form.elements[btn.getAttribute('data-ai-from')];
    if (!target || !source) return;
    var text = (source.value || '').trim();
    if (!text) { toast(T.empty, 'error'); source.focus(); return; }

    (target.value || '').trim() ? ask(T.replace).then(function (ok) { if (ok) go(); }) : go();

    function go() {
      var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      var body = new URLSearchParams();
      body.append('form', btn.getAttribute('data-ai-form'));
      body.append('field', btn.getAttribute('data-ai-translate'));
      body.append('to', btn.getAttribute('data-ai-to'));
      body.append('text', text);
      var label = btn.textContent;
      btn.disabled = true; btn.classList.add('is-busy'); btn.textContent = T.working;
      fetch(btn.getAttribute('data-ai-url'), { method: 'POST', body: body, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) {
          if (d && typeof d.text === 'string') {
            target.value = d.text;
            // the form's own listeners (draft, "unsaved changes", character counters) see the new text
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            target.classList.add('ai-filled');
            setTimeout(function () { target.classList.remove('ai-filled'); }, 2500);
            toast(T.done, 'success');
          } else {
            toast((d && d.error) || T.error, 'error');
          }
        })
        .catch(function () { toast(T.error, 'error'); })
        .then(function () { btn.disabled = false; btn.classList.remove('is-busy'); btn.textContent = label; });
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-ai-translate]');
    if (btn) { e.preventDefault(); run(btn); }
  });
})();
