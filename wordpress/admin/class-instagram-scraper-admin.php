<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/admin
 */

class Instagram_Scraper_Admin {

    private $plugin_name;
    private $version;
    private $options;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('instagram_scraper_options');

        add_action('admin_post_instagram_scraper_refresh_feed', array($this, 'refresh_feed'));
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/instagram-scraper-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/instagram-scraper-admin.js', array('jquery'), $this->version, false);

        wp_localize_script($this->plugin_name, 'instagram_scraper_admin', array(
            'refresh_confirm' => __('Are you sure you want to refresh the Instagram feed? This will clear the cache and fetch new data.', 'instagram-scraper'),
        ));
    }

    public function add_plugin_admin_menu() {
        add_options_page(
            __('Instagram Scraper Settings', 'instagram-scraper'),
            __('Instagram Feed', 'instagram-scraper'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );
    }

    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name) . '">' . __('Settings', 'instagram-scraper') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    public function display_plugin_setup_page() {
        include_once('partials/instagram-scraper-admin-display.php');
    }

    public function options_update() {
        register_setting(
            $this->plugin_name,
            'instagram_scraper_options',
            array($this, 'validate_options')
        );
    }

    public function validate_options($input) {
        $valid = array();

        // Validate JSON Feed URL
        $valid['feed_url'] = esc_url_raw($input['feed_url']);

        // Validate columns (1-4)
        $valid['columns'] = isset($input['columns']) ? intval($input['columns']) : 3;
        $valid['columns'] = max(1, min(4, $valid['columns']));

        // Validate image size
        $valid_sizes = array('thumbnail', 'medium', 'large', 'full');
        $valid['image_size'] = isset($input['image_size']) && in_array($input['image_size'], $valid_sizes)
            ? $input['image_size']
            : 'medium';

        // Validate post count (1-24)
        $valid['post_count'] = isset($input['post_count']) ? intval($input['post_count']) : 12;
        $valid['post_count'] = max(1, min(24, $valid['post_count']));

        // Validate cache duration (minimum 1 hour, maximum 7 days)
        $valid['cache_duration'] = isset($input['cache_duration']) ? intval($input['cache_duration']) : 3600;
        $valid['cache_duration'] = max(3600, min(604800, $valid['cache_duration']));

        // Preserve last updated timestamp
        $valid['last_updated'] = isset($this->options['last_updated']) ? $this->options['last_updated'] : 0;

        // If feed URL changed, clear cache to force refresh
        if (isset($this->options['feed_url']) && $valid['feed_url'] !== $this->options['feed_url']) {
            $cache = new Instagram_Scraper_Cache($this->plugin_name, $this->version);
            $cache->clear_cache();
        }

        return $valid;
    }

    public function refresh_feed() {
        if (!isset($_POST['instagram_scraper_nonce']) || !wp_verify_nonce($_POST['instagram_scraper_nonce'], 'instagram_scraper_refresh')) {
            wp_die(__('Security check failed', 'instagram-scraper'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action', 'instagram-scraper'));
        }

        $cache = new Instagram_Scraper_Cache($this->plugin_name, $this->version);
        $cache->clear_cache();

        $api = new Instagram_Scraper_API($this->plugin_name, $this->version);
        $success = $api->fetch_and_cache_data();

        $redirect_url = add_query_arg(
            array(
                'page'    => $this->plugin_name,
                'refresh' => $success ? 'success' : 'error',
            ),
            admin_url('options-general.php')
        );

        wp_redirect($redirect_url);
        exit;
    }
}
