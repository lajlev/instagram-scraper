<?php
/**
 * Define the internationalization functionality.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper_i18n {

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'instagram-scraper',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
