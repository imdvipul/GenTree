<?php

class User
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail($email)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $sql = "
            INSERT INTO users (
                first_name,
                last_name,
                email,
                password,
                phone_number,
                role,
                family_id,
                provider,
                provider_id
            ) VALUES (
                :first_name,
                :last_name,
                :email,
                :password,
                :phone_number,
                'member',
                :family_id,
                :provider,
                :provider_id
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':first_name'   => $data['first_name'],
            ':last_name'    => $data['last_name'],
            ':email'        => $data['email'],
            ':password'     => $data['password'],
            ':phone_number' => $data['phone_number'],
            ':family_id'    => $data['family_id'],
            ':provider'     => $data['provider'],
            ':provider_id'  => $data['provider_id'],
        ]);
    }

    public function updateFamily($userId, $familyId)
{
    $stmt = $this->pdo->prepare("
        UPDATE users 
        SET family_id = ? 
        WHERE id = ?
    ");

    return $stmt->execute([$familyId, $userId]);
}

}
