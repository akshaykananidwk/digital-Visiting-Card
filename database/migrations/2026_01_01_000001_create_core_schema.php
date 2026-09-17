<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Core schema: identity, RBAC, billing, cards, templates, analytics and the
 * operational tables used by the updater/backup subsystems.
 */
return [
    'up' => static function (Database $db): void {
        $p = $db->prefix();
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo = $db->pdo();

        // ------------------------------------------------------------ RBAC --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `rank` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_roles_slug` (`slug`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}permissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `group` VARCHAR(50) NOT NULL DEFAULT 'general',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_permissions_slug` (`slug`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}role_permissions` (
            `role_id` INT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`),
            KEY `idx_rp_permission` (`permission_id`),
            CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `{$p}roles` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `{$p}permissions` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ----------------------------------------------------------- Users --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `phone` VARCHAR(25) NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'customer',
            `status` ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
            `reseller_id` INT UNSIGNED NULL,
            `avatar` VARCHAR(255) NULL,
            `company` VARCHAR(150) NULL,
            `gstin` VARCHAR(20) NULL,
            `address` VARCHAR(255) NULL,
            `city` VARCHAR(100) NULL,
            `state` VARCHAR(100) NULL,
            `pincode` VARCHAR(12) NULL,
            `country` VARCHAR(100) NULL DEFAULT 'India',
            `email_verified_at` DATETIME NULL,
            `onboarding_step` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `meta` JSON NULL,
            `last_login_at` DATETIME NULL,
            `last_login_ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_users_email` (`email`),
            UNIQUE KEY `uniq_users_uuid` (`uuid`),
            KEY `idx_users_role_status` (`role`, `status`),
            KEY `idx_users_reseller` (`reseller_id`),
            KEY `idx_users_created` (`created_at`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}password_resets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(190) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `ip_address` VARCHAR(45) NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_reset_token` (`token_hash`),
            KEY `idx_reset_email` (`email`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}email_verifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `verified_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_verify_token` (`token_hash`),
            KEY `idx_verify_user` (`user_id`),
            CONSTRAINT `fk_verify_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ------------------------------------------------------- Resellers --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}resellers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `code` VARCHAR(20) NOT NULL,
            `company_name` VARCHAR(150) NOT NULL,
            `brand_name` VARCHAR(150) NULL,
            `brand_logo` VARCHAR(255) NULL,
            `brand_favicon` VARCHAR(255) NULL,
            `tagline` VARCHAR(190) NULL,
            `support_email` VARCHAR(190) NULL,
            `support_phone` VARCHAR(25) NULL,
            `support_whatsapp` VARCHAR(25) NULL,
            `domain` VARCHAR(190) NULL,
            `domain_status` ENUM('none','pending','verified','failed') NOT NULL DEFAULT 'none',
            `domain_token` VARCHAR(64) NULL,
            `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_customers` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_sales` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_reseller_code` (`code`),
            UNIQUE KEY `uniq_reseller_user` (`user_id`),
            UNIQUE KEY `uniq_reseller_domain` (`domain`),
            CONSTRAINT `fk_reseller_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}wallet_transactions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reseller_id` INT UNSIGNED NOT NULL,
            `type` ENUM('credit','debit') NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `balance_before` DECIMAL(12,2) NOT NULL,
            `balance_after` DECIMAL(12,2) NOT NULL,
            `reference_type` VARCHAR(40) NULL,
            `reference_id` INT UNSIGNED NULL,
            `description` VARCHAR(255) NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_wallet_reseller` (`reseller_id`, `created_at`),
            CONSTRAINT `fk_wallet_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `{$p}resellers` (`id`) ON DELETE CASCADE
        ) {$engine}");

        // ----------------------------------------------------------- Plans --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}plans` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(60) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `reseller_price` DECIMAL(10,2) NULL,
            `mrp` DECIMAL(10,2) NULL,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `duration_days` INT UNSIGNED NOT NULL DEFAULT 365,
            `trial_days` INT UNSIGNED NOT NULL DEFAULT 0,
            `card_limit` INT NOT NULL DEFAULT 1,
            `product_limit` INT NOT NULL DEFAULT 10,
            `service_limit` INT NOT NULL DEFAULT 10,
            `gallery_limit` INT NOT NULL DEFAULT 10,
            `video_limit` INT NOT NULL DEFAULT 3,
            `lead_limit` INT NOT NULL DEFAULT -1,
            `storage_limit_mb` INT NOT NULL DEFAULT 100,
            `premium_templates` TINYINT(1) NOT NULL DEFAULT 0,
            `custom_domain` TINYINT(1) NOT NULL DEFAULT 0,
            `remove_branding` TINYINT(1) NOT NULL DEFAULT 0,
            `analytics` TINYINT(1) NOT NULL DEFAULT 1,
            `qr_download` TINYINT(1) NOT NULL DEFAULT 1,
            `vcard` TINYINT(1) NOT NULL DEFAULT 1,
            `enquiry_form` TINYINT(1) NOT NULL DEFAULT 1,
            `seo_controls` TINYINT(1) NOT NULL DEFAULT 0,
            `api_access` TINYINT(1) NOT NULL DEFAULT 0,
            `priority_support` TINYINT(1) NOT NULL DEFAULT 0,
            `features` JSON NULL,
            `is_free` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plans_slug` (`slug`),
            KEY `idx_plans_active` (`is_active`, `sort_order`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}subscriptions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `plan_id` INT UNSIGNED NOT NULL,
            `order_id` INT UNSIGNED NULL,
            `reseller_id` INT UNSIGNED NULL,
            `status` ENUM('pending','active','expired','cancelled','failed','refunded') NOT NULL DEFAULT 'pending',
            `starts_at` DATETIME NULL,
            `ends_at` DATETIME NULL,
            `grace_until` DATETIME NULL,
            `cancelled_at` DATETIME NULL,
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `source` VARCHAR(30) NOT NULL DEFAULT 'self',
            `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
            `snapshot` JSON NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_subscription_uuid` (`uuid`),
            KEY `idx_sub_user_status` (`user_id`, `status`),
            KEY `idx_sub_ends` (`ends_at`),
            CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `{$p}plans` (`id`)
        ) {$engine}");

        // -------------------------------------------------- Orders/Payments --
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}orders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_number` VARCHAR(30) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `plan_id` INT UNSIGNED NOT NULL,
            `reseller_id` INT UNSIGNED NULL,
            `subscription_id` INT UNSIGNED NULL,
            `status` ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
            `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
            `gateway_order_id` VARCHAR(100) NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `customer_gstin` VARCHAR(20) NULL,
            `notes` VARCHAR(255) NULL,
            `meta` JSON NULL,
            `paid_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_order_number` (`order_number`),
            UNIQUE KEY `uniq_gateway_order` (`gateway_order_id`),
            KEY `idx_orders_user` (`user_id`, `status`),
            KEY `idx_orders_created` (`created_at`),
            CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `{$p}users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_order_plan` FOREIGN KEY (`plan_id`) REFERENCES `{$p}plans` (`id`)
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}payments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
            `gateway_payment_id` VARCHAR(100) NULL,
            `gateway_order_id` VARCHAR(100) NULL,
            `gateway_signature` VARCHAR(255) NULL,
            `method` VARCHAR(40) NULL,
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `status` ENUM('created','authorized','captured','failed','refunded') NOT NULL DEFAULT 'created',
            `error_code` VARCHAR(60) NULL,
            `error_description` VARCHAR(255) NULL,
            `verified_at` DATETIME NULL,
            `raw_response` JSON NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_gateway_payment` (`gateway_payment_id`),
            KEY `idx_payments_order` (`order_id`),
            KEY `idx_payments_user` (`user_id`),
            CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `{$p}orders` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}invoices` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_number` VARCHAR(30) NOT NULL,
            `order_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `billing_name` VARCHAR(150) NOT NULL,
            `billing_email` VARCHAR(190) NULL,
            `billing_phone` VARCHAR(25) NULL,
            `billing_address` VARCHAR(500) NULL,
            `billing_gstin` VARCHAR(20) NULL,
            `seller_gstin` VARCHAR(20) NULL,
            `place_of_supply` VARCHAR(100) NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `cgst` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `sgst` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `igst` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `status` ENUM('draft','issued','paid','cancelled') NOT NULL DEFAULT 'issued',
            `line_items` JSON NULL,
            `issued_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_invoice_number` (`invoice_number`),
            KEY `idx_invoice_user` (`user_id`),
            CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `{$p}orders` (`id`) ON DELETE CASCADE
        ) {$engine}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$p}webhook_events` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
            `event_id` VARCHAR(120) NOT NULL,
            `event_type` VARCHAR(80) NOT NULL,
            `payload_hash` CHAR(64) NOT NULL,
            `status` ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
            `error` VARCHAR(255) NULL,
            `payload` JSON NULL,
            `processed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_webhook_event` (`gateway`, `event_id`),
            KEY `idx_webhook_type` (`event_type`)
        ) {$engine}");
    },

    'down' => static function (Database $db): void {
        $p = $db->prefix();
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'webhook_events', 'invoices', 'payments', 'orders', 'subscriptions', 'plans',
            'wallet_transactions', 'resellers', 'email_verifications', 'password_resets',
            'users', 'role_permissions', 'permissions', 'roles',
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$p}{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    },
];
