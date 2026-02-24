<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('instagram_scraper_options');

// Delete transients
delete_transient('instagram_scraper_feed_data');
delete_transient('instagram_scraper_last_error');

// Remove scheduled cron events
$timestamp = wp_next_scheduled('instagram_scraper_daily_update');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'instagram_scraper_daily_update');
}

// Drop custom table
global $wpdb;
$table_name = $wpdb->prefix . 'instagram_scraper_images';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
