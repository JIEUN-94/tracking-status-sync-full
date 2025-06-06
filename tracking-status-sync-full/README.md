# 📦 글로벌 배송사 연동 상태 추적 시스템 (PHP 리팩토링 프로젝트)

이 프로젝트는 실제 운영 중인 배송 추적 시스템의 핵심 로직을  
**PHP 8.3 환경에 맞춰 리팩토링한 결과물**입니다.  
GitHub에는 전체 레거시 코드 중 일부만 선별하여,  
**가독성과 구조 이해에 초점을 맞춰 정리**하였습니다.

---

## 🌐 프로젝트 개요

본 시스템은 세계 각국의 배송사 API와 연동하여  
**송장 번호 기반으로 실시간 배송 상태를 수집**하고,  
이를 사내에 정의된 **고유 배송 상태 코드 체계와 매칭하여 DB에 자동 저장**하는 구조입니다.

- 연동 대상에는 **DHL, FedEx, 일본 우편, 우체국, 말레이시아 로컬사, 동남아시아 전역**까지 포함되며,  
  **국제/국내 대부분의 주요 배송사 API에 대응할 수 있도록 설계**되었습니다.
- 해당 PHP 모듈은 **서버 예약작업(Cron 등)**을 통해 자동 실행되며,  
  대량 송장 처리를 안정적으로 수행할 수 있도록 구현되어 있습니다.

---

## 🧠 리팩토링 포인트

- 배송사별 인증 방식/응답 구조에 따라 **로직을 유연하게 분리**
- **공통 로직 함수화** (`curl` 호출, 응답 파싱, 에러 로깅 등)
- **config.php 분리**로 API 키 및 URL 별도 관리 (보안성 강화)
- `filter_input()` 기반의 **입력값 정제 처리 적용**
- 에러 발생 시 자동으로 `logs/error_log.txt`에 **에러 내역 기록**

---

## 📁 디렉토리 구성

📦 프로젝트 루트
├── README.md
├── legacy/
│   └── tracking_update.php
└── refactored/
    ├── tracking_update.php
    ├── config.php
    ├── helpers.php
    └── logs/
        └── error_log.txt

---

## ✅ 참고 사항

- 이 리팩토링은 **전체 배송사 로직 중 구조적으로 다른 API 유형 세 가지만** 선별하여 정리한 버전입니다.
- 실제 운영 환경에서는 수십 개 이상의 배송사에 대응되며,  
  각 배송사 별로 커스터마이징된 응답 처리와 상태 매핑 로직이 구현되어 있습니다.
