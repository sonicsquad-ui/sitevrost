<?php
/**
 * Загрузка изображений из админки (TinyMCE и поля «изображение»).
 * Принимает JSON {name, data(base64)} — только для авторизованных администраторов.
 */
require dirname(__DIR__) . '/engine/core.php';
header('Content-Type: application/json; charset=utf-8');

if (!sv_is_admin()) sv_json_response(['ok' => false, 'error' => 'Требуется авторизация'], 403);
if (!sv_csrf_check()) sv_json_response(['ok' => false, 'error' => 'Ошибка сессии, обновите страницу'], 403);

$raw = (string)file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) sv_json_response(['ok' => false, 'error' => 'Некорректный запрос'], 400);

$res = sv_upload_base64((string)($in['data'] ?? ''), (string)($in['name'] ?? 'image.jpg'));
sv_json_response($res, $res['ok'] ? 200 : 400);
