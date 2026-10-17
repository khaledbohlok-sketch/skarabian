/* Documents & Photos upload popup: drag & drop, file list, AJAX upload with progress and per-file errors.
 * (An external file because the Content-Security-Policy blocks inline scripts.) */
(function () {
  var form = document.getElementById('doc-upload-form');
  if (!form) return;
  var input = document.getElementById('doc-upload-input');
  var zone = form.querySelector('[data-dropzone]');
  var list = form.querySelector('[data-file-list]');
  var progress = form.querySelector('[data-progress]');
  var bar = form.querySelector('[data-progress-bar]');
  var submitBtn = form.querySelector('[data-submit]');
  var errorBox = form.querySelector('[data-upload-errors]');

  function showErrors(messages) {
    errorBox.innerHTML = '';
    if (!messages || !messages.length) { errorBox.hidden = true; return; }
    if (messages.length === 1) {
      errorBox.textContent = messages[0];
    } else {
      var ul = document.createElement('ul');
      messages.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
      errorBox.appendChild(ul);
    }
    errorBox.hidden = false;
  }

  function renderList(files) {
    list.innerHTML = '';
    Array.prototype.forEach.call(files, function (f) {
      var li = document.createElement('li');
      li.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
      list.appendChild(li);
    });
  }

  input.addEventListener('change', function () { renderList(input.files); });

  ['dragenter', 'dragover'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      renderList(input.files);
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!input.files.length) {
      showErrors([form.dataset.msgNone]);
      return;
    }
    showErrors(null);
    var fd = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    progress.hidden = false;
    submitBtn.disabled = true;
    xhr.upload.addEventListener('progress', function (e) {
      if (e.lengthComputable) {
        bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      }
    });
    xhr.onload = function () {
      submitBtn.disabled = false;
      progress.hidden = true;
      var data = null;
      try { data = JSON.parse(xhr.responseText); } catch (err) { /* non-JSON: treat as success below */ }
      // refused (expired page or sign-in, no permission, too large…): nothing was uploaded
      if (xhr.status === 419 || xhr.status === 401) {
        var ar = document.documentElement.dir === 'rtl';
        showErrors([form.dataset.msgExpired || (ar ? 'انتهت صلاحية الصفحة. أعد تحميل الصفحة ثم ارفع الملفات مرة أخرى.' : 'This page has expired. Reload the page and upload the files again.')]);
        return;
      }
      if (xhr.status < 200 || xhr.status >= 300) { showErrors([form.dataset.msgError]); return; }
      if (data && data.errors && data.errors.length) { showErrors(data.errors); return; }
      window.parent.location.reload();
    };
    xhr.onerror = function () {
      submitBtn.disabled = false;
      progress.hidden = true;
      showErrors([form.dataset.msgError]);
    };
    xhr.send(fd);
  });
})();
