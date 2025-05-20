<?php
namespace WPAvatar;

/**
 * Marketing Class
 *
 * 处理营销组件相关的功能和管理界面
 */
class Marketing {
    /**
     * 初始化营销组件功能
     */
    public static function init() {
        // 注册短代码
        add_shortcode('wpavatar_latest_commenters', [__CLASS__, 'render_latest_commenters']);
        add_shortcode('wpavatar_latest_users', [__CLASS__, 'render_latest_users']);
        add_shortcode('wpavatar_random_users', [__CLASS__, 'render_random_users']);
        add_shortcode('wpavatar_author', [__CLASS__, 'render_author_avatar']);

        // 添加头像样式
        add_action('wp_head', [__CLASS__, 'add_frontend_styles']);

        // 如果是管理员界面，添加管理界面功能
        if (is_admin()) {
            add_action('admin_init', [__CLASS__, 'register_settings']);
        }
    }

    /**
     * 注册设置字段
     */
    public static function register_settings() {
        register_setting('wpavatar_marketing', 'wpavatar_commenters_count', ['type' => 'integer', 'default' => 15]);
        register_setting('wpavatar_marketing', 'wpavatar_commenters_size', ['type' => 'integer', 'default' => 45]);
        register_setting('wpavatar_marketing', 'wpavatar_users_count', ['type' => 'integer', 'default' => 15]);
        register_setting('wpavatar_marketing', 'wpavatar_users_size', ['type' => 'integer', 'default' => 40]);
    }

    /**
     * 添加前端样式
     */
    public static function add_frontend_styles() {
        echo '
        <style>
        /* 最近评论者头像样式 */
        .wpavatar-latest-commenters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }
        .wpavatar-latest-commenters .comment-avatar {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .wpavatar-latest-commenters .comment-avatar a {
            display: inline-block;
            overflow: hidden;
            margin-left: -15px;
        }
        .wpavatar-latest-commenters .comment-avatar:first-child a {
            margin-left: 0;
        }
        .wpavatar-latest-commenters .comment-avatar img {
            border-radius: 50%;
            width: auto;
            display: inline-block;
            overflow: hidden;
            box-shadow: 0 1px 0 1px rgba(0, 0, 0, .1);
            border: 3px solid #FFF;
            position: relative;
        }
        .wpavatar-latest-commenters .comment-avatar img:hover {
            border: 3px solid #000;
            z-index: 10;
        }

        /* 用户头像列表样式 */
        .wpavatar-latest-users,
        .wpavatar-random-users {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }
        .wpavatar-latest-user,
        .wpavatar-random-user {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 80px;
        }
        .wpavatar-latest-user img,
        .wpavatar-random-user img {
            border-radius: 50%;
            margin-bottom: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .wpavatar-latest-user-name,
        .wpavatar-random-user-name {
            font-size: 12px;
            line-height: 1.3;
            margin-top: 5px;
            word-break: break-word;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        </style>
        ';
    }

    /**
     * 渲染最近评论者头像
     */
    public static function render_latest_commenters($atts) {
        $default_count = wpavatar_get_option('wpavatar_commenters_count', 15);
        $default_size = wpavatar_get_option('wpavatar_commenters_size', 45);

        $atts = shortcode_atts([
            'size' => $default_size,
            'number' => $default_count,
        ], $atts, 'wpavatar_latest_commenters');

        // 获取全部文章的最新评论，限制为指定的评论者数量
        $comments = get_comments(array(
            'number' => $atts['number'] * 3, // 获取更多评论以确保有足够不重复的评论者
            'status' => 'approve',
        ));

        if (empty($comments)) {
            return '<div class="wpavatar-latest-commenters">暂无评论用户头像</div>';
        }

        // 初始化一个数组用于存储已显示的评论者
        $seen_emails = array();
        $output = '<div class="wpavatar-latest-commenters">';

        foreach ($comments as $comment) {
            // 检查评论者的电子邮件地址
            $comment_email = $comment->comment_author_email;

            // 如果该评论者已经显示过，则跳过
            if (in_array($comment_email, $seen_emails) || empty($comment_email)) {
                continue;
            }

            // 获取评论的链接
            $comment_link = get_comment_link($comment);

            // 创建带链接的头像
            $avatar_with_link = '<a href="' . esc_url($comment_link) . '" title="' . esc_attr($comment->comment_author) . '">';
            $avatar_with_link .= get_avatar($comment, $atts['size'], '', $comment->comment_author);
            $avatar_with_link .= '</a>';

            // 添加头像到输出
            $output .= '<div class="comment-avatar">' . $avatar_with_link . '</div>';

            // 记录该评论者，防止重复显示
            $seen_emails[] = $comment_email;

            // 如果已经达到了显示数量限制，跳出循环
            if (count($seen_emails) >= $atts['number']) {
                break;
            }
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * 渲染最新用户头像
     */
    public static function render_latest_users($atts) {
        $default_count = wpavatar_get_option('wpavatar_users_count', 15);
        $default_size = wpavatar_get_option('wpavatar_users_size', 40);

        $atts = shortcode_atts([
            'number' => $default_count,
            'size' => $default_size
        ], $atts);

        $users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => $atts['number']
        ]);

        if (empty($users)) {
            return '<div class="wpavatar-latest-users">暂无用户</div>';
        }

        $output = '<div class="wpavatar-latest-users">';
        foreach ($users as $user) {
            $output .= '<div class="wpavatar-latest-user">';
            $output .= get_avatar($user->ID, $atts['size'], '', $user->display_name);
            $output .= '<div class="wpavatar-latest-user-name">' . esc_html($user->display_name) . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * 渲染随机用户头像
     */
    public static function render_random_users($atts) {
        $default_count = wpavatar_get_option('wpavatar_users_count', 15);
        $default_size = wpavatar_get_option('wpavatar_users_size', 40);

        $atts = shortcode_atts([
            'number' => $default_count,
            'size' => $default_size
        ], $atts);

        $args = [
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => intval($atts['number']) * 2 // 获取更多用户以便随机选择
        ];
        $users = get_users($args);

        if (empty($users)) {
            return '<div class="wpavatar-random-users">暂无用户</div>';
        }

        // 打乱用户数组以随机化顺序
        shuffle($users);

        // 限制显示的用户数量
        $users = array_slice($users, 0, intval($atts['number']));

        $output = '<div class="wpavatar-random-users">';
        foreach ($users as $user) {
            $output .= '<div class="wpavatar-random-user">';
            $output .= get_avatar($user->ID, $atts['size'], '', $user->display_name);
            $output .= '<div class="wpavatar-random-user-name">' . esc_html($user->display_name) . '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * 渲染文章作者头像
     */
    public static function render_author_avatar($atts) {
        $atts = shortcode_atts([
            'size' => '96',
        ], $atts);

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        $author_id = get_post_field('post_author', $post_id);
        if (!$author_id) {
            return '';
        }

        $avatar = get_avatar($author_id, $atts['size']);

        return $avatar;
    }

    /**
     * 生成预览头像
     */
    public static function generate_preview($shape = 'square', $size = 96, $count = 6) {
        global $wpdb;

        // 获取最近的评论者
        $sql = "SELECT DISTINCT comment_author_email, comment_author
                FROM {$wpdb->comments}
                WHERE comment_approved = '1'
                AND comment_author_email != ''
                ORDER BY comment_date DESC
                LIMIT %d";

        $commenters = $wpdb->get_results($wpdb->prepare($sql, $count));

        $output = '<div class="wpavatar-preview-grid">';

        if (!empty($commenters)) {
            $output .= '<div class="preview-section">';
            $output .= '<h4>最近评论者预览</h4>';
            $output .= '<div class="wpavatar-preview-commenters">';

            foreach ($commenters as $commenter) {
                if (empty($commenter->comment_author_email)) continue;

                $output .= '<div class="preview-item">';
                $avatar_args = [
                    'class' => 'preview-avatar',
                    'size' => $size
                ];

                if ($shape === 'circle') {
                    $avatar_args['extra_attr'] = 'style="border-radius: 50%; overflow: hidden;"';
                } elseif ($shape === 'rounded') {
                    $avatar_args['extra_attr'] = 'style="border-radius: 8px; overflow: hidden;"';
                }

                $output .= get_avatar($commenter->comment_author_email, $size, '', $commenter->comment_author, $avatar_args);
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        // 获取最新用户
        $recent_users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => $count
        ]);

        if (!empty($recent_users)) {
            $output .= '<div class="preview-section">';
            $output .= '<h4>最新用户预览</h4>';
            $output .= '<div class="wpavatar-preview-users">';

            foreach ($recent_users as $user) {
                $output .= '<div class="preview-item">';
                $avatar_args = [
                    'class' => 'preview-avatar',
                    'size' => $size
                ];

                if ($shape === 'circle') {
                    $avatar_args['extra_attr'] = 'style="border-radius: 50%; overflow: hidden;"';
                } elseif ($shape === 'rounded') {
                    $avatar_args['extra_attr'] = 'style="border-radius: 8px; overflow: hidden;"';
                }

                $output .= get_avatar($user->ID, $size, '', $user->display_name, $avatar_args);
                $output .= '<span class="preview-name">' . esc_html($user->display_name) . '</span>';
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * 渲染管理界面
     */
    public static function render_admin_page() {
        // 使用wpavatar_get_option而不是get_option获取设置
        $commenters_count = wpavatar_get_option('wpavatar_commenters_count', 15);
        $commenters_size = wpavatar_get_option('wpavatar_commenters_size', 45);
        $users_count = wpavatar_get_option('wpavatar_users_count', 15);
        $users_size = wpavatar_get_option('wpavatar_users_size', 40);

        // 检查多站点网络控制
        $is_option_disabled = false;
        if (is_multisite() && get_site_option('wpavatar_network_enabled', 1)) {
            $network_controlled_options = get_site_option('wpavatar_network_controlled_options', array());
            if (!is_array($network_controlled_options)) {
                $network_controlled_options = explode(',', $network_controlled_options);
            }

            // 检查营销组件选项是否由网络控制
            $is_commenters_count_disabled = in_array('wpavatar_commenters_count', $network_controlled_options) ? 'disabled' : '';
            $is_commenters_size_disabled = in_array('wpavatar_commenters_size', $network_controlled_options) ? 'disabled' : '';
            $is_users_count_disabled = in_array('wpavatar_users_count', $network_controlled_options) ? 'disabled' : '';
            $is_users_size_disabled = in_array('wpavatar_users_size', $network_controlled_options) ? 'disabled' : '';

            // 检查是否强制使用网络设置
            if (get_site_option('wpavatar_network_enforce', 0)) {
                $is_commenters_count_disabled = 'disabled';
                $is_commenters_size_disabled = 'disabled';
                $is_users_count_disabled = 'disabled';
                $is_users_size_disabled = 'disabled';
            }
        } else {
            $is_commenters_count_disabled = '';
            $is_commenters_size_disabled = '';
            $is_users_count_disabled = '';
            $is_users_size_disabled = '';
        }

        ?>
        <form method="post" action="options.php" id="wpavatar-marketing-form">
            <?php settings_fields('wpavatar_marketing'); ?>

            <table class="form-table">
                <tr>
                    <th colspan="2"><h3><?php _e('最近评论者设置', 'wpavatar'); ?></h3></th>
                </tr>
                <tr>
                    <th><?php _e('显示数量', 'wpavatar'); ?></th>
                    <td>
                        <input type="number" name="wpavatar_commenters_count" value="<?php echo esc_attr($commenters_count); ?>" min="1" max="50" class="small-text wpavatar-input" <?php echo $is_commenters_count_disabled; ?>>
                        <p class="description"><?php _e('显示的最近评论者数量', 'wpavatar'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('头像大小', 'wpavatar'); ?></th>
                    <td>
                        <input type="number" name="wpavatar_commenters_size" value="<?php echo esc_attr($commenters_size); ?>" min="20" max="150" class="small-text wpavatar-input" <?php echo $is_commenters_size_disabled; ?>>
                        <p class="description"><?php _e('评论者头像大小（像素）', 'wpavatar'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php _e('用户头像设置', 'wpavatar'); ?></h3></th>
                </tr>
                <tr>
                    <th><?php _e('显示数量', 'wpavatar'); ?></th>
                    <td>
                        <input type="number" name="wpavatar_users_count" value="<?php echo esc_attr($users_count); ?>" min="1" max="50" class="small-text wpavatar-input" <?php echo $is_users_count_disabled; ?>>
                        <p class="description"><?php _e('显示的用户数量', 'wpavatar'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('头像大小', 'wpavatar'); ?></th>
                    <td>
                        <input type="number" name="wpavatar_users_size" value="<?php echo esc_attr($users_size); ?>" min="20" max="150" class="small-text wpavatar-input" <?php echo $is_users_size_disabled; ?>>
                        <p class="description"><?php _e('用户头像大小（像素）', 'wpavatar'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="wpavatar-preview-container">
                <h3><?php _e('预览效果', 'wpavatar'); ?></h3>
                <?php echo self::generate_preview('circle', 45, 5); ?>
                <p class="description"><?php _e('预览显示使用圆形头像样式', 'wpavatar'); ?></p>
            </div>

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
                <h4><?php _e('使用场景', 'wpavatar'); ?></h4>
                <p><?php _e('这些简码非常适合用于：', 'wpavatar'); ?></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php _e('侧边栏挂件：展示活跃评论者和用户', 'wpavatar'); ?></li>
                    <li><?php _e('文章底部：增强社区感并鼓励互动', 'wpavatar'); ?></li>
                    <li><?php _e('关于页面：展示您的社区成员', 'wpavatar'); ?></li>
                    <li><?php _e('会员页面：显示最新加入的会员', 'wpavatar'); ?></li>
                </ul>
                <p><strong><?php _e('提示：', 'wpavatar'); ?></strong> <?php _e('您可以将这些简码与其他WordPress挂件组合使用，创建更丰富的社区展示。', 'wpavatar'); ?></p>
            </div>
        </form>

        <style>
        .wpavatar-preview-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 15px;
        }
        .preview-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
        }
        .preview-section h4 {
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .wpavatar-preview-commenters {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .wpavatar-preview-commenters .preview-item {
            margin-right: -15px;
        }
        .wpavatar-preview-users {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .wpavatar-preview-users .preview-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .preview-avatar {
            margin-bottom: 5px;
        }
        .preview-name {
            font-size: 12px;
            margin-top: 5px;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        </style>
        <?php
    }
}
