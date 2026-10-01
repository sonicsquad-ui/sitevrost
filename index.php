<?php
/**
 * Сайт в Рост — фронт-контроллер.
 * Все запросы, не найденные на диске (через .htaccess), попадают сюда.
 */

// Встроенный сервер PHP (локальный предпросмотр): отдаём статику напрямую
if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) return false;
    $_SERVER['REQUEST_URI'] = $uri;
}

require __DIR__ . '/engine/core.php';
require __DIR__ . '/engine/render.php';
require __DIR__ . '/engine/router.php';

sv_route();
