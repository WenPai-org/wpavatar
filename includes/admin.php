<?php
namespace WPAvatar;

class Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_notices', [__CLASS__, 'display_notices']);
    }

    public static function add_admin_menu() {
        add_options_page(
            __('文派头像设置', 'wpavatar'),
            __('头像', 'wpavatar'),
            'manage_options',
            'wpavatar-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        // 注册设置组和设置字段
        register_setting('wpavatar_basic', 'wpavatar_enable_cravatar', ['type' => 'boolean']);
        register_setting('wpavatar_basic', 'wpavatar_cdn_type', ['type' => 'string']);
        register_setting('wpavatar_basic', 'wpavatar_cravatar_route', ['type' => 'string']);
        register_setting('wpavatar_basic', 'wpavatar_third_party_mirror', ['type' => 'string']);
        register_setting('wpavatar_basic', 'wpavatar_custom_cdn', ['type' => 'string']);
        register_setting('wpavatar_basic', 'wpavatar_hash_method', ['type' => 'string']);
        register_setting('wpavatar_basic', 'wpavatar_timeout', ['type' => 'integer']);

        register_setting('wpavatar_cache', 'wpavatar_enable_cache', ['type' => 'boolean']);
        register_setting('wpavatar_cache', 'wpavatar_cache_path', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_cache_path']
        ]);
        register_setting('wpavatar_cache', 'wpavatar_cache_expire', ['type' => 'integer']);

        register_setting('wpavatar_advanced', 'wpavatar_seo_alt', ['type' => 'string']);
        register_setting('wpavatar_advanced', 'wpavatar_fallback_mode', ['type' => 'boolean']);
        register_setting('wpavatar_advanced', 'wpavatar_fallback_avatar', ['type' => 'string']);

        register_setting('wpavatar_shortcodes', 'wpavatar_shortcode_size', ['type' => 'integer']);
        register_setting('wpavatar_shortcodes', 'wpavatar_shortcode_class', ['type' => 'string']);
        register_setting('wpavatar_shortcodes', 'wpavatar_shortcode_shape', ['type' => 'string']);
    }

    public static function sanitize_cache_path($value) {
        $value = sanitize_text_field($value);
        $value = rtrim($value, '/\\') . '/';

        if (!preg_match('~^(?:/|\\\\|[a-zA-Z]:)~', $value)) {
            $value = WP_CONTENT_DIR . '/' . ltrim($value, '/\\');
        }

        if (!file_exists($value)) {
            if (!wp_mkdir_p($value)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_path_invalid',
                    __('无法创建缓存目录，请检查权限', 'wpavatar'),
                    'error'
                );
                return get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
            }
        } elseif (!is_dir($value)) {
            add_settings_error(
                'wpavatar_cache',
                'cache_path_invalid',
                __('指定的路径不是有效目录', 'wpavatar'),
                'error'
            );
            return get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        } elseif (!is_writable($value)) {
            add_settings_error(
                'wpavatar_cache',
                'cache_path_invalid',
                __('缓存目录不可写，请检查权限', 'wpavatar'),
                'error'
            );
            return get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        }

        $index_file = $value . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden.');
        }

        return $value;
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'settings_page_wpavatar-settings') {
            return;
        }

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
            'cache_path' => get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR),
            'plugin_url' => WPAVATAR_PLUGIN_URL,
            'assets_url' => WPAVATAR_PLUGIN_URL . 'assets/',
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
            'settings_saved' => __('设置已成功保存。', 'wpavatar')
        ]);
    }

    public static function display_notices() {
        settings_errors('wpavatar_basic');
        settings_errors('wpavatar_cache');
        settings_errors('wpavatar_advanced');
        settings_errors('wpavatar_shortcodes');
    }

    public static function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
        ?>
        <div class="wrap wpavatar-settings">
          <h1><?php esc_html_e('文派头像设置', 'wpavatar'); ?>
              <span style="font-size: 13px; padding-left: 10px;"><?php printf(esc_html__('Version: %s', 'wpavatar'), esc_html(WPAVATAR_VERSION)); ?></span>
              <a href="https://wpavatar.com/document/" target="_blank" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('Document', 'wpavatar'); ?></a>
              <a href="https://sharecms.com/forums/" target="_blank" class="button button-secondary"><?php esc_html_e('Support', 'wpavatar'); ?></a>
          </h1>

            <div id="wpavatar-status" class="notice" style="display:none; margin-top: 10px;"></div>
            <div class="wpavatar-card">
            <div class="wpavatar-tabs-wrapper">
                <div class="wpavatar-sync-tabs">
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
                </div>
            </div>

            <div class="wpavatar-section" id="wpavatar-section-basic" style="<?php echo $active_tab !== 'basic' ? 'display: none;' : ''; ?>">
                <h2><?php _e('基础设置', 'wpavatar'); ?></h2>
                <p class="wpavatar-section-desc"><?php _e('配置头像服务和CDN设置。', 'wpavatar'); ?></p>

                <form method="post" action="options.php" id="wpavatar-basic-form">
                    <?php
                    settings_fields('wpavatar_basic');

                    $enable_cravatar = get_option('wpavatar_enable_cravatar', 1);
                    $cdn_type = get_option('wpavatar_cdn_type', 'cravatar_route');
                    $cravatar_route = get_option('wpavatar_cravatar_route', 'cravatar.com');
                    $third_party_mirror = get_option('wpavatar_third_party_mirror', 'weavatar.com');
                    $custom_cdn = get_option('wpavatar_custom_cdn', '');
                    $hash_method = get_option('wpavatar_hash_method', 'md5');
                    $timeout = get_option('wpavatar_timeout', 5);
                    ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('启用初认头像', 'wpavatar'); ?></th>
                            <td>
                                <label class="wpavatar-switch">
                                    <input type="checkbox" name="wpavatar_enable_cravatar" value="1" <?php checked($enable_cravatar); ?>>
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
                                    <input type="radio" name="wpavatar_cdn_type" value="cravatar_route" <?php checked($cdn_type, 'cravatar_route'); ?>>
                                    <span class="wpavatar-radio-label"><?php _e('Cravatar自选线路', 'wpavatar'); ?></span>
                                </label><br>
                                <label class="wpavatar-radio">
                                    <input type="radio" name="wpavatar_cdn_type" value="third_party" <?php checked($cdn_type, 'third_party'); ?>>
                                    <span class="wpavatar-radio-label"><?php _e('第三方镜像', 'wpavatar'); ?></span>
                                </label><br>
                                <label class="wpavatar-radio">
                                    <input type="radio" name="wpavatar_cdn_type" value="custom" <?php checked($cdn_type, 'custom'); ?>>
                                    <span class="wpavatar-radio-label"><?php _e('自定义CDN', 'wpavatar'); ?></span>
                                </label>
                            </td>
                        </tr>
                        <tr class="cdn-option cravatar-route-option" <?php echo $cdn_type !== 'cravatar_route' ? 'style="display:none;"' : ''; ?>>
                            <th><?php _e('Cravatar官方源', 'wpavatar'); ?></th>
                            <td>
                                <select name="wpavatar_cravatar_route" class="wpavatar-select">
                                    <option value="cravatar.cn" <?php selected($cravatar_route, 'cravatar.cn'); ?>><?php _e('默认线路 (cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="cn.cravatar.com" <?php selected($cravatar_route, 'cn.cravatar.com'); ?>><?php _e('中国 (cn.cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="hk.cravatar.com" <?php selected($cravatar_route, 'hk.cravatar.com'); ?>><?php _e('香港 (hk.cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="en.cravatar.com" <?php selected($cravatar_route, 'en.cravatar.com'); ?>><?php _e('国际 (en.cravatar.com)', 'wpavatar'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择适合您网站访客的Cravatar线路', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr class="cdn-option third-party-option" <?php echo $cdn_type !== 'third_party' ? 'style="display:none;"' : ''; ?>>
                            <th><?php _e('第三方镜像', 'wpavatar'); ?></th>
                            <td>
                                <select name="wpavatar_third_party_mirror" class="wpavatar-select">
                                    <option value="weavatar.com" <?php selected($third_party_mirror, 'weavatar.com'); ?>><?php _e('WeAvatar (weavatar.com)', 'wpavatar'); ?></option>
                                    <option value="libravatar.org" <?php selected($third_party_mirror, 'libravatar.org'); ?>><?php _e('Libravatar (libravatar.org)', 'wpavatar'); ?></option>
                                    <option value="gravatar.loli.net" <?php selected($third_party_mirror, 'gravatar.loli.net'); ?>><?php _e('Loli镜像 (gravatar.loli.net)', 'wpavatar'); ?></option>
                                    <option value="gravatar.webp.se/avatar" <?php selected($third_party_mirror, 'gravatar.webp.se/avatar'); ?>><?php _e('Webp源 (gravatar.webp.se)', 'wpavatar'); ?></option>
                                    <option value="dn-qiniu-avatar.qbox.me/avatar" <?php selected($third_party_mirror, 'dn-qiniu-avatar.qbox.me/avatar'); ?>><?php _e('七牛镜像 (dn-qiniu-avatar)', 'wpavatar'); ?></option>
                                    <option value="gravatar.w3tt.com/avatar" <?php selected($third_party_mirror, 'gravatar.w3tt.com/avatar'); ?>><?php _e('万维网测试小组 (W3TT) ', 'wpavatar'); ?></option>
                                </select>
                                <p class="description"><?php _e('选择第三方头像镜像站', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr class="cdn-option custom-cdn-option" <?php echo $cdn_type !== 'custom' ? 'style="display:none;"' : ''; ?>>
                            <th><?php _e('自定义CDN', 'wpavatar'); ?></th>
                            <td>
                                <input type="text" name="wpavatar_custom_cdn" value="<?php echo esc_attr($custom_cdn); ?>" class="regular-text wpavatar-input">
                                <p class="description"><?php _e('输入自定义CDN域名，例如：cdn.example.com', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('头像哈希方法', 'wpavatar'); ?></th>
                            <td>
                                <label class="wpavatar-radio">
                                    <input type="radio" name="wpavatar_hash_method" value="md5" <?php checked($hash_method, 'md5'); ?>>
                                    <span class="wpavatar-radio-label"><?php _e('MD5 (Cravatar默认)', 'wpavatar'); ?></span>
                                </label><br>
                                <label class="wpavatar-radio">
                                    <input type="radio" name="wpavatar_hash_method" value="sha256" <?php checked($hash_method, 'sha256'); ?>>
                                    <span class="wpavatar-radio-label"><?php _e('SHA256 (Gravatar默认)', 'wpavatar'); ?></span>
                                </label>
                                <p class="description"><?php _e('选择头像邮箱的哈希方法，Cravatar目前使用MD5，一般Gravatar镜像均为SHA256', 'wpavatar'); ?></p>
                                <p class="description hash-method-notice" style="color: #d63638; <?php echo $cdn_type !== 'cravatar_route' ? 'display:none;' : ''; ?>"><?php _e('注意：使用Cravatar服务时，哈希方法将仅使用MD5', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('超时设置', 'wpavatar'); ?></th>
                            <td>
                                <input type="number" name="wpavatar_timeout" value="<?php echo esc_attr($timeout); ?>" min="1" max="30" class="small-text wpavatar-input">
                                <?php _e('秒', 'wpavatar'); ?>
                                <p class="description"><?php _e('头像请求的最大等待时间，超过后将使用备用头像', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div class="wpavatar-submit-wrapper">
                        <?php wp_nonce_field('wpavatar_basic_nonce', 'wpavatar_basic_nonce'); ?>
                        <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                    </div>
                </form>
            </div>

            <div class="wpavatar-section" id="wpavatar-section-cache" style="<?php echo $active_tab !== 'cache' ? 'display: none;' : ''; ?>">
                <h2><?php _e('缓存控制', 'wpavatar'); ?></h2>
                <p class="wpavatar-section-desc"><?php _e('管理头像缓存设置和操作。', 'wpavatar'); ?></p>

                <div class="wpavatar-stats-card">
                    <h3><?php _e('缓存统计', 'wpavatar'); ?></h3>
                    <div id="cache-stats" class="cache-stats-wrapper"></div>
                    <div class="wpavatar-action-buttons">
                        <button type="button" id="check-cache" class="button button-secondary"><?php _e('检查缓存状态', 'wpavatar'); ?></button>
                        <button type="button" id="purge-cache" class="button button-secondary"><?php _e('清空缓存', 'wpavatar'); ?></button>
                    </div>
                </div>

                <form method="post" action="options.php" id="wpavatar-cache-form">
                    <?php
                    settings_fields('wpavatar_cache');

                    $enable_cache = get_option('wpavatar_enable_cache', 1);
                    $cache_path = get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
                    $cache_expire = get_option('wpavatar_cache_expire', 7);
                    ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('启用本地缓存', 'wpavatar'); ?></th>
                            <td>
                                <label class="wpavatar-switch">
                                    <input type="checkbox" name="wpavatar_enable_cache" value="1" <?php checked($enable_cache); ?>>
                                    <span class="wpavatar-slider"></span>
                                    <span class="wpavatar-switch-label"><?php _e('缓存头像到本地服务器', 'wpavatar'); ?></span>
                                </label>
                                <p class="description"><?php _e('将头像缓存到本地可以减少外部请求，提高网站加载速度', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('缓存目录', 'wpavatar'); ?></th>
                            <td>
                                <input type="text" name="wpavatar_cache_path" value="<?php echo esc_attr($cache_path); ?>" class="regular-text wpavatar-input">
                                <p class="description"><?php _e('确保目录可写，建议路径：/wp-content/uploads/cravatar', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('缓存过期时间', 'wpavatar'); ?></th>
                            <td>
                                <input type="number" name="wpavatar_cache_expire" value="<?php echo esc_attr($cache_expire); ?>" min="1" max="30" class="small-text wpavatar-input">
                                <?php _e('天', 'wpavatar'); ?>
                                <p class="description"><?php _e('头像缓存的有效期，过期后将重新获取', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div class="wpavatar-submit-wrapper">
                        <?php wp_nonce_field('wpavatar_cache_nonce', 'wpavatar_cache_nonce'); ?>
                        <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                    </div>
                </form>
            </div>

            <div class="wpavatar-section" id="wpavatar-section-advanced" style="<?php echo $active_tab !== 'advanced' ? 'display: none;' : ''; ?>">
                <h2><?php _e('高级设置', 'wpavatar'); ?></h2>
                <p class="wpavatar-section-desc"><?php _e('配置头像的SEO和备用方案。', 'wpavatar'); ?></p>

                <form method="post" action="options.php" id="wpavatar-advanced-form">
                    <?php
                    settings_fields('wpavatar_advanced');

                    $seo_alt = get_option('wpavatar_seo_alt', '%s的头像');
                    $fallback_mode = get_option('wpavatar_fallback_mode', 1);
                    $fallback_avatar = get_option('wpavatar_fallback_avatar', 'default');

                    $local_avatars = \WPAvatar\Cravatar::get_local_avatars();
                    ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('SEO替代文本', 'wpavatar'); ?></th>
                            <td>
                                <input type="text" name="wpavatar_seo_alt" value="<?php echo esc_attr($seo_alt); ?>" class="regular-text wpavatar-input">
                                <p class="description"><?php _e('头像的ALT文本，%s将被替换为用户名', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('头像加载失败处理', 'wpavatar'); ?></th>
                            <td>
                                <label class="wpavatar-switch">
                                    <input type="checkbox" name="wpavatar_fallback_mode" value="1" <?php checked($fallback_mode); ?>>
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
                                    <?php foreach ($local_avatars as $key => $avatar) : ?>
                                    <label>
                                        <input type="radio" name="wpavatar_fallback_avatar" value="<?php echo esc_attr($key); ?>" <?php checked($fallback_avatar, $key); ?>>
                                        <img src="<?php echo esc_url($avatar['url']); ?>" alt="<?php echo esc_attr($avatar['name']); ?>" width="48" height="48">
                                        <span class="avatar-option-name"><?php echo esc_html($avatar['name']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">
                                    <?php
                                    $wpcy_installed = class_exists('WP_China_Yes'); // 检查是否安装了文派叶子插件
                                    $wpcy_link = $wpcy_installed
                                        ? admin_url('admin.php?page=wp-china-yes')
                                        : 'https://wpcy.com';

                                    printf(
                                        __('选择您喜欢的故障备用头像，如需智能线路切换，请使用%s。', 'wpavatar'),
                                        sprintf(
                                            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                                            esc_url($wpcy_link),
                                            __('文派叶子 🍃（WPCY.COM）', 'wpavatar')
                                        )
                                    );
                                    ?>
                                </p>
                                                          </td>
                        </tr>
                    </table>
                    <div class="wpavatar-submit-wrapper">
                        <?php wp_nonce_field('wpavatar_advanced_nonce', 'wpavatar_advanced_nonce'); ?>
                        <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                    </div>
                </form>
            </div>


            <div class="wpavatar-section" id="wpavatar-section-shortcodes" style="<?php echo $active_tab !== 'shortcodes' ? 'display: none;' : ''; ?>">
                <h2><?php _e('简码设置', 'wpavatar'); ?></h2>
                <p class="wpavatar-section-desc"><?php _e('配置头像简码的默认参数和预览效果。', 'wpavatar'); ?></p>

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
                        <div class="wpavatar-preview-item">
                            <h4><?php _e('方形', 'wpavatar'); ?></h4>
                            <img src="<?= WPAVATAR_PLUGIN_URL ?>assets/images/wapuu-china.png" width="80" height="80" class="avatar-square">
                        </div>
                        <div class="wpavatar-preview-item">
                            <h4><?php _e('圆角', 'wpavatar'); ?></h4>
                            <img src="<?= WPAVATAR_PLUGIN_URL ?>assets/images/wapuu-china.png" width="80" height="80" class="avatar-rounded" style="border-radius:8px">
                        </div>
                        <div class="wpavatar-preview-item">
                            <h4><?php _e('圆形', 'wpavatar'); ?></h4>
                            <img src="<?= WPAVATAR_PLUGIN_URL ?>assets/images/wapuu-china.png" width="80" height="80" class="avatar-circle" style="border-radius:50%">
                        </div>
                    </div>
                    <p class="description"><?php _e('预览使用当前登录账户的头像和示例图片', 'wpavatar'); ?></p>
                </div>

                <form method="post" action="options.php" id="wpavatar-shortcodes-form">
                    <?php
                    settings_fields('wpavatar_shortcodes');

                    $shortcode_size = get_option('wpavatar_shortcode_size', 96);
                    $shortcode_class = get_option('wpavatar_shortcode_class', 'wpavatar');
                    $shortcode_shape = get_option('wpavatar_shortcode_shape', 'square');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('默认头像大小', 'wpavatar'); ?></th>
                            <td>
                                <input type="number" name="wpavatar_shortcode_size" value="<?php echo esc_attr($shortcode_size); ?>" min="16" max="512" class="small-text wpavatar-input">
                                <p class="description"><?php _e('简码默认头像大小（像素）', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('默认CSS类名', 'wpavatar'); ?></th>
                            <td>
                                <input type="text" name="wpavatar_shortcode_class" value="<?php echo esc_attr($shortcode_class); ?>" class="regular-text wpavatar-input">
                                <p class="description"><?php _e('简码生成的头像默认CSS类', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('默认头像形状', 'wpavatar'); ?></th>
                            <td>
                                <select name="wpavatar_shortcode_shape" class="wpavatar-select">
                                    <option value="square" <?php selected($shortcode_shape, 'square'); ?>><?php _e('方形', 'wpavatar'); ?></option>
                                    <option value="rounded" <?php selected($shortcode_shape, 'rounded'); ?>><?php _e('圆角方形', 'wpavatar'); ?></option>
                                    <option value="circle" <?php selected($shortcode_shape, 'circle'); ?>><?php _e('圆形', 'wpavatar'); ?></option>
                                </select>
                                <p class="description"><?php _e('简码生成的头像默认形状', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div class="wpavatar-submit-wrapper">
                        <?php wp_nonce_field('wpavatar_shortcodes_nonce', 'wpavatar_shortcodes_nonce'); ?>
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
                                        <td><code>[wpavatar]</code></td>
                                        <td><?php _e('显示用户头像', 'wpavatar'); ?></td>
                                        <td>
                                            <ul>
                                                <li><code>size</code> - <?php _e('头像大小（像素）', 'wpavatar'); ?></li>
                                                <li><code>user_id</code> - <?php _e('用户ID，默认为当前用户', 'wpavatar'); ?></li>
                                                <li><code>class</code> - <?php _e('CSS类名', 'wpavatar'); ?></li>
                                                <li><code>shape</code> - <?php _e('形状：square(方形)、rounded(圆角)、circle(圆形)', 'wpavatar'); ?></li>
                                                <li><code>title</code> - <?php _e('可选的标题文本', 'wpavatar'); ?></li>
                                            </ul>
                                        </td>
                                        <td><code>[wpavatar size="128" user_id="1" shape="circle" class="my-avatar"]</code></td>
                                    </tr>
                                    <tr>
                                        <td><code>[wpavatar_username]</code></td>
                                        <td><?php _e('显示用户名', 'wpavatar'); ?></td>
                                        <td>
                                            <ul>
                                                <li><code>user_id</code> - <?php _e('用户ID，默认为当前用户', 'wpavatar'); ?></li>
                                                <li><code>before</code> - <?php _e('用户名前的文本', 'wpavatar'); ?></li>
                                                <li><code>after</code> - <?php _e('用户名后的文本', 'wpavatar'); ?></li>
                                            </ul>
                                        </td>
                                        <td><code>[wpavatar_username before="欢迎，" after="！"]</code></td>
                                    </tr>
                                </tbody>
                            </table>
                            <h4><?php _e('在菜单项中使用', 'wpavatar'); ?></h4>
                            <p><?php _e('您可以在菜单项的标题中使用特殊标记来插入头像和用户名：', 'wpavatar'); ?></p>
                            <ul>
                                <li><code>{wpavatar}</code> - <?php _e('插入当前用户的头像（圆形，32像素）', 'wpavatar'); ?></li>
                                <li><code>{wpavatar_username}</code> - <?php _e('插入当前用户的用户名', 'wpavatar'); ?></li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
        </div>
                </div>
        <style>
        .wpavatar-preview-container {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .wpavatar-preview-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin: 15px 0;
        }

        .wpavatar-preview-item {
            text-align: center;
        }

        .wpavatar-preview-item h4 {
            margin-bottom: 10px;
            font-weight: normal;
        }

        .avatar-circle {
            border-radius: 50% !important;
            overflow: hidden;
        }

        .avatar-rounded {
            border-radius: 8px !important;
            overflow: hidden;
        }

        .default-avatar-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .default-avatar-options label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 8px;
            border: 2px solid transparent;
            border-radius: 5px;
            width: 80px;
            text-align: center;
        }
        .default-avatar-options label:hover {
            background-color: #f0f0f1;
        }
        .default-avatar-options input[type="radio"] {
            margin-bottom: 8px;
        }
        .default-avatar-options input[type="radio"]:checked + img {
            border: 2px solid #2271b1;
            border-radius: 50%;
        }
        .avatar-option-name {
            margin-top: 5px;
            font-size: 12px;
            line-height: 1.3;
        }
        </style>
        <?php
    }
}

class Network {
    public static function init() {
        if (is_network_admin()) {
            add_action('network_admin_menu', [__CLASS__, 'add_network_menu']);
            add_action('network_admin_edit_wpavatar_network', [__CLASS__, 'save_network_settings']);
        }
    }

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

    public static function render_network_page() {
        ?>
        <div class="wrap wpavatar-settings">
            <h1><?php esc_html_e('WPAvatar网络设置', 'wpavatar'); ?></h1>

            <div id="wpavatar-network-status" class="notice" style="display:none; margin-top: 10px;"></div>

            <div class="wpavatar-card">
                <h2><?php _e('网络范围设置', 'wpavatar'); ?></h2>
                <p class="wpavatar-section-desc"><?php _e('配置多站点网络的WPAvatar设置。', 'wpavatar'); ?></p>

                <form method="post" action="edit.php?action=wpavatar_network" id="wpavatar-network-form">
                    <?php wp_nonce_field('wpavatar_network_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('启用网络范围设置', 'wpavatar'); ?></th>
                            <td>
                                <label class="wpavatar-switch">
                                    <input type="checkbox" name="wpavatar_network_enabled" value="1" <?php checked(get_site_option('wpavatar_network_enabled', 1)); ?>>
                                    <span class="wpavatar-slider"></span>
                                    <span class="wpavatar-switch-label"><?php _e('在所有站点启用WPAvatar', 'wpavatar'); ?></span>
                                </label>
                                <p class="description"><?php _e('启用后，WPAvatar将在网络中的所有站点上生效', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('默认CDN类型', 'wpavatar'); ?></th>
                            <td>
                                <select name="wpavatar_network_cdn_type" class="wpavatar-select">
                                    <option value="cravatar_route" <?php selected(get_site_option('wpavatar_network_cdn_type', 'cravatar_route'), 'cravatar_route'); ?>><?php _e('Cravatar线路', 'wpavatar'); ?></option>
                                    <option value="third_party" <?php selected(get_site_option('wpavatar_network_cdn_type', 'cravatar_route'), 'third_party'); ?>><?php _e('第三方镜像', 'wpavatar'); ?></option>
                                    <option value="custom" <?php selected(get_site_option('wpavatar_network_cdn_type', 'cravatar_route'), 'custom'); ?>><?php _e('自定义CDN', 'wpavatar'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('默认Cravatar线路', 'wpavatar'); ?></th>
                            <td>
                                <select name="wpavatar_network_cravatar_route" class="wpavatar-select">
                                    <option value="cravatar.com" <?php selected(get_site_option('wpavatar_network_cravatar_route', 'cravatar.com'), 'cravatar.com'); ?>><?php _e('默认线路 (cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="cn.cravatar.com" <?php selected(get_site_option('wpavatar_network_cravatar_route', 'cravatar.com'), 'cn.cravatar.com'); ?>><?php _e('中国大陆 (cn.cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="hk.cravatar.com" <?php selected(get_site_option('wpavatar_network_cravatar_route', 'cravatar.com'), 'hk.cravatar.com'); ?>><?php _e('香港 (hk.cravatar.com)', 'wpavatar'); ?></option>
                                    <option value="en.cravatar.com" <?php selected(get_site_option('wpavatar_network_cravatar_route', 'cravatar.com'), 'en.cravatar.com'); ?>><?php _e('国际 (en.cravatar.com)', 'wpavatar'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('哈希方法', 'wpavatar'); ?></th>
                            <td>
                                <p class="description" style="color: #d63638;"><?php _e('注意：使用Cravatar服务时，哈希方法将强制使用MD5', 'wpavatar'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div class="wpavatar-submit-wrapper">
                        <button type="submit" class="button button-primary"><?php _e('保存设置', 'wpavatar'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public static function save_network_settings() {
        check_admin_referer('wpavatar_network_settings');

        update_site_option('wpavatar_network_enabled', isset($_POST['wpavatar_network_enabled']) ? 1 : 0);

        if (isset($_POST['wpavatar_network_cdn_type'])) {
            update_site_option('wpavatar_network_cdn_type', sanitize_text_field($_POST['wpavatar_network_cdn_type']));
        }

        if (isset($_POST['wpavatar_network_cravatar_route'])) {
            update_site_option('wpavatar_network_cravatar_route', sanitize_text_field($_POST['wpavatar_network_cravatar_route']));
        }

        wp_redirect(add_query_arg(['page' => 'wpavatar-network', 'updated' => 'true'], network_admin_url('settings.php')));
        exit;
    }
}
