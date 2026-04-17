<?php

namespace WPSocialReviews\Database\Migrations;

class ReviewFormsMigrator
{
    static $tableName = 'wpsr_review_forms';

    public static function migrate()
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . static::$tableName;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check table existence
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            $sql = "CREATE TABLE $table (
                `id`            INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title`         VARCHAR(255) NOT NULL,
                `slug`          VARCHAR(255) NOT NULL,
                `schema`        LONGTEXT NOT NULL,
                `settings`      LONGTEXT NULL,
                `status`        VARCHAR(20) DEFAULT 'active',
                `created_by`    INT(11) NULL,
                `created_at`    TIMESTAMP NULL,
                `updated_at`    TIMESTAMP NULL,
                INDEX `idx_status` (`status`),
                INDEX `idx_slug` (`slug`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
