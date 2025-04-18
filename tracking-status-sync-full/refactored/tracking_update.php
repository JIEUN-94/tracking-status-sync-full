<?php
require_once __DIR__ . '/interfaces/CourierTrackerInterface.php';
require_once __DIR__ . '/couriers/CJTracker.php';

use Couriers\CJTracker;

function runTracking(string $hawb, string $courier): array {
    $map = [
        'CJ' => new CJTracker(),
        // 다른 택배사도 여기에 추가
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
