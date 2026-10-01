<?php
/**
 * Сайт в Рост — маршрутизация и шаблоны вывода страниц.
 */

function sv_route(): void {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
    $path = '/' . trim($path, '/');
    $query = [];
    parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);

    if ($path === '/sitemap.xml') { sv_render_sitemap_xml(); return; }
    if ($path === '/search') { sv_render_search_page($query['q'] ?? ''); return; }

    // Блог
    if ($path === '/blog' || $path === '/blog/') { sv_render_blog_list($query['cat'] ?? ''); return; }
    if (preg_match('#^/blog/([a-z0-9\-_]+)/?$#u', $path, $m)) { sv_render_post($m[1]); return; }

    // Кейсы
    if ($path === '/keysy') { sv_render_cases($query); return; }
    if ($path === '/keysy-seo') { sv_render_cases(['service' => 'seo'], 'Кейсы по SEO и GEO'); return; }
    if ($path === '/keysy-razrabotka') { sv_render_cases(['service' => 'dev'], 'Кейсы по разработке'); return; }
    if (preg_match('#^/keysy/([a-z0-9\-_]+)/?$#u', $path, $m)) { sv_render_case($m[1]); return; }

    // Обычные страницы
    $slug = trim($path, '/');
    $page = sv_page_by_slug($slug);
    if ($page) { sv_render_page($page); return; }

    sv_render_404();
}

/* ================================================================== */
/* Обычная страница                                                    */
/* ================================================================== */
function sv_render_page(array $page): void {
    $S = sv_settings();
    // подстановка CSRF-токена в формы, вставленные в контент ({{CSRF}})
    if (!empty($page['content'])) {
        $page['content'] = str_replace('{{CSRF}}', '<input type="hidden" name="csrf" value="' . sv_e(sv_csrf_token()) . '">', $page['content']);
    }
    $slug = $page['slug'] ?? '';
    $tpl = $page['template'] ?? 'page';
    $isHome = ($slug === '');

    // Дополнительные JSON-LD
    $jsonld = sv_org_jsonld() . sv_website_jsonld();
    if ($isHome) {
        $jsonld .= sv_jsonld([
            '@context' => 'https://schema.org', '@type' => 'ProfessionalService',
            'name' => 'Диджитал-агентство «Сайт в Рост»', 'url' => sv_site_url('/'),
            'description' => $page['description'] ?? '',
            'telephone' => $S['contacts']['phone_raw'] ?? '', 'email' => $S['contacts']['email'] ?? '',
            'priceRange' => '₽₽', 'areaServed' => 'Россия',
        ]);
    }
    if (($page['schema'] ?? '') === 'Service') {
        $jsonld .= sv_jsonld([
            '@context' => 'https://schema.org', '@type' => 'Service',
            'name' => $page['title'] ?? '', 'description' => $page['description'] ?? '',
            'url' => sv_site_url(sv_url($slug)),
            'provider' => ['@type' => 'Organization', 'name' => 'Сайт в Рост', 'url' => sv_site_url('/')],
            'areaServed' => 'Россия',
        ]);
    }
    if (!empty($page['faq_items'])) {
        $jsonld .= sv_jsonld([
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['a'])],
            ], $page['faq_items']),
        ]);
    }
    if ($slug === 'kontakty') {
        $c = $S['contacts'] ?? [];
        $jsonld .= sv_jsonld([
            '@context' => 'https://schema.org', '@type' => 'LocalBusiness',
            'name' => 'Диджитал-агентство «Сайт в Рост»',
            'image' => sv_site_url('/assets/img/og-cover.jpg'),
            'url' => sv_site_url('/kontakty'), 'telephone' => $c['phone_raw'] ?? '',
            'email' => $c['email'] ?? '', 'priceRange' => '₽₽',
            'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'RU', 'streetAddress' => $c['address'] ?? ''],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 47.2357, 'longitude' => 39.7015],
            'openingHours' => 'Mo-Fr 09:00-19:00',
            'sameAs' => array_values(array_filter([$c['telegram'] ?? '', $c['vk'] ?? '', $c['max'] ?? ''])),
        ]);
    }

    sv_head($page, ['jsonld' => $jsonld]);
    sv_header($slug);

    if (!$isHome) {
        sv_breadcrumbs($page, $page['crumb'] ?? $page['h1'] ?? $page['title'] ?? '');
    }

    if ($tpl === 'home') {
        echo $page['content'] ?? '';
        sv_home_dynamic($page);
    } elseif ($tpl === 'sitemap-html') {
        echo '<main class="page-main container"><h1 class="page-h1">' . sv_e($page['h1'] ?? $page['title'] ?? '') . '</h1>';
        echo $page['content'] ?? '';
        sv_render_sitemap_html();
        echo '</main>';
    } elseif ($tpl === 'faq') {
        echo '<main class="page-main container"><h1 class="page-h1">' . sv_e($page['h1'] ?? $page['title'] ?? '') . '</h1>';
        echo $page['content'] ?? '';
        if (!empty($page['faq_items'])) {
            echo '<div class="faq">';
            foreach ($page['faq_items'] as $i => $f) {
                echo '<details class="faq__item"><summary>' . sv_e($f['q']) . '</summary><div class="faq__a">' . $f['a'] . '</div></details>';
            }
            echo '</div>';
        }
        echo '</main>';
    } elseif ($tpl === 'contacts') {
        echo '<main class="page-main container"><h1 class="page-h1">' . sv_e($page['h1'] ?? $page['title'] ?? '') . '</h1>';
        echo $page['content'] ?? '';
        sv_render_contacts_block();
        echo '</main>';
    } else {
        $wide = ($tpl === 'wide') ? ' page-main--wide' : '';
        echo '<main class="page-main container' . $wide . '"><h1 class="page-h1">' . sv_e($page['h1'] ?? $page['title'] ?? '') . '</h1>';
        echo '<div class="content">' . ($page['content'] ?? '') . '</div>';
        echo '<div class="case-cta"><h3>Обсудим вашу задачу?</h3><p style="color:var(--text-soft);margin-bottom:16px">Оставьте контакты — вернёмся с планом и сметой в течение рабочего дня.</p>' . sv_lead_form('page_' . $slug, '', 'Отправить заявку', $slug, true) . '</div>';
        echo '</main>';
    }

    sv_footer();
    sv_widgets();
}

/* ---------- Динамические секции главной: кейсы, блог, FAQ ---------- */
function sv_home_dynamic(array $page): void {
    $cases = sv_cases();
    usort($cases, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $cases = array_slice($cases, 0, 3);
    $posts = sv_posts();
    usort($posts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $posts = array_slice($posts, 0, 3);
    ?>
<section class="section section--gray" id="cases">
  <div class="container">
    <div class="section__head" style="display:flex;align-items:flex-end;justify-content:space-between;max-width:none;gap:20px;flex-wrap:wrap">
      <div><div class="eyebrow">Портфолио</div><h2 class="h2">Кейсы с цифрами</h2></div>
      <a class="btn btn--ghost" href="/keysy">Все кейсы →</a>
    </div>
    <div class="grid grid--3">
      <?php foreach ($cases as $c): ?>
        <article class="card card--case">
          <a class="card__img card__img--case" href="/keysy/<?= sv_e($c['slug']) ?>" style="--g1:<?= sv_e($c['g1'] ?? '#5B5BF0') ?>;--g2:<?= sv_e($c['g2'] ?? '#22D3EE') ?>">
            <span class="card__case-metric"><?= sv_e($c['metrics'][0]['value'] ?? '') ?></span>
            <span class="card__case-metric-label"><?= sv_e($c['metrics'][0]['label'] ?? '') ?></span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="/keysy/<?= sv_e($c['slug']) ?>"><?= sv_e($c['title']) ?></a></h3>
            <p class="card__text"><?= sv_e($c['excerpt'] ?? '') ?></p>
            <a class="card__more" href="/keysy/<?= sv_e($c['slug']) ?>">Смотреть кейс <?= sv_icon('arrow-right', 16) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="blog">
  <div class="container">
    <div class="section__head" style="display:flex;align-items:flex-end;justify-content:space-between;max-width:none;gap:20px;flex-wrap:wrap">
      <div><div class="eyebrow">База знаний</div><h2 class="h2">Свежее из блога</h2></div>
      <a class="btn btn--ghost" href="/blog">Все статьи →</a>
    </div>
    <div class="grid grid--3">
      <?php foreach ($posts as $p) sv_blog_card($p); ?>
    </div>
  </div>
</section>

<?php if (!empty($page['faq_items'])): ?>
<section class="section section--gray" id="faq">
  <div class="container">
    <div class="section__head"><div class="eyebrow">FAQ</div><h2 class="h2">Частые вопросы</h2><p class="section__sub">Больше ответов — на странице <a href="/faq">«Частые вопросы»</a></p></div>
    <div class="faq">
      <?php foreach ($page['faq_items'] as $f): ?>
        <details class="faq__item"><summary><?= sv_e($f['q']) ?></summary><div class="faq__a"><?= $f['a'] ?></div></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif;
}

/* ================================================================== */
/* Блог: список                                                        */
/* ================================================================== */
function sv_render_blog_list(string $cat = ''): void {
    $page = sv_page_by_slug('blog') ?? ['title' => 'Блог агентства «Сайт в Рост»', 'description' => 'Статьи, гайды и исследования о SEO, GEO, SXO, разработке сайтов и digital-маркетинге.', 'slug' => 'blog', 'parent' => '', 'h1' => 'Блог агентства'];
    $cats = sv_blog_cats();
    $posts = sv_posts();
    usort($posts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    if ($cat) $posts = array_values(array_filter($posts, fn($p) => ($p['category'] ?? '') === $cat));

    $jsonld = sv_org_jsonld() . sv_jsonld([
        '@context' => 'https://schema.org', '@type' => 'Blog',
        'name' => 'Блог «Сайт в Рост»', 'url' => sv_site_url('/blog'),
        'blogPost' => array_slice(array_map(fn($p) => [
            '@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => sv_site_url('/blog/' . $p['slug']),
            'datePublished' => $p['date'] ?? '',
        ], $posts), 0, 20),
    ]);
    $page['title'] = $cat ? (($cats[$cat]['name'] ?? $cat) . ' — Блог «Сайт в Рост»') : ($page['title'] ?? '');
    sv_head($page, ['jsonld' => $jsonld]);
    sv_header('blog');
    sv_breadcrumbs($page, 'Блог');
    ?>
<main class="page-main container">
  <h1 class="page-h1"><?= sv_e($page['h1'] ?? 'Блог') ?></h1>
  <div class="content"><?= $page['content'] ?? '' ?></div>
  <div class="chips">
    <a class="chip<?= $cat === '' ? ' is-active' : '' ?>" href="/blog">Все статьи</a>
    <?php foreach ($cats as $slugC => $cInfo): ?>
      <a class="chip<?= $cat === $slugC ? ' is-active' : '' ?>" href="/blog?cat=<?= sv_e($slugC) ?>"><?= sv_e($cInfo['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="grid grid--3 blog-grid">
    <?php foreach ($posts as $p): sv_blog_card($p); endforeach; ?>
  </div>
  <?php if (!$posts): ?><p class="empty">Статей в этой категории пока нет.</p><?php endif; ?>
</main>
<?php
    sv_footer();
    sv_widgets();
}

function sv_blog_card(array $p, bool $small = false): void {
    $cats = sv_blog_cats();
    $catName = $cats[$p['category'] ?? '']['name'] ?? '';
    ?>
<article class="card card--post<?= $small ? ' card--post-sm' : '' ?>">
  <a class="card__img" href="/blog/<?= sv_e($p['slug']) ?>">
    <img src="<?= sv_e($p['image'] ?? '/assets/img/og-cover.jpg') ?>" alt="<?= sv_e($p['title']) ?>" loading="lazy">
    <?php if ($catName): ?><span class="card__tag"><?= sv_e($catName) ?></span><?php endif; ?>
  </a>
  <div class="card__body">
    <time class="card__date" datetime="<?= sv_e($p['date'] ?? '') ?>"><?= sv_ru_date($p['date'] ?? '') ?></time>
    <h3 class="card__title"><a href="/blog/<?= sv_e($p['slug']) ?>"><?= sv_e($p['title']) ?></a></h3>
    <p class="card__text"><?= sv_e($p['excerpt'] ?? '') ?></p>
    <a class="card__more" href="/blog/<?= sv_e($p['slug']) ?>">Читать статью <?= sv_icon('arrow-right', 16) ?></a>
  </div>
</article>
<?php
}

/* ================================================================== */
/* Блог: статья                                                        */
/* ================================================================== */
function sv_render_post(string $slug): void {
    $post = sv_post_by_slug($slug);
    if (!$post) { sv_render_404(); return; }
    $cats = sv_blog_cats();
    $authorsAll = sv_authors();
    $authors = [];
    foreach (($post['authors'] ?? []) as $aid) if (isset($authorsAll[$aid])) $authors[] = $authorsAll[$aid];
    if (!$authors) $authors = array_slice(array_values($authorsAll), 0, 1);

    $page = ['slug' => 'blog/' . $slug, 'parent' => 'blog', 'title' => $post['title'], 'description' => $post['description'] ?? sv_strip_words($post['content'] ?? '', 30), 'keywords' => implode(', ', $post['tags'] ?? []), 'og_image' => $post['image'] ?? ''];

    $jsonld = sv_org_jsonld() . sv_jsonld([
        '@context' => 'https://schema.org', '@type' => 'Article',
        'headline' => $post['title'], 'description' => $page['description'],
        'image' => sv_site_url($post['image'] ?? '/assets/img/og-cover.jpg'),
        'datePublished' => $post['date'] ?? '', 'dateModified' => $post['updated'] ?? ($post['date'] ?? ''),
        'url' => sv_site_url('/blog/' . $slug),
        'author' => array_map(fn($a) => ['@type' => 'Person', 'name' => $a['name'], 'jobTitle' => $a['role'] ?? ''], $authors),
        'publisher' => ['@type' => 'Organization', 'name' => 'Сайт в Рост', 'logo' => ['@type' => 'ImageObject', 'url' => sv_site_url('/assets/img/logo.svg')]],
        'mainEntityOfPage' => sv_site_url('/blog/' . $slug),
        'articleSection' => $cats[$post['category'] ?? '']['name'] ?? '',
        'keywords' => implode(', ', $post['tags'] ?? []),
    ]);

    sv_head($page, ['jsonld' => $jsonld, 'type' => 'post']);
    sv_header('blog');
    sv_breadcrumbs($page, $post['title']);

    // Советуем почитать: 3 последних статьи, кроме текущей
    $all = sv_posts();
    usort($all, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $related = array_values(array_filter($all, fn($p) => $p['slug'] !== $slug));
    $related = array_slice($related, 0, 3);
    ?>
<main class="post container">
  <article>
    <header class="post__head">
      <div class="post__meta">
        <a class="chip" href="/blog?cat=<?= sv_e($post['category'] ?? '') ?>"><?= sv_e($cats[$post['category'] ?? '']['name'] ?? 'Блог') ?></a>
        <time datetime="<?= sv_e($post['date'] ?? '') ?>"><?= sv_ru_date($post['date'] ?? '') ?></time>
        <?php if (!empty($post['read_time'])): ?><span>· <?= sv_e($post['read_time']) ?> мин чтения</span><?php endif; ?>
      </div>
      <h1 class="post__title"><?= sv_e($post['title']) ?></h1>
      <?php if (!empty($post['excerpt'])): ?><p class="post__lead"><?= sv_e($post['excerpt']) ?></p><?php endif; ?>
    </header>
    <?php if (!empty($post['image'])): ?><img class="post__cover" src="<?= sv_e($post['image']) ?>" alt="<?= sv_e($post['title']) ?>"><?php endif; ?>
    <div class="content content--post">
      <?= $post['content'] ?? '' ?>
    </div>
    <footer class="post__authors">
      <?php foreach ($authors as $a): ?>
      <div class="author">
        <img class="author__photo" src="<?= sv_e($a['photo'] ?? '') ?>" alt="<?= sv_e($a['name']) ?>" width="72" height="72" loading="lazy">
        <div>
          <div class="author__name"><?= sv_e($a['name']) ?></div>
          <div class="author__role"><?= sv_e($a['role'] ?? '') ?></div>
          <p class="author__bio"><?= sv_e($a['bio'] ?? '') ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </footer>
  </article>
  <section class="related">
    <h2 class="h2">Советуем почитать</h2>
    <div class="grid grid--3">
      <?php foreach ($related as $p) sv_blog_card($p); ?>
    </div>
  </section>
</main>
<?php
    sv_footer();
    sv_widgets();
}

/* ================================================================== */
/* Кейсы: список с фильтрами                                           */
/* ================================================================== */
function sv_render_cases(array $filter = [], string $forceTitle = ''): void {
    $page = sv_page_by_slug('keysy') ?? ['title' => 'Кейсы — Сайт в Рост', 'description' => '', 'slug' => 'keysy', 'h1' => 'Кейсы и портфолио', 'content' => ''];
    $cases = sv_cases();
    usort($cases, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

    $industries = []; $services = [];
    foreach ($cases as $c) {
        foreach (($c['industry'] ?? []) as $i) $industries[$i] = true;
        foreach (($c['service'] ?? []) as $s) $services[$s] = true;
    }
    $indNames = ['med' => 'Медицина', 'build' => 'Строительство', 'b2b' => 'B2B / Промышленность', 'ecom' => 'E-commerce', 'law' => 'Юридические услуги', 'other' => 'Другое'];
    $srvNames = ['seo' => 'SEO / GEO', 'dev' => 'Разработка', 'ads' => 'Реклама', 'serm' => 'Репутация'];

    $filtered = $cases;
    if (!empty($filter['industry'])) $filtered = array_values(array_filter($filtered, fn($c) => in_array($filter['industry'], $c['industry'] ?? [])));
    if (!empty($filter['service'])) $filtered = array_values(array_filter($filtered, fn($c) => in_array($filter['service'], $c['service'] ?? [])));

    if ($forceTitle) { $page['title'] = $forceTitle . ' — Сайт в Рост'; $page['h1'] = $forceTitle; }

    sv_head($page, ['jsonld' => sv_org_jsonld() . sv_jsonld([
        '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $page['h1'] ?? 'Кейсы',
        'itemListElement' => array_map(fn($i, $c) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => sv_site_url('/keysy/' . $c['slug']), 'name' => $c['title']], array_keys($filtered), $filtered),
    ])]);
    sv_header('keysy');
    sv_breadcrumbs($page, $page['h1'] ?? 'Кейсы');
    ?>
<main class="page-main container">
  <h1 class="page-h1"><?= sv_e($page['h1'] ?? 'Кейсы') ?></h1>
  <div class="content"><?= $page['content'] ?? '' ?></div>
  <div class="cases-filters" data-cases-filters>
    <div class="cases-filters__group">
      <span class="cases-filters__label">Отрасль:</span>
      <a class="chip<?= empty($filter['industry']) ? ' is-active' : '' ?>" href="/keysy<?= !empty($filter['service']) ? '?service=' . sv_e($filter['service']) : '' ?>">Все</a>
      <?php foreach (array_keys($industries) as $i): ?>
        <a class="chip<?= ($filter['industry'] ?? '') === $i ? ' is-active' : '' ?>" href="/keysy?industry=<?= sv_e($i) ?><?= !empty($filter['service']) ? '&service=' . sv_e($filter['service']) : '' ?>"><?= sv_e($indNames[$i] ?? $i) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="cases-filters__group">
      <span class="cases-filters__label">Услуга:</span>
      <a class="chip<?= empty($filter['service']) ? ' is-active' : '' ?>" href="/keysy<?= !empty($filter['industry']) ? '?industry=' . sv_e($filter['industry']) : '' ?>">Все</a>
      <?php foreach (array_keys($services) as $s): ?>
        <a class="chip<?= ($filter['service'] ?? '') === $s ? ' is-active' : '' ?>" href="/keysy?service=<?= sv_e($s) ?><?= !empty($filter['industry']) ? '&industry=' . sv_e($filter['industry']) : '' ?>"><?= sv_e($srvNames[$s] ?? $s) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="grid grid--3 cases-grid">
    <?php foreach ($filtered as $c): ?>
      <article class="card card--case">
        <a class="card__img card__img--case" href="/keysy/<?= sv_e($c['slug']) ?>" style="--g1:<?= sv_e($c['g1'] ?? '#5B5BF0') ?>;--g2:<?= sv_e($c['g2'] ?? '#22D3EE') ?>">
          <span class="card__case-metric"><?= sv_e(($c['metrics'][0]['value'] ?? '')) ?></span>
          <span class="card__case-metric-label"><?= sv_e(($c['metrics'][0]['label'] ?? '')) ?></span>
        </a>
        <div class="card__body">
          <div class="card__tags">
            <?php foreach (($c['service'] ?? []) as $s): ?><span class="card__tag card__tag--s"><?= sv_e($srvNames[$s] ?? $s) ?></span><?php endforeach; ?>
            <?php foreach (($c['industry'] ?? []) as $i): ?><span class="card__tag card__tag--i"><?= sv_e($indNames[$i] ?? $i) ?></span><?php endforeach; ?>
          </div>
          <h3 class="card__title"><a href="/keysy/<?= sv_e($c['slug']) ?>"><?= sv_e($c['title']) ?></a></h3>
          <p class="card__text"><?= sv_e($c['excerpt'] ?? '') ?></p>
          <a class="card__more" href="/keysy/<?= sv_e($c['slug']) ?>">Смотреть кейс <?= sv_icon('arrow-right', 16) ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$filtered): ?><p class="empty">По выбранным фильтрам кейсов пока нет — загляните позже.</p><?php endif; ?>
</main>
<?php
    sv_footer();
    sv_widgets();
}

/* ================================================================== */
/* Кейс: отдельная страница                                            */
/* ================================================================== */
function sv_render_case(string $slug): void {
    $case = sv_case_by_slug($slug);
    if (!$case) { sv_render_404(); return; }
    $page = ['slug' => 'keysy/' . $slug, 'parent' => 'keysy', 'title' => $case['title'] . ' — Кейс «Сайт в Рост»', 'description' => $case['excerpt'] ?? '', 'keywords' => '', 'og_image' => $case['image'] ?? ''];
    $jsonld = sv_org_jsonld() . sv_jsonld([
        '@context' => 'https://schema.org', '@type' => 'Article',
        'headline' => $case['title'], 'description' => $page['description'],
        'url' => sv_site_url('/keysy/' . $slug), 'datePublished' => $case['date'] ?? '',
        'author' => ['@type' => 'Organization', 'name' => 'Сайт в Рост'],
    ]);
    sv_head($page, ['jsonld' => $jsonld, 'type' => 'case']);
    sv_header('keysy');
    sv_breadcrumbs($page, $case['title']);
    ?>
<main class="post case container">
  <article>
    <h1 class="post__title"><?= sv_e($case['title']) ?></h1>
    <?php if (!empty($case['metrics'])): ?>
    <div class="case-metrics">
      <?php foreach ($case['metrics'] as $m): ?>
        <div class="case-metric"><div class="case-metric__v"><?= sv_e($m['value']) ?></div><div class="case-metric__l"><?= sv_e($m['label']) ?></div></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="content content--post"><?= $case['content'] ?? '' ?></div>
    <div class="case-cta">
      <h3>Хотите похожий результат?</h3>
      <?= sv_lead_form('case_' . $slug, '', 'Получить разбор моего проекта') ?>
    </div>
  </article>
  <section class="related">
    <h2 class="h2">Другие кейсы</h2>
    <div class="grid grid--3">
      <?php
      $others = array_values(array_filter(sv_cases(), fn($c) => $c['slug'] !== $slug));
      usort($others, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
      foreach (array_slice($others, 0, 3) as $c): ?>
        <article class="card card--case">
          <a class="card__img card__img--case" href="/keysy/<?= sv_e($c['slug']) ?>" style="--g1:<?= sv_e($c['g1'] ?? '#5B5BF0') ?>;--g2:<?= sv_e($c['g2'] ?? '#22D3EE') ?>">
            <span class="card__case-metric"><?= sv_e(($c['metrics'][0]['value'] ?? '')) ?></span>
            <span class="card__case-metric-label"><?= sv_e(($c['metrics'][0]['label'] ?? '')) ?></span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="/keysy/<?= sv_e($c['slug']) ?>"><?= sv_e($c['title']) ?></a></h3>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php
    sv_footer();
    sv_widgets();
}

/* ================================================================== */
/* Контакты (карта + реквизиты из настроек)                            */
/* ================================================================== */
function sv_render_contacts_block(): void {
    $S = sv_settings();
    $c = $S['contacts'] ?? [];
    ?>
<div class="contacts-grid">
  <div class="contacts-card">
    <h2>Свяжитесь с нами</h2>
    <ul class="contacts-list">
      <li><span class="contacts-list__ic"><?= sv_icon('phone', 18) ?></span><div><b>Телефон</b><a href="tel:<?= sv_e(preg_replace('/[^\d+]/', '', $c['phone_raw'] ?? '')) ?>"><?= sv_e($c['phone_display'] ?? '') ?></a></div></li>
      <li><span class="contacts-list__ic"><?= sv_icon('mail', 18) ?></span><div><b>E-mail</b><a href="mailto:<?= sv_e($c['email'] ?? '') ?>"><?= sv_e($c['email'] ?? '') ?></a></div></li>
      <?php if (!empty($c['telegram'])): ?><li><span class="contacts-list__ic"><?= sv_icon('tg', 18) ?></span><div><b>Telegram</b><a href="<?= sv_e($c['telegram']) ?>" target="_blank" rel="noopener"><?= sv_e($c['telegram']) ?></a></div></li><?php endif; ?>
      <?php if (!empty($c['vk'])): ?><li><span class="contacts-list__ic"><?= sv_icon('vk', 18) ?></span><div><b>ВКонтакте</b><a href="<?= sv_e($c['vk']) ?>" target="_blank" rel="noopener"><?= sv_e($c['vk']) ?></a></div></li><?php endif; ?>
      <?php if (!empty($c['max'])): ?><li><span class="contacts-list__ic"><?= sv_icon('max', 18) ?></span><div><b>MAX</b><a href="<?= sv_e($c['max']) ?>" target="_blank" rel="noopener">Профиль в MAX</a></div></li><?php endif; ?>
      <?php if (!empty($c['address'])): ?><li><span class="contacts-list__ic"><?= sv_icon('pin', 18) ?></span><div><b>Адрес</b><?= sv_e($c['address']) ?></div></li><?php endif; ?>
      <?php if (!empty($c['schedule'])): ?><li><span class="contacts-list__ic"><?= sv_icon('clock', 18) ?></span><div><b>Режим работы</b><?= sv_e($c['schedule']) ?></div></li><?php endif; ?>
    </ul>
    <?php if (!empty($c['requisites'])): ?><div class="contacts-req"><h3>Реквизиты</h3><p><?= nl2br(sv_e($c['requisites'])) ?></p></div><?php endif; ?>
  </div>
  <div class="contacts-form">
    <h2>Напишите нам</h2>
    <?= sv_lead_form('contacts_page', '', 'Отправить сообщение') ?>
  </div>
</div>
<div class="contacts-map">
  <iframe src="https://yandex.ru/map-widget/v1/?ll=39.701500%2C47.235700&z=12" width="100%" height="380" frameborder="0" loading="lazy" title="Мы на карте" allowfullscreen></iframe>
</div>
<?php
}

/* ================================================================== */
/* HTML-карта сайта                                                    */
/* ================================================================== */
function sv_render_sitemap_html(): void {
    $pages = sv_pages();
    $posts = sv_posts();
    $cases = sv_cases();
    usort($posts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    ?>
<div class="sitemap-html">
  <h2 class="h2">Основные страницы</h2>
  <ul class="sitemap-list">
    <li><a href="/"><b>Главная</b> <span>sitevrost.ru/</span></a></li>
    <?php foreach ($pages as $p): if (($p['slug'] ?? '') === '' || !empty($p['hide_in_sitemap'])) continue; ?>
      <li><a href="<?= sv_e(sv_url($p['slug'])) ?>"><b><?= sv_e($p['title']) ?></b> <span>sitevrost.ru<?= sv_e(sv_url($p['slug'])) ?></span></a></li>
    <?php endforeach; ?>
    <li><a href="/blog"><b>Блог агентства</b> <span>sitevrost.ru/blog</span></a></li>
    <li><a href="/keysy"><b>Кейсы и портфолио</b> <span>sitevrost.ru/keysy</span></a></li>
  </ul>
  <h2 class="h2">Кейсы</h2>
  <ul class="sitemap-list">
    <?php foreach ($cases as $c): ?>
      <li><a href="/keysy/<?= sv_e($c['slug']) ?>"><b><?= sv_e($c['title']) ?></b> <span>sitevrost.ru/keysy/<?= sv_e($c['slug']) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <h2 class="h2">Статьи блога</h2>
  <ul class="sitemap-list">
    <?php foreach ($posts as $p): ?>
      <li><a href="/blog/<?= sv_e($p['slug']) ?>"><b><?= sv_e($p['title']) ?></b> <span>sitevrost.ru/blog/<?= sv_e($p['slug']) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php
}

/* ================================================================== */
/* Поиск: страница результатов (без JS)                                */
/* ================================================================== */
function sv_render_search_page(string $q): void {
    $results = sv_search($q);
    $page = ['slug' => 'search', 'parent' => '', 'title' => 'Поиск по сайту — Сайт в Рост', 'description' => 'Поиск по страницам, статьям блога и кейсам сайта Сайт в Рост.', 'keywords' => '', 'og_image' => ''];
    sv_head($page, ['jsonld' => sv_website_jsonld()]);
    sv_header('');
    sv_breadcrumbs($page, 'Поиск');
    ?>
<main class="page-main container">
  <h1 class="page-h1">Результаты поиска</h1>
  <form class="search-inline" action="/search" method="get">
    <input type="search" name="q" value="<?= sv_e($q) ?>" placeholder="Что ищем?" maxlength="100">
    <button class="btn btn--primary" type="submit">Найти</button>
  </form>
  <?php if ($q): ?>
    <p class="search-count">По запросу «<?= sv_e($q) ?>» найдено: <?= count($results) ?></p>
    <div class="search-list">
      <?php foreach ($results as $r): ?>
        <a class="search-item" href="<?= sv_e($r['url']) ?>">
          <span class="search-item__type"><?= sv_e($r['type']) ?></span>
          <span class="search-item__title"><?= sv_e($r['title']) ?></span>
          <span class="search-item__snip"><?= sv_e($r['snippet']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$results): ?><p class="empty">Ничего не найдено. Попробуйте изменить запрос.</p><?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<?php
    sv_footer();
    sv_widgets();
}

/* ================================================================== */
/* sitemap.xml                                                         */
/* ================================================================== */
function sv_render_sitemap_xml(): void {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [];
    $add = function (string $loc, string $lastmod = '', string $priority = '0.5') use (&$urls) {
        $urls[] = ['loc' => sv_site_url($loc), 'lastmod' => $lastmod ? date('Y-m-d', strtotime($lastmod)) : date('Y-m-d'), 'priority' => $priority];
    };
    foreach (sv_pages() as $p) {
        $slug = $p['slug'] ?? '';
        $prio = $slug === '' ? '1.0' : (substr_count($slug, '/') === 0 ? '0.8' : '0.6');
        $add(sv_url($slug), $p['updated'] ?? '', $prio);
    }
    foreach (sv_posts() as $p) $add('/blog/' . $p['slug'], $p['updated'] ?? ($p['date'] ?? ''), '0.7');
    foreach (sv_cases() as $c) $add('/keysy/' . $c['slug'], $c['date'] ?? '', '0.7');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>  <url>
    <loc><?= sv_e($u['loc']) ?></loc>
    <lastmod><?= $u['lastmod'] ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority><?= $u['priority'] ?></priority>
  </url>
<?php endforeach; ?></urlset>
<?php
    exit;
}

/* ================================================================== */
/* 404                                                                 */
/* ================================================================== */
function sv_render_404(): void {
    http_response_code(404);
    $page = ['slug' => '404', 'parent' => '', 'title' => 'Страница не найдена — Сайт в Рост', 'description' => '', 'keywords' => '', 'og_image' => ''];
    sv_head($page);
    sv_header('');
    ?>
<main class="page-main container nf">
  <div class="nf__num">404</div>
  <h1 class="page-h1">Такой страницы нет</h1>
  <p>Возможно, ссылка устарела или страница была перемещена. Загляните в <a href="/sitemap">карту сайта</a> или воспользуйтесь поиском.</p>
  <p><a class="btn btn--primary" href="/">На главную</a> <a class="btn btn--ghost" href="/keysy">Наши кейсы</a></p>
</main>
<?php
    sv_footer();
    sv_widgets();
}
