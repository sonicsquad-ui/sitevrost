<?php
/**
 * Сайт в Рост — ядро: конфигурация, сессии, данные, безопасность.
 * Файловое хранилище (JSON) — работает на любом простом хостинге с PHP 7.4+ без БД.
 */

if (!defined('SV_ROOT')) define('SV_ROOT', dirname(__DIR__));
define('SV_DATA', SV_ROOT . '/data');
define('SV_UPLOAD', SV_ROOT . '/upload');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
if (!ini_get('date.timezone')) date_default_timezone_set('Europe/Moscow');

/* Полифилы mb_* для экзотических окружений (на REG.RU mbstring есть всегда) */
if (!function_exists('mb_internal_encoding')) { function mb_internal_encoding($e = null) { return true; } }
if (!function_exists('mb_strlen')) { function mb_strlen($s, $e = null) { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr($s, $a, $b = null, $e = null) { return substr($s, $a, $b); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower($s, $e = null) { return strtolower($s); } }

mb_internal_encoding('UTF-8');

/* ------------------------------------------------------------------ */
/* Конфигурация (храним в PHP-файле, недоступном извне)               */
/* ------------------------------------------------------------------ */
function sv_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $f = SV_DATA . '/config.php';
        $cfg = is_file($f) ? (require $f) : [];
        if (!is_array($cfg)) $cfg = [];
    }
    return $cfg;
}

/* ------------------------------------------------------------------ */
/* Хелперы                                                             */
/* ------------------------------------------------------------------ */
function sv_e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function sv_url(string $path): string {
    if ($path === '') return '/';
    return '/' . ltrim($path, '/');
}

function sv_json_read(string $name, $default = []) {
    static $cache = [];
    if (array_key_exists($name, $cache)) return $cache[$name];
    $f = SV_DATA . '/' . $name . '.json';
    $res = $default;
    if (is_file($f)) {
        $raw = @file_get_contents($f);
        if ($raw !== false) {
            $d = json_decode($raw, true);
            if (is_array($d)) $res = $d;
        }
    }
    $cache[$name] = $res;
    return $res;
}

function sv_json_write(string $name, $data): bool {
    $dir = SV_DATA;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $f = $dir . '/' . $name . '.json';
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $f);
}

function sv_json_invalidate(string $name): void { /* кэш в рамках запроса */ }

function sv_settings(): array {
    static $s = null;
    if ($s === null) $s = sv_json_read('settings', []);
    return $s;
}

function sv_pages(): array { return sv_json_read('pages', []); }
function sv_posts(): array { return sv_json_read('posts', []); }
function sv_cases(): array { return sv_json_read('cases', []); }
function sv_authors(): array { return sv_json_read('authors', []); }
function sv_blog_cats(): array { $s = sv_settings(); return $s['blog_cats'] ?? []; }

function sv_page_by_slug(string $slug): ?array {
    foreach (sv_pages() as $p) if (($p['slug'] ?? '') === $slug) return $p;
    return null;
}
function sv_post_by_slug(string $slug): ?array {
    foreach (sv_posts() as $p) if (($p['slug'] ?? '') === $slug) return $p;
    return null;
}
function sv_case_by_slug(string $slug): ?array {
    foreach (sv_cases() as $c) if (($c['slug'] ?? '') === $slug) return $c;
    return null;
}

function sv_generate_id(string $prefix): string {
    return $prefix . '_' . substr(bin2hex(random_bytes(6)), 0, 10);
}

function sv_now(): string { return date('Y-m-d H:i:s'); }
function sv_today(): string { return date('Y-m-d'); }

function sv_ru_date(string $date, bool $withYear = true): string {
    $months = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = strtotime($date);
    if (!$ts) return $date;
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ($withYear ? ' ' . date('Y', $ts) : '');
}

function sv_strip_words(?string $html, int $limit = 30): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
    $words = explode(' ', $text);
    if (count($words) > $limit) $words = array_slice($words, 0, $limit);
    return implode(' ', $words);
}

function sv_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/* ------------------------------------------------------------------ */
/* Собственная файловая сессия (независимость от настроек хостинга)    */
/* ------------------------------------------------------------------ */
$GLOBALS['SVR_SESSION'] = null;
$GLOBALS['SVR_SID'] = '';

function sv_setcookie(string $name, string $value, int $expire): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($name, $value, [
        'expires' => $expire, 'path' => '/', 'domain' => '',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}

function sv_session(): array {
    if ($GLOBALS['SVR_SESSION'] !== null) return $GLOBALS['SVR_SESSION'];
    $sid = $_COOKIE['SVRSESSION'] ?? '';
    $valid = (bool)preg_match('/^[a-f0-9]{64}$/', $sid);
    if (!$valid) {
        $sid = bin2hex(random_bytes(32));
        sv_setcookie('SVRSESSION', $sid, time() + 86400 * 30);
    }
    $GLOBALS['SVR_SID'] = $sid;
    $dir = SV_DATA . '/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $f = $dir . '/' . $sid . '.json';
    $data = [];
    if (is_file($f)) {
        $raw = @file_get_contents($f);
        $d = $raw ? json_decode($raw, true) : null;
        if (is_array($d)) $data = $d;
        // чистим старые сессии
        if (time() - (int)@filemtime($f) > 86400 * 30) { @unlink($f); $data = []; }
    }
    $GLOBALS['SVR_SESSION'] = $data;
    register_shutdown_function('sv_session_save');
    return $data;
}

function sv_session_set(string $key, $value): void {
    sv_session();
    $GLOBALS['SVR_SESSION'][$key] = $value;
}

function sv_session_get(string $key, $default = null) {
    $s = sv_session();
    return $s[$key] ?? $default;
}

function sv_session_destroy(): void {
    $sid = $GLOBALS['SVR_SID'] ?: ($_COOKIE['SVRSESSION'] ?? '');
    if ($sid && preg_match('/^[a-f0-9]{64}$/', $sid)) {
        @unlink(SV_DATA . '/sessions/' . $sid . '.json');
    }
    $GLOBALS['SVR_SESSION'] = [];
    sv_setcookie('SVRSESSION', '', time() - 3600);
}

function sv_session_save(): void {
    if ($GLOBALS['SVR_SESSION'] === null || !$GLOBALS['SVR_SID']) return;
    $f = SV_DATA . '/sessions/' . $GLOBALS['SVR_SID'] . '.json';
    @file_put_contents($f, json_encode($GLOBALS['SVR_SESSION'], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */
function sv_csrf_token(): string {
    $t = sv_session_get('csrf');
    if (!$t) { $t = bin2hex(random_bytes(16)); sv_session_set('csrf', $t); }
    return $t;
}

function sv_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . sv_e(sv_csrf_token()) . '">';
}

function sv_csrf_check(): bool {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $real = sv_session_get('csrf');
    return $real && is_string($t) && hash_equals($real, $t);
}

/* ------------------------------------------------------------------ */
/* Авторизация администратора                                          */
/* ------------------------------------------------------------------ */
function sv_is_admin(): bool {
    return sv_session_get('admin') === true;
}

function sv_login_attempts(string $ip): array {
    $all = sv_json_read('login_attempts', []);
    return $all[$ip] ?? [];
}

function sv_login_blocked(string $ip): bool {
    $tries = array_filter(sv_login_attempts($ip), fn($t) => $t > time() - 900);
    return count($tries) >= 5;
}

function sv_login_register_fail(string $ip): void {
    $all = sv_json_read('login_attempts', []);
    $tries = array_filter($all[$ip] ?? [], fn($t) => $t > time() - 900);
    $tries[] = time();
    $all[$ip] = array_values($tries);
    sv_json_write('login_attempts', $all);
}

function sv_login_clear(string $ip): void {
    $all = sv_json_read('login_attempts', []);
    unset($all[$ip]);
    sv_json_write('login_attempts', $all);
}

function sv_try_login(string $login, string $password): bool {
    $ip = sv_ip();
    if (sv_login_blocked($ip)) return false;
    $cfg = sv_config();
    $okUser = hash_equals($cfg['admin_login'] ?? 'admin', $login);
    $okPass = !empty($cfg['admin_pass_hash']) && password_verify($password, $cfg['admin_pass_hash']);
    if ($okUser && $okPass) {
        sv_login_clear($ip);
        // перегенерируем сессию
        sv_session_destroy();
        $sid = bin2hex(random_bytes(32));
        sv_setcookie('SVRSESSION', $sid, time() + 86400 * 2);
        $GLOBALS['SVR_SID'] = $sid;
        $GLOBALS['SVR_SESSION'] = [];
        sv_session_set('admin', true);
        sv_session_set('admin_login_time', time());
        return true;
    }
    sv_login_register_fail($ip);
    return false;
}

function sv_save_admin_password(string $newPassword): void {
    $cfg = sv_config();
    $cfg['admin_pass_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $code = "<?php\n// Служебный файл. Доступ извне запрещён.\nreturn " . var_export($cfg, true) . ";\n";
    @file_put_contents(SV_DATA . '/config.php', $code, LOCK_EX);
}

/* ------------------------------------------------------------------ */
/* Отправка почты + лог (на простом хостинге работает mail())          */
/* ------------------------------------------------------------------ */
function sv_send_mail(string $to, string $subject, string $htmlBody): bool {
    $fromEmail = 'no-reply@sitevrost.ru';
    $fromName = 'Сайт в Рост';
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "From: {$fromName} <{$fromEmail}>\r\n"
        . "Reply-To: {$fromEmail}\r\n";
    $sent = false;
    if (function_exists('mail')) {
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers, '-f' . $fromEmail);
    }
    // резервный лог, чтобы письма не терялись при проблемах с mail()
    $log = SV_DATA . '/mail.log';
    $entry = '[' . sv_now() . '] ' . ($sent ? 'OK' : 'FAIL') . " -> {$to} | {$subject}\n";
    @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
    return (bool)$sent;
}

/* ------------------------------------------------------------------ */
/* JSON-ответы API                                                     */
/* ------------------------------------------------------------------ */
function sv_json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ */
/* Защита загрузки файлов (админка)                                    */
/* ------------------------------------------------------------------ */
function sv_upload_base64(string $base64, string $name): array {
    if (!sv_is_admin()) return ['ok' => false, 'error' => 'Не авторизован'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    if (!in_array($ext, $allowed, true)) return ['ok' => false, 'error' => 'Недопустимый тип файла. Разрешены: ' . implode(', ', $allowed)];
    $data = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $base64), true);
    if ($data === false || strlen($data) < 10) return ['ok' => false, 'error' => 'Повреждённые данные'];
    if (strlen($data) > 8 * 1024 * 1024) return ['ok' => false, 'error' => 'Файл больше 8 МБ'];
    // проверка сигнатуры изображения (GD есть не во всех окружениях)
    if (function_exists('getimagesizefromstring')) {
        $info = @getimagesizefromstring($data);
        if (!$info) return ['ok' => false, 'error' => 'Файл не является изображением'];
    } else {
        $sig = substr($data, 0, 8);
        $okSig = strpos($sig, "\xFF\xD8\xFF") === 0 || strpos($sig, "\x89PNG") === 0 || strpos($sig, 'GIF8') === 0 || substr($data, 0, 4) === 'RIFF';
        if (!$okSig) return ['ok' => false, 'error' => 'Файл не является изображением'];
    }
    $dir = SV_UPLOAD . '/' . date('Y/m');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fname = date('d') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $fname, $data, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл (проверьте права на папку /upload)'];
    }
    return ['ok' => true, 'url' => '/upload/' . date('Y/m') . '/' . $fname, 'location' => '/upload/' . date('Y/m') . '/' . $fname];
}

/* ------------------------------------------------------------------ */
/* Поиск по сайту                                                      */
/* ------------------------------------------------------------------ */
function sv_search(string $q, int $limit = 12): array {
    $q = trim(mb_substr($q, 0, 100));
    if (mb_strlen($q) < 2) return [];
    $ql = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    $results = [];
    $push = function (string $title, string $url, string $type, string $hay) use ($ql, $q, &$results) {
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
        $pos = strpos($hay, $ql);
        if ($pos === false) return;
        $start = max(0, $pos - 60);
        $snip = mb_substr($hay, $start, 160);
        if ($start > 0) $snip = '…' . $snip;
        $results[] = ['title' => $title, 'url' => $url, 'type' => $type, 'snippet' => $snip . '…', 'pos' => $pos];
    };
    foreach (sv_pages() as $p) {
        $push($p['title'] ?? '', sv_url($p['slug'] ?? ''), 'Страница',
            ($p['title'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . sv_strip_words($p['content'] ?? '', 120));
    }
    foreach (sv_posts() as $p) {
        $push($p['title'] ?? '', '/blog/' . ($p['slug'] ?? ''), 'Блог',
            ($p['title'] ?? '') . ' ' . ($p['excerpt'] ?? '') . ' ' . sv_strip_words($p['content'] ?? '', 120));
    }
    foreach (sv_cases() as $c) {
        $push($c['title'] ?? '', '/keysy/' . ($c['slug'] ?? ''), 'Кейс',
            ($c['title'] ?? '') . ' ' . ($c['excerpt'] ?? '') . ' ' . sv_strip_words($c['content'] ?? '', 120));
    }
    usort($results, fn($a, $b) => $a['pos'] <=> $b['pos']);
    return array_slice($results, 0, $limit);
}

/* ------------------------------------------------------------------ */
/* Заявки                                                              */
/* ------------------------------------------------------------------ */
function sv_save_lead(array $lead): void {
    $leads = sv_json_read('leads', []);
    array_unshift($leads, $lead);
    if (count($leads) > 5000) $leads = array_slice($leads, 0, 5000);
    sv_json_write('leads', $leads);
}

/* Хлебные крошки: цепочка от главной до страницы */
function sv_crumbs(array $page): array {
    $crumbs = [['name' => 'Главная', 'url' => '/']];
    $chain = [];
    $slug = $page['parent'] ?? '';
    $guard = 0;
    while ($slug && $guard++ < 10) {
        $parent = sv_page_by_slug($slug);
        if (!$parent) break;
        array_unshift($chain, ['name' => $parent['crumb'] ?? $parent['title'] ?? '', 'url' => sv_url($parent['slug'])]);
        $slug = $parent['parent'] ?? '';
    }
    return array_merge($crumbs, $chain);
}
