<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

$line_name = filter_input(INPUT_GET, 'line_name', FILTER_SANITIZE_STRING) ?? '';
$track_no = filter_input(INPUT_GET, 'track_no', FILTER_SANITIZE_STRING) ?? '';

if (!$line_name || !$track_no) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

switch ($line_name) {
    case 'TIKI':
        $raw = callTiki($track_no, $config['TIKI']);
        $response = parseTikiResponse($raw);
        break;
    case 'SKYNET':
        $raw = callSkynet($track_no, $config['SKYNET']);
        $response = parseSkynetResponse($raw);
        break;
    case 'EFSTH':
        $raw = callAfterShip($track_no, $config['EFSTH']);
        $response = parseAfterShipResponse($raw);
        break;
    default:
        $response = ['error' => 'Unsupported carrier'];
}

echo json_encode($response);
?>
