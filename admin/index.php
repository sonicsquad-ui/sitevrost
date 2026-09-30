<?php
/**
 * Административная панель сайта «Сайт в Рост».
 * Адрес: /admin — вход по логину и паролю.
 */
require dirname(__DIR__) . '/engine/core.php';
require dirname(__DIR__) . '/engine/render.php';

$s = $_GET['s'] ?? 'dash';
$act = $_POST['action'] ?? '';

/* ---------- Авторизация ---------- */
if (!sv_is_admin()) {
    $err = '';
    if ($act === 'login') {
        if (!sv_csrf_check()) {
            $err = 'Сессия устарела, обновите страницу.';
        } elseif (sv_try_login(trim($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: /admin/?s=dash');
            exit;
        } else {
            $err = sv_login_blocked(sv_ip()) ? 'Слишком много попыток. Вход заблокирован на 15 минут.' : 'Неверный логин или пароль.';
        }
    }
    sv_admin_login_page($err);
    exit;
}

/* ---------- Обработчики POST ---------- */
if ($act) {
    if (!sv_csrf_check()) { http_response_code(403); exit('CSRF'); }
    switch ($act) {
        case 'logout':
            sv_session_destroy();
            header('Location: /admin/');
            exit;
        case 'page_save':
            $pages = sv_pages();
            $id = trim($_POST['id'] ?? '');
            $slug = trim(trim((string)($_POST['slug'] ?? '')), '/');
            if ($slug === '' && ($_POST['title'] ?? '') !== '') $slug = '';
            if (!$id) { $id = sv_generate_id('pg'); $pages[] = ['id' => $id]; }
            foreach ($pages as $k => $p) if ($p['id'] === $id) { $idx = $k; }
            if (!isset($idx)) { $idx = count($pages); $pages[] = ['id' => $id]; }
            foreach ($pages as $k => $p) {
                if (($p['slug'] ?? '') === $slug && $p['id'] !== $id) $errDup = true;
            }
            if (!empty($errDup)) { header('Location: /admin/?s=page_edit&id=' . $id . '&err=' . urlencode('URL уже занят другой страницей')); exit; }
            $pages[$idx] = array_merge($pages[$idx], [
                'id' => $id,
                'slug' => $slug,
                'title' => trim((string)($_POST['title'] ?? '')),
                'h1' => trim((string)($_POST['h1'] ?? '')),
                'crumb' => trim((string)($_POST['crumb'] ?? '')),
                'parent' => trim((string)($_POST['parent'] ?? '')),
                'template' => trim((string)($_POST['template'] ?? 'page')),
                'schema' => trim((string)($_POST['schema'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
                'keywords' => trim((string)($_POST['keywords'] ?? '')),
                'og_image' => trim((string)($_POST['og_image'] ?? '')),
                'content' => (string)($_POST['content'] ?? ''),
                'updated' => sv_today(),
            ]);
            if (!empty($_POST['faq_json'])) {
                $fq = json_decode((string)$_POST['faq_json'], true);
                if (is_array($fq)) $pages[$idx]['faq_items'] = $fq;
            }
            sv_json_write('pages', $pages);
            header('Location: /admin/?s=pages&ok=1');
            exit;
        case 'page_delete':
            $id = $_POST['id'] ?? '';
            sv_json_write('pages', array_values(array_filter(sv_pages(), fn($p) => $p['id'] !== $id)));
            header('Location: /admin/?s=pages&ok=1');
            exit;
        case 'page_duplicate':
            $id = $_POST['id'] ?? '';
            $src = null;
            foreach (sv_pages() as $p) if ($p['id'] === $id) $src = $p;
            if ($src) {
                $copy = $src;
                $copy['id'] = sv_generate_id('pg');
                $copy['slug'] = ($src['slug'] ? $src['slug'] . '-copy' : 'copy-' . substr($copy['id'], 3));
                $copy['title'] = ($src['title'] ?? '') . ' (копия)';
                $copy['updated'] = sv_today();
                $pages = sv_pages();
                $pages[] = $copy;
                sv_json_write('pages', $pages);
            }
            header('Location: /admin/?s=pages&ok=1');
            exit;
        case 'post_save':
            $posts = sv_posts();
            $id = trim($_POST['id'] ?? '');
            if (!$id) { $id = sv_generate_id('post'); $posts[] = ['id' => $id]; }
            $idx = null;
            foreach ($posts as $k => $p) if ($p['id'] === $id) $idx = $k;
            if ($idx === null) { $idx = count($posts); $posts[] = ['id' => $id]; }
            $authors = array_values(array_filter(array_map('trim', (array)($_POST['authors'] ?? []))));
            $posts[$idx] = array_merge($posts[$idx], [
                'id' => $id,
                'slug' => trim((string)($_POST['slug'] ?? ''), '/'),
                'title' => trim((string)($_POST['title'] ?? '')),
                'category' => trim((string)($_POST['category'] ?? '')),
                'date' => trim((string)($_POST['date'] ?: sv_today())),
                'updated' => sv_today(),
                'read_time' => (int)($_POST['read_time'] ?? 7),
                'authors' => $authors,
                'image' => trim((string)($_POST['image'] ?? '')),
                'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? ''))))),
                'content' => (string)($_POST['content'] ?? ''),
            ]);
            sv_json_write('posts', $posts);
            header('Location: /admin/?s=posts&ok=1');
            exit;
        case 'post_delete':
            $id = $_POST['id'] ?? '';
            sv_json_write('posts', array_values(array_filter(sv_posts(), fn($p) => $p['id'] !== $id)));
            header('Location: /admin/?s=posts&ok=1');
            exit;
        case 'post_duplicate':
            $id = $_POST['id'] ?? '';
            $src = null;
            foreach (sv_posts() as $p) if ($p['id'] === $id) $src = $p;
            if ($src) {
                $copy = $src;
                $copy['id'] = sv_generate_id('post');
                $copy['slug'] = ($src['slug'] ?? 'post') . '-copy';
                $copy['title'] = ($src['title'] ?? '') . ' (копия)';
                $posts = sv_posts();
                $posts[] = $copy;
                sv_json_write('posts', $posts);
            }
            header('Location: /admin/?s=posts&ok=1');
            exit;
        case 'case_save':
            $cases = sv_cases();
            $id = trim($_POST['id'] ?? '');
            if (!$id) { $id = sv_generate_id('case'); $cases[] = ['id' => $id]; }
            $idx = null;
            foreach ($cases as $k => $c) if ($c['id'] === $id) $idx = $k;
            if ($idx === null) { $idx = count($cases); $cases[] = ['id' => $id]; }
            $metrics = [];
            foreach ((array)($_POST['m_value'] ?? []) as $i => $v) {
                $l = (string)(($_POST['m_label'] ?? [])[$i] ?? '');
                if (trim((string)$v) !== '' || trim($l) !== '') $metrics[] = ['value' => trim((string)$v), 'label' => trim($l)];
            }
            $cases[$idx] = array_merge($cases[$idx], [
                'id' => $id,
                'slug' => trim((string)($_POST['slug'] ?? ''), '/'),
                'title' => trim((string)($_POST['title'] ?? '')),
                'date' => trim((string)($_POST['date'] ?: sv_today())),
                'industry' => array_values(array_filter(array_map('trim', (array)($_POST['industry'] ?? [])))),
                'service' => array_values(array_filter(array_map('trim', (array)($_POST['service'] ?? [])))),
                'g1' => trim((string)($_POST['g1'] ?? '#5B5BF0')),
                'g2' => trim((string)($_POST['g2'] ?? '#22D3EE')),
                'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
                'metrics' => $metrics,
                'content' => (string)($_POST['content'] ?? ''),
            ]);
            sv_json_write('cases', $cases);
            header('Location: /admin/?s=cases&ok=1');
            exit;
        case 'case_delete':
            $id = $_POST['id'] ?? '';
            sv_json_write('cases', array_values(array_filter(sv_cases(), fn($c) => $c['id'] !== $id)));
            header('Location: /admin/?s=cases&ok=1');
            exit;
        case 'lead_status':
            $id = $_POST['id'] ?? '';
            $leads = sv_json_read('leads', []);
            foreach ($leads as &$l) if ($l['id'] === $id) $l['status'] = ($l['status'] === 'new') ? 'processed' : 'new';
            unset($l);
            sv_json_write('leads', $leads);
            header('Location: /admin/?s=leads&ok=1');
            exit;
        case 'lead_delete':
            $id = $_POST['id'] ?? '';
            sv_json_write('leads', array_values(array_filter(sv_json_read('leads', []), fn($l) => $l['id'] !== $id)));
            header('Location: /admin/?s=leads&ok=1');
            exit;
        case 'settings_save':
            $S = sv_settings();
            $S['site']['name'] = trim((string)($_POST['site_name'] ?? $S['site']['name']));
            $S['site']['domain'] = rtrim(trim((string)($_POST['site_domain'] ?? $S['site']['domain'])), '/');
            $S['contacts']['phone_display'] = trim((string)($_POST['phone_display'] ?? ''));
            $S['contacts']['phone_raw'] = trim((string)($_POST['phone_raw'] ?? ''));
            $S['contacts']['email'] = trim((string)($_POST['c_email'] ?? ''));
            $S['contacts']['telegram'] = trim((string)($_POST['c_tg'] ?? ''));
            $S['contacts']['vk'] = trim((string)($_POST['c_vk'] ?? ''));
            $S['contacts']['max'] = trim((string)($_POST['c_max'] ?? ''));
            $S['contacts']['address'] = trim((string)($_POST['c_addr'] ?? ''));
            $S['contacts']['schedule'] = trim((string)($_POST['c_sched'] ?? ''));
            $S['contacts']['schedule_short'] = trim((string)($_POST['c_sched_s'] ?? ''));
            $S['contacts']['requisites'] = (string)($_POST['c_req'] ?? '');
            $S['topbar']['enabled'] = !empty($_POST['topbar_enabled']);
            $S['topbar']['text'] = trim((string)($_POST['topbar_text'] ?? ''));
            $S['header']['cta_text'] = trim((string)($_POST['hdr_cta'] ?? ''));
            $S['footer']['about'] = (string)($_POST['ftr_about'] ?? '');
            $S['footer']['copyright'] = trim((string)($_POST['ftr_copy'] ?? ''));
            $S['footer']['disclaimer'] = trim((string)($_POST['ftr_disc'] ?? ''));
            $S['cta']['enabled'] = !empty($_POST['cta_enabled']);
            $S['cta']['title'] = trim((string)($_POST['cta_title'] ?? ''));
            $S['cta']['text'] = trim((string)($_POST['cta_text'] ?? ''));
            $S['cta']['button'] = trim((string)($_POST['cta_button'] ?? ''));
            $S['global_block']['enabled'] = !empty($_POST['gb_enabled']);
            $S['global_block']['html'] = (string)($_POST['gb_html'] ?? '');
            $S['lead_email'] = trim((string)($_POST['lead_email'] ?? ''));
            $S['og_image'] = trim((string)($_POST['og_image'] ?? ''));
            $cols = json_decode((string)($_POST['footer_cols_json'] ?? ''), true);
            if (is_array($cols)) $S['footer_cols'] = $cols;
            sv_json_write('settings', $S);
            header('Location: /admin/?s=settings&ok=1');
            exit;
        case 'menu_save':
            $S = sv_settings();
            $menu = json_decode((string)($_POST['menu_json'] ?? ''), true);
            if (is_array($menu)) { $S['menu'] = $menu; sv_json_write('settings', $S); header('Location: /admin/?s=menu&ok=1'); exit; }
            header('Location: /admin/?s=menu&err=' . urlencode('JSON невалиден, изменения не сохранены'));
            exit;
        case 'cats_save':
            $S = sv_settings();
            $cats = [];
            foreach ((array)($_POST['cat_slug'] ?? []) as $i => $slugC) {
                $slugC = trim((string)$slugC);
                $name = trim((string)(($_POST['cat_name'] ?? [])[$i] ?? ''));
                if ($slugC !== '' && $name !== '') $cats[$slugC] = ['name' => $name, 'description' => trim((string)(($_POST['cat_desc'] ?? [])[$i] ?? ''))];
            }
            $newSlug = trim((string)($_POST['new_cat_slug'] ?? ''));
            $newName = trim((string)($_POST['new_cat_name'] ?? ''));
            if ($newSlug && $newName) $cats[$newSlug] = ['name' => $newName, 'description' => ''];
            $S['blog_cats'] = $cats;
            sv_json_write('settings', $S);
            header('Location: /admin/?s=cats&ok=1');
            exit;
        case 'password_save':
            $cfg = sv_config();
            if (!password_verify((string)($_POST['cur'] ?? ''), $cfg['admin_pass_hash'] ?? '')) {
                header('Location: /admin/?s=security&err=' . urlencode('Текущий пароль неверен'));
                exit;
            }
            $new = (string)($_POST['new'] ?? '');
            if (strlen($new) < 8) { header('Location: /admin/?s=security&err=' . urlencode('Новый пароль слишком короткий (мин. 8 символов)')); exit; }
            if ($new !== (string)($_POST['new2'] ?? '')) { header('Location: /admin/?s=security&err=' . urlencode('Пароли не совпадают')); exit; }
            sv_save_admin_password($new);
            header('Location: /admin/?s=security&ok=1');
            exit;
    }
}

/* ---------- Страницы админки ---------- */
sv_admin_page($s);
exit;

/* ================================================================== */
function sv_admin_login_page(string $err): void {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Вход — Сайт в Рост</title>
<style>
body{margin:0;font-family:'Segoe UI',Arial,sans-serif;background:#0E1220;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border-radius:18px;padding:38px 34px;width:100%;max-width:380px;box-shadow:0 30px 70px rgba(0,0,0,.5)}
h1{font-size:20px;margin:0 0 6px}p{color:#667;margin:0 0 22px;font-size:14px}
label{display:block;font-size:13px;font-weight:600;color:#445;margin-bottom:14px}
input{width:100%;border:2px solid #e3e7f0;border-radius:10px;padding:11px 14px;font-size:15px;margin-top:6px}
input:focus{outline:none;border-color:#5B5BF0}
button{width:100%;background:linear-gradient(118deg,#5B5BF0,#22D3EE);border:0;color:#fff;font-weight:700;font-size:15px;border-radius:10px;padding:13px;cursor:pointer}
.err{background:#FDECEE;color:#C4323A;border-radius:10px;padding:10px 14px;font-size:13.5px;margin-bottom:16px}
</style>
</head>
<body>
<form class="box" method="post" action="/admin/">
  <h1>Панель управления</h1>
  <p>Сайт в Рост · доступ только для сотрудников</p>
  <?php if ($err): ?><div class="err"><?= sv_e($err) ?></div><?php endif; ?>
  <input type="hidden" name="action" value="login">
  <?= sv_csrf_field() ?>
  <label>Логин<input type="text" name="login" autocomplete="username" required maxlength="50"></label>
  <label>Пароль<input type="password" name="password" autocomplete="current-password" required maxlength="100"></label>
  <button type="submit">Войти</button>
</form>
</body>
</html>
<?php
}

/* ---------- Каркас админки ---------- */
function sv_admin_page(string $s): void {
    $S = sv_settings();
    $leads = sv_json_read('leads', []);
    $newLeads = count(array_filter($leads, fn($l) => ($l['status'] ?? 'new') === 'new'));
    $nav = [
        'dash' => 'Дашборд', 'pages' => 'Страницы', 'posts' => 'Статьи блога', 'cats' => 'Категории блога',
        'cases' => 'Кейсы', 'leads' => 'Заявки' . ($newLeads ? " ($newLeads)" : ''), 'settings' => 'Шапка и футер', 'menu' => 'Меню', 'security' => 'Безопасность',
    ];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Админка — Сайт в Рост</title>
<meta name="csrf-token" content="<?= sv_e(sv_csrf_token()) ?>">
<link rel="stylesheet" href="/admin/admin.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin" onerror="var s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js';document.head.appendChild(s);"></script>
</head>
<body>
<div class="adm">
  <aside class="adm__side">
    <div class="adm__logo">Сайт в Рост <small>админ-панель</small></div>
    <nav>
      <?php foreach ($nav as $k => $label): ?>
        <a class="<?= $s === $k || ($s === 'page_edit' && $k === 'pages') || (($s === 'post_edit') && $k === 'posts') || (($s === 'case_edit') && $k === 'cases') ? 'is-active' : '' ?>" href="/admin/?s=<?= $k ?>"><?= sv_e($label) ?></a>
      <?php endforeach; ?>
      <a class="adm__exit" href="/">← На сайт</a>
      <form method="post"><input type="hidden" name="action" value="logout"><?= sv_csrf_field() ?><button type="submit">Выйти</button></form>
    </nav>
  </aside>
  <main class="adm__main">
    <?php if (isset($_GET['ok'])) echo '<div class="flash flash--ok">Сохранено.</div>'; ?>
    <?php if (isset($_GET['err'])) echo '<div class="flash flash--err">' . sv_e((string)$_GET['err']) . '</div>'; ?>
    <?php
    switch ($s) {
        case 'pages': sv_adm_pages(); break;
        case 'page_edit': sv_adm_page_edit($_GET['id'] ?? ''); break;
        case 'posts': sv_adm_posts(); break;
        case 'post_edit': sv_adm_post_edit($_GET['id'] ?? ''); break;
        case 'cats': sv_adm_cats(); break;
        case 'cases': sv_adm_cases(); break;
        case 'case_edit': sv_adm_case_edit($_GET['id'] ?? ''); break;
        case 'leads': sv_adm_leads(); break;
        case 'settings': sv_adm_settings(); break;
        case 'menu': sv_adm_menu(); break;
        case 'security': sv_adm_security(); break;
        default: sv_adm_dash($newLeads);
    }
    ?>
  </main>
</div>
<script src="/admin/admin.js"></script>
</body>
</html>
<?php
}

function adm_th(string $t): string { return '<th>' . sv_e($t) . '</th>'; }

/* ---------- Дашборд ---------- */
function sv_adm_dash(int $newLeads): void {
    $pages = sv_pages(); $posts = sv_posts(); $cases = sv_cases(); $leads = sv_json_read('leads', []);
    ?>
    <h1>Дашборд</h1>
    <div class="stats">
      <div class="stat"><b><?= count($pages) ?></b><span>страниц сайта</span></div>
      <div class="stat"><b><?= count($posts) ?></b><span>статей в блоге</span></div>
      <div class="stat"><b><?= count($cases) ?></b><span>кейсов</span></div>
      <div class="stat"><b><?= $newLeads ?></b><span>новых заявок</span></div>
    </div>
    <h2>Последние заявки</h2>
    <?php sv_adm_leads_table(array_slice($leads, 0, 5), false); ?>
    <p class="hint">Разделы: <b>Страницы</b> — редактирование всех страниц сайта в визуальном редакторе; <b>Статьи блога</b> — редактор статей; <b>Шапка и футер</b> — сквозные блоки; <b>Меню</b> — навигация.</p>
<?php
}

/* ---------- Страницы ---------- */
function sv_adm_pages(): void {
    ?>
    <h1>Страницы сайта <a class="btn btn--new" href="/admin/?s=page_edit">+ Создать страницу</a></h1>
    <table class="tbl">
      <tr><?= adm_th('Название (Title)') ?><?= adm_th('URL') ?><?= adm_th('Шаблон') ?><?= adm_th('Обновлена') ?><?= adm_th('Действия') ?></tr>
      <?php foreach (sv_pages() as $p): ?>
        <tr>
          <td><b><?= sv_e($p['title'] ?? '(без title)') ?></b></td>
          <td><code>/<?= sv_e($p['slug'] ?? '') ?></code></td>
          <td><?= sv_e($p['template'] ?? 'page') ?></td>
          <td><?= sv_e($p['updated'] ?? '') ?></td>
          <td class="td-act">
            <a class="btn" href="/admin/?s=page_edit&id=<?= sv_e($p['id']) ?>">Редактировать</a>
            <form method="post" onsubmit="return confirm('Скопировать страницу?')"><input type="hidden" name="action" value="page_duplicate"><input type="hidden" name="id" value="<?= sv_e($p['id']) ?>"><?= sv_csrf_field() ?><button class="btn" type="submit">Копия</button></form>
            <form method="post" onsubmit="return confirm('Удалить страницу безвозвратно?')"><input type="hidden" name="action" value="page_delete"><input type="hidden" name="id" value="<?= sv_e($p['id']) ?>"><?= sv_csrf_field() ?><button class="btn btn--danger" type="submit">Удалить</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
<?php
}

function sv_adm_page_edit(string $id): void {
    $p = ['title' => '', 'h1' => '', 'crumb' => '', 'slug' => '', 'parent' => '', 'template' => 'page', 'schema' => '', 'description' => '', 'keywords' => '', 'og_image' => '', 'content' => '', 'faq_items' => []];
    $found = false;
    foreach (sv_pages() as $x) if ($x['id'] === $id) { $p = array_merge($p, $x); $found = true; }
    if ($id && !$found) { echo '<p>Страница не найдена.</p>'; return; }
    ?>
    <h1><?= $found ? 'Редактирование страницы' : 'Новая страница' ?></h1>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="page_save">
      <input type="hidden" name="id" value="<?= sv_e($id) ?>">
      <?= sv_csrf_field() ?>
      <div class="frm__row">
        <label class="frm__w">Title (заголовок вкладки)<input name="title" value="<?= sv_e($p['title']) ?>" required></label>
        <label>URL (slug)<input name="slug" value="<?= sv_e($p['slug']) ?>" placeholder="например: seo/audit (пусто = главная)"></label>
      </div>
      <div class="frm__row">
        <label class="frm__w">H1 на странице<input name="h1" value="<?= sv_e($p['h1']) ?>"></label>
        <label>Название в крошках<input name="crumb" value="<?= sv_e($p['crumb']) ?>"></label>
      </div>
      <div class="frm__row">
        <label>Родительская страница (для крошек)
          <select name="parent">
            <option value="">— нет (верхний уровень)</option>
            <?php foreach (sv_pages() as $par): if ($par['id'] === $id || ($par['slug'] ?? '') === '') continue; ?>
              <option value="<?= sv_e($par['slug']) ?>" <?= ($p['parent'] ?? '') === ($par['slug'] ?? '') ? 'selected' : '' ?>>/<?= sv_e($par['slug']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Шаблон
          <select name="template">
            <?php foreach (['page' => 'Обычная страница', 'home' => 'Главная', 'wide' => 'Широкая', 'faq' => 'FAQ', 'contacts' => 'Контакты', 'sitemap-html' => 'Карта сайта'] as $tv => $tl): ?>
              <option value="<?= $tv ?>" <?= $p['template'] === $tv ? 'selected' : '' ?>><?= $tl ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Schema.org<input name="schema" value="<?= sv_e($p['schema']) ?>" placeholder="Service / пусто"></label>
      </div>
      <label>Description (мета-описание)<textarea name="description" rows="2"><?= sv_e($p['description']) ?></textarea></label>
      <label>Keywords<input name="keywords" value="<?= sv_e($p['keywords']) ?>"></label>
      <div class="frm__row">
        <label class="frm__w">OG-изображение (путь или загрузите)
          <span class="frm__with-btn"><input name="og_image" id="f_og_image" value="<?= sv_e($p['og_image']) ?>" placeholder="/upload/2026/09/xxx.jpg"><button type="button" class="btn" data-upload="#f_og_image">Загрузить</button></span>
        </label>
      </div>
      <label>Контент страницы (визуальный редактор; вкладка «Код» — вставка HTML в любое место)
        <textarea name="content" class="rte" rows="18"><?= sv_e($p['content']) ?></textarea>
      </label>
      <label>FAQ-элементы (JSON: [{"q":"…","a":"…"}]) — для микроразметки FAQPage<textarea name="faq_json" rows="3"><?= sv_e(json_encode($p['faq_items'] ?? [], JSON_UNESCAPED_UNICODE)) ?></textarea></label>
      <div class="frm__btns">
        <button class="btn btn--primary" type="submit">Сохранить</button>
        <a class="btn" href="/<?= sv_e($p['slug']) ?>" target="_blank">Открыть на сайте</a>
        <a class="btn" href="/admin/?s=pages">К списку</a>
      </div>
    </form>
<?php
}

/* ---------- Блог ---------- */
function sv_adm_posts(): void {
    ?>
    <h1>Статьи блога <a class="btn btn--new" href="/admin/?s=post_edit">+ Новая статья</a></h1>
    <table class="tbl">
      <tr><?= adm_th('Заголовок') ?><?= adm_th('Категория') ?><?= adm_th('Дата') ?><?= adm_th('Действия') ?></tr>
      <?php foreach (sv_posts() as $p): ?>
        <tr>
          <td><b><?= sv_e($p['title']) ?></b></td>
          <td><?= sv_e($p['category']) ?></td>
          <td><?= sv_e($p['date']) ?></td>
          <td class="td-act">
            <a class="btn" href="/admin/?s=post_edit&id=<?= sv_e($p['id']) ?>">Редактировать</a>
            <form method="post" onsubmit="return confirm('Скопировать статью?')"><input type="hidden" name="action" value="post_duplicate"><input type="hidden" name="id" value="<?= sv_e($p['id']) ?>"><?= sv_csrf_field() ?><button class="btn" type="submit">Копия</button></form>
            <form method="post" onsubmit="return confirm('Удалить статью?')"><input type="hidden" name="action" value="post_delete"><input type="hidden" name="id" value="<?= sv_e($p['id']) ?>"><?= sv_csrf_field() ?><button class="btn btn--danger" type="submit">Удалить</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
<?php
}

function sv_adm_post_edit(string $id): void {
    $p = ['title' => '', 'slug' => '', 'category' => '', 'date' => sv_today(), 'read_time' => 7, 'authors' => [], 'image' => '', 'excerpt' => '', 'description' => '', 'tags' => [], 'content' => ''];
    foreach (sv_posts() as $x) if ($x['id'] === $id) $p = array_merge($p, $x);
    $cats = sv_blog_cats();
    $authors = sv_authors();
    ?>
    <h1><?= $id ? 'Редактирование статьи' : 'Новая статья' ?></h1>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="post_save">
      <input type="hidden" name="id" value="<?= sv_e($id) ?>">
      <?= sv_csrf_field() ?>
      <label class="frm__w">Заголовок<input name="title" value="<?= sv_e($p['title']) ?>" required></label>
      <div class="frm__row">
        <label>URL (slug)<input name="slug" value="<?= sv_e($p['slug']) ?>" required placeholder="latinskimi-simbolami"></label>
        <label>Категория
          <select name="category">
            <?php foreach ($cats as $cs => $ci): ?><option value="<?= sv_e($cs) ?>" <?= $p['category'] === $cs ? 'selected' : '' ?>><?= sv_e($ci['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Дата<input type="date" name="date" value="<?= sv_e($p['date']) ?>"></label>
        <label>Минут чтения<input type="number" name="read_time" value="<?= (int)$p['read_time'] ?>" min="1"></label>
      </div>
      <label>Авторы (1–2)
        <?php foreach ($authors as $a): ?>
          <label class="chk"><input type="checkbox" name="authors[]" value="<?= sv_e($a['id']) ?>" <?= in_array($a['id'], $p['authors'] ?? []) ? 'checked' : '' ?>> <?= sv_e($a['name']) ?> — <?= sv_e($a['role']) ?></label>
        <?php endforeach; ?>
      </label>
      <div class="frm__row">
        <label class="frm__w">Обложка
          <span class="frm__with-btn"><input name="image" id="f_post_image" value="<?= sv_e($p['image']) ?>"><button type="button" class="btn" data-upload="#f_post_image">Загрузить</button></span>
        </label>
        <label class="frm__w">Теги (через запятую)<input name="tags" value="<?= sv_e(implode(', ', $p['tags'] ?? [])) ?>"></label>
      </div>
      <label>Анонс (excerpt)<textarea name="excerpt" rows="2"><?= sv_e($p['excerpt']) ?></textarea></label>
      <label>Description<textarea name="description" rows="2"><?= sv_e($p['description']) ?></textarea></label>
      <label>Текст статьи (визуальный редактор)
        <textarea name="content" class="rte" rows="20"><?= sv_e($p['content']) ?></textarea>
      </label>
      <div class="frm__btns">
        <button class="btn btn--primary" type="submit">Сохранить</button>
        <?php if ($id): ?><a class="btn" href="/blog/<?= sv_e($p['slug']) ?>" target="_blank">Открыть на сайте</a><?php endif; ?>
        <a class="btn" href="/admin/?s=posts">К списку</a>
      </div>
    </form>
<?php
}

function sv_adm_cats(): void {
    $cats = sv_blog_cats();
    ?>
    <h1>Категории блога</h1>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="cats_save">
      <?= sv_csrf_field() ?>
      <?php foreach ($cats as $cs => $ci): ?>
        <div class="frm__row">
          <label>Slug<input name="cat_slug[]" value="<?= sv_e($cs) ?>" required></label>
          <label class="frm__w">Название<input name="cat_name[]" value="<?= sv_e($ci['name']) ?>" required></label>
          <label class="frm__w">Описание<input name="cat_desc[]" value="<?= sv_e($ci['description'] ?? '') ?>"></label>
        </div>
      <?php endforeach; ?>
      <h2>Добавить категорию</h2>
      <div class="frm__row">
        <label>Slug (латиницей)<input name="new_cat_slug" placeholder="novosti"></label>
        <label>Название<input name="new_cat_name" placeholder="Новости"></label>
      </div>
      <button class="btn btn--primary" type="submit">Сохранить категории</button>
    </form>
<?php
}

/* ---------- Кейсы ---------- */
function sv_adm_cases(): void {
    ?>
    <h1>Кейсы <a class="btn btn--new" href="/admin/?s=case_edit">+ Новый кейс</a></h1>
    <table class="tbl">
      <tr><?= adm_th('Название') ?><?= adm_th('Дата') ?><?= adm_th('Действия') ?></tr>
      <?php foreach (sv_cases() as $c): ?>
        <tr>
          <td><b><?= sv_e($c['title']) ?></b></td>
          <td><?= sv_e($c['date']) ?></td>
          <td class="td-act">
            <a class="btn" href="/admin/?s=case_edit&id=<?= sv_e($c['id']) ?>">Редактировать</a>
            <form method="post" onsubmit="return confirm('Удалить кейс?')"><input type="hidden" name="action" value="case_delete"><input type="hidden" name="id" value="<?= sv_e($c['id']) ?>"><?= sv_csrf_field() ?><button class="btn btn--danger" type="submit">Удалить</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
<?php
}

function sv_adm_case_edit(string $id): void {
    $c = ['title' => '', 'slug' => '', 'date' => sv_today(), 'industry' => [], 'service' => [], 'g1' => '#5B5BF0', 'g2' => '#22D3EE', 'excerpt' => '', 'metrics' => [], 'content' => ''];
    foreach (sv_cases() as $x) if ($x['id'] === $id) $c = array_merge($c, $x);
    $metrics = $c['metrics'] ?: [['value' => '', 'label' => '']];
    ?>
    <h1><?= $id ? 'Редактирование кейса' : 'Новый кейс' ?></h1>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="case_save">
      <input type="hidden" name="id" value="<?= sv_e($id) ?>">
      <?= sv_csrf_field() ?>
      <label class="frm__w">Название<input name="title" value="<?= sv_e($c['title']) ?>" required></label>
      <div class="frm__row">
        <label>Slug<input name="slug" value="<?= sv_e($c['slug']) ?>" required></label>
        <label>Дата<input type="date" name="date" value="<?= sv_e($c['date']) ?>"></label>
        <label>Цвет 1<input name="g1" value="<?= sv_e($c['g1']) ?>"></label>
        <label>Цвет 2<input name="g2" value="<?= sv_e($c['g2']) ?>"></label>
      </div>
      <label>Отрасли
        <?php foreach (['med' => 'Медицина', 'build' => 'Строительство', 'b2b' => 'B2B', 'ecom' => 'E-commerce', 'law' => 'Юристы', 'other' => 'Другое'] as $k => $l): ?>
          <label class="chk"><input type="checkbox" name="industry[]" value="<?= $k ?>" <?= in_array($k, $c['industry'] ?? []) ? 'checked' : '' ?>> <?= $l ?></label>
        <?php endforeach; ?>
      </label>
      <label>Услуги
        <?php foreach (['seo' => 'SEO/GEO', 'dev' => 'Разработка', 'ads' => 'Реклама', 'serm' => 'Репутация'] as $k => $l): ?>
          <label class="chk"><input type="checkbox" name="service[]" value="<?= $k ?>" <?= in_array($k, $c['service'] ?? []) ? 'checked' : '' ?>> <?= $l ?></label>
        <?php endforeach; ?>
      </label>
      <h2>Метрики (до 4)</h2>
      <?php for ($i = 0; $i < 4; $i++): $m = $metrics[$i] ?? ['value' => '', 'label' => '']; ?>
        <div class="frm__row">
          <label>Значение<input name="m_value[]" value="<?= sv_e($m['value']) ?>" placeholder="×2,4"></label>
          <label class="frm__w">Подпись<input name="m_label[]" value="<?= sv_e($m['label']) ?>" placeholder="рост звонков"></label>
        </div>
      <?php endfor; ?>
      <label>Анонс<textarea name="excerpt" rows="2"><?= sv_e($c['excerpt']) ?></textarea></label>
      <label>Текст кейса (визуальный редактор)
        <textarea name="content" class="rte" rows="14"><?= sv_e($c['content']) ?></textarea>
      </label>
      <div class="frm__btns">
        <button class="btn btn--primary" type="submit">Сохранить</button>
        <a class="btn" href="/admin/?s=cases">К списку</a>
      </div>
    </form>
<?php
}

/* ---------- Заявки ---------- */
function sv_adm_leads(): void {
    ?>
    <h1>Заявки с форм сайта</h1>
    <p class="hint">Все заявки дублируются письмом на <?= sv_e(sv_settings()['lead_email'] ?? 'sonicsquad@mail.ru') ?>.</p>
    <?php sv_adm_leads_table(sv_json_read('leads', []), true); ?>
<?php
}

function sv_adm_leads_table(array $leads, bool $full): void {
    ?>
    <table class="tbl">
      <tr><?= adm_th('Дата') ?><?= adm_th('Контакт') ?><?= adm_th('Форма / страница') ?><?= adm_th('Сообщение') ?><?= adm_th('Статус') ?><?= adm_th('Действия') ?></tr>
      <?php if (!$leads): ?><tr><td colspan="6" class="muted">Заявок пока нет.</td></tr><?php endif; ?>
      <?php foreach ($leads as $l): $isNew = ($l['status'] ?? 'new') === 'new'; ?>
        <tr class="<?= $isNew ? 'is-new' : '' ?>">
          <td><?= sv_e($l['date'] ?? '') ?></td>
          <td><b><?= sv_e($l['name'] ?? '') ?></b><br><?= sv_e($l['phone'] ?? '') ?><?= !empty($l['email']) ? '<br>' . sv_e($l['email']) : '' ?></td>
          <td><?= sv_e($l['form'] ?? '') ?><br><small>/<?= sv_e($l['page'] ?? '') ?></small></td>
          <td><?= sv_e(mb_substr((string)($l['message'] ?? ''), 0, 120)) ?>
            <?php if (!empty($l['quiz'])): ?><br><small>Квиз: <?= sv_e(implode('; ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($l['quiz']), $l['quiz']))) ?></small><?php endif; ?>
          </td>
          <td><?= $isNew ? '<span class="badge badge--new">Новая</span>' : '<span class="badge">Обработана</span>' ?></td>
          <td class="td-act">
            <form method="post"><input type="hidden" name="action" value="lead_status"><input type="hidden" name="id" value="<?= sv_e($l['id']) ?>"><?= sv_csrf_field() ?><button class="btn" type="submit" title="Переключить статус"><?= $isNew ? '✓ Обработана' : '↺ В новые' ?></button></form>
            <?php if ($full): ?><form method="post" onsubmit="return confirm('Удалить заявку?')"><input type="hidden" name="action" value="lead_delete"><input type="hidden" name="id" value="<?= sv_e($l['id']) ?>"><?= sv_csrf_field() ?><button class="btn btn--danger" type="submit">Удалить</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
<?php
}

/* ---------- Настройки ---------- */
function sv_adm_settings(): void {
    $S = sv_settings();
    $c = $S['contacts'] ?? [];
    ?>
    <h1>Шапка, футер и сквозные блоки</h1>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="settings_save">
      <?= sv_csrf_field() ?>
      <h2>Шапка сайта</h2>
      <label class="chk"><input type="checkbox" name="topbar_enabled" <?= !empty($S['topbar']['enabled']) ? 'checked' : '' ?>> Показывать верхнюю промо-полосу</label>
      <label>Текст промо-полосы<input name="topbar_text" value="<?= sv_e($S['topbar']['text'] ?? '') ?>"></label>
      <div class="frm__row">
        <label>Текст кнопки CTA в шапке<input name="hdr_cta" value="<?= sv_e($S['header']['cta_text'] ?? '') ?>"></label>
        <label class="frm__w">Название сайта<input name="site_name" value="<?= sv_e($S['site']['name'] ?? '') ?>"></label>
        <label class="frm__w">Домен<input name="site_domain" value="<?= sv_e($S['site']['domain'] ?? '') ?>"></label>
      </div>
      <h2>Контакты и соцсети</h2>
      <div class="frm__row">
        <label>Телефон (для отображения)<input name="phone_display" value="<?= sv_e($c['phone_display'] ?? '') ?>"></label>
        <label>Телефон (для набора)<input name="phone_raw" value="<?= sv_e($c['phone_raw'] ?? '') ?>"></label>
        <label>E-mail<input name="c_email" value="<?= sv_e($c['email'] ?? '') ?>"></label>
      </div>
      <div class="frm__row">
        <label>Telegram<input name="c_tg" value="<?= sv_e($c['telegram'] ?? '') ?>"></label>
        <label>ВКонтакте<input name="c_vk" value="<?= sv_e($c['vk'] ?? '') ?>"></label>
        <label>MAX<input name="c_max" value="<?= sv_e($c['max'] ?? '') ?>"></label>
      </div>
      <div class="frm__row">
        <label class="frm__w">Адрес<input name="c_addr" value="<?= sv_e($c['address'] ?? '') ?>"></label>
        <label>Режим работы<input name="c_sched" value="<?= sv_e($c['schedule'] ?? '') ?>"></label>
        <label>Коротко (в шапке)<input name="c_sched_s" value="<?= sv_e($c['schedule_short'] ?? '') ?>"></label>
      </div>
      <label>Реквизиты<textarea name="c_req" rows="3"><?= sv_e($c['requisites'] ?? '') ?></textarea></label>
      <div class="frm__row">
        <label class="frm__w">Почта для заявок<input name="lead_email" value="<?= sv_e($S['lead_email'] ?? '') ?>"></label>
        <label class="frm__w">OG-изображение по умолчанию
          <span class="frm__with-btn"><input name="og_image" id="f_og_def" value="<?= sv_e($S['og_image'] ?? '') ?>"><button type="button" class="btn" data-upload="#f_og_def">Загрузить</button></span>
        </label>
      </div>
      <h2>Футер</h2>
      <label>Текст «о компании» в футере<textarea name="ftr_about" rows="3"><?= sv_e($S['footer']['about'] ?? '') ?></textarea></label>
      <div class="frm__row">
        <label class="frm__w">Копирайт<input name="ftr_copy" value="<?= sv_e($S['footer']['copyright'] ?? '') ?>"></label>
        <label class="frm__w">Дисклеймер<input name="ftr_disc" value="<?= sv_e($S['footer']['disclaimer'] ?? '') ?>"></label>
      </div>
      <label>Колонки ссылок футера (JSON)
        <textarea name="footer_cols_json" rows="8"><?= sv_e(json_encode($S['footer_cols'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
      </label>
      <h2>Сквозной CTA-блок (перед футером)</h2>
      <label class="chk"><input type="checkbox" name="cta_enabled" <?= !empty($S['cta']['enabled']) ? 'checked' : '' ?>> Показывать CTA-блок</label>
      <div class="frm__row">
        <label class="frm__w">Заголовок<input name="cta_title" value="<?= sv_e($S['cta']['title'] ?? '') ?>"></label>
        <label>Текст кнопки<input name="cta_button" value="<?= sv_e($S['cta']['button'] ?? '') ?>"></label>
      </div>
      <label>Текст<textarea name="cta_text" rows="2"><?= sv_e($S['cta']['text'] ?? '') ?></textarea></label>
      <h2>Произвольный сквозной блок (HTML на всех страницах)</h2>
      <label class="chk"><input type="checkbox" name="gb_enabled" <?= !empty($S['global_block']['enabled']) ? 'checked' : '' ?>> Показывать блок</label>
      <label>HTML-код блока<textarea name="gb_html" rows="5"><?= sv_e($S['global_block']['html'] ?? '') ?></textarea></label>
      <div class="frm__btns"><button class="btn btn--primary" type="submit">Сохранить настройки</button></div>
    </form>
<?php
}

function sv_adm_menu(): void {
    $S = sv_settings();
    ?>
    <h1>Меню сайта</h1>
    <p class="hint">Структура: [{"label":"Пункт","url":"/...","mega":true/false,"children":[{"title":"Группа","url":"/...","items":[{"label":"...","url":"/..."}]}]}]. Вложенность — один уровень групп.</p>
    <form method="post" class="frm">
      <input type="hidden" name="action" value="menu_save">
      <?= sv_csrf_field() ?>
      <textarea name="menu_json" rows="24" class="mono"><?= sv_e(json_encode($S['menu'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></textarea>
      <div class="frm__btns"><button class="btn btn--primary" type="submit">Сохранить меню</button></div>
    </form>
<?php
}

function sv_adm_security(): void {
    ?>
    <h1>Безопасность</h1>
    <div class="card-sec">
      <h2>Смена пароля администратора</h2>
      <form method="post" class="frm">
        <input type="hidden" name="action" value="password_save">
        <?= sv_csrf_field() ?>
        <div class="frm__row">
          <label>Текущий пароль<input type="password" name="cur" required autocomplete="current-password"></label>
          <label>Новый пароль (мин. 8 символов)<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
          <label>Повторите новый<input type="password" name="new2" required autocomplete="new-password"></label>
        </div>
        <button class="btn btn--primary" type="submit">Сменить пароль</button>
      </form>
    </div>
    <div class="card-sec">
      <h2>Защита сайта</h2>
      <ul>
        <li>Пароль хранится в виде необратимого хеша (password_hash) в <code>data/config.php</code>.</li>
        <li>После 5 неудачных попыток вход блокируется на 15 минут по IP.</li>
        <li>Все формы защищены CSRF-токеном и скрытым полем-ловушкой от ботов.</li>
        <li>Загрузка файлов — только изображения, с проверкой сигнатуры; PHP в /upload отключён.</li>
        <li>Служебные папки (data, engine, tools) закрыты от внешнего доступа (.htaccess).</li>
        <li>Рекомендуем после переноса на REG.RU: включить HTTPS и раскомментировать HSTS в .htaccess.</li>
      </ul>
    </div>
<?php
}
