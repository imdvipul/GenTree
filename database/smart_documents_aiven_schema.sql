-- ============================================================================
-- GenTree – Smart Document Manager Database Schema
-- Target: console.aiven.io (MySQL 8.0 / defaultdb)
-- ============================================================================

-- 1. Document Folders Table
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

-- Seed default folders if none exist
INSERT IGNORE INTO `document_folders` (`id`, `name`, `color`, `icon`) VALUES
(1, 'Personal & IDs', 4280391411, 'badge'),
(2, 'Bills & Receipts', 4283215696, 'receipt'),
(3, 'Medical & Health', 4293467747, 'medical_services'),
(4, 'Insurance & Policies', 4294940672, 'security'),
(5, 'Certificates & Degrees', 4288423856, 'school'),
(6, 'General Documents', 4284511051, 'folder');

-- 2. Smart Documents Table
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
  KEY `idx_smart_docs_fav` (`is_favorite`),
  CONSTRAINT `fk_smart_docs_folder` FOREIGN KEY (`folder_id`) REFERENCES `document_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Smart Document Pages Table
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
  CONSTRAINT `fk_smart_pages_doc` FOREIGN KEY (`document_id`) REFERENCES `smart_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Backward Compatibility: Add new columns to existing `documents` table
ALTER TABLE `documents` 
  ADD COLUMN IF NOT EXISTS `folder_id` INT UNSIGNED NULL AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `page_count` INT NOT NULL DEFAULT 1 AFTER `title`,
  ADD COLUMN IF NOT EXISTS `format` VARCHAR(20) NOT NULL DEFAULT 'pdf' AFTER `page_count`,
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NOT NULL DEFAULT 'General' AFTER `document_type`,
  ADD COLUMN IF NOT EXISTS `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `isdelete`,
  ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) NULL AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `enhanced_path` VARCHAR(500) NULL AFTER `pdf_path`,
  ADD COLUMN IF NOT EXISTS `thumbnail_path` VARCHAR(500) NULL AFTER `enhanced_path`,
  ADD COLUMN IF NOT EXISTS `ocr_status` VARCHAR(50) NOT NULL DEFAULT 'none' AFTER `status`;
