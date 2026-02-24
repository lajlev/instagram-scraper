<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/public
 */

class Instagram_Scraper_Public {

    private $plugin_name;
    private $version;
    private $options;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('instagram_scraper_options');
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/instagram-scraper-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/instagram-scraper-public.js', array('jquery'), $this->version, false);
    }

    /**
     * Shortcode handler for [instagram_feed].
     *
     * @param array $atts Shortcode attributes.
     * @return string The shortcode output.
     */
    public function shortcode_handler($atts) {
        $default_count = isset($this->options['post_count']) ? $this->options['post_count'] : 12;

        $atts = shortcode_atts(
            array(
                'columns' => isset($this->options['columns']) ? $this->options['columns'] : 3,
                'size'    => isset($this->options['image_size']) ? $this->options['image_size'] : 'medium',
                'count'   => $default_count,
            ),
            $atts,
            'instagram_feed'
        );

        $columns = max(1, min(4, intval($atts['columns'])));
        $size = in_array($atts['size'], array('thumbnail', 'medium', 'large', 'full')) ? $atts['size'] : 'medium';
        $count = max(1, min(24, intval($atts['count'])));

        // Get Instagram data
        $api = new Instagram_Scraper_API($this->plugin_name, $this->version);
        $instagram_data = $api->get_instagram_data();

        ob_start();

        if (empty($instagram_data) || empty($instagram_data['data'])) {
            echo '<div class="ig-scraper-error">';
            echo esc_html__('No Instagram images found. Please check the plugin settings.', 'instagram-scraper');
            echo '</div>';
            return ob_get_clean();
        }

        $images = array_slice($instagram_data['data'], 0, $count);

        echo '<div class="ig-scraper-container">';
        echo '<div class="ig-scraper-grid ig-scraper-columns-' . esc_attr($columns) . '">';

        foreach ($images as $image) {
            $this->render_image_item($image, $size);
        }

        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Render a single image item.
     *
     * @param array  $image The image data.
     * @param string $size  The image size.
     */
    private function render_image_item($image, $size) {
        $attachment_id = isset($image['media_id']) ? $image['media_id'] : 0;

        if (!$attachment_id) {
            return;
        }

        $image_url = wp_get_attachment_image_url($attachment_id, $size);

        if (!$image_url) {
            return;
        }

        $caption = isset($image['caption']) ? $image['caption'] : '';
        $permalink = isset($image['permalink']) ? $image['permalink'] : '';
        $timestamp = isset($image['timestamp']) ? strtotime($image['timestamp']) : 0;

        echo '<div class="ig-scraper-item">';

        if ($permalink) {
            echo '<a href="' . esc_url($permalink) . '" target="_blank" rel="noopener noreferrer">';
        }

        echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(wp_trim_words($caption, 10)) . '" loading="lazy" />';

        if ($permalink) {
            echo '</a>';
        }

        if ($caption) {
            echo '<div class="ig-scraper-caption">';
            echo '<div class="ig-scraper-caption-inner">';
            echo esc_html($caption);
            echo '</div>';
            echo '</div>';
        }

        if ($timestamp) {
            echo '<div class="ig-scraper-date">';
            echo esc_html(date_i18n(get_option('date_format'), $timestamp));
            echo '</div>';
        }

        echo '</div>';
    }
}
