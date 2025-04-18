<?php
// 기본 autoload 없이 직접 include 방식 사용
require_once __DIR__ . '/../app/Interfaces/CourierTrackerInterface.php';
require_once __DIR__ . '/../app/Couriers/OriginalTrackingCode_BACKUP.php';

// 여기에 새로운 리팩토링된 Dispatcher/Runner 추가 예정
echo "🚀 배송 추적 모듈 실행 준비됨 (리팩토링 버전)";
