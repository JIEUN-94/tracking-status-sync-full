# 📦 Tracking Status Sync (포트폴리오 최종 완성형)

단일 실행 파일로 다양한 택배사의 배송 상태를 추적할 수 있는 구조이며,  
유지보수성과 확장성을 극대화한 실전형 PHP 8.3 포트폴리오입니다.

---

## ✅ 실행 예시

```bash
php public/run.php CJ EFS1234567890
```

## ⚙ 구조

- `app/Interfaces/` : 공통 인터페이스
- `app/Core/` : Dispatcher, Logger, Config 포함
- `app/Couriers/` : 각 택배사별 추적 클래스
- `public/run.php` : 실행용 단일 진입점
- `config/.env.php` : API 키 등 민감 정보 분리
- `logs/` : 로그 자동 생성

---

## 🛠 특징

- Dispatcher 기반 클래스 자동 매핑
- try/catch + 로그 기록 (실패 추적 가능)
- 택배사 코드 대소문자 무시
- 설정 파일 `.env.php`에서 API 키 불러옴

---

> ✨ 실무 적용 + 포트폴리오 제출용으로 완벽한 구조입니다.
