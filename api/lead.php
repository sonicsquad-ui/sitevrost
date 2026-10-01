<?php
/**
 * Приём заявок со всех форм сайта.
 * Заявка сохраняется в админку и дублируется письмом на почту агентства.
 */
require dirname(__DIR__) . '/engine/core.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sv_json_response(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

// Антиспам: ограничение по частоте с одного IP
$rate = sv_json_read('lead_rate', []);
$ip = sv_ip();
$recent = array_values(array_filter($rate[$ip] ?? [], fn($t) => $t > time() - 600));
if (count($recent) >= 5) {
    sv_json_response(['ok' => false, 'error' => 'Слишком много заявок подряд. Попробуйте через несколько минут или позвоните нам.'], 429);
}

// Проверка CSRF (токен есть в каждой форме)
if (!sv_csrf_check()) {
    sv_json_response(['ok' => false, 'error' => 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.'], 403);
}

// Поля (поддержка и $_POST, и тела запроса)
$in = $_POST;
if (!$in) {
    $raw = (string)file_get_contents('php://input');
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) $in = $j;
    } else {
        parse_str($raw, $in);
    }
}

// Ханипот (скрытое поле для ботов)
if (!empty($in['hp_field'])) {
    sv_json_response(['ok' => true, 'message' => 'Спасибо! Заявка отправлена.']); // боту говорим "ок"
}

$name = trim((string)($in['name'] ?? ''));
$phone = trim((string)($in['phone'] ?? ''));
$email = trim((string)($in['email'] ?? ''));
$message = trim((string)($in['message'] ?? ''));
$agree = !empty($in['agree']) && in_array($in['agree'], ['1', 'true', 'on', true], true);
$form = preg_replace('/[^a-z0-9_\-]/i', '', (string)($in['form'] ?? 'site')) ?: 'site';
$pageSlug = mb_substr(trim((string)($in['page'] ?? '')), 0, 120);
$quiz = is_array($in['quiz'] ?? null) ? $in['quiz'] : [];

// Валидация
$errors = [];
if (mb_strlen($name) < 2) $errors[] = 'Укажите имя.';
if (!preg_match('/^[\d\s()+\-]{6,30}$/', $phone) && !filter_var($phone, FILTER_VALIDATE_EMAIL) && !preg_match('/@[a-z0-9\.\-]+\.[a-z]{2,}/iu', $email)) {
    $errors[] = 'Укажите корректный телефон или e-mail.';
}
if (!$agree) $errors[] = 'Для отправки нужно согласие на обработку персональных данных.';
if (mb_strlen($name) > 100 || mb_strlen($message) > 2000) $errors[] = 'Слишком длинное значение поля.';

if ($errors) {
    sv_json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

$lead = [
    'id' => sv_generate_id('lead'),
    'date' => sv_now(),
    'status' => 'new',
    'form' => $form,
    'page' => $pageSlug,
    'ip' => $ip,
    'name' => mb_substr($name, 0, 100),
    'phone' => mb_substr($phone, 0, 30),
    'email' => mb_substr($email, 0, 100),
    'message' => mb_substr($message, 0, 2000),
    'quiz' => $quiz,
];
sv_save_lead($lead);

// Ограничитель частоты
$recent[] = time();
$rate[$ip] = $recent;
// чистим старые записи по всем IP
foreach ($rate as $k => $v) $rate[$k] = array_values(array_filter($v, fn($t) => $t > time() - 600));
sv_json_write('lead_rate', $rate);

// Дублируем на почту
$S = sv_settings();
$to = $S['lead_email'] ?? 'sonicsquad@mail.ru';
$quizHtml = '';
if ($quiz) {
    $quizHtml = '<p><b>Ответы из квиза:</b></p><ul>';
    foreach ($quiz as $k => $v) $quizHtml .= '<li>' . sv_e($k) . ': ' . sv_e(is_array($v) ? implode(', ', $v) : (string)$v) . '</li>';
    $quizHtml .= '</ul>';
}
$body = '<h2 style="font-family:Arial,sans-serif">Новая заявка с сайта Сайт в Рост</h2>'
    . '<table cellpadding="6" style="font-family:Arial,sans-serif;border-collapse:collapse">'
    . '<tr><td style="border:1px solid #ddd"><b>Дата</b></td><td style="border:1px solid #ddd">' . sv_e($lead['date']) . '</td></tr>'
    . '<tr><td style="border:1px solid #ddd"><b>Форма</b></td><td style="border:1px solid #ddd">' . sv_e($form) . '</td></tr>'
    . '<tr><td style="border:1px solid #ddd"><b>Страница</b></td><td style="border:1px solid #ddd">' . sv_e($pageSlug ?: '—') . '</td></tr>'
    . '<tr><td style="border:1px solid #ddd"><b>Имя</b></td><td style="border:1px solid #ddd">' . sv_e($lead['name']) . '</td></tr>'
    . '<tr><td style="border:1px solid #ddd"><b>Телефон</b></td><td style="border:1px solid #ddd">' . sv_e($lead['phone']) . '</td></tr>'
    . ($email ? '<tr><td style="border:1px solid #ddd"><b>E-mail</b></td><td style="border:1px solid #ddd">' . sv_e($email) . '</td></tr>' : '')
    . ($message ? '<tr><td style="border:1px solid #ddd"><b>Сообщение</b></td><td style="border:1px solid #ddd">' . nl2br(sv_e($message)) . '</td></tr>' : '')
    . '</table>' . $quizHtml
    . '<p style="font-family:Arial,sans-serif;color:#777">Управлять заявками можно в админке: /admin → Заявки.</p>';
sv_send_mail($to, 'Заявка с сайта: ' . $form . ' (' . $lead['name'] . ')', $body);

sv_json_response(['ok' => true, 'message' => 'Спасибо! Заявка отправлена. Мы свяжемся с вами в течение рабочего дня.']);
