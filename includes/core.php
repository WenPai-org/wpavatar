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
        if (wpavatar_get_option('wpavatar_enable_cravatar', true)) {
            // 使用高优先级过滤器来预处理头像数据
            add_filter('pre_get_avatar_data', [__CLASS__, 'pre_get_avatar_data'], 1, 2);

            // 直接过滤avatar_url，无论是否已经通过pre_get_avatar_data处理
            add_filter('get_avatar_url', [__CLASS__, 'get_avatar_url'], 999, 2);

            // 其他过滤器
            add_filter('um_user_avatar_url_filter', [__CLASS__, 'replace_avatar_url'], 1);
            add_filter('bp_gravatar_url', [__CLASS__, 'replace_avatar_url'], 1);
            add_filter('user_profile_picture_description', [__CLASS__, 'modify_profile_picture_description'], 1);
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

    /**
     * 预处理头像数据，根据配置决定是否强制使用MD5
     */
    public static function pre_get_avatar_data($args, $id_or_email) {
        if (is_null($args)) {
            $args = [];
        }

        $email = self::get_email_from_id_or_email($id_or_email);

        if (empty($email)) {
            return $args;
        }

        // 检查是否为 Cravatar 线路，只有 Cravatar 线路才强制使用 MD5
        $cdn_type = wpavatar_get_option('wpavatar_cdn_type', 'cravatar_route');
        if ($cdn_type === 'cravatar_route') {
            // Cravatar 线路强制使用 MD5
            $args['hash_method'] = 'md5';

            // 移除SHA256散列标记（如果存在）
            if (isset($args['hash']) && $args['hash'] === 'sha256') {
                unset($args['hash']);
            }
        } else {
            // 尊重用户选择的哈希方法
            $hash_method = wpavatar_get_option('wpavatar_hash_method', 'md5');
            $args['hash_method'] = $hash_method;
        }

        // 确保我们有email_hash供后续使用，根据选择的哈希方法计算
        if ($args['hash_method'] === 'sha256' && function_exists('hash')) {
            $args['wpavatar_email_hash'] = hash('sha256', strtolower(trim($email)));
        } else {
            $args['wpavatar_email_hash'] = md5(strtolower(trim($email)));
        }

        // 添加超时属性
        $timeout = wpavatar_get_option('wpavatar_timeout', 5);
        $args['extra_attr'] = isset($args['extra_attr']) ? $args['extra_attr'] : '';
        $args['extra_attr'] .= ' data-timeout="' . esc_attr($timeout) . '"';

        return $args;
    }

    /**
     * 直接替换头像URL，根据配置决定是否强制使用Cravatar和MD5哈希
     */
    public static function get_avatar_url($url, $id_or_email) {
        // 如果地址已经是我们支持的域名，则直接返回
        $cdn_type = wpavatar_get_option('wpavatar_cdn_type', 'cravatar_route');
        $cdn_domain = '';

        if ($cdn_type === 'cravatar_route') {
            $cdn_domain = wpavatar_get_option('wpavatar_cravatar_route', 'cravatar.com');
        } elseif ($cdn_type === 'third_party') {
            $cdn_domain = wpavatar_get_option('wpavatar_third_party_mirror', 'weavatar.com');
        } elseif ($cdn_type === 'custom') {
            $cdn_domain = wpavatar_get_option('wpavatar_custom_cdn', '');
            if (empty($cdn_domain)) {
                $cdn_domain = 'cravatar.com';
            }
        }

        // 检查URL是否已经使用了支持的域名
        if (strpos($url, $cdn_domain) !== false) {
            return $url;
        }

        // 获取用户设置的哈希方法
        $hash_method = wpavatar_get_option('wpavatar_hash_method', 'md5');

        // 仅当使用Cravatar线路或者自定义CDN包含"cravatar"时，强制使用MD5
        $force_md5 = ($cdn_type === 'cravatar_route' ||
                     ($cdn_type === 'custom' && strpos(strtolower($cdn_domain), 'cravatar') !== false));

        if ($force_md5) {
            // 对于Cravatar，删除hash=sha256参数
            $url = str_replace(['?hash=sha256', '&hash=sha256'], ['', ''], $url);
        }

        // 从URL提取邮箱哈希
        $hash = '';

        // 根据URL中是否包含SHA256或MD5哈希进行处理
        if (preg_match('/\/avatar\/([a-f0-9]{64})/', $url, $matches)) {
            // SHA256哈希
            if ($force_md5) {
                // 如果强制使用MD5，需要重新计算哈希
                $email = self::get_email_from_id_or_email($id_or_email);
                if (!empty($email)) {
                    $hash = md5(strtolower(trim($email)));
                } else {
                    // 如果无法获取邮箱，则使用默认头像
                    return self::replace_avatar_url($url);
                }
            } else {
                // 尊重SHA256
                $hash = $matches[1];
            }
        } elseif (preg_match('/\/avatar\/([a-f0-9]{32})/', $url, $matches)) {
            // MD5哈希
            $hash = $matches[1];
        }

        if (empty($hash)) {
            return self::replace_avatar_url($url);
        }

        // 构建新的头像URL
        $new_url = 'https://' . $cdn_domain . '/avatar/' . $hash;

        // 保留原始URL中的参数
        $query_params = [];
        $parsed_url = parse_url($url);
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
        }

        // 如果使用SHA256并且不是强制使用MD5的情况，添加哈希参数
        if ($hash_method === 'sha256' && !$force_md5 && strlen($hash) === 64) {
            $query_params['hash'] = 'sha256';
        }

        // 构建完整URL
        if (!empty($query_params)) {
            $new_url .= '?' . http_build_query($query_params);
        }

        return $new_url;
    }

    /**
     * 从ID或邮箱获取邮箱地址
     */
    public static function get_email_from_id_or_email($id_or_email) {
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
            if ($id_or_email instanceof \WP_User) {
                $email = $id_or_email->user_email;
            } elseif ($id_or_email instanceof \WP_Post) {
                $user = get_user_by('id', (int)$id_or_email->post_author);
                if ($user) {
                    $email = $user->user_email;
                }
            } elseif ($id_or_email instanceof \WP_Comment) {
                if (!empty($id_or_email->user_id)) {
                    $user = get_user_by('id', (int)$id_or_email->user_id);
                    if ($user) {
                        $email = $user->user_email;
                    }
                } elseif (!empty($id_or_email->comment_author_email)) {
                    $email = $id_or_email->comment_author_email;
                }
            } elseif (isset($id_or_email->comment_ID)) {
                $comment = get_comment($id_or_email->comment_ID);
                if ($comment) {
                    if ($comment->user_id) {
                        $user = get_user_by('id', (int)$comment->user_id);
                        if ($user) {
                            $email = $user->user_email;
                        }
                    } else {
                        $email = $comment->comment_author_email;
                    }
                }
            }
        }

        return $email;
    }

    public static function replace_avatar_url($url) {
        // 移除hash=sha256参数，因为Cravatar不支持
        if (strpos($url, 'hash=sha256') !== false) {
            $url = str_replace(['?hash=sha256', '&hash=sha256'], ['', ''], $url);
        }

        // 更换Gravatar域名为Cravatar域名
        foreach (self::$gravatar_domains as $domain) {
            if (strpos($url, $domain) !== false) {
                $cdn_type = wpavatar_get_option('wpavatar_cdn_type', 'cravatar_route');
                $cdn_domain = '';

                if ($cdn_type === 'cravatar_route') {
                    $cdn_domain = wpavatar_get_option('wpavatar_cravatar_route', 'cravatar.com');
                } elseif ($cdn_type === 'third_party') {
                    $cdn_domain = wpavatar_get_option('wpavatar_third_party_mirror', 'weavatar.com');
                } elseif ($cdn_type === 'custom') {
                    $custom_cdn = wpavatar_get_option('wpavatar_custom_cdn', '');
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
        $seo_alt = wpavatar_get_option('wpavatar_seo_alt');
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
        if (wpavatar_get_option('wpavatar_fallback_mode', 1)) {
            $fallback_type = wpavatar_get_option('wpavatar_fallback_avatar', 'default');
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

        if (wpavatar_get_option('wpavatar_enable_cache', true)) {
            add_filter('get_avatar_url', [__CLASS__, 'prepare_cache_url'], 99, 2);
            add_filter('get_avatar', [__CLASS__, 'serve_cached_avatar'], 20, 5);
        }

        add_action('wpavatar_purge_cache', [__CLASS__, 'purge_expired']);

        add_action('comment_post', [__CLASS__, 'cache_comment_avatar'], 10, 2);
        add_action('profile_update', [__CLASS__, 'cache_user_avatar'], 10, 1);
    }

    public static function setup_cache_dir() {
        $base_dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        $base_dir = rtrim($base_dir, '/\\') . '/';

        if (!file_exists($base_dir)) {
            if (!wp_mkdir_p($base_dir)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_dir_error',
                    __('无法创建缓存目录，请检查权限', 'wpavatar'),
                    'error'
                );
                return false;
            }
        }

        if (!is_writable($base_dir)) {
            add_settings_error(
                'wpavatar_cache',
                'cache_dir_writable',
                __('缓存目录不可写，请检查权限', 'wpavatar'),
                'error'
            );
            return false;
        }

        // Create index.php in base dir
        $index_file = $base_dir . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden.');
        }

        // Create .htaccess in base dir
        $htaccess_file = $base_dir . '.htaccess';
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

        // If multisite, create site-specific directory
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            $site_dir = $base_dir . 'site-' . $blog_id . '/';

            if (!file_exists($site_dir)) {
                if (!wp_mkdir_p($site_dir)) {
                    add_settings_error(
                        'wpavatar_cache',
                        'cache_dir_error',
                        __('无法创建站点缓存目录，请检查权限', 'wpavatar'),
                        'error'
                    );
                    return false;
                }
            }

            if (!is_writable($site_dir)) {
                add_settings_error(
                    'wpavatar_cache',
                    'cache_dir_writable',
                    __('站点缓存目录不可写，请检查权限', 'wpavatar'),
                    'error'
                );
                return false;
            }

            // Create index.php in site dir
            $site_index_file = $site_dir . 'index.php';
            if (!file_exists($site_index_file)) {
                @file_put_contents($site_index_file, '<?php // Silence is golden.');
            }
        }

        return true;
    }

    public static function prepare_cache_url($url, $id_or_email) {
        if (!wpavatar_get_option('wpavatar_enable_cache', true)) {
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
        if (!wpavatar_get_option('wpavatar_enable_cache', true)) {
            return $avatar;
        }

        preg_match('/src=([\'"])([^\'"]+)\\1/', $avatar, $matches);
        if (empty($matches[2])) {
            return $avatar;
        }

        $url = $matches[2];

        $is_avatar_url = false;

        $cdn_type = wpavatar_get_option('wpavatar_cdn_type', 'cravatar_route');
        if ($cdn_type === 'cravatar_route') {
            $cdn_domain = wpavatar_get_option('wpavatar_cravatar_route', 'cravatar.com');
            if (strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        } elseif ($cdn_type === 'third_party') {
            $cdn_domain = wpavatar_get_option('wpavatar_third_party_mirror', 'weavatar.com');
            if (strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        } elseif ($cdn_type === 'custom') {
            $cdn_domain = wpavatar_get_option('wpavatar_custom_cdn', '');
            if (!empty($cdn_domain) && strpos($url, $cdn_domain) !== false) {
                $is_avatar_url = true;
            }
        }

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

        $cache_expire = wpavatar_get_option('wpavatar_cache_expire', 7) * DAY_IN_SECONDS;
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
        $email = Cravatar::get_email_from_id_or_email($id_or_email);

        if (empty($email)) {
            return '';
        }

        // 获取用户设置的哈希方法
        $cdn_type = wpavatar_get_option('wpavatar_cdn_type', 'cravatar_route');
        $hash_method = wpavatar_get_option('wpavatar_hash_method', 'md5');

        // 检查是否需要强制使用MD5（针对Cravatar服务）
        $force_md5 = false;
        if ($cdn_type === 'cravatar_route') {
            $force_md5 = true;
        } elseif ($cdn_type === 'custom') {
            $custom_cdn = wpavatar_get_option('wpavatar_custom_cdn', '');
            if (strpos(strtolower($custom_cdn), 'cravatar') !== false) {
                $force_md5 = true;
            }
        }

        // 根据设置和条件选择哈希方法
        if ($force_md5 || $hash_method === 'md5') {
            return md5(strtolower(trim($email)));
        } else {
            // 使用SHA256
            if (function_exists('hash')) {
                return hash('sha256', strtolower(trim($email)));
            } else {
                // 如果不支持hash函数，回退到MD5
                return md5(strtolower(trim($email)));
            }
        }
    }

    public static function get_cache_path($hash, $size) {
        $dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        $dir = trailingslashit($dir);

        // Add site-specific directory if multisite
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            $dir = $dir . 'site-' . $blog_id . '/';
        }

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

        $timeout = wpavatar_get_option('wpavatar_timeout', 5);

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
        if ($comment_approved !== 1 || !wpavatar_get_option('wpavatar_enable_cache', true)) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment || empty($comment->comment_author_email)) {
            return;
        }

        $email = $comment->comment_author_email;
        $size = wpavatar_get_option('wpavatar_shortcode_size', 96);

        $avatar_url = get_avatar_url($email, ['size' => $size]);

        $hash = self::get_avatar_hash($email);
        if (empty($hash)) {
            return;
        }

        $cache_file = self::get_cache_path($hash, $size);

        self::cache_remote_avatar($avatar_url, $cache_file);

        $retina_url = get_avatar_url($email, ['size' => $size * 2]);
        $retina_cache_file = self::get_cache_path($hash, $size * 2);
        self::cache_remote_avatar($retina_url, $retina_cache_file);
    }

    public static function cache_user_avatar($user_id) {
        if (!wpavatar_get_option('wpavatar_enable_cache', true)) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;
        $size = wpavatar_get_option('wpavatar_shortcode_size', 96);

        $avatar_url = get_avatar_url($email, ['size' => $size]);

        $hash = self::get_avatar_hash($email);
        if (empty($hash)) {
            return;
        }

        $cache_file = self::get_cache_path($hash, $size);

        self::cache_remote_avatar($avatar_url, $cache_file);

        $retina_url = get_avatar_url($email, ['size' => $size * 2]);
        $retina_cache_file = self::get_cache_path($hash, $size * 2);
        self::cache_remote_avatar($retina_url, $retina_cache_file);
    }

    public static function purge_expired() {
        $base_dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);
        if (!file_exists($base_dir) || !is_dir($base_dir)) {
            return;
        }

        $expire_days = wpavatar_get_option('wpavatar_cache_expire', 7);
        $expire_time = time() - ($expire_days * DAY_IN_SECONDS);

        // If multisite, purge site-specific directory
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            $site_dir = trailingslashit($base_dir) . 'site-' . $blog_id . '/';

            if (file_exists($site_dir) && is_dir($site_dir)) {
                $files = glob($site_dir . '*.jpg');
                if ($files) {
                    foreach ($files as $file) {
                        if (filemtime($file) < $expire_time) {
                            @unlink($file);
                        }
                    }
                }
            }
        } else {
            // For non-multisite, purge all files in the base directory
            $files = glob(trailingslashit($base_dir) . '*.jpg');
            if ($files) {
                foreach ($files as $file) {
                    if (filemtime($file) < $expire_time) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    public static function schedule_purge() {
        if (!wp_next_scheduled('wpavatar_purge_cache')) {
            wp_schedule_event(time(), 'daily', 'wpavatar_purge_cache');
        }
    }

    public static function check_cache_status() {
        $base_dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);

        // For multisite, check site-specific directory
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            $site_dir = trailingslashit($base_dir) . 'site-' . $blog_id;

            $stats = [
                'path' => $site_dir,
                'exists' => file_exists($site_dir) && is_dir($site_dir),
                'writable' => is_writable($site_dir),
                'file_count' => 0,
                                'size' => 0
                            ];

                            if ($stats['exists']) {
                                $files = glob(trailingslashit($site_dir) . '*.jpg');
                                $stats['file_count'] = count($files ?: []);
                                foreach ($files ?: [] as $file) {
                                    $stats['size'] += filesize($file);
                                }
                                $stats['size'] = size_format($stats['size']);
                            }
                        } else {
                            // For single site
                            $stats = [
                                'path' => $base_dir,
                                'exists' => file_exists($base_dir) && is_dir($base_dir),
                                'writable' => is_writable($base_dir),
                                'file_count' => 0,
                                'size' => 0
                            ];

                            if ($stats['exists']) {
                                $files = glob(trailingslashit($base_dir) . '*.jpg');
                                $stats['file_count'] = count($files ?: []);
                                foreach ($files ?: [] as $file) {
                                    $stats['size'] += filesize($file);
                                }
                                $stats['size'] = size_format($stats['size']);
                            }
                        }

                        ob_start();
                        ?>
                        <div class="cache-stats">
                            <p><?php printf(esc_html__('缓存目录: %s', 'wpavatar'), esc_html($stats['path'])); ?></p>
                            <p><?php printf(esc_html__('目录存在: %s', 'wpavatar'), $stats['exists'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
                            <p><?php printf(esc_html__('目录可写: %s', 'wpavatar'), $stats['writable'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
                            <p><?php printf(esc_html__('缓存文件数: %d', 'wpavatar'), $stats['file_count']); ?></p>
                            <p><?php printf(esc_html__('缓存总大小: %s', 'wpavatar'), $stats['size']); ?></p>
                        </div>
                        <?php
                        return ob_get_clean();
                    }

                    public static function check_all_cache_status() {
                        $base_dir = wpavatar_get_option('wpavatar_cache_path', WPAVATAR_CACHE_DIR);

                        $global_stats = [
                            'path' => $base_dir,
                            'exists' => file_exists($base_dir) && is_dir($base_dir),
                            'writable' => is_writable($base_dir),
                            'site_count' => 0,
                            'total_files' => 0,
                            'total_size' => 0
                        ];

                        $site_stats = [];

                        if ($global_stats['exists']) {
                            // Find all site directories
                            $site_dirs = glob(trailingslashit($base_dir) . 'site-*', GLOB_ONLYDIR);
                            $global_stats['site_count'] = count($site_dirs ?: []);

                            // Check each site directory
                            foreach ($site_dirs ?: [] as $site_dir) {
                                $blog_id = intval(str_replace(trailingslashit($base_dir) . 'site-', '', $site_dir));
                                if ($blog_id > 0) {
                                    $files = glob($site_dir . '/*.jpg');
                                    $file_count = count($files ?: []);
                                    $global_stats['total_files'] += $file_count;

                                    $size = 0;
                                    foreach ($files ?: [] as $file) {
                                        $size += filesize($file);
                                        $global_stats['total_size'] += filesize($file);
                                    }

                                    // Try to get blog name
                                    $blog_name = '';
                                    if (function_exists('get_blog_details')) {
                                        $blog_details = get_blog_details($blog_id);
                                        if ($blog_details) {
                                            $blog_name = $blog_details->blogname;
                                        }
                                    }

                                    $site_stats[] = [
                                        'id' => $blog_id,
                                        'name' => $blog_name ?: sprintf(esc_html__('站点 #%d', 'wpavatar'), $blog_id),
                                        'files' => $file_count,
                                        'size' => size_format($size)
                                    ];
                                }
                            }

                            // Also check for legacy non-site specific files
                            $legacy_files = glob(trailingslashit($base_dir) . '*.jpg');
                            $legacy_count = count($legacy_files ?: []);
                            if ($legacy_count > 0) {
                                $global_stats['total_files'] += $legacy_count;

                                $legacy_size = 0;
                                foreach ($legacy_files ?: [] as $file) {
                                    $legacy_size += filesize($file);
                                    $global_stats['total_size'] += filesize($file);
                                }

                                $site_stats[] = [
                                    'id' => 0,
                                    'name' => __('旧版缓存文件（非站点特定）', 'wpavatar'),
                                    'files' => $legacy_count,
                                    'size' => size_format($legacy_size)
                                ];
                            }
                        }

                        // Sort sites by file count (descending)
                        usort($site_stats, function($a, $b) {
                            return $b['files'] - $a['files'];
                        });

                        ob_start();
                        ?>
                        <div class="network-cache-stats">
                            <h4><?php esc_html_e('全局缓存统计', 'wpavatar'); ?></h4>
                            <p><?php printf(esc_html__('缓存根目录: %s', 'wpavatar'), esc_html($global_stats['path'])); ?></p>
                            <p><?php printf(esc_html__('目录存在: %s', 'wpavatar'), $global_stats['exists'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
                            <p><?php printf(esc_html__('目录可写: %s', 'wpavatar'), $global_stats['writable'] ? __('是', 'wpavatar') : __('否', 'wpavatar')); ?></p>
                            <p><?php printf(esc_html__('站点缓存目录数: %d', 'wpavatar'), $global_stats['site_count']); ?></p>
                            <p><?php printf(esc_html__('总缓存文件数: %d', 'wpavatar'), $global_stats['total_files']); ?></p>
                            <p><?php printf(esc_html__('总缓存大小: %s', 'wpavatar'), size_format($global_stats['total_size'])); ?></p>

                            <?php if (!empty($site_stats)): ?>
                                <h4><?php esc_html_e('站点缓存详情', 'wpavatar'); ?></h4>
                                <table class="widefat striped" style="margin-top: 10px;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('站点', 'wpavatar'); ?></th>
                                            <th><?php esc_html_e('文件数', 'wpavatar'); ?></th>
                                            <th><?php esc_html_e('大小', 'wpavatar'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($site_stats as $stat): ?>
                                            <tr>
                                                <td><?php echo esc_html($stat['name']); ?></td>
                                                <td><?php echo esc_html($stat['files']); ?></td>
                                                <td><?php echo esc_html($stat['size']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
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
                        $default_size = wpavatar_get_option('wpavatar_shortcode_size', 96);
                        $default_class = wpavatar_get_option('wpavatar_shortcode_class', 'wpavatar');
                        $default_shape = wpavatar_get_option('wpavatar_shortcode_shape', 'square');

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

                        // 检查用户是否已登录且有显示名称，如果没有则返回空字符串
                        if (!$user || !$user->ID || empty($user->display_name)) {
                            return '';
                        }

                        return $atts['before'] . $user->display_name . $atts['after'];
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
