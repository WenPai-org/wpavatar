<?php
/**
 * WPAvatar 兼容性修复
 *
 * 用于处理与其他插件的兼容性问题
 */

namespace WPAvatar;

class Compatibility {
    /**
     * 初始化兼容性修复
     */
    public static function init() {
        // 在所有插件加载后、主题初始化前运行
        add_action('after_setup_theme', [__CLASS__, 'handle_wp_china_yes_compatibility'], 5);

        // 处理与 Marketing 和 Shortcode 类之间的兼容性
        add_action('init', [__CLASS__, 'handle_shortcode_compatibility'], 1);
    }

    /**
     * 处理与 WP-China-Yes 插件的兼容性
     */
    public static function handle_wp_china_yes_compatibility() {
        // 检查 WP-China-Yes 插件是否激活
        if (self::is_wp_china_yes_active()) {
            // 移除 WP-China-Yes 的头像替换过滤器
            self::remove_wp_china_yes_filters();

            // 重新初始化 WPAvatar 的 Cravatar 功能，使用更高的优先级
            self::reinitialize_wpavatar_filters();

            // 可选：添加管理界面通知
            if (is_admin()) {
                add_action('admin_notices', [__CLASS__, 'admin_compatibility_notice']);
            }
        }
    }

    /**
     * 处理 Marketing 和 Shortcode 类之间的简码兼容性
     */
    public static function handle_shortcode_compatibility() {
        // 确保只处理一次
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // 移除 Marketing 类中可能与 Shortcode 类冲突的简码定义
        remove_shortcode('wpavatar');
        remove_shortcode('wpavatar_username');
        remove_filter('walker_nav_menu_start_el', array('\WPAvatar\Marketing', 'menu_item_replace'), 10);

        // 重新添加 Shortcode 类的简码
        add_shortcode('wpavatar', ['\WPAvatar\Shortcode', 'render_avatar']);
        add_shortcode('wpavatar_username', ['\WPAvatar\Shortcode', 'render_username']);
        add_filter('walker_nav_menu_start_el', ['\WPAvatar\Shortcode', 'menu_item_replace'], 10, 4);
    }

    /**
     * 检查 WP-China-Yes 插件是否激活
     */
    private static function is_wp_china_yes_active() {
        return class_exists('WenPai\\ChinaYes\\Service\\Super') ||
               defined('CHINA_YES_VERSION') ||
               function_exists('WenPai\\ChinaYes\\get_settings');
    }

    /**
     * 移除 WP-China-Yes 的头像替换过滤器
     */
    private static function remove_wp_china_yes_filters() {
        // 找到可能的 Super 类实例
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
    }

    /**
     * 重新初始化 WPAvatar 的 Cravatar 过滤器，使用更高的优先级
     */
    private static function reinitialize_wpavatar_filters() {
        if (wpavatar_get_option('wpavatar_enable_cravatar', true)) {
            // 使用高优先级再次添加过滤器
            add_filter('um_user_avatar_url_filter', ['\WPAvatar\Cravatar', 'replace_avatar_url'], 9999);
            add_filter('bp_gravatar_url', ['\WPAvatar\Cravatar', 'replace_avatar_url'], 9999);
            add_filter('user_profile_picture_description', ['\WPAvatar\Cravatar', 'modify_profile_picture_description'], 9999);

            // 确保 get_avatar_url 过滤器的优先级高于其他插件
            remove_filter('get_avatar_url', ['\WPAvatar\Cravatar', 'get_avatar_url'], 999);
            add_filter('get_avatar_url', ['\WPAvatar\Cravatar', 'get_avatar_url'], 9999, 2);
        }
    }

    /**
     * 管理界面兼容性通知
     */
    public static function admin_compatibility_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_wpavatar-settings') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>检测到文派叶子 🍃（WPCY.COM）插件，WPAvatar 生态组件兼容补丁已生效，确保文派头像设置优先。</p>';
            echo '</div>';
        }
    }
}
