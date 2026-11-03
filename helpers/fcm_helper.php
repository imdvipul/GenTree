<?php
require_once __DIR__ . "/../config/database.php";

/**
 * Universal Firebase Cloud Messaging (FCM) Push Notification Dispatcher
 *
 * Supports:
 *  1. Google Service Account (HTTP v1 API) via pure PHP OpenSSL JWT signing
 *  2. FCM Legacy Server Key (https://fcm.googleapis.com/fcm/send)
 *
 * Automatically fetches user device tokens from `users.fcm_token` in MySQL.
 */

function getFcmAccessToken(): ?string {
    // 1. Check cached token in sys temp dir
    $cacheFile = sys_get_temp_dir() . '/gentree_fcm_token_' . md5(__DIR__) . '.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['access_token']) && isset($cached['expires_at'])) {
            if ($cached['expires_at'] > time() + 60) {
                return $cached['access_token'];
            }
        }
    }

    // 2. Locate Service Account Credentials
    $sa = null;
    $possibleFiles = [
        __DIR__ . '/../config/service-account.json',
        __DIR__ . '/../config/firebase-service-account.json',
        __DIR__ . '/../../service-account.json',
    ];

    foreach ($possibleFiles as $f) {
        if (file_exists($f)) {
            $sa = json_decode(file_get_contents($f), true);
            if ($sa && isset($sa['private_key']) && isset($sa['client_email'])) {
                break;
            }
        }
    }

    if (!$sa) {
        $envSa = getenv('FIREBASE_SERVICE_ACCOUNT');
        if ($envSa) {
            if (file_exists($envSa)) {
                $sa = json_decode(file_get_contents($envSa), true);
            } else {
                $sa = json_decode($envSa, true);
            }
        }
    }

    if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
        return null;
    }

    // 3. Create RS256 signed JWT assertion
    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600
    ];

    $b64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $b64Claims = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
    $sigInput  = "$b64Header.$b64Claims";

    $binarySig = '';
    $ok = openssl_sign($sigInput, $binarySig, $sa['private_key'], 'SHA256');
    if (!$ok) {
        error_log("[FCM] Failed to sign JWT with service account private key");
        return null;
    }

    $b64Sig = rtrim(strtr(base64_encode($binarySig), '+/', '-_'), '=');
    $jwt = "$sigInput.$b64Sig";

    // 4. Request OAuth2 token from Google
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 && $res) {
        $tokenData = json_decode($res, true);
        if ($tokenData && isset($tokenData['access_token'])) {
            $tokenData['expires_at'] = $now + ($tokenData['expires_in'] ?? 3600);
            @file_put_contents($cacheFile, json_encode($tokenData));
            return $tokenData['access_token'];
        }
    }

    error_log("[FCM] OAuth2 token request failed with status $status: $res");
    return null;
}

/**
 * Dispatches an FCM Push Notification to target user IDs.
 *
 * @param array|int|string $userIds Target user ID or array of user IDs
 * @param string $title Notification title
 * @param string $body Notification message body
 * @param array $dataPayload Custom key-value data payload for deep-linking
 * @return array Status array with success and failure counts
 */
function sendFcmNotification($userIds, string $title, string $body, array $dataPayload = []): array {
    global $pdo;

    if (!is_array($userIds)) {
        $userIds = [(int)$userIds];
    }
    $userIds = array_values(array_filter(array_unique(array_map('intval', $userIds))));
    if (empty($userIds)) {
        return ['success' => 0, 'failure' => 0, 'message' => 'No target users specified'];
    }

    // 1. Fetch valid FCM device tokens for target users
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, fcm_token 
        FROM users 
        WHERE id IN ($placeholders) 
          AND fcm_token IS NOT NULL 
          AND fcm_token != ''
          AND isdelete = 0
    ");
    $stmt->execute($userIds);
    $userTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($userTokens)) {
        return ['success' => 0, 'failure' => 0, 'message' => 'No active FCM tokens found for users'];
    }

    // Ensure all data values are stringified for FCM
    $stringData = [];
    foreach ($dataPayload as $k => $v) {
        $stringData[(string)$k] = is_null($v) ? '' : (is_bool($v) ? ($v ? '1' : '0') : (string)$v);
    }
    if (!isset($stringData['title'])) $stringData['title'] = $title;
    if (!isset($stringData['body']))  $stringData['body']  = $body;

    $successCount = 0;
    $failureCount = 0;
    $deadTokens   = [];

    // 2. Try HTTP v1 API first (Google Service Account)
    $accessToken = getFcmAccessToken();
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: 'gentree-e1a39';

    if ($accessToken) {
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($userTokens as $u) {
            $token = $u['fcm_token'];

            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'high_importance_channel',
                            'sound' => 'default',
                            'default_sound' => true,
                            'default_vibrate_timings' => true,
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: application/json; UTF-8",
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $successCount++;
            } else {
                $failureCount++;
                error_log("[FCM v1 Error] User {$u['id']} HTTP $httpCode: $res");
                if ($httpCode === 404 || str_contains($res, 'UNREGISTERED')) {
                    $deadTokens[] = $u['id'];
                }
            }
        }
    } else {
        // 3. Fallback to FCM Legacy API (FCM_SERVER_KEY)
        $serverKey = getenv('FCM_SERVER_KEY');
        if ($serverKey) {
            $tokens = array_column($userTokens, 'fcm_token');
            $legacyPayload = [
                'registration_ids' => $tokens,
                'priority'         => 'high',
                'notification'     => [
                    'title'        => $title,
                    'body'         => $body,
                    'sound'        => 'default',
                    'channel_id'   => 'high_importance_channel',
                ],
                'data'             => $stringData,
            ];

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($legacyPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: key={$serverKey}",
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $res) {
                $json = json_decode($res, true);
                $successCount = $json['success'] ?? 0;
                $failureCount = $json['failure'] ?? 0;
            } else {
                error_log("[FCM Legacy Error] HTTP $httpCode: $res");
                $failureCount = count($tokens);
            }
        } else {
            error_log("[FCM] Notice: Neither Google Service Account nor FCM_SERVER_KEY configured yet. Push skipped.");
            return [
                'success' => 0,
                'failure' => count($userTokens),
                'message' => 'Push notification skipped: Service account or FCM_SERVER_KEY not configured.'
            ];
        }
    }

    // Clean up dead/unregistered tokens
    if (!empty($deadTokens)) {
        try {
            $dPlaceholders = implode(',', array_fill(0, count($deadTokens), '?'));
            $clearStmt = $pdo->prepare("UPDATE users SET fcm_token = NULL WHERE id IN ($dPlaceholders)");
            $clearStmt->execute($deadTokens);
        } catch (Throwable $e) {}
    }

    return [
        'success' => $successCount,
        'failure' => $failureCount,
        'tokens_targeted' => count($userTokens)
    ];
}
