#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;

// Читаем логин и пароль из файла .env
$env = parse_ini_file(__DIR__ . '/.env');
$login = $env['M4_LOGIN'];
$password = $env['M4_PASSWORD'];

// ФИО передаем параметром при запуске
$fio = $argv[1] ?? 'Кандидат';

// Создаем HTTP клиент (отключаем проверку SSL для тестового сервера)
$client = new Client(['verify' => false, 'timeout' => 30]);

echo "\n=== M4 API ТЕСТ ===\n\n";

// ============================================
// 1. Авторизация
// ============================================
echo "1. Авторизация...\n";
$response = $client->post('https://developer-api.m4.systems:4443/api_auth/login_check', [
    'json' => ['username' => $login, 'password' => $password]
]);
$auth = json_decode($response->getBody(), true);
$token = $auth['token'];

// Ищем URL сервиса SD (Service Desk)
$sdUrl = null;
foreach ($auth['services'] as $service) {
    if ($service['code'] === 'SD') {
        $sdUrl = rtrim($service['apiUrl'], '/');
    }
}
echo "OK\n\n";

// ============================================
// 2. Получаем список заявок за последние 3 дня
// ============================================
echo "2. Получаем заявки за последние 3 дня...\n";
$date = date('d.m.Y H:i:s', strtotime('-3 days'));
$response = $client->post($sdUrl, [
    'headers' => ['Authorization' => "Bearer $token"],
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'M4GetTasks',
        'params' => ['lastUpdate' => $date],
        'id' => 1
    ]
]);
$data = json_decode($response->getBody(), true);
$tasks = $data['result'] ?? [];
$count = count($tasks);

echo "Найдено: $count заявок\n\n";

// Проверяем, хватает ли заявок для теста
if ($count < 2) {
    echo "Недостаточно заявок для выполнения тестового сценария\n";
    exit(0);
}

// ============================================
// 3. Берем вторую заявку из списка (индекс 1)
// ============================================
$task = $tasks[1];
$taskId = $task['taskId'] ?? $task['id'];
echo "3. Вторая заявка, ID: $taskId\n\n";

// ============================================
// 4. Получаем детальную информацию по заявке
// ============================================
echo "4. Детальная информация по заявке:\n";
$response = $client->post($sdUrl, [
    'headers' => ['Authorization' => "Bearer $token"],
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'M4GetTaskDetails',
        'params' => ['taskId' => (int)$taskId],
        'id' => 2
    ]
]);
$details = json_decode($response->getBody(), true)['result'] ?? [];

// Определяем статус (может быть массивом или строкой)
$status = $details['status'] ?? null;
if (is_array($status)) {
    $statusName = $status['name'] ?? 'N/A';
} else {
    $statusName = $details['statusName'] ?? 'N/A';
}

// Выводим требуемые поля
echo "taskId: {$details['taskId']}\n";
echo "req: {$details['req']}\n";
echo "caption: {$details['caption']}\n";
echo "status/statusName: $statusName\n\n";

// ============================================
// 5. Загружаем два изображения на сервер
// ============================================
echo "5. Загружаем изображения...\n";

// Ищем URL сервиса STORAGE для загрузки файлов
$storageUrl = null;
foreach ($auth['services'] as $service) {
    if ($service['code'] === 'STORAGE') {
        $storageUrl = rtrim($service['apiUrl'], '/');
    }
}

// Создаем папку для изображений, если её нет
$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

// Ищем изображения в папке
$images = glob($imagesDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Если нет изображений - создаем тестовые
if (count($images) < 2) {
    echo "Создаем тестовые изображения...\n";
    for ($i = 1; $i <= 2; $i++) {
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, rand(0,255), rand(0,255), rand(0,255)));
        imagejpeg($img, $imagesDir . "/img$i.jpg");
        imagedestroy($img);
    }
    $images = glob($imagesDir . '/*.jpg');
}

// Загружаем первые два изображения
$guids = [];
foreach (array_slice($images, 0, 2) as $file) {
    echo basename($file) . "... ";
    $response = $client->post($storageUrl . '/putfile.php', [
        'headers' => ['Authorization' => "Bearer $token"],
        'multipart' => [['name' => 'file', 'contents' => fopen($file, 'r')]]
    ]);
    $result = json_decode($response->getBody(), true);
    $guids[] = $result['result']['guid'];
    echo "OK\n";
}

// ============================================
// 6. Прикрепляем изображения к заявке
// ============================================
echo "\n6. Прикрепляем изображения к заявке...\n";
$files = array_map(function($guid) {
    return ['guid' => $guid, 'typeAttachId' => 5]; // тип 5 = изображение
}, $guids);

$client->post($sdUrl, [
    'headers' => ['Authorization' => "Bearer $token"],
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'M4AddTaskAttach',
        'params' => ['taskId' => (int)$taskId, 'files' => $files],
        'id' => 3
    ]
]);
echo "OK\n";

// ============================================
// 7. Добавляем публичный комментарий
// ============================================
echo "\n7. Добавляем комментарий...\n";
$comment = "Тестовый комментарий от кандидата: $fio, " . date('d.m.Y H:i:s');
$client->post($sdUrl, [
    'headers' => ['Authorization' => "Bearer $token"],
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'M4AddTaskComment',
        'params' => [
            'taskId' => (int)$taskId,
            'comment' => $comment,
            'isPublic' => true
        ],
        'id' => 4
    ]
]);
echo "OK: $comment\n";

// ============================================
// 8. Выход из системы
// ============================================
echo "\n8. Выход из системы...\n";
$client->post('https://developer-api.m4.systems:4443/api_auth', [
    'headers' => ['Authorization' => "Bearer $token"],
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'logout',
        'id' => 5
    ]
]);
echo "OK\n\n";

echo "=== ГОТОВО ===\n";
