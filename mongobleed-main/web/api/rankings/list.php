<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../lib/safe.php';

$page = get_int_param('page', 1, 1, 1000000);
$pageSize = get_int_param('pageSize', 10, 1, 100);
$skip = ($page - 1) * $pageSize;

$uri = getenv('MONGODB_URI') ?: 'mongodb://gud425:PwForGud425!!%40%40@mongobleed-target:27017/secretdb?authSource=admin';
$db = getenv('MONGODB_DB') ?: 'secretdb';
$col = getenv('MONGODB_COLLECTION') ?: 'rankings';

$out = ['ok' => true, 'items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0, 'totalPages' => 1];
try {
    if (!class_exists('MongoDB\Driver\Manager')) {
        throw new RuntimeException('MongoDB driver not loaded');
    }
    $manager = new MongoDB\Driver\Manager($uri);

    // total count
    $countCmd = new MongoDB\Driver\Command(['count' => $col, 'query' => (object)[]]);
    $countCursor = $manager->executeCommand($db, $countCmd);
    $countResult = current($countCursor->toArray());
    $total = isset($countResult->n) ? (int)$countResult->n : 0;
    $out['total'] = $total;
    $out['totalPages'] = max(1, (int)ceil($total / $pageSize));

    // page items with rank
    $pipeline = [
        ['$setWindowFields' => [
            // MongoDB 8.x: $rank는 최상위 sortBy에 정확히 하나의 정렬 키를 요구
            'sortBy' => ['wins' => -1],
            'output' => ['rank' => ['$rank' => (object)[]]]
        ]],
        ['$sort' => ['wins' => -1, 'lastWinAt' => -1]],
        ['$skip' => $skip],
        ['$limit' => $pageSize],
        ['$project' => ['_id' => 0, 'name' => 1, 'wins' => 1, 'lastWinAt' => 1, 'rank' => 1]]
    ];
    $cmd = new MongoDB\Driver\Command([
        'aggregate' => $col,
        'pipeline' => $pipeline,
        'cursor' => new stdClass()
    ]);
    $cursor = $manager->executeCommand($db, $cmd);
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
        $out['items'][] = $item;
    }
} catch (Throwable $e) {
    error_log('[rankings/list][mongo_error] ' . $e->getMessage());
    $out = ['ok' => false, 'items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0, 'totalPages' => 1];
}

echo json_encode($out);



