<?php

declare(strict_types=1);

use App\Core\Database;

/** Templates, cards and all card content tables. */
return [
    'up' => static function (Database $db): void {
        $p = $db->prefix();
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo = $db->pdo();

        // ------------------------------------------------------- Templates --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}template_categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `parent_id` INT UNSIGNED NULL,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `icon` VARCHAR(60) NULL,
            `description` VARCHAR(255) NULL,
            `template_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_tplcat_slug` (`slug`),
            KEY `idx_tplcat_parent` (`parent_id`),
            KEY `idx_tplcat_active` (`is_active`, `sort_order`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}templates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(40) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `category_id` INT UNSIGNED NULL,
            `layout` VARCHAR(60) NOT NULL DEFAULT 'classic',
            `theme_mode` ENUM('light','dark','auto') NOT NULL DEFAULT 'light',
            `style` VARCHAR(60) NOT NULL DEFAULT 'modern',
            `industry` VARCHAR(80) NULL,
            `color_family` VARCHAR(40) NULL,
            `preview_image` VARCHAR(255) NULL,
            `config` JSON NOT NULL,
            `tags` VARCHAR(255) NULL,
            `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
            `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_template_code` (`code`),
            KEY `idx_template_category` (`category_id`, `is_active`),
            KEY `idx_template_flags` (`is_active`, `is_premium`, `sort_order`),
            KEY `idx_template_layout` (`layout`),
            KEY `idx_template_style` (`style`),
            KEY `idx_template_usage` (`usage_count`),
            FULLTEXT KEY `ft_template_search` (`name`, `tags`, `industry`),
            CONSTRAINT `fk_template_category` FOREIGN KEY (`category_id`) REFERENCES `{$p}template_categories` (`id`) ON DELETE SET NULL
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}template_components` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `template_id` INT UNSIGNED NOT NULL,
            `component` VARCHAR(60) NOT NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `settings` JSON NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_component_template` (`template_id`, `sort_order`),
            CONSTRAINT `fk_component_template` FOREIGN KEY (`template_id`) REFERENCES `{$p}templates` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ----------------------------------------------------------- Cards --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}cards` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `reseller_id` INT UNSIGNED NULL,
            `template_id` INT UNSIGNED NULL,
            `category_id` INT UNSIGNED NULL,
            `slug` VARCHAR(100) NOT NULL,
            `status` ENUM('draft','published','suspended','expired') NOT NULL DEFAULT 'draft',
            `title` VARCHAR(150) NOT NULL,
            `full_name` VARCHAR(150) NULL,
            `designation` VARCHAR(150) NULL,
            `business_name` VARCHAR(190) NULL,
            `business_category` VARCHAR(120) NULL,
            `tagline` VARCHAR(255) NULL,
            `about` TEXT NULL,
            `profile_image` VARCHAR(255) NULL,
            `cover_image` VARCHAR(255) NULL,
            `logo_image` VARCHAR(255) NULL,
            `phone` VARCHAR(25) NULL,
            `phone_alt` VARCHAR(25) NULL,
            `whatsapp` VARCHAR(25) NULL,
            `whatsapp_message` VARCHAR(255) NULL,
            `email` VARCHAR(190) NULL,
            `website` VARCHAR(255) NULL,
            `address` VARCHAR(500) NULL,
            `city` VARCHAR(100) NULL,
            `state` VARCHAR(100) NULL,
            `pincode` VARCHAR(12) NULL,
            `country` VARCHAR(100) NULL DEFAULT 'India',
            `map_link` VARCHAR(500) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `business_hours` JSON NULL,
            `upi_id` VARCHAR(100) NULL,
            `payment_note` VARCHAR(255) NULL,
            `seo_title` VARCHAR(190) NULL,
            `seo_description` VARCHAR(320) NULL,
            `seo_image` VARCHAR(255) NULL,
            `seo_keywords` VARCHAR(255) NULL,
            `theme_overrides` JSON NULL,
            `settings` JSON NULL,
            `draft_data` JSON NULL,
            `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `leads_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `published_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_card_slug` (`slug`),
            UNIQUE KEY `uniq_card_uuid` (`uuid`),
            KEY `idx_card_user` (`user_id`, `status`),
            KEY `idx_card_reseller` (`reseller_id`),
            KEY `idx_card_template` (`template_id`),
            KEY `idx_card_status_pub` (`status`, `published_at`),
            KEY `idx_card_expires` (`expires_at`),
            CONSTRAINT `fk_card_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_card_template` FOREIGN KEY (`template_id`) REFERENCES `{$p}templates` (`id`) ON DELETE SET NULL
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_sections` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `section` VARCHAR(50) NOT NULL,
            `title` VARCHAR(150) NULL,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `settings` JSON NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_card_section` (`card_id`, `section`),
            KEY `idx_section_order` (`card_id`, `sort_order`),
            CONSTRAINT `fk_section_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_services` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `description` TEXT NULL,
            `icon` VARCHAR(60) NULL,
            `image` VARCHAR(255) NULL,
            `price` DECIMAL(10,2) NULL,
            `price_label` VARCHAR(60) NULL,
            `cta_label` VARCHAR(60) NULL,
            `cta_link` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_service_card` (`card_id`, `sort_order`),
            CONSTRAINT `fk_service_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_products` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(190) NOT NULL,
            `description` TEXT NULL,
            `image` VARCHAR(255) NULL,
            `price` DECIMAL(10,2) NULL,
            `discount_price` DECIMAL(10,2) NULL,
            `sku` VARCHAR(60) NULL,
            `category` VARCHAR(100) NULL,
            `stock_status` ENUM('in_stock','out_of_stock','made_to_order') NOT NULL DEFAULT 'in_stock',
            `cta_type` ENUM('whatsapp','call','link','none') NOT NULL DEFAULT 'whatsapp',
            `cta_link` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_product_card` (`card_id`, `sort_order`),
            CONSTRAINT `fk_product_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_gallery` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `type` ENUM('image','video','youtube') NOT NULL DEFAULT 'image',
            `path` VARCHAR(500) NULL,
            `thumbnail` VARCHAR(500) NULL,
            `title` VARCHAR(190) NULL,
            `caption` VARCHAR(255) NULL,
            `width` SMALLINT UNSIGNED NULL,
            `height` SMALLINT UNSIGNED NULL,
            `filesize` INT UNSIGNED NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_gallery_card` (`card_id`, `type`, `sort_order`),
            CONSTRAINT `fk_gallery_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}card_social_links` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `platform` VARCHAR(40) NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `label` VARCHAR(100) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_card_platform` (`card_id`, `platform`),
            CONSTRAINT `fk_social_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}qr_codes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NOT NULL,
            `target_url` VARCHAR(500) NOT NULL,
            `foreground` VARCHAR(9) NOT NULL DEFAULT '#000000',
            `background` VARCHAR(9) NOT NULL DEFAULT '#FFFFFF',
            `ecc_level` CHAR(1) NOT NULL DEFAULT 'M',
            `scan_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_scanned_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_qr_card` (`card_id`),
            CONSTRAINT `fk_qr_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}custom_domains` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `card_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `domain` VARCHAR(190) NOT NULL,
            `verification_token` VARCHAR(64) NOT NULL,
            `status` ENUM('pending','verified','failed','disabled') NOT NULL DEFAULT 'pending',
            `verified_at` DATETIME NULL,
            `last_checked_at` DATETIME NULL,
            `notes` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_custom_domain` (`domain`),
            KEY `idx_domain_user` (`user_id`),
            CONSTRAINT `fk_domain_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_domain_card` FOREIGN KEY (`card_id`) REFERENCES `{$p}cards` (`id`) ON DELETE CASCADE
        ) {$engine}");
    },

    'down' => static function (Database $db): void {
        $p = $db->prefix();
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'custom_domains', 'qr_codes', 'card_social_links', 'card_gallery', 'card_products',
            'card_services', 'card_sections', 'cards', 'template_components', 'templates', 'template_categories',
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$p}{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    },
];
