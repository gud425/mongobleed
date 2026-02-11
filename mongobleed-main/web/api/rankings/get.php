<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../lib/safe.php';

$name = isset($_GET['name']) ? (string)$_GET['name'] : '';
$name = sanitize_name($name);
if ($name === '') {
    echo json_encode(['ok' => false]);
    exit;
}

$uri = getenv('MONGODB_URI') ?: 'mongodb://gud425:PwForGud425!!%40%40@mongobleed-target:27017/secretdb?authSource=admin';
$db = getenv('MONGODB_DB') ?: 'secretdb';
$col = getenv('MONGODB_COLLECTION') ?: 'rankings';

try {
    if (!class_exists('MongoDB\Driver\Manager')) {
        throw new RuntimeException('MongoDB driver not loaded');
    }
    $manager = new MongoDB\Driver\Manager($uri);
    $pipeline = [
        ['$setWindowFields' => [
            // MongoDB 8.x: $rank는 최상위 sortBy에 정확히 하나의 정렬 키를 요구
            'sortBy' => ['wins' => -1],
            'output' => ['rank' => ['$rank' => (object)[]]]
        ]],
        ['$match' => ['name' => $name]],
        ['$project' => ['_id' => 0, 'name' => 1, 'wins' => 1, 'lastWinAt' => 1, 'rank' => 1]],
        ['$limit' => 1]
    ];
    $cmd = new MongoDB\Driver\Command([
        'aggregate' => $col,
        'pipeline' => $pipeline,
        'cursor' => new stdClass()
    ]);
    $cursor = $manager->executeCommand($db, $cmd);
    $item = null;
    foreach ($cursor as $doc) {
        $item = [
            'name' => (string)($doc->name ?? ''),
            'wins' => isset($doc->wins) ? (int)$doc->wins : 0,
            'rank' => isset($doc->rank) ? (int)$doc->rank : null,
            'lastWinAt' => null
        ];
        if (isset($doc->lastWinAt) && $doc->lastWinAt instanceof MongoDB\BSON\UTCDateTime) {
            $item['lastWinAt'] = $doc->lastWinAt->toDateTime()->format(DateTime::ATOM);
        }
    }
    if (!$item) {
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode(['ok' => true, 'item' => $item]);
} catch (Throwable $e) {
    error_log('[rankings/get][mongo_error] ' . $e->getMessage());
    echo json_encode(['ok' => false]);
}



