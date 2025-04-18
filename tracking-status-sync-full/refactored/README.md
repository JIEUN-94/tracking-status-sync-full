# 📦 PHP 기반 배송 상태 수집 모듈 (Vanilla PHP 리팩토링 버전)

이 프로젝트는 프레임워크 없이 순수 PHP로 구성된 배송 상태 수집 모듈입니다.  
기존 단일 파일 배치 스크립트를 **PHP 8.3 스타일로 구조화**한 리팩토링 예제입니다.

---

## ✅ 구조 설명

- PSR 유사 구조
- 각 택배사별 클래스로 분리 (`App\Couriers\`)
- `CourierTrackerInterface` 로 인터페이스 기반 설계
- `public/run.php` 를 통해 Web 또는 CLI 실행 가능

---

## 📂 폴더 구조

```
tracking-status-sync-refactored-full/
├── app/
│   ├── Interfaces/
│   │   └── CourierTrackerInterface.php
│   └── Couriers/
│       └── CJTracker.php
├── public/
│   └── run.php
└── README.md
```

---

## 🚀 실행 방법

```bash
php public/run.php
```

> 추후 `DHLTracker`, `AftershipTracker` 등 추가 가능
