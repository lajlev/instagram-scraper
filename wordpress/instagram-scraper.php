<?php
/**
 * Instagram Scraper Feed
 *
 * @package           InstagramScraper
 * @author            Michael Lajlev
 * @copyright         2025 lillefar.dk
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Instagram Scraper Feed
 * Plugin URI:        https://github.com/lajlev/instagram-scraper
 * Description:       Display Instagram images from a JSON feed hosted on Google Cloud Storage.
 * Version:           2.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Michael Lajlev
 * Author URI:        https://lillefar.dk
 * Text Domain:       instagram-scraper
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('INSTAGRAM_SCRAPER_VERSION', '2.0.0');
define('INSTAGRAM_SCRAPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INSTAGRAM_SCRAPER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INSTAGRAM_SCRAPER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_instagram_scraper() {
    require_once INSTAGRAM_SCRAPER_PLUGIN_DIR . 'includes/class-instagram-scraper-activator.php';
    Instagram_Scraper_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_instagram_scraper() {
    require_once INSTAGRAM_SCRAPER_PLUGIN_DIR . 'includes/class-instagram-scraper-deactivator.php';
    Instagram_Scraper_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_instagram_scraper');
register_deactivation_hook(__FILE__, 'deactivate_instagram_scraper');

/**
 * The core plugin class.
 */
require_once INSTAGRAM_SCRAPER_PLUGIN_DIR . 'includes/class-instagram-scraper.php';

/**
 * Begins execution of the plugin.
 */
function run_instagram_scraper() {
    $plugin = new Instagram_Scraper();
    $plugin->run();
}

run_instagram_scraper();
