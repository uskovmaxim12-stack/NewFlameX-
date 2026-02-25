<?php
header('Content-Type: application/json');

// Настройки RCON (замени на свои)
define('RCON_HOST', '127.0.0.1');      // IP сервера
define('RCON_PORT', 25575);             // Порт RCON
define('RCON_PASSWORD', 'твой_пароль'); // Пароль из server.properties

// Разрешённые названия донатов (для безопасности)
$allowedDonats = ['IMPERIAL', 'NETHER', 'SPACE', 'SAMURAI', 'FLAME'];

// Подключение к SQLite
$db = new SQLite3('database.sqlite');
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

// ===== ГЕНЕРАЦИЯ КОДА =====
if ($action === 'generate') {
    $nickname = trim($_POST['nickname'] ?? '');
    $donat_name = strtoupper(trim($_POST['donat_name'] ?? ''));
    $amount = intval($_POST['amount'] ?? 0);

    if (!$nickname || !in_array($donat_name, $allowedDonats) || $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Неверные данные']);
        exit;
    }

    // Генерация уникального кода (NFX + 6 символов)
    do {
        $code = 'NFX-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $check = $db->querySingle("SELECT id FROM requests WHERE code = '$code'");
    } while ($check);

    $stmt = $db->prepare("INSERT INTO requests (code, nickname, donat_name, amount) VALUES (:code, :nickname, :donat_name, :amount)");
    $stmt->bindValue(':code', $code);
    $stmt->bindValue(':nickname', $nickname);
    $stmt->bindValue(':donat_name', $donat_name);
    $stmt->bindValue(':amount', $amount);
    $stmt->execute();

    echo json_encode(['success' => true, 'code' => $code]);
    exit;
}

// ===== ПОДТВЕРЖДЕНИЕ ОПЛАТЫ =====
if ($action === 'confirm') {
    $code = trim($_POST['code'] ?? '');
    if (!$code) {
        echo json_encode(['success' => false, 'error' => 'Код не указан']);
        exit;
    }

    // Проверяем, что заявка существует и ещё не подтверждена
    $stmt = $db->prepare("UPDATE requests SET status='paid' WHERE code=:code AND status='pending'");
    $stmt->bindValue(':code', $code);
    $stmt->execute();

    if ($db->changes() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Код не найден или уже обработан']);
    }
    exit;
}

// Неизвестное действие
echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
