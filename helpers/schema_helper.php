<?php
/**
 * Auto-migration helper for GenTree database schema updates.
 * Safely adds new tables and columns if they do not yet exist in the target database.
 */

function ensureDailySuvicharSchema(PDO $pdo, bool $force = false): array {
    $flagFile = sys_get_temp_dir() . '/.daily_suvichars_migrated_v1';
    if (!$force && file_exists($flagFile)) {
        return ['status' => true, 'cached' => true];
    }

    $added = [];
    $existing = [];

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM daily_suvichars")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($cols)) {
            return ['status' => false, 'message' => 'Table daily_suvichars not found'];
        }

        $needed = [
            'user_id'           => "ADD COLUMN user_id INT UNSIGNED NULL DEFAULT NULL AFTER author",
            'family_id'         => "ADD COLUMN family_id INT UNSIGNED NULL DEFAULT NULL AFTER user_id",
            'is_user_submitted' => "ADD COLUMN is_user_submitted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active",
            'is_delete'         => "ADD COLUMN is_delete TINYINT(1) NOT NULL DEFAULT 0 AFTER is_user_submitted",
        ];

        foreach ($needed as $col => $alterSql) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec("ALTER TABLE daily_suvichars {$alterSql}");
                $added[] = $col;
            } else {
                $existing[] = $col;
            }
        }

        @file_put_contents($flagFile, (string)time());

        return [
            'status' => true,
            'added' => $added,
            'existing' => $existing
        ];
    } catch (Throwable $e) {
        error_log("[ensureDailySuvicharSchema Error] " . $e->getMessage());
        return [
            'status' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Ensures Smart Document Manager tables & columns exist on Aiven MySQL.
 */
function ensureSmartDocumentsSchema(PDO $pdo, bool $force = false): array {
    $flagFile = sys_get_temp_dir() . '/.smart_documents_migrated_v1';
    if (!$force && file_exists($flagFile)) {
        return ['status' => true, 'cached' => true];
    }

    $results = [];

    try {
        // 1. Folders table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `document_folders` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NULL,
              `name` VARCHAR(150) NOT NULL,
              `color` BIGINT UNSIGNED DEFAULT 4280391411,
              `icon` VARCHAR(50) DEFAULT 'folder',
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_folder_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ");
        $results['document_folders'] = 'ready';

        // Seed default folders if table is empty
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `document_folders`")->fetchColumn();
        if ($count === 0) {
            $defaultFolders = [
                ['Personal & IDs', 4280391411, 'badge'],
                ['Bills & Receipts', 4283215696, 'receipt'],
                ['Medical & Health', 4293467747, 'medical_services'],
                ['Insurance & Policies', 4294940672, 'security'],
                ['Certificates & Degrees', 4288423856, 'school'],
                ['General Documents', 4284511051, 'folder']
            ];
            $stmt = $pdo->prepare("INSERT INTO `document_folders` (`name`, `color`, `icon`) VALUES (?, ?, ?)");
            foreach ($defaultFolders as $f) {
                $stmt->execute($f);
            }
            $results['folders_seeded'] = count($defaultFolders);
        }

        // 2. Smart Documents Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `smart_documents` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NULL,
              `folder_id` INT UNSIGNED NULL,
              `name` VARCHAR(255) NOT NULL,
              `original_path` VARCHAR(500) NOT NULL,
              `enhanced_path` VARCHAR(500) NULL,
              `pdf_path` VARCHAR(500) NULL,
              `thumbnail_path` VARCHAR(500) NOT NULL,
              `page_count` INT NOT NULL DEFAULT 1,
              `file_size` BIGINT NOT NULL DEFAULT 0,
              `format` VARCHAR(20) NOT NULL DEFAULT 'pdf',
              `category` VARCHAR(100) NOT NULL DEFAULT 'General',
              `is_favorite` TINYINT(1) NOT NULL DEFAULT 0,
              `ocr_text` LONGTEXT NULL,
              `ocr_status` VARCHAR(50) NOT NULL DEFAULT 'none',
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_smart_docs_user` (`user_id`),
              KEY `idx_smart_docs_folder` (`folder_id`),
              KEY `idx_smart_docs_fav` (`is_favorite`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ");
        $results['smart_documents'] = 'ready';

        // 3. Smart Document Pages Table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `smart_document_pages` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `document_id` INT UNSIGNED NOT NULL,
              `page_number` INT NOT NULL DEFAULT 1,
              `original_path` VARCHAR(500) NOT NULL,
              `enhanced_path` VARCHAR(500) NOT NULL,
              `filter_applied` VARCHAR(50) NOT NULL DEFAULT 'magic',
              `rotation` INT NOT NULL DEFAULT 0,
              `page_ocr_text` LONGTEXT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_smart_pages_doc` (`document_id`),
              CONSTRAINT `fk_smart_document_pages_doc` FOREIGN KEY (`document_id`) REFERENCES `smart_documents` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ");
        $results['smart_document_pages'] = 'ready';

        // 4. Also add columns to existing `documents` table for backward compatibility
        try {
            $docCols = $pdo->query("SHOW COLUMNS FROM `documents`")->fetchAll(PDO::FETCH_COLUMN);
            $docNeeded = [
                'folder_id'      => "ADD COLUMN `folder_id` INT UNSIGNED NULL AFTER `user_id`",
                'page_count'     => "ADD COLUMN `page_count` INT NOT NULL DEFAULT 1 AFTER `title`",
                'format'         => "ADD COLUMN `format` VARCHAR(20) NOT NULL DEFAULT 'pdf' AFTER `page_count`",
                'category'       => "ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'General' AFTER `document_type`",
                'is_favorite'    => "ADD COLUMN `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `isdelete`",
                'pdf_path'       => "ADD COLUMN `pdf_path` VARCHAR(500) NULL AFTER `file_path`",
                'enhanced_path'  => "ADD COLUMN `enhanced_path` VARCHAR(500) NULL AFTER `pdf_path`",
                'thumbnail_path' => "ADD COLUMN `thumbnail_path` VARCHAR(500) NULL AFTER `enhanced_path`",
                'ocr_status'     => "ADD COLUMN `ocr_status` VARCHAR(50) NOT NULL DEFAULT 'none' AFTER `status`"
            ];

            foreach ($docNeeded as $col => $alterSql) {
                if (!in_array($col, $docCols, true)) {
                    $pdo->exec("ALTER TABLE `documents` {$alterSql}");
                    $results['documents_added'][] = $col;
                }
            }
        } catch (Throwable $e) {
            error_log("[ensureSmartDocumentsSchema documents alter Warning] " . $e->getMessage());
        }

        @file_put_contents($flagFile, (string)time());

        return [
            'status' => true,
            'message' => 'Smart Documents schema successfully verified/created',
            'details' => $results
        ];

    } catch (Throwable $e) {
        error_log("[ensureSmartDocumentsSchema Error] " . $e->getMessage());
        return [
            'status' => false,
            'error' => $e->getMessage()
        ];
    }
}
