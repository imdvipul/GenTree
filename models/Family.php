<?php

class Family {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($name, $bio, $coverImage, $createdBy) {
        $stmt = $this->pdo->prepare("
            INSERT INTO families (name, bio, cover_image, created_by)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$name, $bio, $coverImage, $createdBy]);
    }

    public function update($id, $name, $bio, $coverImage) {
        $stmt = $this->pdo->prepare("
            UPDATE families
            SET name = ?, bio = ?, cover_image = ?
            WHERE id = ? AND isdelete = 0
        ");
        return $stmt->execute([$name, $bio, $coverImage, $id]);
    }

    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT id, name, bio, cover_image, created_by, created_at
            FROM families
            WHERE isdelete = 0
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
