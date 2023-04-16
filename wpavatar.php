<?php
/**
 * Plugin Name: WPAvatar
 * Plugin URI: https://wpavatar.com/download
 * Description: Must-have for WordPress sites in China, showing your ICP license.
 * Author: WPfanyi
 * Author URI: https://wpfanyi.com/
 * Text Domain: wpavatar
 * Domain Path: /languages
 * Version: 1.0
 * Network: True
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * WP Avatar is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WP Avatar is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

 if ( ! function_exists( 'get_cravatar_url' ) ) {
     /**
      * 替换 Gravatar 头像为 Cravatar 头像
      *
      * Cravatar 是 Gravatar 在中国的完美替代方案，你可以在 https://cravatar.cn 更新你的头像
      */
     function get_cravatar_url( $url ) {
         $sources = array(
             'www.gravatar.com',
             '0.gravatar.com',
             '1.gravatar.com',
             '2.gravatar.com',
             'secure.gravatar.com',
             'cn.gravatar.com',
             'gravatar.com',
         );
         return str_replace( $sources, 'cravatar.cn', $url );
     }
     add_filter( 'um_user_avatar_url_filter', 'get_cravatar_url', 1 );
     add_filter( 'bp_gravatar_url', 'get_cravatar_url', 1 );
     add_filter( 'get_avatar_url', 'get_cravatar_url', 1 );
 }
 if ( ! function_exists( 'set_defaults_for_cravatar' ) ) {
     /**
      * 替换 WordPress 讨论设置中的默认头像
      */
     function set_defaults_for_cravatar( $avatar_defaults ) {
         $avatar_defaults['gravatar_default'] = 'Cravatar 标志';
         return $avatar_defaults;
     }
     add_filter( 'avatar_defaults', 'set_defaults_for_cravatar', 1 );
 }
 if ( ! function_exists( 'set_user_profile_picture_for_cravatar' ) ) {
     /**
      * 替换个人资料卡中的头像上传地址
      */
     function set_user_profile_picture_for_cravatar() {
         return '<a href="https://cravatar.cn" target="_blank">您可以在 Cravatar 修改您的资料图片</a>';
     }
     add_filter( 'user_profile_picture_description', 'set_user_profile_picture_for_cravatar', 1 );
 }
