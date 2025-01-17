<?php
/**
 * Plugin Name: Xeroom - Woocommerce to Xero Accounts Synch
 * Plugin URI:  https://www.xeroom.com/
 * Description: Enables sales orders and associated data to be automatically posted to Xero to create new sales invoices, credit notes and applied payments. This avoids rekeying of data and enables up-to-date, accurate, easy bookkeeping and accounting.
 * Version:     3.1.4
 * Author:      Peter Lloyd
 * Author URI:  https://www.xeroom.com/
 */

defined( 'ABSPATH' ) || exit;

/**
 * Xeroom Define Http Path
 */
define( 'XEROOM_HTTP_PATH', plugin_dir_url( __FILE__ ) );
/**
 * Xeroom Define Root Path
 */
define( 'XEROOM_ROOT_PATH', plugin_dir_path( __FILE__ ) );
/**
 * Xeroom Define CSS Path
 */
define( 'XEROOM_CSS_PATH', XEROOM_HTTP_PATH . 'css/' );
/**
 * Xeroom Define JS Path
 */
define( 'XEROOM_JS_PATH', XEROOM_HTTP_PATH . 'js/' );
/**
 * Xeroom Define Image Path
 */
define( 'XEROOM_IMAGE_PATH', XEROOM_HTTP_PATH . 'images/' );
/**
 * Xeroom Define Log Path
 */
define( 'XEROOM_LOG_PATH', XEROOM_ROOT_PATH . 'library/log/' );
/**
 * Xeroom Define Excel Path
 */
define( 'XEROOM_EXCEL_PATH', XEROOM_ROOT_PATH . 'library/excel/' );

$plugin_data = array();
if ( is_admin() ) {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$plugin_data = get_plugin_data( __FILE__ );
}

if ( ! defined( 'XEROOM_PLUGIN_FILE' ) ) {
	define( 'XEROOM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'XEROOM_PLUGIN_URL' ) ) {
	define( 'XEROOM_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
}

if ( ! defined( 'XEROOM_PLUGIN_PATH' ) ) {
	define( 'XEROOM_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! defined( 'XEROOM_TYPE' ) ) {
	global $wpdb;

	$xeroLicActive     = $wpdb->prefix . 'xeroom_license_key_status';
	$sql               = 'SELECT * FROM ' . $xeroLicActive . ' WHERE id=1';
	$xeroLicensekeyAct = $wpdb->get_results( $sql );

	if( $xeroLicensekeyAct ) {
		define( 'XEROOM_TYPE', $xeroLicensekeyAct[0]->xero_method );
	} else {
		define( 'XEROOM_TYPE', 'lite' );
	}
}

if ( ! defined( 'XEROOM_V' ) ) {
	define( 'XEROOM_V', 'd8e92ac14b9375b25a585a647b19d8c891c46267c9a3599611c1ad0a7a3f320d' );
}

if ( ! defined( 'XEROOM_VERSION' ) ) {
	define( 'XEROOM_VERSION', ! empty( $plugin_data ) ? $plugin_data['Version'] : '' );
}

if ( ! defined( 'XEROOM_ST' ) ) {
	if('lite' === XEROOM_TYPE || '' === XEROOM_TYPE ) {
		define( 'XEROOM_ST', array(
			'data' => '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',
			'iv' => '+CyxU6KvR/gsq4MIzUD39g=='
		) );
	} else {
		define( 'XEROOM_ST', array(
			'data' => 'TBwQYpJaE82302SkZiWvrQ',
			'iv'   => 'BXfLkctpq+pD57b7IhpGfA=='
		) );
	}
}

/**
 * Check for WooCommerce.
 */
function xeroom_check_for_woocommerce() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}

	if ( is_multisite() ) {
		if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
			$woo_need = is_plugin_active_for_network( 'woocommerce/woocommerce.php' );

			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				$woo_need = true;
			}
		} else {
			$woo_need = is_plugin_active( 'woocommerce/woocommerce.php' );
		}
		// this plugin runs on a single site.
	} else {
		$woo_need = is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	return $woo_need;
}

if ( ! xeroom_check_for_woocommerce() ) {
	add_action(
		'admin_notices',
		function () {
			$install_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'install-plugin',
						'plugin' => 'woocommerce',
					),
					admin_url( 'update.php' )
				),
				'install-plugin_woocommerce'
			);
			/* translators: Notice message */
			$admin_notice_content = sprintf( esc_html__( '%1$sXeroom is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for Self Service Dashboard to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s', 'xeroom' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>', '<a href="' . esc_url( $install_url ) . '">', '</a>' );
			/* translators: Notice HTML */
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', wp_kses_post( $admin_notice_content ) );
		}
	);

	return;
}

include_once( 'index-file.php' );

register_activation_hook( __FILE__, 'xeroom_master_install' );
register_uninstall_hook( 'uninstall.php', 'xeroom_master_uninstall' );

add_filter( 'plugin_row_meta', 'xeroom_plugin_row_meta', 10, 2 );
/**
 * @param $links
 * @param $file
 *
 * @return array
 */
function xeroom_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}

	$row_meta = array(
		'support'      => '<a href="' . esc_url( apply_filters( 'xeroom_support_url', 'https://www.xeroom.com/support/' ) ) . '" aria-label="' . esc_attr__( 'Xeroom support', 'xeroom' ) . '">' . esc_html__( 'Support', 'xeroom' ) . '</a>',
		'view-details' => sprintf( '<a href="%s" class="thickbox" title="%s">%s</a>',
			self_admin_url( 'plugin-install.php?tab=plugin-information&amp;plugin=xeroom&amp;TB_iframe=true&amp;width=600&amp;height=550' ),
			esc_attr( sprintf( __( 'More information about %s' ), 'Xeroom' ) ),
			__( 'View Details' )
		),
	);

	return array_merge( $links, $row_meta );
}

add_filter( 'plugins_api', 'xeroom_plugin_info', 20, 3 );
/**
 * @param $res
 * @param $action
 * @param $args
 *
 * @return false|stdClass
 */
function xeroom_plugin_info( $res, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return false;
	}

	$plugin_slug = 'xeroom';

	// do nothing if it is not our plugin
	if ( $plugin_slug !== $args->slug ) {
		return false;
	}

	// trying to get from cache first
	if ( false == $remote = get_transient( 'xeroom_update_' . $plugin_slug ) ) {
		// xeroom.json is the file with the actual plugin information on your server
		$remote = wp_remote_get( 'https://www.xeroom.com/wp-content/uploads/xeroom.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
			set_transient( 'xeroom_update_' . $plugin_slug, $remote, 43200 ); // 12 hours cache
		}
	}

	if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
		$remote = json_decode( $remote['body'] );
		$res    = new stdClass();

		$res->name           = 'Xeroom';
		$res->slug           = $plugin_slug;
		$res->version        = $remote->version;
		$res->tested         = $remote->tested;
		$res->requires       = $remote->requires;
		$res->author         = '<a href="https://www.xeroom.com/">Xeroom</a>';
		$res->author_profile = 'https://www.xeroom.com/';
		$res->download_link  = xeroom_lincese_status() ? $remote->download_url : '';
		$res->trunk          = xeroom_lincese_status() ? $remote->download_url : '';
		$res->requires_php   = '7.2';
		$res->last_updated   = $remote->last_updated;
		$res->sections       = array(
			'description'  => $remote->sections->description,
			'installation' => $remote->sections->installation,
			'changelog'    => $remote->sections->changelog,
		);

		$res->banners = array(
			'low'  => 'https://www.xeroom.com/wp-content/uploads/xeroom_hero_diagram.png',
			'high' => 'https://www.xeroom.com/wp-content/uploads/xeroom_hero_diagram.png',
		);

		return $res;
	}

	return false;
}

add_filter( 'pre_set_site_transient_update_plugins', 'xeroom_push_update', 50 );
add_filter( 'site_transient_update_plugins', 'xeroom_push_update', 50 );
/**
 * @param $transient
 *
 * @return mixed
 */
function xeroom_push_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	if ( false == $remote = get_transient( 'xeroom_update_xeroom' ) ) {
		// info.json is the file with the actual plugin information on your server
		$remote = wp_remote_get( 'https://www.xeroom.com/wp-content/uploads/xeroom.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
			set_transient( 'xeroom_update_xeroom', $remote, 43200 ); // 12 hours cache
		}
	}

	if ( $remote && ! is_wp_error( $remote ) ) {
		if ( ! empty( $remote['body'] ) ) {
            $plugin_data = get_plugin_data( __FILE__ );
            $plugin_version = $plugin_data['Version']; 
			$remote = json_decode( $remote['body'] );
			// your installed plugin version should be on the line below!
			if ( ! is_wp_error( $remote ) && $remote && version_compare( $plugin_version, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<' ) ) {
				$res                                 = new stdClass();
				$res->slug                           = 'xeroom';
				$res->plugin                         = 'xeroom/xeroom.php';
				$res->new_version                    = $remote->version;
				$res->tested                         = $remote->tested;
				$res->package                        = xeroom_lincese_status() ? $remote->download_url : '';
				$transient->response[ $res->plugin ] = $res;
				$transient->checked[ $res->plugin ]  = $remote->version;
			}
		}
	}

	return $transient;
}

add_action( 'upgrader_process_complete', 'xeroom_after_update', 10, 2 );
/**
 * @param $upgrader_object
 * @param $options
 */
function xeroom_after_update( $upgrader_object, $options ) {
	if ( $options['action'] == 'update' && $options['type'] === 'plugin' ) {
		delete_transient( 'xeroom_update_xeroom' );
	}
}

/**
 * Check license status
 *
 * @return bool
 */
function xeroom_lincese_status() {
	global $wpdb;

	$active              = false;
	$xero_License_key    = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'xeroom_license_key_status WHERE id=1' );
	$xero_plugin_license = sanitize_text_field( $xero_License_key[0]->status );
	if ( ! empty( $xero_plugin_license ) && 'active' === $xero_plugin_license ) {
		$active = true;
	}

	return $active;
}

add_action( 'in_plugin_update_message-xeroom/xeroom.php', 'xeroom_update_message', 10, 2 );
/**
 * @param $plugin_info_array
 * @param $plugin_info_object
 */
function xeroom_update_message( $plugin_info_array, $plugin_info_object ) {
	if ( empty( $plugin_info_array['package'] ) ) {
		echo esc_html( ' Your licence has expired - Please repurchase, add your new licence key to Xeroom settings and reactivate before upgrading.' );
	}
}

/**
 * Compatible with HPOS
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * @param $array
 * @param $key
 *
 * @return array
 */
function xeroom_write_array( $array, $key ) {
	$jsonString = json_encode( $array );

	$iv   = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
	$data = openssl_encrypt( $jsonString, 'aes-256-cbc', $key, 0, $iv );

	return [
		'data' => $data,
		'iv'   => base64_encode( $iv ),
	];
}

/**
 * @param $data
 * @param $key
 *
 * @return mixed|null
 */
function xeroom_read_array( $data, $key ) {
	$iv   = base64_decode( $data['iv'] );
	$data = openssl_decrypt( $data['data'], 'aes-256-cbc', $key, 0, $iv );

	return json_decode( $data, true );
}

/**
 * Increment invoice counter
 *
 * @return int
 */
function increment_invoice_counter() {
	$count = get_transient( 'xeroom_daily_invoice_count' );
	if ( $count === false ) {
		$count = 0;
	}
	$count ++;
	set_transient( 'xeroom_daily_invoice_count', $count, DAY_IN_SECONDS );

	return $count;
}

/**
 * Increment invoice counter
 *
 * @return int
 */
function increment_credit_note_counter() {
	$count = get_transient( 'xeroom_daily_credit_note_count' );
	if ( $count === false ) {
		$count = 0;
	}
	$count ++;
	set_transient( 'xeroom_daily_credit_note_count', $count, DAY_IN_SECONDS );

	return $count;
}

/**
 * Check if invoice can be posted to Xero
 *
 * @return bool
 */
function can_post_invoice_to_xero() {
	$limit = get_option( 'xeroom_daily_invoice_limit', 50 );
	$count = get_transient( 'xeroom_daily_invoice_count' );
    
	if ( false === $count ) {
		$count = 0;
	}
    
    if ( 0 === $limit ) {
		return false;
	}

	return $count < $limit;
}

/**
 * Check if invoice can be posted to Xero
 *
 * @return bool
 */
function can_post_credit_note_to_xero() {
	$limit = get_option( 'xeroom_daily_invoice_limit', 50 );
	$count = get_transient( 'xeroom_daily_credit_note_count' );
    
	if ( false === $count ) {
		$count = 0;
	}
    
    if ( 0 === $limit ) {
		return false;
	}

	return $count < $limit;
}

if ( ! wp_next_scheduled( 'xeroom_reset_daily_invoice_counter' ) ) {
	wp_schedule_event( time(), 'daily', 'xeroom_reset_daily_invoice_counter' );
}

add_action( 'xeroom_reset_daily_invoice_counter', 'xeroom_reset_invoice_counter' );

/**
 * Reset invoice counter
 */
function xeroom_reset_invoice_counter() {
	delete_transient( 'xeroom_daily_invoice_count' );
    delete_transient( 'xeroom_daily_credit_note_count' );
}

/**
 * The maximum number of orders that can be placed at once.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! can_post_invoice_to_xero() ) {
			/* translators: Notice message */
			$admin_notice_invoice_content = esc_html__( 'Number of Orders sent daily reached.' );
			/* translators: Notice HTML */
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', wp_kses_post( $admin_notice_invoice_content ) );
		}

		if ( ! can_post_credit_note_to_xero() ) {
			/* translators: Notice message */
			$admin_cn_invoice_content = esc_html__( 'Number of Credit Notes sent daily reached.' );
			/* translators: Notice HTML */
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', wp_kses_post( $admin_cn_invoice_content ) );
		}
	}
);
