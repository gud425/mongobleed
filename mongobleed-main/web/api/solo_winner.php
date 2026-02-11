<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/lib/safe.php';

/**
 * MongoDB Wire Protocol 형식 검증 (opCode만 체크)
 */
function validate_wire_protocol(string $payload) {
    // 헤더 파싱을 위한 최소 크기
    if (strlen($payload) < 16) {
        return 'too short to parse header (minimum 16 bytes)';
    }
    
    // 헤더 파싱 (little-endian)
    $header = unpack('VmsgLen/VrequestId/VresponseTo/VopCode', substr($payload, 0, 16));
    $opCode = $header['opCode'];
    
    // 유효한 opCode 목록
    $validOpCodes = [
        1      => 'OP_REPLY',
        2004   => 'OP_QUERY',
        2005   => 'OP_GET_MORE',
        2006   => 'OP_DELETE',
        2007   => 'OP_KILL_CURSORS',
        2010   => 'OP_INSERT',
        2011   => 'OP_UPDATE',
        2012   => 'OP_COMPRESSED',
        2013   => 'OP_MSG',
    ];
    
    if (!isset($validOpCodes[$opCode])) {
        return sprintf('invalid opCode: %d (valid: %s)', $opCode, implode(', ', array_keys($validOpCodes)));
    }
    
    return true;
}

function resolve_wire_mode(array $data): int {
    $candidatesJson = ['wireMode', 'wiremode', 'wm'];
    foreach ($candidatesJson as $k) {
        if (isset($data[$k]) && (is_scalar($data[$k]) || is_int($data[$k]))) {
            return ((int)$data[$k] === 1) ? 1 : 0;
        }
    }
    $candidatesGet = ['wireMode', 'wiremode', 'wm'];
    foreach ($candidatesGet as $k) {
        if (isset($_GET[$k])) {
            return ((int)$_GET[$k] === 1) ? 1 : 0;
        }
    }
    return 0;
}

// GET 파라미터로 wireMode 힌트 확인
$wireModeHint = isset($_GET['wireMode']) || isset($_GET['wiremode']) || isset($_GET['wm']);
$jsonMaxBytes = $wireModeHint ? 2_000_000 : 10_000;

// JSON 요청 파싱
$data = read_json_body($jsonMaxBytes);

// wireMode 최종 결정 (GET + JSON 본문 모두 확인)
$wireMode = resolve_wire_mode($data ?: []);

// ★★★ wireMode=1이면 바로 Wire Protocol 처리로 점프 ★★★
if ($wireMode === 1) {
    // payload_b64 필수
    if (!$data || !isset($data['payload_b64']) || !is_scalar($data['payload_b64'])) {
        echo json_encode(['ok' => false, 'mode' => 'wire', 'error' => 'payload_b64 required']);
        exit;
    }
    
    // 1) Base64 디코딩 (암호문)
    $ct = base64_decode((string)$data['payload_b64'], true);
    if ($ct === false) {
        echo json_encode(['ok' => false, 'mode' => 'wire', 'error' => 'base64 decode failed']);
        exit;
    }
    
    // 2) AES-256-CBC 복호화 → BSON 바이너리
    $passphrase = getenv('AES_PASSPHRASE') ?: 'fiveHongNam!!';
    $salt = 'SALT_FOR_PBKDF2!!';
    $iv = 'IV_FOR_CBC_16B!!';
    $iter = 100000;
    $key = hash_pbkdf2('sha256', $passphrase, $salt, $iter, 32, true);
    
    $payload = @openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($payload === false) {
        echo json_encode(['ok' => false, 'mode' => 'wire', 'error' => 'decrypt failed']);
        exit;
    }
    
    // 3) Wire Protocol 형식 검증
    $wireValidation = validate_wire_protocol($payload);
    if ($wireValidation !== true) {
        echo json_encode([
            'ok' => false, 
            'mode' => 'wire', 
            'error' => 'invalid wire protocol format',
            'detail' => $wireValidation,
            'hint' => 'MongoDB Wire Protocol'
        ]);
        exit;
    }
    
    // 로깅
    $name = ($data && isset($data['name']) && is_scalar($data['name'])) ? sanitize_name((string)$data['name']) : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $line = sprintf("[%s] name=%s ip=%s wireMode=1 payload_len=%d\n", date('c'), $name, $ip, strlen($payload));
    @file_put_contents(__DIR__ . '/../solo_winners.log', $line, FILE_APPEND);
    
    // 4) TCP 전송
    $timeoutMs = 2000;
    $targetHost = 'mongobleed-target';
    $targetPort = 27017;
    $addr = "tcp://{$targetHost}:{$targetPort}";

    $errno = 0; $errstr = '';
    $socket = @stream_socket_client($addr, $errno, $errstr, $timeoutMs / 1000.0, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        echo json_encode(['ok' => false, 'mode' => 'wire', 'error' => 'connect failed', 'detail' => $errstr]);
        exit;
    }
    stream_set_timeout($socket, (int)floor($timeoutMs / 1000), ($timeoutMs % 1000) * 1000);

    // 전송
    $written = 0;
    $len = strlen($payload);
    while ($written < $len) {
        $n = fwrite($socket, substr($payload, $written));
        if ($n === false || $n === 0) break;
        $written += $n;
    }

    // 응답 수신 (단일 메시지)
    stream_set_blocking($socket, true);
    $responsesAll = '';
    $hdr = @fread($socket, 4);
    if ($hdr && strlen($hdr) === 4) {
        $lenArr = @unpack('Vlen', $hdr);
        $msgLen = $lenArr['len'] ?? 0;
        if ($msgLen >= 16 && $msgLen <= 2 * 1024 * 1024) {
            $body = '';
            $need = $msgLen - 4;
            while (strlen($body) < $need) {
                $chunk = @fread($socket, min(8192, $need - strlen($body)));
                if ($chunk === '' || $chunk === false) break;
                $body .= $chunk;
            }
            $responsesAll = $hdr . $body;
        }
    }
    @fclose($socket);

    echo json_encode([
        'ok' => true,
        'mode' => 'wire',
        'written' => $written,
        'data' => base64_encode($responsesAll),
    ]);
    exit;
}

// ========== 이하 wireMode=0 (기존 드라이버 경로) ==========

$name = '';
$ts = date('c');
$ua = '';
$hadEnc = false;

// 1) 단일 payload_b64 (고정 salt/iv, AES-256-CBC)
if ($data && isset($data['payload_b64']) && is_scalar($data['payload_b64'])) {
    $ctB64 = (string)$data['payload_b64'];
    $ct = base64_decode($ctB64, true);
    if ($ct === false) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $passphrase = getenv('AES_PASSPHRASE') ?: 'fiveHongNam!!';
    $salt = 'SALT_FOR_PBKDF2!!';
    $iv = 'IV_FOR_CBC_16B!!';
    $iter = 100000;
    $key = hash_pbkdf2('sha256', $passphrase, $salt, $iter, 32, true);
    $plain = @openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $decoded = json_decode($plain, true);
    if (!is_array($decoded)) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $data = $decoded;
    $hadEnc = true;
}

// 2) 기존 enc 객체 (GCM/CBC 지원)
if (!$hadEnc && $data && isset($data['enc']) && is_array($data['enc'])) {
    $enc = $data['enc'];
    $alg = isset($enc['alg']) && is_scalar($enc['alg']) ? (string)$enc['alg'] : 'AES-256-GCM';
    $ivB64 = isset($enc['iv_b64']) && is_scalar($enc['iv_b64']) ? (string)$enc['iv_b64'] : '';
    $ctB64 = isset($enc['ct_b64']) && is_scalar($enc['ct_b64']) ? (string)$enc['ct_b64'] : '';
    $saltB64 = isset($enc['salt_b64']) && is_scalar($enc['salt_b64']) ? (string)$enc['salt_b64'] : '';
    $iter = isset($enc['iter']) && is_scalar($enc['iter']) ? (int)$enc['iter'] : 100000;
    $iv = base64_decode($ivB64, true);
    $ct = base64_decode($ctB64, true);
    $salt = base64_decode($saltB64, true);
    if ($iv === false || $ct === false || $salt === false) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $passphrase = getenv('AES_PASSPHRASE') ?: 'fiveHongNam!!';
    $key = hash_pbkdf2('sha256', $passphrase, $salt, $iter, 32, true);
    if (strcasecmp($alg, 'AES-256-CBC') === 0) {
        $plain = @openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    } else {
        $tagB64 = isset($enc['tag_b64']) && is_scalar($enc['tag_b64']) ? (string)$enc['tag_b64'] : '';
        $tag = base64_decode($tagB64, true);
        if ($tag === false) {
            echo json_encode(['ok' => false, 'error' => 'wrong input']);
            exit;
        }
        $plain = @openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '');
    }
    if ($plain === false) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $decoded = json_decode($plain, true);
    if (!is_array($decoded)) {
        echo json_encode(['ok' => false, 'error' => 'wrong input']);
        exit;
    }
    $data = $decoded;
    $hadEnc = true;
}

// 암호문 미제공 거부
if (!$hadEnc) {
    echo json_encode(['ok' => false, 'error' => 'wrong input']);
    exit;
}

// 필드 추출
if ($data && isset($data['name']) && is_scalar($data['name'])) {
    $name = sanitize_name((string)$data['name']);
}
$ts = ($data && isset($data['timestamp']) && is_scalar($data['timestamp'])) ? (string)$data['timestamp'] : $ts;
$ua = ($data && isset($data['userAgent']) && is_scalar($data['userAgent'])) ? substr((string)$data['userAgent'], 0, 300) : $ua;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$line = sprintf("[%s] name=%s ip=%s ua=%s wireMode=0\n", $ts, $name, $ip, $ua);
@file_put_contents(__DIR__ . '/../solo_winners.log', $line, FILE_APPEND);

// MongoDB Upsert
$ok = true;
$wins = null;
try {
    if (!class_exists('MongoDB\Driver\Manager')) {
        throw new RuntimeException('MongoDB driver not loaded');
    }
    if ($name === '' || mb_strlen($name, 'UTF-8') < 1) {
        throw new InvalidArgumentException('invalid name');
    }
    $uri = getenv('MONGODB_URI') ?: 'mongodb://gud425:PwForGud425!!%40%40@mongobleed-target:27017/secretdb?authSource=admin';
    $db = getenv('MONGODB_DB') ?: 'secretdb';
    $col = getenv('MONGODB_COLLECTION') ?: 'rankings';
    $manager = new MongoDB\Driver\Manager($uri);

    $bulk = new MongoDB\Driver\BulkWrite(['ordered' => true]);
    $lastWinAt = new MongoDB\BSON\UTCDateTime((new DateTime($ts))->getTimestamp() * 1000);
    $bulk->update(
        ['name' => $name],
        ['$inc' => ['wins' => 1], '$set' => ['lastWinAt' => $lastWinAt]],
        ['upsert' => true]
    );
    $wc = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
    $manager->executeBulkWrite($db . '.' . $col, $bulk, ['writeConcern' => $wc]);

    $query = new MongoDB\Driver\Query(['name' => $name], ['limit' => 1, 'projection' => ['wins' => 1]]);
    $cursor = $manager->executeQuery($db . '.' . $col, $query);
    foreach ($cursor as $doc) {
        if (isset($doc->wins)) {
            $wins = (int)$doc->wins;
        }
    }
} catch (Throwable $e) {
    error_log('[solo_winner][mongo_error] ' . $e->getMessage());
    $ok = false;
}

echo json_encode(['ok' => $ok, 'wins' => $wins]);