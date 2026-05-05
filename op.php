<?php
// proxy_license.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

// Config
$auth = '3zttGFiAFI7BdpGAa33RWh8njvEySl3N';
$subscriberId = '1293097877';

$api = "https://tb.tapi.videoready.tv/content-detail/api/partner/cdn/player/details/chotiluli/" . rawurlencode($id);

// CURL INIT
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,

    // Headers
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $auth",
        "subscriberId: $subscriberId",
        "Accept: application/json"
    ],

    // टाइमआउट वाढवले
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,

    // SSL fix (test साठी)
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,

    // User agent add
    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",

    // HTTP version force
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
]);

$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// Error handling
if ($error) {
    http_response_code(502);
    echo json_encode([
        'error' => 'curl_failed',
        'details' => $error
    ]);
    exit;
}

if (!$res || $http_code !== 200) {
    http_response_code(502);
    echo json_encode([
        'error' => 'failed_fetch_api',
        'http_code' => $http_code,
        'response' => $res
    ]);
    exit;
}

// JSON decode
$data = json_decode($res, true);

if (!$data) {
    http_response_code(502);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// Extract values
$license = $data['data']['dashWidewineLicenseUrl'] ?? null;
$play    = $data['data']['dashWidewinePlayUrl'] ?? null;

// Output
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'license_encrypted' => $license,
    'play_encrypted' => $play,
    'raw_response' => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);