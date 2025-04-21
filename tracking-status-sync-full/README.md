# 배송사 추적 API 리팩토링 (최적화 포함)

## 포함된 배송사
- TIKI
- SKYNET
- EFSTH (AfterShip 기반)

## 주요 최적화 사항
- 응답 파싱 함수 분리 (`parseXResponse()`)
- 에러 로깅 기능 (`logs/error_log.txt`)
- 보안: `filter_input()` + fallback
- 민감 정보 분리 (config.php)

## 폴더 구성
- `/러게시`: 원본 PHP 그대로 정리본
- `/리팩토링`: 리팩토링 및 최적화 적용본
