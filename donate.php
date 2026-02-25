<?php
header('Content-Type: application/json');

$db = new SQLite3('database.sqlite');
// Создаём таблицу, если её нет
$db->exec("CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE,
    nickname TEXT,
    donat_name TEXT,
    amount INTEGER,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$action = $_POST['action'] ?? '';

if ($action === 'generate') {
    // Генерация уникального кода
    $nickname = $_POST['nickname'] ?? '';
    $donat_name = $_POST['donat_name'] ?? '';
    $amount = intval($_POST['amount'] ?? 0);

    if (!$nickname || !$donat_name || !$amount) {
        echo json_encode(['success' => false, 'error' => 'Не все данные']);
        exit;
    }

    // Генерируем код (NFX + 6 случайных символов)
    $code = 'NFX-' . strtoupper(substr(md5(uniqid()), 0, 6));

    // Сохраняем в БД
    $stmt = $db->prepare("INSERT INTO requests (code, nickname, donat_name, amount) VALUES (:code, :nickname, :donat_name, :amount)");
    $stmt->bindValue(':code', $code);
    $stmt->bindValue(':nickname', $nickname);
    $stmt->bindValue(':donat_name', $donat_name);
    $stmt->bindValue(':amount', $amount);
    $stmt->execute();

    echo json_encode(['success' => true, 'code' => $code]);
}
elseif ($action === 'confirm') {
    // Пользователь подтверждает, что оплатил (просто меняем статус на "paid", но админ потом подтвердит)
    $code = $_POST['code'] ?? '';
    if (!$code) {
        echo json_encode(['success' => false, 'error' => 'Код не указан']);
        exit;
    }

    $stmt = $db->prepare("UPDATE requests SET status='paid' WHERE code=:code AND status='pending'");
    $stmt->bindValue(':code', $code);
    $stmt->execute();

    if ($db->changes() > 0) {
        // Здесь можно отправить уведомление админу (например, через Telegram или просто проигнорировать)
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Код не найден или уже подтверждён']);
    }
}
