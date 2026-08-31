-- ============================================================
--  GenTree family_api — Document Intelligence Schema Migrations
--  Creates tables for documents, fields, bills, warranties, 
--  insurance policies, and smart alerts.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. DOCUMENTS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED  NOT NULL,
    document_type VARCHAR(100)  NOT NULL, -- bill, card, warranty, insurance, certificate, letter, other
    title         VARCHAR(255)  NOT NULL,
    file_path     VARCHAR(500)  NOT NULL,
    mime_type     VARCHAR(100)  NULL,
    document_date DATE          NULL,
    expiry_date   DATE          NULL,
    status        VARCHAR(50)   NOT NULL DEFAULT 'active', -- active, expiring, expired, none
    ocr_text      LONGTEXT      NULL,
    summary       TEXT          NULL,
    confidence    DOUBLE        NOT NULL DEFAULT 1.0,
    isdelete      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_doc (user_id, isdelete)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. DOCUMENT FIELDS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_fields (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    document_id   INT UNSIGNED  NOT NULL,
    field_name    VARCHAR(100)  NOT NULL,
    field_value   TEXT          NULL,
    field_type    VARCHAR(50)   NOT NULL DEFAULT 'text', -- text, date, number, currency
    confidence    DOUBLE        NOT NULL DEFAULT 1.0,
    is_verified   TINYINT(1)    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_field (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. BILLS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bills (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    document_id      INT UNSIGNED  NOT NULL,
    vendor_name      VARCHAR(255)  NOT NULL DEFAULT '',
    bill_number      VARCHAR(100)  NULL,
    consumer_number  VARCHAR(100)  NULL,
    billing_date     DATE          NULL,
    due_date         DATE          NULL,
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status   ENUM('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
    currency         VARCHAR(10)   NOT NULL DEFAULT 'INR',
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_bill (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. BILL ITEMS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bill_items (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    bill_id    INT UNSIGNED  NOT NULL,
    item_name  VARCHAR(255)  NOT NULL,
    quantity   INT           NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bill_item (bill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. WARRANTIES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS warranties (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    document_id   INT UNSIGNED  NOT NULL,
    product_name  VARCHAR(255)  NOT NULL,
    model_number  VARCHAR(100)  NULL,
    serial_number VARCHAR(100)  NULL,
    purchase_date DATE          NULL,
    start_date    DATE          NULL,
    end_date      DATE          NULL,
    seller        VARCHAR(255)  NULL,
    manufacturer  VARCHAR(255)  NULL,
    status        ENUM('active','expired') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_warranty (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. INSURANCE POLICIES ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS insurance_policies (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    document_id    INT UNSIGNED  NOT NULL,
    company_name   VARCHAR(255)  NOT NULL,
    policy_number  VARCHAR(100)  NOT NULL,
    policy_type    VARCHAR(100)  NULL,
    start_date     DATE          NULL,
    end_date       DATE          NULL,
    premium_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sum_insured    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    renewal_date   DATE          NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_insurance (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 7. DOCUMENT REMINDERS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_reminders (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    document_id   INT UNSIGNED  NOT NULL,
    user_id       INT UNSIGNED  NOT NULL,
    reminder_type VARCHAR(50)   NOT NULL DEFAULT 'due_date', -- due_date, expiry, custom
    reminder_date DATE          NOT NULL,
    message       TEXT          NOT NULL,
    status        ENUM('pending','sent') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc_reminder (document_id),
    KEY idx_user_reminder (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
