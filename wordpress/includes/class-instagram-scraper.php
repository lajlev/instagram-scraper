<?php
/**
 * The core plugin class.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if (defined('INSTAGRAM_SCRAPER_VERSION')) {
            $this->version = INSTAGRAM_SCRAPER_VERSION;
        } else {
            $this->version = '2.0.0';
        }
        $this->plugin_name = 'instagram-scraper';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
        $this->check_cron_health();
    }

    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-instagram-scraper-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-instagram-scraper-i18n.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-instagram-scraper-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-instagram-scraper-cache.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-instagram-scraper-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-instagram-scraper-public.php';

        $this->loader = new Instagram_Scraper_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new Instagram_Scraper_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        $plugin_admin = new Instagram_Scraper_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_filter('plugin_action_links_' . INSTAGRAM_SCRAPER_PLUGIN_BASENAME, $plugin_admin, 'add_action_links');
        $this->loader->add_action('admin_init', $plugin_admin, 'options_update');
    }

    private function define_public_hooks() {
        $plugin_public = new Instagram_Scraper_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_shortcode('instagram_feed', $plugin_public, 'shortcode_handler');
    }

    private function define_cron_hooks() {
        $plugin_api = new Instagram_Scraper_API($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('instagram_scraper_daily_update', $plugin_api, 'fetch_and_cache_data');
        $this->loader->add_action('instagram_scraper_daily_update', $this, 'log_cron_execution');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public function log_cron_execution() {
        error_log('[Instagram Scraper] Cron job executed at: ' . date('Y-m-d H:i:s'));
    }

    private function check_cron_health() {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            error_log('[Instagram Scraper] WARNING: WP_CRON is disabled in wp-config.php.');
        }

        $next_scheduled = wp_next_scheduled('instagram_scraper_daily_update');
        if (!$next_scheduled) {
            $timestamp = strtotime('tomorrow 3:00 am');
            wp_schedule_event($timestamp, 'daily', 'instagram_scraper_daily_update');
        }
    }
}
