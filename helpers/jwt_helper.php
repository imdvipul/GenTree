<?php

function generateJWT(array $payload, int $expireHours = 24): string {
    $header = base64_encode(json_encode([
        "alg" => "HS256",
        "typ" => "JWT"
    ]));

    $payload['iat'] = time();
    $payload['exp'] = time() + (3600 * $expireHours);

    $payloadEncoded = base64_encode(json_encode($payload));

    $secret = "GENTREE_SUPER_SECRET_KEY"; // 🔐 move to env in prod

    $signature = hash_hmac(
        'sha256',
        "$header.$payloadEncoded",
        $secret,
        true
    );

    return "$header.$payloadEncoded." . base64_encode($signature);
}

function verifyJWT(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    $secret = "GENTREE_SUPER_SECRET_KEY"; // 🔐 move to env in prod

    $expectedSignature = hash_hmac(
        'sha256',
        "$headerEncoded.$payloadEncoded",
        $secret,
        true
    );

    if (!hash_equals(base64_decode($signatureEncoded), $expectedSignature)) {
        return null;
    }

    $payload = json_decode(base64_decode($payloadEncoded), true);

    if ($payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
