<?php

/**
 * Load a value from .env file (simple key=value parser).
 * Falls back to getenv() / $_ENV if not found in file.
 */
function env(string $key, string $default = ''): string {
    static $loaded = false;
    static $vars   = [];

    if (!$loaded) {
        $loaded = true;
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (!str_contains($line, '='))        continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v);
            }
        }
    }

    return $vars[$key] ?? (getenv($key) ?: $default);
}

function generateJWT(array $payload, int $expireHours = 1500): string {
    $header = base64_encode(json_encode([
        "alg" => "HS256",
        "typ" => "JWT"
    ]));

    $payload['iat'] = time();
    $payload['exp'] = time() + (3600 * $expireHours);

    $payloadEncoded = base64_encode(json_encode($payload));

    $secret = env('JWT_SECRET', 'GENTREE_SUPER_SECRET_KEY');

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

    $secret = env('JWT_SECRET', 'GENTREE_SUPER_SECRET_KEY');

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
