<?php
require_once __DIR__ . '/../app/Interfaces/CourierTrackerInterface.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/CourierDispatcher.php';

use App\Core\CourierDispatcher;

$courier = $argv[1] ?? '';
$hawb = $argv[2] ?? 'EFS1234567890';

if (!$courier) {
    echo "❗ 사용법: php run.php [택배사 코드] [HAWB번호]\n";
    exit;
}

$dispatcher = new CourierDispatcher();
$result = $dispatcher->dispatch($courier, $hawb);
print_r($result);
