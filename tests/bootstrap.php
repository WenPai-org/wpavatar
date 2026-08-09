<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wpavatar-tests/wp-content' );
define( 'WPAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/uploads/cravatar' );
define( 'WPAVATAR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPAVATAR_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpavatar/' );
define( 'WPAVATAR_VERSION', '1.9.5' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );
$GLOBALS['wpavatar_test_options'] = array();
$GLOBALS['wpavatar_settings_errors'] = array();
function __( $text, $domain = null ) { return $text; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['wpavatar_settings_errors'][] = compact( 'setting', 'code', 'message' ); }
function wpavatar_get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['wpavatar_test_options'] ) ? $GLOBALS['wpavatar_test_options'][ $name ] : $default; }
function is_multisite() { return false; }
function get_current_blog_id() { return 1; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function content_url( $path = '' ) { return 'https://example.test/wp-content' . $path; }
function wp_kses_post( $value ) { return (string) $value; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { return array_merge( $pairs, (array) $atts ); }
function get_current_user_id() { return 0; }
function wp_get_current_user() { return (object) array( 'ID' => 0, 'display_name' => '' ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function get_user_by( $field, $value ) { return $GLOBALS['wpavatar_test_user'] ?? false; }
function get_option( $name, $default = false ) { return $default; }
function get_comments( $args = array() ) {
 return isset( $GLOBALS['wpavatar_get_comments'] ) ? ( $GLOBALS['wpavatar_get_comments'] )( $args ) : array();
}
function get_users( $args = array() ) { return array(); }
function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '', $args = array() ) {
 return '[avatar:' . (string) $id_or_email . ']';
}
require_once dirname( __DIR__ ) . '/includes/functions.php';
require_once dirname( __DIR__ ) . '/includes/core.php';
require_once dirname( __DIR__ ) . '/includes/marketing.php';
