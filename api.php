<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/db/db.php';

ks_guard_request('api', 40, 10, 30);

if (php_sapi_name() !== 'cli') {
    $providedToken = $_SERVER['HTTP_X_KS_TOKEN'] ?? ($_GET['_sig'] ?? '');
    if (!ks_verify_api_token((string) $providedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$action = $_GET['action'] ?? 'check';
$clientVersion = $_GET['version'] ?? '';

$currentVersion = get_version_hash();
$nextToken = ks_api_token();

if ($action === 'version') {
    echo json_encode([
        'version' => $currentVersion,
        'token' => $nextToken
    ]);
    exit;
}

$payload = [
    'version' => $currentVersion,
    'token' => $nextToken,
    'settings' => get_all_settings(),
    'bgm_playlist' => get_bgm_playlist(),
    'slides' => get_slides(true)
];

if ($action === 'data') {
    echo json_encode($payload);
    exit;
}

if (!empty($clientVersion) && $clientVersion === $currentVersion) {
    echo json_encode([
        'changed' => false,
        'version' => $currentVersion,
        'token' => $nextToken
    ]);
} else {
    $payload['changed'] = true;
    echo json_encode($payload);
}
