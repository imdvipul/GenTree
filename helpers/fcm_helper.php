<?php

function sendPushNotification($fcmToken, $title, $body, $data = []) {

    $serviceAccountPath = __DIR__ . "/../config/firebase_service_account.json";
    $projectId = "gentree-e1a39"; // your Firebase project id

    $key = json_decode(file_get_contents($serviceAccountPath), true);

    // Create JWT for OAuth2
    $now = time();
    $jwt = [
        "iss" => $key["client_email"],
        "scope" => "https://www.googleapis.com/auth/firebase.messaging",
        "aud" => "https://oauth2.googleapis.com/token",
        "iat" => $now,
        "exp" => $now + 3600
    ];

    $base64UrlHeader = rtrim(strtr(base64_encode(json_encode(["alg"=>"RS256","typ"=>"JWT"])), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($jwt)), '+/', '-_'), '=');

    openssl_sign("$base64UrlHeader.$base64UrlPayload", $signature, $key["private_key"], "SHA256");
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $jwtAssertion = "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

    // Exchange JWT for access token
    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion" => $jwtAssertion
        ])
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($response['access_token'])) return false;

    $accessToken = $response['access_token'];

    // Send FCM push
    $payload = [
        "message" => [
            "token" => $fcmToken,
            "notification" => [
                "title" => $title,
                "body" => $body
            ],
            "data" => $data
        ]
    ];

    $ch = curl_init("https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
