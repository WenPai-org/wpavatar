<?php
/**
 * Plugin Name: WPAvatar
 * Version: 1.8.1
 * Plugin URI: https://wpavatar.com/download
 * Description: Replace Gravatar with Cravatar, a perfect replacement of Gravatar in China.
 * Author: WPfanyi
 * Author URI: https://wpfanyi.com/
 * Text Domain: wpavatar
 * Domain Path: /languages
 * Network: true
 */

defined('ABSPATH') || exit;

define('WPAVATAR_VERSION', '1.8.0');
define('WPAVATAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAVATAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/uploads/cravatar');

// Create necessary plugin directories if they don't exist
if (!file_exists(WPAVATAR_PLUGIN_DIR . 'assets')) {
    wp_mkdir_p(WPAVATAR_PLUGIN_DIR . 'assets');
}

if (!file_exists(WPAVATAR_PLUGIN_DIR . 'assets/css')) {
    wp_mkdir_p(WPAVATAR_PLUGIN_DIR . 'assets/css');

    $css_file = WPAVATAR_PLUGIN_DIR . 'admin.css';
    if (file_exists($css_file)) {
        copy($css_file, WPAVATAR_PLUGIN_DIR . 'assets/css/admin.css');
    }
}

if (!file_exists(WPAVATAR_PLUGIN_DIR . 'assets/js')) {
    wp_mkdir_p(WPAVATAR_PLUGIN_DIR . 'assets/js');

    $js_file = WPAVATAR_PLUGIN_DIR . 'admin.js';
    if (file_exists($js_file)) {
        copy($js_file, WPAVATAR_PLUGIN_DIR . 'assets/js/admin.js');
    }
}

if (!file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images')) {
    wp_mkdir_p(WPAVATAR_PLUGIN_DIR . 'assets/images');
}

if (!file_exists(WPAVATAR_PLUGIN_DIR . 'includes')) {
    wp_mkdir_p(WPAVATAR_PLUGIN_DIR . 'includes');

    if (file_exists(WPAVATAR_PLUGIN_DIR . 'core.php')) {
        copy(WPAVATAR_PLUGIN_DIR . 'core.php', WPAVATAR_PLUGIN_DIR . 'includes/core.php');
    }

    if (file_exists(WPAVATAR_PLUGIN_DIR . 'admin.php')) {
        copy(WPAVATAR_PLUGIN_DIR . 'admin.php', WPAVATAR_PLUGIN_DIR . 'includes/admin.php');
    }
}

// Include core files
require_once WPAVATAR_PLUGIN_DIR . 'includes/core.php';
require_once WPAVATAR_PLUGIN_DIR . 'includes/admin.php';
require_once WPAVATAR_PLUGIN_DIR . 'includes/multisite.php';

// Register AJAX actions
add_action('wp_ajax_wpavatar_purge_cache', 'wpavatar_purge_cache_ajax');
function wpavatar_purge_cache_ajax() {
    check_ajax_referer('wpavatar_admin_nonce', 'nonce');

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('权限不足', 'wpavatar'));
        return;
    }

    // Get cache directory path
    $dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);

    // Add blog-specific directory if multisite
    if (is_multisite()) {
        $blog_id = get_current_blog_id();
        $dir = trailingslashit($dir) . 'site-' . $blog_id . '/';
    }

    $dir = rtrim($dir, '/\\') . '/';

    if (!file_exists($dir) || !is_dir($dir)) {
        wp_send_json_error(__('缓存目录不存在', 'wpavatar'));
        return;
    }

    $files = glob($dir . '*.jpg');
    $count = 0;

    if ($files) {
        foreach ($files as $file) {
            if (strpos($file, $dir) === 0 && file_exists($file)) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
    }

    wp_send_json_success(sprintf(__('已清空 %d 个缓存文件', 'wpavatar'), $count));
}

add_action('wp_ajax_wpavatar_purge_all_cache', 'wpavatar_purge_all_cache_ajax');
function wpavatar_purge_all_cache_ajax() {
    check_ajax_referer('wpavatar_admin_nonce', 'nonce');

    // Check user capability for network admin
    if (!current_user_can('manage_network_options')) {
        wp_send_json_error(__('权限不足', 'wpavatar'));
        return;
    }

    // Get base cache directory path
    $base_dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
    $base_dir = rtrim($base_dir, '/\\') . '/';

    if (!file_exists($base_dir) || !is_dir($base_dir)) {
        wp_send_json_error(__('缓存目录不存在', 'wpavatar'));
        return;
    }

    $count = 0;

    // Get all site cache directories
    $site_dirs = glob($base_dir . 'site-*', GLOB_ONLYDIR);

    if ($site_dirs) {
        foreach ($site_dirs as $site_dir) {
            $files = glob($site_dir . '/*.jpg');
            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        if (@unlink($file)) {
                            $count++;
                        }
                    }
                }
            }
        }
    }

    // Also check legacy non-site specific files
    $legacy_files = glob($base_dir . '*.jpg');
    if ($legacy_files) {
        foreach ($legacy_files as $file) {
            if (file_exists($file)) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
    }

    wp_send_json_success(sprintf(__('已清空所有站点的 %d 个缓存文件', 'wpavatar'), $count));
}

add_action('wp_ajax_wpavatar_check_cache', 'wpavatar_check_cache_ajax');
function wpavatar_check_cache_ajax() {
    check_ajax_referer('wpavatar_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('权限不足', 'wpavatar'));
        return;
    }

    $result = \WPAvatar\Cache::check_cache_status();
    wp_send_json_success($result);
}

add_action('wp_ajax_wpavatar_check_all_cache', 'wpavatar_check_all_cache_ajax');
function wpavatar_check_all_cache_ajax() {
    check_ajax_referer('wpavatar_admin_nonce', 'nonce');

    if (!current_user_can('manage_network_options')) {
        wp_send_json_error(__('权限不足', 'wpavatar'));
        return;
    }

    $result = \WPAvatar\Cache::check_all_cache_status();
    wp_send_json_success($result);
}

add_action('plugins_loaded', function () {
    // Load text domain for translations
    load_plugin_textdomain('wpavatar', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Create default avatar if it doesn't exist
    $default_avatar = WPAVATAR_PLUGIN_DIR . 'assets/images/default-avatar.png';
    if (!file_exists($default_avatar)) {
        $placeholder = WPAVATAR_PLUGIN_DIR . 'assets/images/placeholder-avatar.png';

        if (file_exists($placeholder)) {
            copy($placeholder, $default_avatar);
        } else {
            $avatar_image = imagecreatetruecolor(96, 96);
            $bg_color = imagecolorallocate($avatar_image, 238, 238, 238);
            $text_color = imagecolorallocate($avatar_image, 68, 68, 68);

            imagefill($avatar_image, 0, 0, $bg_color);
            imagestring($avatar_image, 5, 30, 40, 'Avatar', $text_color);

            wp_mkdir_p(dirname($default_avatar));
            imagepng($avatar_image, $default_avatar);
            imagedestroy($avatar_image);
        }
    }

    // Fetch Cravatar logo if it doesn't exist
    $cravatar_logo = WPAVATAR_PLUGIN_DIR . 'assets/images/cravatar-logo.png';
    if (!file_exists($cravatar_logo) && function_exists('file_get_contents')) {
        $logo_url = 'https://cn.cravatar.com/avatar/00000000000000000000000000000000?d=cravatar_logo';
        $logo_data = @file_get_contents($logo_url);
        if ($logo_data) {
            wp_mkdir_p(dirname($cravatar_logo));
            @file_put_contents($cravatar_logo, $logo_data);
        }
    }

    // Initialize multisite network support first
    if (is_multisite()) {
        \WPAvatar\Network::init();
    }

    // Initialize core components
    \WPAvatar\Core::init();
    \WPAvatar\Cravatar::init();
    \WPAvatar\Cache::init();
    \WPAvatar\Shortcode::init();

    // Initialize admin settings (will be conditionally disabled in multisite if network managed)
    if (is_admin()) {
        // Check if we should initialize settings in site admin
        $should_init_settings = true;

        if (is_multisite() && get_site_option('wpavatar_network_enforce', 0) && !is_network_admin()) {
            $should_init_settings = false;
        }

        if ($should_init_settings) {
            \WPAvatar\Settings::init();
        }
    }

    // Filter text to replace Gravatar references with Cravatar
    add_filter('gettext', 'wpavatar_replace_gravatar_text', 20, 3);
    add_filter('ngettext', 'wpavatar_replace_gravatar_text_plural', 20, 4);
});

/**
 * Replace Gravatar text with Cravatar in translations
 *
 * @param string $translated_text Translated text
 * @param string $text Original text
 * @param string $domain Text domain
 * @return string Modified translated text
 */
function wpavatar_replace_gravatar_text($translated_text, $text, $domain) {
    // Get enable_cravatar setting
    $enable_cravatar = wpavatar_get_option('wpavatar_enable_cravatar', 1);

    if (!$enable_cravatar) {
        return $translated_text;
    }

    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $current_screen ? $current_screen->id : '';

    $is_discussion_page = ($screen_id === 'options-discussion' ||
                          (isset($_GET['page']) && $_GET['page'] === 'discussion'));

    $is_comments_page = ($screen_id === 'edit-comments' ||
                         $screen_id === 'comment');

    $is_profile_page = ($screen_id === 'profile' ||
                       $screen_id === 'user-edit');

    if ($is_discussion_page || $is_comments_page || $is_profile_page) {
        $translated_text = str_replace('Gravatar', 'Cravatar', $translated_text);
        $translated_text = str_replace('gravatar', 'cravatar', $translated_text);
    }

    return $translated_text;
}

/**
 * Replace Gravatar text with Cravatar in plural translations
 *
 * @param string $translated_text Translated text
 * @param string $single Singular text
 * @param string $plural Plural text
 * @param int $number Number for plural form
 * @return string Modified translated text
 */
function wpavatar_replace_gravatar_text_plural($translated_text, $single, $plural, $number) {
    // Get enable_cravatar setting
    $enable_cravatar = wpavatar_get_option('wpavatar_enable_cravatar', 1);

    if (!$enable_cravatar) {
        return $translated_text;
    }

    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $current_screen ? $current_screen->id : '';

    $is_relevant_page = ($screen_id === 'options-discussion' ||
                         $screen_id === 'edit-comments' ||
                         $screen_id === 'comment' ||
                         $screen_id === 'profile' ||
                         $screen_id === 'user-edit' ||
                         (isset($_GET['page']) && $_GET['page'] === 'discussion'));

    if ($is_relevant_page) {
        $translated_text = str_replace('Gravatar', 'Cravatar', $translated_text);
        $translated_text = str_replace('gravatar', 'cravatar', $translated_text);
    }

    return $translated_text;
}

/**
 * Helper function to get the correct option based on multisite status
 *
 * @param string $option_name Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
function wpavatar_get_option($option_name, $default = false) {
    if (is_multisite()) {
        // Check if network settings are enabled
        if (get_site_option('wpavatar_network_enabled', 1)) {
            // Check if this option is controlled by network
            $network_controlled_options = get_site_option('wpavatar_network_controlled_options', array());

            // Convert to array if it's a string (for backward compatibility)
            if (!is_array($network_controlled_options)) {
                $network_controlled_options = explode(',', $network_controlled_options);
            }

            // If this option is controlled by network or network enforces all settings
            if (in_array($option_name, $network_controlled_options) || get_site_option('wpavatar_network_enforce', 0)) {
                return get_site_option($option_name, $default);
            }
        }
    }

    // Default to site option
    return get_option($option_name, $default);
}

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Set default options for single site
    add_option('wpavatar_enable_cravatar', 1);
    add_option('wpavatar_cdn_type', 'cravatar_route');
    add_option('wpavatar_cravatar_route', 'cravatar.com');
    add_option('wpavatar_third_party_mirror', 'weavatar.com');
    add_option('wpavatar_custom_cdn', '');
    add_option('wpavatar_hash_method', 'md5');
    add_option('wpavatar_timeout', 5);

    add_option('wpavatar_enable_cache', 1);
    add_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
    add_option('wpavatar_cache_expire', 15);

    add_option('wpavatar_seo_alt', '%s的头像');
    add_option('wpavatar_fallback_mode', 1);
    add_option('wpavatar_fallback_avatar', 'default');

    add_option('wpavatar_shortcode_size', 96);
    add_option('wpavatar_shortcode_class', 'wpavatar');
    add_option('wpavatar_shortcode_shape', 'square');

    // Create cache directory
    wp_mkdir_p(WPAVATAR_CACHE_DIR);

    // Create index.php to prevent directory listing
    $index_file = rtrim(WPAVATAR_CACHE_DIR, '/\\') . '/index.php';
    if (!file_exists($index_file)) {
        @file_put_contents($index_file, '<?php // Silence is golden.');
    }

    // Create .htaccess to configure cache
    $htaccess_file = rtrim(WPAVATAR_CACHE_DIR, '/\\') . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# Prevent directory listing\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "# Cache images for one week\n";
        $htaccess_content .= "<IfModule mod_expires.c>\n";
        $htaccess_content .= "ExpiresActive On\n";
        $htaccess_content .= "ExpiresByType image/jpeg \"access plus 1 week\"\n";
        $htaccess_content .= "</IfModule>\n";
        @file_put_contents($htaccess_file, $htaccess_content);
    }

    // Set default options for multisite
    if (is_multisite()) {
        // Network settings
        add_site_option('wpavatar_network_enabled', 1);
        add_site_option('wpavatar_network_enforce', 0);

        // Define default network controlled options
        $default_controlled = array(
            'wpavatar_enable_cravatar',
            'wpavatar_cdn_type',
            'wpavatar_cravatar_route',
            'wpavatar_third_party_mirror',
            'wpavatar_custom_cdn'
        );
        add_site_option('wpavatar_network_controlled_options', $default_controlled);

        // Copy regular options to network options
        foreach ([
            'wpavatar_enable_cravatar',
            'wpavatar_cdn_type',
            'wpavatar_cravatar_route',
            'wpavatar_third_party_mirror',
            'wpavatar_custom_cdn',
            'wpavatar_hash_method',
            'wpavatar_timeout',
            'wpavatar_enable_cache',
            'wpavatar_cache_path',
            'wpavatar_cache_expire',
            'wpavatar_seo_alt',
            'wpavatar_fallback_mode',
            'wpavatar_fallback_avatar',
            'wpavatar_shortcode_size',
            'wpavatar_shortcode_class',
            'wpavatar_shortcode_shape'
        ] as $option_name) {
            add_site_option($option_name, get_option($option_name));
        }

        // If current site is not the main site, create site-specific cache dir
        if (!is_main_site()) {
            $blog_id = get_current_blog_id();
            $cache_dir = trailingslashit(WPAVATAR_CACHE_DIR) . 'site-' . $blog_id;
            wp_mkdir_p($cache_dir);

            $index_file = $cache_dir . '/index.php';
            if (!file_exists($index_file)) {
                @file_put_contents($index_file, '<?php // Silence is golden.');
            }
        }
    }

    // Schedule daily cache purge
    if (!wp_next_scheduled('wpavatar_purge_cache')) {
        wp_schedule_event(time(), 'daily', 'wpavatar_purge_cache');
    }
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled events
    wp_clear_scheduled_hook('wpavatar_purge_cache');
});

// 确保 WPAvatar 插件已激活
add_action('plugins_loaded', function() {
    if (class_exists('WPAvatar\\Cravatar')) {
        // 添加兼容性过滤器，确保 WPAvatar 的优先级高于 WP-China-Yes
        add_action('after_setup_theme', function() {
            // 检查 WP-China-Yes 插件是否激活
            if (class_exists('WenPai\\ChinaYes\\Service\\Super') || defined('CHINA_YES_VERSION')) {
                // 移除 WP-China-Yes 的头像相关过滤器
                global $wp_filter;

                $filters_to_check = [
                    'get_avatar_url',
                    'um_user_avatar_url_filter',
                    'bp_gravatar_url',
                    'user_profile_picture_description',
                    'avatar_defaults'
                ];

                foreach ($filters_to_check as $filter_name) {
                    if (isset($wp_filter[$filter_name])) {
                        foreach ($wp_filter[$filter_name]->callbacks as $priority => $callbacks) {
                            foreach ($callbacks as $callback_key => $callback_data) {
                                if (is_array($callback_data['function']) &&
                                    is_object($callback_data['function'][0]) &&
                                    get_class($callback_data['function'][0]) === 'WenPai\\ChinaYes\\Service\\Super') {

                                    $method_name = $callback_data['function'][1];
                                    remove_filter($filter_name, [$callback_data['function'][0], $method_name], $priority);
                                }
                            }
                        }
                    }
                }

                // 重新添加 WPAvatar 的过滤器，使用更高的优先级
                if (wpavatar_get_option('wpavatar_enable_cravatar', true)) {
                    // 移除原有优先级的过滤器
                    remove_filter('get_avatar_url', ['WPAvatar\\Cravatar', 'get_avatar_url'], 999);
                    remove_filter('um_user_avatar_url_filter', ['WPAvatar\\Cravatar', 'replace_avatar_url'], 1);
                    remove_filter('bp_gravatar_url', ['WPAvatar\\Cravatar', 'replace_avatar_url'], 1);

                    // 使用更高优先级重新添加
                    add_filter('get_avatar_url', ['WPAvatar\\Cravatar', 'get_avatar_url'], 9999, 2);
                    add_filter('um_user_avatar_url_filter', ['WPAvatar\\Cravatar', 'replace_avatar_url'], 9999);
                    add_filter('bp_gravatar_url', ['WPAvatar\\Cravatar', 'replace_avatar_url'], 9999);
                    add_filter('user_profile_picture_description', ['WPAvatar\\Cravatar', 'modify_profile_picture_description'], 9999);
                }

                // 添加管理界面通知
                add_action('admin_notices', function() {
                    $screen = get_current_screen();
                    if ($screen && $screen->id === 'settings_page_wpavatar-settings') {
                        echo '<div class="notice notice-info is-dismissible">';
                        echo '<p>检测到文派叶子（WPCY.COM）插件，WPAvatar 生态组件兼容性补丁已生效，确保文派头像设置优先。</p>';
                        echo '</div>';
                    }
                });
            }
        }, 5);
    }
});
