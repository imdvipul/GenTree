<?php

define('JWT_SECRET', 'SUPER_SECRET_KEY_CHANGE_THIS');
define('JWT_EXPIRY', 3600); // 1 hour

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT($payload) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];

    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;

    $base64Header  = base64UrlEncode(json_encode($header));
    $base64Payload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac(
        'sha256',
        $base64Header . "." . $base64Payload,
        JWT_SECRET,
        true
    );

    $base64Signature = base64UrlEncode($signature);

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function verifyJWT($token) {
    $parts = explode('.', $token);

    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;

    $validSignature = base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

    if (!hash_equals($validSignature, $signature)) return false;

    $payloadData = json_decode(base64UrlDecode($payload), true);

    if ($payloadData['exp'] < time()) return false;

    return $payloadData;
}
