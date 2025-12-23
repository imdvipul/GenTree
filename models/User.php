<?php

class User {
    private PDO $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    // 🔍 Find user for login
    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, password, role, family_id
            FROM users
            WHERE email = ?
            AND isdelete = 0
            LIMIT 1
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🧑 Create user with family_id
    public function create(
        string $name,
        string $email,
        string $password,
        int $family_id,
        string $provider = 'email',
        ?string $providerId = null
    ) {
        $stmt = $this->db->prepare("
            INSERT INTO users
            (name, email, password, family_id, provider, provider_id)
            VALUES
            (:name, :email, :password, :family_id, :provider, :provider_id)
        ");

        return $stmt->execute([
            'name'        => $name,
            'email'       => $email,
            'password'    => $password,
            'family_id'   => $family_id,
            'provider'    => $provider,
            'provider_id' => $providerId
        ]);
    }
}
