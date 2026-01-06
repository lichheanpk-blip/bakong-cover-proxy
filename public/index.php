<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// ================= CONFIG =================
date_default_timezone_set('Asia/Phnom_Penh');
$ENABLE_LOGS     = false; // Set to false to disable logs
$BAKONG_BASE_URL = 'https://api-bakong.nbc.gov.kh';
$TOKEN_FILE = __DIR__ . '/../storage/token.json';

// ================= LOAD TOKEN =================
function getBakongToken(string $file): string
{
    if (!file_exists($file)) {
        return '';
    }

    $data = json_decode(file_get_contents($file), true);
    return $data['token'] ?? '';
}

// ================= CORS =================
$app->add(function (Request $request, $handler) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// ================= ROOT =================
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'status'  => 'ok',
        'message' => 'Bakong OpenAPI Auto Gateway',
        'example' => '/v1/check_transaction_by_md5'
    ], JSON_PRETTY_PRINT));

    return $response->withHeader('Content-Type', 'application/json');
});

// ================= CRON RENEW TOKEN =================
$app->get('/bakong/renew/cron', function (Request $request, Response $response) use ($BAKONG_BASE_URL, $TOKEN_FILE, $ENABLE_LOGS) {
    $params = $request->getQueryParams();
    $cronKey = ''; // HARDCODED KEY

    if (!isset($params['key']) || $params['key'] !== $cronKey) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Invalid or missing cron key'
        ]));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    // Renewal Logic
    $email = 'user@gmail.com'; // Hardcoded email per request
    $payload = json_encode(['email' => $email]);

    $ch = curl_init($BAKONG_BASE_URL . '/v1/renew_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Curl error during renewal',
            'error' => $curlError
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $data = json_decode($result, true);

    if (
        $httpCode !== 200 || 
        !isset($data['responseCode']) || 
        $data['responseCode'] !== 0 || 
        empty($data['data']['token'])
    ) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Failed to renew token from Bakong',
            'bakong_response' => $data
        ]));
        return $response->withStatus(502)->withHeader('Content-Type', 'application/json');
    }

    // Save Token
    file_put_contents($TOKEN_FILE, json_encode([
        'token' => $data['data']['token'],
        'updated_at' => date('c')
    ], JSON_PRETTY_PRINT));

    // Log the event
    if ($ENABLE_LOGS) {
        $logFile = __DIR__ . '/../logs/renew-token.log';
        $logEntry = '[' . date('Y-m-d H:i:s') . '] CRON RENEWAL SUCCESS' . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    $response->getBody()->write(json_encode([
        'status' => 'success',
        'message' => 'Token renewed successfully',
        'renewed_at' => date('c')
    ]));

    return $response->withHeader('Content-Type', 'application/json');
});

// ================= PROXY ALL ROUTES =================
$app->map(['GET','POST','PUT','PATCH','DELETE'], '/{path:.*}', function (
    Request $request,
    Response $response,
    array $args
) use ($BAKONG_BASE_URL, $TOKEN_FILE, $ENABLE_LOGS) {
    
    // Helper for logging
    $logFile = __DIR__ . '/../logs/proxy.log';
    $log = function($msg, $data = null) use ($logFile, $ENABLE_LOGS) {
        if (!$ENABLE_LOGS) return;

        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        if ($data !== null) {
            $entry .= is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);
            $entry .= PHP_EOL;
        }
        $entry .= str_repeat('-', 20) . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    };

    $accessToken = getBakongToken($TOKEN_FILE);

    if (empty($accessToken)) {
        $log('❌ Access token missing');
        $response->getBody()->write(json_encode([
            'responseCode' => 1,
            'responseMessage' => 'Access token not available. Please renew token.'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $method = $request->getMethod();
    $path   = '/' . $args['path'];
    $bakongUrl = $BAKONG_BASE_URL . $path;

    // Body
    $body = (string) $request->getBody();
    if (empty($body)) {
        $body = json_encode($request->getParsedBody() ?? $request->getQueryParams());
    }

    // Headers
    $headers = ['Content-Type: application/json'];
    if (!$request->hasHeader('Authorization')) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    // Log Request
    $log("➡️ OUTGOING REQUEST: $method $bakongUrl", [
        'headers' => $headers,
        'body' => $body
    ]);

    // CURL
    $ch = curl_init($bakongUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => in_array($method, ['POST','PUT','PATCH']) ? $body : null,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        $log("❌ CURL ERROR", $error);
        curl_close($ch);
        $response->getBody()->write(json_encode([
            'responseCode' => 1,
            'responseMessage' => 'Gateway Error',
            'error' => $error
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    curl_close($ch);

    // Log Response
    $log("⬅️ BAKONG RESPONSE ($status)", $result);

    // Check if Bakong returned HTML (likely a 404 page) instead of JSON
    if (strpos($result, '<!DOCTYPE html>') !== false || strpos($result, '<html') !== false) {
        $log("⚠️ HTML DETECTED - Converting to JSON 404");
        $response->getBody()->write(json_encode([
            'responseCode' => 1,
            'responseMessage' => 'Not Found',
            'errorCode' => 404,
            'data' => null
        ]));
        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($result);
    return $response
        ->withStatus($status)
        ->withHeader('Content-Type', 'application/json');
});

$app->run();

