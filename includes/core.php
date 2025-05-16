<?php
namespace WPAvatar;

class Core {
    public static function init() {
    }
}

class Cravatar {
    public static $gravatar_domains = [
        'www.gravatar.com',
        '0.gravatar.com',
        '1.gravatar.com',
        '2.gravatar.com',
        'secure.gravatar.com',
        's.gravatar.com',
        'cn.gravatar.com',
        'gravatar.com'
    ];

    public static function init() {
        if (get_option('wpavatar_enable_cravatar', true)) {
            add_filter('get_avatar_url', [__CLASS__, 'replace_avatar_url'], 1);
            add_filter('um_user_avatar_url_filter', [__CLASS__, 'replace_avatar_url'], 1);
            add_filter('bp_gravatar_url', [__CLASS__, 'replace_avatar_url'], 1);
            add_filter('user_profile_picture_description', [__CLASS__, 'modify_profile_picture_description'], 1);
            add_filter('pre_get_avatar_data', [__CLASS__, 'pre_get_avatar_data'], 9, 2);
            add_filter('get_avatar', [__CLASS__, 'add_seo_alt'], 10, 5);
        }
    }

    public static function get_local_avatars() {
        $avatars = [];

        if (file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images/default-avatar.png')) {
            $avatars['default'] = [
                'url' => WPAVATAR_PLUGIN_URL . 'assets/images/default-avatar.png',
                'name' => __('默认头像', 'wpavatar')
            ];
        }

        if (file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images/wapuu.png')) {
            $avatars['wapuu'] = [
                'url' => WPAVATAR_PLUGIN_URL . 'assets/images/wapuu.png',
                'name' => __('文派Wapuu', 'wpavatar')
            ];
        }

        if (file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images/wapuu-china.png')) {
            $avatars['wapuu-china'] = [
                'url' => WPAVATAR_PLUGIN_URL . 'assets/images/wapuu-china.png',
                'name' => __('中国Wapuu', 'wpavatar')
            ];
        }

        if (file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images/cravatar-default.png')) {
            $avatars['cravatar'] = [
                'url' => WPAVATAR_PLUGIN_URL . 'assets/images/cravatar-default.png',
                'name' => __('Cravatar默认', 'wpavatar')
            ];
        }

        if (file_exists(WPAVATAR_PLUGIN_DIR . 'assets/images/cravatar-logo.png')) {
            $avatars['cravatar-logo'] = [
                'url' => WPAVATAR_PLUGIN_URL . 'assets/images/cravatar-logo.png',
                'name' => __('Cravatar标志', 'wpavatar')
            ];
        }

        return $avatars;
    }

    public static function modify_profile_picture_description() {
        return '<a href="https://cravatar.com" target="_blank" rel="noopener">' . __('您可以在初认头像修改您的资料图片', 'wpavatar') . '</a>';
    }

    public static function pre_get_avatar_data($args, $id_or_email) {
        if (is_null($args)) {
            $args = [];
        }

        $email = '';
        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', (int)$id_or_email);
            if ($user) {
                $email = $user->user_email;
            }
        } elseif (is_string($id_or_email)) {
            if (is_email($id_or_email)) {
                $email = $id_or_email;
            }
        } elseif (is_object($id_or_email)) {
            if (isset($id_or_email->user_id) && $id_or_email->user_id) {
                $user = get_user_by('id', $id_or_email->user_id);
                if ($user) {
                    $email = $user->user_email;
                }
            } elseif (isset($id_or_email->comment_author_email)) {
                $email = $id_or_email->comment_author_email;
            } elseif (isset($id_or_email->user_email)) {
                $email = $id_or_email->user_email;
            }
        }

        if (empty($email)) {
            return $args;
        }

        // 确定使用的哈希方法
        $cdn_type = get_option('wpavatar_cdn_type', 'cravatar_route');
        $use_md5 = true;

        // Cravatar只支持MD5，如果使用Cravatar相关服务，强制使用MD5
        if ($cdn_type !== 'cravatar_route') {
            // 检查第三方镜像或自定义CDN是否与Cravatar相关
            $third_party_mirror = get_option('wpavatar_third_party_mirror', '');
            $custom_cdn = get_option('wpavatar_custom_cdn', '');
            $is_cravatar_related = (
                strpos(strtolower($third_party_mirror), 'cravatar') !== false ||
                strpos(strtolower($custom_cdn), 'cravatar') !== false
            );

            if (!$is_cravatar_related) {
                // 非Cravatar服务使用用户设置的哈希方法
                $hash_method = get_option('wpavatar_hash_method', 'sha256');
                $use_md5 = ($hash_method === 'md5');
            }
        }

        // 检查WordPress版本，决定是否支持SHA256
        $wp_version = get_bloginfo('version');
        $use_sha256_support = version_compare($wp_version, '6.8', '>=');

        // 设置哈希方法
        if (!$use_md5 && $use_sha256_support) {
            $args['hash_method'] = 'sha256';
            $args['hash'] = 'sha256';
        } else {
            $args['hash_method'] = 'md5';

            if (isset($args['hash']) && $args['hash'] === 'sha256') {
                unset($args['hash']);
            }
        }

        // 计算邮箱地址的哈希值
        if (!isset($args['wpavatar_email_hash'])) {
            if (!$use_md5) {
                $args['wpavatar_email_hash'] = hash('sha256', strtolower(trim($email)));
            } else {
                $args['wpavatar_email_hash'] = md5(strtolower(trim($email)));
            }
        }

        // 设置超时属性
        $timeout = get_option('wpavatar_timeout', 5);
        $args['extra_attr'] = isset($args['extra_attr']) ? $args['extra_attr'] : '';
        $args['extra_attr'] .= ' data-timeout="' . esc_attr($timeout) . '"';

        return $args;
    }
    
    public static function replace_avatar_url($url) {
        // 遍历所有Gravatar域名，替换为Cravatar相关域名
        foreach (self::$gravatar_domains as $domain) {
            if (strpos($url, $domain) !== false) {
                $cdn_type = get_option('wpavatar_cdn_type', 'cravatar_route');
                $cdn_domain = '';

                if ($cdn_type === 'cravatar_route') {
                    $cdn_domain = get_option('wpavatar_cravatar_route', 'cravatar.com');
                } elseif ($cdn_type === 'third_party') {
                    $cdn_domain = get_option('wpavatar_third_party_mirror', 'weavatar.com');
                } elseif ($cdn_type === 'custom') {
                    $custom_cdn = get_option('wpavatar_custom_cdn', '');
                    if (!empty($custom_cdn)) {
                        $cdn_domain = $custom_cdn;
                    } else {
                        $cdn_domain = 'cravatar.com';
                    }
                }

                return str_replace($domain, $cdn_domain, $url);
            }
        }

        return $url;
    }

    public static function add_seo_alt($avatar, $id_or_email, $size, $default, $alt) {
        // 添加SEO友好的alt属性
        $seo_alt = get_option('wpavatar_seo_alt');
        if (!empty($seo_alt)) {
            $user = false;
            if (is_numeric($id_or_email)) {
                $user = get_user_by('id', $id_or_email);
            } elseif (is_object($id_or_email)) {
                if (isset($id_or_email->user_id) && $id_or_email->user_id) {
                    $user = get_user_by('id', $id_or_email->user_id);
                } elseif (isset($id_or_email->comment_author_email)) {
                    $user = (object) [
                        'display_name' => $id_or_email->comment_author
                    ];
                }
            } elseif (is_string($id_or_email) && is_email($id_or_email)) {
                $user = get_user_by('email', $id_or_email);
            }

            $alt_text = $user ? sprintf($seo_alt, $user->display_name) : __('用户头像', 'wpavatar');
            $avatar = preg_replace('/alt=([\'"])[^\'"]*\\1/', "alt='$alt_text'", $avatar);
        }

        // 添加头像加载失败的备用显示
        if (get_option('wpavatar_fallback_mode', 1)) {
            $fallback_type = get_option('wpavatar_fallback_avatar', 'default');
            $local_avatars = self::get_local_avatars();

            if (isset($local_avatars[$fallback_type])) {
                $fallback_url = $local_avatars[$fallback_type]['url'];

                if (strpos($avatar, 'onerror') === false) {
                    $avatar = str_replace('<img ', '<img onerror="this.onerror=null;this.src=\'' . esc_url($fallback_url) . '\';" ', $avatar);
                }
            }
        }

        return $avatar;
    }
}

class Cache {
    public static function init() {
        add_action('init', [__CLASS__, 'setup_cache_dir']);
        add_action('init', [__CLASS__, 'schedule_purge']);

        if (get_option('wpavatar_enable_cache', true)) {
            add_filter('get_avatar_url', [__CLASS__, 'prepare_cache_url'], 99, 2);
            add_filter('get_avatar', [__CLASS__, 'serve_cached_avatar'], 20, 5);
        }

        add_action('wpavatar_purge_cache', [__CLASS__, 'purge_expired']);

        add_action('comment_post', [__CLASS__, 'cache_comment_avatar'], 10, 2);
        add_action('profile_update', [__CLASS__, 'cache_user_avatar'], 10, 1);
    }

    public static function setup_cache_dir() {
        $dir = get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);

        $dir = rtrim($dir, '/\\') . '/';

        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_dir_error',
                    __('无法创建缓存目录，请检查权限', 'wpavatar'),
                    'error'
                );
                return false;
            }
        }

        if (!is_writable($dir)) {
            add_settings_error(
                'wpavatar_cache',
                'cache_dir_writable',
                __('缓存目录不可写，请检查权限', 'wpavatar'),
                'error'
            );
            return false;
        }

        $index_file = $dir . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden.');
        }

        $htaccess_file = $dir . '.htaccess';
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

        return true;
    }

    public static function prepare_cache_url($url, $id_or_email) {
        if (!get_option('wpavatar_enable_cache', true)) {
            return $url;
        }

        if (strpos($url, 'cache-key=') === false) {
            $hash = self::get_avatar_hash($id_or_email);
            if ($hash) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'cache-key=' . $hash;
            }
        }

        return $url;
    }

    public static function serve_cached_avatar($avatar, $id_or_email, $size, $default, $alt) {
        if (!get_option('wpavatar_enable_cache', true)) {
            return $avatar;
        }

        preg_match('/src=([\'"])([^\'"]+)\\1/', $avatar, $matches);
        if (empty($matches[2])) {
            return $avatar;
        }

        $url = $matches[2];

        $is_avatar_url = false;

        $cdn_type = get_option('wpavatar_cdn_type', 'cravatar_route');
        if ($cdn_type === 'cravatar_route') {
            $cdn_domain = get_option('wpavatar_cravatar_route', 'cravatar.com');
            if (strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        } elseif ($cdn_type === 'third_party') {
            $cdn_domain = get_option('wpavatar_third_party_mirror', 'weavatar.com');
            if (strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        } elseif ($cdn_type === 'custom') {
            $cdn_domain = get_option('wpavatar_custom_cdn', '');
            if (!empty($cdn_domain) && strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        }

        // 检查是否为Gravatar URL
        if (!$is_avatar_url) {
            foreach (Cravatar::$gravatar_domains as $domain) {
                if (strpos($url, $domain) !== false) {
                    $is_avatar_url = true;
                    break;
                }
            }
        }

        if (!$is_avatar_url) {
            return $avatar;
        }

        $hash = self::get_avatar_hash($id_or_email);
        $cache_file = self::get_cache_path($hash, $size);

        $cache_expire = get_option('wpavatar_cache_expire', 7) * DAY_IN_SECONDS;
        if (file_exists($cache_file) && filemtime($cache_file) > (time() - $cache_expire)) {
            $cached_url = content_url(str_replace(WP_CONTENT_DIR, '', $cache_file));
            return str_replace($url, esc_url($cached_url), $avatar);
        }

        if (self::cache_remote_avatar($url, $cache_file)) {
            $cached_url = content_url(str_replace(WP_CONTENT_DIR, '', $cache_file));
            return str_replace($url, esc_url($cached_url), $avatar);
        }

        return $avatar;
    }

    public static function get_avatar_hash($id_or_email) {
        $email = '';

        if (is_object($id_or_email)) {
            if (isset($id_or_email->comment_author_email)) {
                $email = $id_or_email->comment_author_email;
            } elseif (isset($id_or_email->user_email)) {
                $email = $id_or_email->user_email;
            }
        } elseif (is_numeric($id_or_email)) {
            $user = get_user_by('id', $id_or_email);
            $email = $user ? $user->user_email : '';
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $email = $id_or_email;
        }

        if (empty($email)) {
            return '';
        }

        $cdn_type = get_option('wpavatar_cdn_type', 'cravatar_route');
        $use_md5 = true;

        // 确定使用的哈希方法
        if ($cdn_type !== 'cravatar_route' &&
            strpos(get_option('wpavatar_third_party_mirror', ''), 'cravatar') === false &&
            strpos(get_option('wpavatar_custom_cdn', ''), 'cravatar') === false) {
            $hash_method = get_option('wpavatar_hash_method', 'md5');
            $use_md5 = ($hash_method === 'md5');
        }

        if (!$use_md5) {
            return hash('sha256', strtolower(trim($email)));
        } else {
            return md5(strtolower(trim($email)));
        }
    }

    public static function get_cache_path($hash, $size) {
        $dir = get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        $dir = trailingslashit($dir);
        wp_mkdir_p($dir);
        return $dir . "{$hash}-{$size}.jpg";
    }

    public static function cache_remote_avatar($url, $dest) {
        $dir = dirname($dest);
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log('WPAvatar: Failed to create cache directory: ' . $dir);
                return false;
            }
        }

        if (!is_writable($dir)) {
            error_log('WPAvatar: Cache directory is not writable: ' . $dir);
            return false;
        }

        $timeout = get_option('wpavatar_timeout', 5);

        $response = wp_remote_get($url, [
            'timeout' => $timeout,
            'user-agent' => 'WPAvatar/' . WPAVATAR_VERSION . '; ' . home_url()
        ]);

        if (is_wp_error($response)) {
            error_log('WPAvatar: Error fetching avatar: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('WPAvatar: Bad response code: ' . $response_code);
            return false;
        }

        $image = wp_remote_retrieve_body($response);
        if (empty($image)) {
            error_log('WPAvatar: Empty image received');
            return false;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (strpos($content_type, 'image/') !== 0) {
            error_log('WPAvatar: Invalid content type: ' . $content_type);
            return false;
        }

        $result = file_put_contents($dest, $image);
        if ($result === false) {
            error_log('WPAvatar: Failed to write cache file: ' . $dest);
            return false;
        }

        @chmod($dest, 0644);

        return true;
    }

    public static function cache_comment_avatar($comment_id, $comment_approved) {
        if ($comment_approved !== 1 || !get_option('wpavatar_enable_cache', true)) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment || empty($comment->comment_author_email)) {
            return;
        }

        $email = $comment->comment_author_email;
        $size = get_option('wpavatar_shortcode_size', 96);

        $avatar_url = get_avatar_url($email, ['size' => $size]);

        $hash = self::get_avatar_hash($email);
        if (empty($hash)) {
            return;
        }

        $cache_file = self::get_cache_path($hash, $size);

        self::cache_remote_avatar($avatar_url, $cache_file);

        // 缓存2x尺寸的头像，用于高分辨率显示器
        $retina_url = get_avatar_url($email, ['size' => $size * 2]);
        $retina_cache_file = self::get_cache_path($hash, $size * 2);
        self::cache_remote_avatar($retina_url, $retina_cache_file);
    }

    public static function cache_user_avatar($user_id) {
        if (!get_option('wpavatar_enable_cache', true)) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;
        $size = get_option('wpavatar_shortcode_size', 96);

        $avatar_url = get_avatar_url($email, ['size' => $size]);

        $hash = self::get_avatar_hash($email);
        if (empty($hash)) {
            return;
        }

        $cache_file = self::get_cache_path($hash, $size);

        self::cache_remote_avatar($avatar_url, $cache_file);

        // 缓存2x尺寸的头像，用于高分辨率显示器
        $retina_url = get_avatar_url($email, ['size' => $size * 2]);
        $retina_cache_file = self::get_cache_path($hash, $size * 2);
        self::cache_remote_avatar($retina_url, $retina_cache_file);
    }

    public static function purge_expired() {
        $dir = get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        if (!file_exists($dir) || !is_dir($dir)) {
            return;
        }

        $files = glob(trailingslashit($dir) . '*.jpg');
        if (!$files) {
            return;
        }

        $expire_days = get_option('wpavatar_cache_expire', 7);
        $expire_time = time() - ($expire_days * DAY_IN_SECONDS);

        foreach ($files as $file) {
            if (filemtime($file) < $expire_time) {
                @unlink($file);
            }
        }
    }

    public static function schedule_purge() {
        if (!wp_next_scheduled('wpavatar_purge_cache')) {
            wp_schedule_event(time(), 'daily', 'wpavatar_purge_cache');
        }
    }

    public static function check_cache_status() {
        $dir = get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        $stats = [
            'path' => $dir,
            'exists' => file_exists($dir) && is_dir($dir),
            'writable' => is_writable($dir),
            'file_count' => 0,
            'size' => 0
        ];

        if ($stats['exists']) {
            $files = glob(trailingslashit($dir) . '*.jpg');
            $stats['file_count'] = count($files ?: []);
            foreach ($files ?: [] as $file) {
                $stats['size'] += filesize($file);
            }
            $stats['size'] = size_format($stats['size']);
        }

        ob_start();
        ?>
        <div class="cache-stats">
            <p><?php printf(__('缓存目录: %s', 'wpavatar'), esc_html($stats['path'])); ?></p>
            <p><?php printf(__('目录存在: %s', 'wpavatar'), $stats['exists'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
            <p><?php printf(__('目录可写: %s', 'wpavatar'), $stats['writable'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
            <p><?php printf(__('缓存文件数: %d', 'wpavatar'), $stats['file_count']); ?></p>
            <p><?php printf(__('缓存总大小: %s', 'wpavatar'), $stats['size']); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}

class Shortcode {
    public static function init() {
        add_shortcode('wpavatar', [__CLASS__, 'render_avatar']);
        add_shortcode('wpavatar_username', [__CLASS__, 'render_username']);
        add_filter('walker_nav_menu_start_el', [__CLASS__, 'menu_item_replace'], 10, 4);
    }

    public static function render_avatar($atts) {
        $default_size = get_option('wpavatar_shortcode_size', 96);
        $default_class = get_option('wpavatar_shortcode_class', 'wpavatar');
        $default_shape = get_option('wpavatar_shortcode_shape', 'square');

        $atts = shortcode_atts([
            'size'    => $default_size,
            'user_id' => get_current_user_id(),
            'class'   => $default_class,
            'email'   => '',
            'shape'   => $default_shape,
            'title'   => ''
        ], $atts);

        if (empty($atts['user_id']) && empty($atts['email'])) {
            $atts['user_id'] = get_current_user_id();
        }

        $classes = [$atts['class']];

        if ($atts['shape'] === 'circle') {
            $classes[] = 'avatar-circle';
            $style = 'style="border-radius: 50%; overflow: hidden;"';
        } elseif ($atts['shape'] === 'rounded') {
            $classes[] = 'avatar-rounded';
            $style = 'style="border-radius: 8px; overflow: hidden;"';
        } else {
            $classes[] = 'avatar-square';
            $style = '';
        }

        $avatar_args = [
            'class' => implode(' ', $classes),
            'size' => intval($atts['size']),
            'extra_attr' => $style
        ];

        if (!empty($atts['title'])) {
            $avatar_args['extra_attr'] .= ' title="' . esc_attr($atts['title']) . '"';
        }

        ob_start();
        if (!empty($atts['email'])) {
            echo get_avatar($atts['email'], intval($atts['size']), 'default', '', $avatar_args);
        } else {
            echo get_avatar($atts['user_id'], intval($atts['size']), 'default', '', $avatar_args);
        }
        return ob_get_clean();
    }

    public static function render_username($atts) {
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'before' => '',
            'after' => ''
        ], $atts);

        if (!empty($atts['user_id'])) {
            $user = get_user_by('id', $atts['user_id']);
        } else {
            $user = wp_get_current_user();
        }

        $username = $user && $user->display_name ? $user->display_name : __('匿名用户', 'wpavatar');
        return $atts['before'] . $username . $atts['after'];
    }

    public static function menu_item_replace($item_output, $item, $depth, $args) {
        if (strpos($item_output, '{wpavatar}') !== false) {
            $item_output = str_replace('{wpavatar}', do_shortcode('[wpavatar shape="circle" size="32"]'), $item_output);
        }
        if (strpos($item_output, '{wpavatar_username}') !== false) {
            $item_output = str_replace('{wpavatar_username}', do_shortcode('[wpavatar_username]'), $item_output);
        }
        return $item_output;
    }

    public static function generate_preview($user_id = 0, $shape = 'square', $size = 96) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $atts = [
            'user_id' => $user_id,
            'shape' => $shape,
            'size' => $size,
            'class' => 'wpavatar-preview'
        ];

        return self::render_avatar($atts);
    }
}
