<?php
// proxy_license.php
// Usage: proxy_license.php?id=8
ini_set('display_errors', 0);
error_reporting(0);

$id = $_GET['id'] ?? null;
if (!$id) { http_response_code(400); echo 'Missing id'; exit; }

// Config
$auth = '3zttGFiAFI7BdpGAa33RWh8njvEySl3N'; // तुमचा Authorization token
$subscriberId = '1293097877'; // subscriberId
$api = "https://tb.tapi.videoready.tv/content-detail/api/partner/cdn/player/details/chotiluli/".rawurlencode($id);

// Call API
$ch = curl_init($api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $auth",
    "subscriberId: $subscriberId",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if (!$res || $code !== 200) {
    http_response_code(502);
    echo json_encode(['error'=>'failed_fetch_api','http_code'=>$code,'curl_error'=>$err]);
    exit;
}

$data = json_decode($res, true);
if (!$data) {
    http_response_code(502);
    echo json_encode(['error'=>'invalid_json']);
    exit;
}

// pick the encrypted license string(s)
$license = $data['data']['dashWidewineLicenseUrl'] ?? null;
$play   = $data['data']['dashWidewinePlayUrl'] ?? null;

// return to caller as JSON (no decryption)
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'=>'ok',
    'license_encrypted' => $license,
    'play_encrypted' => $play,
    'raw_response' => $data
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);