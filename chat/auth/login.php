$res = CognitoService::login($email, $password);

echo json_encode([
    "status" => true,
    "jwt" => $yourJwt,
    "cognito" => [
        "accessToken" => $res['AccessToken'],
        "idToken" => $res['IdToken'],
    ]
]);
