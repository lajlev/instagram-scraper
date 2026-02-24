<?php
/**
 * The API functionality of the plugin.
 *
 * Handles fetching Instagram data from the GCS-hosted JSON feed
 * and downloading images to the WordPress media library.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/includes
 */

class Instagram_Scraper_API {

    private $plugin_name;
    private $version;
    private $options;
    private $cache;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('instagram_scraper_options');
        $this->cache = new Instagram_Scraper_Cache($plugin_name, $version);
    }

    /**
     * Fetch data from the GCS JSON feed and cache it.
     *
     * @return bool True on success, false on failure.
     */
    public function fetch_and_cache_data() {
        $this->log_message("Starting fetch_and_cache_data - " . date('Y-m-d H:i:s'));
        $feed_url = isset($this->options['feed_url']) ? $this->options['feed_url'] : '';

        if (empty($feed_url)) {
            $this->log_message("Error: No JSON feed URL configured");
            return false;
        }

        $this->log_message("Fetching: {$feed_url}");

        $response = wp_remote_get($feed_url, array(
            'timeout' => 60,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            $this->log_message("Request Error: " . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_message("HTTP Error: {$response_code}");
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['posts']) || !is_array($data['posts'])) {
            $this->log_message("Error: Invalid JSON structure");
            return false;
        }

        $this->log_message("Received " . count($data['posts']) . " posts from feed");

        // Process and save images
        $post_count = isset($this->options['post_count']) ? (int) $this->options['post_count'] : 12;
        $processed_data = $this->process_instagram_data($data['posts'], $post_count);
        $this->log_message("Processed " . count($processed_data) . " posts");

        // Update the cache
        $cache_result = $this->cache->update_cache($processed_data);

        // Update last updated timestamp
        $this->options['last_updated'] = time();
        update_option('instagram_scraper_options', $this->options);

        $this->log_message("Completed fetch_and_cache_data");
        return $cache_result;
    }

    /**
     * Process Instagram data from the JSON feed.
     *
     * @param array $posts The posts array from the JSON feed.
     * @param int   $limit Maximum number of posts to process.
     * @return array The processed data.
     */
    private function process_instagram_data($posts, $limit = 12) {
        $processed_data = array();

        // Sort by timestamp (newest first)
        usort($posts, function ($a, $b) {
            $time_a = strtotime($a['timestamp'] ?? '');
            $time_b = strtotime($b['timestamp'] ?? '');
            return $time_b - $time_a;
        });

        $posts = array_slice($posts, 0, $limit);

        foreach ($posts as $item) {
            if (empty($item['id']) || empty($item['image_url'])) {
                continue;
            }

            $instagram_id = sanitize_text_field($item['id']);
            $image_url = esc_url_raw($item['image_url']);
            $permalink = isset($item['permalink']) ? esc_url_raw($item['permalink']) : '';
            $caption = isset($item['caption']) ? sanitize_textarea_field($item['caption']) : '';
            $timestamp = isset($item['timestamp']) ? sanitize_text_field($item['timestamp']) : '';

            // Check if image is already saved in media library
            $media_id = $this->get_saved_image_id($instagram_id);

            // If not saved, download and save the image
            if (!$media_id) {
                $media_id = $this->save_instagram_image($image_url, $instagram_id, $caption);
            }

            if ($media_id) {
                $processed_data[] = array(
                    'instagram_id' => $instagram_id,
                    'media_id'     => $media_id,
                    'permalink'    => $permalink,
                    'caption'      => $caption,
                    'timestamp'    => $timestamp,
                    'url'          => $image_url,
                    'is_video'     => !empty($item['is_video']),
                    'video_url'    => isset($item['video_url']) ? esc_url_raw($item['video_url']) : '',
                    'likes'        => isset($item['likes']) ? (int) $item['likes'] : 0,
                    'comments'     => isset($item['comments']) ? (int) $item['comments'] : 0,
                    'hashtags'     => isset($item['hashtags']) ? array_map('sanitize_text_field', $item['hashtags']) : array(),
                );
            }
        }

        return $processed_data;
    }

    /**
     * Check if an Instagram image is already saved in the media library.
     *
     * @param string $instagram_id The Instagram post ID (shortcode).
     * @return int|bool The attachment ID if found, false otherwise.
     */
    private function get_saved_image_id($instagram_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'instagram_scraper_images';

        $media_id = $wpdb->get_var($wpdb->prepare(
            "SELECT media_id FROM $table_name WHERE instagram_id = %s",
            $instagram_id
        ));

        return $media_id ? (int) $media_id : false;
    }

    /**
     * Save an Instagram image to the media library.
     *
     * @param string $url          The image URL.
     * @param string $instagram_id The Instagram post ID.
     * @param string $caption      The image caption.
     * @return int|bool The attachment ID if successful, false otherwise.
     */
    private function save_instagram_image($url, $instagram_id, $caption = '') {
        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_message("Failed to download image for post: {$instagram_id}");
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        $filename = 'instagram-' . $instagram_id . '.jpg';

        $upload = wp_upload_bits($filename, null, $image_data);

        if ($upload['error']) {
            $this->log_message("Upload error for {$instagram_id}: " . $upload['error']);
            return false;
        }

        $wp_filetype = wp_check_filetype($filename, null);

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'    => sprintf(__('Instagram Image %s', 'instagram-scraper'), $instagram_id),
            'post_content'  => $caption,
            'post_status'   => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);

        if (!$attachment_id) {
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        $this->save_image_relationship($instagram_id, $attachment_id, $url);

        return $attachment_id;
    }

    /**
     * Save the relationship between Instagram ID and media ID.
     */
    private function save_image_relationship($instagram_id, $media_id, $url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'instagram_scraper_images';

        $wpdb->insert(
            $table_name,
            array(
                'instagram_id' => $instagram_id,
                'media_id'     => $media_id,
                'url'          => $url,
                'timestamp'    => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s')
        );
    }

    /**
     * Log a message.
     */
    private function log_message($message) {
        error_log('[Instagram Scraper] ' . $message);
        set_transient('instagram_scraper_last_error', $message, DAY_IN_SECONDS);

        $log_dir = WP_CONTENT_DIR . '/instagram-scraper-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/debug.log';
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($log_file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Get the latest Instagram data.
     *
     * @return array The Instagram data.
     */
    public function get_instagram_data() {
        $data = $this->cache->get_cache();

        if (empty($data)) {
            $this->fetch_and_cache_data();
            $data = $this->cache->get_cache();
        }

        return $data;
    }
}
