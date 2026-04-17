<?php

namespace WPSocialReviews\Database\Migrations;

class ReviewsMigrator
{
    static $tableName = 'wpsr_reviews';

    public static function migrate()
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . static::$tableName;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check table existence
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            $sql = "CREATE TABLE $table (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,		
				`platform_name` varchar(255),
				`source_id` varchar(255),
				`review_id` varchar(255),
				`category` varchar(255),
				`review_title` varchar(255),
				`reviewer_name` varchar(255),
				`reviewer_email` varchar(255) DEFAULT NULL,
				`reviewer_url` varchar(255),
				`reviewer_img` TEXT NULL,
				`reviewer_text` LONGTEXT NULL,
				`review_time` timestamp NULL,
				`rating` int(11),
				`review_approved` int(11) DEFAULT 1,
				`recommendation_type` varchar(255),
				`fields` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_platform_name` (`platform_name`),
                INDEX `idx_review_id` (`review_id`),
                INDEX `idx_source_id` (`source_id`),
                INDEX `idx_review_approved` (`review_approved`),
                INDEX `idx_rating` (`rating`),
                INDEX `idx_reviewer_email` (`reviewer_email`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            static::alterTable($table);
        }
    }

    public static function alterTable($table)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check existing columns
        $existing_columns = $wpdb->get_col($wpdb->prepare("DESC %i", $table), 0);
        if(!in_array('category', $existing_columns)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing column
            $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD %i varchar(255) NULL AFTER %i', $table, 'category', 'source_id'));
        }

        if(!in_array('review_approved', $existing_columns)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing column
            $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD %i int(11) DEFAULT 1 AFTER %i', $table, 'review_approved', 'recommendation_type'));
        }

        if(!in_array('review_id', $existing_columns)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing column
            $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD %i varchar(255) NULL AFTER %i', $table, 'review_id', 'source_id'));
        }

        if(!in_array('fields', $existing_columns)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing column
            $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD %i LONGTEXT NULL AFTER %i', $table, 'fields', 'recommendation_type'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to modify column
        $wpdb->query($wpdb->prepare("ALTER TABLE %i MODIFY COLUMN %i TEXT NULL", $table, 'reviewer_img'));

        if (!in_array('reviewer_email', $existing_columns)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing column
            $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD %i varchar(255) DEFAULT NULL AFTER %i', $table, 'reviewer_email', 'reviewer_name'));
        }

        static::addMissingIndexes($table);
    }

    public static function addMissingIndexes($table)
    {
        global $wpdb;

        // Safely escape table name
        $table = esc_sql($table);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check existing indexes
        $existing_indexes = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM %i", $table));
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            $existing_index_names[] = $index->Key_name;
        }

        $indexes = [
            'idx_platform_name' => 'platform_name',
            'idx_review_id'     => 'review_id',
            'idx_source_id'     => 'source_id',
            'idx_review_approved'     => 'review_approved',
            'idx_rating'        => 'rating',
        ];

        foreach ($indexes as $index_name => $column_name) {
            if (!in_array($index_name, $existing_index_names)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing indexes
                $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD INDEX %i (%i)", $table, $index_name, $column_name));
            }
        }

        // Index on reviewer_email for duplicate-check query performance.
        // Uniqueness is enforced at the application layer (respects one_review_per_email toggle).
        if (!in_array('idx_reviewer_email', $existing_index_names)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add index
            $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD INDEX %i (%i)", $table, 'idx_reviewer_email', 'reviewer_email'));
        }
    }
}
