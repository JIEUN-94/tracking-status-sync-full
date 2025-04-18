# 📦 Tracking Status Sync (PHP 8.3 Full Refactor)

이 프로젝트는 실제 운영되던 배송 상태 추적 스크립트(`tracking_update.php`)를  
PHP 8.3 기반으로 구조화하고, 업체별 클래스로 분리하며 리팩토링한 구조입니다.

---

## ✅ 주요 리팩토링 포인트

- PSR-4 유사 구조 적용 (Vanilla PHP 기반)
- `CourierTrackerInterface` 인터페이스 설계
- 각 업체별 추적 클래스 구현 예정 (CJ, DHL, Aftership, 17Track 등)
- 공통 실행자 `run.php` 제공 (CLI / 웹 둘 다 가능)
- API KEY 및 민감 정보는 블러 처리 예정

---

## 📁 구조

```
tracking-status-sync-83-full/
├── app/
│   ├── Interfaces/
│   │   └── CourierTrackerInterface.php
│   └── Couriers/
│       └── OriginalTrackingCode_BACKUP.php  # 원본 전체 코드
├── public/
│   └── run.php
└── README.md
```

---

### ⚙ 향후 계획
- 각 택배사별 `track()` 메서드 구현 (파일 분리)
- 공통 유틸, DB 업데이트 모듈 분리
- `.env` 또는 `config.php` 방식 설정값 적용

