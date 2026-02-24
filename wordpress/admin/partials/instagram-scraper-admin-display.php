<?php
/**
 * Admin area view for the plugin.
 *
 * @since      2.0.0
 * @package    Instagram_Scraper
 * @subpackage Instagram_Scraper/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get plugin options
$options = get_option('instagram_scraper_options');
$feed_url = isset($options['feed_url']) ? $options['feed_url'] : '';
$columns = isset($options['columns']) ? intval($options['columns']) : 3;
$image_size = isset($options['image_size']) ? $options['image_size'] : 'medium';
$post_count = isset($options['post_count']) ? intval($options['post_count']) : 12;
$cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 3600;
$last_updated = isset($options['last_updated']) ? intval($options['last_updated']) : 0;

// Get cache status
$cache = new Instagram_Scraper_Cache($this->plugin_name, $this->version);
$cache_valid = $cache->is_cache_valid();
$cache_age = $cache->get_cache_age();
$cache_expiration = $cache->get_cache_expiration();

// Get last log message if any
$last_error = get_transient('instagram_scraper_last_error');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    if (isset($_GET['refresh'])) {
        if ($_GET['refresh'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__("Instagram feed refreshed successfully!", 'instagram-scraper') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__("Error refreshing Instagram feed. Please check your feed URL and try again.", 'instagram-scraper') . '</p></div>';
        }
    }

    if ($last_error) {
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__("Last log: ", 'instagram-scraper') . esc_html($last_error) . '</p></div>';
    }
    ?>

    <div class="ig-scraper-admin-content">
        <div class="ig-scraper-admin-main">
            <form method="post" action="options.php">
                <?php
                settings_fields($this->plugin_name);
                do_settings_sections($this->plugin_name);
                ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('JSON Feed URL', 'instagram-scraper'); ?></th>
                        <td>
                            <input type="url" name="instagram_scraper_options[feed_url]" value="<?php echo esc_attr($feed_url); ?>" class="regular-text" placeholder="https://storage.googleapis.com/your-bucket/posts.json" />
                            <p class="description"><?php esc_html_e('The URL to your posts.json file hosted on Google Cloud Storage.', 'instagram-scraper'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Grid Columns', 'instagram-scraper'); ?></th>
                        <td>
                            <select name="instagram_scraper_options[columns]">
                                <option value="1" <?php selected($columns, 1); ?>><?php esc_html_e('1 Column', 'instagram-scraper'); ?></option>
                                <option value="2" <?php selected($columns, 2); ?>><?php esc_html_e('2 Columns', 'instagram-scraper'); ?></option>
                                <option value="3" <?php selected($columns, 3); ?>><?php esc_html_e('3 Columns', 'instagram-scraper'); ?></option>
                                <option value="4" <?php selected($columns, 4); ?>><?php esc_html_e('4 Columns', 'instagram-scraper'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Number of columns to display in the grid.', 'instagram-scraper'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Image Size', 'instagram-scraper'); ?></th>
                        <td>
                            <select name="instagram_scraper_options[image_size]">
                                <option value="thumbnail" <?php selected($image_size, 'thumbnail'); ?>><?php esc_html_e('Thumbnail', 'instagram-scraper'); ?></option>
                                <option value="medium" <?php selected($image_size, 'medium'); ?>><?php esc_html_e('Medium', 'instagram-scraper'); ?></option>
                                <option value="large" <?php selected($image_size, 'large'); ?>><?php esc_html_e('Large', 'instagram-scraper'); ?></option>
                                <option value="full" <?php selected($image_size, 'full'); ?>><?php esc_html_e('Full Size', 'instagram-scraper'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Size of images to display in the grid.', 'instagram-scraper'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Post Count', 'instagram-scraper'); ?></th>
                        <td>
                            <input type="number" name="instagram_scraper_options[post_count]" value="<?php echo esc_attr($post_count); ?>" min="1" max="24" class="small-text" />
                            <p class="description"><?php esc_html_e('Number of posts to download and display (1-24).', 'instagram-scraper'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Cache Duration', 'instagram-scraper'); ?></th>
                        <td>
                            <select name="instagram_scraper_options[cache_duration]">
                                <option value="3600" <?php selected($cache_duration, 3600); ?>><?php esc_html_e('1 Hour', 'instagram-scraper'); ?></option>
                                <option value="21600" <?php selected($cache_duration, 21600); ?>><?php esc_html_e('6 Hours', 'instagram-scraper'); ?></option>
                                <option value="43200" <?php selected($cache_duration, 43200); ?>><?php esc_html_e('12 Hours', 'instagram-scraper'); ?></option>
                                <option value="86400" <?php selected($cache_duration, 86400); ?>><?php esc_html_e('1 Day', 'instagram-scraper'); ?></option>
                                <option value="172800" <?php selected($cache_duration, 172800); ?>><?php esc_html_e('2 Days', 'instagram-scraper'); ?></option>
                                <option value="604800" <?php selected($cache_duration, 604800); ?>><?php esc_html_e('1 Week', 'instagram-scraper'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('How long to cache the Instagram data before re-fetching.', 'instagram-scraper'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <div class="ig-scraper-admin-sidebar">
            <div class="ig-scraper-admin-box">
                <h3><?php esc_html_e('Cache Status', 'instagram-scraper'); ?></h3>
                <p>
                    <?php if ($cache_valid): ?>
                        <span class="ig-scraper-status ig-scraper-status-valid"><?php esc_html_e('Valid', 'instagram-scraper'); ?></span>
                    <?php else: ?>
                        <span class="ig-scraper-status ig-scraper-status-invalid"><?php esc_html_e('Invalid or Empty', 'instagram-scraper'); ?></span>
                    <?php endif; ?>
                </p>

                <?php if ($last_updated): ?>
                    <p><?php esc_html_e('Last Updated:', 'instagram-scraper'); ?> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_updated)); ?></p>
                <?php endif; ?>

                <?php if ($cache_age): ?>
                    <p><?php esc_html_e('Cache Age:', 'instagram-scraper'); ?> <?php echo esc_html(human_time_diff(time() - $cache_age, time())); ?></p>
                <?php endif; ?>

                <?php if ($cache_expiration): ?>
                    <p><?php esc_html_e('Expires In:', 'instagram-scraper'); ?> <?php echo esc_html(human_time_diff(time(), time() + $cache_expiration)); ?></p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="instagram_scraper_refresh_feed" />
                    <?php wp_nonce_field('instagram_scraper_refresh', 'instagram_scraper_nonce'); ?>
                    <?php submit_button(__("Refresh Now", 'instagram-scraper'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div class="ig-scraper-admin-box">
                <h3><?php esc_html_e('Shortcode Usage', 'instagram-scraper'); ?></h3>
                <p><?php esc_html_e('Use this shortcode to display the Instagram feed on your site:', 'instagram-scraper'); ?></p>
                <code>[instagram_feed]</code>

                <p><?php esc_html_e('With custom attributes:', 'instagram-scraper'); ?></p>
                <code>[instagram_feed columns="2" size="large" count="6"]</code>

                <h4><?php esc_html_e('Available Attributes:', 'instagram-scraper'); ?></h4>
                <ul>
                    <li><code>columns</code>: <?php esc_html_e('Number of columns (1-4)', 'instagram-scraper'); ?></li>
                    <li><code>size</code>: <?php esc_html_e('Image size (thumbnail, medium, large, full)', 'instagram-scraper'); ?></li>
                    <li><code>count</code>: <?php esc_html_e('Number of images to display', 'instagram-scraper'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
