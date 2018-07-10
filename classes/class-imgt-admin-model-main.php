<?php
defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

/**
 * Class that handle the data for the main page.
 *
 * @package Imagify Tools
 * @since   1.0
 * @author  Grégory Viguier
 */
class IMGT_Admin_Model_Main {

	/**
	 * Class version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.2';

	/**
	 * Info cache duration in minutes.
	 *
	 * @var int
	 */
	const CACHE_DURATION = 30;

	/**
	 * Prefix used to cache the requests.
	 *
	 * @var string
	 */
	const REQUEST_CACHE_PREFIX = 'imgt_req_';

	/**
	 * Data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The constructor.
	 *
	 * @since 1.0
	 * @author Grégory Viguier
	 */
	public function __construct() {
		global $wpdb, $wp_object_cache;

		/**
		 * Uploads dir and URL.
		 */
		$error_string  = '***' . __( 'Error', 'imagify-tools' ) . '***';
		$wp_upload_dir = (array) wp_upload_dir();
		$wp_upload_dir = array_merge( array(
			'path'    => $error_string, // /absolute/path/to/uploads/sub/dir
			'url'     => $error_string, // http://example.com/wp-content/uploads/sub/dir
			'subdir'  => $error_string, // /sub/dir
			'basedir' => $error_string, // /absolute/path/to/uploads
			'baseurl' => $error_string, // http://example.com/wp-content/uploads
			'error'   => $error_string, // false
		), $wp_upload_dir );

		if ( '' === $wp_upload_dir['error'] ) {
			$wp_upload_dir['error'] = '***' . __( 'empty string', 'imagify-tools' ) . '***';
		}
		if ( false === $wp_upload_dir['error'] ) {
			$wp_upload_dir['error'] = 'false (boolean)';
		}

		/**
		 * Chmod and backup dir.
		 */
		$filesystem = imagify_get_filesystem();
		$chmod_dir  = fileperms( ABSPATH ) & 0777 | 0755;
		$chmod_file = fileperms( ABSPATH . 'index.php' ) & 0777 | 0644;
		$backup_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'backup/';

		/**
		 * Image editor.
		 */
		$image_path = admin_url( 'images/arrows.png' );
		$image_path = str_replace( site_url( '/' ), ABSPATH, $image_path );

		if ( file_exists( $image_path ) ) {
			$image_editor = wp_get_image_editor( $image_path );

			if ( ! is_wp_error( $image_editor ) ) {
				$image_editor = get_class( $image_editor );
				$image_editor = str_replace( 'WP_Image_Editor_', '', $image_editor );
			}
		} else {
			$image_editor = new WP_Error( 'image_not_found', __( 'Image not found.', 'imagify-tools' ) );
		}

		/**
		 * Requests.
		 */
		$blocking_link = imagify_tools_get_site_transient( 'imgt_blocking_requests' ) ? __( 'Make optimization back to async', 'imagify-tools' ) : __( 'Make optimization non async', 'imagify-tools' );
		$blocking_link = '<a class="imgt-button imgt-button-ternary imgt-button-mini" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . IMGT_Admin_Post::get_action( 'switch_blocking_requests' ) ), IMGT_Admin_Post::get_action( 'switch_blocking_requests' ) ) ) . '">' . $blocking_link . '</a>';
		$ajax_url      = admin_url( 'admin-ajax.php?action=' . IMGT_Admin_Post::get_action( 'test' ) );
		$post_url      = admin_url( 'admin-post.php?action=' . IMGT_Admin_Post::get_action( 'test' ) );
		$requests      = array(
			array(
				'label'     => __( 'cURL enabled', 'imagify-tools' ),
				'value'     => function_exists( 'curl_init' ) && function_exists( 'curl_exec' ),
				'compare'   => true,
				'more_info' => $blocking_link,
			),
			array(
				/* translators: %s is a URL. */
				'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>imagify.io</code>' ),
				'value'     => (bool) $this->are_requests_blocked( 'https://imagify.io' ),
				'compare'   => false,
				'more_info' => $this->are_requests_blocked( 'https://imagify.io' ) . $this->get_clear_request_cache_link( 'https://imagify.io' ),
			),
			array(
				/* translators: %s is a URL. */
				'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>app.imagify.io</code>' ),
				'value'     => (bool) $this->are_requests_blocked( 'https://app.imagify.io/api/version/' ),
				'compare'   => false,
				'more_info' => $this->are_requests_blocked( 'https://app.imagify.io/api/version/' ) . $this->get_clear_request_cache_link( 'https://app.imagify.io/api/version/' ),
			),
			array(
				/* translators: %s is a URL. */
				'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>s2-amz-par.imagify.io</code>' ),
				'value'     => (bool) $this->are_requests_blocked( 'https://s2-amz-par.imagify.io/wpm.png' ),
				'compare'   => false,
				'more_info' => $this->are_requests_blocked( 'https://s2-amz-par.imagify.io/wpm.png' ) . $this->get_clear_request_cache_link( 'https://s2-amz-par.imagify.io/wpm.png' ),
			),
			array(
				/* translators: %s is a URL. */
				'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>storage.imagify.io</code>' ),
				'value'     => (bool) $this->are_requests_blocked( 'http://storage.imagify.io/images/index.png' ),
				'compare'   => false,
				'more_info' => $this->are_requests_blocked( 'http://storage.imagify.io/images/index.png' ) . $this->get_clear_request_cache_link( 'http://storage.imagify.io/images/index.png' ),
			),
			array(
				/* translators: %s is a URL. */
				'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>' . preg_replace( '@^https?://@', '', admin_url( 'admin-ajax.php' ) ) . '</code>' ),
				'value'     => (bool) $this->are_requests_blocked( $ajax_url, 'POST' ),
				'compare'   => false,
				'more_info' => $this->are_requests_blocked( $ajax_url, 'POST' ) . $this->get_clear_request_cache_link( $ajax_url, 'POST' ),
			),
		);

		if ( $this->are_requests_blocked( $ajax_url, 'POST' ) && preg_match( '@^https://@', $ajax_url ) ) {
			/* translators: %s is a URL. */
			$requests[4]['label'] = sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>' . admin_url( 'admin-ajax.php' ) . '</code>' );

			$urls = array(
				set_url_scheme( $post_url, 'https' ),
				set_url_scheme( site_url( 'wp-cron.php' ), 'https' ),
				set_url_scheme( $ajax_url, 'http' ),
				set_url_scheme( $post_url, 'http' ),
				set_url_scheme( site_url( 'wp-cron.php' ), 'http' ),
			);

			foreach ( $urls as $url ) {
				$requests[] = array(
					/* translators: %s is a URL. */
					'label'     => sprintf( __( 'Requests to %s blocked', 'imagify-tools' ), '<code>' . $url . '</code>' ),
					'value'     => (bool) $this->are_requests_blocked( $url, 'POST' ),
					'compare'   => false,
					'more_info' => $this->are_requests_blocked( $url, 'POST' ) . $this->get_clear_request_cache_link( $url, 'POST' ),
				);
			}
		}

		/**
		 * Attachments / Files.
		 */
		$attachments = array(
			array(
				'label'     => __( 'Attachments with invalid or missing WP metas', 'imagify-tools' ),
				'value'     => $this->count_medias_with_invalid_wp_metas(),
				'is_error'  => $this->count_medias_with_invalid_wp_metas() > 0,
				'more_info' => $this->get_clear_cache_link( 'imgt_medias_invalid_wp_metas', 'clear_medias_with_invalid_wp_metas_cache' ),
			),
		);

		if ( class_exists( 'Imagify_Folders_DB', true ) ) {
			$attachments[] = array(
				'label'   => __( 'Folders table is ready', 'imagify-tools' ),
				'value'   => Imagify_Folders_DB::get_instance()->can_operate(),
				'compare' => true,
			);
		}

		if ( class_exists( 'Imagify_Files_DB', true ) ) {
			$attachments[] = array(
				'label'   => __( 'Files table is ready', 'imagify-tools' ),
				'value'   => Imagify_Files_DB::get_instance()->can_operate(),
				'compare' => true,
			);
		}

		if ( $this->count_orphan_files() !== false ) {
			$attachments[] = array(
				'label'     => __( 'Orphan files count', 'imagify-tools' ),
				'value'     => $this->count_orphan_files(),
				'is_error'  => $this->count_orphan_files() > 0,
				'more_info' => $this->get_clear_cache_link( 'imgt_orphan_files', 'clear_orphan_files_cache' ),
			);
		}

		/**
		 * Table NGG.
		 */
		$ngg_table_engine_fix_link = '';
		$ngg_table_engine_compare  = 'InnoDB';
		$ngg_table_engine          = $wpdb->get_var( $wpdb->prepare(
			'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			DB_NAME,
			$wpdb->prefix . 'ngg_imagify_data'
		) );

		if ( is_null( $ngg_table_engine ) ) {
			$ngg_table_engine_compare  = __( 'The table doesn\'t exist.', 'imagify-tools' );
			$ngg_table_engine          = __( 'The table doesn\'t exist.', 'imagify-tools' );
		} elseif ( $ngg_table_engine !== $ngg_table_engine_compare ) {
			$ngg_table_engine_fix_link = IMGT_Admin_Post::get_action( 'fix_ngg_table_engine' );
			$ngg_table_engine_fix_link = wp_nonce_url( admin_url( 'admin-post.php?action=' . $ngg_table_engine_fix_link ), $ngg_table_engine_fix_link );
			$ngg_table_engine_fix_link = '<br/> <a class="imgt-button imgt-button-ternary imgt-button-mini" href="' . esc_url( $ngg_table_engine_fix_link ) . '">' . __( 'Fix it', 'imagify-tools' ) . '</a>';
		}

		/**
		 * Imagify settings.
		 */
		$imagify_settings = get_site_option( 'imagify_settings' );

		/**
		 * Set the data.
		 */
		$this->data = array(
			__( 'Filesystem Tests', 'imagify-tools' ) => array(
				array(
					'label'     => 'ABSPATH',
					'value'     => ABSPATH,
					'is_error'  => ! path_is_absolute( ABSPATH ),
					'more_info' => __( 'Should be an absolute path.', 'imagify-tools' ),
				),
				array(
					'label'     => 'IMAGIFY_PATH',
					'value'     => defined( 'IMAGIFY_PATH' ) ? IMAGIFY_PATH : __( 'Not defined', 'imagify-tools' ),
					'is_error'  => defined( 'IMAGIFY_PATH' ) && ! path_is_absolute( IMAGIFY_PATH ),
					'more_info' => __( 'Should be an absolute path.', 'imagify-tools' ),
				),
				array(
					'label'     => 'wp_upload_dir() <em>(path)</em>',
					'value'     => $wp_upload_dir['path'],
					'is_error'  => $error_string === $wp_upload_dir['path'] || strpos( $wp_upload_dir['path'], ABSPATH ) !== 0 || ! path_is_absolute( $wp_upload_dir['path'] ),
					/* translators: %s is a file path. */
					'more_info' => sprintf( __( 'Should be an absolute path and start with %s.', 'imagify-tools' ), '<code>' . ABSPATH . '</code>' ),
				),
				array(
					'label'     => 'wp_upload_dir() <em>(url)</em>',
					'value'     => $wp_upload_dir['url'],
					'is_error'  => $error_string === $wp_upload_dir['url'] || ! filter_var( $wp_upload_dir['url'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED ),
					'more_info' => __( 'Should be a valid URL.', 'imagify-tools' ),
				),
				array(
					'label'     => 'wp_upload_dir() <em>(subdir)</em>',
					'value'     => $wp_upload_dir['subdir'],
					'is_error'  => $error_string === $wp_upload_dir['path'],
					'more_info' => 'Meh',
				),
				array(
					'label'     => 'wp_upload_dir() <em>(basedir)</em>',
					'value'     => $wp_upload_dir['basedir'],
					'is_error'  => $error_string === $wp_upload_dir['basedir'] || strpos( $wp_upload_dir['basedir'], ABSPATH ) !== 0 || ! path_is_absolute( $wp_upload_dir['basedir'] ),
					/* translators: %s is a file path. */
					'more_info' => sprintf( __( 'Should be an absolute path and start with %s.', 'imagify-tools' ), '<code>' . ABSPATH . '</code>' ),
				),
				array(
					'label'     => 'wp_upload_dir() <em>(baseurl)</em>',
					'value'     => $wp_upload_dir['baseurl'],
					'is_error'  => $error_string === $wp_upload_dir['baseurl'] || ! filter_var( $wp_upload_dir['baseurl'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED ),
					'more_info' => __( 'Should be a valid URL.', 'imagify-tools' ),
				),
				array(
					'label'     => 'wp_upload_dir() <em>(error)</em>',
					'value'     => $wp_upload_dir['error'],
					'compare'   => 'false (boolean)',
					/* translators: %s is a value. */
					'more_info' => sprintf( __( 'Should be %s.', 'imagify-tools' ), '<code>false (boolean)</code>' ),
				),
				array(
					'label'     => __( 'Backups folder exists and is writable', 'imagify-tools' ),
					'value'     => file_exists( $backup_dir ) && wp_is_writable( $backup_dir ),
					'compare'   => ! empty( $imagify_settings['backup'] ),
					'more_info' => ! empty( $imagify_settings['backup'] ) ? __( 'Backup is enabled.', 'imagify-tools' ) : __( 'No need, backup is disabled.', 'imagify-tools' ),
				),
				array(
					'label'     => 'imagify_get_filesystem()',
					'value'     => $filesystem,
					'is_error'  => ! is_object( $filesystem ) || ! $filesystem || ! isset( $filesystem->errors ) || array_filter( (array) $filesystem->errors ),
					/* translators: 1 and 2 are data names. */
					'more_info' => sprintf( __( '%1$s and %2$s should be empty.', 'imagify-tools' ), '<code>WP_Error->errors</code>', '<code>WP_Error->error_data</code>' ),
				),
				array(
					'label'     => 'FS_CHMOD_DIR',
					'value'     => defined( 'FS_CHMOD_DIR' ) ? $this->to_octal( FS_CHMOD_DIR ) . ' (' . FS_CHMOD_DIR . ')' : __( 'Not defined', 'imagify-tools' ),
					'compare'   => defined( 'FS_CHMOD_DIR' ) ? $this->to_octal( $chmod_dir ) . ' (' . $chmod_dir . ')' : null,
					/* translators: %s is a value. */
					'more_info' => sprintf( __( 'Should be %s.', 'imagify-tools' ), '<code>' . $this->to_octal( $chmod_dir ) . ' (' . $chmod_dir . ')</code>' ),
				),
				array(
					'label'     => 'FS_CHMOD_FILE',
					'value'     => defined( 'FS_CHMOD_FILE' ) ? $this->to_octal( FS_CHMOD_FILE ) . ' (' . FS_CHMOD_FILE . ')' : __( 'Not defined', 'imagify-tools' ),
					'compare'   => defined( 'FS_CHMOD_FILE' ) ? $this->to_octal( $chmod_file ) . ' (' . $chmod_file . ')' : null,
					/* translators: %s is a value. */
					'more_info' => sprintf( __( 'Should be %s.', 'imagify-tools' ), '<code>' . $this->to_octal( $chmod_file ) . ' (' . $chmod_file . ')</code>' ),
				),
				array(
					'label'     => __( 'Image Editor Component', 'imagify-tools' ),
					'value'     => is_wp_error( $image_editor ) ? $image_editor->get_error_message() : $image_editor,
					'is_error'  => is_wp_error( $image_editor ),
					/* translators: 1 and 2 are values. */
					'more_info' => sprintf( __( 'Should be %1$s or %2$s.', 'imagify-tools' ), '<code>Imagick</code>', '<code>GD</code>' ),
				),
			),
			__( 'Requests Tests', 'imagify-tools' ) => $requests,
			__( 'Attachments', 'imagify-tools' )    => $attachments,
			__( 'Various Tests and Values', 'imagify-tools' ) => array(
				array(
					'label'     => __( 'Your IP address', 'imagify-tools' ),
					'value'     => imagify_tools_get_ip(),
				),
				array(
					'label'     => __( 'Your user ID', 'imagify-tools' ),
					'value'     => get_current_user_id(),
				),
				array(
					'label'     => __( 'PHP version', 'imagify-tools' ),
					'value'     => PHP_VERSION,
				),
				array(
					/* translators: 1 and 2 are constant names. */
					'label'     => sprintf( __( 'Memory Limit (%1$s value / %2$s value / real value)', 'imagify-tools' ), '<code>WP_MEMORY_LIMIT</code>', '<code>WP_MAX_MEMORY_LIMIT</code>' ),
					'value'     => WP_MEMORY_LIMIT . ' / ' . WP_MAX_MEMORY_LIMIT . ' / ' . @ini_get( 'memory_limit' ),
				),
				array(
					'label'     => __( 'Uses external object cache', 'imagify-tools' ),
					'value'     => wp_using_ext_object_cache() ? wp_using_ext_object_cache() : false,
					'more_info' => wp_using_ext_object_cache() ? get_class( $wp_object_cache ) : '',
				),
				array(
					'label'     => __( 'NGG table engine', 'imagify-tools' ),
					'value'     => $ngg_table_engine,
					'compare'   => $ngg_table_engine_compare,
					/* translators: %s is a value. */
					'more_info' => sprintf( __( 'If exists, should be %s.', 'imagify-tools' ), '<code>InnoDB</code>' ) . $ngg_table_engine_fix_link,
				),
				array(
					'label'     => __( 'Is multisite', 'imagify-tools' ),
					'value'     => is_multisite(),
				),
				array(
					'label'     => __( 'Is SSL', 'imagify-tools' ),
					'value'     => is_ssl(),
					'compare'   => $this->is_ssl(),
					/* translators: %s is a function name. */
					'more_info' => is_ssl() !== $this->is_ssl() ? sprintf( __( 'The function %s returns a wrong result, it could be a problem related with the way SSL is implemented.', 'imagify-tools' ), '<code>is_ssl()</code>' ) : '',
				),
				array(
					'label'     => __( 'Settings', 'imagify-tools' ),
					'value'     => $imagify_settings,
				),
				array(
					'label'     => __( 'Imagify User', 'imagify-tools' ),
					'value'     => $this->get_imagify_user(),
					'more_info' => $this->get_clear_cache_link( 'imgt_user', 'clear_imagify_user_cache' ),
				),
				array(
					'label'     => '$_SERVER',
					'value'     => $this->sanitize( $_SERVER ),
				),
			),
		);
	}

	/**
	 * Get the data.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @return array.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Tell if requests to a given URL are blocked.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  string $url    An URL.
	 * @param  string $method The http method to use.
	 * @return string         An empty string if not blocked. A short information text if blocked.
	 */
	protected function are_requests_blocked( $url, $method = 'GET' ) {
		static $infos = array();

		$method         = strtoupper( $method );
		$transient_name = self::REQUEST_CACHE_PREFIX . substr( md5( "$url|$method" ), 0, 10 );

		if ( isset( $infos[ $transient_name ] ) ) {
			return $infos[ $transient_name ];
		}

		$infos[ $transient_name ] = imagify_tools_get_site_transient( $transient_name );

		if ( false !== $infos[ $transient_name ] ) {
			$infos[ $transient_name ] = 'OK' === $infos[ $transient_name ] ? '' : $infos[ $transient_name ];
			return $infos[ $transient_name ];
		}

		$infos[ $transient_name ] = array();

		// Blocked by constant or filter?
		$is_blocked = _wp_http_get_object()->block_request( $url );

		if ( $is_blocked ) {
			$infos[ $transient_name ][] = __( 'Blocked internally.', 'imagify-tools' );
		}

		// Blocked by .htaccess, firewall, or host?
		$is_blocked = wp_remote_request( $url, array(
			'method'     => $method,
			'user-agent' => 'Imagify Tools',
			'cookies'    => $_COOKIE, // WPCS: input var okay.
			'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
		) );

		if ( ! is_wp_error( $is_blocked ) ) {
			$http_code  = wp_remote_retrieve_response_code( $is_blocked );
			$is_blocked = 200 !== $http_code;
			$http_code .= ' ' . get_status_header_desc( $http_code );
		}

		if ( $is_blocked ) {
			if ( is_wp_error( $is_blocked ) ) {
				/* translators: 1 is an error code. */
				$infos[ $transient_name ][] = sprintf( __( 'Request returned an error: %s', 'imagify-tools' ), '<pre>' . $is_blocked->get_error_message() . '</pre>' );
			} elseif ( preg_match( '@^https?://([^/]+\.)?imagify\.io(/|\?|$)@', $url ) ) {
				/* translators: 1 is a file name, 2 is a HTTP request code. */
				$infos[ $transient_name ][] = sprintf( __( 'Blocked by %1$s file, a firewall, the host, or it could be down (http code is %2$s).', 'imagify-tools' ), '<code>.htaccess</code>', "<code>$http_code</code>" );
			} else {
				/* translators: 1 is a file name, 2 is a HTTP request code. */
				$infos[ $transient_name ][] = sprintf( __( 'Blocked by %1$s file, a firewall, or the host (http code is %2$s).', 'imagify-tools' ), '<code>.htaccess</code>', "<code>$http_code</code>" );
			}
		}

		$infos[ $transient_name ] = implode( ' ', $infos[ $transient_name ] );
		$infos[ $transient_name ] = '' === $infos[ $transient_name ] ? 'OK' : $infos[ $transient_name ];

		imagify_tools_set_site_transient( $transient_name, $infos[ $transient_name ], self::CACHE_DURATION * MINUTE_IN_SECONDS );

		$infos[ $transient_name ] = 'OK' === $infos[ $transient_name ] ? '' : $infos[ $transient_name ];
		return $infos[ $transient_name ];
	}

	/**
	 * Get the link to clear the request cache (delete the transient).
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  string $url    An URL.
	 * @param  string $method The http method to use.
	 * @return string
	 */
	protected function get_clear_request_cache_link( $url, $method = 'GET' ) {
		$line_break     = $this->are_requests_blocked( $url, $method ) ? '<br/>' : '';
		$method         = strtoupper( $method );
		$transient_name = substr( md5( "$url|$method" ), 0, 10 );

		return $line_break . $this->get_clear_cache_link( self::REQUEST_CACHE_PREFIX . $transient_name, 'clear_request_cache', array( 'cache' => $transient_name ) );
	}

	/**
	 * Get all mime types which could be optimized by Imagify.
	 *
	 * @since  1.0.2
	 * @author Grégory Viguier
	 *
	 * @return array The mime types.
	 */
	public function get_mime_types() {
		if ( function_exists( 'imagify_get_mime_types' ) ) {
			return imagify_get_mime_types();
		}

		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
		);
	}

	/**
	 * Get post statuses related to attachments.
	 *
	 * @since  1.0.2
	 * @author Grégory Viguier
	 *
	 * @return array The post statuses.
	 */
	public function get_post_statuses() {
		static $statuses;

		if ( function_exists( 'imagify_get_post_statuses' ) ) {
			return imagify_get_post_statuses();
		}

		if ( isset( $statuses ) ) {
			return $statuses;
		}

		$statuses = array(
			'inherit' => 'inherit',
			'private' => 'private',
		);

		$custom_statuses = get_post_stati( array( 'public' => true ) );
		unset( $custom_statuses['publish'] );

		if ( $custom_statuses ) {
			$statuses = array_merge( $statuses, $custom_statuses );
		}

		return $statuses;
	}

	/**
	 * Get the number of attachment where the post meta '_wp_attached_file' can't be worked with.
	 *
	 * @since  1.0.2
	 * @author Grégory Viguier
	 *
	 * @return int
	 */
	protected function count_medias_with_invalid_wp_metas() {
		global $wpdb;
		static $transient_value;

		if ( isset( $transient_value ) ) {
			return $transient_value;
		}

		$transient_name  = 'imgt_medias_invalid_wp_metas';
		$transient_value = imagify_tools_get_site_transient( $transient_name );

		if ( false !== $transient_value ) {
			return (int) $transient_value;
		}

		if ( class_exists( 'Imagify_DB', true ) && method_exists( 'Imagify_DB', 'get_required_wp_metadata_where_clause' ) ) {
			$mime_types      = Imagify_DB::get_mime_types();
			$statuses        = Imagify_DB::get_post_statuses();
			$nodata_join     = Imagify_DB::get_required_wp_metadata_join_clause( 'p.ID', false, false );
			$nodata_where    = Imagify_DB::get_required_wp_metadata_where_clause( array(), false, false );
			$transient_value = $wpdb->get_var( // WPCS: unprepared SQL ok.
				"
				SELECT COUNT( p.ID )
				FROM $wpdb->posts AS p
					$nodata_join
				WHERE p.post_mime_type IN ( $mime_types )
					AND p.post_type = 'attachment'
					AND p.post_status IN ( $statuses )
					$nodata_where"
			);
		} else {
			$mime_types = $this->get_mime_types();
			$extensions = implode( '|', array_keys( $mime_types ) );
			$extensions = explode( '|', $extensions );
			$extensions = "OR ( LOWER( imrwpmt1.meta_value ) NOT LIKE '%." . implode( "' AND LOWER( imrwpmt1.meta_value ) NOT LIKE '%.", $extensions ) . "' )";
			$mime_types = esc_sql( $mime_types );
			$mime_types = "'" . implode( "','", $mime_types ) . "'";
			$statuses   = esc_sql( $this->get_post_statuses() );
			$statuses   = "'" . implode( "','", $statuses ) . "'";

			$transient_value = $wpdb->get_var( // WPCS: unprepared SQL ok.
				"
				SELECT COUNT( p.ID )
				FROM $wpdb->posts AS p
				LEFT JOIN $wpdb->postmeta AS imrwpmt1
					ON ( p.ID = imrwpmt1.post_id AND imrwpmt1.meta_key = '_wp_attached_file' )
				LEFT JOIN $wpdb->postmeta AS imrwpmt2
					ON ( p.ID = imrwpmt2.post_id AND imrwpmt2.meta_key = '_wp_attachment_metadata' )
				WHERE p.post_mime_type IN ( $mime_types )
					AND p.post_type = 'attachment'
					AND p.post_status IN ( $statuses )
					AND ( imrwpmt2.meta_value IS NULL OR imrwpmt1.meta_value IS NULL OR imrwpmt1.meta_value LIKE '%://%' OR imrwpmt1.meta_value LIKE '_:\\\\\%' $extensions )"
			);
		}

		imagify_tools_set_site_transient( $transient_name, $transient_value, self::CACHE_DURATION * MINUTE_IN_SECONDS );

		return $transient_value;
	}

	/**
	 * Get the number of "custom files" that have no folder.
	 *
	 * @since  1.0.2
	 * @author Grégory Viguier
	 *
	 * @return int|bool The number of files. False if the tables are not ready.
	 */
	protected function count_orphan_files() {
		global $wpdb;
		static $transient_value;

		if ( isset( $transient_value ) ) {
			return $transient_value;
		}

		$folders_can_operate = class_exists( 'Imagify_Folders_DB', true ) && Imagify_Folders_DB::get_instance()->can_operate();
		$files_can_operate   = class_exists( 'Imagify_Files_DB', true ) && Imagify_Files_DB::get_instance()->can_operate();

		if ( ! $folders_can_operate || ! $files_can_operate ) {
			$transient_value = false;
			return $transient_value;
		}

		$transient_name  = 'imgt_orphan_files';
		$transient_value = imagify_tools_get_site_transient( $transient_name );

		if ( false !== $transient_value ) {
			return (int) $transient_value;
		}

		$folders_db      = Imagify_Folders_DB::get_instance();
		$folders_table   = $folders_db->get_table_name();
		$folders_key     = $folders_db->get_primary_key();
		$folders_key_esc = esc_sql( $folders_key );

		$files_db      = Imagify_Files_DB::get_instance();
		$files_table   = $files_db->get_table_name();
		$files_key_esc = esc_sql( $files_db->get_primary_key() );
		$folder_ids    = $wpdb->get_col( "SELECT $folders_key_esc FROM $folders_table" ); // WPCS: unprepared SQL ok.

		if ( $folder_ids ) {
			$folder_ids = $folders_db->cast_col( $folder_ids, $folders_key );
			$folder_ids = Imagify_DB::prepare_values_list( $folder_ids );

			$transient_value = (int) $wpdb->get_var( "SELECT COUNT( $files_key_esc ) FROM $files_table WHERE folder_id NOT IN ( $folder_ids )" ); // WPCS: unprepared SQL ok.
		} else {
			$transient_value = (int) $wpdb->get_var( "SELECT COUNT( $files_key_esc ) FROM $files_table" ); // WPCS: unprepared SQL ok.
		}

		imagify_tools_set_site_transient( $transient_name, $transient_value, self::CACHE_DURATION * MINUTE_IN_SECONDS );

		return $transient_value;
	}

	/**
	 * Get (and cache) the Imagify user.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @return object|string
	 */
	protected function get_imagify_user() {
		static $imagify_user;

		if ( ! function_exists( 'get_imagify_user' ) ) {
			return __( 'Needs Imagify to be installed', 'imagify-tools' );
		}

		if ( isset( $imagify_user ) ) {
			return $imagify_user;
		}

		$imagify_user = imagify_tools_get_site_transient( 'imgt_user' );

		if ( ! $imagify_user ) {
			$imagify_user = get_imagify_user();
			imagify_tools_set_site_transient( 'imgt_user', $imagify_user, self::CACHE_DURATION * MINUTE_IN_SECONDS );
		}

		return $imagify_user;
	}

	/**
	 * Get the link to clear a cache (delete the transient).
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  string $transient_name Name of the transient that stores the data.
	 * @param  string $clear_action   Admin post action.
	 * @param  array  $args           Parameters to add to the link URL.
	 * @return string
	 */
	protected function get_clear_cache_link( $transient_name, $clear_action, $args = array() ) {
		$link = ' <a class="imgt-button imgt-button-ternary imgt-button-mini" href="' . esc_url( $this->get_clear_cache_url( $clear_action, $args ) ) . '">' . __( 'Clear cache', 'imagify-tools' ) . '</a>';

		$transient_timeout = $this->get_transient_timeout( $transient_name );
		$current_time      = time();

		if ( ! $transient_timeout || $transient_timeout < $current_time ) {
			$time_diff = self::CACHE_DURATION;
		} else {
			$time_diff = $transient_timeout - $current_time;
			$time_diff = ceil( $time_diff / MINUTE_IN_SECONDS );
		}

		/* translators: %d is a number of minutes. */
		return $link .= ' <span class="imgt-small-info">(' . sprintf( _n( 'cache cleared in less than %d minute', 'cache cleared in less than %d minutes', $time_diff, 'imagify-tools' ), $time_diff ) . ')</span>';
	}

	/**
	 * Get the URL to clear a cache (delete the transient).
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  string $action Admin post action.
	 * @param  array  $args   Parameters to add to the link URL.
	 * @return string
	 */
	protected function get_clear_cache_url( $action, $args = array() ) {
		$action = IMGT_Admin_Post::get_action( $action );
		$url    = wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Tell if the site uses SSL.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @return bool
	 */
	protected function is_ssl() {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) { // WPCS: sanitization ok.
			return true;
		}
		if ( preg_match( '@^https://@', admin_url( 'admin-ajax.php' ) ) ) {
			return true;
		}
		return is_ssl();
	}

	/**
	 * Sanitize some data.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  mixed $data The data to sanitize.
	 * @return mixed
	 */
	protected function sanitize( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( $this, 'sanitize' ), $data );
		}

		if ( is_object( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data->$k = $this->sanitize( $v );
			}
			return $data;
		}

		$data = wp_unslash( $data );

		if ( is_numeric( $data ) ) {
			return $data + 0;
		}

		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		return $data;
	}

	/**
	 * Get the value of a site transient timeout expiration.
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 *
	 * @param  string $transient Transient name. Expected to not be SQL-escaped.
	 * @return int               Expiration time in seconds.
	 */
	protected function get_transient_timeout( $transient ) {
		return (int) get_site_option( '_site_transient_timeout_' . $transient );
	}

	/**
	 * Transform an "octal" integer to a "readable" string like "0644".
	 *
	 * Reminder:
	 * `$perm = fileperms( $file );`
	 *
	 *  WHAT                                         | TYPE   | FILE   | FOLDER |
	 * ----------------------------------------------+--------+--------+--------|
	 * `$perm`                                       | int    | 33188  | 16877  |
	 * `substr( decoct( $perm ), -4 )`               | string | '0644' | '0755' |
	 * `substr( sprintf( '%o', $perm ), -4 )`        | string | '0644' | '0755' |
	 * `$perm & 0777`                                | int    | 420    | 493    |
	 * `decoct( $perm & 0777 )`                      | string | '644'  | '755'  |
	 * `substr( sprintf( '%o', $perm & 0777 ), -4 )` | string | '644'  | '755'  |
	 *
	 * @since  1.0
	 * @author Grégory Viguier
	 * @source SecuPress
	 *
	 * @param  int $int An "octal" integer.
	 * @return string
	 */
	protected function to_octal( $int ) {
		return substr( '0' . decoct( $int ), -4 );
	}
}
