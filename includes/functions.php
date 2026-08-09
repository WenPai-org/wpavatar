<?php
/**
 * Shared validation helpers for WPAvatar settings and cache operations.
 *
 * @package WPAvatar
 */

defined( 'ABSPATH' ) || exit;


function wpavatar_add_settings_error( $setting, $code, $message ) {
	if ( function_exists( 'add_settings_error' ) ) {
		add_settings_error( $setting, $code, $message );
	}
}

function wpavatar_sanitize_option_value( $name, $value ) {
	$callbacks = array(
		'wpavatar_enable_cravatar'    => 'rest_sanitize_boolean',
		'wpavatar_cdn_type'           => 'wpavatar_sanitize_cdn_type',
		'wpavatar_cravatar_route'     => 'wpavatar_sanitize_cravatar_route',
		'wpavatar_third_party_mirror' => 'wpavatar_sanitize_third_party_mirror',
		'wpavatar_custom_cdn'         => 'wpavatar_sanitize_custom_cdn',
		'wpavatar_timeout'            => 'wpavatar_sanitize_timeout',
		'wpavatar_enable_cache'       => 'rest_sanitize_boolean',
		'wpavatar_cache_path'         => 'wpavatar_sanitize_cache_path',
		'wpavatar_cache_expire'       => 'wpavatar_sanitize_cache_expire',
		'wpavatar_seo_alt'            => 'sanitize_text_field',
		'wpavatar_fallback_mode'      => 'rest_sanitize_boolean',
		'wpavatar_fallback_avatar'    => 'wpavatar_sanitize_fallback_avatar',
		'wpavatar_shortcode_size'     => 'wpavatar_sanitize_avatar_size',
		'wpavatar_shortcode_class'    => 'wpavatar_sanitize_shortcode_class',
		'wpavatar_shortcode_shape'    => 'wpavatar_sanitize_shortcode_shape',
		'wpavatar_commenters_count'   => 'wpavatar_sanitize_marketing_count',
		'wpavatar_commenters_size'    => 'wpavatar_sanitize_marketing_size',
		'wpavatar_users_count'        => 'wpavatar_sanitize_marketing_count',
		'wpavatar_users_size'         => 'wpavatar_sanitize_marketing_size',
	);

	return isset( $callbacks[ $name ] ) ? call_user_func( $callbacks[ $name ], $value ) : null;
}
function wpavatar_sanitize_cdn_type( $value ) {
	$value = sanitize_key( $value );
	return in_array( $value, array( 'cravatar_route', 'third_party', 'custom' ), true ) ? $value : 'cravatar_route';
}

function wpavatar_sanitize_cravatar_route( $value ) {
	$value   = strtolower( trim( sanitize_text_field( $value ) ) );
	$allowed = array( 'cravatar.com', 'cravatar.cn', 'cn.cravatar.com', 'hk.cravatar.com', 'en.cravatar.com' );
	return in_array( $value, $allowed, true ) ? $value : 'cravatar.com';
}

function wpavatar_sanitize_third_party_mirror( $value ) {
	$value = strtolower( trim( sanitize_text_field( $value ) ) );
	return in_array( $value, array( 'weavatar.com', 'libravatar.org' ), true ) ? $value : 'weavatar.com';
}

function wpavatar_sanitize_custom_cdn( $value ) {
	$value = strtolower( rtrim( trim( sanitize_text_field( $value ) ), '.' ) );
	if ( '' === $value ) {
		return '';
	}

	$parts = wp_parse_url( 'https://' . $value );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || $parts['host'] !== $value || isset( $parts['port'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['path'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
		wpavatar_add_settings_error( 'wpavatar_basic', 'invalid_custom_cdn', __( '自定义 CDN 必须是单独的 HTTPS 主机名，不能包含协议、端口或路径。', 'wpavatar' ) );
		return '';
	}

	if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			wpavatar_add_settings_error( 'wpavatar_basic', 'unsafe_custom_cdn', __( '自定义 CDN 不能使用内网或保留 IP 地址。', 'wpavatar' ) );
			return '';
		}
		return $value;
	}

	if ( ! preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value ) ) {
		wpavatar_add_settings_error( 'wpavatar_basic', 'invalid_custom_cdn', __( '自定义 CDN 主机名无效。', 'wpavatar' ) );
		return '';
	}

	return $value;
}

function wpavatar_sanitize_timeout( $value ) {
	return max( 1, min( 30, absint( $value ) ) );
}

function wpavatar_sanitize_cache_expire( $value ) {
	return max( 1, min( 30, absint( $value ) ) );
}

function wpavatar_sanitize_avatar_size( $value ) {
	return max( 16, min( 512, absint( $value ) ) );
}

function wpavatar_sanitize_marketing_count( $value ) {
	return max( 1, min( 50, absint( $value ) ) );
}

function wpavatar_sanitize_marketing_size( $value ) {
	return max( 20, min( 150, absint( $value ) ) );
}

function wpavatar_sanitize_shortcode_shape( $value ) {
	$value = sanitize_key( $value );
	return in_array( $value, array( 'square', 'rounded', 'circle' ), true ) ? $value : 'square';
}

function wpavatar_sanitize_shortcode_class( $value ) {
	$classes = preg_split( '/\\s+/', trim( sanitize_text_field( $value ) ) );
	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
	return $classes ? implode( ' ', array_unique( $classes ) ) : 'wpavatar';
}

function wpavatar_sanitize_fallback_avatar( $value ) {
	$value = sanitize_key( $value );
	return array_key_exists( $value, \WPAvatar\Cravatar::get_local_avatars() ) ? $value : 'default';
}

function wpavatar_sanitize_cache_path( $value ) {
	$value = sanitize_text_field( $value );
	if ( '' === trim( $value ) ) {
		return trailingslashit( WPAVATAR_CACHE_DIR );
	}

	if ( ! preg_match( '~^(?:/|\\\\|[a-zA-Z]:)~', $value ) ) {
		$value = trailingslashit( WP_CONTENT_DIR ) . ltrim( $value, '/\\\\' );
	}

	$value       = wp_normalize_path( untrailingslashit( $value ) );
	$content_dir = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
	if ( false !== strpos( $value, '/../' ) || $value === $content_dir . '/..' || 0 !== strpos( $value . '/', $content_dir . '/' ) ) {
		wpavatar_add_settings_error( 'wpavatar_cache', 'unsafe_cache_path', __( '缓存目录必须位于 wp-content 目录内。', 'wpavatar' ) );
		return trailingslashit( WPAVATAR_CACHE_DIR );
	}

	return trailingslashit( $value );
}

function wpavatar_get_cache_base_dir() {
	return wpavatar_sanitize_cache_path( wpavatar_get_option( 'wpavatar_cache_path', WPAVATAR_CACHE_DIR ) );
}

function wpavatar_get_cdn_domain() {
	$type = wpavatar_sanitize_cdn_type( wpavatar_get_option( 'wpavatar_cdn_type', 'cravatar_route' ) );
	if ( 'third_party' === $type ) {
		return wpavatar_sanitize_third_party_mirror( wpavatar_get_option( 'wpavatar_third_party_mirror', 'weavatar.com' ) );
	}
	if ( 'custom' === $type ) {
		$custom = wpavatar_sanitize_custom_cdn( wpavatar_get_option( 'wpavatar_custom_cdn', '' ) );
		return $custom ? $custom : 'cravatar.com';
	}
	return wpavatar_sanitize_cravatar_route( wpavatar_get_option( 'wpavatar_cravatar_route', 'cravatar.com' ) );
}

function wpavatar_is_allowed_avatar_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
		return false;
	}

	$host    = strtolower( rtrim( $parts['host'], '.' ) );
	$allowed = array_merge( \WPAvatar\Cravatar::$gravatar_domains, array( wpavatar_get_cdn_domain() ) );
	return in_array( $host, array_map( 'strtolower', $allowed ), true );
}
