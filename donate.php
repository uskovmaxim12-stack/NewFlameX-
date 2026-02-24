<?php
$allowed_donats = ['IMPERIAL', 'NETHER', 'SPACE', 'SAMURAI', 'FLAME'];
if (!in_array(strtoupper($donat), $allowed_donats)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый донат']);
    exit;
}
// donate.php — скрипт выдачи доната через RCON

header('Content-Type: application/json');

// Настройки RCON (заполни своими данными)
$rcon_host = '127.0.0.1';      // IP сервера Minecraft (если скрипт на том же сервере, то localhost)
$rcon_port = 25575;             // Порт RCON
$rcon_password = 'твой_пароль'; // Пароль из server.properties

// Получаем данные от сайта (POST-запрос)
$input = json_decode(file_get_contents('php://input'), true);
$nick = $input['nick'] ?? '';
$donat = $input['donat'] ?? '';

if (!$nick || !$donat) {
    echo json_encode(['success' => false, 'error' => 'Не указан ник или донат']);
    exit;
}

// Подключаемся к RCON
$socket = @fsockopen($rcon_host, $rcon_port, $errno, $errstr, 5);
if (!$socket) {
    echo json_encode(['success' => false, 'error' => "Не удалось подключиться к RCON: $errstr"]);
    exit;
}

// Авторизация (протокол RCON)
$packet = pack('VV', 3, strlen($rcon_password) + 2) . "\x00\x00" . $rcon_password . "\x00\x00";
fwrite($socket, $packet);
$response = fread($socket, 4); // Читаем длину
if (!$response) {
    echo json_encode(['success' => false, 'error' => 'Ошибка авторизации RCON']);
    fclose($socket);
    exit;
}

// Отправляем команду (пример для LuckPerms)
$command = "lp user $nick parent add $donat";
$packet = pack('VV', 2, strlen($command) + 2) . "\x00\x00" . $command . "\x00\x00";
fwrite($socket, $packet);
$response = fread($socket, 4); // Можно прочитать ответ при желании

fclose($socket);

// Отвечаем сайту
echo json_encode(['success' => true, 'message' => "Команда $command отправлена"]);
