#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use M4\M4ApiClient;

// Читаем логин и пароль из файла .env
$env = parse_ini_file(__DIR__ . '/.env');
<<<<<<< HEAD
$login = $env['M4_LOGIN'] ?? null;
$password = $env['M4_PASSWORD'] ?? null;
=======
$login = $env['M4_LOGIN'];
$password = $env['M4_PASSWORD'];
>>>>>>> f2d416fcef536aa7257b8d88f33df2dd6a5c1a47

// Проверяем, что логин и пароль указаны
if (!$login || !$password) {
    die("Ошибка: создай файл .env с M4_LOGIN и M4_PASSWORD\n");
}

// ФИО передаем первым параметром при запуске
$fio = $argv[1] ?? 'Кандидат';

// Создаем экземпляр API клиента
$api = new M4ApiClient();

echo "\n=== M4 API ===\n\n";

// 1. Авторизация
echo "1. Авторизация...\n";
$api->auth($login, $password);
echo "OK\n\n";

// 2. Получаем список заявок за последние 3 дня
echo "2. Получаем заявки...\n";
$date = date('d.m.Y H:i:s', strtotime('-3 days'));
$tasks = $api->getTasks($date);
$count = count($tasks);
echo "Найдено: $count\n\n";

// Если заявок меньше двух - завершаемся без ошибки
if ($count < 2) {
    echo "Недостаточно заявок для выполнения тестового сценария\n";
    exit(0);
}

// 3. Берем вторую заявку (индекс 1)
$task = $tasks[1];
$taskId = $task['taskId'] ?? $task['id'];
echo "3. Вторая заявка: $taskId\n\n";

// 4. Получаем детальную информацию по заявке
echo "4. Детали заявки...\n";
$details = $api->getTask($taskId);

// Определяем название статуса (может быть массивом или строкой)
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

// 5. Загружаем два изображения на сервер
echo "5. Загружаем изображения...\n";
$imagesDir = __DIR__ . '/images';

// Создаем папку для картинок если её нет
if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);

// Ищем картинки в папке
$images = glob($imagesDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Если картинок нет - создаем тестовые
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

// Загружаем первые две картинки
$guids = [];
foreach (array_slice($images, 0, 2) as $file) {
    echo basename($file) . "... ";
    $guids[] = $api->upload($file);
    echo "OK\n";
}

// 6. Прикрепляем загруженные картинки к заявке
echo "\n6. Прикрепляем файлы...\n";
$api->attach($taskId, $guids);
echo "OK\n";

// 7. Добавляем публичный комментарий
echo "\n7. Добавляем комментарий...\n";
$comment = "Тестовый комментарий от кандидата: $fio, " . date('d.m.Y H:i:s');
$api->comment($taskId, $comment);
echo "OK: $comment\n";

// 8. Выход из системы
echo "\n8. Выход...\n";
$api->logout();
echo "OK\n\n";

echo "=== ГОТОВО ===\n";
