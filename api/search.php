<?php
/**
 * Сквозной поиск по сайту (лупа в шапке).
 */
require dirname(__DIR__) . '/engine/core.php';
header('Content-Type: application/json; charset=utf-8');
$q = trim((string)($_GET['q'] ?? ''));
sv_json_response(['ok' => true, 'q' => $q, 'results' => sv_search($q)]);
