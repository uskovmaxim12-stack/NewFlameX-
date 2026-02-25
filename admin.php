<?php
// Защита уже через .htaccess, поэтому просто показываем панель
$db = new SQLite3('database.sqlite');

// Если нажата кнопка "Выдать"
if (isset($_POST['approve'])) {
    $id = intval($_POST['id']);
    // Получаем данные заявки
    $stmt = $db->prepare("SELECT * FROM requests WHERE id=:id AND status='paid'");
    $stmt->bindValue(':id', $id);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        // Здесь код для выдачи через RCON (как в предыдущем donate.php)
        $nick = $row['nickname'];
        $donat = $row['donat_name'];
        // Подключение к RCON...
        // Если успешно, обновляем статус
        $update = $db->prepare("UPDATE requests SET status='approved' WHERE id=:id");
        $update->bindValue(':id', $id);
        $update->execute();
        $message = "Донат $donat выдан игроку $nick";
    } else {
        $message = "Заявка не найдена или уже обработана";
    }
}

// Получаем все заявки со статусом 'paid' (ожидают подтверждения)
$pending = $db->query("SELECT * FROM requests WHERE status='paid' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админка донатов</title>
    <meta charset="utf-8">
    <style>
        body { background: #12161f; color: white; font-family: sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #1e2432; }
        button { background: #10b981; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Заявки на донат (ожидают оплаты)</h1>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>
    <table>
        <tr><th>ID</th><th>Код</th><th>Ник</th><th>Донат</th><th>Сумма</th><th>Дата</th><th>Действие</th></tr>
        <?php while ($row = $pending->fetchArray(SQLITE3_ASSOC)): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['code'] ?></td>
            <td><?= htmlspecialchars($row['nickname']) ?></td>
            <td><?= $row['donat_name'] ?></td>
            <td><?= $row['amount'] ?> ₽</td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" name="approve">✅ Выдать донат</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
