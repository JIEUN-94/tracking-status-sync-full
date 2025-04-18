<?php
require_once __DIR__ . '/../app/Interfaces/CourierTrackerInterface.php';
require_once __DIR__ . '/../app/Couriers/CJTracker.php';

use App\Couriers\CJTracker;

function runTracking(string $hawb, string $courier): array {
    $map = [
        'CJ' => new CJTracker(),
    ];

    $tracker = $map[$courier] ?? null;

    if (!$tracker) {
        return ['error' => 'Unknown courier'];
    }

    return $tracker->track($hawb);
}

// 예시 실행
$hawb = 'EFS1234567890';
$courier = 'CJ';

$result = runTracking($hawb, $courier);
print_r($result);
