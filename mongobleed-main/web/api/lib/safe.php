<?php
// 공용 입력 필터/검증 유틸 (NoSQL 인젝션 방어 중심)

// 서버 식별 최소화: PHP가 노출하는 X-Powered-By 제거
@header_remove('X-Powered-By');
@header('X-Powered-By:');

/**
 * 허용 문자만 남기는 이름 정규화
 * - 허용: 영문/숫자/한글/공백/언더스코어/하이픈
 * - 길이: 1~20
 */
function sanitize_name(string $name): string {
    // normalize whitespace
    $name = trim($name);
    // whitelist filter
    $name = preg_replace('/[^\p{Hangul}a-zA-Z0-9 _-]/u', '', $name);
    // collapse multiple spaces
    $name = preg_replace('/\s{2,}/u', ' ', $name);
    // length limit
    if (mb_strlen($name, 'UTF-8') > 20) {
        $name = mb_substr($name, 0, 20, 'UTF-8');
    }
    return $name;
}

/**
 * GET 정수 파라미터 안전 파싱
 */
function get_int_param(string $key, int $default, int $min, int $max): int {
    $raw = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    if ($raw === false || $raw === null) return $default;
    if ($raw < $min) return $min;
    if ($raw > $max) return $max;
    return $raw;
}

/**
 * 요청 본문 JSON 읽기 (작은 요청만 허용)
 */
function read_json_body(int $maxBytes = 10_000): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') === false) {
        return [];
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || $raw === null) return [];
    if (strlen($raw) > $maxBytes) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    return $data;
}


