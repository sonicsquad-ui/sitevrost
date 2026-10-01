<?php
/**
 * Сайт в Рост — рендеринг макета: шапка, подвал, мета-теги, микроразметка.
 * Шапка/футер/сквозные блоки читаются из data/settings.json и редактируются в админке.
 */

function sv_site_url(string $path = ''): string {
    $s = sv_settings();
    return rtrim($s['site']['domain'] ?? 'https://sitevrost.ru', '/') . sv_url($path);
}

function sv_head(array $page, array $extra = []): void {
    $S = sv_settings();
    $slug = $page['slug'] ?? '';
    $isPost = ($extra['type'] ?? '') === 'post';
    $isCase = ($extra['type'] ?? '') === 'case';
    $title = $page['title'] ?? ($S['site']['name'] ?? 'Сайт в Рост');
    $desc = $page['description'] ?? '';
    $keywords = $page['keywords'] ?? '';
    $image = $page['og_image'] ?? '';
    if (!$image) $image = $S['og_image'] ?? '/assets/img/og-cover.jpg';
    if ($image && $image[0] === '/') $image = sv_site_url($image);
    $canonical = sv_site_url($slug ? sv_url($slug) : '/');
    $type = $isPost ? 'article' : 'website';
    $csrf = sv_csrf_token();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sv_e($title) ?></title>
<meta name="description" content="<?= sv_e($desc) ?>">
<?php if ($keywords): ?><meta name="keywords" content="<?= sv_e($keywords) ?>"><?php endif; ?>
<link rel="canonical" href="<?= sv_e($canonical) ?>">
<meta name="robots" content="index, follow">
<meta name="csrf-token" content="<?= sv_e($csrf) ?>">
<!-- Open Graph -->
<meta property="og:type" content="<?= $type ?>">
<meta property="og:site_name" content="<?= sv_e($S['site']['name'] ?? 'Сайт в Рост') ?>">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="<?= sv_e($title) ?>">
<meta property="og:description" content="<?= sv_e($desc) ?>">
<meta property="og:url" content="<?= sv_e($canonical) ?>">
<meta property="og:image" content="<?= sv_e($image) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= sv_e($title) ?>">
<meta name="twitter:description" content="<?= sv_e($desc) ?>">
<meta name="twitter:image" content="<?= sv_e($image) ?>">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Unbounded:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<?php if (($extra['jsonld'] ?? '') !== '') echo $extra['jsonld']; ?>
<?php if (!empty($S['head_code'])): ?>
<!-- Код из админки: мета-теги поисковых систем, счётчики -->
<?= $S['head_code'] . "\n" ?>
<?php endif; ?>
</head>
<body>
<?php
}

/* ---------- Иконки (инлайн спрайт) ---------- */
function sv_icon(string $name, int $size = 20): string {
    $icons = [
        'phone' => '<path d="M4 5c0 8.284 6.716 15 15 15l1.5-3.5-4-2.5-2 1.5c-2.5-1.2-5.3-4-6.5-6.5l1.5-2-2.5-4L4 5z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'arrow-up' => '<path d="M12 20V4m0 0-6 6m6-6 6 6"/>',
        'arrow-right' => '<path d="M4 12h16m0 0-6-6m6 6-6 6"/>',
        'check' => '<path d="m4 12 5 5L20 7"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'burger' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'pin' => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'tg' => '<path d="M21.5 4.6 2.9 11.8c-.9.35-.85 1.65.08 1.93l4.6 1.4 1.75 5.3c.28.85 1.37 1.03 1.9.3l2.5-3.4 4.7 3.5c.7.5 1.7.1 1.87-.75l2.7-13.9c.2-1-.75-1.85-1.7-1.48Z"/>',
        'vk' => '<path d="M3 7c.2 5.5 3.3 10 9.5 10h1v-3.6c2.1.2 3.6 1.7 4.3 3.6H21c-.8-2.6-2.5-4.3-4.5-5.1 2-1.1 3.9-3.2 4.3-4.9h-2.9c-.7 1.8-2.4 3.6-4.4 3.8V7h-2.8v6.6C8.5 13.1 6 10.4 5.8 7H3z"/>',
        'max' => '<path d="M12 3a9 9 0 1 0 9 9h-4.5A4.5 4.5 0 1 1 12 7.5V3z"/>',
        'rocket' => '<path d="M12 15c4-3 6-7 6-11-4 0-8 2-11 6l-3 1 4 4 1-3c-1.5 3-1.5 5-1.5 5s2 0 5-1.5l-3 4 4-1 1-3.5"/><circle cx="14" cy="10" r="1.5"/>',
        'chart' => '<path d="M4 20V10m5.5 10V4M15 20v-8m5.5 8V8"/>',
        'code' => '<path d="m8 6-6 6 6 6m8-12 6 6-6 6"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.5 3 7.5 7.5 9 4.5-1.5 7.5-4.5 7.5-9V6L12 3z"/><path d="m8.5 12 2.5 2.5 4.5-4.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 3-5.5 6.5-5.5s6 2 6.5 5.5"/><circle cx="17.5" cy="9" r="2.5"/><path d="M16 15c3 0 5 1.7 5.5 5"/>',
        'doc' => '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
        'star' => '<path d="m12 3 2.7 5.8 6.3.7-4.7 4.3 1.3 6.2L12 16.9 6.4 20l1.3-6.2L3 9.5l6.3-.7L12 3z"/>',
        'map' => '<path d="m9 4-5 2v14l5-2 6 2 5-2V4l-5 2-6-2zm0 0v14m6-12v14"/>',
    ];
    $body = $icons[$name] ?? '';
    $fill = in_array($name, ['tg', 'vk', 'max', 'star']) ? 'fill="currentColor" stroke="none"' : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
    return "<svg class=\"ic ic-{$name}\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 24 24\" aria-hidden=\"true\" {$fill}>{$body}</svg>";
}

function sv_social_links(array $S, string $cls = ''): string {
    $c = $S['contacts'] ?? [];
    $out = '';
    if (!empty($c['telegram'])) $out .= '<a class="soc ' . $cls . '" href="' . sv_e($c['telegram']) . '" target="_blank" rel="noopener" aria-label="Telegram">' . sv_icon('tg', 18) . '</a>';
    if (!empty($c['vk'])) $out .= '<a class="soc ' . $cls . '" href="' . sv_e($c['vk']) . '" target="_blank" rel="noopener" aria-label="ВКонтакте">' . sv_icon('vk', 18) . '</a>';
    if (!empty($c['max'])) $out .= '<a class="soc ' . $cls . '" href="' . sv_e($c['max']) . '" target="_blank" rel="noopener" aria-label="MAX">' . sv_icon('max', 18) . '</a>';
    return $out;
}

/* ---------- Шапка ---------- */
function sv_header(string $active = ''): void {
    $S = sv_settings();
    $c = $S['contacts'] ?? [];
    $menu = $S['menu'] ?? [];
    ?>
<div class="topbar">
  <div class="container topbar__in">
    <?php if (!empty($S['topbar']['enabled'])): ?><div class="topbar__promo"><?= sv_e($S['topbar']['text'] ?? '') ?></div><?php endif; ?>
    <div class="topbar__right">
      <?php if (!empty($c['schedule'])): ?><span class="topbar__item"><?= sv_icon('clock', 15) ?><?= sv_e($c['schedule']) ?></span><?php endif; ?>
      <?php if (!empty($c['email'])): ?><a class="topbar__item" href="mailto:<?= sv_e($c['email']) ?>"><?= sv_icon('mail', 15) ?><?= sv_e($c['email']) ?></a><?php endif; ?>
      <span class="topbar__soc"><?= sv_social_links($S) ?></span>
    </div>
  </div>
</div>
<header class="hdr" id="hdr">
  <div class="container hdr__in">
    <a class="logo" href="/" aria-label="Сайт в Рост — на главную">
      <img src="/assets/img/logo.svg" alt="Логотип Сайт в Рост" width="170" height="44">
    </a>
    <nav class="nav" id="nav" aria-label="Основное меню">
      <ul class="nav__list">
        <?php foreach ($menu as $mi => $item): ?>
          <?php $hasKids = !empty($item['children']); ?>
          <li class="nav__item<?= $hasKids ? ' nav__item--drop' : '' ?><?= ($active === ($item['url'] ?? '')) ? ' is-active' : '' ?>">
            <?php if ($hasKids): ?>
              <button class="nav__link nav__link--btn" type="button" aria-expanded="false"><?= sv_e($item['label']) ?> <?= sv_icon('chevron', 14) ?></button>
              <div class="nav__drop<?= isset($item['mega']) && $item['mega'] ? ' nav__drop--mega' : '' ?>">
                <?php if (isset($item['mega']) && $item['mega']): ?>
                  <div class="container nav__mega">
                    <?php foreach ($item['children'] as $group): ?>
                      <div class="nav__group">
                        <?php if (!empty($group['title'])): ?><a class="nav__group-title" href="<?= sv_e($group['url'] ?? '#') ?>"><?= sv_e($group['title']) ?></a><?php else: ?><div class="nav__group-title"><?= sv_e($group['label'] ?? '') ?></div><?php endif; ?>
                        <ul>
                          <?php foreach (($group['items'] ?? []) as $sub): ?>
                            <li><a href="<?= sv_e($sub['url']) ?>"><?= sv_e($sub['label']) ?></a></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <ul class="nav__sub">
                    <?php foreach ($item['children'] as $sub): ?>
                      <li><a href="<?= sv_e($sub['url']) ?>"><?= sv_e($sub['label']) ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <a class="nav__link" href="<?= sv_e($item['url'] ?? '/') ?>"><?= sv_e($item['label']) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="hdr__actions">
      <button class="hdr__search" id="searchToggle" type="button" aria-label="Поиск по сайту"><?= sv_icon('search', 20) ?></button>
      <?php if (!empty($c['phone_raw'])): ?>
        <a class="hdr__phone" href="tel:<?= sv_e(preg_replace('/[^\d+]/', '', $c['phone_raw'])) ?>">
          <span class="hdr__phone-num"><?= sv_e($c['phone_display'] ?? $c['phone_raw']) ?></span>
          <?php if (!empty($c['schedule_short'])): ?><span class="hdr__phone-note"><?= sv_e($c['schedule_short']) ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <button class="btn btn--primary hdr__cta" type="button" data-open-modal="lead-modal"><?= sv_e($S['header']['cta_text'] ?? 'Обсудить проект') ?></button>
      <button class="burger" id="burger" type="button" aria-label="Открыть меню" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>
<?php
}

/* ---------- Хлебные крошки + микроразметка ---------- */
function sv_breadcrumbs(array $page, string $current): void {
    $crumbs = sv_crumbs($page);
    $crumbs[] = ['name' => $current, 'url' => ''];
    $items = [];
    foreach ($crumbs as $i => $cr) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $cr['name'], 'item' => sv_site_url($cr['url'] ?: ($page['slug'] ?? ''))];
    }
    $jsonld = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    echo '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    ?>
<nav class="crumbs container" aria-label="Хлебные крошки">
  <ol>
    <?php foreach ($crumbs as $i => $cr): ?>
      <li>
        <?php if ($cr['url'] === '' || $i === count($crumbs) - 1): ?>
          <span class="crumbs__current" aria-current="page"><?= sv_e($cr['name']) ?></span>
        <?php else: ?>
          <a href="<?= sv_e($cr['url']) ?>"><?= sv_e($cr['name']) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
<?php
}

/* ---------- Футер ---------- */
function sv_footer(): void {
    $S = sv_settings();
    $c = $S['contacts'] ?? [];
    $f = $S['footer'] ?? [];
    $cols = $S['footer_cols'] ?? [];
    ?>
<footer class="ftr">
  <div class="container">
    <div class="ftr__grid">
      <div class="ftr__brand">
        <a class="logo logo--light" href="/"><img src="/assets/img/logo-light.svg" alt="Сайт в Рост" width="170" height="44"></a>
        <p><?= sv_e($f['about'] ?? '') ?></p>
        <div class="ftr__soc"><?= sv_social_links($S) ?></div>
      </div>
      <?php foreach ($cols as $col): ?>
        <div class="ftr__col">
          <div class="ftr__title"><?= sv_e($col['title'] ?? '') ?></div>
          <ul>
            <?php foreach (($col['links'] ?? []) as $l): ?>
              <li><a href="<?= sv_e($l['url']) ?>"><?= sv_e($l['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <div class="ftr__col ftr__col--contacts">
        <div class="ftr__title">Контакты</div>
        <?php if (!empty($c['phone_display'])): ?><a class="ftr__phone" href="tel:<?= sv_e(preg_replace('/[^\d+]/', '', $c['phone_raw'] ?? '')) ?>"><?= sv_e($c['phone_display']) ?></a><?php endif; ?>
        <?php if (!empty($c['email'])): ?><a class="ftr__mail" href="mailto:<?= sv_e($c['email']) ?>"><?= sv_e($c['email']) ?></a><?php endif; ?>
        <?php if (!empty($c['address'])): ?><div class="ftr__addr"><?= sv_icon('pin', 16) ?> <?= sv_e($c['address']) ?></div><?php endif; ?>
        <?php if (!empty($c['schedule'])): ?><div class="ftr__addr"><?= sv_icon('clock', 16) ?> <?= sv_e($c['schedule']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="ftr__bottom">
      <div class="ftr__copy"><?= sv_e($f['copyright'] ?? '') ?></div>
      <nav class="ftr__legal" aria-label="Юридические документы">
        <a href="/politika-konfidencialnosti">Политика конфиденциальности</a>
        <a href="/polzovatelskoe-soglashenie">Пользовательское соглашение</a>
        <a href="/pravila-polzovaniya">Правила пользования сайтом</a>
        <a href="/oferta">Оферта</a>
        <a href="/sitemap">Карта сайта</a>
      </nav>
      <div class="ftr__disc"><?= sv_e($f['disclaimer'] ?? '') ?></div>
    </div>
  </div>
</footer>
<?php
}

/* ---------- Сквозные виджеты: CTA, формы, cookie, наверх, поиск, модалка ---------- */
function sv_lead_form(string $formName, string $title, string $btnText, string $pageSlug = '', bool $compact = false): string {
    $csrf = sv_csrf_field();
    $cls = $compact ? 'lead-form lead-form--compact' : 'lead-form';
    ob_start(); ?>
<form class="<?= $cls ?>" data-form="<?= sv_e($formName) ?>" data-page="<?= sv_e($pageSlug) ?>" novalidate>
  <?= $csrf ?>
  <input type="text" name="hp_field" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
  <?php if ($title): ?><div class="lead-form__title"><?= sv_e($title) ?></div><?php endif; ?>
  <div class="lead-form__grid">
    <label class="field"><span>Ваше имя*</span><input type="text" name="name" required maxlength="100" placeholder="Иван Петров" autocomplete="name"></label>
    <label class="field"><span>Телефон*</span><input type="tel" name="phone" required maxlength="30" placeholder="+7 ___ ___-__-__" autocomplete="tel"></label>
    <?php if (!$compact): ?><label class="field field--wide"><span>Задача (необязательно)</span><textarea name="message" rows="3" maxlength="2000" placeholder="Например: нужен корпоративный сайт и продвижение в Яндексе"></textarea></label><?php endif; ?>
  </div>
  <div class="lead-form__foot">
    <label class="agree"><input type="checkbox" name="agree" required><span class="agree__box"></span><span>Я соглашаюсь с <a href="/politika-konfidencialnosti" target="_blank">политикой конфиденциальности</a> и даю согласие на обработку персональных данных*</span></label>
    <button class="btn btn--primary btn--lg" type="submit"><?= sv_e($btnText) ?></button>
  </div>
  <div class="lead-form__msg" role="status" aria-live="polite"></div>
</form>
<?php return ob_get_clean();
}

function sv_widgets(): void {
    $S = sv_settings();
    $cta = $S['cta'] ?? [];
    ?>
<!-- Сквозной CTA-блок (редактируется в админке) -->
<?php if (!empty($S['cta']['enabled'])): ?>
<section class="cta-band" id="cta-band">
  <div class="container cta-band__in">
    <div class="cta-band__text">
      <h2><?= sv_e($cta['title'] ?? 'Обсудим ваш проект?') ?></h2>
      <p><?= sv_e($cta['text'] ?? '') ?></p>
    </div>
    <div class="cta-band__form"><?= sv_lead_form('cta_block', '', $cta['button'] ?? 'Получить план роста', '', true) ?></div>
  </div>
</section>
<?php endif; ?>

<!-- Сквозной блок (произвольный HTML из админки) -->
<?php if (!empty($S['global_block']['enabled']) && !empty($S['global_block']['html'])) echo $S['global_block']['html']; ?>

<!-- Поиск -->
<div class="search-pop" id="searchPop" role="dialog" aria-modal="true" aria-label="Поиск по сайту" hidden>
  <div class="search-pop__panel">
    <form class="search-pop__form" id="searchForm">
      <?= sv_csrf_field() ?>
      <input type="search" id="searchInput" name="q" placeholder="Поиск по сайту: услуги, статьи, кейсы…" autocomplete="off" maxlength="100">
      <button type="button" class="search-pop__close" id="searchClose" aria-label="Закрыть поиск"><?= sv_icon('close', 20) ?></button>
    </form>
    <div class="search-pop__results" id="searchResults"><div class="search-pop__hint">Начните вводить — минимум 2 символа. Ищем по страницам, блогу и кейсам.</div></div>
  </div>
</div>

<!-- Модалка заявки -->
<div class="modal" id="lead-modal" role="dialog" aria-modal="true" aria-labelledby="lead-modal-title" hidden>
  <div class="modal__back" data-close-modal></div>
  <div class="modal__panel">
    <button class="modal__close" type="button" data-close-modal aria-label="Закрыть"><?= sv_icon('close', 20) ?></button>
    <h3 id="lead-modal-title">Обсудить проект</h3>
    <p class="modal__sub">Оставьте контакты — перезвоним в течение рабочего дня и подготовим бесплатный план роста.</p>
    <?= sv_lead_form('modal', '', 'Отправить заявку', '', true) ?>
  </div>
</div>

<!-- Cookie -->
<div class="cookie" id="cookieBar" hidden>
  <div class="container cookie__in">
    <p>Мы используем файлы cookie для корректной работы сайта и улучшения сервиса. Продолжая пользоваться сайтом, вы соглашаетесь с <a href="/politika-konfidencialnosti">политикой конфиденциальности</a> и <a href="/pravila-polzovaniya">правилами пользования сайтом</a>.</p>
    <button class="btn btn--primary" id="cookieAccept" type="button">Принять</button>
  </div>
</div>

<!-- Наверх -->
<button class="totop" id="toTop" type="button" aria-label="Наверх"><?= sv_icon('arrow-up', 20) ?></button>
<?php if (!empty($S['body_code'])): ?>
<!-- Код из админки перед </body>: счётчики и виджеты -->
<?= $S['body_code'] . "\n" ?>
<?php endif; ?>
<script src="/assets/js/main.js" defer></script>
</body>
</html>
<?php
}

/* ---------- JSON-LD блоки ---------- */
function sv_jsonld(array $data): string {
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

function sv_org_jsonld(): string {
    $S = sv_settings();
    $c = $S['contacts'] ?? [];
    return sv_jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Диджитал-агентство «Сайт в Рост»',
        'url' => sv_site_url('/'),
        'logo' => sv_site_url('/assets/img/logo.svg'),
        'telephone' => $c['phone_raw'] ?? '',
        'email' => $c['email'] ?? '',
        'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'RU', 'streetAddress' => $c['address'] ?? ''],
        'sameAs' => array_values(array_filter([$c['telegram'] ?? '', $c['vk'] ?? '', $c['max'] ?? ''])),
    ]);
}

function sv_website_jsonld(): string {
    return sv_jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Сайт в Рост',
        'url' => sv_site_url('/'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => sv_site_url('/search?q={search_term_string}'), 'query-input' => 'required name=search_term_string'],
    ]);
}
