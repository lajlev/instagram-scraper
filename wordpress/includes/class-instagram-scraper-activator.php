<?php
/**
 * Fired during plugin activation.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper_Activator {

    public static function activate() {
        // Add default options if they don't exist
        if (!get_option('instagram_scraper_options')) {
            $default_options = array(
                'feed_url'       => '',
                'columns'        => 3,
                'image_size'     => 'medium',
                'post_count'     => 12,
                'cache_duration' => 3600, // 1 hour
                'last_updated'   => 0,
            );
            add_option('instagram_scraper_options', $default_options);
        }

        // Schedule the cron job for daily updates
        if (!wp_next_scheduled('instagram_scraper_daily_update')) {
            $timestamp = strtotime('tomorrow 3:00 am');
            wp_schedule_event($timestamp, 'daily', 'instagram_scraper_daily_update');
        }

        // Create custom table to track saved images
        self::create_tables();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'instagram_scraper_images';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                instagram_id varchar(255) NOT NULL,
                media_id bigint(20) NOT NULL,
                url varchar(500) NOT NULL,
                timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY instagram_id (instagram_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
