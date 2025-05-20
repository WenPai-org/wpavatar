<?php
/**
 * Enhanced Network Integration for WPAvatar
 *
 * This file provides multisite support for the WPAvatar plugin,
 * allowing for network-wide settings management.
 */

namespace WPAvatar;

/**
 * Class to handle multisite network functionality
 */
class Network {
    /**
     * Default network controlled options
     */
    private static $default_controlled_options = [
        'wpavatar_enable_cravatar',
        'wpavatar_cdn_type',
        'wpavatar_cravatar_route',
        'wpavatar_third_party_mirror',
        'wpavatar_custom_cdn'
    ];

    /**
     * Initialize network functionality
     */
    public static function init() {
        // Register network settings when in network admin
        if (is_network_admin()) {
            add_action('network_admin_menu', [__CLASS__, 'add_network_menu']);
            add_action('network_admin_edit_wpavatar_network_settings', [__CLASS__, 'save_network_settings']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'network_admin_scripts']);
        }

        // Add network settings link in network plugins list
        add_filter('network_admin_plugin_action_links_wpavatar/wpavatar.php', [__CLASS__, 'add_network_action_links']);

        // Add notice in site admin settings when network managed
        if (is_multisite() && get_site_option('wpavatar_network_enabled', 1)) {
            add_action('admin_notices', [__CLASS__, 'network_managed_notice']);
            add_action('admin_menu', [__CLASS__, 'maybe_remove_site_menu'], 999);
        }

        // Hook for new blog creation
        add_action('wpmu_new_blog', [__CLASS__, 'new_site_created']);
    }

    /**
     * Handle creation of new blog in multisite
     */
    public static function new_site_created($blog_id) {
        if (get_site_option('wpavatar_network_enabled', 1)) {
            // Create cache directory for the new site
            $cache_path = get_site_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
            $site_cache_dir = trailingslashit($cache_path) . 'site-' . $blog_id;

            if (!file_exists($site_cache_dir)) {
                wp_mkdir_p($site_cache_dir);

                // Create index.php to prevent directory listing
                $index_file = $site_cache_dir . '/index.php';
                if (!file_exists($index_file)) {
                    @file_put_contents($index_file, '<?php // Silence is golden.');
                }
            }

            // If network settings are enforced, copy relevant settings to the new site
            if (get_site_option('wpavatar_network_enforce', 0)) {
                switch_to_blog($blog_id);

                // Apply all network settings to the new site
                $options_to_copy = [
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
                    'wpavatar_shortcode_shape',
                    // 添加营销组件选项
                    'wpavatar_commenters_count',
                    'wpavatar_commenters_size',
                    'wpavatar_users_count',
                    'wpavatar_users_size'
                ];

                foreach ($options_to_copy as $option) {
                    update_option($option, get_site_option($option));
                }

                restore_current_blog();
            }
        }
    }

    /**
     * Add network admin scripts and styles
     */
    public static function network_admin_scripts($hook) {
        if ($hook !== 'settings_page_wpavatar-network') {
            return;
        }

        // Enqueue the same scripts and styles as the regular admin page
        wp_enqueue_style(
            'wpavatar-admin',
            WPAVATAR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPAVATAR_VERSION
        );

        wp_enqueue_script(
            'wpavatar-admin',
            WPAVATAR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPAVATAR_VERSION,
            true
        );

        wp_localize_script('wpavatar-admin', 'wpavatar', [
            'nonce' => wp_create_nonce('wpavatar_admin_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'cache_path' => get_site_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR),
            'plugin_url' => WPAVATAR_PLUGIN_URL,
            'assets_url' => WPAVATAR_PLUGIN_URL . 'assets/',
            'is_network_admin' => is_network_admin() ? '1' : '0',
        ]);

        wp_localize_script('wpavatar-admin', 'wpavatar_l10n', [
            'checking' => __('检查中...', 'wpavatar'),
            'checking_status' => __('正在检查缓存状态...', 'wpavatar'),
            'check_failed' => __('检查失败，请重试', 'wpavatar'),
            'request_failed' => __('请求失败，请检查网络连接', 'wpavatar'),
            'check_cache' => __('检查缓存状态', 'wpavatar'),
            'confirm_purge' => __('确定要清空所有缓存头像吗？此操作无法撤销。', 'wpavatar'),
            'purging' => __('清空中...', 'wpavatar'),
            'purging_cache' => __('正在清空缓存...', 'wpavatar'),
            'purge_failed' => __('清空失败，请重试', 'wpavatar'),
            'purge_cache' => __('清空缓存', 'wpavatar'),
            'enter_custom_cdn' => __('请输入自定义CDN域名', 'wpavatar'),
            'enter_cache_path' => __('请输入缓存目录路径', 'wpavatar'),
            'settings_saved' => __('设置已成功保存。', 'wpavatar'),
            'confirm_import' => __('确定要从所选站点导入设置吗？此操作将覆盖当前的网络设置。', 'wpavatar'),
            'confirm_reset' => __('确定要重置所有控制选项吗？该操作会将所有站点恢复为各自的设置。', 'wpavatar')
        ]);
    }

    /**
     * Add network menu
     */
    public static function add_network_menu() {
        add_submenu_page(
            'settings.php',
            __('WPAvatar网络设置', 'wpavatar'),
            __('WPAvatar', 'wpavatar'),
            'manage_network_options',
            'wpavatar-network',
            [__CLASS__, 'render_network_page']
        );
    }

    /**
     * Add network action links
     */
    public static function add_network_action_links($links) {
        $settings_link = '<a href="' . network_admin_url('settings.php?page=wpavatar-network') . '">' . __('网络设置', 'wpavatar') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display network managed notice on site-level settings
     */
    public static function network_managed_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'settings_page_wpavatar-settings') {
            return;
        }

        $network_controlled_options = get_site_option('wpavatar_network_controlled_options', self::$default_controlled_options);
        if (!is_array($network_controlled_options)) {
            $network_controlled_options = explode(',', $network_controlled_options);
        }

        $controlled_count = count($network_controlled_options);
        $option_count = 15; // Approximate total number of WPAvatar options

        if (get_site_option('wpavatar_network_enforce', 0)) {
            echo '<div class="notice notice-warning"><p>' .
                __('WPAvatar 插件正由网络管理员强制管理。所有设置将使用网络级别配置，任何更改将被忽略。如有疑问请联系网络管理员。', 'wpavatar') .
                '</p></div>';
        } else if ($controlled_count > 0) {
            echo '<div class="notice notice-info"><p>' .
                sprintf(
                    __('WPAvatar 插件的 %d 项设置由网络管理员控制。这些设置的更改将不会生效。', 'wpavatar'),
                    $controlled_count
                ) .
                '</p></div>';
        }
    }

    /**
     * Maybe remove site menu if network settings enforced
     */
    public static function maybe_remove_site_menu() {
        // If network settings completely override site settings
        if (get_site_option('wpavatar_network_enforce', 0)) {
            remove_submenu_page('options-general.php', 'wpavatar-settings');
        }
    }

    /**
     * Render network settings page
     */
    public static function render_network_page() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('您没有足够权限访问此页面。', 'wpavatar'));
        }

        // Display update message if settings were just updated
        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                __('网络设置已保存。', 'wpavatar') .
                '</p></div>';
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'network';

        ?>
        <div class="wrap wpavatar-settings">
            <h1><?php esc_html_e('文派头像设置', 'wpavatar'); ?>
              <span style="font-size: 13px; padding-left: 10px;"><?php printf(esc_html__('版本: %s', 'wpavatar'), esc_html(WPAVATAR_VERSION)); ?></span>
              <a href="https://wpavatar.com/document/" target="_blank" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('文档', 'wpavatar'); ?></a>
              <a href="https://cravatar.com/forums/" target="_blank" class="button button-secondary"><?php esc_html_e('支持', 'wpavatar'); ?></a>
            </h1>

            <div id="wpavatar-status" class="notice" style="display:none; margin-top: 10px; padding: 8px 12px;"></div>

            <!-- Add tabs for each section -->
            <div class="wpavatar-card">
                <div class="wpavatar-tabs-wrapper">
                    <div class="wpavatar-sync-tabs">
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'network' ? 'active' : ''; ?>" data-tab="network">
                            <?php _e('网络管理', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'basic' ? 'active' : ''; ?>" data-tab="basic">
                            <?php _e('基础设置', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'cache' ? 'active' : ''; ?>" data-tab="cache">
                            <?php _e('缓存控制', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'advanced' ? 'active' : ''; ?>" data-tab="advanced">
                            <?php _e('高级设置', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'shortcodes' ? 'active' : ''; ?>" data-tab="shortcodes">
                            <?php _e('头像简码', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'marketing' ? 'active' : ''; ?>" data-tab="marketing">
                            <?php _e('营销组件', 'wpavatar'); ?>
                        </button>
                        <button type="button" class="wpavatar-tab <?php echo $active_tab === 'tools' ? 'active' : ''; ?>" data-tab="tools">
                            <?php _e('实用工具', 'wpavatar'); ?>
                        </button>
                    </div>
                </div>

                <!-- Network Management Section -->
                <div class="wpavatar-section" id="wpavatar-section-network" style="<?php echo $active_tab !== 'network' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('网络管理设置', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('配置多站点网络的WPAvatar管理方式。', 'wpavatar'); ?></p>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-network-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="network">

                        <table class="form-table">
                            <tr>
                                <th><?php _e('启用网络级管理', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-switch">
                                        <input type="checkbox" name="wpavatar_network_enabled" value="1" <?php checked(get_site_option('wpavatar_network_enabled', 1)); ?>>
                                        <span class="wpavatar-slider"></span>
                                        <span class="wpavatar-switch-label"><?php _e('在所有站点使用网络设置', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('启用后，所有子站点将使用选定的网络设置，子站点的对应设置将被覆盖', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('强制使用网络设置', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-switch">
                                        <input type="checkbox" name="wpavatar_network_enforce" value="1" <?php checked(get_site_option('wpavatar_network_enforce', 0)); ?>>
                                        <span class="wpavatar-slider"></span>
                                        <span class="wpavatar-switch-label"><?php _e('完全禁用子站点设置页面', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('启用后，子站点将无法访问WPAvatar设置页面，完全由网络管理', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('网络控制选项', 'wpavatar'); ?></th>
                                <td>
                                    <?php
                                    // Get current controlled options
                                    $controlled_options = get_site_option('wpavatar_network_controlled_options', self::$default_controlled_options);
                                    if (!is_array($controlled_options)) {
                                        $controlled_options = explode(',', $controlled_options);
                                    }

                                    // Define all available options with user-friendly labels
                                    $all_options = [
                                        'wpavatar_enable_cravatar' => __('启用初认头像', 'wpavatar'),
                                        'wpavatar_cdn_type' => __('线路选择', 'wpavatar'),
                                        'wpavatar_cravatar_route' => __('Cravatar官方源', 'wpavatar'),
                                        'wpavatar_third_party_mirror' => __('第三方镜像', 'wpavatar'),
                                        'wpavatar_custom_cdn' => __('自定义CDN', 'wpavatar'),
                                        'wpavatar_hash_method' => __('头像哈希方法', 'wpavatar'),
                                        'wpavatar_timeout' => __('超时设置', 'wpavatar'),
                                        'wpavatar_enable_cache' => __('启用本地缓存', 'wpavatar'),
                                        'wpavatar_cache_path' => __('缓存目录', 'wpavatar'),
                                        'wpavatar_cache_expire' => __('缓存过期时间', 'wpavatar'),
                                        'wpavatar_seo_alt' => __('SEO替代文本', 'wpavatar'),
                                        'wpavatar_fallback_mode' => __('头像加载失败处理', 'wpavatar'),
                                        'wpavatar_fallback_avatar' => __('备用头像选择', 'wpavatar'),
                                        'wpavatar_shortcode_size' => __('默认头像大小', 'wpavatar'),
                                        'wpavatar_shortcode_class' => __('默认CSS类名', 'wpavatar'),
                                        'wpavatar_shortcode_shape' => __('默认头像形状', 'wpavatar'),
                                        'wpavatar_commenters_count' => __('评论者数量', 'wpavatar'),
                                        'wpavatar_commenters_size' => __('评论者头像大小', 'wpavatar'),
                                        'wpavatar_users_count' => __('用户数量', 'wpavatar'),
                                        'wpavatar_users_size' => __('用户头像大小', 'wpavatar'),
                                    ];

                                    // Group options by category
                                    $option_groups = [
                                        'basic' => [
                                            'title' => __('基础设置', 'wpavatar'),
                                            'options' => [
                                                'wpavatar_enable_cravatar',
                                                'wpavatar_cdn_type',
                                                'wpavatar_cravatar_route',
                                                'wpavatar_third_party_mirror',
                                                'wpavatar_custom_cdn',
                                                'wpavatar_hash_method',
                                                'wpavatar_timeout'
                                            ]
                                        ],
                                        'cache' => [
                                            'title' => __('缓存控制', 'wpavatar'),
                                            'options' => [
                                                'wpavatar_enable_cache',
                                                'wpavatar_cache_path',
                                                'wpavatar_cache_expire'
                                            ]
                                        ],
                                        'advanced' => [
                                            'title' => __('高级设置', 'wpavatar'),
                                            'options' => [
                                                'wpavatar_seo_alt',
                                                'wpavatar_fallback_mode',
                                                'wpavatar_fallback_avatar'
                                            ]
                                        ],
                                        'shortcodes' => [
                                            'title' => __('头像简码', 'wpavatar'),
                                            'options' => [
                                                'wpavatar_shortcode_size',
                                                'wpavatar_shortcode_class',
                                                'wpavatar_shortcode_shape'
                                            ]
                                        ],
                                        'marketing' => [
                                            'title' => __('营销组件', 'wpavatar'),
                                            'options' => [
                                                'wpavatar_commenters_count',
                                                'wpavatar_commenters_size',
                                                'wpavatar_users_count',
                                                'wpavatar_users_size'
                                            ]
                                        ]
                                    ];

                                    // Output options by group
                                    echo '<div class="network-control-options">';
                                    foreach ($option_groups as $group => $group_data) {
                                        echo '<div class="option-group">';
                                        echo '<h4>' . esc_html($group_data['title']) . '</h4>';

                                        foreach ($group_data['options'] as $option) {
                                            echo '<label class="wpavatar-checkbox">';
                                            echo '<input type="checkbox" name="wpavatar_network_controlled_options[]" value="' . esc_attr($option) . '" ' .
                                                 (in_array($option, $controlled_options) ? 'checked' : '') . '>';
                                            echo '<span class="wpavatar-checkbox-label">' . esc_html($all_options[$option]) . '</span>';
                                            echo '</label><br>';
                                        }

                                        echo '</div>';
                                    }
                                    echo '</div>';
                                    ?>
                                    <p class="description"><?php _e('选中的选项将在所有站点使用网络设置，未选中的选项可由站点管理员自定义。', 'wpavatar'); ?></p>
                                    <div class="wpavatar-action-buttons" style="margin-top:10px;">
                                        <button type="button" id="select-all-options" class="button button-secondary"><?php _e('全选', 'wpavatar'); ?></button>
                                        <button type="button" id="deselect-all-options" class="button button-secondary"><?php _e('取消全选', 'wpavatar'); ?></button>
                                        <button type="button" id="reset-default-options" class="button button-secondary"><?php _e('恢复默认', 'wpavatar'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Basic Settings Section -->
                <div class="wpavatar-section" id="wpavatar-section-basic" style="<?php echo $active_tab !== 'basic' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('基础设置', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('配置头像服务和CDN设置，应用于所有网络站点。', 'wpavatar'); ?></p>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-basic-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="basic">

                        <table class="form-table">
                            <tr>
                                <th><?php _e('启用初认头像', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-switch">
                                        <input type="checkbox" name="wpavatar_enable_cravatar" value="1" <?php checked(get_site_option('wpavatar_enable_cravatar', 1), 1); ?>>
                                        <span class="wpavatar-slider"></span>
                                        <span class="wpavatar-switch-label"><?php _e('替换WordPress默认头像为Cravatar', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('启用后将WordPress默认的Gravatar头像替换为Cravatar，提高国内访问速度', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('线路选择', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-radio">
                                        <input type="radio" name="wpavatar_cdn_type" value="cravatar_route" <?php checked(get_site_option('wpavatar_cdn_type', 'cravatar_route'), 'cravatar_route'); ?>>
                                        <span class="wpavatar-radio-label"><?php _e('Cravatar自选线路', 'wpavatar'); ?></span>
                                    </label><br>
                                    <label class="wpavatar-radio">
                                        <input type="radio" name="wpavatar_cdn_type" value="third_party" <?php checked(get_site_option('wpavatar_cdn_type', 'cravatar_route'), 'third_party'); ?>>
                                        <span class="wpavatar-radio-label"><?php _e('第三方镜像', 'wpavatar'); ?></span>
                                    </label><br>
                                    <label class="wpavatar-radio">
                                        <input type="radio" name="wpavatar_cdn_type" value="custom" <?php checked(get_site_option('wpavatar_cdn_type', 'cravatar_route'), 'custom'); ?>>
                                        <span class="wpavatar-radio-label"><?php _e('自定义CDN', 'wpavatar'); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr class="cdn-option cravatar-route-option" <?php echo get_site_option('wpavatar_cdn_type', 'cravatar_route') !== 'cravatar_route' ? 'style="display:none;"' : ''; ?>>
                                <th><?php _e('Cravatar官方源', 'wpavatar'); ?></th>
                                <td>
                                    <select name="wpavatar_cravatar_route" class="wpavatar-select">
                                        <option value="cravatar.cn" <?php selected(get_site_option('wpavatar_cravatar_route', 'cravatar.com'), 'cravatar.cn'); ?>><?php _e('默认线路 (cravatar.com)', 'wpavatar'); ?></option>
                                        <option value="cn.cravatar.com" <?php selected(get_site_option('wpavatar_cravatar_route', 'cravatar.com'), 'cn.cravatar.com'); ?>><?php _e('中国 (cn.cravatar.com)', 'wpavatar'); ?></option>
                                        <option value="hk.cravatar.com" <?php selected(get_site_option('wpavatar_cravatar_route', 'cravatar.com'), 'hk.cravatar.com'); ?>><?php _e('香港 (hk.cravatar.com)', 'wpavatar'); ?></option>
                                        <option value="en.cravatar.com" <?php selected(get_site_option('wpavatar_cravatar_route', 'cravatar.com'), 'en.cravatar.com'); ?>><?php _e('国际 (en.cravatar.com)', 'wpavatar'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('选择适合您网站访客的Cravatar线路', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr class="cdn-option third-party-option" <?php echo get_site_option('wpavatar_cdn_type', 'cravatar_route') !== 'third_party' ? 'style="display:none;"' : ''; ?>>
                                <th><?php _e('第三方镜像', 'wpavatar'); ?></th>
                                <td>
                                    <select name="wpavatar_third_party_mirror" class="wpavatar-select">
                                        <option value="weavatar.com" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'weavatar.com'); ?>><?php _e('WeAvatar (weavatar.com)', 'wpavatar'); ?></option>
                                        <option value="libravatar.org" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'libravatar.org'); ?>><?php _e('Libravatar (libravatar.org)', 'wpavatar'); ?></option>
                                        <option value="gravatar.loli.net" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'gravatar.loli.net'); ?>><?php _e('Loli镜像 (gravatar.loli.net)', 'wpavatar'); ?></option>
                                        <option value="gravatar.webp.se/avatar" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'gravatar.webp.se/avatar'); ?>><?php _e('Webp源 (gravatar.webp.se)', 'wpavatar'); ?></option>
                                        <option value="dn-qiniu-avatar.qbox.me/avatar" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'dn-qiniu-avatar.qbox.me/avatar'); ?>><?php _e('七牛镜像 (dn-qiniu-avatar)', 'wpavatar'); ?></option>
                                        <option value="gravatar.w3tt.com/avatar" <?php selected(get_site_option('wpavatar_third_party_mirror', 'weavatar.com'), 'gravatar.w3tt.com/avatar'); ?>><?php _e('万维网测试小组 (W3TT) ', 'wpavatar'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('选择第三方头像镜像站', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr class="cdn-option custom-cdn-option" <?php echo get_site_option('wpavatar_cdn_type', 'cravatar_route') !== 'custom' ? 'style="display:none;"' : ''; ?>>
                                <th><?php _e('自定义CDN', 'wpavatar'); ?></th>
                                <td>
                                    <input type="text" name="wpavatar_custom_cdn" value="<?php echo esc_attr(get_site_option('wpavatar_custom_cdn', '')); ?>" class="regular-text wpavatar-input">
                                    <p class="description"><?php _e('输入自定义CDN域名，例如：cdn.example.com', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('头像哈希方法', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-radio">
                                        <input type="radio" name="wpavatar_hash_method" value="md5" <?php checked(get_site_option('wpavatar_hash_method', 'md5'), 'md5'); ?>>
                                        <span class="wpavatar-radio-label"><?php _e('MD5 (Cravatar默认)', 'wpavatar'); ?></span>
                                    </label><br>
                                    <label class="wpavatar-radio">
                                        <input type="radio" name="wpavatar_hash_method" value="sha256" <?php checked(get_site_option('wpavatar_hash_method', 'md5'), 'sha256'); ?>>
                                        <span class="wpavatar-radio-label"><?php _e('SHA256 (Gravatar默认)', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('选择头像邮箱的哈希方法，Cravatar目前使用MD5，一般Gravatar镜像均为SHA256', 'wpavatar'); ?></p>
                                    <p class="description hash-method-notice" style="color: #d63638; <?php echo get_site_option('wpavatar_cdn_type', 'cravatar_route') !== 'cravatar_route' ? 'display:none;' : ''; ?>"><?php _e('注意：使用Cravatar服务时，哈希方法将仅使用MD5。', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('超时设置', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_timeout" value="<?php echo esc_attr(get_site_option('wpavatar_timeout', 5)); ?>" min="1" max="30" class="small-text wpavatar-input">
                                    <?php _e('秒', 'wpavatar'); ?>
                                    <p class="description"><?php _e('头像请求的最大等待时间，超过后将使用备用头像', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Cache Settings Section -->
                <div class="wpavatar-section" id="wpavatar-section-cache" style="<?php echo $active_tab !== 'cache' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('缓存控制', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('管理头像缓存设置和操作，应用于所有网络站点。', 'wpavatar'); ?></p>

                    <div class="wpavatar-stats-card">
                        <h3><?php _e('网络缓存统计', 'wpavatar'); ?></h3>
                        <div id="cache-stats" class="cache-stats-wrapper"></div>
                        <div class="wpavatar-action-buttons">
                            <button type="button" id="check-all-cache" class="button button-secondary"><?php _e('检查所有站点缓存', 'wpavatar'); ?></button>
                            <button type="button" id="purge-all-cache" class="button button-secondary"><?php _e('清空所有站点缓存', 'wpavatar'); ?></button>
                        </div>
                    </div>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-cache-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="cache">

                        <table class="form-table">
                            <tr>
                                <th><?php _e('启用本地缓存', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-switch">
                                        <input type="checkbox" name="wpavatar_enable_cache" value="1" <?php checked(get_site_option('wpavatar_enable_cache', 1)); ?>>
                                        <span class="wpavatar-slider"></span>
                                        <span class="wpavatar-switch-label"><?php _e('缓存头像到本地服务器', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('将头像缓存到本地可以减少外部请求，提高网站加载速度', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('缓存根目录', 'wpavatar'); ?></th>
                                <td>
                                    <input type="text" name="wpavatar_cache_path" value="<?php echo esc_attr(get_site_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR)); ?>" class="regular-text wpavatar-input">
                                    <p class="description"><?php _e('确保目录可写，每个站点将在此目录下创建独立的子目录', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('缓存过期时间', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_cache_expire" value="<?php echo esc_attr(get_site_option('wpavatar_cache_expire', 15)); ?>" min="1" max="90" class="small-text wpavatar-input">
                                    <?php _e('天', 'wpavatar'); ?>
                                    <p class="description"><?php _e('头像缓存的有效期，过期后将重新获取', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Advanced Settings Section -->
                <div class="wpavatar-section" id="wpavatar-section-advanced" style="<?php echo $active_tab !== 'advanced' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('高级设置', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('配置头像的SEO和备用方案，应用于所有网络站点。', 'wpavatar'); ?></p>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-advanced-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="advanced">

                        <table class="form-table">
                            <tr>
                                <th><?php _e('SEO替代文本', 'wpavatar'); ?></th>
                                <td>
                                    <input type="text" name="wpavatar_seo_alt" value="<?php echo esc_attr(get_site_option('wpavatar_seo_alt', '%s的头像')); ?>" class="regular-text wpavatar-input">
                                    <p class="description"><?php _e('头像的ALT文本，%s将被替换为用户名', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('头像加载失败处理', 'wpavatar'); ?></th>
                                <td>
                                    <label class="wpavatar-switch">
                                        <input type="checkbox" name="wpavatar_fallback_mode" value="1" <?php checked(get_site_option('wpavatar_fallback_mode', 1)); ?>>
                                        <span class="wpavatar-slider"></span>
                                        <span class="wpavatar-switch-label"><?php _e('启用备用头像', 'wpavatar'); ?></span>
                                    </label>
                                    <p class="description"><?php _e('当头像服务器无法访问或加载超时时，自动使用本地备用头像，避免拖慢网站加载速度', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('备用头像选择', 'wpavatar'); ?></th>
                                <td>
                                    <div class="default-avatar-options">
                                        <?php
                                        $local_avatars = \WPAvatar\Cravatar::get_local_avatars();
                                        $fallback_avatar = get_site_option('wpavatar_fallback_avatar', 'default');

                                        foreach ($local_avatars as $key => $avatar) :
                                        ?>
                                        <label>
                                            <input type="radio" name="wpavatar_fallback_avatar" value="<?php echo esc_attr($key); ?>" <?php checked($fallback_avatar, $key); ?>>
                                            <img src="<?php echo esc_url($avatar['url']); ?>" alt="<?php echo esc_attr($avatar['name']); ?>" width="48" height="48">
                                            <span class="avatar-option-name"><?php echo esc_html($avatar['name']); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php _e('选择您的故障备用头像', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Shortcode Settings Section -->
                <div class="wpavatar-section" id="wpavatar-section-shortcodes" style="<?php echo $active_tab !== 'shortcodes' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('简码设置', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('配置头像简码的默认参数，应用于所有网络站点。', 'wpavatar'); ?></p>

                    <div class="wpavatar-preview-container">
                        <h3><?php _e('头像预览', 'wpavatar'); ?></h3>
                        <div class="wpavatar-preview-wrapper">
                            <div class="wpavatar-preview-item">
                                <h4><?php _e('方形', 'wpavatar'); ?></h4>
                                <?php echo \WPAvatar\Shortcode::generate_preview(get_current_user_id(), 'square', 80); ?>
                            </div>
                            <div class="wpavatar-preview-item">
                                <h4><?php _e('圆角', 'wpavatar'); ?></h4>
                                <?php echo \WPAvatar\Shortcode::generate_preview(get_current_user_id(), 'rounded', 80); ?>
                            </div>
                            <div class="wpavatar-preview-item">
                                <h4><?php _e('圆形', 'wpavatar'); ?></h4>
                                <?php echo \WPAvatar\Shortcode::generate_preview(get_current_user_id(), 'circle', 80); ?>
                            </div>
                        </div>
                        <p class="description"><?php _e('预览使用当前登录账户的头像', 'wpavatar'); ?></p>
                    </div>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-shortcodes-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="shortcodes">

                        <table class="form-table">
                            <tr>
                                <th><?php _e('默认头像大小', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_shortcode_size" value="<?php echo esc_attr(get_site_option('wpavatar_shortcode_size', 96)); ?>" min="16" max="512" class="small-text wpavatar-input">
                                    <p class="description"><?php _e('简码默认头像大小（像素）', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('默认CSS类名', 'wpavatar'); ?></th>
                                <td>
                                    <input type="text" name="wpavatar_shortcode_class" value="<?php echo esc_attr(get_site_option('wpavatar_shortcode_class', 'wpavatar')); ?>" class="regular-text wpavatar-input">
                                    <p class="description"><?php _e('简码生成的头像默认CSS类', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('默认头像形状', 'wpavatar'); ?></th>
                                <td>
                                    <select name="wpavatar_shortcode_shape" class="wpavatar-select">
                                        <option value="square" <?php selected(get_site_option('wpavatar_shortcode_shape', 'square'), 'square'); ?>><?php _e('方形', 'wpavatar'); ?></option>
                                        <option value="rounded" <?php selected(get_site_option('wpavatar_shortcode_shape', 'square'), 'rounded'); ?>><?php _e('圆角方形', 'wpavatar'); ?></option>
                                        <option value="circle" <?php selected(get_site_option('wpavatar_shortcode_shape', 'square'), 'circle'); ?>><?php _e('圆形', 'wpavatar'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('简码生成的头像默认形状', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Marketing Settings Section -->
                <div class="wpavatar-section" id="wpavatar-section-marketing" style="<?php echo $active_tab !== 'marketing' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('营销组件设置', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('配置营销组件简码和显示效果，应用于所有网络站点。', 'wpavatar'); ?></p>

                    <form method="post" action="edit.php?action=wpavatar_network_settings" id="wpavatar-marketing-form">
                        <?php wp_nonce_field('wpavatar_network_settings'); ?>
                        <input type="hidden" name="tab" value="marketing">

                        <table class="form-table">
                            <tr>
                                <th colspan="2"><h3><?php _e('最近评论者设置', 'wpavatar'); ?></h3></th>
                            </tr>
                            <tr>
                                <th><?php _e('显示数量', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_commenters_count" value="<?php echo esc_attr(get_site_option('wpavatar_commenters_count', 15)); ?>" min="1" max="50" class="small-text wpavatar-input">
                                    <p class="description"><?php _e('显示的最近评论者数量', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('头像大小', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_commenters_size" value="<?php echo esc_attr(get_site_option('wpavatar_commenters_size', 45)); ?>" min="20" max="150" class="small-text wpavatar-input">
                                    <p class="description"><?php _e('评论者头像大小（像素）', 'wpavatar'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th colspan="2"><h3><?php _e('用户头像设置', 'wpavatar'); ?></h3></th>
                            </tr>
                            <tr>
                                <th><?php _e('显示数量', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_users_count" value="<?php echo esc_attr(get_site_option('wpavatar_users_count', 15)); ?>" min="1" max="50" class="small-text wpavatar-input">
                                    <p class="description"><?php _e('显示的用户数量', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('头像大小', 'wpavatar'); ?></th>
                                <td>
                                    <input type="number" name="wpavatar_users_size" value="<?php echo esc_attr(get_site_option('wpavatar_users_size', 40)); ?>" min="20" max="150" class="small-text wpavatar-input">
                                    <p class="description"><?php _e('用户头像大小（像素）', 'wpavatar'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpavatar-submit-wrapper">
                            <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                        </div>

                        <div class="wpavatar-card shortcode-docs">
                            <h3><?php _e('可用简码', 'wpavatar'); ?></h3>
                            <div class="wpavatar-table-wrapper">
                                <table class="widefat wpavatar-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('简码', 'wpavatar'); ?></th>
                                            <th><?php _e('描述', 'wpavatar'); ?></th>
                                            <th><?php _e('参数', 'wpavatar'); ?></th>
                                            <th><?php _e('示例', 'wpavatar'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>[wpavatar_latest_commenters]</code></td>
                                            <td><?php _e('显示最近评论者头像', 'wpavatar'); ?></td>
                                            <td>
                                                <ul>
                                                    <li><code>number</code> - <?php _e('显示的评论者数量', 'wpavatar'); ?></li>
                                                    <li><code>size</code> - <?php _e('头像大小（像素）', 'wpavatar'); ?></li>
                                                </ul>
                                            </td>
                                            <td><code>[wpavatar_latest_commenters number="10" size="50"]</code></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wpavatar_latest_users]</code></td>
                                            <td><?php _e('显示最新注册的用户头像', 'wpavatar'); ?></td>
                                            <td>
                                                <ul>
                                                    <li><code>number</code> - <?php _e('显示的用户数量', 'wpavatar'); ?></li>
                                                    <li><code>size</code> - <?php _e('头像大小（像素）', 'wpavatar'); ?></li>
                                                </ul>
                                            </td>
                                            <td><code>[wpavatar_latest_users number="12" size="40"]</code></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wpavatar_random_users]</code></td>
                                            <td><?php _e('显示随机用户头像', 'wpavatar'); ?></td>
                                            <td>
                                                <ul>
                                                    <li><code>number</code> - <?php _e('显示的用户数量', 'wpavatar'); ?></li>
                                                    <li><code>size</code> - <?php _e('头像大小（像素）', 'wpavatar'); ?></li>
                                                </ul>
                                            </td>
                                            <td><code>[wpavatar_random_users number="12" size="40"]</code></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wpavatar_author]</code></td>
                                            <td><?php _e('显示当前文章作者头像', 'wpavatar'); ?></td>
                                            <td>
                                                <ul>
                                                    <li><code>size</code> - <?php _e('头像大小（像素）', 'wpavatar'); ?></li>
                                                </ul>
                                            </td>
                                            <td><code>[wpavatar_author size="96"]</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tools Section -->
                <div class="wpavatar-section" id="wpavatar-section-tools" style="<?php echo $active_tab !== 'tools' ? 'display: none;' : ''; ?>">
                    <h2><?php _e('工具', 'wpavatar'); ?></h2>
                    <p class="wpavatar-section-desc"><?php _e('网络管理工具和操作。', 'wpavatar'); ?></p>

                    <div class="wpavatar-card tools-card">
                        <h3><?php _e('从站点导入设置', 'wpavatar'); ?></h3>
                        <p><?php _e('将网络中的任意站点的WPAvatar设置导入为网络默认设置。', 'wpavatar'); ?></p>

                        <form method="post" action="edit.php?action=wpavatar_import_site_settings" id="wpavatar-import-site-form">
                            <?php wp_nonce_field('wpavatar_import_site_settings'); ?>

                            <select name="import_blog_id" class="wpavatar-select">
                                <?php
                                $sites = get_sites(array('number' => 100));
                                foreach ($sites as $site) {
                                    switch_to_blog($site->blog_id);
                                    $blog_name = get_bloginfo('name');
                                    restore_current_blog();

                                    echo '<option value="' . esc_attr($site->blog_id) . '">' .
                                         esc_html($blog_name) . ' (' . esc_html($site->domain . $site->path) . ')</option>';
                                }
                                ?>
                            </select>

                            <div class="wpavatar-submit-wrapper" style="padding-top:10px; border-top:none;">
                                <button type="submit" class="button button-primary" id="import-site-settings"><?php _e('导入设置', 'wpavatar'); ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="wpavatar-card tools-card">
                        <h3><?php _e('批量操作', 'wpavatar'); ?></h3>
                        <p><?php _e('对网络中的所有站点进行批量操作。', 'wpavatar'); ?></p>

                        <form method="post" action="edit.php?action=wpavatar_apply_to_all_sites" id="wpavatar-bulk-action-form">
                            <?php wp_nonce_field('wpavatar_apply_to_all_sites'); ?>

                            <p><strong><?php _e('将当前网络设置应用到所有站点', 'wpavatar'); ?></strong></p>
                            <p class="description"><?php _e('这将覆盖每个站点的现有设置，使其与网络设置保持一致。', 'wpavatar'); ?></p>

                            <div class="wpavatar-submit-wrapper" style="padding-top:10px; border-top:none;">
                                <button type="submit" class="button button-primary" id="apply-to-all-sites"><?php _e('应用到所有站点', 'wpavatar'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .network-control-options {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        .option-group {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .option-group h4 {
            margin-top: 0;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .wpavatar-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .wpavatar-checkbox-label {
            margin-left: 8px;
        }
        .tools-card {
            margin-bottom: 20px;
        }
        @media (max-width: 782px) {
            .network-control-options {
                flex-direction: column;
            }
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Select/deselect all network controlled options
            $('#select-all-options').on('click', function() {
                $('input[name="wpavatar_network_controlled_options[]"]').prop('checked', true);
            });

            $('#deselect-all-options').on('click', function() {
                $('input[name="wpavatar_network_controlled_options[]"]').prop('checked', false);
            });

            $('#reset-default-options').on('click', function() {
                if (confirm(wpavatar_l10n.confirm_reset)) {
                    var defaultOptions = ['wpavatar_enable_cravatar', 'wpavatar_cdn_type', 'wpavatar_cravatar_route', 'wpavatar_third_party_mirror', 'wpavatar_custom_cdn'];

                    $('input[name="wpavatar_network_controlled_options[]"]').each(function() {
                        $(this).prop('checked', defaultOptions.indexOf($(this).val()) !== -1);
                    });
                }
            });

            // Network cache management
            $('#check-all-cache').on('click', function() {
                var $button = $(this);
                var $stats = $('#cache-stats');

                $button.prop('disabled', true).text(wpavatar_l10n.checking);
                $stats.html('<p>' + wpavatar_l10n.checking_status + '</p>');

                $.ajax({
                    type: 'POST',
                    url: wpavatar.ajaxurl,
                    data: {
                        action: 'wpavatar_check_all_cache',
                        nonce: wpavatar.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $stats.html(response.data);
                        } else {
                            $stats.html('<div class="error"><p>' + (response.data || wpavatar_l10n.check_failed) + '</p></div>');
                        }
                    },
                    error: function() {
                        $stats.html('<div class="error"><p>' + wpavatar_l10n.request_failed + '</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('检查所有站点缓存', 'wpavatar'); ?>');
                    }
                });
            });

            $('#purge-all-cache').on('click', function() {
                var $button = $(this);
                var $stats = $('#cache-stats');
                var $status = $('#wpavatar-status');

                if (!confirm(wpavatar_l10n.confirm_purge)) {
                    return;
                }

                $button.prop('disabled', true).text(wpavatar_l10n.purging);
                $stats.html('<p>' + wpavatar_l10n.purging_cache + '</p>');

                $.ajax({
                    type: 'POST',
                    url: wpavatar.ajaxurl,
                    data: {
                        action: 'wpavatar_purge_all_cache',
                        nonce: wpavatar.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.removeClass('notice-error')
                                   .addClass('notice-success')
                                   .text(response.data)
                                   .show()
                                   .delay(3000)
                                   .fadeOut();

                            setTimeout(function() {
                                $('#check-all-cache').trigger('click');
                            }, 1000);
                        } else {
                            $status.removeClass('notice-success')
                                   .addClass('notice-error')
                                   .text(response.data || wpavatar_l10n.purge_failed)
                                   .show();
                        }
                    },
                    error: function() {
                        $status.removeClass('notice-success')
                               .addClass('notice-error')
                               .text(wpavatar_l10n.request_failed)
                               .show();
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('清空所有站点缓存', 'wpavatar'); ?>');
                    }
                });
            });

            // Import site settings confirmation
            $('#import-site-settings').on('click', function(e) {
                if (!confirm(wpavatar_l10n.confirm_import)) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // Apply to all sites confirmation
            $('#apply-to-all-sites').on('click', function(e) {
                if (!confirm('<?php _e('确定要将网络设置应用到所有站点吗？此操作将覆盖每个站点的现有设置。', 'wpavatar'); ?>')) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            // Check cache stats on load if on cache tab
            if ($('#wpavatar-section-cache').is(':visible')) {
                setTimeout(function() {
                    $('#check-all-cache').trigger('click');
                }, 300);
            }
        });
        </script>
        <?php
    }

    /**
     * Save network settings
     */
    public static function save_network_settings() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('您没有足够权限修改这些设置。', 'wpavatar'));
            return;
        }

        // Verify nonce
        check_admin_referer('wpavatar_network_settings');

        // Get the current tab
        $current_tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : '';

        // Process network management settings
        if ($current_tab === 'network') {
            // Network management settings
            update_site_option('wpavatar_network_enabled', isset($_POST['wpavatar_network_enabled']) ? 1 : 0);
            update_site_option('wpavatar_network_enforce', isset($_POST['wpavatar_network_enforce']) ? 1 : 0);

            // Network controlled options
            $controlled_options = isset($_POST['wpavatar_network_controlled_options']) ? $_POST['wpavatar_network_controlled_options'] : array();
            update_site_option('wpavatar_network_controlled_options', $controlled_options);
        }

        // Process basic settings
        if ($current_tab === 'basic') {
            update_site_option('wpavatar_enable_cravatar', isset($_POST['wpavatar_enable_cravatar']) ? 1 : 0);
            update_site_option('wpavatar_cdn_type', sanitize_text_field($_POST['wpavatar_cdn_type'] ?? 'cravatar_route'));
            update_site_option('wpavatar_cravatar_route', sanitize_text_field($_POST['wpavatar_cravatar_route'] ?? 'cravatar.com'));
            update_site_option('wpavatar_third_party_mirror', sanitize_text_field($_POST['wpavatar_third_party_mirror'] ?? 'weavatar.com'));
            update_site_option('wpavatar_custom_cdn', sanitize_text_field($_POST['wpavatar_custom_cdn'] ?? ''));
            update_site_option('wpavatar_hash_method', sanitize_text_field($_POST['wpavatar_hash_method'] ?? 'md5'));
            update_site_option('wpavatar_timeout', intval($_POST['wpavatar_timeout'] ?? 5));
        }

        // Process cache settings
        if ($current_tab === 'cache') {
            update_site_option('wpavatar_enable_cache', isset($_POST['wpavatar_enable_cache']) ? 1 : 0);

            // Sanitize cache path
            $cache_path = sanitize_text_field($_POST['wpavatar_cache_path'] ?? WPAVATAR_CACHE_DIR);
            $cache_path = rtrim($cache_path, '/\\') . '/';

            if (!preg_match('~^(?:/|\\\\|[a-zA-Z]:)~', $cache_path)) {
                $cache_path = WP_CONTENT_DIR . '/' . ltrim($cache_path, '/\\');
            }

            if (!file_exists($cache_path)) {
                if (!wp_mkdir_p($cache_path)) {
                    add_settings_error(
                        'wpavatar_cache',
                        'cache_path_invalid',
                        __('无法创建缓存目录，请检查权限', 'wpavatar'),
                        'error'
                    );
                }
            } elseif (!is_dir($cache_path)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_path_invalid',
                    __('指定的路径不是有效目录', 'wpavatar'),
                    'error'
                );
            } elseif (!is_writable($cache_path)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_path_invalid',
                    __('缓存目录不可写，请检查权限', 'wpavatar'),
                    'error'
                );
            } else {
                // Valid cache path, create index.php if it doesn't exist
                $index_file = $cache_path . 'index.php';
                if (!file_exists($index_file)) {
                    @file_put_contents($index_file, '<?php // Silence is golden.');
                }

                update_site_option('wpavatar_cache_path', $cache_path);
            }

            update_site_option('wpavatar_cache_expire', intval($_POST['wpavatar_cache_expire'] ?? 15));
        }

        // Process advanced settings
        if ($current_tab === 'advanced') {
            update_site_option('wpavatar_seo_alt', sanitize_text_field($_POST['wpavatar_seo_alt'] ?? '%s的头像'));
            update_site_option('wpavatar_fallback_mode', isset($_POST['wpavatar_fallback_mode']) ? 1 : 0);
            update_site_option('wpavatar_fallback_avatar', sanitize_text_field($_POST['wpavatar_fallback_avatar'] ?? 'default'));
        }

        // Process shortcode settings
        if ($current_tab === 'shortcodes') {
            update_site_option('wpavatar_shortcode_size', intval($_POST['wpavatar_shortcode_size'] ?? 96));
            update_site_option('wpavatar_shortcode_class', sanitize_text_field($_POST['wpavatar_shortcode_class'] ?? 'wpavatar'));
            update_site_option('wpavatar_shortcode_shape', sanitize_text_field($_POST['wpavatar_shortcode_shape'] ?? 'square'));
        }

        // Process marketing settings
        if ($current_tab === 'marketing') {
            update_site_option('wpavatar_commenters_count', intval($_POST['wpavatar_commenters_count'] ?? 15));
            update_site_option('wpavatar_commenters_size', intval($_POST['wpavatar_commenters_size'] ?? 45));
            update_site_option('wpavatar_users_count', intval($_POST['wpavatar_users_count'] ?? 15));
            update_site_option('wpavatar_users_size', intval($_POST['wpavatar_users_size'] ?? 40));
        }

        // Redirect back to the appropriate page with update message
        $redirect_url = add_query_arg([
            'page' => 'wpavatar-network',
            'updated' => 'true'
        ], network_admin_url('settings.php'));

        // Add the tab if it exists
        if (!empty($current_tab)) {
            $redirect_url = add_query_arg('tab', $current_tab, $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Import settings from a site to network settings
     */
    public static function import_site_settings() {
        if (!isset($_POST['import_blog_id']) || !current_user_can('manage_network_options')) {
            wp_die(__('无效的请求或权限不足。', 'wpavatar'));
            return;
        }

        check_admin_referer('wpavatar_import_site_settings');

        $blog_id = intval($_POST['import_blog_id']);
        if ($blog_id <= 0) {
            wp_die(__('无效的站点ID。', 'wpavatar'));
            return;
        }

        // Switch to the selected blog to get its settings
        switch_to_blog($blog_id);

        // List of all options we want to import
        $options_to_import = [
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
            'wpavatar_shortcode_shape',
            // 添加营销组件选项
            'wpavatar_commenters_count',
            'wpavatar_commenters_size',
            'wpavatar_users_count',
            'wpavatar_users_size'
        ];

        // Copy options from site to network
        foreach ($options_to_import as $option) {
            $value = get_option($option);
            if ($value !== false) {
                update_site_option($option, $value);
            }
        }

        restore_current_blog();

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'page' => 'wpavatar-network',
            'tab' => 'tools',
            'imported' => 'true'
        ], network_admin_url('settings.php')));

        exit;
    }

    /**
     * Apply network settings to all sites
     */
    public static function apply_to_all_sites() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('权限不足。', 'wpavatar'));
            return;
        }

        check_admin_referer('wpavatar_apply_to_all_sites');

        // Get list of all sites
        $sites = get_sites(array('number' => 500)); // Limit to 500 sites for performance

        // Get network-controlled options
        $controlled_options = get_site_option('wpavatar_network_controlled_options', self::$default_controlled_options);
        if (!is_array($controlled_options)) {
            $controlled_options = explode(',', $controlled_options);
        }

        // Apply settings to each site
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            // Apply all options in the controlled list
            foreach ($controlled_options as $option) {
                $network_value = get_site_option($option);
                if ($network_value !== false) {
                    update_option($option, $network_value);
                }
            }

            // 确保也应用营销组件设置
            $marketing_options = [
                'wpavatar_commenters_count',
                'wpavatar_commenters_size',
                'wpavatar_users_count',
                'wpavatar_users_size'
            ];

            foreach ($marketing_options as $option) {
                if (in_array($option, $controlled_options)) {
                    $network_value = get_site_option($option);
                    if ($network_value !== false) {
                        update_option($option, $network_value);
                    }
                }
            }

            // Ensure site cache directory exists
            $cache_base = get_site_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
            $site_cache_dir = trailingslashit($cache_base) . 'site-' . $site->blog_id;

            if (!file_exists($site_cache_dir)) {
                wp_mkdir_p($site_cache_dir);

                // Create index.php
                $index_file = $site_cache_dir . '/index.php';
                if (!file_exists($index_file)) {
                    @file_put_contents($index_file, '<?php // Silence is golden.');
                }
            }

            restore_current_blog();
        }

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'page' => 'wpavatar-network',
            'tab' => 'tools',
            'applied' => 'true'
        ], network_admin_url('settings.php')));

        exit;
    }
}

// Add actions for tools
add_action('network_admin_edit_wpavatar_import_site_settings', ['\WPAvatar\Network', 'import_site_settings']);
add_action('network_admin_edit_wpavatar_apply_to_all_sites', ['\WPAvatar\Network', 'apply_to_all_sites']);
