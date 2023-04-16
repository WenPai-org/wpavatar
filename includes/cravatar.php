<?php
/*
Name: Cravatar
URI: https://cravatar.cn
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/



if (!function_exists('get_cravatar_url')) {
    /**
     * Replace Gravatar with Cravatar
     *
     * Cravatar is the perfect alternative to Gravatar in China, you can update your avatar at https://cravatar.cn.
     */
    function get_cravatar_url($url)
    {
        $sources = array(
            'www.gravatar.com',
            '0.gravatar.com',
            '1.gravatar.com',
            '2.gravatar.com',
            'secure.gravatar.com',
            'cn.gravatar.com',
            'gravatar.com',
        );
        return str_replace($sources, 'cravatar.cn', $url);
    }

    if (get_option('wpavatar_enable_cravatar', '0') == '1') {
        add_filter('um_user_avatar_url_filter', 'get_cravatar_url', 1);
        add_filter('bp_gravatar_url', 'get_cravatar_url', 1);
        add_filter('get_avatar_url', 'get_cravatar_url', 1);
    }
}

if (!function_exists('set_defaults_for_cravatar')) {
    /**
     * Replace default avatar with Cravatar in Discussion Settings
     */
    function set_defaults_for_cravatar($avatar_defaults)
    {
        $avatar_defaults['gravatar_default'] = __('Cravatar Logo', 'wpavatar');
        return $avatar_defaults;
    }

    if (get_option('wpavatar_enable_cravatar', '0') == '1') {
        add_filter('avatar_defaults', 'set_defaults_for_cravatar', 1);
    }
}

if (!function_exists('set_user_profile_picture_for_cravatar')) {
    /**
     * Replace avatar upload link in user profile
     */
    function set_user_profile_picture_for_cravatar()
    {
        return '<a href="https://cravatar.cn" target="_blank" rel="noopener">'.__('You can update your profile picture at Cravatar.cn', 'wpavatar').'</a>';
    }

    if (get_option('wpavatar_enable_cravatar', '0') == '1') {
        add_filter('user_profile_picture_description', 'set_user_profile_picture_for_cravatar', 1);
    }
}

add_action('admin_menu', 'wpavatar_create_settings_page');

function wpavatar_create_settings_page()
{
  add_options_page(
      __('Avatar Settings', 'wpavatar'),
      __('Avatar', 'wpavatar'),
      'manage_options',
      'wpavatar-settings',
      'wpavatar_render_settings_page'
  );

}

function wpavatar_render_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2><?php _e( 'WordPress Avatar Settings', 'wpavatar' ); ?></h2>
        <p><?php _e( 'Cravatar is the perfect alternative to Gravatar in China, you can update your avatar at', 'wpavatar' ); ?> <a href="https://cravatar.cn" target="_blank" rel="noopener">Cravatar.cn</a>.</p>
        <form method="post" action="options.php">
            <?php settings_fields('wpavatar_settings'); ?>
            <?php do_settings_sections('wpavatar-settings'); ?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><?php _e('Enable Cravatar', 'wpavatar'); ?></th>
                    <td>
                        <?php $enable_cravatar = get_option('wpavatar_enable_cravatar', '1'); ?>
                        <label><input type="radio" name="wpavatar_enable_cravatar" value="1" <?php checked($enable_cravatar, '1'); ?>> <?php _e('Yes', 'wpavatar'); ?></label>
                        <label><input type="radio" name="wpavatar_enable_cravatar" value="0" <?php checked($enable_cravatar, '0'); ?>> <?php _e('No', 'wpavatar'); ?></label>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(__('Save Changes', 'wpavatar')); ?>
        </form>
    </div>
    <?php
  }

add_action('admin_init', 'wpavatar_register_settings');

function wpavatar_register_settings()
{
    register_setting('wpavatar_settings', 'wpavatar_enable_cravatar', 'absint');
}
