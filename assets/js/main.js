/* Сайт в Рост — фронтенд-логика */
(function () {
  'use strict';

  var d = document;

  /* ---------- Бургер-меню и аккордеоны выпадающих меню ---------- */
  var burger = d.getElementById('burger');
  if (burger) {
    burger.addEventListener('click', function () {
      var open = d.body.classList.toggle('menu-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  d.querySelectorAll('.nav__item--drop > .nav__link--btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var item = btn.parentElement;
      var wasOpen = item.classList.contains('is-open');
      d.querySelectorAll('.nav__item.is-open').forEach(function (i) { i.classList.remove('is-open'); });
      if (!wasOpen) item.classList.add('is-open');
    });
  });
  d.addEventListener('click', function (e) {
    if (d.body.classList.contains('menu-open') && e.target.closest && e.target.closest('.nav a')) {
      d.body.classList.remove('menu-open');
    }
  });

  /* ---------- Поиск (лупа) ---------- */
  var searchPop = d.getElementById('searchPop');
  var searchToggle = d.getElementById('searchToggle');
  var searchClose = d.getElementById('searchClose');
  var searchInput = d.getElementById('searchInput');
  var searchResults = d.getElementById('searchResults');
  var searchTimer = null;

  function openSearch() {
    if (!searchPop) return;
    searchPop.hidden = false;
    d.body.style.overflow = 'hidden';
    setTimeout(function () { searchInput && searchInput.focus(); }, 60);
  }
  function closeSearch() {
    if (!searchPop) return;
    searchPop.hidden = true;
    d.body.style.overflow = '';
  }
  if (searchToggle) searchToggle.addEventListener('click', openSearch);
  if (searchClose) searchClose.addEventListener('click', closeSearch);
  if (searchPop) searchPop.addEventListener('click', function (e) { if (e.target === searchPop) closeSearch(); });
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSearch(); closeAllModals(); }
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var q = searchInput.value.trim();
      if (q.length < 2) {
        searchResults.innerHTML = '<div class="search-pop__hint">Начните вводить — минимум 2 символа. Ищем по страницам, блогу и кейсам.</div>';
        return;
      }
      searchTimer = setTimeout(function () {
        searchResults.innerHTML = '<div class="search-pop__hint">Ищем…</div>';
        fetch('/api/search.php?q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var res = data.results || [];
            if (!res.length) {
              searchResults.innerHTML = '<div class="search-pop__hint">По запросу «' + escapeHtml(q) + '» ничего не найдено. <a href="/search?q=' + encodeURIComponent(q) + '">Показать страницу поиска</a></div>';
              return;
            }
            var html = '';
            res.forEach(function (r) {
              html += '<a class="search-item" href="' + escapeAttr(r.url) + '">'
                + '<span class="search-item__type">' + escapeHtml(r.type) + '</span>'
                + '<span class="search-item__title">' + escapeHtml(r.title) + '</span>'
                + '<span class="search-item__snip">' + escapeHtml(r.snippet) + '</span>'
                + '</a>';
            });
            searchResults.innerHTML = html;
          })
          .catch(function () { searchResults.innerHTML = '<div class="search-pop__hint">Ошибка поиска. Попробуйте ещё раз.</div>'; });
      }, 280);
    });
    var searchForm = d.getElementById('searchForm');
    if (searchForm) searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var q = searchInput.value.trim();
      if (q) window.location.href = '/search?q=' + encodeURIComponent(q);
    });
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function escapeAttr(s) { return escapeHtml(s); }

  /* ---------- Модалки ---------- */
  function closeAllModals() {
    d.querySelectorAll('.modal').forEach(function (m) { m.hidden = true; });
    d.body.style.overflow = '';
  }
  d.querySelectorAll('[data-open-modal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = d.getElementById(b.getAttribute('data-open-modal'));
      if (m) { m.hidden = false; d.body.style.overflow = 'hidden'; }
    });
  });
  d.querySelectorAll('[data-close-modal]').forEach(function (b) {
    b.addEventListener('click', closeAllModals);
  });

  /* ---------- Табы ---------- */
  d.querySelectorAll('[data-tabs]').forEach(function (wrap) {
    var btns = wrap.querySelectorAll('.tabs__btn');
    var panels = wrap.querySelectorAll('.tabs__panel');
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        btns.forEach(function (b) { b.classList.remove('is-active'); });
        panels.forEach(function (p) { p.classList.remove('is-active'); });
        btn.classList.add('is-active');
        var target = wrap.querySelector('[data-panel="' + btn.getAttribute('data-tab') + '"]');
        if (target) target.classList.add('is-active');
      });
    });
  });

  /* ---------- Квиз расчёта стоимости ---------- */
  var quiz = d.getElementById('quizCalc');
  if (quiz) {
    var panels = quiz.querySelectorAll('.quiz__panel');
    var dots = quiz.querySelectorAll('.quiz__step-dot');
    var current = 0;
    var answers = {};
    var PRICE_BASE = { 'Лендинг': 45000, 'Сайт-визитка': 35000, 'Корпоративный сайт': 120000, 'Интернет-магазин': 250000, 'Пока не знаю': 80000 };

    function show(i) {
      panels.forEach(function (p, idx) { p.classList.toggle('is-active', idx === i); });
      dots.forEach(function (dd, idx) { dd.classList.toggle('is-done', idx <= i); });
      current = i;
    }
    quiz.querySelectorAll('[data-quiz-next]').forEach(function (b) {
      b.addEventListener('click', function () {
        var panel = panels[current];
        var checked = panel.querySelectorAll('input[type=checkbox]:checked, input[type=radio]:checked');
        var vals = [];
        checked.forEach(function (c) { vals.push(c.value); });
        var key = panel.getAttribute('data-step-key');
        if (key) answers[key] = vals;
        if (current === panels.length - 2) { // перед шагом контактов показываем оценку
          var est = quiz.querySelector('[data-quiz-estimate]');
          if (est) est.textContent = estimatePrice();
        }
        show(Math.min(current + 1, panels.length - 1));
      });
    });
    quiz.querySelectorAll('[data-quiz-back]').forEach(function (b) {
      b.addEventListener('click', function () { show(Math.max(current - 1, 0)); });
    });
    function estimatePrice() {
      var type = (answers['Тип сайта'] || [''])[0];
      var base = PRICE_BASE[type] || 80000;
      var k = 1 + ((answers['Что важно'] || []).length * 0.08);
      if ((answers['Срок'] || [''])[0] === 'Срочно, в течение 2 недель') k += 0.15;
      var low = Math.round(base * k / 1000) * 1000;
      return 'от ' + low.toLocaleString('ru-RU') + ' ₽';
    }
    // прокидываем ответы квиза в скрытое поле формы при отправке
    var qForm = quiz.querySelector('form[data-form]');
    if (qForm) {
      qForm.addEventListener('svr:before-send', function () {
        var inp = qForm.querySelector('[name="quiz_data"]');
        if (!inp) { inp = d.createElement('input'); inp.type = 'hidden'; inp.name = 'quiz_data'; qForm.appendChild(inp); }
        inp.value = JSON.stringify(answers);
      });
    }
  }

  /* ---------- Формы: валидация, согласие, AJAX ---------- */
  d.querySelectorAll('form[data-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.querySelector('.lead-form__msg');
      // сброс ошибок
      form.querySelectorAll('.is-error').forEach(function (el) { el.classList.remove('is-error'); });
      var ok = true;
      var name = form.querySelector('[name=name]');
      var phone = form.querySelector('[name=phone]');
      var email = form.querySelector('[name=email]');
      var agree = form.querySelector('[name=agree]');
      if (name && name.value.trim().length < 2) { name.closest('.field, label') && name.closest('.field').classList.add('is-error'); ok = false; }
      var contactOk = (phone && /^[\d\s()+\-]{6,30}$/.test(phone.value.trim())) || (email && /\S+@\S+\.\S+/.test(email.value.trim()));
      if (!contactOk) {
        if (phone) phone.closest('.field').classList.add('is-error');
        if (email) email.closest('.field').classList.add('is-error');
        ok = false;
      }
      if (agree && !agree.checked) {
        var label = agree.closest('label');
        if (label) label.classList.add('is-error');
        if (msg) { msg.textContent = 'Отметьте галочку согласия на обработку персональных данных — без неё отправить нельзя.'; msg.className = 'lead-form__msg is-err'; }
        agree.focus();
        return;
      }
      if (!ok) {
        if (msg) { msg.textContent = 'Проверьте выделенные поля.'; msg.className = 'lead-form__msg is-err'; }
        return;
      }
      form.dispatchEvent(new CustomEvent('svr:before-send'));
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.dataset.old = btn.innerHTML; btn.innerHTML = 'Отправляем…'; }
      var fd = new FormData(form);
      fd.set('form', form.getAttribute('data-form') || 'site');
      fd.set('page', form.getAttribute('data-page') || window.location.pathname);
      // quiz_data -> quiz[ответы]
      var qd = fd.get('quiz_data');
      if (qd) {
        try {
          var parsed = JSON.parse(qd);
          Object.keys(parsed).forEach(function (k) { fd.set('quiz[' + k + ']', (parsed[k] || []).join(', ')); });
        } catch (err) { /* noop */ }
        fd.delete('quiz_data');
      }
      fetch('/api/lead.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': (d.querySelector('meta[name=csrf-token]') || {}).content || form.querySelector('[name=csrf]').value } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            form.reset();
            if (msg) { msg.textContent = res.message || 'Заявка отправлена!'; msg.className = 'lead-form__msg is-ok'; }
            try { localStorage.setItem('svr_lead_sent', '1'); } catch (err2) { /* noop */ }
          } else {
            if (msg) { msg.textContent = res.error || 'Не удалось отправить. Позвоните нам или напишите в мессенджер.'; msg.className = 'lead-form__msg is-err'; }
          }
        })
        .catch(function () {
          if (msg) { msg.textContent = 'Ошибка сети. Попробуйте ещё раз.'; msg.className = 'lead-form__msg is-err'; }
        })
        .finally(function () { if (btn) { btn.disabled = false; if (btn.dataset.old) btn.innerHTML = btn.dataset.old; } });
    });
    // снимаем ошибку при вводе
    form.addEventListener('input', function (e) {
      var f = e.target.closest('.field.is-error'); if (f) f.classList.remove('is-error');
      var lbl = e.target.closest('.agree.is-error'); if (lbl) lbl.classList.remove('is-error');
    });
  });

  /* ---------- Cookie-уведомление (только для новых посетителей) ---------- */
  var cookieBar = d.getElementById('cookieBar');
  if (cookieBar) {
    var accepted = false;
    try { accepted = localStorage.getItem('svr_cookie_ok') === '1'; } catch (err) { /* noop */ }
    if (!accepted) {
      setTimeout(function () { cookieBar.hidden = false; }, 900);
    }
    var acceptBtn = d.getElementById('cookieAccept');
    if (acceptBtn) acceptBtn.addEventListener('click', function () {
      try { localStorage.setItem('svr_cookie_ok', '1'); } catch (err2) { /* noop */ }
      d.cookie = 'svr_cookie_ok=1; path=/; max-age=' + (60 * 60 * 24 * 365);
      cookieBar.hidden = true;
    });
  }

  /* ---------- Кнопка «Наверх» ---------- */
  var toTop = d.getElementById('toTop');
  if (toTop) {
    window.addEventListener('scroll', function () {
      toTop.classList.toggle('is-visible', window.scrollY > 600);
    }, { passive: true });
    toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }

  /* ---------- Появление секций ---------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    d.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    d.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
  }
})();
