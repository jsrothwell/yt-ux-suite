<?php
/**
 * Plugin Name: YouTube User Experience Suite
 * Plugin URI: https://example.com/yt-ux-suite
 * Description: Enhance user experience with lazy loading, responsive video player, search, notification bar, and timestamp links
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: yt-ux
 */

if (!defined('ABSPATH')) {
    exit;
}

class YTUXSuite {
    private $version = '1.0.0';
    
    public function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_head', [$this, 'add_inline_styles']);
        add_action('wp_footer', [$this, 'add_notification_bar']);
        
        // Content filters
        add_filter('the_content', [$this, 'process_video_embeds']);
        add_filter('embed_oembed_html', [$this, 'make_embeds_responsive'], 10, 4);
        
        // AJAX hooks
        add_action('wp_ajax_ytux_dismiss_notification', [$this, 'dismiss_notification']);
        add_action('wp_ajax_nopriv_ytux_dismiss_notification', [$this, 'dismiss_notification']);
        add_action('wp_ajax_ytux_video_search', [$this, 'handle_video_search']);
        add_action('wp_ajax_nopriv_ytux_video_search', [$this, 'handle_video_search']);
        
        // Shortcodes
        add_shortcode('video_search', [$this, 'video_search_shortcode']);
        add_shortcode('yt_timestamp', [$this, 'timestamp_link_shortcode']);
        add_shortcode('latest_videos', [$this, 'latest_videos_shortcode']);
        
        // Widget
        add_action('widgets_init', [$this, 'register_widgets']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    public function activate() {
        // Set default options
        add_option('ytux_lazy_load', '1');
        add_option('ytux_responsive_embeds', '1');
        add_option('ytux_enable_search', '1');
        add_option('ytux_notification_bar', '1');
        add_option('ytux_notification_text', 'New video uploaded! Check it out →');
        add_option('ytux_notification_duration', '7');
        add_option('ytux_notification_position', 'top');
        add_option('ytux_optimize_thumbnails', '1');
        add_option('ytux_preload_strategy', 'metadata');
        add_option('ytux_enable_keyboard', '1');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'YT User Experience',
            'YT UX',
            'manage_options',
            'yt-ux',
            [$this, 'admin_dashboard_page'],
            'dashicons-visibility',
            31
        );
        
        add_submenu_page(
            'yt-ux',
            'Settings',
            'Settings',
            'manage_options',
            'yt-ux-settings',
            [$this, 'admin_settings_page']
        );
    }
    
    public function register_settings() {
        // Performance Settings
        register_setting('ytux_settings', 'ytux_lazy_load');
        register_setting('ytux_settings', 'ytux_optimize_thumbnails');
        register_setting('ytux_settings', 'ytux_preload_strategy');
        
        // Video Player Settings
        register_setting('ytux_settings', 'ytux_responsive_embeds');
        register_setting('ytux_settings', 'ytux_enable_keyboard');
        register_setting('ytux_settings', 'ytux_autoplay');
        
        // Search Settings
        register_setting('ytux_settings', 'ytux_enable_search');
        register_setting('ytux_settings', 'ytux_search_placeholder');
        
        // Notification Bar Settings
        register_setting('ytux_settings', 'ytux_notification_bar');
        register_setting('ytux_settings', 'ytux_notification_text');
        register_setting('ytux_settings', 'ytux_notification_link');
        register_setting('ytux_settings', 'ytux_notification_duration');
        register_setting('ytux_settings', 'ytux_notification_position');
        register_setting('ytux_settings', 'ytux_notification_color');
        register_setting('ytux_settings', 'ytux_notification_bg');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'yt-ux') === false) {
            return;
        }
        
        wp_enqueue_style('ytux-admin-css', plugin_dir_url(__FILE__) . 'css/admin.css', [], $this->version);
        wp_enqueue_script('ytux-admin-js', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery'], $this->version, true);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('ytux-frontend-css', plugin_dir_url(__FILE__) . 'css/frontend.css', [], $this->version);
        wp_enqueue_script('ytux-frontend-js', plugin_dir_url(__FILE__) . 'js/frontend.js', ['jquery'], $this->version, true);
        
        // Lazy loading library
        if (get_option('ytux_lazy_load', '1')) {
            wp_enqueue_script('lozad', 'https://cdn.jsdelivr.net/npm/lozad@1.16.0/dist/lozad.min.js', [], '1.16.0', true);
        }
        
        wp_localize_script('ytux-frontend-js', 'ytuxAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ytux_nonce'),
            'lazyLoad' => get_option('ytux_lazy_load', '1'),
            'enableKeyboard' => get_option('ytux_enable_keyboard', '1'),
            'searchPlaceholder' => get_option('ytux_search_placeholder', 'Search videos...')
        ]);
    }
    
    public function add_inline_styles() {
        $notification_color = get_option('ytux_notification_color', '#ffffff');
        $notification_bg = get_option('ytux_notification_bg', '#ff0000');
        $notification_position = get_option('ytux_notification_position', 'top');
        
        ?>
        <style>
            .ytux-notification-bar {
                background: <?php echo esc_attr($notification_bg); ?>;
                color: <?php echo esc_attr($notification_color); ?>;
                <?php echo $notification_position === 'top' ? 'top: 0;' : 'bottom: 0;'; ?>
            }
            
            .ytux-notification-bar a {
                color: <?php echo esc_attr($notification_color); ?>;
            }
        </style>
        <?php
    }
    
    public function add_notification_bar() {
        if (!get_option('ytux_notification_bar', '1')) {
            return;
        }
        
        // Check if user has dismissed notification
        $dismissed = isset($_COOKIE['ytux_notification_dismissed']) ? $_COOKIE['ytux_notification_dismissed'] : 0;
        $duration = get_option('ytux_notification_duration', '7');
        
        // Check if notification should be shown
        $latest_post = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($latest_post)) {
            return;
        }
        
        $post_date = strtotime($latest_post[0]->post_date);
        $days_old = (time() - $post_date) / (60 * 60 * 24);
        
        // Don't show if post is older than duration setting
        if ($days_old > $duration) {
            return;
        }
        
        // Don't show if already dismissed
        if ($dismissed == $latest_post[0]->ID) {
            return;
        }
        
        $notification_text = get_option('ytux_notification_text', 'New video uploaded! Check it out →');
        $notification_link = get_option('ytux_notification_link', '');
        
        if (empty($notification_link)) {
            $notification_link = get_permalink($latest_post[0]->ID);
        }
        
        ?>
        <div class="ytux-notification-bar" data-post-id="<?php echo $latest_post[0]->ID; ?>">
            <div class="ytux-notification-content">
                <a href="<?php echo esc_url($notification_link); ?>" class="ytux-notification-link">
                    <span class="ytux-notification-icon">🎬</span>
                    <?php echo esc_html($notification_text); ?>
                </a>
                <button class="ytux-notification-close" aria-label="Close notification">×</button>
            </div>
        </div>
        <?php
    }
    
    public function dismiss_notification() {
        check_ajax_referer('ytux_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        setcookie('ytux_notification_dismissed', $post_id, time() + (86400 * 30), '/');
        
        wp_send_json_success();
    }
    
    public function process_video_embeds($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $lazy_load = get_option('ytux_lazy_load', '1');
        
        if ($lazy_load) {
            // Replace YouTube iframes with lazy loading version
            $content = preg_replace_callback(
                '/<iframe[^>]*src=["\']([^"\']*youtube[^"\']*)["\'][^>]*>.*?<\/iframe>/i',
                function($matches) {
                    $iframe = $matches[0];
                    
                    // Extract src
                    preg_match('/src=["\']([^"\']*)["\']/', $iframe, $src_match);
                    $src = $src_match[1];
                    
                    // Replace src with data-src for lazy loading
                    $iframe = str_replace('src="' . $src . '"', 'data-src="' . $src . '" src="about:blank"', $iframe);
                    $iframe = str_replace("src='" . $src . "'", "data-src='" . $src . "' src='about:blank'", $iframe);
                    
                    // Add lazy loading class
                    $iframe = str_replace('<iframe', '<iframe class="lozad ytux-lazy-video"', $iframe);
                    
                    return $iframe;
                },
                $content
            );
        }
        
        return $content;
    }
    
    public function make_embeds_responsive($html, $url, $attr, $post_id) {
        if (!get_option('ytux_responsive_embeds', '1')) {
            return $html;
        }
        
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return '<div class="ytux-responsive-embed">' . $html . '</div>';
        }
        
        return $html;
    }
    
    public function handle_video_search() {
        check_ajax_referer('ytux_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search']);
        
        $args = [
            'post_type' => 'post',
            'posts_per_page' => 10,
            's' => $search_term,
            'orderby' => 'relevance'
        ];
        
        $query = new WP_Query($args);
        
        $results = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $results[] = [
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                    'date' => get_the_date()
                ];
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success($results);
    }
    
    // SHORTCODE: Video Search
    public function video_search_shortcode($atts) {
        $atts = shortcode_atts([
            'placeholder' => get_option('ytux_search_placeholder', 'Search videos...'),
            'button_text' => 'Search'
        ], $atts);
        
        if (!get_option('ytux_enable_search', '1')) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ytux-video-search">
            <form class="ytux-search-form">
                <input type="text" 
                       name="video_search" 
                       class="ytux-search-input" 
                       placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                       autocomplete="off">
                <button type="submit" class="ytux-search-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>
                    </svg>
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </form>
            
            <div class="ytux-search-results" style="display: none;">
                <div class="ytux-search-results-header">
                    <span class="ytux-results-count"></span>
                    <button class="ytux-clear-search">Clear</button>
                </div>
                <div class="ytux-results-grid"></div>
            </div>
            
            <div class="ytux-search-loading" style="display: none;">
                <div class="ytux-spinner"></div>
                Searching...
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // SHORTCODE: Timestamp Link
    public function timestamp_link_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'video_url' => '',
            'time' => '0',
            'text' => $content
        ], $atts);
        
        if (empty($atts['video_url'])) {
            return $content;
        }
        
        // Convert time to seconds if in MM:SS or HH:MM:SS format
        $seconds = $this->parse_timestamp($atts['time']);
        
        // Add timestamp parameter to URL
        $url = add_query_arg('t', $seconds . 's', $atts['video_url']);
        
        return sprintf(
            '<a href="%s" class="ytux-timestamp-link" data-time="%s" target="_blank">%s</a>',
            esc_url($url),
            esc_attr($seconds),
            esc_html($atts['text'] ?: $atts['time'])
        );
    }
    
    // SHORTCODE: Latest Videos Grid
    public function latest_videos_shortcode($atts) {
        $atts = shortcode_atts([
            'count' => 6,
            'columns' => 3
        ], $atts);
        
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $atts['count'],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            return '<p>No videos found.</p>';
        }
        
        ob_start();
        ?>
        <div class="ytux-latest-videos ytux-grid-<?php echo esc_attr($atts['columns']); ?>">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <div class="ytux-video-card">
                    <a href="<?php the_permalink(); ?>" class="ytux-video-card-link">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="ytux-video-thumbnail">
                                <?php 
                                if (get_option('ytux_lazy_load', '1')) {
                                    echo '<img class="lozad" data-src="' . get_the_post_thumbnail_url(get_the_ID(), 'medium_large') . '" alt="' . esc_attr(get_the_title()) . '">';
                                } else {
                                    the_post_thumbnail('medium_large');
                                }
                                ?>
                                <div class="ytux-play-icon">
                                    <svg width="60" height="60" viewBox="0 0 60 60">
                                        <circle cx="30" cy="30" r="30" fill="rgba(0,0,0,0.7)"/>
                                        <polygon points="23,18 23,42 42,30" fill="white"/>
                                    </svg>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ytux-video-info">
                            <h3 class="ytux-video-title"><?php the_title(); ?></h3>
                            <div class="ytux-video-meta">
                                <span class="ytux-video-date"><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago'; ?></span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    private function parse_timestamp($time) {
        // If already in seconds
        if (is_numeric($time)) {
            return intval($time);
        }
        
        // Parse MM:SS or HH:MM:SS format
        $parts = explode(':', $time);
        $parts = array_reverse($parts);
        
        $seconds = 0;
        $multipliers = [1, 60, 3600]; // seconds, minutes, hours
        
        foreach ($parts as $i => $part) {
            $seconds += intval($part) * $multipliers[$i];
        }
        
        return $seconds;
    }
    
    // Admin Pages
    public function admin_dashboard_page() {
        ?>
        <div class="wrap">
            <h1>YouTube User Experience Dashboard</h1>
            
            <div class="ytux-dashboard-cards">
                <div class="ytux-card">
                    <div class="ytux-card-icon">⚡</div>
                    <h3>Performance</h3>
                    <p>Lazy loading videos and optimized thumbnails improve page load times by up to 50%.</p>
                    <p><strong>Status:</strong> <?php echo get_option('ytux_lazy_load', '1') ? '✅ Active' : '❌ Inactive'; ?></p>
                </div>
                
                <div class="ytux-card">
                    <div class="ytux-card-icon">📱</div>
                    <h3>Responsive Videos</h3>
                    <p>All video embeds automatically adapt to screen size for perfect mobile viewing.</p>
                    <p><strong>Status:</strong> <?php echo get_option('ytux_responsive_embeds', '1') ? '✅ Active' : '❌ Inactive'; ?></p>
                </div>
                
                <div class="ytux-card">
                    <div class="ytux-card-icon">🔍</div>
                    <h3>Video Search</h3>
                    <p>Help visitors find exactly what they're looking for with instant search.</p>
                    <p><strong>Status:</strong> <?php echo get_option('ytux_enable_search', '1') ? '✅ Active' : '❌ Inactive'; ?></p>
                </div>
                
                <div class="ytux-card">
                    <div class="ytux-card-icon">🔔</div>
                    <h3>Notification Bar</h3>
                    <p>Alert visitors to new video uploads with a customizable notification bar.</p>
                    <p><strong>Status:</strong> <?php echo get_option('ytux_notification_bar', '1') ? '✅ Active' : '❌ Inactive'; ?></p>
                </div>
            </div>
            
            <div class="ytux-quick-start">
                <h2>Quick Start Guide</h2>
                <ol>
                    <li>Configure settings in <a href="<?php echo admin_url('admin.php?page=yt-ux-settings'); ?>">Settings</a></li>
                    <li>Add video search to any page: <code>[video_search]</code></li>
                    <li>Display latest videos: <code>[latest_videos count="6" columns="3"]</code></li>
                    <li>Create timestamp links: <code>[yt_timestamp video_url="URL" time="2:30"]Click here[/yt_timestamp]</code></li>
                    <li>All features work automatically - videos will lazy load, embeds will be responsive!</li>
                </ol>
            </div>
        </div>
        
        <style>
        .ytux-dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .ytux-card {
            background: #fff;
            padding: 25px;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .ytux-card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .ytux-card h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        .ytux-card p {
            color: #646970;
            margin: 5px 0;
        }
        .ytux-quick-start {
            background: #fff;
            padding: 25px;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            margin-top: 30px;
        }
        .ytux-quick-start code {
            background: #f0f0f1;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    public function admin_settings_page() {
        if (isset($_POST['ytux_settings_submit'])) {
            check_admin_referer('ytux_settings_nonce');
            
            // Performance Settings
            update_option('ytux_lazy_load', isset($_POST['ytux_lazy_load']) ? '1' : '0');
            update_option('ytux_optimize_thumbnails', isset($_POST['ytux_optimize_thumbnails']) ? '1' : '0');
            update_option('ytux_preload_strategy', sanitize_text_field($_POST['ytux_preload_strategy']));
            
            // Video Player Settings
            update_option('ytux_responsive_embeds', isset($_POST['ytux_responsive_embeds']) ? '1' : '0');
            update_option('ytux_enable_keyboard', isset($_POST['ytux_enable_keyboard']) ? '1' : '0');
            update_option('ytux_autoplay', isset($_POST['ytux_autoplay']) ? '1' : '0');
            
            // Search Settings
            update_option('ytux_enable_search', isset($_POST['ytux_enable_search']) ? '1' : '0');
            update_option('ytux_search_placeholder', sanitize_text_field($_POST['ytux_search_placeholder']));
            
            // Notification Bar Settings
            update_option('ytux_notification_bar', isset($_POST['ytux_notification_bar']) ? '1' : '0');
            update_option('ytux_notification_text', sanitize_text_field($_POST['ytux_notification_text']));
            update_option('ytux_notification_link', esc_url_raw($_POST['ytux_notification_link']));
            update_option('ytux_notification_duration', intval($_POST['ytux_notification_duration']));
            update_option('ytux_notification_position', sanitize_text_field($_POST['ytux_notification_position']));
            update_option('ytux_notification_color', sanitize_hex_color($_POST['ytux_notification_color']));
            update_option('ytux_notification_bg', sanitize_hex_color($_POST['ytux_notification_bg']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>User Experience Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ytux_settings_nonce'); ?>
                
                <h2>⚡ Performance Optimization</h2>
                <table class="form-table">
                    <tr>
                        <th>Lazy Load Videos</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_lazy_load" value="1" <?php checked(get_option('ytux_lazy_load', '1'), '1'); ?>>
                                Load videos only when they come into view (improves page speed)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Optimize Thumbnails</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_optimize_thumbnails" value="1" <?php checked(get_option('ytux_optimize_thumbnails', '1'), '1'); ?>>
                                Use optimized thumbnail sizes
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Preload Strategy</th>
                        <td>
                            <select name="ytux_preload_strategy">
                                <option value="none" <?php selected(get_option('ytux_preload_strategy', 'metadata'), 'none'); ?>>None (fastest)</option>
                                <option value="metadata" <?php selected(get_option('ytux_preload_strategy', 'metadata'), 'metadata'); ?>>Metadata only (recommended)</option>
                                <option value="auto" <?php selected(get_option('ytux_preload_strategy', 'metadata'), 'auto'); ?>>Auto (slower)</option>
                            </select>
                            <p class="description">How much video data to preload</p>
                        </td>
                    </tr>
                </table>
                
                <h2>📱 Video Player</h2>
                <table class="form-table">
                    <tr>
                        <th>Responsive Embeds</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_responsive_embeds" value="1" <?php checked(get_option('ytux_responsive_embeds', '1'), '1'); ?>>
                                Make all video embeds responsive (mobile-friendly)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Keyboard Controls</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_enable_keyboard" value="1" <?php checked(get_option('ytux_enable_keyboard', '1'), '1'); ?>>
                                Enable keyboard shortcuts (Space = play/pause, Arrow keys = seek)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Autoplay</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_autoplay" value="1" <?php checked(get_option('ytux_autoplay', '0'), '1'); ?>>
                                Autoplay videos when page loads (not recommended)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2>🔍 Video Search</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable Search</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_enable_search" value="1" <?php checked(get_option('ytux_enable_search', '1'), '1'); ?>>
                                Allow visitors to search your videos
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Search Placeholder</th>
                        <td>
                            <input type="text" name="ytux_search_placeholder" value="<?php echo esc_attr(get_option('ytux_search_placeholder', 'Search videos...')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                
                <h2>🔔 Notification Bar</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable Notification Bar</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ytux_notification_bar" value="1" <?php checked(get_option('ytux_notification_bar', '1'), '1'); ?>>
                                Show notification bar for new videos
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Notification Text</th>
                        <td>
                            <input type="text" name="ytux_notification_text" value="<?php echo esc_attr(get_option('ytux_notification_text', 'New video uploaded! Check it out →')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Link URL</th>
                        <td>
                            <input type="url" name="ytux_notification_link" value="<?php echo esc_attr(get_option('ytux_notification_link', '')); ?>" class="regular-text">
                            <p class="description">Leave empty to link to latest post</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Show Duration</th>
                        <td>
                            <input type="number" name="ytux_notification_duration" value="<?php echo esc_attr(get_option('ytux_notification_duration', '7')); ?>" min="1" max="30"> days
                            <p class="description">How many days to show notification after new video</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Position</th>
                        <td>
                            <select name="ytux_notification_position">
                                <option value="top" <?php selected(get_option('ytux_notification_position', 'top'), 'top'); ?>>Top of page</option>
                                <option value="bottom" <?php selected(get_option('ytux_notification_position', 'top'), 'bottom'); ?>>Bottom of page</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Text Color</th>
                        <td>
                            <input type="text" name="ytux_notification_color" value="<?php echo esc_attr(get_option('ytux_notification_color', '#ffffff')); ?>" class="ytux-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th>Background Color</th>
                        <td>
                            <input type="text" name="ytux_notification_bg" value="<?php echo esc_attr(get_option('ytux_notification_bg', '#ff0000')); ?>" class="ytux-color-picker">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="ytux_settings_submit" class="button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ytux-color-picker').wpColorPicker();
        });
        </script>
        <?php
    }
    
    public function register_widgets() {
        register_widget('YTUX_Search_Widget');
        register_widget('YTUX_Latest_Videos_Widget');
    }
}

// Widget: Video Search
class YTUX_Search_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('ytux_search_widget', 'Video Search', ['description' => 'Search your videos']);
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . $instance['title'] . $args['after_title'];
        }
        echo do_shortcode('[video_search]');
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Search Videos';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
}

// Widget: Latest Videos
class YTUX_Latest_Videos_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('ytux_latest_widget', 'Latest Videos', ['description' => 'Display latest videos']);
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . $instance['title'] . $args['after_title'];
        }
        $count = !empty($instance['count']) ? $instance['count'] : 3;
        echo do_shortcode('[latest_videos count="' . $count . '" columns="1"]');
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Latest Videos';
        $count = !empty($instance['count']) ? $instance['count'] : 3;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>">Number of videos:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="number" value="<?php echo esc_attr($count); ?>" min="1" max="10">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['count'] = (!empty($new_instance['count'])) ? intval($new_instance['count']) : 3;
        return $instance;
    }
}

// Initialize the plugin
new YTUXSuite();