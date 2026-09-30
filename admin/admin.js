/* Админка: TinyMCE + загрузка изображений */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';

  function uploadBase64(name, data) {
    return fetch('/api/upload.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ name: name, data: data })
    }).then(function (r) { return r.json(); });
  }

  function fileToBase64(file) {
    return new Promise(function (resolve, reject) {
      var fr = new FileReader();
      fr.onload = function () { resolve(String(fr.result)); };
      fr.onerror = reject;
      fr.readAsDataURL(file);
    });
  }

  function initTiny() {
    if (typeof tinymce === 'undefined') { setTimeout(initTiny, 300); return; }
    if (document.querySelectorAll('textarea.rte').length === 0) return;
    tinymce.init({
      selector: 'textarea.rte',
      language: 'ru',
      language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.7.29/langs6/ru.min.js',
      height: 520,
      branding: false,
      promotion: false,
      plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount codesample emoticons',
      toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link unlink image table codesample blockquote | charmap emoticons hr | removeformat visualblocks code fullscreen help',
      menubar: 'file edit view insert format tools table',
      valid_elements: '*[*]',
      extended_valid_elements: 'div[*],section[*],span[*],details[*],summary[*],button[*],svg[*],path[*]',
      convert_urls: false,
      relative_urls: false,
      style_formats: [
        { title: 'Кнопка основная', selector: 'a', attributes: { 'class': 'btn btn--primary' } },
        { title: 'Кнопка лайм', selector: 'a', attributes: { 'class': 'btn btn--lime' } },
        { title: 'Кнопка контур', selector: 'a', attributes: { 'class': 'btn btn--ghost' } },
        { title: 'Лид-абзац', selector: 'p', attributes: { 'class': 'lead' } },
        { title: 'Секция 2 колонки', block: 'div', attributes: { 'class': 'grid grid--2' } },
        { title: 'Карточка услуги', block: 'div', attributes: { 'class': 'svc-card' } },
        { title: 'Блок CTA', block: 'div', attributes: { 'class': 'case-cta' } }
      ],
      images_upload_handler: function (blobInfo) {
        return new Promise(function (resolve, reject) {
          var fr = new FileReader();
          fr.onload = function () {
            uploadBase64(blobInfo.filename() || 'image.png', String(fr.result))
              .then(function (res) { res.ok ? resolve(res.url) : reject('Ошибка: ' + (res.error || 'загрузка не удалась')); })
              .catch(function () { reject('Ошибка сети'); });
          };
          fr.readAsDataURL(blobInfo.blob());
        });
      },
      file_picker_callback: function (cb, value, meta) {
        if (meta.filetype !== 'image') { cb(value); return; }
        var inp = document.createElement('input');
        inp.type = 'file';
        inp.accept = 'image/*';
        inp.onchange = function () {
          var f = inp.files[0];
          if (!f) return;
          fileToBase64(f).then(function (b64) {
            return uploadBase64(f.name, b64);
          }).then(function (res) {
            if (res.ok) cb(res.url, { title: f.name });
            else alert('Ошибка: ' + res.error);
          });
        };
        inp.click();
      },
      setup: function (editor) {
        editor.ui.registry.addMenuItem('svrhtml', {
          text: 'Вставить HTML-код в позицию курсора',
          onAction: function () {
            editor.windowManager.open({
              title: 'Вставка HTML',
              size: 'medium',
              body: { type: 'panel', items: [{ type: 'textarea', name: 'code', label: 'HTML' } ] },
              buttons: [
                { type: 'cancel', text: 'Отмена' },
                { type: 'submit', text: 'Вставить', primary: true }
              ],
              onSubmit: function (api) {
                editor.insertContent(api.getData().code);
                api.close();
              }
            });
          }
        });
        editor.ui.registry.addMenuItem('svrbutton', {
          text: 'Кнопка со ссылкой',
          onAction: function () {
            editor.windowManager.open({
              title: 'Кнопка со ссылкой',
              body: {
                type: 'panel',
                items: [
                  { type: 'input', name: 'url', label: 'URL' },
                  { type: 'input', name: 'text', label: 'Текст кнопки' },
                  { type: 'selectbox', name: 'style', label: 'Стиль', items: [
                    { text: 'Основная', value: 'btn btn--primary' },
                    { text: 'Лайм', value: 'btn btn--lime' },
                    { text: 'Контурная', value: 'btn btn--ghost' }
                  ] }
                ]
              },
              buttons: [ { type: 'cancel', text: 'Отмена' }, { type: 'submit', text: 'Вставить', primary: true } ],
              onSubmit: function (api) {
                var d = api.getData();
                editor.insertContent('<a class="' + d.style + '" href="' + d.url + '">' + d.text + '</a>&nbsp;');
                api.close();
              }
            });
          }
        });
      }
    });
    // пункты в меню Insert
    setTimeout(function () {
      try {
        tinymce.activeEditor;
      } catch (e) { /* noop */ }
    }, 100);
  }

  /* Кнопки загрузки файлов рядом с полями */
  document.querySelectorAll('[data-upload]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.getAttribute('data-upload'));
      var inp = document.createElement('input');
      inp.type = 'file';
      inp.accept = 'image/*';
      inp.onchange = function () {
        var f = inp.files[0];
        if (!f) return;
        btn.disabled = true;
        btn.textContent = 'Загрузка…';
        fileToBase64(f).then(function (b64) { return uploadBase64(f.name, b64); })
          .then(function (res) {
            if (res.ok && target) target.value = res.url;
            else alert('Ошибка: ' + (res.error || 'загрузка не удалась'));
          })
          .catch(function () { alert('Ошибка сети'); })
          .finally(function () { btn.disabled = false; btn.textContent = 'Загрузить'; });
      };
      inp.click();
    });
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initTiny);
  else initTiny();
})();
