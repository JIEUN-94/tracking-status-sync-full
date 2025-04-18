<?php
namespace Couriers;

use Interfaces\CourierTrackerInterface;

class CJTracker implements CourierTrackerInterface {
    public function track(string $hawb): array {
        // 실제 CJ API 호출 로직은 이곳에 구현
        return [
            'hawb' => $hawb,
            'status' => 'In Transit',
            'last_update' => date('Y-m-d H:i:s'),
            'courier' => 'CJ',
        ];
    }
}
