CREATE DATABASE IF NOT EXISTS `spendwise_app`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `spendwise_app`;

-- ─────────────────────────────────────────────────────────────────────────────
-- Core auth tables (unchanged)
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150)  NOT NULL,
  `email`         VARCHAR(190)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `phone`         VARCHAR(40)   NOT NULL DEFAULT '',
  `avatar`        LONGTEXT      NULL,
  `provider`      VARCHAR(20)   NOT NULL DEFAULT 'email',
  `provider_uid`  VARCHAR(191)  NULL     DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email`        (`email`),
  UNIQUE KEY `uniq_users_provider_uid` (`provider_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_states` (
  `user_id`    INT UNSIGNED NOT NULL,
  `state_json` LONGTEXT     NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_states_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_codes` (
  `user_id`    INT UNSIGNED NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `code`       VARCHAR(6)   NOT NULL,
  `attempts`   TINYINT      NOT NULL DEFAULT 0,  -- [SEC] brute-force guard: invalidate after 5 wrong guesses
  `expires_at` DATETIME     NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_password_reset_codes_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: add attempts column to existing installations
ALTER TABLE `password_reset_codes`
  ADD COLUMN IF NOT EXISTS `attempts` TINYINT NOT NULL DEFAULT 0
    COMMENT '[SEC] brute-force guard' AFTER `code`;

-- ─────────────────────────────────────────────────────────────────────────────
-- Phase 1: Relational tables (migrated from user_states.state_json)
-- ─────────────────────────────────────────────────────────────────────────────

-- Tracks whether a user's JSON blob has been decomposed into the tables below.
-- Once migrated = 1, sw_load_state() reads from the relational tables; the old
-- blob is kept as a read-only backup until Phase 2 cleanup.
CREATE TABLE IF NOT EXISTS `user_migration_log` (
  `user_id`        INT UNSIGNED NOT NULL,
  `migrated`       TINYINT(1)   NOT NULL DEFAULT 0,
  `migrated_at`    TIMESTAMP    NULL,
  `expense_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `budget_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `recurring_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `bill_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_migration_log_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual expense transactions.
-- external_id = the UUID/string that the frontend generated (used as the
-- frontend-visible identifier; DB id is purely internal).
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED   NOT NULL,
  `external_id` VARCHAR(64)    NOT NULL,
  `amount`      DECIMAL(15,2)  NOT NULL,
  `category`    VARCHAR(100)   NOT NULL DEFAULT '',
  `date`        DATE           NOT NULL,
  `note`        TEXT           NULL,
  `receipt`     LONGTEXT       NULL,          -- base64-encoded image data
  `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_expense_user_ext`  (`user_id`, `external_id`),
  KEY           `idx_expense_user_date` (`user_id`, `date`),
  KEY           `idx_expense_user_cat`  (`user_id`, `category`),
  CONSTRAINT `fk_expenses_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly category spending limits.
-- month is stored as YYYY-MM (e.g. "2025-01") to match the frontend format.
CREATE TABLE IF NOT EXISTS `budgets` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `external_id`  VARCHAR(64)   NOT NULL,
  `category`     VARCHAR(100)  NOT NULL,
  `month`        VARCHAR(7)    NOT NULL,       -- YYYY-MM
  `limit_amount` DECIMAL(15,2) NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_budget_user_ext`      (`user_id`, `external_id`),
  UNIQUE KEY `uniq_budget_cat_month`     (`user_id`, `category`, `month`),
  KEY           `idx_budget_user_month`  (`user_id`, `month`),
  CONSTRAINT `fk_budgets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring expense templates (subscriptions, rent, etc.).
CREATE TABLE IF NOT EXISTS `recurring_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `external_id` VARCHAR(64)   NOT NULL,
  `name`        VARCHAR(255)  NOT NULL,
  `amount`      DECIMAL(15,2) NOT NULL,
  `category`    VARCHAR(100)  NOT NULL DEFAULT '',
  `frequency`   VARCHAR(20)   NOT NULL DEFAULT 'monthly',
  `start_date`  DATE          NOT NULL,
  `end_date`    DATE          NULL,
  `next_due`    DATE          NOT NULL,
  `active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_recurring_user_ext`     (`user_id`, `external_id`),
  KEY           `idx_recurring_user_active` (`user_id`, `active`),
  KEY           `idx_recurring_next_due`    (`user_id`, `next_due`),
  CONSTRAINT `fk_recurring_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periodic bills (electricity, rent, subscriptions with due dates).
CREATE TABLE IF NOT EXISTS `bills` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `external_id` VARCHAR(64)   NOT NULL,
  `name`        VARCHAR(255)  NOT NULL,
  `amount`      DECIMAL(15,2) NOT NULL,
  `category`    VARCHAR(100)  NOT NULL DEFAULT '',
  `due_date`    DATE          NOT NULL,
  `status`      VARCHAR(20)   NOT NULL DEFAULT 'upcoming',  -- upcoming | overdue | paid
  `paid_date`   DATE          NULL,
  `reference`   VARCHAR(255)  NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bill_user_ext`     (`user_id`, `external_id`),
  KEY           `idx_bill_user_status` (`user_id`, `status`),
  KEY           `idx_bill_due_date`    (`user_id`, `due_date`),
  CONSTRAINT `fk_bills_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user UI preferences and notification metadata.
-- extra_json stores any unrecognised state keys (e.g. __notifications_meta)
-- so nothing is silently lost during save/load round-trips.
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id`             INT UNSIGNED NOT NULL,
  `dark_mode`           TINYINT(1)   NOT NULL DEFAULT 0,
  `notifications_email` TINYINT(1)   NOT NULL DEFAULT 1,
  `language`            VARCHAR(5)   NOT NULL DEFAULT 'en',
  `categories`          TEXT         NOT NULL DEFAULT '[]',  -- JSON array of category strings
  `notif_seen_at`       DATETIME     NULL,
  `extra_json`          MEDIUMTEXT   NULL,   -- catch-all for forward-compat state keys
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_prefs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
