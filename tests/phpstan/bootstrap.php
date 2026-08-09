<?php
/**
 * Runtime constants used only while PHPStan builds its symbol table.
 *
 * @package WPAvatar
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/wordpress/' );
defined( 'WP_CONTENT_DIR' ) || define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
defined( 'WPAVATAR_VERSION' ) || define( 'WPAVATAR_VERSION', 'phpstan' );
defined( 'WPAVATAR_PLUGIN_DIR' ) || define( 'WPAVATAR_PLUGIN_DIR', __DIR__ . '/../../' );
defined( 'WPAVATAR_PLUGIN_URL' ) || define( 'WPAVATAR_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpavatar/' );
defined( 'WPAVATAR_CACHE_DIR' ) || define( 'WPAVATAR_CACHE_DIR', WP_CONTENT_DIR . '/uploads/cravatar' );
