<?php

date_default_timezone_set('Asia/Phnom_Penh');
$ENABLE_LOGS     = false; // Set to false to disable logs
$BAKONG_BASE_URL = 'https://api-bakong.nbc.gov.kh';
$EMAIL = 'lichheanpk@gmail.com';

$TOKEN_FILE = __DIR__ . '/storage/token.json';
$LOG_FILE   = __DIR__ . '/logs/renew-token.log';

function logMsg(string $msg)
{
    global $LOG_FILE, $ENABLE_LOGS;
    if (!$ENABLE_LOGS) return;
    
    file_put_contents(
        $LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

logMsg('--- Token renewal started ---');

$payload = json_encode([
    'email' => $EMAIL
]);

$ch = curl_init($BAKONG_BASE_URL . '/v1/renew_token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    logMsg('CURL ERROR: ' . curl_error($ch));
    curl_close($ch);
    echo "❌ CURL error\n";
    exit(1);
}

curl_close($ch);

logMsg('HTTP STATUS: ' . $httpCode);
logMsg('RAW RESPONSE: ' . $response);

$data = json_decode($response, true);

if (
    $httpCode !== 200 ||
    !isset($data['responseCode']) ||
    $data['responseCode'] !== 0 ||
    empty($data['data']['token'])
) {
    logMsg('❌ Token renewal failed (invalid response)');
    echo "❌ Token renewal failed\n";
    exit(1);
}

file_put_contents($TOKEN_FILE, json_encode([
    'token' => $data['data']['token'],
    'updated_at' => date('c')
], JSON_PRETTY_PRINT));

logMsg('✅ Token renewed successfully');
echo "✅ Token renewed successfully\n";
