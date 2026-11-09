<?php
declare(strict_types=1);

/**
 * Firebase Cloud Messaging HTTP v1 sender.
 * Set ELZOCO_FIREBASE_SERVICE_ACCOUNT_PATH to a service-account JSON file
 * stored outside the public web root. Never commit that JSON file.
 */

function push_b64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function push_firebase_credentials(): ?array
{
    static $credentials = false;
    if ($credentials !== false) {
        return $credentials;
    }

    $path = trim((string)(getenv('ELZOCO_FIREBASE_SERVICE_ACCOUNT_PATH') ?: ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        $credentials = null;
        return null;
    }

    $real = realpath($path);
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
    if ($real === false || ($root && str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
        error_log('[firebase push] Service account file must be outside DOCUMENT_ROOT.');
        $credentials = null;
        return null;
    }

    $data = json_decode((string)file_get_contents($real), true);
    if (!is_array($data)
        || empty($data['project_id'])
        || empty($data['client_email'])
        || empty($data['private_key'])) {
        error_log('[firebase push] Service account JSON is incomplete.');
        $credentials = null;
        return null;
    }

    $credentials = $data;
    return $credentials;
}

function push_firebase_access_token(): ?string
{
    static $cached = null;
    if (is_array($cached) && ($cached['expires'] ?? 0) > time() + 60) {
        return $cached['token'];
    }

    $credentials = push_firebase_credentials();
    if (!$credentials || !function_exists('curl_init') || !function_exists('openssl_sign')) {
        return null;
    }

    $now = time();
    $header = push_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $claim = push_b64url(json_encode([
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ], JSON_UNESCAPED_SLASHES));

    $unsigned = $header . '.' . $claim;
    $privateKey = openssl_pkey_get_private((string)$credentials['private_key']);
    if (!$privateKey || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('[firebase push] Could not sign service-account assertion.');
        return null;
    }
    $assertion = $unsigned . '.' . push_b64url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $response = is_string($raw) ? json_decode($raw, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($response) || empty($response['access_token'])) {
        error_log('[firebase push] OAuth token request failed: HTTP ' . $status . ' ' . $error);
        return null;
    }

    $cached = [
        'token' => (string)$response['access_token'],
        'expires' => $now + (int)($response['expires_in'] ?? 3600),
    ];
    return $cached['token'];
}

/** Send one FCM HTTP v1 notification to a set of registered device tokens. */
function push_send_rows(array $rows, string $title, string $body, string $kind = 'announcement', string $tone = 'info'): int
{
    if (!$rows) {
        return 0;
    }

    $credentials = push_firebase_credentials();
    $accessToken = push_firebase_access_token();
    if (!$credentials || !$accessToken || !function_exists('curl_multi_init')) {
        return 0;
    }

    $project = rawurlencode((string)$credentials['project_id']);
    $endpoint = 'https://fcm.googleapis.com/v1/projects/' . $project . '/messages:send';
    $sent = 0;

    foreach (array_chunk($rows, 30) as $chunk) {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($chunk as $row) {
            $payload = [
                'message' => [
                    'token' => (string)$row['token_value'],
                    'notification' => [
                        'title' => mb_substr($title, 0, 180),
                        'body' => mb_substr($body, 0, 450),
                    ],
                    'data' => [
                        'kind' => (string)$kind,
                        'tone' => (string)$tone,
                        'source' => 'elzoco_hrm',
                    ],
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => ['channel_id' => 'elzoco_alerts', 'sound' => 'default'],
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json; charset=utf-8',
                ],
            ]);
            $handles[(int)$row['id']] = ['handle' => $ch, 'row' => $row];
            curl_multi_add_handle($multi, $ch);
        }

        do {
            $status = curl_multi_exec($multi, $active);
            if ($active && $status === CURLM_OK) {
                curl_multi_select($multi, 1.0);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $item) {
            $ch = $item['handle'];
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $raw = curl_multi_getcontent($ch);
            $response = is_string($raw) ? json_decode($raw, true) : null;

            if ($http >= 200 && $http < 300) {
                $sent++;
            } elseif ($http === 404 || (is_array($response) && str_contains((string)($response['error']['details'][0]['errorCode'] ?? ''), 'UNREGISTERED'))) {
                q('UPDATE mobile_push_tokens SET revoked_at=NOW(),updated_at=NOW() WHERE id=?', [(int)$item['row']['id']]);
            } elseif ($http > 0) {
                error_log('[firebase push] FCM send failed: HTTP ' . $http . ' for device row ' . (int)$item['row']['id']);
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
    }

    return $sent;
}

function push_send_recipient(string $recipient, string $title, string $body, string $kind = 'announcement', string $tone = 'info'): int
{
    if (!preg_match('/^(emp|admin):(\\d+)$/', $recipient, $match)) {
        return 0;
    }
    $type = $match[1] === 'emp' ? 'employee' : 'admin';
    $rows = all(
        'SELECT id,token_value FROM mobile_push_tokens
         WHERE user_type=? AND user_id=? AND revoked_at IS NULL',
        [$type, (int)$match[2]]
    );
    return push_send_rows($rows, $title, $body, $kind, $tone);
}

function push_send_employees(array $employeeIds, string $title, string $body, string $kind = 'announcement', string $tone = 'info'): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $employeeIds), static fn($id) => $id > 0)));
    if (!$ids) {
        return 0;
    }
    $rows = all(
        'SELECT p.id,p.token_value
         FROM mobile_push_tokens p
         INNER JOIN employees e ON e.id=p.user_id
         WHERE p.user_type=\'employee\' AND p.revoked_at IS NULL
           AND e.in_latest=1 AND e.inactive=0
           AND e.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        $ids
    );
    return push_send_rows($rows, $title, $body, $kind, $tone);
}
