<?php
/**
 * The caching functionality of the plugin.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper_Cache {

    private $plugin_name;
    private $version;
    private $options;
    private $cache_key = 'instagram_scraper_feed_data';

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('instagram_scraper_options');
    }

    private function get_cache_duration() {
        $default_duration = 3600; // 1 hour
        $duration = isset($this->options['cache_duration']) ? (int) $this->options['cache_duration'] : $default_duration;
        return max($duration, 3600);
    }

    public function get_cache() {
        $cached_data = get_transient($this->cache_key);

        if (false === $cached_data) {
            return false;
        }

        if (!isset($cached_data['data']) || !is_array($cached_data['data'])) {
            return false;
        }

        return $cached_data;
    }

    public function update_cache($data) {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        $duration = $this->get_cache_duration();

        $cache_data = array(
            'timestamp' => time(),
            'data'      => $data,
        );

        return set_transient($this->cache_key, $cache_data, $duration);
    }

    public function clear_cache() {
        return delete_transient($this->cache_key);
    }

    public function is_cache_valid() {
        $cached_data = $this->get_cache();

        if (false === $cached_data || empty($cached_data['timestamp'])) {
            return false;
        }

        $cache_time = (int) $cached_data['timestamp'];
        $cache_duration = $this->get_cache_duration();

        if (($cache_time + $cache_duration) < time()) {
            return false;
        }

        return true;
    }

    public function get_cache_age() {
        $cached_data = $this->get_cache();

        if (false === $cached_data || empty($cached_data['timestamp'])) {
            return false;
        }

        return time() - (int) $cached_data['timestamp'];
    }

    public function get_cache_expiration() {
        $cached_data = $this->get_cache();

        if (false === $cached_data || empty($cached_data['timestamp'])) {
            return false;
        }

        $cache_time = (int) $cached_data['timestamp'];
        $cache_duration = $this->get_cache_duration();

        return max(0, ($cache_time + $cache_duration) - time());
    }
}
