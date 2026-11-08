<?php
/**
 * Auto-migration helper for GenTree database schema updates.
 * Safely adds new columns if they do not yet exist in the target database.
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

        // Try adding helpful indexes for performance
        try {
            $indices = $pdo->query("SHOW INDEX FROM daily_suvichars")->fetchAll(PDO::FETCH_ASSOC);
            $indexNames = array_unique(array_column($indices, 'Key_name'));

            if (!in_array('idx_suvichar_active_user', $indexNames, true)) {
                $pdo->exec("ALTER TABLE daily_suvichars ADD INDEX idx_suvichar_active_user (is_active, is_delete, is_user_submitted)");
            }
            if (!in_array('idx_suvichar_user_family', $indexNames, true)) {
                $pdo->exec("ALTER TABLE daily_suvichars ADD INDEX idx_suvichar_user_family (user_id, family_id)");
            }
        } catch (Throwable $idxEx) {
            error_log("[ensureDailySuvicharSchema Index Warning] " . $idxEx->getMessage());
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
