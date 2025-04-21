<?php
function callTiki($track_no, $conf) {
    $ch = curl_init($conf['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-access-token: ' . $conf['token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['tracking_number' => $track_no]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        logError("TIKI: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

function callSkynet($track_no, $conf) {
    $url = $conf['url'] . '?tracking_number=' . urlencode($track_no);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $conf['token']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        logError("SKYNET: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

function callAfterShip($track_no, $conf) {
    $url = $conf['url'] . '/v4/trackings/' . $conf['slug'] . '/' . $track_no;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'aftership-api-key: ' . $conf['key']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        logError("AFTERSHIP: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

// Response parsers
function parseTikiResponse($data) {
    return ['status' => $data['status'] ?? 'unknown'];
}
function parseSkynetResponse($data) {
    return ['status' => $data['Description'] ?? 'unknown'];
}
function parseAfterShipResponse($data) {
    return ['status' => $data['data']['tracking']['tag'] ?? 'unknown'];
}

// Log errors to file
function logError($msg) {
    $logFile = __DIR__ . '/logs/error_log.txt';
    file_put_contents($logFile, "[" . date('c') . "] " . $msg . PHP_EOL, FILE_APPEND);
}
?>
