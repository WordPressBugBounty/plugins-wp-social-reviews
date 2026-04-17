<?php

namespace WPSocialReviews\Database\Migrations;

class CacheMigrator
{
    static $tableName = 'wpsr_caches';

    public static function migrate()
    {
        global $wpdb;

		$charsetCollate = $wpdb->get_charset_collate();

		$table = $wpdb->prefix . static::$tableName;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check table existence
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            $sql = "CREATE TABLE $table (
                `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,		
				`platform` varchar(255) null,
				`name` varchar(255),
				`value` LONGTEXT null,
				`expiration` TIMESTAMP NULL,
				`failed_count` int(11) default 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `idx_platform` (`platform`),
                INDEX `idx_name` (`name`)
            ) $charsetCollate;";

            dbDelta($sql);
        } else {
            static::alterTable($table);
        }
    }

    public static function alterTable($table) 
    {
        static::addMissingIndexes($table);
    }

    public static function addMissingIndexes($table)
    {
        global $wpdb;

        // Sanitize table name to prevent injection
        $table = esc_sql($table);

        // Get existing indexes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check existing indexes
        $existing_indexes = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM %i", $table));
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            $existing_index_names[] = $index->Key_name;
        }

        // Define the indexes you want to ensure
        $indexes = [
            'idx_platform' => 'platform',
            'idx_name'     => 'name',
        ];

        // Add missing indexes
        foreach ($indexes as $index_name => $column_name) {
            if (!in_array($index_name, $existing_index_names)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to add missing indexes
                $wpdb->query($wpdb->prepare("ALTER TABLE %i ADD INDEX %i (%i)", $table, $index_name, $column_name));
            }
        }
    }
}
