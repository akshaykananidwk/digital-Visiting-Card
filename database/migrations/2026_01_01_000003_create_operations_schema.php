<?php

declare(strict_types=1);

use App\Core\Database;

/** Analytics, leads, settings, notifications, audit and update tables. */
return [
    'up' => static function (Database $db): void {
        $p = $db->prefix();
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo = $db->pdo();

        // ------------------------------------------------------- Analytics --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_views` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `visitor_hash` CHAR(64) NOT NULL,
            `referrer_host` VARCHAR(190) NULL,
            `device_type` ENUM('mobile','tablet','desktop','bot','unknown') NOT NULL DEFAULT 'unknown',
            `source` VARCHAR(40) NULL,
            `country` CHAR(2) NULL,
            `viewed_on` DATE NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_view_card_date` (`card_id`, `viewed_on`),
            KEY `idx_view_visitor` (`card_id`, `visitor_hash`, `viewed_on`),
            CONSTRAINT `fk_view_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `event_type` VARCHAR(40) NOT NULL,
            `label` VARCHAR(120) NULL,
            `visitor_hash` CHAR(64) NOT NULL,
            `device_type` ENUM('mobile','tablet','desktop','bot','unknown') NOT NULL DEFAULT 'unknown',
            `occurred_on` DATE NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_event_card_type` (`card_id`, `event_type`, `occurred_on`),
            CONSTRAINT `fk_event_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_daily_stats` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `stat_date` DATE NOT NULL,
            `views` INT UNSIGNED NOT NULL DEFAULT 0,
            `unique_views` INT UNSIGNED NOT NULL DEFAULT 0,
            `calls` INT UNSIGNED NOT NULL DEFAULT 0,
            `whatsapp` INT UNSIGNED NOT NULL DEFAULT 0,
            `emails` INT UNSIGNED NOT NULL DEFAULT 0,
            `websites` INT UNSIGNED NOT NULL DEFAULT 0,
            `directions` INT UNSIGNED NOT NULL DEFAULT 0,
            `shares` INT UNSIGNED NOT NULL DEFAULT 0,
            `saves` INT UNSIGNED NOT NULL DEFAULT 0,
            `qr_scans` INT UNSIGNED NOT NULL DEFAULT 0,
            `leads` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_card_day` (`card_id`, `stat_date`),
            KEY `idx_stats_date` (`stat_date`),
            CONSTRAINT `fk_stats_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ----------------------------------------------------------- Leads --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}leads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `phone` VARCHAR(25) NULL,
            `email` VARCHAR(190) NULL,
            `subject` VARCHAR(190) NULL,
            `message` TEXT NULL,
            `source` VARCHAR(40) NOT NULL DEFAULT 'card_form',
            `status` ENUM('new','read','contacted','converted','spam','archived') NOT NULL DEFAULT 'new',
            `ip_hash` CHAR(64) NULL,
            `user_agent` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_lead_card` (`card_id`, `status`),
            KEY `idx_lead_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_lead_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_lead_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ---------------------------------------------------- Platform ops --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}settings` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key` VARCHAR(100) NOT NULL,
            `value` LONGTEXT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'string',
            `group` VARCHAR(50) NOT NULL DEFAULT 'general',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_setting_key` (`key`),
            KEY `idx_setting_group` (`group`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}notifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `channel` ENUM('email','whatsapp','in_app','sms') NOT NULL DEFAULT 'in_app',
            `event` VARCHAR(60) NOT NULL DEFAULT 'generic',
            `recipient` VARCHAR(190) NULL,
            `title` VARCHAR(190) NOT NULL,
            `body` TEXT NULL,
            `link` VARCHAR(500) NULL,
            `status` ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
            `sent_at` DATETIME NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_notification_user` (`user_id`, `status`, `created_at`),
            KEY `idx_notification_event` (`event`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `actor_role` VARCHAR(40) NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(60) NULL,
            `entity_id` INT UNSIGNED NULL,
            `context` JSON NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_audit_user` (`user_id`, `created_at`),
            KEY `idx_audit_action` (`action`, `created_at`),
            KEY `idx_audit_entity` (`entity_type`, `entity_id`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `key_hash` CHAR(64) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rate_key` (`key_hash`),
            KEY `idx_rate_expiry` (`expires_at`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}api_tokens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `abilities` JSON NULL,
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `revoked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_api_token` (`token_hash`),
            KEY `idx_token_user` (`user_id`),
            CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ---------------------------------------------- Updates / backups --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}update_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `from_version` VARCHAR(20) NULL,
            `to_version` VARCHAR(20) NULL,
            `commit_hash` VARCHAR(45) NULL,
            `commit_message` VARCHAR(500) NULL,
            `commit_date` DATETIME NULL,
            `branch` VARCHAR(100) NULL,
            `status` ENUM('running','success','failed','rolled_back') NOT NULL DEFAULT 'running',
            `stage` VARCHAR(60) NULL,
            `files_changed` INT UNSIGNED NOT NULL DEFAULT 0,
            `files_list` JSON NULL,
            `migration_status` VARCHAR(30) NULL,
            `migrations_run` JSON NULL,
            `backup_id` INT UNSIGNED NULL,
            `backup_status` VARCHAR(30) NULL,
            `health_status` VARCHAR(30) NULL,
            `health_report` JSON NULL,
            `rollback_status` VARCHAR(30) NULL,
            `error_log` LONGTEXT NULL,
            `steps` JSON NULL,
            `duration_seconds` INT UNSIGNED NULL,
            `initiated_by` INT UNSIGNED NULL,
            `started_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_update_status` (`status`, `started_at`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}backups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(190) NOT NULL,
            `type` ENUM('full','files','database') NOT NULL DEFAULT 'full',
            `trigger_source` VARCHAR(30) NOT NULL DEFAULT 'manual',
            `files_path` VARCHAR(500) NULL,
            `database_path` VARCHAR(500) NULL,
            `files_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `database_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `files_checksum` CHAR(64) NULL,
            `database_checksum` CHAR(64) NULL,
            `app_version` VARCHAR(20) NULL,
            `status` ENUM('running','completed','failed','restored','deleted') NOT NULL DEFAULT 'running',
            `verified` TINYINT(1) NOT NULL DEFAULT 0,
            `error` VARCHAR(500) NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `completed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_backup_status` (`status`, `created_at`)
        ) {$engine}");
    },

    'down' => static function (Database $db): void {
        $p = $db->prefix();
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'backups', 'update_logs', 'api_tokens', 'rate_limits', 'audit_logs', 'notifications',
            'settings', 'leads', 'card_daily_stats', 'card_events', 'card_views',
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$p}{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    },
];
