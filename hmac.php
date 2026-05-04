<?php
error_reporting(0);
header("Content-Type: application/json");

/* ================= CONFIG ================= */

$CACHE_TIME = 60; // seconds

$id = $_GET["id"] ?? null;
if (!$id) {
    echo json_encode(["error" => "Missing id"]);
    exit;
}

$cache_file = __DIR__ . "/cache_" . md5($id) . ".json";

/* ================= CACHE ================= */

// return cache instantly
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $CACHE_TIME)) {
    echo file_get_contents($cache_file);
    exit;
}

/* ================= CURL FUNCTION ================= */

function curlGet($url, $headerOnly = false) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => $headerOnly,
        CURLOPT_NOBODY => $headerOnly,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);

    $res = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $res;
}

/* ================= DECRYPT ================= */

function tp_decrypt($encrypted) {
    if (!$encrypted) return null;

    $encrypted = explode("#", $encrypted)[0];
    $decoded = base64_decode($encrypted);

    if (!$decoded) return null;

    return openssl_decrypt(
        $decoded,
        "AES-128-ECB",
        "aesEncryptionKey",
        OPENSSL_RAW_DATA
    );
}

/* ================= FETCH OP ================= */

$op_url = "https://elitebeam.shop/Premium/tplay/op.php?id=" . urlencode($id);

$op_json = curlGet($op_url);

if (!$op_json) {
    echo json_encode(["error" => "Failed to fetch op"]);
    exit;
}

$op = json_decode($op_json, true);

if (!$op) {
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

/* ================= EXTRACT ================= */

// PLAY
$enc_play = $op["play_encrypted"]
    ?? $op["dashWidewinePlayUrl"]
    ?? $op["raw_response"]["data"]["dashWidewinePlayUrl"]
    ?? null;

$mpd = tp_decrypt($enc_play);

// LICENSE
$enc_lic = $op["dashWidewineLicenseUrl"]
    ?? $op["raw_response"]["data"]["dashWidewineLicenseUrl"]
    ?? $op["license_encrypted"]
    ?? null;

$license_url = tp_decrypt($enc_lic);

/* ================= COOKIES ================= */

$hdnea = null;
$hdntl = null;

if ($mpd) {

    // hdnea from URL
    $query = parse_url($mpd, PHP_URL_QUERY);
    parse_str($query, $params);

    if (!empty($params["hdnea"])) {
        $hdnea = "hdnea=" . $params["hdnea"];
    }

    // hdntl from header
    $header = curlGet($mpd, true);

    // fallback if header fail
    if (!$header) {
        $header = curlGet($mpd, false);
    }

    if ($header && preg_match('/hdntl=([^&\r\n]+)/', $header, $m)) {
        $hdntl = "hdntl=" . $m[1];
    }
}

/* ================= EXPIRY ================= */

function expiry($str) {
    if (!$str) return null;

    if (preg_match('/exp=(\d+)/', $str, $x)) {
        return date("d/m/Y h:i A", $x[1]);
    }

    return null;
}

/* ================= OUTPUT ================= */

$out = [
    "Type" => "TP Cookie API FAST",
    "mpd_link" => $mpd,
    "widevine" => $license_url,
    "hmac" => [
        "hdnea" => [
            "value" => $hdnea,
            "expires_at" => expiry($hdnea)
        ],
        "hdntl" => [
            "value" => $hdntl,
            "expires_at" => expiry($hdntl)
        ]
    ],
    "userAgent" =>
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/135.0.0.0 Safari/537.36"
];

/* ================= SAVE CACHE ================= */

file_put_contents($cache_file, json_encode($out));

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);