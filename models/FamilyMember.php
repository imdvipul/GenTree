<?php

class FamilyMember {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO family_members 
        (family_id, first_name, last_name, nickname, gender, birth_date, bio, avatar, is_default_viewpoint)
        VALUES
        (:family_id, :first_name, :last_name, :nickname, :gender, :birth_date, :bio, :avatar, :is_default_viewpoint)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE family_members SET
            first_name = :first_name,
            last_name = :last_name,
            nickname = :nickname,
            gender = :gender,
            birth_date = :birth_date,
            bio = :bio,
            avatar = :avatar,
            is_default_viewpoint = :is_default_viewpoint
        WHERE id = :id AND iddelete = 0";

        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function listByFamily(int $familyId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM family_members 
             WHERE family_id = ? AND iddelete = 0 
             ORDER BY is_default_viewpoint DESC, created_at ASC"
        );
        $stmt->execute([$familyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
