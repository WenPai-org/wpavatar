<?php
/**
 * Plugin Name: WPAvatar
 * Plugin URI: https://wpavatar.com/download
 * Description: Replace Gravatar with Cravatar, a perfect replacement of Gravatar in China.
 * Author: WPfanyi
 * Author URI: https://wpfanyi.com/
 * Text Domain: wpavatar
 * Domain Path: /languages
 * Version: 1.0
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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


require_once( plugin_dir_path( __FILE__ ) . 'includes/cravatar.php' );


register_activation_hook( __FILE__, 'wpavatar_activate' );

function wpavatar_activate() {
  update_option( 'wpavatar_enable_cravatar', '1' );
}


// Load translation
add_action( 'init', 'wpavatar_load_textdomain' );
function wpavatar_load_textdomain() {
	load_plugin_textdomain( 'wpavatar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
