<?php
namespace App\Couriers;

use App\Interfaces\CourierTrackerInterface;

class CJTracker implements CourierTrackerInterface {
    public function track(string $hawb): array {
        // TODO: 실제 CJ API 연동 로직으로 대체
        return [
            'hawb' => $hawb,
            'status' => '배송중',
            'last_update' => date('Y-m-d H:i:s'),
            'courier' => 'CJ대한통운',
        ];
    }
}
