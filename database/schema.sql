-- ============================================================
--  GenTree family_api — Complete Database Schema
--  Generated from actual controller & model SQL queries
--
--  Run with:
--  mysql -u root -h 127.0.0.1 family_db < database/schema.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. USERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(100)  NOT NULL DEFAULT '',
    last_name     VARCHAR(100)  NOT NULL DEFAULT '',
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NULL,
    phone_number  VARCHAR(30)   NULL,
    role          ENUM('admin','member') NOT NULL DEFAULT 'member',
    family_id     INT UNSIGNED  NULL,
    provider      VARCHAR(50)   NOT NULL DEFAULT 'email',
    provider_id   VARCHAR(255)  NULL,
    avatar        VARCHAR(500)  NULL,
    fcm_token     TEXT          NULL,
    isdelete      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. FAMILIES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS families (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200)  NOT NULL,
    bio           TEXT          NULL,
    cover_image   VARCHAR(500)  NULL,
    created_by    INT UNSIGNED  NOT NULL,
    iddelete      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. FAMILY MEMBERSHIPS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS family_memberships (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    family_id  INT UNSIGNED  NOT NULL,
    user_id    INT UNSIGNED  NOT NULL,
    role       VARCHAR(50)   NOT NULL DEFAULT 'member',
    status     VARCHAR(50)   NOT NULL DEFAULT 'active',
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_family (user_id, family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. FAMILY MEMBERS ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS family_members (
    id                   INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    family_id            INT UNSIGNED  NULL,
    user_id              INT UNSIGNED  NULL,
    first_name           VARCHAR(100)  NOT NULL DEFAULT '',
    last_name            VARCHAR(100)  NOT NULL DEFAULT '',
    nickname             VARCHAR(100)  NULL,
    gender               ENUM('male','female','other') NULL,
    birth_date           DATE          NULL,
    bio                  TEXT          NULL,
    avatar_url           VARCHAR(500)  NULL,
    is_default_viewpoint TINYINT(1)    NOT NULL DEFAULT 0,
    iddelete             TINYINT(1)    NOT NULL DEFAULT 0,
    created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. USER MEMBER LINKS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_member_links (
    id        INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED  NOT NULL,
    family_id INT UNSIGNED  NOT NULL,
    member_id INT UNSIGNED  NOT NULL,
    UNIQUE KEY uq_user_family_member (user_id, family_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. RELATIONSHIPS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS relationships (
    id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    family_id         INT UNSIGNED  NOT NULL,
    person_id         INT UNSIGNED  NOT NULL,
    related_person_id INT UNSIGNED  NOT NULL,
    relation_type     VARCHAR(100)  NOT NULL,
    is_active         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 7. FAMILY INVITES ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS family_invites (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    family_id   INT UNSIGNED  NOT NULL,
    token       VARCHAR(200)  NOT NULL UNIQUE,
    role        VARCHAR(50)   NOT NULL DEFAULT 'member',
    created_by  INT UNSIGNED  NOT NULL,
    expires_at  DATETIME      NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 8. LIFE EVENTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS life_events (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    member_id      INT UNSIGNED  NULL,
    audience_type  VARCHAR(50)   NOT NULL DEFAULT 'family',
    title          VARCHAR(255)  NOT NULL,
    description    TEXT          NULL,
    event_date     DATE          NULL,
    date_precision VARCHAR(20)   NOT NULL DEFAULT 'day',
    location       VARCHAR(255)  NULL,
    emoji          VARCHAR(20)   NULL,
    iddelete       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 9. LIFE EVENT MEMBERS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS life_event_members (
    id        INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    event_id  INT UNSIGNED  NOT NULL,
    member_id INT UNSIGNED  NOT NULL,
    UNIQUE KEY uq_event_member (event_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 10. LIFE EVENT FAMILIES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS life_event_families (
    id        INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    event_id  INT UNSIGNED  NOT NULL,
    family_id INT UNSIGNED  NOT NULL,
    UNIQUE KEY uq_event_family (event_id, family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 11. THOUGHTFUL MESSAGES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS thoughtful_messages (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    family_id          INT UNSIGNED  NULL,
    created_by         INT UNSIGNED  NULL,
    message            TEXT          NOT NULL,
    image              VARCHAR(500)  NULL,
    is_for_all_members TINYINT(1)    NOT NULL DEFAULT 1,
    iddelete           TINYINT(1)    NOT NULL DEFAULT 0,
    created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 12. THOUGHTFUL MESSAGE MEMBERS ──────────────────────────
CREATE TABLE IF NOT EXISTS thoughtful_message_members (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED  NOT NULL,
    member_id  INT UNSIGNED  NOT NULL,
    UNIQUE KEY uq_msg_member (message_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 13. THOUGHTFUL MESSAGE LIKES ────────────────────────────
CREATE TABLE IF NOT EXISTS thoughtful_message_likes (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED  NOT NULL,
    user_id    INT UNSIGNED  NOT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like (message_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 14. NOTIFICATIONS ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    title           VARCHAR(255)  NOT NULL,
    message         TEXT          NULL,
    type            VARCHAR(100)  NULL,
    reference_token VARCHAR(255)  NULL,
    is_read         TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 15. EMAIL OTPs ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_otps (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150)  NOT NULL,
    otp        VARCHAR(10)   NOT NULL,
    expires_at DATETIME      NOT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 16. CHATS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS chats (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    type       ENUM('direct','group') NOT NULL DEFAULT 'direct',
    name       VARCHAR(200)  NULL,
    created_by INT UNSIGNED  NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 17. CHAT PARTICIPANTS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_participants (
    id        INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    chat_id   INT UNSIGNED  NOT NULL,
    user_id   INT UNSIGNED  NOT NULL,
    role      VARCHAR(50)   NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_user (chat_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;