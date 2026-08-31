<?php
require __DIR__ . '/../vendor/autoload.php';

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;

class CognitoService {
    private static function client() {
        return new CognitoIdentityProviderClient([
            'region' => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ]
        ]);
    }

    public static function register($email, $password) {
        $client = self::client();

        return $client->signUp([
            'ClientId' => $_ENV['COGNITO_CLIENT_ID'],
            'Username' => $email,
            'Password' => $password,
            'UserAttributes' => [
                ['Name' => 'email', 'Value' => $email]
            ]
        ]);
    }

    public static function login($email, $password) {
        $client = self::client();

        $res = $client->initiateAuth([
            'AuthFlow' => 'USER_PASSWORD_AUTH',
            'ClientId' => $_ENV['COGNITO_CLIENT_ID'],
            'AuthParameters' => [
                'USERNAME' => $email,
                'PASSWORD' => $password,
            ]
        ]);

        return $res['AuthenticationResult'];
    }
}
