<?php
// Простая защита паролем (можно заменить на .htaccess)
$valid_login = 'admin';
$valid_password = 'StrongPassword123'; // Смени на свой пароль!

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != $valid_login || $_SERVER['PHP_AUTH_PW'] != $valid_password) {
    header('WWW-Authenticate: Basic realm="Admin area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Требуется авторизация';
    exit;
}

// Настройки RCON
define('RCON_HOST', '127.0.0.1');
define('RCON_PORT', 25575);
define('RCON_PASSWORD', 'твой_пароль');

$db = new SQLite3('database.sqlite');

// Функция отправки RCON-команды
function sendRconCommand($command) {
    $socket = @fsockopen(RCON_HOST, RCON_PORT, $errno, $errstr, 5);
    if (!$socket) {
        return "Ошибка подключения к RCON: $errstr";
    }

    // Авторизация
    $packet = pack('VV', 3, strlen(RCON_PASSWORD) + 2) . "\x00\x00" . RCON_PASSWORD . "\x00\x00";
    fwrite($socket, $packet);
    $response = fread($socket, 4);
    if (!$response) {
        fclose($socket);
        return "Ошибка авторизации RCON";
    }

    // Отправка команды
    $packet = pack('VV', 2, strlen($command) + 2) . "\x00\x00" . $command . "\x00\x00";
    fwrite($socket, $packet);
    $response = fread($socket, 4);
    fclose($socket);
    return "Команда отправлена";
}

// Обработка подтверждения
if (isset($_POST['approve'])) {
    $id = intval($_POST['id']);
    $stmt = $db->prepare("SELECT * FROM requests WHERE id=:id AND status='paid'");
    $stmt->bindValue(':id', $id);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $nick = $row['nickname'];
        $donat = $row['donat_name'];
        // Формируем команду (пример для LuckPerms)
        $command = "lp user $nick parent add $donat";
        $result = sendRconCommand($command);

        // Обновляем статус
        $update = $db->prepare("UPDATE requests SET status='approved' WHERE id=:id");
        $update->bindValue(':id', $id);
        $update->execute();

        $message = "✅ Донат $donat выдан игроку $nick. Результат: $result";
    } else {
        $message = "❌ Заявка не найдена или уже обработана.";
    }
}

// Получаем заявки со статусом 'paid' (ожидают выдачи)
$pending = $db->query("SELECT * FROM requests WHERE status='paid' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админ-панель NewFlameX</title>
    <meta charset="utf-8">
    <style>
        body { background: #12161f; color: white; font-family: 'Montserrat', sans-serif; padding: 20px; }
        h1 { color: #ff9a9e; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #1e2432; color: #ffe066; }
        td { background: #1a1f2b; }
        button { background: #10b981; border: none; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; cursor: pointer; }
        button:hover { background: #059669; }
        .message { background: #1e3a2e; padding: 10px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>👑 Админ-панель NewFlameX</h1>
    <?php if (isset($message)) echo "<div class='message'>$message</div>"; ?>
    <h2>Заявки, ожидающие подтверждения оплаты</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Код</th>
            <th>Ник</th>
            <th>Донат</th>
            <th>Сумма</th>
            <th>Дата</th>
            <th>Действие</th>
        </tr>
        <?php while ($row = $pending->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><strong><?= htmlspecialchars($row['code']) ?></strong></td>
            <td><?= htmlspecialchars($row['nickname']) ?></td>
            <td><?= $row['donat_name'] ?></td>
            <td><?= $row['amount'] ?> ₽</td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" name="approve">✅ Выдать донат</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <p><a href="index.html" style="color:#ff9a9e;">← Вернуться на сайт</a></p>
</body>
</html>
