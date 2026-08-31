<?php

function ensureChatTables(PDO $pdo) {

    // Create chats table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chats (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('direct','group') NOT NULL,
            title VARCHAR(150) DEFAULT NULL,
            created_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Create chat_participants table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_participants (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            role ENUM('admin','member') DEFAULT 'member',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_participant (chat_id, user_id)
        ) ENGINE=InnoDB;
    ");
}
