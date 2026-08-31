<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;

function firebaseAuth() {
    $serviceAccount = getenv('FIREBASE_CREDENTIALS');
    if ($serviceAccount) {
        if (str_starts_with(trim($serviceAccount), '{')) {
            $serviceAccount = json_decode($serviceAccount, true);
        }
    } else {
        $serviceAccount = __DIR__ . '/firebase-admin.json';
    }

    $factory = (new Factory)->withServiceAccount($serviceAccount);

    return $factory->createAuth();
}
