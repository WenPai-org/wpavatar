<?php
namespace WPAvatar;

class Core {
	public static function init() {
	}
}

class Cravatar {
	public static $gravatar_domains = array(
		'www.gravatar.com',
		'0.gravatar.com',
		'1.gravatar.com',
		'2.gravatar.com',
		'secure.gravatar.com',
		's.gravatar.com',
		'cn.gravatar.com',
		'gravatar.com',
	);

	public static function init() {
		if ( wpavatar_get_option( 'wpavatar_enable_cravatar', true ) ) {
			// 使用高优先级过滤器来预处理头像数据
			add_filter( 'pre_get_avatar_data', array( __CLASS__, 'pre_get_avatar_data' ), 1, 2 );

			// 直接过滤avatar_url，无论是否已经通过pre_get_avatar_data处理
			add_filter( 'get_avatar_url', array( __CLASS__, 'get_avatar_url' ), 999, 2 );

			// 其他过滤器
			add_filter( 'um_user_avatar_url_filter', array( __CLASS__, 'replace_avatar_url' ), 1 );
			add_filter( 'bp_gravatar_url', array( __CLASS__, 'replace_avatar_url' ), 1 );
			add_filter( 'user_profile_picture_description', array( __CLASS__, 'modify_profile_picture_description' ), 1 );
			add_filter( 'get_avatar', array( __CLASS__, 'add_seo_alt' ), 10, 5 );
		}
	}

	public static function get_local_avatars() {
		$avatars = array();

		if ( file_exists( WPAVATAR_PLUGIN_DIR . 'assets/images/default-avatar.png' ) ) {
			$avatars['default'] = array(
				'url'  => WPAVATAR_PLUGIN_URL . 'assets/images/default-avatar.png',
				'name' => __( '默认头像', 'wpavatar' ),
			);
		}

		if ( file_exists( WPAVATAR_PLUGIN_DIR . 'assets/images/wapuu.png' ) ) {
			$avatars['wapuu'] = array(
				'url'  => WPAVATAR_PLUGIN_URL . 'assets/images/wapuu.png',
				'name' => __( '文派Wapuu', 'wpavatar' ),
			);
		}

		if ( file_exists( WPAVATAR_PLUGIN_DIR . 'assets/images/wapuu-china.png' ) ) {
			$avatars['wapuu-china'] = array(
				'url'  => WPAVATAR_PLUGIN_URL . 'assets/images/wapuu-china.png',
				'name' => __( '中国Wapuu', 'wpavatar' ),
			);
		}

		if ( file_exists( WPAVATAR_PLUGIN_DIR . 'assets/images/cravatar-default.png' ) ) {
			$avatars['cravatar'] = array(
				'url'  => WPAVATAR_PLUGIN_URL . 'assets/images/cravatar-default.png',
				'name' => __( 'Cravatar默认', 'wpavatar' ),
			);
		}

		if ( file_exists( WPAVATAR_PLUGIN_DIR . 'assets/images/cravatar-logo.png' ) ) {
			$avatars['cravatar-logo'] = array(
				'url'  => WPAVATAR_PLUGIN_URL . 'assets/images/cravatar-logo.png',
				'name' => __( 'Cravatar标志', 'wpavatar' ),
			);
		}

		return $avatars;
	}

	public static function modify_profile_picture_description() {
		return '<a href="https://cravatar.com" target="_blank" rel="noopener">' . __( '您可以在初认头像修改您的资料图片', 'wpavatar' ) . '</a>';
	}

	/**
	 * 预处理头像数据，根据用户设置选择哈希方法
	 *
	 * SHA256 迁移: Cravatar 服务端已支持 SHA256，不再强制 MD5
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if ( is_null( $args ) ) {
			$args = array();
		}

		$email = self::get_email_from_id_or_email( $id_or_email );

		if ( empty( $email ) ) {
			return $args;
		}

		// 统一使用 SHA256
		$args['hash_method']         = 'sha256';
		$args['wpavatar_email_hash'] = hash( 'sha256', strtolower( trim( $email ) ) );

		// 添加超时属性
		$timeout             = wpavatar_get_option( 'wpavatar_timeout', 5 );
		$args['extra_attr']  = isset( $args['extra_attr'] ) ? $args['extra_attr'] : '';
		$args['extra_attr'] .= ' data-timeout="' . esc_attr( $timeout ) . '"';

		return $args;
	}

	/**
	 * 直接替换头像URL，根据用户哈希设置构建 Cravatar URL
	 *
	 * SHA256 迁移: 不再强制 MD5，Cravatar 服务端通过 hash 长度自动识别算法
	 */
	public static function get_avatar_url( $url, $id_or_email ) {
		$cdn_domain = wpavatar_get_cdn_domain();
		$parsed_url = wp_parse_url( $url );

		if ( is_array( $parsed_url ) && isset( $parsed_url['host'] ) && strtolower( rtrim( $parsed_url['host'], '.' ) ) === strtolower( $cdn_domain ) ) {
			return $url;
		}

		$hash = '';
		if ( preg_match( '/\/avatar\/([a-f0-9]{64})/i', $url, $matches ) ) {
			$hash = strtolower( $matches[1] );
		} elseif ( preg_match( '/\/avatar\/([a-f0-9]{32})/i', $url, $matches ) ) {
			$hash = strtolower( $matches[1] );
		}

		if ( empty( $hash ) ) {
			return self::replace_avatar_url( $url );
		}

		$new_url = 'https://' . $cdn_domain . '/avatar/' . $hash;
		if ( is_array( $parsed_url ) && isset( $parsed_url['query'] ) ) {
			$new_url .= '?' . $parsed_url['query'];
		}

		return $new_url;
	}

	public static function get_email_from_id_or_email( $id_or_email ) {
		$email = '';

		if ( is_numeric( $id_or_email ) ) {
			$user = get_user_by( 'id', (int) $id_or_email );
			if ( $user ) {
				$email = $user->user_email;
			}
		} elseif ( is_string( $id_or_email ) ) {
			if ( is_email( $id_or_email ) ) {
				$email = $id_or_email;
			}
		} elseif ( is_object( $id_or_email ) ) {
			if ( $id_or_email instanceof \WP_User ) {
				$email = $id_or_email->user_email;
			} elseif ( $id_or_email instanceof \WP_Post ) {
				$user = get_user_by( 'id', (int) $id_or_email->post_author );
				if ( $user ) {
					$email = $user->user_email;
				}
			} elseif ( $id_or_email instanceof \WP_Comment ) {
				if ( ! empty( $id_or_email->user_id ) ) {
					$user = get_user_by( 'id', (int) $id_or_email->user_id );
					if ( $user ) {
						$email = $user->user_email;
					}
				} elseif ( ! empty( $id_or_email->comment_author_email ) ) {
					$email = $id_or_email->comment_author_email;
				}
			} elseif ( isset( $id_or_email->comment_ID ) ) {
				$comment = get_comment( $id_or_email->comment_ID );
				if ( $comment ) {
					if ( $comment->user_id ) {
						$user = get_user_by( 'id', (int) $comment->user_id );
						if ( $user ) {
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

	public static function replace_avatar_url( $url ) {
		$parsed_url = wp_parse_url( $url );
		if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
			return $url;
		}

		$host = strtolower( rtrim( $parsed_url['host'], '.' ) );
		if ( ! in_array( $host, self::$gravatar_domains, true ) ) {
			return $url;
		}

		return preg_replace(
			'~^https?://' . preg_quote( $parsed_url['host'], '~' ) . '~i',
			'https://' . wpavatar_get_cdn_domain(),
			$url,
			1
		);
	}

	public static function add_seo_alt( $avatar, $id_or_email, $size, $default, $alt ) {
		// 添加SEO友好的alt属性
		$seo_alt = wpavatar_get_option( 'wpavatar_seo_alt' );
		if ( ! empty( $seo_alt ) ) {
			$user = false;
			if ( is_numeric( $id_or_email ) ) {
				$user = get_user_by( 'id', $id_or_email );
			} elseif ( is_object( $id_or_email ) ) {
				if ( isset( $id_or_email->user_id ) && $id_or_email->user_id ) {
					$user = get_user_by( 'id', $id_or_email->user_id );
				} elseif ( isset( $id_or_email->comment_author_email ) ) {
					$user = (object) array(
						'display_name' => $id_or_email->comment_author,
					);
				}
			} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
				$user = get_user_by( 'email', $id_or_email );
			}

			$alt_text = $user ? str_replace( '%s', $user->display_name, $seo_alt ) : __( 'User avatar', 'wpavatar' );
			$avatar   = preg_replace( '/alt=([\'"])[^\'"]*\\1/', "alt='" . esc_attr( $alt_text ) . "'", $avatar );
		}

		// 添加头像加载失败的备用显示
		if ( wpavatar_get_option( 'wpavatar_fallback_mode', 1 ) ) {
			$fallback_type = wpavatar_get_option( 'wpavatar_fallback_avatar', 'default' );
			$local_avatars = self::get_local_avatars();

			if ( isset( $local_avatars[ $fallback_type ] ) ) {
				$fallback_url = $local_avatars[ $fallback_type ]['url'];

				if ( strpos( $avatar, 'onerror' ) === false ) {
					$avatar = str_replace( '<img ', '<img onerror="this.onerror=null;this.src=\'' . esc_url( $fallback_url ) . '\';" ', $avatar );
				}
			}
		}

		return $avatar;
	}
}

class Cache {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'setup_cache_dir' ) );
		add_action( 'init', array( __CLASS__, 'schedule_purge' ) );

		if ( wpavatar_get_option( 'wpavatar_enable_cache', true ) ) {
			add_filter( 'get_avatar_url', array( __CLASS__, 'prepare_cache_url' ), 99, 2 );
			add_filter( 'get_avatar', array( __CLASS__, 'serve_cached_avatar' ), 20, 5 );
		}

		add_action( 'wpavatar_purge_cache', array( __CLASS__, 'purge_expired' ) );

		add_action( 'comment_post', array( __CLASS__, 'cache_comment_avatar' ), 10, 2 );
		add_action( 'profile_update', array( __CLASS__, 'cache_user_avatar' ), 10, 1 );
	}

	public static function setup_cache_dir() {
		$base_dir = wpavatar_get_cache_base_dir();
		$base_dir = rtrim( $base_dir, '/\\' ) . '/';

		if ( ! file_exists( $base_dir ) ) {
			if ( ! wp_mkdir_p( $base_dir ) ) {
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						'wpavatar_cache',
						'cache_dir_error',
						__( '无法创建缓存目录，请检查权限', 'wpavatar' ),
						'error'
					);
				}
				return false;
			}
		}

		if ( ! is_writable( $base_dir ) ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					'wpavatar_cache',
					'cache_dir_writable',
					__( '缓存目录不可写，请检查权限', 'wpavatar' ),
					'error'
				);
			}
			return false;
		}

		// Create index.php in base dir
		$index_file = $base_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, '<?php // Silence is golden.' );
		}

		// Create .htaccess in base dir
		$htaccess_file = $base_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content  = "# Prevent directory listing\n";
			$htaccess_content .= "Options -Indexes\n";
			$htaccess_content .= "# Cache images for one week\n";
			$htaccess_content .= "<IfModule mod_expires.c>\n";
			$htaccess_content .= "ExpiresActive On\n";
			$htaccess_content .= "ExpiresByType image/jpeg \"access plus 1 week\"\n";
			$htaccess_content .= "</IfModule>\n";
			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// If multisite, create site-specific directory
		if ( is_multisite() ) {
			$blog_id  = get_current_blog_id();
			$site_dir = $base_dir . 'site-' . $blog_id . '/';

			if ( ! file_exists( $site_dir ) ) {
				if ( ! wp_mkdir_p( $site_dir ) ) {
					if ( function_exists( 'add_settings_error' ) ) {
						add_settings_error(
							'wpavatar_cache',
							'cache_dir_error',
							__( '无法创建站点缓存目录，请检查权限', 'wpavatar' ),
							'error'
						);
					}
					return false;
				}
			}

			if ( ! is_writable( $site_dir ) ) {
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						'wpavatar_cache',
						'cache_dir_writable',
						__( '站点缓存目录不可写，请检查权限', 'wpavatar' ),
						'error'
					);
				}
				return false;
			}

			// Create index.php in site dir
			$site_index_file = $site_dir . 'index.php';
			if ( ! file_exists( $site_index_file ) ) {
				@file_put_contents( $site_index_file, '<?php // Silence is golden.' );
			}
		}

		return true;
	}

	public static function prepare_cache_url( $url, $id_or_email ) {
		if ( ! wpavatar_get_option( 'wpavatar_enable_cache', true ) ) {
			return $url;
		}

		if ( strpos( $url, 'cache-key=' ) === false ) {
			$hash = self::get_avatar_hash( $id_or_email );
			if ( $hash ) {
				$url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . 'cache-key=' . $hash;
			}
		}

		return $url;
	}

	public static function serve_cached_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		if ( ! wpavatar_get_option( 'wpavatar_enable_cache', true ) ) {
			return $avatar;
		}

		preg_match( '/\ssrc=([\'"])([^\'"]+)\\1/i', $avatar, $matches );
		if ( empty( $matches[2] ) ) {
			return $avatar;
		}

		$html_url = $matches[2];
		$url      = html_entity_decode( $html_url, ENT_QUOTES, 'UTF-8' );

		if ( ! wpavatar_is_allowed_avatar_url( $url ) ) {
			return $avatar;
		}

		$hash       = self::get_avatar_hash( $id_or_email );
		$cache_file = self::get_cache_path( $hash, $size );
		if ( empty( $cache_file ) ) {
			return $avatar;
		}

		$cache_expire = wpavatar_get_option( 'wpavatar_cache_expire', 7 ) * DAY_IN_SECONDS;
		if ( file_exists( $cache_file ) && filemtime( $cache_file ) > ( time() - $cache_expire ) ) {
			$cached_url = content_url( str_replace( WP_CONTENT_DIR, '', $cache_file ) );
			return str_replace( $html_url, esc_url( $cached_url ), $avatar );
		}

		if ( self::cache_remote_avatar( $url, $cache_file ) ) {
			$cached_url = content_url( str_replace( WP_CONTENT_DIR, '', $cache_file ) );
			return str_replace( $html_url, esc_url( $cached_url ), $avatar );
		}

		return $avatar;
	}

	public static function get_avatar_hash( $id_or_email ) {
		$email = Cravatar::get_email_from_id_or_email( $id_or_email );

		if ( empty( $email ) ) {
			return '';
		}

		// 统一使用 SHA256
		return hash( 'sha256', strtolower( trim( $email ) ) );
	}

	public static function get_cache_path( $hash, $size ) {
		if ( ! preg_match( '/^[a-f0-9]{32}(?:[a-f0-9]{32})?$/', $hash ) ) {
			return '';
		}

		$size = wpavatar_sanitize_avatar_size( $size );
		$dir  = trailingslashit( wpavatar_get_cache_base_dir() );

		if ( is_multisite() ) {
			$dir .= 'site-' . get_current_blog_id() . '/';
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		return $dir . $hash . '-' . $size . '.jpg';
	}

	public static function cache_remote_avatar( $url, $dest ) {
		if ( empty( $dest ) || ! wpavatar_is_allowed_avatar_url( $url ) ) {
			return false;
		}

		$dir = dirname( $dest );
		if ( ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) || ! is_writable( $dir ) ) {
			return false;
		}

		$lock = @fopen( $dest . '.lock', 'x' );
		if ( false === $lock ) {
			return false;
		}

		$tmp = '';
		try {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => wpavatar_sanitize_timeout( wpavatar_get_option( 'wpavatar_timeout', 5 ) ),
					'redirection'         => 2,
					'limit_response_size' => 2 * MB_IN_BYTES,
					'user-agent'          => 'WPAvatar/' . WPAVATAR_VERSION . '; ' . home_url(),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$image = wp_remote_retrieve_body( $response );
			if ( empty( $image ) || strlen( $image ) > 2 * MB_IN_BYTES ) {
				return false;
			}

			$image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $image ) : false;
			$mime       = is_array( $image_info ) ? ( $image_info['mime'] ?? '' ) : '';
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
				return false;
			}

			$tmp = tempnam( $dir, 'wpavatar-' );
			if ( false === $tmp || false === file_put_contents( $tmp, $image, LOCK_EX ) ) {
				return false;
			}

			@chmod( $tmp, 0644 );
			if ( ! @rename( $tmp, $dest ) ) {
				return false;
			}
			$tmp = '';
			return true;
		} finally {
			if ( $tmp && file_exists( $tmp ) ) {
				@unlink( $tmp );
			}
			fclose( $lock );
			@unlink( $dest . '.lock' );
		}
	}

	public static function cache_comment_avatar( $comment_id, $comment_approved ) {
		if ( $comment_approved !== 1 || ! wpavatar_get_option( 'wpavatar_enable_cache', true ) ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment || empty( $comment->comment_author_email ) ) {
			return;
		}

		$email = $comment->comment_author_email;
		$size  = wpavatar_get_option( 'wpavatar_shortcode_size', 96 );

		$avatar_url = get_avatar_url( $email, array( 'size' => $size ) );

		$hash = self::get_avatar_hash( $email );
		if ( empty( $hash ) ) {
			return;
		}

		$cache_file = self::get_cache_path( $hash, $size );

		self::cache_remote_avatar( $avatar_url, $cache_file );

		$retina_url        = get_avatar_url( $email, array( 'size' => $size * 2 ) );
		$retina_cache_file = self::get_cache_path( $hash, $size * 2 );
		self::cache_remote_avatar( $retina_url, $retina_cache_file );
	}

	public static function cache_user_avatar( $user_id ) {
		if ( ! wpavatar_get_option( 'wpavatar_enable_cache', true ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$email = $user->user_email;
		$size  = wpavatar_get_option( 'wpavatar_shortcode_size', 96 );

		$avatar_url = get_avatar_url( $email, array( 'size' => $size ) );

		$hash = self::get_avatar_hash( $email );
		if ( empty( $hash ) ) {
			return;
		}

		$cache_file = self::get_cache_path( $hash, $size );

		self::cache_remote_avatar( $avatar_url, $cache_file );

		$retina_url        = get_avatar_url( $email, array( 'size' => $size * 2 ) );
		$retina_cache_file = self::get_cache_path( $hash, $size * 2 );
		self::cache_remote_avatar( $retina_url, $retina_cache_file );
	}

	public static function purge_expired() {
		$base_dir = wpavatar_get_cache_base_dir();
		if ( ! file_exists( $base_dir ) || ! is_dir( $base_dir ) ) {
			return;
		}

		$expire_days = wpavatar_get_option( 'wpavatar_cache_expire', 7 );
		$expire_time = time() - ( $expire_days * DAY_IN_SECONDS );

		// If multisite, purge site-specific directory
		if ( is_multisite() ) {
			$blog_id  = get_current_blog_id();
			$site_dir = trailingslashit( $base_dir ) . 'site-' . $blog_id . '/';

			if ( file_exists( $site_dir ) && is_dir( $site_dir ) ) {
				$files = glob( $site_dir . '*.jpg' );
				if ( $files ) {
					foreach ( $files as $file ) {
						if ( filemtime( $file ) < $expire_time ) {
							@unlink( $file );
						}
					}
				}
			}
		} else {
			// For non-multisite, purge all files in the base directory
			$files = glob( trailingslashit( $base_dir ) . '*.jpg' );
			if ( $files ) {
				foreach ( $files as $file ) {
					if ( filemtime( $file ) < $expire_time ) {
						@unlink( $file );
					}
				}
			}
		}
	}

	public static function schedule_purge() {
		if ( ! wp_next_scheduled( 'wpavatar_purge_cache' ) ) {
			wp_schedule_event( time(), 'daily', 'wpavatar_purge_cache' );
		}
	}

	public static function check_cache_status() {
		$base_dir = wpavatar_get_cache_base_dir();

		// For multisite, check site-specific directory
		if ( is_multisite() ) {
			$blog_id  = get_current_blog_id();
			$site_dir = trailingslashit( $base_dir ) . 'site-' . $blog_id;

			$stats = array(
				'path'       => $site_dir,
				'exists'     => file_exists( $site_dir ) && is_dir( $site_dir ),
				'writable'   => is_writable( $site_dir ),
				'file_count' => 0,
				'size'       => 0,
			);

			if ( $stats['exists'] ) {
				$files               = glob( trailingslashit( $site_dir ) . '*.jpg' );
				$stats['file_count'] = count( $files ?: array() );
				foreach ( $files ?: array() as $file ) {
					$stats['size'] += filesize( $file );
				}
				$stats['size'] = size_format( $stats['size'] );
			}
		} else {
			// For single site
			$stats = array(
				'path'       => $base_dir,
				'exists'     => file_exists( $base_dir ) && is_dir( $base_dir ),
				'writable'   => is_writable( $base_dir ),
				'file_count' => 0,
				'size'       => 0,
			);

			if ( $stats['exists'] ) {
				$files               = glob( trailingslashit( $base_dir ) . '*.jpg' );
				$stats['file_count'] = count( $files ?: array() );
				foreach ( $files ?: array() as $file ) {
					$stats['size'] += filesize( $file );
				}
				$stats['size'] = size_format( $stats['size'] );
			}
		}

						ob_start();
		?>
						<div class="cache-stats">
							<p><?php printf( __( '缓存目录: %s', 'wpavatar' ), esc_html( $stats['path'] ) ); ?></p>
							<p><?php printf( __( '目录存在: %s', 'wpavatar' ), $stats['exists'] ? __( '是', 'wpavatar' ) : __( '否', 'wpavatar' ) ); ?></p>
							<p><?php printf( __( '目录可写: %s', 'wpavatar' ), $stats['writable'] ? __( '是', 'wpavatar' ) : __( '否', 'wpavatar' ) ); ?></p>
							<p><?php printf( __( '缓存文件数: %d', 'wpavatar' ), $stats['file_count'] ); ?></p>
							<p><?php printf( __( '缓存总大小: %s', 'wpavatar' ), $stats['size'] ); ?></p>
						</div>
						<?php
						return ob_get_clean();
	}

	public static function check_all_cache_status() {
		$base_dir = wpavatar_get_cache_base_dir();

		$global_stats = array(
			'path'        => $base_dir,
			'exists'      => file_exists( $base_dir ) && is_dir( $base_dir ),
			'writable'    => is_writable( $base_dir ),
			'site_count'  => 0,
			'total_files' => 0,
			'total_size'  => 0,
		);

		$site_stats = array();

		if ( $global_stats['exists'] ) {
			// Find all site directories
			$site_dirs                  = glob( trailingslashit( $base_dir ) . 'site-*', GLOB_ONLYDIR );
			$global_stats['site_count'] = count( $site_dirs ?: array() );

			// Check each site directory
			foreach ( $site_dirs ?: array() as $site_dir ) {
				$blog_id = intval( str_replace( trailingslashit( $base_dir ) . 'site-', '', $site_dir ) );
				if ( $blog_id > 0 ) {
					$files                        = glob( $site_dir . '/*.jpg' );
					$file_count                   = count( $files ?: array() );
					$global_stats['total_files'] += $file_count;

					$size = 0;
					foreach ( $files ?: array() as $file ) {
						$size                       += filesize( $file );
						$global_stats['total_size'] += filesize( $file );
					}

					// Try to get blog name
					$blog_name = '';
					if ( function_exists( 'get_blog_details' ) ) {
						$blog_details = get_blog_details( $blog_id );
						if ( $blog_details ) {
							$blog_name = $blog_details->blogname;
						}
					}

					$site_stats[] = array(
						'id'    => $blog_id,
						'name'  => $blog_name ?: sprintf( __( '站点 #%d', 'wpavatar' ), $blog_id ),
						'files' => $file_count,
						'size'  => size_format( $size ),
					);
				}
			}

			// Also check for legacy non-site specific files
			$legacy_files = glob( trailingslashit( $base_dir ) . '*.jpg' );
			$legacy_count = count( $legacy_files ?: array() );
			if ( $legacy_count > 0 ) {
				$global_stats['total_files'] += $legacy_count;

				$legacy_size = 0;
				foreach ( $legacy_files ?: array() as $file ) {
					$legacy_size                += filesize( $file );
					$global_stats['total_size'] += filesize( $file );
				}

				$site_stats[] = array(
					'id'    => 0,
					'name'  => __( '旧版缓存文件（非站点特定）', 'wpavatar' ),
					'files' => $legacy_count,
					'size'  => size_format( $legacy_size ),
				);
			}
		}

		// Sort sites by file count (descending)
		usort(
			$site_stats,
			function ( $a, $b ) {
				return $b['files'] - $a['files'];
			}
		);

		ob_start();
		?>
						<div class="network-cache-stats">
							<h4><?php _e( '全局缓存统计', 'wpavatar' ); ?></h4>
							<p><?php printf( __( '缓存根目录: %s', 'wpavatar' ), esc_html( $global_stats['path'] ) ); ?></p>
							<p><?php printf( __( '目录存在: %s', 'wpavatar' ), $global_stats['exists'] ? __( '是', 'wpavatar' ) : __( '否', 'wpavatar' ) ); ?></p>
							<p><?php printf( __( '目录可写: %s', 'wpavatar' ), $global_stats['writable'] ? __( '是', 'wpavatar' ) : __( '否', 'wpavatar' ) ); ?></p>
							<p><?php printf( __( '站点缓存目录数: %d', 'wpavatar' ), $global_stats['site_count'] ); ?></p>
							<p><?php printf( __( '总缓存文件数: %d', 'wpavatar' ), $global_stats['total_files'] ); ?></p>
							<p><?php printf( __( '总缓存大小: %s', 'wpavatar' ), size_format( $global_stats['total_size'] ) ); ?></p>

			<?php if ( ! empty( $site_stats ) ) : ?>
								<h4><?php _e( '站点缓存详情', 'wpavatar' ); ?></h4>
								<table class="widefat striped" style="margin-top: 10px;">
									<thead>
										<tr>
											<th><?php _e( '站点', 'wpavatar' ); ?></th>
											<th><?php _e( '文件数', 'wpavatar' ); ?></th>
											<th><?php _e( '大小', 'wpavatar' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $site_stats as $stat ) : ?>
											<tr>
												<td><?php echo esc_html( $stat['name'] ); ?></td>
												<td><?php echo esc_html( $stat['files'] ); ?></td>
												<td><?php echo esc_html( $stat['size'] ); ?></td>
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
		add_shortcode( 'wpavatar', array( __CLASS__, 'render_avatar' ) );
		add_shortcode( 'wpavatar_username', array( __CLASS__, 'render_username' ) );
		add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'menu_item_replace' ), 10, 4 );
	}

	public static function render_avatar( $atts ) {
		$default_size  = wpavatar_get_option( 'wpavatar_shortcode_size', 96 );
		$default_class = wpavatar_get_option( 'wpavatar_shortcode_class', 'wpavatar' );
		$default_shape = wpavatar_get_option( 'wpavatar_shortcode_shape', 'square' );

		$atts = shortcode_atts(
			array(
				'size'    => $default_size,
				'user_id' => get_current_user_id(),
				'class'   => $default_class,
				'email'   => '',
				'shape'   => $default_shape,
				'title'   => '',
			),
			$atts
		);

		if ( empty( $atts['user_id'] ) && empty( $atts['email'] ) ) {
			$atts['user_id'] = get_current_user_id();
		}

		$classes = array( $atts['class'] );

		if ( $atts['shape'] === 'circle' ) {
			$classes[] = 'avatar-circle';
			$style     = 'style="border-radius: 50%; overflow: hidden;"';
		} elseif ( $atts['shape'] === 'rounded' ) {
			$classes[] = 'avatar-rounded';
			$style     = 'style="border-radius: 8px; overflow: hidden;"';
		} else {
			$classes[] = 'avatar-square';
			$style     = '';
		}

		$avatar_args = array(
			'class'      => implode( ' ', $classes ),
			'size'       => intval( $atts['size'] ),
			'extra_attr' => $style,
		);

		if ( ! empty( $atts['title'] ) ) {
			$avatar_args['extra_attr'] .= ' title="' . esc_attr( $atts['title'] ) . '"';
		}

		ob_start();
		if ( ! empty( $atts['email'] ) ) {
			echo get_avatar( $atts['email'], intval( $atts['size'] ), 'default', '', $avatar_args );
		} else {
			echo get_avatar( $atts['user_id'], intval( $atts['size'] ), 'default', '', $avatar_args );
		}
		return ob_get_clean();
	}

	public static function render_username( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
				'before'  => '',
				'after'   => '',
			),
			$atts
		);

		if ( ! empty( $atts['user_id'] ) ) {
			$user = get_user_by( 'id', $atts['user_id'] );
		} else {
			$user = wp_get_current_user();
		}

		// 检查用户是否已登录且有显示名称，如果没有则返回空字符串
		if ( ! $user || ! $user->ID || empty( $user->display_name ) ) {
			return '';
		}

		return wp_kses_post( $atts['before'] ) . esc_html( $user->display_name ) . wp_kses_post( $atts['after'] );
	}

	public static function menu_item_replace( $item_output, $item, $depth, $args ) {
		if ( strpos( $item_output, '{wpavatar}' ) !== false ) {
			$item_output = str_replace( '{wpavatar}', do_shortcode( '[wpavatar shape="circle" size="32"]' ), $item_output );
		}
		if ( strpos( $item_output, '{wpavatar_username}' ) !== false ) {
			$item_output = str_replace( '{wpavatar_username}', do_shortcode( '[wpavatar_username]' ), $item_output );
		}
		return $item_output;
	}

	public static function generate_preview( $user_id = 0, $shape = 'square', $size = 96 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$atts = array(
			'user_id' => $user_id,
			'shape'   => $shape,
			'size'    => $size,
			'class'   => 'wpavatar-preview',
		);

		return self::render_avatar( $atts );
	}
}
