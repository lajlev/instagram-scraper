<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper_Deactivator {

    public static function deactivate() {
        // Clear the scheduled cron job
        $timestamp = wp_next_scheduled('instagram_scraper_daily_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'instagram_scraper_daily_update');
        }

        // Clear any transients
        delete_transient('instagram_scraper_feed_data');
        delete_transient('instagram_scraper_last_error');
    }
}
