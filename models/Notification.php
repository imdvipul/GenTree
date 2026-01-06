<?php

class Notification
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($userId, $title, $message, $type, $referenceToken = null)
{
    $stmt = $this->pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, reference_token)
        VALUES (?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $userId,
        $title,
        $message,
        $type,
        $referenceToken
    ]);
}

}
