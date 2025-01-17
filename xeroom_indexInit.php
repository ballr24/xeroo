<?php

use Automattic\WooCommerce\Utilities\OrderUtil;
use GuzzleHttp\Client;
use GuzzleHttp\json_encode;

/**
 * Load Xeroom Process
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;


if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}

if ( is_plugin_active_for_network( 'xeroom/xeroom.php' ) ) {
	/**
	 * Is Network Activated
	 */
	define( 'XERO_PLUGIN_NETWORK_ACTIVATED', true );
} else {
	/**
	 * Activated on Single Blog
	 */
	define( 'XERO_PLUGIN_NETWORK_ACTIVATED', false );
}

/**
 * Get Option
 *
 * @param $option_name
 *
 * @return mixed|void
 */
function get_xero_option( $option_name ) {
	if ( true === XERO_PLUGIN_NETWORK_ACTIVATED ) {
		return get_site_option( $option_name );
	} else {
		return get_option( $option_name );
	}
}

/**
 * Update Option
 *
 * @param $option_name
 * @param $option_value
 *
 * @return bool
 */
function update_xero_option( $option_name, $option_value ) {
	if ( true === XERO_PLUGIN_NETWORK_ACTIVATED ) {
		return update_site_option( $option_name, $option_value );
	} else {
		return update_option( $option_name, $option_value );
	}
}

/**
 * Delete Option
 *
 * @param $option_name
 *
 * @return bool
 */
function delete_xero_option( $option_name ) {
	if ( true === XERO_PLUGIN_NETWORK_ACTIVATED ) {
		return delete_site_option( $option_name );
	} else {
		return delete_option( $option_name );
	}
}

/**
 * Add Xeroom Menu Items
 */
function xeroom_menu_icon_under_admin() {
	global $wpdb;
	$xeroLicActive     = $wpdb->prefix . "xeroom_license_key_status";
	$sql               = "SELECT * FROM " . $xeroLicActive . " WHERE id=1";
	$xeroLicensekeyAct = $wpdb->get_results( $sql );

	if ( $xeroLicensekeyAct ) {
		$active     = sanitize_text_field( $xeroLicensekeyAct[0]->status );
		$lic_key    = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );
		$lic_method = sanitize_text_field( $xeroLicensekeyAct[0]->xero_method );

		$icon = 'xeroom_admin_menu_icon.png';
		if ( 'lite' !== XEROOM_TYPE ) {
			$icon = 'favicon-32x32.png';
		}

		add_menu_page( 'Add Xero API', 'Xeroom', 'manage_options', 'add_xero_api_fields', 'xeroom_api_setting', XEROOM_IMAGE_PATH . $icon, 71 );
		add_submenu_page( 'add_xero_api_fields', 'Settings', 'Settings', 'manage_options', 'add_xero_api_fields' );
		add_submenu_page( 'add_xero_api_fields', 'Debug Page', 'Debug Page', 'administrator', 'xeroom_debug_page', 'xeroom_debug_page' );
		if ( $active == 'active' && 'lite' !== XEROOM_TYPE ) {
			add_submenu_page( 'add_xero_api_fields', 'Bulk Export to Xero', 'Bulk Export to Xero', 'administrator', 'xeroom_export_woo_xero', 'xeroom_export_setting' );
			add_submenu_page( 'add_xero_api_fields', 'Log Files', 'Log Files', 'administrator', 'xeroom_log_woo_xero', 'xeroom_log_setting' );
		}
	} else {
		add_action( 'admin_notices', function () {
			$class   = 'notice notice-error';
			$message = __( 'The Xeroom plugin install failed!', 'xeroom' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		} );
	}
}

/**
 * Display Admin message if the Xeroom licence is not set or is not active
 */
function xeroom_display_message_if_licence_not_valid() {
	$currentScreen = get_current_screen();

	if ( 'toplevel_page_add_xero_api_fields' != $currentScreen->id ) {
		$active = get_xero_option( 'xero_connection_status' );
		if ( $active != 'active' ) {
			$licenseErrMessage = get_xero_option( 'xero_connection_status_message' );
			$license_status    = get_xero_option( 'xero_license_status' );
			if ( ! empty( $licenseErrMessage ) && ! $license_status ) {
				add_action( 'admin_notices', function () use ( $licenseErrMessage ) {
					$class = 'notice notice-error';

					printf( '<div class="%1$s"><p><b>%2$s:</b> %3$s</p></div>', esc_attr( $class ), 'Xeroom', esc_html( $licenseErrMessage ) );
				} );
			}
		}
	}

	$fetch_tax_method = get_xero_option( 'xero_tax_method' );
	if ( $fetch_tax_method && 'xero_complex_tax' == $fetch_tax_method ) {
		$xero_taxes_association = get_xero_option( 'xero_taxes_association' );
		$xeroTime               = date( 'Y-m-d H:i:s' );

		if ( ! $xero_taxes_association ) {
			$message = __( 'Taxes have not been associated to Xero Tax Names. Please review your tax settings and enter the corresponding Xero Tax Names.', 'xeroom' );

			add_action( 'admin_notices', function () use ( $message ) {
				$class = 'notice notice-error';

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			} );

//			returnErrorMessageByXero( 0, $message, $xeroTime, '' );
		} else {
			$empty = false;
			foreach ( $xero_taxes_association as $ket => $val ) {
				if ( empty( $val ) ) {
					$empty = true;
					break;
				}
			}

			if ( $empty ) {
				$message = __( 'Some taxes have not been associated to Xero Tax Names. Please review your tax settings and enter the corresponding Xero Tax Names.', 'xeroom' );

				add_action( 'admin_notices', function () use ( $message ) {
					$class = 'notice notice-error';

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
				} );

//				returnErrorMessageByXero( 0, $message, $xeroTime, '' );
			}
		}
	}

}

/**
 * Display Xeroom API Settings
 */
function xeroom_api_setting() {
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_api_setting.php' );
}

/**
 * Display Xeroom Debug Page
 */
function xeroom_debug_page() {
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_debug_page.php' );
}

/**
 * Display Xeroom Export Page
 */
function xeroom_export_setting() {
	require_once( XEROOM_ROOT_PATH . 'library/Classes/vendor/autoload.php' );
	require_once( XEROOM_ROOT_PATH . 'library/Classes/excel_reader.php' );
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_export_import_functions.php' );
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_export_setting.php' );
}

/**
 * Display Xeroom Logs Page
 */
function xeroom_log_setting() {
	require_once( XEROOM_ROOT_PATH . 'library/Classes/vendor/autoload.php' );
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_export_import_functions.php' );
	require_once( XEROOM_ROOT_PATH . 'library/xeroom_log_setting.php' );
}

/**
 * Xeroom Order Duplicate
 *
 * @param $order_id
 *
 * @return int
 */
function xeroom_order_duplicate( $order_id ) {
	global $wpdb;
	$xeroExportTable = $wpdb->prefix . 'xeroom_orders_export';
	$sql             = "SELECT `order_id` FROM `" . $xeroExportTable . "` WHERE `order_id` = " . $order_id;
	$ordersListArr   = $wpdb->get_results( $sql );
	if ( count( $ordersListArr ) > 0 ) {
		return 1;
	} else {
		return 0;
	}
}

add_action( 'wp_ajax_exportDataWooToXero', 'export_woo_to_xero_data' );
/**
 * Export WooCommerce data to Xero
 */
function export_woo_to_xero_data() {
	global $wpdb;

	$xeroExportTable = $wpdb->prefix . 'xeroom_orders_export';
	$xeroomLogExport = $wpdb->prefix . 'xeroom_export_log';
	$xeroom_orderId  = $_REQUEST['orderId'];
	$xeroom_Ignore   = $_REQUEST['ignore'];
	$xeroom_Payment  = $_REQUEST['payment'];
	$spin_time       = $_REQUEST['spinTime'];

	$response = "";
	if ( $spin_time == 1 ) {
		$wpdb->get_results( "TRUNCATE $xeroomLogExport" );
	}
	if ( $xeroom_Ignore == "No repeat" ) {
		if ( xeroom_order_duplicate( $xeroom_orderId ) == 0 ) {
			$response .= xeroom_sendWooInvoiceToXero( $xeroom_orderId, 'manually' );
			if ( $xeroom_Payment == 'Paid' ) {
				$response .= xeroom_paymentDoneOnCheckout( $xeroom_orderId, 'manually' );
			}
		} else {
			$response .= "Order id " . $xeroom_orderId . " already sent to xero account.";
		}
		if ( xeroom_order_duplicate( $xeroom_orderId ) == 0 ) {
			$wpdb->insert( $xeroExportTable, array( 'order_id' => intval( $xeroom_orderId ), 'status' => sanitize_text_field( $xeroom_Payment ) ) );
		}
		$wpdb->insert( $xeroomLogExport,
			array(
				'order_id'     => intval( $xeroom_orderId ),
				'payment_type' => sanitize_text_field( $xeroom_Payment ),
				'ignore_type'  => sanitize_text_field( $xeroom_Ignore ),
				'status'       => sanitize_text_field( $response )
			) );
		wp_die();
	} else if ( $xeroom_Ignore == "Yes repeat" ) {
		$response .= xeroom_sendWooInvoiceToXero( $xeroom_orderId, 'manually' );
		if ( $xeroom_Payment == 'Paid' ) {
			$response .= xeroom_paymentDoneOnCheckout( $xeroom_orderId, 'manually' );
		}
		if ( xeroom_order_duplicate( $xeroom_orderId ) == 0 ) {
			$wpdb->insert( $xeroExportTable, array( 'order_id' => intval( $xeroom_orderId ), 'status' => sanitize_text_field( $xeroom_Payment ) ) );
		}
		$wpdb->insert( $xeroomLogExport,
			array(
				'order_id'     => intval( $xeroom_orderId ),
				'payment_type' => sanitize_text_field( $xeroom_Payment ),
				'ignore_type'  => sanitize_text_field( $xeroom_Ignore ),
				'status'       => sanitize_text_field( $response )
			) );
		wp_die();
	} else {
		$wpdb->insert( $xeroomLogExport,
			array(
				'order_id'     => intval( $xeroom_orderId ),
				'payment_type' => sanitize_text_field( $xeroom_Payment ),
				'ignore_type'  => sanitize_text_field( $xeroom_Ignore ),
				'status'       => sanitize_text_field( 'Ignore type is wrong, it must be "Yes repeat" or "No repeat".' )
			) );
		wp_die();
	}
}

add_action( 'wp_ajax_unlinkxeroomlog', 'unlink_xeroom_log_files' );
/**
 * Unlink Log Files
 */
function unlink_xeroom_log_files() {
	unlink( XEROOM_ROOT_PATH . 'library/log/' . $_REQUEST['fileName'] );
}

add_action( 'wp_ajax_unlinkxeroomexcel', 'unlink_xeroom_excel_files' );
/**
 * Unlink Excel Files
 */
function unlink_xeroom_excel_files() {
	unlink( XEROOM_EXCEL_PATH . 'upload_this_data.xlsx' );
}

/**
 * Create Xeroom tables and add Default Settings on plugin activation
 */
function xeroom_master_install() {
	global $wpdb;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$xeroDebugTable  = $wpdb->prefix . 'xeroom_debug';
	$xeroTaxTable    = $wpdb->prefix . 'xeroom_tax';
	$xeroTable       = $wpdb->prefix . 'xeroom_credentials';
	$licenseTable    = $wpdb->prefix . 'xeroom_license_key_status';
	$xeroomOrderExp  = $wpdb->prefix . 'xeroom_orders_export';
	$xeroomLogExport = $wpdb->prefix . 'xeroom_export_log';

	$query         = "SELECT count(*) as tblExist FROM information_schema.TABLES WHERE (TABLE_SCHEMA = '" . DB_NAME . "') AND (TABLE_NAME = '" . $xeroTable . "')";
	$getTableCount = $wpdb->get_results( $query );
	$CreateTabele  = $getTableCount[0]->tblExist;

	if ( $CreateTabele == 0 ) {
		$wpdb->query( "DROP TABLE IF EXISTS $xeroDebugTable" );
		$wpdb->query( "DROP TABLE IF EXISTS $xeroTaxTable" );
		$wpdb->query( "DROP TABLE IF EXISTS $xeroTable" );
		$wpdb->query( "DROP TABLE IF EXISTS $licenseTable" );
		$wpdb->query( "DROP TABLE IF EXISTS $xeroomOrderExp" );
		$wpdb->query( "DROP TABLE IF EXISTS $xeroomLogExport" );

		$xeroDebugQuery = "CREATE TABLE IF NOT EXISTS $xeroDebugTable(
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`order_id` int(11) NOT NULL,
							`debug` text NOT NULL,
							`created_date` datetime NOT NULL,
							PRIMARY KEY (`id`)
							)";
		dbDelta( $xeroDebugQuery );

		$xeroTaxQuery = "CREATE TABLE IF NOT EXISTS $xeroTaxTable(
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `tax_name` varchar(255) NOT NULL,
							  `tax_rate` varchar(255) NOT NULL,
							  `tax_type` varchar(255) NOT NULL,
							  PRIMARY KEY (`id`)
							)";
		dbDelta( $xeroTaxQuery );

		$xeroQuery = "CREATE TABLE IF NOT EXISTS $xeroTable (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`xero_api_key` varchar(255) NOT NULL,
						`xero_api_secret` varchar(255) NOT NULL,
						`sales_account` varchar(255) NOT NULL,
						`bank_code` varchar(255) NOT NULL,
						`tax_code` varchar(255) NOT NULL,
						`asset_code` varchar(255) NOT NULL,
						`sold_code` varchar(255) NOT NULL,
						`stock_master` varchar(255) NOT NULL,
						`product_master` varchar(255) NOT NULL,
						`payment_master` varchar(255) NOT NULL,
						 PRIMARY KEY (`id`)
						)";
		dbDelta( $xeroQuery );

		$xeroomExpQuery = "CREATE TABLE IF NOT EXISTS $xeroomOrderExp (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `order_id` int(11) NOT NULL,
						  `status` varchar(255) NOT NULL,
							PRIMARY KEY (`id`)
						)";
		dbDelta( $xeroomExpQuery );

		$xeroomLogQuery = "CREATE TABLE IF NOT EXISTS $xeroomLogExport (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`order_id` int(11) NOT NULL,
							`payment_type` varchar(255) NOT NULL,
							`ignore_type` varchar(255) NOT NULL,
							`status` text NOT NULL,
							PRIMARY KEY (`id`)
						)";
		dbDelta( $xeroomLogQuery );

		$licenseQuery = "CREATE TABLE IF NOT EXISTS $licenseTable (
						  `id` int(11) NOT NULL AUTO_INCREMENT,
						  `license_key` varchar(255) NOT NULL,
						  `status` varchar(255) NOT NULL,
						  `xero_method` varchar(255) NOT NULL,
							PRIMARY KEY (`id`)
						)";
		dbDelta( $licenseQuery );

		if ( empty( $wpdb->last_error ) ) {
			$wpdb->insert( $xeroTable,
				array(
					'xero_api_key'    => "",
					'xero_api_secret' => "",
					'sales_account'   => 200,
					'bank_code'       => 100,
					'tax_code'        => '20% (VAT on Income)',
					'asset_code'      => 617,
					'sold_code'       => 310,
					'stock_master'    => 'n',
					'product_master'  => 'w',
					'payment_master'  => 'n'
				)
			);
			$wpdb->insert( $licenseTable,
				array(
					'license_key' => '123456789',
					'status'      => "deactive",
					'xero_method' => "lite"
				)
			);
		} else {
			add_action( 'admin_notices', function () {
				$class   = 'notice notice-error';
				$message = esc_html__( 'The Xeroom installation failed! Please contact Xeroom support for assistance.', 'xeroom' );

				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			} );
		}
	}

	if ( ! get_xero_option( 'xero_default_invoice_status' ) ) {
		update_xero_option( 'xero_default_invoice_status', 'AUTHORISED' );
	}
	if ( ! get_xero_option( 'xero_use_extra_sales_account' ) ) {
		update_xero_option( 'xero_use_extra_sales_account', 'none' );
	}
	if ( ! get_xero_option( 'xero_generate_credit_note' ) ) {
		update_xero_option( 'xero_generate_credit_note', 0 );
	}
	if ( ! get_xero_option( 'xero_credit_note_status' ) ) {
		update_xero_option( 'xero_credit_note_status', 0 );
	}
	if ( ! get_xero_option( 'xero_tax_method' ) ) {
		update_xero_option( 'xero_tax_method', 'xero_simple_tax' );
	}
	if ( ! get_xero_option( 'xero_invoice_date' ) ) {
		update_xero_option( 'xero_invoice_date', 'order_date' );
	}
	if ( ! get_xero_option( 'xero_send_invoice_method' ) ) {
		update_xero_option( 'xero_send_invoice_method', 'manually' );
	}
	if ( ! get_xero_option( 'xero_set_invoice_duedate' ) ) {
		update_xero_option( 'xero_set_invoice_duedate', 'use_woo_due_date' );
	}
	if ( ! get_xero_option( 'xero_send_payment_method' ) ) {
		update_xero_option( 'xero_send_payment_method', 'manually' );
	}
	if ( ! get_xero_option( 'xero_contact_name' ) ) {
		update_xero_option( 'xero_contact_name', 'xeroom_use_first_name' );
	}
	if ( ! get_xero_option( 'xero_address_info' ) ) {
		update_xero_option( 'xero_address_info', 'xeroom_use_woo_address' );
	}

	if ( ! get_xero_option( 'xero_invoice_delivery_address' ) ) {
		update_xero_option( 'xero_invoice_delivery_address', 0 );
	}

	if ( ! get_xero_option( 'xero_generate_payment_refund' ) ) {
		update_xero_option( 'xero_generate_payment_refund', 1 );
	}

	if ( ! get_xero_option( 'xero_email_invoice' ) ) {
		update_xero_option( 'xero_email_invoice', 'xeroom_none' );
	}

	if ( ! get_xero_option( 'xero_default_shipping_costs_code' ) ) {
		update_xero_option( 'xero_default_shipping_costs_code', 207 );
	}

	if ( ! get_xero_option( 'xeroom_rounding_account' ) ) {
		update_xero_option( 'xeroom_rounding_account', 860 );
	}

	if ( ! get_xero_option( 'xero_woo_gateway' ) ) {
		update_xero_option( 'xero_woo_gateway', array( 'xero_default_payment' => 100 ) );
	}

	if ( ! get_xero_option( 'sync_stock_data' ) ) {
		$sync_data = array(
			'sync_master'     => 'w',
			'sync_schedule'   => 'n',
			'batch_sync_size' => 100,
		);
		update_xero_option( 'sync_stock_data', $sync_data );
	}

	if ( ! get_xero_option( 'sync_product_data' ) ) {
		$sync_prod_data = array(
			'sync_prod_master'        => 'w',
			'what_to_update'          => 'p',
			'sync_prod_schedule'      => 'n',
			'batch_product_sync_size' => 100,
		);

		update_xero_option( 'sync_product_data', $sync_prod_data );
	}

	wp_mkdir_p( XEROOM_LOG_PATH );

	flush_rewrite_rules();
}

/**
 * Exclude Orders with 0 total value
 *
 * @param $order_id
 *
 * @return bool
 */
function xeroom_exclude_order_with_zero_value( $order_id ) {
	$order      = new WC_Order( $order_id );
	$totalOrder = 1;
	if ( $order ) {
		$totalOrder = absint( $order->get_total() );
	}

	if ( get_xero_option( 'xeroom_exclude_zero_value' ) && 0 === $totalOrder ) {
		return true;
	}

	return false;
}

/**
 * Return Xero Error Message
 *
 * @param $order_id
 * @param $errD
 * @param $xeroTime
 * @param $errorType
 */
function returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType ) {
	if ( $errorType == "manually" ) {
		return;
	} else {
		global $wpdb;
		$recordErrorArray = "";
		$xeroDebugTable   = $wpdb->prefix . 'xeroom_debug';

		if ( is_string( $errD ) ) {
			if ( strpos( $errD, 'oauth_problem' ) !== false ) {
				if ( is_array( $errD ) ) {
					$errD = 'Error while connecting to Xero API. Technical details: ' . print_r( $errD, true );
				} else {
					$errD = 'Error while connecting to Xero API. Technical details: ' . $errD;
				}
				update_xero_option( 'xero_connection_status_message', $errD );
				update_xero_option( 'xero_connection_status', 'failed' );
			}
		} elseif ( is_array( $errD ) ) {
			$errD = print_r( $errD, true );
		}

		$wpdb->insert( $xeroDebugTable,
			array(
				'order_id'     => intval( $order_id ),
				'debug'        => sanitize_text_field( $errD ),
				'created_date' => $xeroTime
			)
		);
	}
}

/**
 * @param $email
 * @param $domains
 *
 * @return bool
 */
function is_restricted_email_domain( $email, $domains ) {
	$pattern = preg_quote( $domains, '/' );
	$pattern = str_replace( '\*', '.*', $pattern );

	if ( preg_match( '/^' . $pattern . '$/i', $email ) ) {
		return true;
	}

	return false;
}

/**
 * Send data to Xero after successfully checkout
 *
 * @param $order_id
 * @param string $errorType
 *
 * @return string
 */
function xeroom_sendWooInvoiceToXero( $order_id, $errorType = "orderProduct" ) {
	if ( ! $order_id ) {
		return;
	}

	if ( ! can_post_invoice_to_xero() ) {
		return esc_html( 'Number of Orders sent daily reached. To increase limit go to Xeroom settings.' );
	}

	global $wpdb;
	global $product;
	global $order;

	$order    = new WC_Order( intval( $order_id ) );
	$country  = new WC_Countries();
	$orderTax = new WC_Tax();

	if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
		return;
	}

	$order_type = wc_get_order( $order_id );
	if ( defined( 'AWCDP_POST_TYPE' ) && $order_type->get_type() == AWCDP_POST_TYPE ) {
		return;
	}

	//define Tables
	$exportErrorXero = $xero_send_error = "";
	$xeroDebugTable  = $wpdb->prefix . 'xeroom_debug';
	$xeroLicActive   = $wpdb->prefix . "xeroom_license_key_status";
	$taxTableName    = $wpdb->prefix . "xeroom_tax";
	$xeroTime        = date( 'Y-m-d H:i:s' );

	if ( xeroom_exclude_order_with_zero_value( $order_id ) ) {
		returnErrorMessageByXero( $order_id, __( 'The Order total is zero and the Exclude sending orders of zero value option is active.' ), $xeroTime, $errorType );

		return;
	}

	$billing_email            = $order->get_billing_email();
	$ebay_and_amazon_settings = get_xero_option( 'ebay_and_amazon_settings' );
	$saved_emails_list        = get_xero_option( 'xeroom_emails_lists' );

	if ( $ebay_and_amazon_settings && $saved_emails_list ) {
		$saved_emails_list = explode( ',', $saved_emails_list );
		if ( is_array( $saved_emails_list ) ) {
			foreach ( $saved_emails_list as $defined_email ) {
				if ( is_restricted_email_domain( $billing_email, $defined_email ) ) {
					echo $the_message = 'The email ' . $billing_email . ' is restricted in eBay and Amazon settings';
					returnErrorMessageByXero( $order_id, $the_message, $xeroTime, $errorType );

					return;
				}
			}
		} else {
			if ( is_restricted_email_domain( $billing_email, $saved_emails_list ) ) {
				echo $the_message = 'The email ' . $billing_email . ' is restricted in eBay and Amazon settings';
				returnErrorMessageByXero( $order_id, $the_message, $xeroTime, $errorType );

				return;
			}
		}
	}

	//License Key
	$sql               = "SELECT * FROM " . $xeroLicActive . " WHERE id=1";
	$xeroLicensekeyAct = $wpdb->get_results( $sql );

	$api_counter = 0;

	if ( $xeroLicensekeyAct ) {
		$active     = sanitize_text_field( $xeroLicensekeyAct[0]->status );
		$lic_key    = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );
		$lic_method = sanitize_text_field( $xeroLicensekeyAct[0]->xero_method );

		$license_checked = get_xero_option( 'xero_license_status' );

		// Invoice Status
		$default_invoice_status = 'AUTHORISED';
		$get_xero_status_option = get_xero_option( 'xero_default_invoice_status' );
		if ( $get_xero_status_option ) {
			$default_invoice_status = esc_attr( $get_xero_status_option );
		}

		//Check License of plugin
		if ( $active == 'active' ) {
			if ( $license_checked != 'expired' ) {
				//Items Details
				$totalItems    = $order->get_item_count();
				$orderCurency  = $order->get_currency();
				$usedCupons    = "Coupon used " . implode( ", ", $order->get_coupon_codes() );
				$shippingPrice = $order->get_shipping_total();
				$totalDiscount = $order->get_total_discount();
//
				// Prices include tax amount
				$included_tax = false;
				if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) && wc_tax_enabled() ) {
					$included_tax = true;
				}

				// User Address
				$shipAddress     = apply_filters( 'xeroom_shipping_address', $order->get_address(), $order_id, 'invoice' );
				$allCountry      = $country->get_countries();
				$countryFullName = $allCountry[ $shipAddress['country'] ];
				$allState        = $country->get_states( $shipAddress['country'] );
				$stateFullName   = '';
				if ( $allState && $shipAddress['state'] ) {
					$stateFullName = $allState[ $shipAddress['state'] ];
				} else {
					$stateFullName = $order->get_billing_state();
				}

				// Tax Rate Used
				$getTaxRate     = array(
					'country'   => $shipAddress['country'],
					'state'     => $shipAddress['state'],
					'city'      => $shipAddress['city'],
					'postcode'  => $shipAddress['postcode'],
					'tax_class' => ''
				);
				$taxRatePercent = $orderTax->find_rates( $getTaxRate );
				if ( count( $taxRatePercent ) != 0 && wc_tax_enabled() ) {
					foreach ( $taxRatePercent as $taxKey => $taxValue ) {
						$taxValue = $taxValue;
					}
					$taxOnWholeCart = $taxValue;
				} else {
					$taxOnWholeCart = array();
				}

				// Xero Connectivity Api Credentials------------
				$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
				$getApiCredentials = $wpdb->get_results( $query );

				if ( $getApiCredentials ) {
					$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
					$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
					$salesAccount  = sanitize_text_field( $getApiCredentials[0]->sales_account );
					$BankCode      = sanitize_text_field( $getApiCredentials[0]->bank_code );
					$TaxCode       = sanitize_text_field( $getApiCredentials[0]->tax_code );
					$AssetCode     = sanitize_text_field( $getApiCredentials[0]->asset_code );
					$SoldCode      = sanitize_text_field( $getApiCredentials[0]->sold_code );
					$StockMaster   = sanitize_text_field( $getApiCredentials[0]->stock_master );
					$productMaster = sanitize_text_field( $getApiCredentials[0]->product_master );

					$oder_gateway_code = $order->get_payment_method();
					if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
						$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
					}

					$due_date_settings      = get_xero_option( 'xero_set_invoice_duedate' );
					$xero_contact_name      = get_xero_option( 'xero_contact_name' );
					$invoice_start_no       = get_xero_option( 'xero_invoice_start_no' );
					$invoice_next_no        = get_xero_option( 'xero_invoice_next_no' );
					$xeroom_invoice_no_sent = $order->get_meta( 'xeroom_invoice_no_sent' );

					$xero_invoice_no = xeroom_invoice_number( $order_id, $xeroom_invoice_no_sent );

					if ( 'manually-resend-invoice' != $errorType ) {
						if ( xero_invoice_exists( $order_id, $xeroApiKey, $xeroApiSecret ) && 'manually' != $errorType ) {
							return;
						}
					}

					/**
					 * Get shipping cost code
					 */
					$shippingCode = get_xero_option( 'xero_default_shipping_costs_code' );

					/**
					 * Get Use Extra Sales Accounts - Shipping
					 */
					$use_extra_sales_accounts = get_xero_option( 'xero_use_extra_sales_account' );
					if ( $order->get_shipping_methods() && $use_extra_sales_accounts && 'geography_zones' == $use_extra_sales_accounts ) {
						$zone_id = xero_fetch_order_zone_id( $order );
						if ( ! empty( $zone_id ) ) {
							$shipping_associated_code = xeroom_invoice_geography_zone( $zone_id );
							if ( ! empty( $shipping_associated_code ) ) {
								$salesAccount = $shipping_associated_code;
							}
						}
					}

					include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
					if ( ! defined( 'BASE_PATH' ) ) {
						define( 'BASE_PATH', dirname( __FILE__ ) );
					}

					if ( ! defined( 'XERO_KEY' ) ) {
						define( 'XERO_KEY', $xeroApiKey );
					}

					if ( ! defined( 'XERO_SECRET' ) ) {
						define( 'XERO_SECRET', $xeroApiSecret );
					}

					if ( ! defined( 'PUBLIC_KEY' ) ) {
						define( 'PUBLIC_KEY', BASE_PATH . '/library/certs/publickey.cer' );
					}

					if ( ! defined( 'PRIVATE_KEY' ) ) {
						define( 'PRIVATE_KEY', BASE_PATH . '/library/certs/privatekey.pem' );
					}

					if ( ! defined( 'FORMAT' ) ) {
						define( 'FORMAT', 'json' );
					}
					$oauth2 = get_xero_option( 'xero_oauth_options' );
					xeroom_check_xero_token( $oauth2 );
					$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

					$api_counter ++;
					$xero_tracking = $xero->TrackingCategories();
					xeroom_check_connection_message( $xero_tracking, $order_id );

					$active_tracking = array();
					if ( is_array( $xero_tracking ) ) {
						$active_tracking = xeroom_fetch_tracking_categories( $xero_tracking );
					}
					$country_name = WC()->countries->countries[ $order->get_billing_country() ];

					$add_tracking      = $tracking_category_id = '';
					$tracking_category = get_xero_option( 'xero_tracking_category' );
					if ( ! empty( $tracking_category ) ) {
						if ( is_array( $active_tracking ) && array_key_exists( esc_attr( $tracking_category ), $active_tracking ) && in_array( $country_name, $active_tracking[ $tracking_category ] ) ) {
							$add_tracking = $country_name;
						} else {
							$add_tracking = apply_filters( 'xeroom_not_tracking_name', __( 'Not tracked' ) );
						}
					}

					// Xero Connectivity Api Credentials------------
					$ordersList   = $order->get_items();
					$orderDetails = array();
					$oKeyValue    = array();
					$countOrder   = 0;

					$fetch_tax_method           = get_xero_option( 'xero_tax_method' );
					$fetch_saved_tax            = get_xero_option( 'xero_tax_methods' );
					$fetch_taxes_association    = get_xero_option( 'xero_taxes_association' );
					$invoice_prefix             = get_xero_option( 'xero_invoice_prefix' );
					$invoice_reference          = get_xero_option( 'xero_invoice_reference_prefix' );
					$shipping_price_code        = get_xero_option( 'xero_shipping_price_code' );
					$shipping_price_description = get_xero_option( 'xero_shipping_price_description' );
					$xero_show_shipping_details = get_xero_option( 'xero_show_shipping_details' );
					$customer_note              = $order->get_customer_note();

					$xero_shipping_price_code = 'shipping_price';
					if ( $shipping_price_code ) {
						$xero_shipping_price_code = esc_attr( $shipping_price_code );
					}

					if ( $xero_show_shipping_details ) {
						$xero_shipping_price_description = strip_tags( $order->get_shipping_method() );
					} else {
						$xero_shipping_price_description = 'Shipping Price';
						if ( $shipping_price_description ) {
							$xero_shipping_price_description = esc_attr( $shipping_price_description );
						}
					}

					$product_tax        = 'standard';
					$item_tax_rate_id   = '';
					$item_tax_rate_rate = 0;
					$products_ids       = array();
					foreach ( $ordersList as $singleorderskey => $singleorders ) {
						$orderDetails[ $countOrder ]['item_price']      = $order->get_item_total( $singleorders, false, true );
						$orderDetails[ $countOrder ]['item_sale_price'] = $order->get_item_subtotal( $singleorders, false, true );
						$orderDetails[ $countOrder ]['name']            = $singleorders['name'];
						$orderDetails[ $countOrder ]['_product_id']     = $singleorders['product_id'];
						$orderDetails[ $countOrder ]['_variation_id']   = $singleorders['variation_id'];
						$orderDetails[ $countOrder ]['_qty']            = $singleorders['quantity'];

						$orderDetails[ $countOrder ]['apply_discount'] = 0;
						if ( $singleorders->get_subtotal() !== $singleorders->get_total() ) {
//							$orderDetails[ $countOrder ]['item_sale_price'] = wc_format_decimal( $singleorders->get_total(), '' );
							$orderDetails[ $countOrder ]['apply_discount'] = wc_format_decimal( $singleorders->get_subtotal() - $singleorders->get_total(), '' );
						}

						$orderDetails[ $countOrder ]['percentage_line_subtotal']        = $singleorders->get_subtotal(); // Pre-discount subtotal
						$orderDetails[ $countOrder ]['percentage_line_total']           = $singleorders->get_total(); // Post-discount total
						$orderDetails[ $countOrder ]['percentage_line_total_tax']       = $singleorders->get_total_tax(); // Tax amount
						$orderDetails[ $countOrder ]['percentage_line_discount_amount'] = $orderDetails[ $countOrder ]['percentage_line_subtotal'] - $orderDetails[ $countOrder ]['percentage_line_total'];

						if ( $orderDetails[ $countOrder ]['percentage_line_discount_amount'] > 100 ) {
							$orderDetails[ $countOrder ]['percentage_line_discount_amount'] = 100;
						}
                        
						$products_ids[ $singleorders['product_id'] ] = $singleorders['quantity'];

						if ( 0 != $totalDiscount ) {
							$orderDetails[ $countOrder ]['get_subtotal_tax'] = round( $singleorders->get_total_tax(), wc_get_price_decimals() );
						}

						// Fetch Order Item Tax
						if ( $fetch_tax_method && wc_tax_enabled() ) {
							if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
								$simple_tax = $singleorders->get_tax_class();
							} else {
								$simple_tax = get_post_meta( $singleorders['product_id'], '_tax_class', true );
							}

							if ( ! empty( $simple_tax ) ) {
								$product_tax = $simple_tax;
							} else {
								$product_tax = 'standard';
							}

							switch ( $fetch_tax_method ) {
								case "xero_simple_tax" :
									if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $product_tax . '_taxmethods', $fetch_saved_tax ) ) {
										$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
										$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
										$orderDetails[ $countOrder ]['total_tax']     = xero_fetch_tax_complex_amount( $singleorders->get_taxes() );
									}
									break;
								case "xero_complex_tax" :
									$tax_data = $singleorders->get_taxes();

									foreach ( $order->get_taxes() as $tax_item ) {
										$rate_id        = $tax_item->get_rate_id();
										$item_total_tax = isset( $tax_data['total'][ $rate_id ] ) ? $tax_data['total'][ $rate_id ] : '';

										if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
											$item_tax_rate_id = $rate_id;
											break;
										}
									}

									if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {
										$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
										$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
										$orderDetails[ $countOrder ]['total_tax']     = xero_fetch_tax_complex_amount( $singleorders->get_taxes() );
									}
									break;
							}
						}

						if ( 'w' == $productMaster ) {
							if ( wc_tax_enabled() ) {
								if ( ! $fetch_tax_method ) {
									$tax_data         = $singleorders->get_taxes();
									$item_tax_rate_id = $tax_name = '';
									foreach ( $order->get_taxes() as $tax_item ) {
										$rate_id = $tax_item->get_rate_id();

										if ( ! empty( $tax_data['total'][ $rate_id ] ) ) {
											$item_tax_rate_rate = $tax_data['total'][ $rate_id ];
											$tax_name           = xero_fetch_tax_rate_name( $rate_id );
											if ( isset( $item_tax_rate_rate ) ) {
												$item_tax_rate_id = xero_tax_type_code( $tax_name );
											}
										}
									}

									if ( ! empty( $item_tax_rate_id ) ) {
										$orderDetails[ $countOrder ]['tax_class'] = $item_tax_rate_id;
										if ( ! empty( $item_tax_rate_id ) ) {
											$orderDetails[ $countOrder ]['total_tax'] = ! empty( $item_tax_rate_rate ) ? $item_tax_rate_rate : 0;
										}
									}
								}
							} else {
								$orderDetails[ $countOrder ]['tax_class'] = 'NONE';
								$orderDetails[ $countOrder ]['total_tax'] = 0;
							}
						}

						$countOrder ++;
					}

					$oderList  = array();
					$uniqueTax = "";

					// Process each order item and adjust for discounts
					foreach ( $orderDetails as $i => $details ) {
						$item_price       = floatval( $details['item_price'] );
						$item_sale_price  = floatval( $details['item_sale_price'] );
						$qty              = intval( $details['_qty'] );
						$discount_applied = false;

						// Adjust item details if a discount is applied
						if ( $item_price != $item_sale_price && $item_sale_price == 0 ) {
							$discount_applied               = true;
							$oderList[ $i ]['Description']  = $details['name'];
							$oderList[ $i ]['Quantity']     = $qty;
							$oderList[ $i ]['UnitAmount']   = 0;
							$oderList[ $i ]['DiscountRate'] = 100;
							$oderList[ $i ]['ItemCode']     = $details['_product_id'];
						} else {
							$oderList[ $i ]['Description']  = $details['name'];
							$oderList[ $i ]['Quantity']     = $qty;
							$oderList[ $i ]['UnitAmount']   = $item_price;
							$oderList[ $i ]['ItemCode']     = $details['_product_id'];
							$oderList[ $i ]['DiscountRate'] = 0;
						}

						// Add tax Rate name
						if ( isset( $details['tax_class'] ) && '' != $details['tax_class'] ) {
							$oderList[ $i ]['TaxType'] = $details['tax_class'];
							if ( $productMaster == "w" ) {
								$oderList[ $i ]['TaxAmount'] = $details['total_tax'];
							}
						} else {
							$oderList[ $i ]['TaxType'] = 'NONE';
						}

						// Handle 100% discount scenario
						if ( $discount_applied && '100' === $oderList[ $i ]['DiscountRate'] ) {
							unset( $oderList[ $i ]['UnitAmount'] );
						}

						$oderList[ $i ]['AccountCode'] = $salesAccount;
					}

					// Create new tax here
					if ( count( $taxOnWholeCart ) != 0 ) {
						$api_counter ++;
						$getAllTax = $xero->TaxRates();
						xeroom_check_connection_message( $getAllTax, $order_id );
						if ( isset( $taxOnWholeCart['rate'] ) ) {
							$taxRate = $taxOnWholeCart['rate'];
							if ( isset( $getAllTax['TaxRates']['TaxRate'] ) ) {
								foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
									if ( $allTaxesNow['Name'] == $TaxCode ) {
										$uniqueTax      = $allTaxesNow['TaxType'];
										$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
										break;
									}
								}
							}
						}

						if ( $uniqueTax == "" && isset( $getAllTax['TaxRates']['TaxRate'] ) ) {
							foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
								if ( $allTaxesNow['TaxComponents']['TaxComponent']['Rate'] == $taxRate ) {
									$uniqueTax      = $allTaxesNow['TaxType'];
									$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
									break;
								}
							}
						}
						if ( $uniqueTax == "" ) {
							$taxName     = "Default Xeroom Sales Tax";
							$query       = "SELECT * FROM `" . $taxTableName . "` WHERE `tax_rate` ='" . $taxRate . "'";
							$getTaxRates = $wpdb->get_results( $query );
							if ( count( $getTaxRates ) > 0 ) {
								$uniqueTax = $getTaxRates[0]->tax_type;
							} else {
								$xero_rate = array(
									"TaxRate" => array(
										"Name"          => "$taxName",
										"ReportTaxType" => 'OUTPUT',
										"TaxComponents" => array(
											"TaxComponent" => array(
												"Name" => "VAT",
												"Rate" => $taxRate
											)
										)
									)
								);
								$taxResult = $xero->TaxRates( $xero_rate );
								$api_counter ++;
								if ( ! empty( $taxResult ) ) {
									if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
										$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
										returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
										$exportErrorXero .= $errD;
									} else if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
										$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
										for ( $er = 0; $er < count( $errD ); $er ++ ) {
											$errorMessage = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $er ]['Message'];
											returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
											$exportErrorXero .= $errorMessage;
										}
									} else if ( isset( $taxResult['Status'] ) && $taxResult['Status'] == "OK" ) {
										$uniqueTax = $taxResult['TaxRates']['TaxRate']['TaxType'];
										$wpdb->insert( $taxTableName,
											array(
												'tax_name' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['Name'] ),
												'tax_rate' => sanitize_text_field( $taxRate ),
												'tax_type' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['TaxType'] )
											)
										);
										update_xero_option( 'xero_connection_status', 'active' );
										update_xero_option( 'xero_connection_status_message', '' );
									} else {
										returnErrorMessageByXero( $order_id, $taxResult, $xeroTime, $errorType );
										$exportErrorXero .= $taxResult;
									}
								}
							}
						}
					}


					// Get items by sku
					$where_skus = array();
					for ( $i = 0; $i < count( $orderDetails ); $i ++ ) {
						if ( isset( $orderDetails[ $i ]['_variation_id'] ) && $orderDetails[ $i ]['_variation_id'] != 0 ) {
							$unitPrice = get_post_meta( $orderDetails[ $i ]['_variation_id'] );
						} else {
							$unitPrice = get_post_meta( $orderDetails[ $i ]['_product_id'] );
						}

						if ( isset( $unitPrice['_sku'][0] ) && $unitPrice['_sku'][0] != "" ) {
							$sku = $unitPrice['_sku'][0];
						} else {
							$sku = $orderDetails[ $i ]['_product_id'];
						}
						$where_skus[] = xeroom_reduce_sku_length( $sku );
					}

					/**
					 * Get products with SKU Code
					 */
					$oauth2 = get_xero_option( 'xero_oauth_options' );
					$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

					$config->setHost( "https://api.xero.com/api.xro/2.0" );

					$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
						new GuzzleHttp\Client(),
						$config
					);

					$xero_tenant_id = $oauth2['tenant_id'];
					$query_string   = 'Code=="' . implode( '" OR Code=="', $where_skus ) . '"';

					$xero_item_data = array();
					try {
						$xero_result_item = $apiInstance->getItems( $xero_tenant_id, null, $query_string );
						if ( $xero_result_item ) {
							foreach ( $xero_result_item as $data_item ) {
								$sku_code = $data_item->getCode();

								$xero_item_data[ $sku_code ]['Code']                               = $sku_code;
								$xero_item_data[ $sku_code ]['Name']                               = $data_item->getName();
								$xero_item_data[ $sku_code ]['Description']                        = $data_item->getDescription();
								$xero_item_data[ $sku_code ]['PurchaseDescription']                = $data_item->getPurchaseDescription();
								$xero_item_data[ $sku_code ]['ItemID']                             = $data_item->getItemId();
								$xero_item_data[ $sku_code ]['QuantityOnHand']                     = $data_item->getQuantityOnHand();
								$xero_item_data[ $sku_code ]['SalesDetails']['UnitPrice']          = $data_item->getSalesDetails()['unit_price'];
								$xero_item_data[ $sku_code ]['SalesDetails']['AccountCode']        = $data_item->getSalesDetails()['account_code'];
								$xero_item_data[ $sku_code ]['SalesDetails']['TaxType']            = $data_item->getSalesDetails()['tax_type'];
								$xero_item_data[ $sku_code ]['PurchaseDetails']['UnitPrice']       = $data_item->getPurchaseDetails()['unit_price'];
								$xero_item_data[ $sku_code ]['PurchaseDetails']['AccountCode']     = $data_item->getPurchaseDetails()['account_code'];
								$xero_item_data[ $sku_code ]['PurchaseDetails']['COGSAccountCode'] = $data_item->getPurchaseDetails()['cogs_account_code'];
							}
						}
					} catch ( Exception $e ) {
						echo 'Exception when calling AccountingApi->getItems: ', $e->getMessage(), PHP_EOL;
					}
					$api_counter ++;

					// End Create new tax here
					$xero_invoice_items = array();
					$xero_items_stock   = array();
					for ( $i = 0; $i < count( $orderDetails ); $i ++ ) {
						/**
						 * Add product to associated category
						 */
						if ( $use_extra_sales_accounts && 'product_categories' == $use_extra_sales_accounts ) {
							$product_categories = fetch_product_category_id( $orderDetails[ $i ]['_product_id'] );

							$category_associated_account = xeroom_product_category_associated( $product_categories );
							if ( ! empty( $category_associated_account ) ) {
								$salesAccount = $category_associated_account;
							}
						}

						$allProductAttr = "";
						if ( isset( $orderDetails[ $i ]['_variation_id'] ) && $orderDetails[ $i ]['_variation_id'] != 0 ) {
							$unitPrice = get_post_meta( $orderDetails[ $i ]['_variation_id'] );
							$itemId    = $orderDetails[ $i ]['_variation_id'];
							$product   = wc_get_product( $orderDetails[ $i ]['_variation_id'] );

							if ( ! empty( $product->get_attribute_summary() ) ) {
								$allProductAttr .= ', ' . $product->get_attribute_summary();
							}

							if ( empty( $product->get_attribute_summary() ) ) {
								$productAttributes = get_post_meta( $orderDetails[ $i ]['_product_id'], '_product_attributes' );

								if ( $productAttributes ) {
									foreach ( $productAttributes[0] as $key => $value ) {
										$attributeName  = get_taxonomy( $value['name'] );
										$attributeName  = $attributeName->labels->name;
										$slugValue      = get_term_by( 'slug', $attributeName->name, $value['name'] );
										$allProductAttr .= ', ' . $attributeName . ': ' . $slugValue->name;
									}


								}
							}

						} else {
							$unitPrice = get_post_meta( $orderDetails[ $i ]['_product_id'] );
							$itemId    = $orderDetails[ $i ]['_product_id'];
							$product   = wc_get_product( $orderDetails[ $i ]['_product_id'] );
						}

						if ( isset( $unitPrice['_sku'][0] ) && $unitPrice['_sku'][0] != "" ) {
							$sku = $unitPrice['_sku'][0];
						} else {
							$sku = $orderDetails[ $i ]['_product_id'];
						}
						if ( isset( $unitPrice['_sale_price'][0] ) && $unitPrice['_sale_price'][0] != "" ) {
							$unitPriceNow = $unitPrice['_sale_price'][0];
							if ( $allProductAttr == "" ) {
								$allProductAttr = ', Full Price: ' . $unitPrice['_regular_price'][0];
							} else {
								$allProductAttr .= ', Full Price: ' . $unitPrice['_regular_price'][0];
							}
						} else {
							$unitPriceNow = $unitPrice['_regular_price'][0];
						}

						$sku = xeroom_reduce_sku_length( $sku );

						$unitPriceNow = $orderDetails[ $i ]['item_price'];
						$unitPriceNow = number_format( (float) $unitPriceNow, 2, '.', '' );
						if ( $allProductAttr != "" ) {
							$descriptionName = $orderDetails[ $i ]['name'] . $allProductAttr;
							if ( $productMaster == "w" ) {
								$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'] . $allProductAttr;
							}
						} else {
							$descriptionName = $orderDetails[ $i ]['name'];
							if ( $productMaster == "w" ) {
								$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'];
							}
						}

						$oderList[ $i ]['Quantity'] = $orderDetails[ $i ]['_qty'];
						if ( $productMaster == "w" || $productMaster == "n" ) {
							$oderList[ $i ]['UnitAmount'] = $unitPriceNow;
						}
						$oderList[ $i ]['ItemCode'] = $sku;

						if ( $uniqueTax != "" ) {
							$oderList[ $i ]['TaxType'] = $uniqueTax;
						}

						// Add tax Rate name
						if ( isset( $orderDetails[ $i ]['tax_class'] ) && '' != $orderDetails[ $i ]['tax_class'] ) {
							$oderList[ $i ]['TaxType'] = $orderDetails[ $i ]['tax_class'];
							if ( $productMaster == "w" ) {
								$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['total_tax'];
							}
						} else {
							$oderList[ $i ]['TaxType'] = 'NONE';
						}

						if ( isset( $orderDetails[ $i ]['get_subtotal_tax'] ) && 0 == $orderDetails[ $i ]['get_subtotal_tax'] && class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
							$oderList[ $i ]['TaxType'] = 'NONE';
						}

						// Apply discount rate.
						if ( 0 != $totalDiscount ) {
							$percentage        = 0;
							$coupon_percentage = 0;
							foreach ( $order->get_coupon_codes() as $coupon_name ) {
								if ( ! $coupon_name ) {
									continue;
								}
								$coupons_obj = new WC_Coupon( $coupon_name );

								if ( 'percent' === $coupons_obj->get_discount_type() || 'recurring_percent' === $coupons_obj->get_discount_type() ) {
									$coupon_percentage = $coupons_obj->get_amount();
									$item_subtotal     = number_format( (float) $orderDetails[ $i ]['item_sale_price'], 2, '.', '' );
									if ( 0 == $item_subtotal ) {
										$percentage = 0;
									} else {
										$percentage = ( ( $item_subtotal - number_format( (float) $orderDetails[ $i ]['item_price'], 2, '.', '' ) ) / $item_subtotal ) * 100;
									}
                                    
                                    if ( $percentage > 100 ) {
											$percentage = 100;
										}
//									$percentage    += round( ( ( $item_subtotal - number_format( (float) $orderDetails[ $i ]['item_price'], 2, '.', '' ) ) / $item_subtotal ) * 100 );
								} else {
									$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['item_price'];
								}

							}

							$unitPriceNow = $orderDetails[ $i ]['item_sale_price'];

							if ( $productMaster == "w" || $productMaster == "n" ) {
								$oderList[ $i ]['UnitAmount'] = $unitPriceNow;
								if ( $orderDetails[ $i ]['apply_discount'] > 0 && empty( $order->get_coupon_codes() ) ) {
									$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['get_subtotal_tax'];

									// Add the discount
									$percent                        = ( $orderDetails[ $i ]['apply_discount'] / ( round( $unitPriceNow * $oderList[ $i ]['Quantity'], 2 ) ) ) * 100;
									$oderList[ $i ]['DiscountRate'] = $percent;
								}
							}

							if ( $percentage > 0 ) {
								$oderList[ $i ]['DiscountRate'] = number_format( (float) $orderDetails[ $i ]['percentage_line_discount_amount'], 2, '.', '' );
								$oderList[ $i ]['TaxAmount']    = number_format( (float) $orderDetails[ $i ]['percentage_line_total_tax'], 2, '.', '' );
                                if( $orderDetails[ $i ]['percentage_line_total'] > 0 ) {
                                    $oderList[ $i ]['LineAmount']   = number_format( (float) $orderDetails[ $i ]['percentage_line_total'], 2, '.', '' );
                                }
							}
						}

						// Add tracking
						$saved_categories = get_xero_option( 'xero_tracking_categories' );
						if ( ! empty( $saved_categories ) ) {
							foreach ( $saved_categories as $key => $categories ) {
								$item_category = get_post_meta( $orderDetails[ $i ]['_product_id'], '_tracking_category_' . str_replace( ' ', '_', $key ), true );
								$item_category = apply_filters( 'xeroom_invoice_item_tracking_category', $item_category, $orderDetails[ $i ] );
								if ( ! empty( $item_category ) ) {
									$oderList[ $i ]['Tracking'][]['TrackingCategory'] = array(
										'Name'   => esc_attr( $key ),
										'Option' => esc_attr( $item_category ),
									);
								}
							}
						} else {
							if ( ! empty( $add_tracking ) ) {
								$oderList[ $i ]['Tracking'] = array(
									'TrackingCategory' => array(
										'Name'   => esc_attr( $tracking_category ),
										'Option' => $add_tracking,
									),
								);
							}
						}

						$oderList[ $i ]['_product_id'] = $orderDetails[ $i ]['_product_id'];

						$saved_product_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_product_account', true );
						if ( $saved_product_account ) {
							$oderList[ $i ]['AccountCode'] = $saved_product_account;
						} else {
							$oderList[ $i ]['AccountCode'] = $salesAccount;
						}

						$saved_cost_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_cost_account', true );
						if ( $saved_cost_account ) {
							$oderList[ $i ]['COGSAccountCode'] = $saved_cost_account;
						} else {
							$oderList[ $i ]['COGSAccountCode'] = $SoldCode;
						}

						$saved_inventory_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_inventory_account', true );
						if ( $saved_inventory_account ) {
							$oderList[ $i ]['InventoryAssetAccountCode'] = $saved_inventory_account;
						} else {
							$oderList[ $i ]['InventoryAssetAccountCode'] = $AssetCode;
						}

						$post_details = get_post( $orderDetails[ $i ]['_product_id'] );
						$xItemName    = xeroom_reduce_item_name_length( $post_details->post_title );
						if ( $xItemName == "" ) {
							$xItemName = "No Name_" . rand();
						}
						$xItemDesc  = $post_details->post_content;
						$xItemStock = $unitPrice['_stock'][0];

						/* Stock Master */
						if ( $StockMaster == "w" ) {
							if ( $xItemStock == 0 || $xItemStock == "" ) {
								if ( isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ) {
									$addNewQuant = $orderDetails[ $i ]['_qty'];

									$increaseItems = array(
										"Invoice" => array(
											"Type"            => "ACCPAY",
											"Contact"         => array(
												"Name" => "Inventory Adjustments"
											),
											"Date"            => date( 'Y-m-d' ),
											"DueDate"         => date( 'Y-m-d' ),
											"LineAmountTypes" => "NoTax",
											"Status"          => $default_invoice_status,
											"LineItems"       => array(
												"LineItem" => array(
													"ItemCode"        => $sku,
													"Description"     => $descriptionName,
													"Quantity"        => $addNewQuant,
													"UnitAmount"      => $unitPriceNow,
													"AccountCode"     => $AssetCode,
													"COGSAccountCode" => $SoldCode,
													"QuantityOnHand"  => $xero_item_data[ $sku ]['QuantityOnHand'],
												)
											)
										)
									);

									// not overwriting Xero data
									if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$increaseItems['Invoice']['LineItems']['LineItem']['Description'] = $xero_desc;
										$increaseItems['Invoice']['LineItems']['LineItem']['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									}

									array_push( $xero_items_stock, $increaseItems );
								} else {
									$createItemWithoutStock = array(
										"Item" => array(
											"Name"                      => $xItemName,
											"Code"                      => $sku,
											"Description"               => $descriptionName,
											"PurchaseDescription"       => $descriptionName,
											"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
											"QuantityOnHand"            => $orderDetails[ $i ]['_qty'],
											"IsSold"                    => true,
											"IsPurchased"               => true,
											"SalesDetails"              => array(
												"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
												"AccountCode" => $oderList[ $i ]['AccountCode'],
												"TaxType"     => $orderDetails[ $i ]['tax_class_new']
											),
											"PurchaseDetails"           => array(
												"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
												"AccountCode" => $oderList[ $i ]['COGSAccountCode']
											)
										)
									);
									if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = $descriptionName;
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$createItemWithoutStock['Item']['Description'] = $xero_desc;
										$createItemWithoutStock['Item']['Name']        = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

										$createItemWithoutStock['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
										$createItemWithoutStock['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
									} else {
										if ( $productMaster == "w" ) {
											$createItemWithoutStock['Item']['PurchaseDetails']['UnitPrice'] = 0;
										}
									}

									array_push( $xero_invoice_items, $createItemWithoutStock['Item'] );

									if ( isset( $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
										$errD = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
										returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
										$exportErrorXero .= $errD;
									} else if ( isset( $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
										$errD = array();
										$errD = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
										for ( $e = 0; $e < count( $errD ); $e ++ ) {
											$errorMessage = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
											returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
											$exportErrorXero .= $errorMessage;
										}
									} else {
										update_xero_option( 'xero_connection_status', 'active' );
										update_xero_option( 'xero_connection_status_message', '' );
									}

									$oderList[ $i ]['Description'] = $descriptionName;
									if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$oderList[ $i ]['Description'] = $xero_desc;
										$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									}
								}
							} else {
								$newItemsCode = array(
									"Item" => array(
										"Code"                      => $sku,
										"Name"                      => $xItemName,
										"Description"               => $descriptionName,
										"PurchaseDescription"       => $xItemName,
										"IsTrackedAsInventory"      => false,
										"IsSold"                    => true,
										"IsPurchased"               => true,
										"QuantityOnHand"            => $xItemStock,
										"TotalCostPool"             => $xItemStock * $unitPriceNow,
										"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
										"SalesDetails"              => array(
											"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
											"AccountCode" => $oderList[ $i ]['AccountCode'],
											"TaxType"     => $orderDetails[ $i ]['tax_class_new']
										),
										"PurchaseDetails"           => array(
											"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
											"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
										)
									)
								);

								if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
									$xero_desc = '-';
									if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
										$xero_desc = $xero_item_data[ $sku ]['Description'];
									}
									$newItemsCode['Item']['Description']                  = $xero_desc;
									$newItemsCode['Item']['PurchaseDescription']          = '' != $xero_item_data[ $sku ]['PurchaseDescription'] ? $xero_item_data[ $sku ]['PurchaseDescription'] : $xItemName;
									$newItemsCode['Item']['Name']                         = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;
									$newItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
								} else {
									if ( $productMaster == "w" ) {
										$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
									}
								}

								$itemQuantity = $xero_item_data[ $sku ]['QuantityOnHand'];
								if ( $itemQuantity > $xItemStock ) {
									$newQuantity = $itemQuantity - $xItemStock;
									$itemAction  = "decrease";
								} else if ( $itemQuantity < $xItemStock ) {
									$newQuantity = $xItemStock - $itemQuantity;
									$itemAction  = "increase";
								}

								if ( $itemAction == "decrease" ) {
									$decreaseItems = array(
										"CreditNote" => array(
											"Type"            => "ACCPAYCREDIT",
											"Contact"         => array(
												"Name" => "Inventory Adjustments"
											),
											"Date"            => date( 'Y-m-d' ),
											"DueDate"         => date( 'Y-m-d' ),
											"LineAmountTypes" => "NoTax",
											"Status"          => $default_invoice_status,
											"LineItems"       => array(
												"LineItem" => array(
													"ItemCode"        => $sku,
													"Description"     => $descriptionName,
													"Quantity"        => $newQuantity,
													"UnitAmount"      => $unitPriceNow,
													"AccountCode"     => $AssetCode,
													"COGSAccountCode" => $SoldCode,
													"QuantityOnHand"  => isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ? $xero_item_data[ $sku ]['QuantityOnHand'] : '',
												)
											)
										)
									);

									if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$decreaseItems['CreditNote']['LineItems']['LineItem']['Description'] = $xero_desc;
										$decreaseItems['CreditNote']['LineItems']['LineItem']['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									}

									array_push( $xero_items_stock, $decreaseItems );
								}

								if ( $itemAction == "increase" ) {
									$increaseItems = array(
										"Invoice" => array(
											"Type"            => "ACCPAY",
											"Contact"         => array(
												"Name" => "Inventory Adjustments"
											),
											"Date"            => date( 'Y-m-d' ),
											"DueDate"         => date( 'Y-m-d' ),
											"LineAmountTypes" => "NoTax",
											"Status"          => $default_invoice_status,
											"LineItems"       => array(
												"LineItem" => array(
													"ItemCode"        => $sku,
													"Description"     => $descriptionName,
													"Quantity"        => $newQuantity,
													"UnitAmount"      => $unitPriceNow,
													"AccountCode"     => $AssetCode,
													"COGSAccountCode" => $SoldCode,
													"QuantityOnHand"  => isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ? $xero_item_data[ $sku ]['QuantityOnHand'] : '',
												)
											)
										)
									);

									if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$increaseItems['Invoice']['LineItems']['LineItem']['Description'] = $xero_desc;
										$increaseItems['Invoice']['LineItems']['LineItem']['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									}

									array_push( $xero_items_stock, $increaseItems );
								}

								array_push( $xero_invoice_items, $newItemsCode['Item'] );
							}
						} else if ( $StockMaster == "x" ) {
							if ( is_array( $xero_item_data[ $sku ] ) ) {
								if ( isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ) {
									$quantityI = $xero_item_data[ $sku ]['QuantityOnHand'];
									$orderPro  = wc_get_product( $itemId );
									/**
									 * Set product Qty. in Woo
									 */
									$newQuantity = $quantityI - $orderDetails[ $i ]['_qty'];
									wc_update_product_stock( $orderPro, $newQuantity );

									if ( $productMaster == "x" ) {
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$oderList[ $i ]['Description'] = $xero_item_data[ $sku ]['Description'];
										}

										if ( $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ) {
											$oderList[ $i ]['UnitAmount'] = $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'];
										}

									}
								} else {
									$simpleItemCode = array(
										"Item" => array(
											"Name"                      => $xItemName,
											"Code"                      => $sku,
											"Description"               => $descriptionName,
											"PurchaseDescription"       => $descriptionName,
											"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
											"SalesDetails"              => array(
												"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
												"AccountCode" => $oderList[ $i ]['AccountCode']
											),
											"PurchaseDetails"           => array(
												"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
												"AccountCode" => $oderList[ $i ]['COGSAccountCode']
											)
										)
									);

									if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$simpleItemCode['Item']['Description'] = $xero_desc;
										$simpleItemCode['Item']['Name']        = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

										$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
										$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
									} else {
										if ( $productMaster == "w" ) {
											$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
										}
									}

									array_push( $xero_invoice_items, $simpleItemCode['Item'] );

									$oderList[ $i ]['Description'] = $descriptionName;
									$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
									if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
										$xero_desc = '-';
										if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
											$xero_desc = $xero_item_data[ $sku ]['Description'];
										}
										$oderList[ $i ]['Description'] = $xero_desc;
										$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									}
								}
							} else {
								$simpleItemCode = array(
									"Item" => array(
										"Name"                      => $xItemName,
										"Code"                      => $sku,
										"Description"               => $descriptionName,
										"PurchaseDescription"       => $descriptionName,
										"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
										"SalesDetails"              => array(
											"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
											"AccountCode" => $oderList[ $i ]['AccountCode']
										),
										"PurchaseDetails"           => array(
											"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
											"AccountCode" => $oderList[ $i ]['COGSAccountCode']
										)
									)
								);

								if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
									$xero_desc = '-';
									if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
										$xero_desc = $xero_item_data[ $sku ]['Description'];
									}
									$simpleItemCode['Item']['Description'] = $xero_desc;
									$simpleItemCode['Item']['Name']        = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

									$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
								} else {
									if ( $productMaster == "w" ) {
										$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
									}
								}

								array_push( $xero_invoice_items, $simpleItemCode['Item'] );

								$oderList[ $i ]['Description'] = $descriptionName;
								$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
								if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
									$xero_desc = '-';
									if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
										$xero_desc = $xero_item_data[ $sku ]['Description'];
									}
									$oderList[ $i ]['Description'] = $xero_desc;
									$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
								}
							}
						} else {
							if ( isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ) {
								$addNewQuant   = $orderDetails[ $i ]['_qty'];
								$increaseItems = array(
									"Invoice" => array(
										"Type"            => "ACCPAY",
										"Contact"         => array(
											"Name" => "Inventory Adjustments"
										),
										"Date"            => date( 'Y-m-d' ),
										"DueDate"         => date( 'Y-m-d' ),
										"LineAmountTypes" => "NoTax",
										"Status"          => $default_invoice_status,
										"LineItems"       => array(
											"LineItem" => array(
												"ItemCode"        => $sku,
												"Description"     => $descriptionName,
												"Quantity"        => $addNewQuant,
												"UnitAmount"      => $unitPriceNow,
												"AccountCode"     => $AssetCode,
												"COGSAccountCode" => $SoldCode,
												"QuantityOnHand"  => isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ? $xero_item_data[ $sku ]['QuantityOnHand'] : '',
											)
										)
									)
								);

								if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
									$xero_desc = '-';
									if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
										$xero_desc = $xero_item_data[ $sku ]['Description'];
									}
									$oderList[ $i ]['Description']                                    = $xero_desc;
									$increaseItems['Invoice']['LineItems']['LineItem']['Description'] = $xero_desc;
									$increaseItems['Invoice']['LineItems']['LineItem']['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
								}

								array_push( $xero_items_stock, $increaseItems );
							} else {
								$simpleItemsCode = array(
									"Item" => array(
										"Name"                      => $xItemName,
										"Code"                      => $sku,
										"Description"               => $descriptionName,
										"PurchaseDescription"       => $descriptionName,
										"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
										"SalesDetails"              => array(
											"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
											"AccountCode" => $oderList[ $i ]['AccountCode'],
											"TaxType"     => $orderDetails[ $i ]['tax_class_new']
										),
										"PurchaseDetails"           => array(
											"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
											"AccountCode" => $oderList[ $i ]['COGSAccountCode']
										)
									)
								);

								if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
									$xero_desc = '-';
									if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
										$xero_desc = $xero_item_data[ $sku ]['Description'];
									}
									$simpleItemsCode['Item']['Description'] = $xero_desc;

									$simpleItemsCode['Item']['Name'] = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

									$simpleItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
									$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
									$simpleItemsCode['Item']['PurchaseDescription']          = $xero_item_data[ $sku ]['PurchaseDescription'];
								} else {
									if ( $productMaster == "w" ) {
										$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
									}
								}

								array_push( $xero_invoice_items, $simpleItemsCode['Item'] );
							}
						}

						if ( '100' === $oderList[ $i ]['DiscountRate'] ) {
//                            $oderList[ $i ]['UnitAmount'] = null;
							unset( $oderList[ $i ]['UnitAmount'] );
						}

					}


					/**
					 * Add extra data
					 */
					if ( $order->get_shipping_method() ) {
						$newItemsCodeShip = array(
							"Item" => array(
								"Code"        => $xero_shipping_price_code,
								"Description" => $xero_shipping_price_description
							)
						);
						array_push( $xero_invoice_items, $newItemsCodeShip['Item'] );
					}

					if ( $totalDiscount != 0 ) {
						foreach ( $order->get_coupon_codes() as $coupon_name ) {
							if ( ! $coupon_name ) {
								continue;
							}
							// Get an instance of WC_Coupon object in an array(necesary to use WC_Coupon methods)
							$coupons_obj = new WC_Coupon( $coupon_name );

							$coupon_description = esc_html__( 'Cart Fixed Discount Coupon' );
							if ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
								$coupon_description = esc_html__( 'Product Fixed Discount coupon' );
							}

							if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
								$coupon_name = esc_html( $coupon_xero_code );
							}

							if ( ! empty( $coupons_obj->get_description() ) ) {
								$coupon_description .= esc_html( $coupons_obj->get_description() );
							}

							$new_discounts_items['Item'] = array(
								"Code"        => xeroom_reduce_coupon_length( $coupon_name ),
								"Description" => $coupon_description
							);

							array_push( $xero_invoice_items, $new_discounts_items['Item'] );
						}
					}

					if ( $order->get_items( array( 'fee' ) ) ) {
						$newItemsCodeDis = array(
							"Item" => array(
								"Code"        => "fee_price",
								"Description" => "Fee Price"
							)
						);

						array_push( $xero_invoice_items, $newItemsCodeDis['Item'] );
					}

					if ( ! empty( trim( $customer_note ) ) && xeroom_add_order_notes() ) {
						$newItemsCodeNote = array(
							"Item" => array(
								"Code"        => "NOTE",
								"Description" => "Invoice Note"
							)
						);
						array_push( $xero_invoice_items, $newItemsCodeNote['Item'] );
					}

					if ( ! empty( trim( $order->get_formatted_shipping_address() ) ) ) {
						$newItemsCodeS = array(
							"Item" => array(
								"Code"        => "SHIPPING ADDRESS",
								"Description" => "Shipping Address"
							)
						);
						array_push( $xero_invoice_items, $newItemsCodeS['Item'] );
					}

					if ( $order->get_payment_method() == 'purchase_order_gateway' && $order->get_meta( '_purchase_order_number' ) ) {
						$newPurchaseOrder = array(
							"Item" => array(
								"Code"        => "PO-NO",
								"Description" => "Purchase Orders"
							)
						);
						array_push( $xero_invoice_items, $newPurchaseOrder['Item'] );
					}

					/**
					 * Send Invoice Items as Batch Start
					 */
					xeroom_send_batch_items_before_invoice( $xero_invoice_items );

					/**
					 * Send Invoice Items as Batch Start
					 */
					xeroom_send_batch_stock_for_items_before_invoice( $xero_items_stock );

					/**
					 * Send Invoice Items as Batch Start
					 */

					// Fetch shippinh tax class
					$item_tax_rate_id = '';
					$shipping_tax     = array();
					if ( $fetch_tax_method && wc_tax_enabled() ) {
						$shipping_tax_class = 'standard';
						$tax_data           = array();
						foreach ( $order->get_items( array( 'shipping' ) ) as $item ) {
							if ( '' != $item->get_tax_class() ) {
								$shipping_tax_class = $item->get_tax_class();
							}
							$tax_data = $item->get_taxes();
						}

						$simple_tax = $order->get_shipping_tax();

						if ( $simple_tax ) {
							$product_tax = $simple_tax;
						} else {
							$product_tax = 'standard';
						}

						switch ( $fetch_tax_method ) {
							case "xero_simple_tax" :
								if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $shipping_tax_class . '_taxmethods', $fetch_saved_tax ) ) {
									$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $shipping_tax_class . '_taxmethods' ] );
								}
								break;
							case "xero_complex_tax" :
								foreach ( $order->get_taxes() as $tax_item ) {
									$rate_id        = $tax_item->get_rate_id();
									$item_total_tax = isset( $tax_data['total'][ $rate_id ] ) ? $tax_data['total'][ $rate_id ] : '';

									if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
										$item_tax_rate_id = $rate_id;
										break;
									}
								}

								if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {
									$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
								}
								break;
						}

						if ( 0 == $simple_tax ) {
							$shipping_tax['tax_class'] = 'NONE';
						}

						if ( 'inherit' == $shipping_tax_class ) {
							$shipping_class = reset( $orderDetails );
							if ( $shipping_class['tax_class'] ) {
								$shipping_tax['tax_class'] = $shipping_class['tax_class'];
							} else {
								$shipping_tax['tax_class'] = 'NONE';
							}

						}

					} else {
						$shipping_tax['tax_class'] = 'NONE';
					}

					if ( $order->get_shipping_method() ) {
						$oderList[ count( $orderDetails ) ]['Description'] = $xero_shipping_price_description;
						$oderList[ count( $orderDetails ) ]['Quantity']    = 1;
						$oderList[ count( $orderDetails ) ]['UnitAmount']  = $shippingPrice;
						$oderList[ count( $orderDetails ) ]['ItemCode']    = $xero_shipping_price_code;

						if ( ! empty( $shipping_tax ) ) {
							$oderList[ count( $orderDetails ) ]['TaxType'] = $shipping_tax['tax_class'];
						}

						$oderList[ count( $orderDetails ) ]['AccountCode'] = $shippingCode && '' != $shippingCode ? $shippingCode : $salesAccount;

						// Add tracking
						if ( ! empty( $add_tracking ) ) {
							$shipping_tracking_category = get_xero_option( 'xero_shipping_tracking_category' );
							if ( $shipping_tracking_category ) {
								$oderList[ count( $orderDetails ) ]['Tracking'] = array(
									'TrackingCategory' => array(
										'Name'   => esc_attr( $shipping_tracking_category ),
										'Option' => $add_tracking,
									),
								);
							} else {
								$oderList[ count( $orderDetails ) ]['Tracking'] = array(
									'TrackingCategory' => array(
										'Name'   => esc_attr( $tracking_category ),
										'Option' => $add_tracking,
									),
								);
							}
						}
					}

					$remove_tax_amount = 0;
					if ( $lic_method != "" ) {
						if ( $totalDiscount != 0 ) {
							$products_tax_type         = reset( $orderDetails );
							$discount_tax['tax_class'] = $products_tax_type['tax_class'];

							if ( $order->get_shipping_method() ) {
								$nextCount = count( $orderDetails ) + 1;
							} else {
								$nextCount = count( $orderDetails );
							}

							$coupon_quantity = 0;

							foreach ( $order->get_coupon_codes() as $coupon_name ) {
								if ( ! $coupon_name ) {
									continue;
								}
								$coupons_obj = new WC_Coupon( $coupon_name );

								$coupon_order_id = wpr_fetch_coupon_item_id( $order_id, $coupon_name );

								$order_discount_amount     = wc_get_order_item_meta( $coupon_order_id, 'discount_amount', true );
								$order_discount_tax_amount = wc_get_order_item_meta( $coupon_order_id, 'discount_amount_tax', true );

//								$oderList[ $nextCount ]['UnitAmount'] = 0;
								$oderList[ $nextCount ]['Quantity'] = 1;
								if ( $coupons_obj->get_discount_type() == 'fixed_cart' ) {
									$remove_tax_amount                     = 1;
									$oderList[ $nextCount ]['Description'] = esc_html__( 'Cart Fixed Discount coupon' );
									if ( $included_tax ) {
										$oderList[ $nextCount ]['UnitAmount'] = - $order_discount_amount;
										$oderList[ $nextCount ]['LineAmount'] = - $order_discount_amount;
										$oderList[ $nextCount ]['TaxAmount']  = - $order_discount_tax_amount;
									} else {
										$oderList[ $nextCount ]['UnitAmount'] = - $coupons_obj->get_amount();
										$oderList[ $nextCount ]['LineAmount'] = - $coupons_obj->get_amount();
										$oderList[ $nextCount ]['TaxAmount']  = - $order_discount_tax_amount;

									}
								} elseif ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
									$remove_tax_amount                     = 1;
									$coupon_applied                        = array_intersect( array_keys( $products_ids ), $coupons_obj->get_product_ids() );
									$oderList[ $nextCount ]['Description'] = esc_html__( 'Fixed product discount' );
									if ( $included_tax ) {
										$oderList[ $nextCount ]['UnitAmount'] = - $order_discount_amount;

									} else {
										$oderList[ $nextCount ]['UnitAmount'] = - $coupons_obj->get_amount();

									}

									if ( count( $coupon_applied ) > 0 ) {
										foreach ( $products_ids as $product_id => $quantity ) {
											if ( in_array( $product_id, $coupons_obj->get_product_ids() ) ) {
												$coupon_quantity += $quantity;
											}
										}

										$oderList[ $nextCount ]['Quantity'] = $coupon_quantity;
									}
								} else {
									$coupon_named_description = esc_html__( 'Cart Percent Discount coupon' );
									if ( ! empty( $coupons_obj->get_description() ) ) {
										$coupon_named_description .= ' (' . esc_html( $coupons_obj->get_description() ) . ')';
									}

									$oderList[ $nextCount ]['Description'] = $coupon_named_description;
								}


								if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
									$coupon_name = esc_html( $coupon_xero_code );
								}

								$oderList[ $nextCount ]['ItemCode'] = xeroom_reduce_coupon_length( $coupon_name );

								// Add tax Rate name
								if ( ! empty( $discount_tax ) ) {
									$oderList[ $nextCount ]['TaxType'] = $discount_tax['tax_class'];
								}

								$oderList[ $nextCount ]['AccountCode'] = $salesAccount;


								// Add tracking
								if ( ! empty( $add_tracking ) && $coupons_obj->get_discount_type() != 'percent' && $coupons_obj->get_discount_type() != 'recurring_percent' ) {
									$oderList[ $nextCount ]['Tracking'] = array(
										'TrackingCategory' => array(
											'Name'   => esc_attr( $tracking_category ),
											'Option' => $add_tracking,
										),
									);
								}

								$nextCount ++;
							}
						}
					}

					// Remove tax ammount if fixed discount
					if ( $remove_tax_amount ) {
						for ( $i = 0; $i < count( $oderList ); $i ++ ) {
							unset( $oderList[ $i ]['TaxAmount'] );
						}
					}

					// Fee tax
					$fee_tax = array();
					if ( $fetch_tax_method && wc_tax_enabled() ) {
						$fee_class            = reset( $orderDetails );
						$fee_tax['tax_class'] = isset( $fee_class['tax_class'] ) ? $fee_class['tax_class'] : '';
					}

					// Add fee if exists
					if ( $order->get_items( array( 'fee' ) ) ) {
						$fee_count = count( $oderList );
						foreach ( $order->get_items( array( 'fee' ) ) as $item_type => $item ) {
							$oderList[ $fee_count ]['Description']  = $item->get_name() ? $item->get_name() : "Fee Price";
							$oderList[ $fee_count ]['Quantity']     = 1;
							$oderList[ $fee_count ]['UnitAmount']   = $order->get_item_total( $item, false, true );
							$oderList[ $fee_count ]['DiscountRate'] = 0;
							$oderList[ $fee_count ]['ItemCode']     = "fee_price";
							if ( ! empty( $fee_tax ) ) {
								$oderList[ $fee_count ]['TaxType'] = $fee_tax['tax_class'];
							}

							$oderList[ $fee_count ]['AccountCode'] = $salesAccount;
							$fee_count ++;
						}
					}

					// Add Invoice new line with Order Note
					if ( ! empty( trim( $customer_note ) ) && xeroom_add_order_notes() ) {
						$next_line                              = count( $oderList );
						$oderList[ $next_line ]['Description']  = $customer_note;
						$oderList[ $next_line ]['Quantity']     = 1;
						$oderList[ $next_line ]['UnitAmount']   = 0;
						$oderList[ $next_line ]['DiscountRate'] = 0;
						$oderList[ $next_line ]['ItemCode']     = "NOTE";
						$oderList[ $next_line ]['AccountCode']  = $salesAccount;
					}


					if ( $order->get_payment_method() == 'purchase_order_gateway' && $order->get_meta( '_purchase_order_number' ) ) {
						$next_po_no_line = count( $oderList );

						$purchase_note = esc_html__( 'Purchase order number: ', 'xeroom' ) . $order->get_meta( '_purchase_order_number' ) . ', ';

						$purchase_note .= ( $order->get_meta( '_purchase_order_company_name' ) ) ? esc_html( $order->get_meta( '_purchase_order_company_name' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_address1' ) ) ? esc_html( $order->get_meta( '_purchase_order_address1' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_address2' ) ) ? esc_html( $order->get_meta( '_purchase_order_address2' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_address3' ) ) ? esc_html( $order->get_meta( '_purchase_order_address3' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_town' ) ) ? esc_html( $order->get_meta( '_purchase_order_town' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_county' ) ) ? esc_html( $order->get_meta( '_purchase_order_county' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_postcode' ) ) ? esc_html( $order->get_meta( '_purchase_order_postcode' ) ) . ', ' : '';
						$purchase_note .= ( $order->get_meta( '_purchase_order_email' ) ) ? esc_html( $order->get_meta( '_purchase_order_email' ) ) . ', ' : '';

						$oderList[ $next_po_no_line ]['Description']  = $purchase_note;
						$oderList[ $next_po_no_line ]['Quantity']     = 1;
						$oderList[ $next_po_no_line ]['UnitAmount']   = 0;
						$oderList[ $next_po_no_line ]['DiscountRate'] = 0;
						$oderList[ $next_po_no_line ]['ItemCode']     = "PO-NO";
						$oderList[ $next_po_no_line ]['AccountCode']  = $salesAccount;
					}


					// Add the shipping address
					$invoice_delivery_address = get_xero_option( 'xero_invoice_delivery_address' );
					if ( ! empty( $order->has_shipping_address() ) && $invoice_delivery_address ) {
						if ( '2' === $invoice_delivery_address || ( '1' === $invoice_delivery_address && $order->get_billing_address_1() !== $order->get_shipping_address_1() ) ) {
							$next__s_line                              = count( $oderList );
							$oderList[ $next__s_line ]['Description']  = str_replace( '<br/>', ', ', $order->get_formatted_shipping_address() );
							$oderList[ $next__s_line ]['Quantity']     = 1;
							$oderList[ $next__s_line ]['UnitAmount']   = 0;
							$oderList[ $next__s_line ]['DiscountRate'] = 0;
							$oderList[ $next__s_line ]['ItemCode']     = "SHIPPING ADDRESS";
							$oderList[ $next__s_line ]['AccountCode']  = $salesAccount;
						}
					}

					for ( $i = 0; $i < count( $oderList ); $i ++ ) {
						$lineItemsArray[] = array( "LineItem" => array( $oderList[ $i ] ) );
					}

					$currentDate = xeroom_invoice_date( $order );

					if ( 'xeroom_use_company' == $xero_contact_name ) {
						$xero_add_contact_name = $shipAddress['company'];
					} elseif ( 'xeroom_use_email' == $xero_contact_name ) {
						$xero_add_contact_name = $shipAddress['email'];
					} else {
						$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
					}

					if ( empty( $xero_add_contact_name ) ) {
						$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
					}

					$transaction_info = '';
					if ( $order->get_transaction_id() ) {
						$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
					}

					if ( $lic_method != "" ) {
						$new_invoice = array(
							array(
								"Type"            => "ACCREC",
								"Contact"         => array(
									"Name"         => $xero_add_contact_name,
									"FirstName"    => $shipAddress['first_name'],
									"LastName"     => $shipAddress['last_name'],
									"EmailAddress" => $shipAddress['email'],
									"Phones"       => array(
										"Phone" => array(
											"PhoneType"   => base64_decode( 'REVGQVVMVA==' ),
											"PhoneNumber" => $shipAddress['phone']
										)
									)
								),
								"Date"            => $currentDate,
								"Status"          => $default_invoice_status,
								"CurrencyCode"    => $orderCurency,
								"LineAmountTypes" => base64_decode( 'RXhjbHVzaXZl' ),
								"Reference"       => $order_id,
								"LineItems"       => $lineItemsArray
							)
						);

						$new_invoice[0]['Contact']['Addresses']['Address'] = array(
							xeroom_client_address_to_sent(
								$shipAddress,
								$stateFullName,
								$countryFullName,
								$shipAddress['email'],
								$shipAddress['first_name'],
								$shipAddress['last_name'],
								$xero_add_contact_name,
								$order
							)
						);
					} else {
						$new_invoice = array(
							array(
								"Type"            => "ACCREC",
								"Contact"         => array(
									"Name"         => $xero_add_contact_name,
									"FirstName"    => $shipAddress['first_name'],
									"LastName"     => $shipAddress['last_name'],
									"EmailAddress" => $shipAddress['email'],
									"Addresses"    => array(
										"Address" => array(
											array(
												"AddressType"  => base64_decode( 'UE9CT1g=' ),
												"AttentionTo"  => "Created using demo version of Xeroom",
												"AddressLine1" => "",
												"AddressLine2" => "",
												"AddressLine3" => "",
												"City"         => "",
												"Region"       => "",
												"Country"      => "",
												"PostalCode"   => ""
											)
										)
									),
									"Phones"       => array(
										"Phone" => array(
											"PhoneType"   => base64_decode( 'REVGQVVMVA==' ),
											"PhoneNumber" => $shipAddress['phone']
										)
									)
								),
								"Date"            => $currentDate,
								"Status"          => $default_invoice_status,
								"CurrencyCode"    => $orderCurency,
								"LineAmountTypes" => base64_decode( 'RXhjbHVzaXZl' ),
								"Reference"       => $order_id,
								"LineItems"       => $lineItemsArray
							)
						);
					}

					if ( $xero_invoice_no ) {
						$new_invoice[0]['InvoiceNumber'] = $xero_invoice_no;
					}

					if ( 'manually-resend-invoice' == $errorType ) {
						if ( $xeroom_invoice_no_sent ) {
							$new_invoice[0]['InvoiceNumber'] = $xeroom_invoice_no_sent . '/' . xeroom_add_invoice_suffix( $order_id );
						} else {
							$new_invoice[0]['InvoiceNumber'] = $xero_invoice_no . '/' . xeroom_add_invoice_suffix( $order_id );
						}
					}

					$new_invoice[0]['Reference'] = xeroom_generate_invoice_reference( $order_id );

					$custom_number_of_days   = get_xero_option( 'xero_due_date_custom_days' );
					$xero_due_date_month_day = get_xero_option( 'xero_due_date_month_day' );
					if ( $due_date_settings && 'use_custom_due_date' == $due_date_settings && $custom_number_of_days ) {
						$new_invoice[0]['DueDate']             = date( 'Y-m-d', strtotime( "+$custom_number_of_days days" ) );
						$new_invoice[0]['ExpectedPaymentDate'] = date( 'Y-m-d', strtotime( "+$custom_number_of_days days" ) );
					} elseif ( $due_date_settings && 'use_specific_month_day' == $due_date_settings && $xero_due_date_month_day ) {
						$new_invoice[0]['DueDate']             = generate_invoice_date( $xero_due_date_month_day );
						$new_invoice[0]['ExpectedPaymentDate'] = generate_invoice_date( $xero_due_date_month_day );;
					} elseif ( $due_date_settings && 'use_xero_due_date' == $due_date_settings ) {
						$xero_date_array = xeroom_get_xero_set_date_before_invoice( date( 'Y-m-d' ), $order );

						if (
                                isset( $xero_date_array['DueDate'] ) &&
                                ! empty( $xero_date_array['DueDate'] ) &&
                                isset( $xero_date_array['ExpectedPaymentDate'] ) &&
                                ! empty( $xero_date_array['ExpectedPaymentDate'] )
                        ) {
							$new_invoice[0]['DueDate']             = $xero_date_array['DueDate'];
							$new_invoice[0]['ExpectedPaymentDate'] = $xero_date_array['ExpectedPaymentDate'];
						} else {
							$new_invoice[0]['DueDate']             = date( 'Y-m-d', strtotime( '+3 day' ) );
							$new_invoice[0]['ExpectedPaymentDate'] = date( 'Y-m-d', strtotime( '+3 day' ) );
						}
					} else {
						$new_invoice[0]['DueDate']             = date( 'Y-m-d', strtotime( '+3 day' ) );
						$new_invoice[0]['ExpectedPaymentDate'] = date( 'Y-m-d', strtotime( '+3 day' ) );
					}

					if ( $license_checked != 'expired' ) {
						$new_invoice = apply_filters( 'xeroom_new_invoice_data', $new_invoice, $order_id );
                        
						if ( 'manually-resend-invoice' == $errorType ) {
							$invoiceCreated = $xero->Invoices( $new_invoice, array( 'InvoiceResend' => true ) );
						} else {
							$invoiceCreated = $xero->Invoices( $new_invoice );
						}

						$order = new WC_Order( intval( $order_id ) );
						$api_counter ++;
						if ( ! empty( $invoiceCreated ) ) {
							if ( isset( $invoiceCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $invoiceCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
								$exportErrorXero .= $errD;
							} else if ( isset( $invoiceCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = array();
								$errD = $invoiceCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $e = 0; $e < count( $errD ); $e ++ ) {
									$errorMessage = $invoiceCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
									$exportErrorXero .= $errorMessage;
								}
							} else if ( isset( $invoiceCreated['Status'] ) && $invoiceCreated['Status'] != "OK" ) {
								returnErrorMessageByXero( $order_id, $invoiceCreated, $xeroTime, $errorType );
								$exportErrorXero .= $invoiceCreated;
							} elseif ( isset( $invoiceCreated['ErrorNumber'] ) ) {
								returnErrorMessageByXero( $order_id, $invoiceCreated, $xeroTime, $errorType );
								$exportErrorXero .= $invoiceCreated['Message'];
							}
						} else {
							$mMessage = "Xero Server Response is empty.";
							returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
							$exportErrorXero .= $mMessage;
						}

						if ( $invoice_start_no || $invoice_prefix ) {
							if ( ! $order->get_meta( 'xeroom_invoice_no_sent' ) ) {
								$order->update_meta_data( 'xeroom_invoice_no_sent', sanitize_text_field( $xero_invoice_no ) );
//								$order->save();
							}
						}

						if ( $xero_invoice_no ) {
							$remove_prefixes = explode( '-', $xero_invoice_no );
							if ( is_array( $remove_prefixes ) ) {
								$xero_invoice_no = end( $remove_prefixes );
							}
							update_xero_option( 'xero_invoice_next_no', absint( $xero_invoice_no ) + 1 );
						}

						if ( isset( $invoiceCreated['Status'] ) && $invoiceCreated['Status'] == "OK" ) {
							$InvoiceNumber = $invoiceCreated['Invoices']['Invoice']['InvoiceNumber'];
							$InvoiceID     = $invoiceCreated['Invoices']['Invoice']['InvoiceID'];
							$AmountDue     = $invoiceCreated['Invoices']['Invoice']['AmountDue'];
							$order         = new WC_Order( $order_id );
							if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
								$order->update_meta_data( 'post_content', sanitize_text_field( $InvoiceNumber ) );
								$order->update_meta_data( 'post_content_filtered', sanitize_text_field( $InvoiceID ) );
								$order->update_meta_data( 'post_mime_type', sanitize_text_field( $AmountDue ) );
//								$order->save();
							} else {
								$my_order_post = array(
									'ID'                    => intval( $order_id ),
									'post_content'          => sanitize_text_field( $InvoiceNumber ),
									'post_content_filtered' => sanitize_text_field( $InvoiceID ),
									'post_mime_type'        => sanitize_text_field( $AmountDue )
								);
								wp_update_post( $my_order_post );
							}

							$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );


							if ( ! $invoice_start_no || ! $invoice_prefix ) {
								if ( ! $order->get_meta( 'xeroom_invoice_no_sent' ) ) {
									$order->update_meta_data( 'xeroom_invoice_no_sent', sanitize_text_field( $InvoiceNumber ) );
//									$order->save();
								}
							}
							$order->save();
							// Fix Rounding in Xero ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
							$invoice_total = $invoiceCreated['Invoices']['Invoice']['Total'];
							$invoice_items = $invoiceCreated['Invoices']['Invoice']['LineItems'];

							if ( $order->get_total() != $invoice_total ) {
								$items_to_xero = array();
								foreach ( $invoice_items as $xero_items ) {
									$items_to_xero[] = $xero_items;
								}

								$rounding_account       = 860;
								$saved_rounding_account = get_xero_option( 'xeroom_rounding_account' );
								if ( $saved_rounding_account ) {
									$rounding_account = esc_attr( $saved_rounding_account );
								}

								$items_to_xero[] = array(
									'Description' => __( 'Rounding adjustment', 'xeroom' ),
									'Quantity'    => 1,
									'UnitAmount'  => $order->get_total() - $invoice_total,
									'AccountCode' => $rounding_account,
								);

								$grouped_items = array();
								for ( $i = 0; $i < count( $items_to_xero ); $i ++ ) {
									if ( isset( $invoiceCreated['Invoices']['Invoice']['LineItems']['LineItem'][0] ) ) {
										$grouped_items[] = array( "LineItem" => $items_to_xero[ $i ] );
									} else {
										$grouped_items[] = array( "LineItem" => array( $items_to_xero[ $i ] ) );
									}
								}

								$rounding_info = array(
									array(
										"InvoiceNumber" => $InvoiceNumber,
										"LineItems"     => $grouped_items,
									),
								);

								$xero_rounding = $xero->Invoices( $rounding_info );
								$api_counter ++;
								if ( ! empty( $xero_rounding ) ) {
									if ( isset( $xero_rounding['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
										$errD = $xero_rounding['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
										returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
									} else if ( isset( $xero_rounding['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
										$errD = $xero_rounding['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
										for ( $e = 0; $e < count( $errD ); $e ++ ) {
											$errorMessage = $xero_rounding['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
											returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
										}
									} else if ( isset( $xero_rounding['Status'] ) && $xero_rounding['Status'] != "OK" ) {
										returnErrorMessageByXero( $order_id, $xero_rounding, $xeroTime, $errorType );
									}
								} else {
									$mMessage = "Xero Server Response is empty.";
									returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
								}

								// Update Amount Due
								if ( isset( $xero_rounding['Status'] ) && $xero_rounding['Status'] == "OK" ) {
									$AmountDue = $xero_rounding['Invoices']['Invoice']['AmountDue'];

									if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
										$order->update_meta_data( 'post_mime_type', sanitize_text_field( $AmountDue ) );
										$order->save();
									} else {
										$my_order_post = array(
											'ID'             => intval( $order_id ),
											'post_mime_type' => sanitize_text_field( $AmountDue )
										);
										wp_update_post( $my_order_post );;
									}
								}
							}

							update_xero_option( 'xero_connection_status', 'active' );
							update_xero_option( 'xero_connection_status_message', '' );
							// Fix Rounding in Xero ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

							// Send invoice to client by Xero
							$xero_email_invoice = get_xero_option( 'xero_email_invoice' );
							if ( $xero_email_invoice && 'xeroom_unpaid' === $xero_email_invoice ) {
								$send_it = new Xeroom_Batch_Request();
								$send_it->xero_send_invoice_to_client( absint( $order_id ) );
								$api_counter ++;
							}
							increment_invoice_counter();
						}

					}
				} else {
					$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
					returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
					$exportErrorXero .= $mMessage;
				}
			} else {
				$mMessage = "Your License expired, Please contact xeroom support team.";
				returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
				$exportErrorXero .= $mMessage;
			}
		} else {
			$mMessage = esc_html__( 'Order posting to Xero by Xeroom failed. Your licence has expired.', 'xeroom' );
			returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
			$exportErrorXero          .= $mMessage;
			$license_validation_error = esc_html__( 'Order posting to Xero by Xeroom failed. Your licence has expired.', 'xeroom' );
		}

		// Add order note
		if ( '' != $exportErrorXero ) {
			$success = 0;
			// Add order note for failing to send data Xero
			if ( ! isset( $license_validation_error ) && ! empty( $license_validation_error ) ) {
				$xero_order_note = __( 'Order posting to Xero by Xeroom failed', 'xeroom' );
				$exportErrorXero .= $xero_order_note;

				$xero_order_note = apply_filters(
					'xero_add_fail_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
			}
		} else {
			$success = 1;
			// Add order note for successfully sent data to Xero
			$xero_order_note = __( 'The order has been successfully sent to Xero using Xeroom', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_success_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );
//			$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );
		}

		$mMessage = "Number of API request on Order $order_id = $api_counter.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );

		if ( ! $success ) {
			return $exportErrorXero;
		} elseif ( $success ) {
			return 'Successful';
		} else {
			return $exportErrorXero;
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}

	$order->save();
}

/**
 * Return Coupon as item id
 *
 * @param $order_id
 * @param $coupon_name
 *
 * @return mixed
 */
function wpr_fetch_coupon_item_id( $order_id, $coupon_name ) {
	global $wpdb;

	$table   = $wpdb->prefix . "woocommerce_order_items";
	$item_id = $wpdb->get_row( "SELECT order_item_id FROM {$table} WHERE order_id = {$order_id} AND order_item_name = '{$coupon_name}'" );

	return $item_id->order_item_id;
}

/**
 * Set Invoice Date
 *
 * @param $order
 *
 * @return false|string
 */
function xeroom_invoice_date( $order ) {
	$invoice_date = get_xero_option( 'xero_invoice_date' );
	if ( 'order_date' == $invoice_date ) {
		if ( $order && method_exists( $order, 'get_date_created' ) ) {
			$date_created = $order->get_date_created();
			if ( $date_created instanceof DateTime ) {
				return $date_created->format( 'Y-m-d H:i:s' );
			}
		}

		// Fallback if unable to get order creation date.
		return date( 'Y-m-d H:i:s' );
	} else {
		return date( 'Y-m-d H:i:s' );
	}
}

/**
 * Send Payment Success to Xero after Successfully Checkout Payment
 *
 * @param $order_id
 * @param string $errorType
 *
 * @return string
 * @throws XeroException
 */
function xeroom_paymentDoneOnCheckout( $order_id, $errorType = "productOrder" ) {
	include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
	global $wpdb;
	$exportErrorXero   = "";
	$xeroTime          = date( 'Y-m-d H:i:s' );
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );

	if ( $getApiCredentials ) {
		$order         = new WC_Order( $order_id );
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = sanitize_text_field( $getApiCredentials[0]->sales_account );
		$BankCode      = sanitize_text_field( $getApiCredentials[0]->bank_code );

		$oder_gateway_code = $order->get_payment_method();
		if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
			$oder_gateway_code = 'accountfunds';
			xeroom_generate_prepayment( $order_id );

			return;
		}

		$order_type = wc_get_order( $order_id );
		if ( defined( 'AWCDP_POST_TYPE' ) && $order_type->get_type() == AWCDP_POST_TYPE ) {
			xeroom_generate_partial_payment( $order_id );

			return;
		}

		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		if ( $order->get_meta( 'xeroom_payment_sent', true ) ) {
			return;
		}

		$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
		if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
			return;
		}

		if ( $errorType == "manually" ) {
			if ( ! defined( 'BASE_PATH' ) ) {
				define( 'BASE_PATH', dirname( __FILE__ ) );
			}

			if ( ! defined( 'XERO_KEY' ) ) {
				define( 'XERO_KEY', $xeroApiKey );
			}

			if ( ! defined( 'XERO_SECRET' ) ) {
				define( 'XERO_SECRET', $xeroApiSecret );
			}

			if ( ! defined( 'PUBLIC_KEY' ) ) {
				define( 'PUBLIC_KEY', '' );
			}

			if ( ! defined( 'PRIVATE_KEY' ) ) {
				define( 'PRIVATE_KEY', '' );
			}

			if ( ! defined( 'FORMAT' ) ) {
				define( 'FORMAT', 'json' );
			}

			$xero_timezone = new DateTimeZone( 'UTC' );
			$xero_time     = date( 'Y-m-d H:i:s' );
			$xero_date     = new DateTime( $xero_time, $xero_timezone );

			$oauth2 = get_xero_option( 'xero_oauth_options' );
			xeroom_check_xero_token( $oauth2 );
			$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$invoice_id   = $order->get_meta( 'post_content_filtered' );
				$order_amount = $order->get_meta( 'post_mime_type' );
			} else {
				$order_details = get_post( $order_id );
				$invoice_id    = $order_details->post_content_filtered;
				$order_amount  = $order_details->post_mime_type;
			}

			$order            = new WC_Order( $order_id );
			$transaction_info = '';
			if ( $order->get_transaction_id() ) {
				$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
			}

			$new_payment = array(
				array(
					"Invoice"   => array(
						"InvoiceID" => $invoice_id
					),
					"Account"   => array(
						"Code" => $BankCode
					),
					"Date"      => $xero_date,
					"Amount"    => $order_amount,
					"Reference" => strtoupper( $oder_gateway_code ) . $transaction_info
				)
			);
			$paymentXero = $xero->Payments( $new_payment );

			if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
				$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] . ". Please Create your bank account on xero and update bank code in xeroom setting's bank code.";
				returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
				$exportErrorXero .= $errD;

				// Add order note for failing to send data Xero
				$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_fail_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
			} else if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
				$errD = array();
				$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
				for ( $e = 0; $e < count( $errD ); $e ++ ) {
					$errorMessage = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
					returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
					$exportErrorXero .= $errorMessage;
				}

				// Add order note for failing to send data Xero
				$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_fail_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
			} else {
				// Add order note for successfully sent data to Xero
				$xero_order_note = __( 'The Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_success_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );

				$order = new WC_Order( $order_id );
				$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );
				$order->update_meta_data( 'xeroom_payment_sent', 'Sent to Xero' );

				$order->save();

				update_xero_option( 'xero_connection_status', 'active' );
				update_xero_option( 'xero_connection_status_message', '' );
			}

			return $exportErrorXero;
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}

	return $exportErrorXero;
}

/**
 * Send Payment Success to Xero after Payment is Done
 *
 * @param $order_id
 */
function xeroom_paymentDoneOnPayment( $order_id ) {
	include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
	global $wpdb;
	$errorType = "productOrder";

	$xeroTime          = date( 'Y-m-d H:i:s' );
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );

	if ( $getApiCredentials ) {
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = sanitize_text_field( $getApiCredentials[0]->sales_account );
		$BankCode      = sanitize_text_field( $getApiCredentials[0]->bank_code );
		$sendOrNot     = $getApiCredentials[0]->payment_master;
		$order         = new WC_Order( $order_id );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$oder_gateway_code = $order->get_payment_method();
		} else {
			$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
		}

		if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
			$oder_gateway_code = 'accountfunds';
			xeroom_generate_prepayment( $order_id );

			return;
		}

		$order_type = wc_get_order( $order_id );
		if ( defined( 'AWCDP_POST_TYPE' ) && $order_type->get_type() == AWCDP_POST_TYPE ) {
			xeroom_generate_partial_payment( $order_id );

			return;
		}

		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );

		if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
			return;
		}

		$send_payment_method = get_xero_option( 'xero_send_payment_method' );
		$invoice_reference   = get_xero_option( 'xero_invoice_reference_prefix' );

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$xero_timezone = new DateTimeZone( 'UTC' );
		$xero_time     = date( 'Y-m-d H:i:s' );
		$xero_date     = new DateTime( $xero_time, $xero_timezone );

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order_details = new WC_Order( $order_id );
		} else {
			$order_details = get_post( $order_id );
		}

		$order            = new WC_Order( $order_id );
		$transaction_info = '';
		if ( $order->get_transaction_id() ) {
			$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
		}

		$reference_no = xeroom_generate_invoice_reference( $order_id );

		if ( 'automatically' == $send_payment_method ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				if ( $order->get_meta( 'xeroom_payment_sent', true ) ) {
					return;
				}

				if ( ! $order->get_meta( 'xeroom_order_sent', true ) ) {
					return;
				}
			} else {
				if ( get_post_meta( $order_id, 'xeroom_payment_sent', true ) ) {
					return;
				}

				if ( ! get_post_meta( $order_id, 'xeroom_order_sent', true ) ) {
					return;
				}
			}

			xeroom_update_reference_no( $order_id );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$invoice_id   = $order->get_meta( 'post_content_filtered' );
				$order_amount = $order->get_meta( 'post_mime_type' );
			} else {
				$invoice_id   = $order_details->post_content_filtered;
				$order_amount = $order_details->post_mime_type;
			}

			$new_payment = array(
				array(
					"Invoice"   => array(
						"InvoiceID" => $invoice_id
					),
					"Account"   => array(
						"Code" => $BankCode
					),
					"Date"      => $xero_date,
					"Amount"    => $order_amount,
					"Reference" => $reference_no
				)
			);
			$paymentXero = $xero->Payments( $new_payment );

			if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
				$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] . ". Please Create your bank account on xero and update bank code in xeroom setting's bank code.";
				returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );

				// Add order note for failing to send data Xero
				$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_fail_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
			} else if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
				$errD = array();
				$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
				for ( $e = 0; $e < count( $errD ); $e ++ ) {
					$errorMessage = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
					returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
				}

				// Add order note for failing to send data Xero
				$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_fail_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
			} else {
				// Add order note for successfully sent data to Xero
				$xero_order_note = __( 'The Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

				xeroom_add_xero_invoice_payment_fee( $order_id );

				$xero_order_note = apply_filters(
					'xero_add_success_message_order',
					$xero_order_note,
					$order
				);

				$order->add_order_note( $xero_order_note );
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order = new WC_Order( $order_id );
					$order->update_meta_data( 'xeroom_payment_sent', 'Sent to Xero' );
					$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );
					$order->save();
				} else {
					update_post_meta( $order_id, 'xeroom_payment_sent', 'Sent to Xero' );
					update_post_meta( $order_id, 'xeroom_order_sent', 'Sent to Xero' );
				}

				update_xero_option( 'xero_connection_status', 'active' );
				update_xero_option( 'xero_connection_status_message', '' );
			}
		} else {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$invoice_no = $order->get_meta( 'post_content' );
			} else {
				$invoice_no = $order_details->post_content;
			}

			$invoice_info = array(
				array(
					"InvoiceNumber" => $invoice_no,
					"Reference"     => $reference_no,
				),
			);

			$xero->Invoices( $invoice_info );
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}
}

/**
 * Send Payment Success to Xero on Order status changed to Complete
 *
 * @param $order_id
 */
function xeroom_paymentDoneOnCheckoutManually( $order_id ) {
	// Send Invoice to Xero when order status changed to Complete
	$xeroTime   = date( 'Y-m-d H:i:s' );
	$order      = new WC_Order( $order_id );
	$order_type = wc_get_order( $order_id );

	if ( defined( 'AWCDP_POST_TYPE' ) && $order_type->get_type() == AWCDP_POST_TYPE ) {
		xeroom_generate_partial_payment( $order_id );

		return;
	}

	$send_invoice_method = get_xero_option( 'xero_send_invoice_method' );

	$xero_autocomplete_orders = get_xero_option( 'xero_autocomplete_orders' );

	$send_payment_method = get_xero_option( 'xero_send_payment_method' );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent = $order->get_meta( 'xeroom_order_sent', true );
	} else {
		$order_sent = get_post_meta( $order_id, 'xeroom_order_sent', true );
	}

	if ( 'completed' == $send_invoice_method || 0 !== $xero_autocomplete_orders ) {
		if ( ! $order_sent ) {
			if ( 'manually' == $send_invoice_method ) {
				return;
			}
			xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
		}
	}

	if ( 'manually' == $send_payment_method ) {
		return;
	}

	if ( ! $order_sent ) {
		if ( 'completed' !== $send_invoice_method ) {
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed. The Invoice needs to be sent to Xero first.', 'xeroom' );

			returnErrorMessageByXero( $order_id, $xero_order_note, $xeroTime, 'productOrder' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );

			return;
		}
	}

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		if ( $order->get_meta( 'xeroom_payment_sent', true ) ) {
			return;
		}
	} else {
		if ( get_post_meta( $order_id, 'xeroom_payment_sent', true ) ) {
			return;
		}
	}

	include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
	global $wpdb;

	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );

	if ( $getApiCredentials ) {
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = sanitize_text_field( $getApiCredentials[0]->sales_account );
		$BankCode      = sanitize_text_field( $getApiCredentials[0]->bank_code );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$oder_gateway_code = $order->get_payment_method();
		} else {
			$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
		}

		if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
			$oder_gateway_code = 'accountfunds';
			xeroom_generate_prepayment( $order_id );

			return;
		}
		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
		if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
			return;
		}

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$xero_timezone = new DateTimeZone( 'UTC' );
		$xero_time     = date( 'Y-m-d H:i:s' );
		$xero_date     = new DateTime( $xero_time, $xero_timezone );

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$invoice_id   = $order->get_meta( 'post_content_filtered' );
			$order_amount = $order->get_meta( 'post_mime_type' );
		} else {
			$order_details = get_post( $order_id );
			$invoice_id    = $order_details->post_content_filtered;
			$order_amount  = $order_details->post_mime_type;
		}


		$transaction_info = '';
		if ( $order->get_transaction_id() ) {
			$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
		}

		$new_payment = array(
			array(
				"Invoice"   => array(
					"InvoiceID" => $invoice_id
				),
				"Account"   => array(
					"Code" => $BankCode
				),
				"Date"      => $xero_date,
				"Amount"    => $order_amount,
				"Reference" => strtoupper( $oder_gateway_code ) . $transaction_info
			)
		);
		$paymentXero = $xero->Payments( $new_payment );

		if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
			$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] . ". Please Create your bank account on xero and update bank code in xeroom setting's bank code.";
			returnErrorMessageByXero( $order_id, $errD, $xeroTime, 'productOrder' );

			// Add order note for failing to send data Xero
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );
		} else if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
			$errD = array();
			$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
			for ( $e = 0; $e < count( $errD ); $e ++ ) {
				$errorMessage = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
				returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, 'productOrder' );
			}

			// Add order note for failing to send data Xero
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );
		} else {
			xeroom_add_xero_invoice_payment_fee( $order_id );

			// Add order note for successfully sent data to Xero
			$xero_order_note = __( 'The Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_success_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order = new WC_Order( $order_id );
				$order->update_meta_data( 'xeroom_payment_sent', 'Sent to Xero' );
				$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );
				$order->save();
			} else {
				update_post_meta( $order_id, 'xeroom_payment_sent', 'Sent to Xero' );
				update_post_meta( $order_id, 'xeroom_order_sent', 'Sent to Xero' );
			}

			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, 'productOrder' );
	}
}

add_action( 'admin_enqueue_scripts', 'xero_enqueue_admin_js' );
/**
 * Enqueue admin script
 *
 * @param $hook
 */
function xero_enqueue_admin_js() {
	if ( ! wp_script_is( 'select2', 'enqueued' ) ) {
		wp_enqueue_style( 'select2', plugins_url( 'css/select2.css', __FILE__ ) );
		wp_register_script( 'select2', plugin_dir_url( __FILE__ ) . 'js/select2.full.js', array( 'jquery' ), '2.0.0', true );
	}

	wp_register_script( 'xero-js', plugin_dir_url( __FILE__ ) . 'js/xero-js.js', array( 'select2' ), XEROOM_VERSION, true );

	$scheme = 'http';
	if ( is_ssl() ) {
		$scheme = 'https';
	}

	$screen = get_current_screen();

	$bulk_status = get_transient( 'xero_bulkit' ) ? 1 : 0;

	$args = array(
		'nonce'              => wp_create_nonce( 'xero-ajax' ),
		'xeroajax'           => admin_url( 'admin-ajax.php', $scheme ),
		'xero_orders'        => esc_attr( $screen->id ),
		'xero_bulk_status'   => $bulk_status,
		'xero_product_batch' => 100,
		'xero_loading'       => sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync job in progress!', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Xeroom debug here', 'xeroom' ) ),
	);
	wp_localize_script( 'xero-js', 'xero_ajax_object', $args );
	wp_enqueue_script( 'xero-js' );
}

add_action( 'add_meta_boxes', 'xero_add_order_meta_boxe' );
/**
 * Register Xero order custom meta box
 */
function xero_add_order_meta_boxe() {
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		add_meta_box( 'xero_meta_box', __( 'Xero invoice status', 'xeroom' ), 'xero_invoice_status_meta_box', 'woocommerce_page_wc-orders', 'side', 'low' );
	} else {
		add_meta_box( 'xero_meta_box', __( 'Xero invoice status', 'xeroom' ), 'xero_invoice_status_meta_box', 'shop_order', 'side', 'low' );
	}
}

/**
 * Display Xero buttons
 *
 * @param $post
 */
function xero_invoice_status_meta_box( $order ) {
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_id = $order->get_id();
	} else {
		$order_id = $order->ID;
	}

	echo sprintf( '<span>%s</span><br />', __( 'Send Invoice to Xero' ) );
	echo sprintf( '<button data-id="%d" data-call="invoice" type="button" class="button button-primary xero-send-as-invoice" id="xero-send-invoice">%s</button><br /><br />', $order_id, __( 'Send Invoice to Xero', 'xeroom' ) );
	echo sprintf( '<span>%s</span><br />', __( 'Send Payment to Xero' ) );
	echo sprintf( '<button data-id="%d" data-call="payment" type="button" class="button button-primary xero-send-as-invoice" id="xero-send-invoice-payment">%s</button><br /><br />', $order_id, __( 'Send Payment to Xero', 'xeroom' ) );
	echo sprintf( '<span>%s</span><br />', __( 'Resend Invoice to Xero' ) );
	echo sprintf( '<button data-id="%d" data-call="resend-invoice" type="button" class="button button-primary xero-send-as-invoice" id="xero-resend-invoice">%s</button>', $order_id, __( 'Resend Invoice to Xero', 'xeroom' ) );
}

add_action( 'wp_ajax_xero_send_invoice', 'xero_resend_invoice_data' );
/**
 * Resend data to Xero
 */
function xero_resend_invoice_data() {
	check_ajax_referer( 'xero-ajax', 'nonce' );

	$order_id     = absint( $_POST['invoice'] );
	$request_type = esc_attr( $_POST['call_type'] );

	if ( ! $order_id && empty( $request_type ) ) {
		wp_die( __( 'Data sending failed, please try again!', 'xeroom' ) );
	}

	$response = xero_send_invoice_data( $order_id, $request_type );

	wp_die( $response );
}

/**
 * Send invoice data to Xero
 *
 * @param $order_id
 */
function xero_send_invoice_data( $order_id, $type ) {
	global $wpdb;

	$display_message = '';
	$xeroTime        = date( 'Y-m-d H:i:s' );
	// Xero Connectivity Api Credentials------------
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );
	$order             = new WC_Order( $order_id );

	if ( $getApiCredentials ) {
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = esc_attr( $getApiCredentials[0]->sales_account );
		$BankCode      = esc_attr( $getApiCredentials[0]->bank_code );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$oder_gateway_code = $order->get_payment_method();
		} else {
			$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
		}

		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}
		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order_details = new WC_Order( $order_id );
			$invoice_no    = $order_details->get_meta( 'post_content' );
			$order_amount  = $order_details->get_meta( 'post_mime_type' );
			$invoice_id    = $order->get_meta( 'post_content_filtered' );
		} else {
			$order_details = get_post( $order_id );
			$invoice_no    = $order_details->post_content;
			$order_amount  = $order_details->post_mime_type;
			$invoice_id    = $order_details->post_content_filtered;
		}

		$display_message = "Xero run as $type.";

		if ( '' != trim( $invoice_no ) ) {

			if ( 'resend-invoice' == $type ) {
				$display_message = xeroom_sendWooInvoiceToXero( $order_id, 'manually-resend-invoice' );
			} else {
				$invoice_info = array(
					array(
						"InvoiceID" => $invoice_id
					)
				);

				$xero_check = $xero->Invoices( $invoice_info );

				if ( $xero_check && ! isset( $xero_check['ErrorNumber'] ) ) {
					if ( isset( $xero_check['Invoices'] ) && count( $xero_check['Invoices'] ) > 0 ) {
						if ( 'invoice' == $type && ! isset( $xero_check['Invoices']['Invoice']['AmountPaid'] ) ) {
							$display_message = __( 'The invoice is already registered in Xero.', 'xeroom' );
						} else {
							$display_message = __( 'Invoice is already paid status in Xero and cannot be resent.', 'xeroom' );
						}

						if ( 'payment' == $type && ( isset( $xero_check['Invoices']['Invoice']['AmountPaid'] ) && 0 == $xero_check['Invoices']['Invoice']['AmountPaid'] ) ) {
							$display_message = xero_send_manually_payment( $order_id );
						}
					} else {
						$display_message = xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
					}
				} else {
					if ( isset( $xero_check['Elements'] ) && $xero_check['Elements']['DataContractBase']['InvoiceNumber'] != $invoice_no && 'invoice' == $type ) {
						$display_message = xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
					} else {
						$display_message = $xero_check['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0]['Message'];
					}

					if ( empty( $display_message ) ) {
						$display_message = 'Invoice not sent to Xero, please try again later.';
					}
				}
			}

		} else {
			$display_message = xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
		}

		returnErrorMessageByXero( $order_id, $display_message, $xeroTime, 'productOrder' );
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, 'productOrder' );
	}

	return $display_message;
}

/**
 * Send Order Payment to Xero on status Change to Complete
 *
 * @param $order_id
 * @param $status
 */
function xeroom_send_payment_on_manually_complete( $order_id, $status ) {
	$send_payment_method = get_xero_option( 'xero_send_payment_method' );
	if ( 'manually' == $send_payment_method ) {
		return;
	}

	if ( 'completed' == $status ) {
		xero_send_manually_payment( $order_id );
	}
}

/**
 * Send payment to Xero manually
 *
 * @param $order_id
 *
 * @return string
 */
function xero_send_manually_payment( $order_id ) {
	$order_type          = wc_get_order( $order_id );
	$order               = new WC_Order( $order_id );
	$send_invoice_method = get_xero_option( 'xero_send_invoice_method' );
	$xeroTime            = date( 'Y-m-d H:i:s' );

	if ( defined( 'AWCDP_POST_TYPE' ) && $order_type->get_type() == AWCDP_POST_TYPE ) {
		xeroom_generate_partial_payment( $order_id );

		return;
	}

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent = $order->get_meta( 'xeroom_order_sent', true );
	} else {
		$order_sent = get_post_meta( $order_id, 'xeroom_order_sent', true );
	}
	if ( ! $order_sent ) {
		xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
	}

	// Check again the Order data
	$order = new WC_Order( $order_id );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent = $order->get_meta( 'xeroom_order_sent', true );
	} else {
		$order_sent = get_post_meta( $order_id, 'xeroom_order_sent', true );
	}

	if ( ! $order_sent ) {
		if ( 'completed' !== $send_invoice_method ) {
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed. The Invoice needs to be sent to Xero first.', 'xeroom' );

			returnErrorMessageByXero( $order_id, $xero_order_note, $xeroTime, 'productOrder' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );

			return;
		}
	}

	include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
	global $wpdb;

	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );

	if ( $getApiCredentials ) {
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = esc_attr( $getApiCredentials[0]->sales_account );
		$BankCode      = esc_attr( $getApiCredentials[0]->bank_code );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$oder_gateway_code = $order->get_payment_method();
		} else {
			$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
		}

		if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
			$oder_gateway_code = 'accountfunds';
			xeroom_generate_prepayment( $order_id );

			return;
		}
		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
		if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
//			return esc_html__( 'Payment sending to Xero by Xeroom failed. The order does not have a payment method selected.', 'xeroom' );
		}

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$xero_timezone = new DateTimeZone( 'UTC' );
		$xero_time     = date( 'Y-m-d H:i:s' );
		$xero_date     = new DateTime( $xero_time, $xero_timezone );

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$invoice_id   = $order->get_meta( 'post_content_filtered' );
			$order_amount = $order->get_meta( 'post_mime_type' );
		} else {
			$order_details = get_post( $order_id );
			$invoice_id    = $order_details->post_content_filtered;
			$order_amount  = $order_details->post_mime_type;
		}

		$transaction_info = '';
		if ( $order->get_transaction_id() ) {
			$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
		}

		$new_payment = array(
			array(
				"Invoice"   => array(
					"InvoiceID" => $invoice_id
				),
				"Account"   => array(
					"Code" => $BankCode
				),
				"Date"      => $xero_date,
				"Amount"    => $order_amount,
				"Reference" => strtoupper( $oder_gateway_code ) . $transaction_info
			)
		);
		$paymentXero = $xero->Payments( $new_payment );

		$order = new WC_Order( $order_id );

		if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
			$errorMessage = $errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] . ". Please Create your bank account on xero and update bank code in xeroom setting's bank code.";
			returnErrorMessageByXero( $order_id, $errD, $xeroTime, 'productOrder' );

			// Add order note for failing to send data Xero
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );
		} else if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
			$errD = array();
			$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
			for ( $e = 0; $e < count( $errD ); $e ++ ) {
				$errorMessage = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
				returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, 'productOrder' );
			}

			// Add order note for failing to send data Xero
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );
		} else {
			$errorMessage = __( 'Payment sent successfully to Xero', 'xeroom' );
			xeroom_add_xero_invoice_payment_fee( $order_id );

			// Add order note for successfully sent data to Xero
			$xero_order_note = __( 'The Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_add_success_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order = new WC_Order( $order_id );
				$order->update_meta_data( 'xeroom_payment_sent', 'Sent to Xero' );
				$order->update_meta_data( 'xeroom_order_sent', 'Sent to Xero' );
				$order->save();
			} else {
				update_post_meta( $order_id, 'xeroom_payment_sent', 'Sent to Xero' );
				update_post_meta( $order_id, 'xeroom_order_sent', 'Sent to Xero' );
			}

			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, 'productOrder' );
	}

	return $errorMessage;
}

/**
 * Send payment for partial refund to Xero manually
 *
 * @param $order_id
 * @param $refund_id
 *
 * @return string|void|null
 * @throws XeroException
 */
function xero_send_manually_payment_for_refund( $order_id, $refund_id ) {
	$order               = new WC_Order( $order_id );
	$refund              = new WC_Order_Refund( $refund_id );
	$send_invoice_method = get_xero_option( 'xero_send_invoice_method' );
	$xeroTime            = date( 'Y-m-d H:i:s' );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent = $order->get_meta( 'xeroom_order_sent', true );
	} else {
		$order_sent = get_post_meta( $order_id, 'xeroom_order_sent', true );
	}
	if ( ! $order_sent ) {
		xeroom_sendWooInvoiceToXero( $order_id, 'orderProduct' );
	}

	// Check again the Order data
	$order = new WC_Order( $order_id );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent = $order->get_meta( 'xeroom_order_sent', true );
	} else {
		$order_sent = get_post_meta( $order_id, 'xeroom_order_sent', true );
	}

	if ( ! $order_sent ) {
		if ( 'completed' !== $send_invoice_method ) {
			$xero_order_note = __( 'Payment sending to Xero by Xeroom failed. The Invoice needs to be sent to Xero first.', 'xeroom' );

			returnErrorMessageByXero( $order_id, $xero_order_note, $xeroTime, 'productOrder' );

			$xero_order_note = apply_filters(
				'xero_add_fail_message_order',
				$xero_order_note,
				$order
			);

			$order->add_order_note( $xero_order_note );

			return;
		}
	}

	// Do the payment for the refund amount.
	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xeroTenantId = $oauth2['tenant_id'];

	$where      = 'EmailAddress="' . $order->get_billing_email() . '"';
	$getContact = $apiInstance->getContacts( $xeroTenantId, null, $where );
	$contactId  = $getContact->getContacts()[0]->getContactId();

	global $wpdb;
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );
	$BankCode          = esc_attr( $getApiCredentials[0]->bank_code );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$oder_gateway_code = $order->get_payment_method();
	} else {
		$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
	}

	if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
		$oder_gateway_code = 'accountfunds';
	}

	if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
		$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
	}

	$xero_default_accounts = get_xero_option( 'xero_default_accounts' );
	$bank_index            = array_search( $BankCode, array_column( $xero_default_accounts, 'Code' ) );

	if ( false !== $bank_index ) {
		$contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact->setContactId( $contactId );

		$accountId = $xero_default_accounts[ $bank_index ]['AccountID'];

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$invoice_id = $order->get_meta( 'post_content_filtered' );
		} else {
			$order_details = get_post( $order_id );
			$invoice_id    = $order_details->post_content_filtered;
		}

		$invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
		$invoice->setInvoiceID( $invoice_id );

		//[Payments:Create]
		$bankaccount = new XeroAPI\XeroPHP\Models\Accounting\Account;
		$bankaccount->setAccountID( trim( $accountId ) );

		$payment = new XeroAPI\XeroPHP\Models\Accounting\Payment;
		$payment->setInvoice( $invoice )
		        ->setAccount( $bankaccount )
		        ->setAmount( $refund->get_amount() );

		$result = $apiInstance->createPayment( $xeroTenantId, $payment );

		//[/Payments:Create]

		if ( $result->getPayments()[0]->getPaymentID() ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( 'xeroom_payment_for_refund_sent', 'Refund Payment Sent to Xero' );
				$order->save();
			} else {
				update_post_meta( $order_id, 'xeroom_payment_for_refund_sent', 'Refund Payment Sent to Xero' );
			}

			// Add order note for successfully sent data to Xero
			$xero_order_note = __( 'The Refund Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

			$xero_order_note = apply_filters(
				'xero_refund_partial_payment_success_message_order',
				$xero_order_note,
				$order
			);
			$order->add_order_note( $xero_order_note );
		} else {

		}
	}
}

/**
 * Is category associated
 *
 * @param $i
 * @param $category_id
 *
 * @return bool
 */
function xeroom_is_associated_category( $i, $category_id ) {
	$is_associated = false;

	$associated_product_categories = get_xero_option( 'xero_associate_product_categories' );

	if ( $associated_product_categories ) {
		foreach ( $associated_product_categories as $key => $value ) {
			if ( $i == $key && is_array( $value ) && array_key_exists( $category_id, $value ) ) {
				$is_associated = true;
			}
		}
	}

	return $is_associated;
}

/**
 * Get associated account to category
 *
 * @param $i
 * @param $category_id
 *
 * @return bool
 */
function xeroom_account_associated_category( $i, $category_id ) {
	$account_no = '';

	$associated_product_categories = get_xero_option( 'xero_associate_product_categories' );

	if ( $associated_product_categories ) {
		foreach ( $associated_product_categories as $key => $value ) {
			if ( $i == $key ) {
				$account_no = $value[ $category_id ];
			}
		}
	}

	return $account_no;
}

/**
 * Is shipping zone associated
 *
 * @param $i
 * @param $zone_id
 *
 * @return bool
 */
function xeroom_is_associated_shipping( $i, $zone_id ) {
	$is_associated = false;

	$associated_shipping_zones = get_xero_option( 'xero_associate_shipping_zones' );

	if ( $associated_shipping_zones ) {
		foreach ( $associated_shipping_zones as $key => $value ) {
			if ( $i == $key && is_array( $value ) && array_key_exists( $zone_id, $value ) ) {
				$is_associated = true;
			}
		}
	}

	return $is_associated;
}

/**
 * Fetch shipping account
 *
 * @param $i
 * @param $shipping_id
 *
 * @return string
 */
function xeroom_fetch_associated_shipping( $i, $shipping_id ) {
	$shipping_no = '';

	$associated_shipping = get_xero_option( 'xero_associate_shipping_zones' );

	if ( $associated_shipping ) {
		foreach ( $associated_shipping as $key => $value ) {
			if ( $i == $key ) {
				$shipping_no = $value[ $shipping_id ];
			}
		}
	}

	return $shipping_no;
}

/**
 * Fetch Associated Category Xero Account
 *
 * @param $categories
 *
 * @return string
 */
function xeroom_product_category_associated( $categories ) {
	$account_no                    = '';
	$associated_product_categories = get_xero_option( 'xero_associate_product_categories' );

	if ( $associated_product_categories ) {
		$key_val = array();
		foreach ( $associated_product_categories as $key => $value ) {
			if ( ! empty( $value ) ) {
				foreach ( $value as $sec => $acc ) {
					$key_val[ $sec ] = esc_attr( $acc );
				}
			}
		}

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $keyc => $cval ) {
				$get_top_parent = get_ancestors( $keyc, 'product_cat' );
				if ( $get_top_parent ) {
					$keyc = $get_top_parent;
				}

				if ( is_array( $keyc ) ) {
					foreach ( $keyc as $term_id ) {
						if ( is_array( $key_val ) && array_key_exists( $term_id, $key_val ) ) {
							$account_no = $key_val[ $term_id ];
							break;
						}
					}
				} else {
					if ( is_array( $key_val ) && array_key_exists( $keyc, $key_val ) ) {
						$account_no = $key_val[ $keyc ];
						break;
					}
				}
			}
		}
	}

	return $account_no;
}

/**
 * Fetch Associated Geography Xero Account
 *
 * @param $shipping_zone
 *
 * @return string
 */
function xeroom_invoice_geography_zone( $shipping_zone ) {
	$account_no = '';

	$associated_shipping = get_xero_option( 'xero_associate_shipping_zones' );

	if ( $associated_shipping ) {
		foreach ( $associated_shipping as $key => $value ) {
			if ( is_array( $value ) && array_key_exists( $shipping_zone, $value ) ) {
				$account_no = $value[ $shipping_zone ];
			}
		}
	}

	return $account_no;
}

/**
 * Fetch first product category
 *
 * @param $product_id
 *
 * @return array
 */
function fetch_product_category_id( $product_id ) {
	$product_cat_id = array();
	$terms          = get_the_terms( $product_id, 'product_cat' );

	if ( $terms ) {
		foreach ( $terms as $term ) {
			$product_cat_id[ $term->term_id ] = $term->term_id;
		}
	}

	return $product_cat_id;
}

/**
 * Fetch shipping zone id
 *
 * @param $shipping_id
 *
 * @return null|string
 */
function xeroom_fetch_zone_id( $shipping_id ) {
	global $wpdb;
	$method_id = $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE instance_id = %d", $shipping_id ) );

	return $method_id;
}

add_action( 'woocommerce_order_actions', 'xeroom_add_order_generate_credit_note' );
/**
 * Add "Generate Credit Note" to Order actions
 *
 * @param $actions
 *
 * @return mixed
 */
function xeroom_add_order_generate_credit_note( $actions ) {
	$credit_note = get_xero_option( 'xero_generate_credit_note' );

	if ( ! $credit_note ) {
		return $actions;
	}

	// add "Generate Credit Note" custom action
	$actions['xeroom_generate_credit_note'] = __( 'Generate Credit Note', 'xeroom' );

	return $actions;
}

add_action( 'woocommerce_order_action_xeroom_generate_credit_note', 'xeroom_process_generate_credit_note' );
/**
 * Generate Credit Note in Xero
 *
 * @param $order
 */
function xeroom_process_generate_credit_note( $order ) {
	global $wpdb;

	$errorType = "orderProduct";

	$order_id = $order;
	if ( is_object( $order ) ) {
		$order_id = $order->ID;
	}

	$credit_note = get_xero_option( 'xero_generate_credit_note' );

	if ( ! $credit_note ) {
		return;
	}

	if ( ! can_post_credit_note_to_xero() ) {
		return esc_html( 'Number of Credit Notes sent daily reached. To increase limit go to Xeroom settings.' );
	}

	// Check if is a Cart redirect - if comes from PayPal
	$url        = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$url_search = parse_url( $url );

	$cart_url       = wc_get_page_permalink( 'cart' );
	$woo_url_search = parse_url( $cart_url );

	if ( $url_search['path'] == $woo_url_search['path'] ) {
		return;
	}

	$order = new WC_Order( $order_id );

	$country  = new WC_Countries();
	$orderTax = new WC_Tax();

	//define Tables
	$exportErrorXero = $xero_send_error = "";
	$xeroDebugTable  = $wpdb->prefix . 'xeroom_debug';
	$xeroLicActive   = $wpdb->prefix . "xeroom_license_key_status";
	$taxTableName    = $wpdb->prefix . "xeroom_tax";
	$xeroTime        = date( 'Y-m-d H:i:s' );

	//License Key
	$sql               = "SELECT * FROM " . $xeroLicActive . " WHERE id=1";
	$xeroLicensekeyAct = $wpdb->get_results( $sql );
	$active            = sanitize_text_field( $xeroLicensekeyAct[0]->status );
	$lic_key           = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );
	$lic_method        = sanitize_text_field( $xeroLicensekeyAct[0]->xero_method );

	// Credit Note Status
	$default_credit_status       = 'SUBMITTED';
	$get_xero_credit_note_status = get_xero_option( 'xero_credit_note_status' );
	if ( $get_xero_credit_note_status ) {
		$default_credit_status = 'AUTHORISED';
	}

	//Check License of plugin
	if ( $active == 'active' ) {
		$license_checked = get_xero_option( 'xero_license_status' );
		if ( $license_checked != 'expired' ) {

			//Items Details
			$totalItems    = $order->get_item_count();
			$orderCurency  = $order->get_currency();
			$usedCupons    = "Coupon used " . implode( ", ", $order->get_coupon_codes() );
			$shippingPrice = $order->get_shipping_total();
			$totalDiscount = $order->get_total_discount();

			// User Address
			$shipAddress     = apply_filters( 'xeroom_shipping_address', $order->get_address(), $order_id, 'invoice' );
			$allCountry      = $country->get_countries();
			$countryFullName = $allCountry[ $shipAddress['country'] ];
			$allState        = $country->get_states( $shipAddress['country'] );
			$stateFullName   = '';
			if ( $allState ) {
				$stateFullName = $allState[ $shipAddress['state'] ];
			} else {
				$stateFullName = $order->get_billing_state();
			}

			// Prices include tax amount
			$included_tax = false;
			if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) && wc_tax_enabled() ) {
				$included_tax = true;
			}

			// Tax Rate Used
			$getTaxRate     = array(
				'country'   => $shipAddress['country'],
				'state'     => $shipAddress['state'],
				'city'      => $shipAddress['city'],
				'postcode'  => $shipAddress['postcode'],
				'tax_class' => ''
			);
			$taxRatePercent = $orderTax->find_rates( $getTaxRate );
			if ( count( $taxRatePercent ) != 0 && wc_tax_enabled() ) {
				foreach ( $taxRatePercent as $taxKey => $taxValue ) {
					$taxValue = $taxValue;
				}
				$taxOnWholeCart = $taxValue;
			} else {
				$taxOnWholeCart = array();
			}

			// Xero Connectivity Api Credentials------------
			$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
			$getApiCredentials = $wpdb->get_results( $query );
			$xeroApiKey        = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
			$xeroApiSecret     = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
			$salesAccount      = esc_attr( $getApiCredentials[0]->sales_account );
			$BankCode          = esc_attr( $getApiCredentials[0]->bank_code );
			$TaxCode           = sanitize_text_field( $getApiCredentials[0]->tax_code );
			$AssetCode         = esc_attr( $getApiCredentials[0]->asset_code );
			$SoldCode          = esc_attr( $getApiCredentials[0]->sold_code );
			$StockMaster       = sanitize_text_field( $getApiCredentials[0]->stock_master );
			$productMaster     = sanitize_text_field( $getApiCredentials[0]->product_master );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$oder_gateway_code = $order->get_payment_method();
			} else {
				$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
			}

			if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
				$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
			}

			/**
			 * Get shipping cost code
			 */
			$shippingCode = get_xero_option( 'xero_default_shipping_costs_code' );

			/**
			 * Get Use Extra Sales Accounts - Shipping
			 */
			$use_extra_sales_accounts = get_xero_option( 'xero_use_extra_sales_account' );
			if ( $order->get_shipping_methods() && $use_extra_sales_accounts && 'geography_zones' == $use_extra_sales_accounts ) {
				$zone_id = xero_fetch_order_zone_id( $order );
				if ( ! empty( $zone_id ) ) {
					$shipping_associated_code = xeroom_invoice_geography_zone( $zone_id );
					if ( ! empty( $shipping_associated_code ) ) {
						$salesAccount = $shipping_associated_code;
					}
				}
			}

			include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
			if ( ! defined( 'BASE_PATH' ) ) {
				define( 'BASE_PATH', dirname( __FILE__ ) );
			}

			if ( ! defined( 'XERO_KEY' ) ) {
				define( 'XERO_KEY', $xeroApiKey );
			}

			if ( ! defined( 'XERO_SECRET' ) ) {
				define( 'XERO_SECRET', $xeroApiSecret );
			}

			if ( ! defined( 'PUBLIC_KEY' ) ) {
				define( 'PUBLIC_KEY', '' );
			}

			if ( ! defined( 'PRIVATE_KEY' ) ) {
				define( 'PRIVATE_KEY', '' );
			}

			if ( ! defined( 'FORMAT' ) ) {
				define( 'FORMAT', 'json' );
			}
			$oauth2 = get_xero_option( 'xero_oauth_options' );
			xeroom_check_xero_token( $oauth2 );
			$xero = new Xero( $xeroApiKey, $xeroApiSecret, PUBLIC_KEY, PRIVATE_KEY, 'json', $oauth2 );

			// Xero Connectivity Api Credentials------------
			$ordersList   = $order->get_items();
			$orderDetails = array();
			$oKeyValue    = array();
			$countOrder   = 0;

			$fetch_tax_method           = get_xero_option( 'xero_tax_method' );
			$fetch_saved_tax            = get_xero_option( 'xero_tax_methods' );
			$fetch_taxes_association    = get_xero_option( 'xero_taxes_association' );
			$shipping_price_code        = get_xero_option( 'xero_shipping_price_code' );
			$shipping_price_description = get_xero_option( 'xero_shipping_price_description' );
			$xero_show_shipping_details = get_xero_option( 'xero_show_shipping_details' );

			$xero_shipping_price_code = 'shipping_price';
			if ( $shipping_price_code ) {
				$xero_shipping_price_code = esc_attr( $shipping_price_code );
			}

			if ( $xero_show_shipping_details ) {
				$xero_shipping_price_description = strip_tags( $order->get_shipping_method() );
			} else {
				$xero_shipping_price_description = 'Shipping Price';
				if ( $shipping_price_description ) {
					$xero_shipping_price_description = esc_attr( $shipping_price_description );
				}
			}

			$product_tax        = 'standard';
			$item_tax_rate_id   = '';
			$item_tax_rate_rate = 0;

			foreach ( $ordersList as $singleorderskey => $singleorders ) {
				$orderDetails[ $countOrder ]['name']          = $singleorders['name'];
				$orderDetails[ $countOrder ]['_product_id']   = $singleorders['product_id'];
				$orderDetails[ $countOrder ]['_variation_id'] = $singleorders['variation_id'];
				$orderDetails[ $countOrder ]['_qty']          = $singleorders['quantity'];
				$orderDetails[ $countOrder ]['item_price']    = $order->get_item_total( $singleorders, false, true );
				$products_ids[ $singleorders['product_id'] ]  = $singleorders['quantity'];
				// Fetch Order Item Tax
				if ( $fetch_tax_method && wc_tax_enabled() ) {
					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$simple_tax = $singleorders->get_tax_class();
					} else {
						$simple_tax = get_post_meta( $singleorders['product_id'], '_tax_class', true );
					}

					if ( ! empty( $simple_tax ) ) {
						$product_tax = $simple_tax;
					} else {
						$product_tax = 'standard';
					}

					switch ( $fetch_tax_method ) {
						case "xero_simple_tax" :
							if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $product_tax . '_taxmethods', $fetch_saved_tax ) ) {
								$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
								$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
								$orderDetails[ $countOrder ]['total_tax']     = xero_fetch_tax_complex_amount( $singleorders->get_taxes() );
							}
							break;
						case "xero_complex_tax" :
							$tax_data = $singleorders->get_taxes();
							foreach ( $order->get_taxes() as $tax_item ) {
								$rate_id        = $tax_item->get_rate_id();
								$item_total_tax = $tax_data['total'][ $rate_id ];
								if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
									$item_tax_rate_id = $rate_id;
									break;
								}
							}

							if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {
								$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
								$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
								$orderDetails[ $countOrder ]['total_tax']     = xero_fetch_tax_complex_amount( $singleorders->get_taxes() );
							}
							break;
					}
				}

				if ( 'w' == $productMaster ) {
					if ( wc_tax_enabled() ) {
						if ( ! $fetch_tax_method ) {
							$tax_data         = $singleorders->get_taxes();
							$item_tax_rate_id = $tax_name = '';
							foreach ( $order->get_taxes() as $tax_item ) {
								$rate_id = $tax_item->get_rate_id();

								if ( ! empty( $tax_data['total'][ $rate_id ] ) ) {
									$item_tax_rate_rate = $tax_data['total'][ $rate_id ];
									$tax_name           = xero_fetch_tax_rate_name( $rate_id );
									if ( isset( $item_tax_rate_rate ) ) {
										$item_tax_rate_id = xero_tax_type_code( $tax_name );
									}
								}
							}

							if ( ! empty( $item_tax_rate_id ) ) {
								$orderDetails[ $countOrder ]['tax_class'] = $item_tax_rate_id;
								if ( ! empty( $item_tax_rate_id ) ) {
									$orderDetails[ $countOrder ]['total_tax'] = ! empty( $item_tax_rate_rate ) ? $item_tax_rate_rate : 0;
								}
							}
						}
					} else {
						$orderDetails[ $countOrder ]['tax_class'] = 'NONE';
						$orderDetails[ $countOrder ]['total_tax'] = 0;
					}
				}

				$countOrder ++;
			}

			$oderList  = array();
			$uniqueTax = "";
			// Create new tax here
			if ( count( $taxOnWholeCart ) != 0 ) {
				$getAllTax = $xero->TaxRates();
				xeroom_check_connection_message( $getAllTax, $order_id );
				if ( isset( $taxOnWholeCart['rate'] ) ) {
					$taxRate = $taxOnWholeCart['rate'];
					if ( isset( $getAllTax['TaxRates']['TaxRate'] ) ) {
						foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
							if ( $allTaxesNow['Name'] == $TaxCode ) {
								$uniqueTax      = $allTaxesNow['TaxType'];
								$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
								break;
							}
						}
					}
				}

				if ( $uniqueTax == "" ) {
					foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
						if ( $allTaxesNow['TaxComponents']['TaxComponent']['Rate'] == $taxRate ) {
							$uniqueTax      = $allTaxesNow['TaxType'];
							$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
							break;
						}
					}
				}
				if ( $uniqueTax == "" ) {
					$taxName     = "Default Xeroom Sales Tax";
					$query       = "SELECT * FROM `" . $taxTableName . "` WHERE `tax_rate` ='" . $taxRate . "'";
					$getTaxRates = $wpdb->get_results( $query );
					if ( count( $getTaxRates ) > 0 ) {
						$uniqueTax = $getTaxRates[0]->tax_type;
					} else {
						$xero_rate = array(
							"TaxRate" => array(
								"Name"          => "$taxName",
								"ReportTaxType" => 'OUTPUT',
								"TaxComponents" => array(
									"TaxComponent" => array(
										"Name" => "VAT",
										"Rate" => $taxRate
									)
								)
							)
						);
						$taxResult = $xero->TaxRates( $xero_rate );
						if ( ! empty( $taxResult ) ) {
							if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $er = 0; $er < count( $errD ); $er ++ ) {
									$errorMessage = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $er ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							} else if ( isset( $taxResult['Status'] ) && $taxResult['Status'] == "OK" ) {
								$uniqueTax = $taxResult['TaxRates']['TaxRate']['TaxType'];
								$wpdb->insert( $taxTableName,
									array(
										'tax_name' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['Name'] ),
										'tax_rate' => sanitize_text_field( $taxRate ),
										'tax_type' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['TaxType'] )
									)
								);
								update_xero_option( 'xero_connection_status', 'active' );
								update_xero_option( 'xero_connection_status_message', '' );
							} else {
								returnErrorMessageByXero( $order_id, $taxResult, $xeroTime, $errorType );
							}
						}
					}
				}
			}


			// Get items by sku
			$where_skus = array();
			for ( $i = 0; $i < count( $orderDetails ); $i ++ ) {
				if ( isset( $orderDetails[ $i ]['_variation_id'] ) && $orderDetails[ $i ]['_variation_id'] != 0 ) {
					$unitPrice = get_post_meta( $orderDetails[ $i ]['_variation_id'] );
				} else {
					$unitPrice = get_post_meta( $orderDetails[ $i ]['_product_id'] );
				}

				if ( $unitPrice['_sku'][0] != "" ) {
					$sku = $unitPrice['_sku'][0];
				} else {
					$sku = $orderDetails[ $i ]['_product_id'];
				}
				$where_skus[] = xeroom_reduce_sku_length( $sku );
			}

			/**
			 * Get products with SKU Code
			 */
			$oauth2 = get_xero_option( 'xero_oauth_options' );
			$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

			$config->setHost( "https://api.xero.com/api.xro/2.0" );

			$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
				new GuzzleHttp\Client(),
				$config
			);

			$xero_tenant_id = $oauth2['tenant_id'];
			$query_string   = 'Code=="' . implode( '" OR Code=="', $where_skus ) . '"';

			$xero_item_data = array();
			try {
				$xero_result_item = $apiInstance->getItems( $xero_tenant_id, null, $query_string );
				if ( $xero_result_item ) {
					foreach ( $xero_result_item as $data_item ) {
						$sku_code = $data_item->getCode();

						$xero_item_data[ $sku_code ]['Code']                               = $sku_code;
						$xero_item_data[ $sku_code ]['Name']                               = $data_item->getName();
						$xero_item_data[ $sku_code ]['Description']                        = $data_item->getDescription();
						$xero_item_data[ $sku_code ]['PurchaseDescription']                = $data_item->getPurchaseDescription();
						$xero_item_data[ $sku_code ]['ItemID']                             = $data_item->getItemId();
						$xero_item_data[ $sku_code ]['QuantityOnHand']                     = $data_item->getQuantityOnHand();
						$xero_item_data[ $sku_code ]['SalesDetails']['UnitPrice']          = $data_item->getSalesDetails()['unit_price'];
						$xero_item_data[ $sku_code ]['SalesDetails']['AccountCode']        = $data_item->getSalesDetails()['account_code'];
						$xero_item_data[ $sku_code ]['SalesDetails']['TaxType']            = $data_item->getSalesDetails()['tax_type'];
						$xero_item_data[ $sku_code ]['PurchaseDetails']['UnitPrice']       = $data_item->getPurchaseDetails()['unit_price'];
						$xero_item_data[ $sku_code ]['PurchaseDetails']['AccountCode']     = $data_item->getPurchaseDetails()['account_code'];
						$xero_item_data[ $sku_code ]['PurchaseDetails']['COGSAccountCode'] = $data_item->getPurchaseDetails()['cogs_account_code'];
					}
				}
			} catch ( Exception $e ) {
				echo 'Exception when calling AccountingApi->getItems: ', $e->getMessage(), PHP_EOL;
			}

			// End Create new tax here
			$xero_invoice_items = array();
			for ( $i = 0; $i < count( $orderDetails ); $i ++ ) {
				/**
				 * Add product to associated category
				 */
				if ( $use_extra_sales_accounts && 'product_categories' == $use_extra_sales_accounts ) {
					$product_categories = fetch_product_category_id( $orderDetails[ $i ]['_product_id'] );

					$category_associated_account = xeroom_product_category_associated( $product_categories );
					if ( ! empty( $category_associated_account ) ) {
						$salesAccount = $category_associated_account;
					}
				}

				if ( isset( $orderDetails[ $i ]['_variation_id'] ) && $orderDetails[ $i ]['_variation_id'] != 0 ) {
					$unitPrice = get_post_meta( $orderDetails[ $i ]['_variation_id'] );
					$itemId    = $orderDetails[ $i ]['_variation_id'];
					$product   = wc_get_product( $orderDetails[ $i ]['_variation_id'] );

					$allProductAttr = "";

					if ( ! empty( $product->get_attribute_summary() ) ) {
						$allProductAttr .= ', ' . $product->get_attribute_summary();
					}

					if ( empty( $product->get_attribute_summary() ) ) {
						$productAttributes = get_post_meta( $orderDetails[ $i ]['_product_id'], '_product_attributes' );

						if ( $productAttributes ) {
							foreach ( $productAttributes[0] as $key => $value ) {
								$attributeName  = get_taxonomy( $value['name'] );
								$attributeName  = $attributeName->labels->name;
								$slugValue      = get_term_by( 'slug', $attributeName->name, $value['name'] );
								$allProductAttr .= ', ' . $attributeName . ': ' . $slugValue->name;
							}
						}
					}
				} else {
					$unitPrice = get_post_meta( $orderDetails[ $i ]['_product_id'] );
					$itemId    = $orderDetails[ $i ]['_product_id'];
					$product   = wc_get_product( $orderDetails[ $i ]['_product_id'] );
				}

				if ( $unitPrice['_sku'][0] != "" ) {
					$sku = $unitPrice['_sku'][0];
				} else {
					$sku = $orderDetails[ $i ]['_product_id'];
				}

				$sku = xeroom_reduce_sku_length( $sku );

				if ( isset( $unitPrice['_sale_price'][0] ) && $unitPrice['_sale_price'][0] != "" ) {
					$unitPriceNow = $unitPrice['_sale_price'][0];
					if ( $allProductAttr == "" ) {
						$allProductAttr = ', Full Price: ' . $unitPrice['_regular_price'][0];
					} else {
						$allProductAttr .= ', Full Price: ' . $unitPrice['_regular_price'][0];
					}
				} else {
					$unitPriceNow = $unitPrice['_regular_price'][0];
				}

				$checkwithoutStock = $xero->Items( $sku );
				xeroom_check_connection_message( $checkwithoutStock, $order_id );

				$unitPriceNow = $orderDetails[ $i ]['item_price'];
//				$unitPriceNow = $product->get_price_excluding_tax();
				$unitPriceNow = number_format( (float) $unitPriceNow, 2, '.', '' );
				if ( $allProductAttr != "" ) {
					$descriptionName = $orderDetails[ $i ]['name'] . $allProductAttr;
					if ( $productMaster == "w" ) {
						$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'] . $allProductAttr;
					}
				} else {
					$descriptionName = $orderDetails[ $i ]['name'];
					if ( $productMaster == "w" ) {
						$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'];
					}
				}

				$oderList[ $i ]['Quantity'] = $orderDetails[ $i ]['_qty'];
				if ( $productMaster == "w" || $productMaster == "n" ) {
					$oderList[ $i ]['UnitAmount'] = $unitPriceNow;
				}
				$oderList[ $i ]['ItemCode']     = $sku;
				$oderList[ $i ]['DiscountRate'] = 0;
				if ( $uniqueTax != "" ) {
					$oderList[ $i ]['TaxType'] = $uniqueTax;
				}

				$saved_product_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_product_account', true );
				if ( $saved_product_account ) {
					$oderList[ $i ]['AccountCode'] = $saved_product_account;
				} else {
					$oderList[ $i ]['AccountCode'] = $salesAccount;
				}

				$saved_cost_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_cost_account', true );
				if ( $saved_cost_account ) {
					$oderList[ $i ]['COGSAccountCode'] = $saved_cost_account;
				} else {
					$oderList[ $i ]['COGSAccountCode'] = $SoldCode;
				}

				$saved_inventory_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_inventory_account', true );
				if ( $saved_inventory_account ) {
					$oderList[ $i ]['InventoryAssetAccountCode'] = $saved_inventory_account;
				} else {
					$oderList[ $i ]['InventoryAssetAccountCode'] = $AssetCode;
				}

				$post_details = get_post( $orderDetails[ $i ]['_product_id'] );
				$xItemName    = xeroom_reduce_item_name_length( $post_details->post_title );
				if ( $xItemName == "" ) {
					$xItemName = "No Name_" . rand();
				}

				// Add tax Rate name
				if ( isset( $orderDetails[ $i ]['tax_class'] ) && '' != $orderDetails[ $i ]['tax_class'] ) {
					$oderList[ $i ]['TaxType'] = $orderDetails[ $i ]['tax_class'];
					if ( $productMaster == "w" ) {
						$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['total_tax'];
					}
				}

				if ( 0 == $orderDetails[ $i ]['get_subtotal_tax'] && class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
					$oderList[ $i ]['TaxType'] = 'NONE';
				}

				$xItemDesc  = $post_details->post_content;
				$xItemStock = $unitPrice['_stock'][0];
				/* Stock Master */
				if ( $StockMaster == "w" ) {
					if ( $xItemStock == 0 || $xItemStock == "" ) {
						$createItemWithoutStock = array(
							"Item"            => array(
								"Code"        => $sku,
								"Name"        => $xItemName,
								"Description" => $descriptionName
							),
							"SalesDetails"    => array(
								"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
								"AccountCode" => $oderList[ $i ]['AccountCode']
							),
							"PurchaseDetails" => array(
								"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
								"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
							)
						);

						if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
							$xero_desc = $descriptionName;
							if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
								$xero_desc = $xero_item_data[ $sku ]['Description'];
							}
							$createItemWithoutStock['Item']['Description'] = $xero_desc;

							$createItemWithoutStock['Item']['Name']                         = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;
							$createItemWithoutStock['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
							$createItemWithoutStock['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
						}

						array_push( $xero_invoice_items, $createItemWithoutStock['Item'] );


						$oderList[ $i ]['Description'] = $descriptionName;
						if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
							$xero_desc = '-';
							if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
								$xero_desc = $xero_item_data[ $sku ]['Description'];
							}
							$oderList[ $i ]['Description'] = $xero_desc;
							$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
						}

					} else {
						$newItemsCode = array(
							"Item" => array(
								"Code"                      => $sku,
								"Name"                      => $xItemName,
								"Description"               => $descriptionName,
								"PurchaseDescription"       => $xItemName,
								"IsTrackedAsInventory"      => false,
								"IsSold"                    => true,
								"IsPurchased"               => true,
								"QuantityOnHand"            => $xItemStock,
								"TotalCostPool"             => $xItemStock * $unitPriceNow,
								"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
								"SalesDetails"              => array(
									"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
									"AccountCode" => $oderList[ $i ]['AccountCode'],
									"TaxType"     => $orderDetails[ $i ]['tax_class_new']
								),
								"PurchaseDetails"           => array(
									"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
									"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
								)
							)
						);

						if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
							$xero_desc = '-';
							if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
								$xero_desc = $xero_item_data[ $sku ]['Description'];
							}
							$newItemsCode['Item']['Description']                  = $xero_desc;
							$newItemsCode['Item']['PurchaseDescription']          = '' != $xero_item_data[ $sku ]['PurchaseDescription'] ? $xero_item_data[ $sku ]['PurchaseDescription'] : $xItemName;
							$newItemsCode['Item']['Name']                         = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;
							$newItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
							$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
						} else {
							if ( $productMaster == "w" ) {
								$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
							}
						}

						array_push( $xero_invoice_items, $newItemsCode['Item'] );
					}
				} else if ( $StockMaster == "x" ) {
					if ( is_array( $xero_item_data[ $sku ] ) ) {
						if ( isset( $xero_item_data[ $sku ]['QuantityOnHand'] ) ) {
							$quantityI = $xero_item_data[ $sku ]['QuantityOnHand'];
							$orderPro  = wc_get_product( $itemId );
							/**
							 * Set product Qty. in Woo
							 */
							$newQuantity = $quantityI - $orderDetails[ $i ]['_qty'];
							wc_update_product_stock( $orderPro, $newQuantity );

							if ( $productMaster == "x" ) {
								if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
									$oderList[ $i ]['Description'] = $xero_item_data[ $sku ]['Description'];
								}

								if ( $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ) {
									$oderList[ $i ]['UnitAmount'] = $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'];
								}

							}
						} else {
							$simpleItemCode = array(
								"Item" => array(
									"Name"                => $xItemName,
									"Code"                => $sku,
									"Description"         => $descriptionName,
									"PurchaseDescription" => $descriptionName,
									"SalesDetails"        => array(
										"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
										"AccountCode" => $oderList[ $i ]['AccountCode']
									),
									"PurchaseDetails"     => array(
										"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
										"AccountCode" => $oderList[ $i ]['COGSAccountCode']
									)
								)
							);

							if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
									$xero_desc = $xero_item_data[ $sku ]['Description'];
								}
								$simpleItemCode['Item']['Description'] = $xero_desc;
								$simpleItemCode['Item']['Name']        = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

								$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
								$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
							} else {
								if ( $productMaster == "w" ) {
									$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
								}
							}

							array_push( $xero_invoice_items, $simpleItemCode['Item'] );

							$oderList[ $i ]['Description'] = $descriptionName;
							$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
							if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
									$xero_desc = $xero_item_data[ $sku ]['Description'];
								}
								$oderList[ $i ]['Description'] = $xero_desc;
								$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
							}
						}
					} else {
						$simpleItemCode = array(
							"Item"            => array(
								"Code"        => $sku,
								"Name"        => $xItemName,
								"Description" => $descriptionName
							),
							"SalesDetails"    => array(
								"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
								"AccountCode" => $oderList[ $i ]['AccountCode']
							),
							"PurchaseDetails" => array(
								"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
								"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
							)
						);

						if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
							$xero_desc = '-';
							if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
								$xero_desc = $xero_item_data[ $sku ]['Description'];
							}
							$simpleItemCode['Item']['Description'] = $xero_desc;
							$simpleItemCode['Item']['Name']        = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

							$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
							$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
						} else {
							if ( $productMaster == "w" ) {
								$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
							}
						}

						array_push( $xero_invoice_items, $simpleItemCode['Item'] );

						$oderList[ $i ]['Description'] = $descriptionName;
						$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
						if ( $productMaster == "x" && isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
							$xero_desc = '-';
							if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
								$xero_desc = $xero_item_data[ $sku ]['Description'];
							}
							$oderList[ $i ]['Description'] = $xero_desc;
							$oderList[ $i ]['UnitAmount']  = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
						}
					}
				} else {
					$simpleItemsCode = array(
						"Item"            => array(
							"Code"        => $sku,
							"Name"        => $xItemName,
							"Description" => $descriptionName
						),
						"SalesDetails"    => array(
							"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
							"AccountCode" => $oderList[ $i ]['AccountCode']
						),
						"PurchaseDetails" => array(
							"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
							"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
						)
					);

					if ( isset( $xero_item_data[ $sku ]['ItemID'] ) ) {
						$xero_desc = '-';
						if ( ! empty( $xero_item_data[ $sku ]['Description'] ) ) {
							$xero_desc = $xero_item_data[ $sku ]['Description'];
						}
						$simpleItemsCode['Item']['Description'] = $xero_desc;

						$simpleItemsCode['Item']['Name'] = '' != $xero_item_data[ $sku ]['Name'] ? $xero_item_data[ $sku ]['Name'] : $xItemName;

						$simpleItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['SalesDetails']['UnitPrice'] : $unitPriceNow;
						$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] ? $xero_item_data[ $sku ]['PurchaseDetails']['UnitPrice'] : $unitPriceNow;
					} else {
						if ( $productMaster == "w" ) {
							$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
						}
					}

					array_push( $xero_invoice_items, $simpleItemsCode['Item'] );
				}
			}

			if ( $order->get_shipping_method() ) {
				$newItemsCodeShip = array(
					"Item" => array(
						"Code"        => $xero_shipping_price_code,
						"Description" => $xero_shipping_price_description
					)
				);
				array_push( $xero_invoice_items, $newItemsCodeShip['Item'] );
			}

			if ( $totalDiscount != 0 ) {
				foreach ( $order->get_coupon_codes() as $coupon_name ) {
					if ( ! $coupon_name ) {
						continue;
					}
					// Get an instance of WC_Coupon object in an array(necesary to use WC_Coupon methods)
					$coupons_obj = new WC_Coupon( $coupon_name );

					$coupon_description = esc_html__( 'Cart Fixed Discount Coupon' );
					if ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
						$coupon_description = esc_html__( 'Product Fixed Discount coupon' );
					}

					if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
						$coupon_name = esc_html( $coupon_xero_code );
					}

					if ( ! empty( $coupons_obj->get_description() ) ) {
						$coupon_description .= esc_html( $coupons_obj->get_description() );
					}

					$new_discounts_items['Item'] = array(
						"Code"        => xeroom_reduce_coupon_length( $coupon_name ),
						"Description" => $coupon_description
					);

					array_push( $xero_invoice_items, $new_discounts_items['Item'] );
				}

			}

			if ( $order->get_items( array( 'fee' ) ) ) {
				$newItemsCodeDis = array(
					"Item" => array(
						"Code"        => "fee_price",
						"Description" => "Fee Price"
					)
				);

				array_push( $xero_invoice_items, $newItemsCodeDis['Item'] );
			}

			/**
			 * Send Invoice Items as Batch Start
			 */
			xeroom_send_batch_items_before_invoice( $xero_invoice_items );
			/**
			 * Send Invoice Items as Batch Start
			 */

			// Fetch shippinh tax class
			$item_tax_rate_id = '';
			$shipping_tax     = array();
			if ( $fetch_tax_method && wc_tax_enabled() ) {
				$shipping_tax_class = 'standard';
				$tax_data           = array();
				foreach ( $order->get_items( array( 'shipping' ) ) as $item ) {
					if ( '' != $item->get_tax_class() ) {
						$shipping_tax_class = $item->get_tax_class();
					}
					$tax_data = $item->get_taxes();
				}

				$simple_tax = $order->get_shipping_tax();

				if ( $simple_tax ) {
					$product_tax = $simple_tax;
				} else {
					$product_tax = 'standard';
				}

				switch ( $fetch_tax_method ) {
					case "xero_simple_tax" :
						if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $shipping_tax_class . '_taxmethods', $fetch_saved_tax ) ) {
							$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $shipping_tax_class . '_taxmethods' ] );
						}
						break;
					case "xero_complex_tax" :
						foreach ( $order->get_taxes() as $tax_item ) {
							$rate_id        = $tax_item->get_rate_id();
							$item_total_tax = isset( $tax_data['total'][ $rate_id ] ) ? $tax_data['total'][ $rate_id ] : '';

							if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
								$item_tax_rate_id = $rate_id;
								break;
							}
						}

						if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {
							$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
						}
						break;
				}

				if ( 0 == $simple_tax ) {
					$shipping_tax['tax_class'] = 'NONE';
				}

				if ( 'inherit' == $shipping_tax_class ) {
					$shipping_class            = reset( $orderDetails );
					$shipping_tax['tax_class'] = $shipping_class['tax_class'];
				}

			} else {
				$shipping_tax['tax_class'] = 'NONE';
			}

			if ( $order->get_shipping_method() ) {
				$oderList[ count( $orderDetails ) ]['Description'] = $xero_shipping_price_description;
				$oderList[ count( $orderDetails ) ]['Quantity']    = 1;
				$oderList[ count( $orderDetails ) ]['UnitAmount']  = $shippingPrice;
//						$oderList[ count( $orderDetails ) ]['DiscountRate'] = 0;
				$oderList[ count( $orderDetails ) ]['ItemCode'] = $xero_shipping_price_code;
				if ( in_array( 'shipping', $taxOnWholeCart ) && $taxOnWholeCart['shipping'] == "yes" ) {
					// Add tax Rate name
//					if ( $uniqueTax != "" ) {
//						$oderList[ count( $orderDetails ) ]['TaxType'] = $uniqueTax;
//					}
				}
				if ( ! empty( $shipping_tax ) ) {
					$oderList[ count( $orderDetails ) ]['TaxType'] = $shipping_tax['tax_class'];
				}
				$oderList[ count( $orderDetails ) ]['AccountCode'] = $shippingCode && '' != $shippingCode ? $shippingCode : $salesAccount;
			}
			if ( $lic_method != "" ) {
				if ( $totalDiscount != 0 ) {
					$products_tax_type         = reset( $orderDetails );
					$discount_tax['tax_class'] = $products_tax_type['tax_class'];

					if ( $order->get_shipping_method() ) {
						$nextCount = count( $orderDetails ) + 1;
					} else {
						$nextCount = count( $orderDetails );
					}

					$coupon_quantity = 0;

					foreach ( $order->get_coupon_codes() as $coupon_name ) {
						if ( ! $coupon_name ) {
							continue;
						}
						$coupons_obj = new WC_Coupon( $coupon_name );

						$coupon_order_id = wpr_fetch_coupon_item_id( $order_id, $coupon_name );

						$order_discount_amount     = wc_get_order_item_meta( $coupon_order_id, 'discount_amount', true );
						$order_discount_tax_amount = wc_get_order_item_meta( $coupon_order_id, 'discount_amount_tax', true );

//								$oderList[ $nextCount ]['UnitAmount'] = 0;
						$oderList[ $nextCount ]['Quantity'] = 1;
						if ( $coupons_obj->get_discount_type() == 'fixed_cart' ) {
							$oderList[ $nextCount ]['Description'] = esc_html__( 'Cart Fixed Discount coupon' );
							if ( $included_tax ) {
								$oderList[ $nextCount ]['UnitAmount'] = - $order_discount_amount;
							} else {
								$oderList[ $nextCount ]['UnitAmount'] = - $coupons_obj->get_amount();
							}
						} elseif ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
							$coupon_applied                        = array_intersect( array_keys( $products_ids ), $coupons_obj->get_product_ids() );
							$oderList[ $nextCount ]['Description'] = esc_html__( 'Fixed product discount' );
							if ( $included_tax ) {
								$oderList[ $nextCount ]['UnitAmount'] = - $order_discount_amount;
							} else {
								$oderList[ $nextCount ]['UnitAmount'] = - $coupons_obj->get_amount();
							}

							if ( count( $coupon_applied ) > 0 ) {
								foreach ( $products_ids as $product_id => $quantity ) {
									if ( in_array( $product_id, $coupons_obj->get_product_ids() ) ) {
										$coupon_quantity += $quantity;
									}
								}

								$oderList[ $nextCount ]['Quantity'] = $coupon_quantity;
							}
						} else {
							$coupon_named_description = esc_html__( 'Cart Percent Discount coupon' );
							if ( ! empty( $coupons_obj->get_description() ) ) {
								$coupon_named_description .= ' (' . esc_html( $coupons_obj->get_description() ) . ')';
							}
							$oderList[ $nextCount ]['Description'] = $coupon_named_description;
						}


						if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
							$coupon_name = esc_html( $coupon_xero_code );
						}

						$oderList[ $nextCount ]['ItemCode'] = xeroom_reduce_coupon_length( $coupon_name );

						// Add tax Rate name
						if ( ! empty( $discount_tax ) ) {
							$oderList[ $nextCount ]['TaxType'] = $discount_tax['tax_class'];
						}

						$oderList[ $nextCount ]['AccountCode'] = $salesAccount;

						// Add tracking
//						if ( ! empty( $add_tracking ) && $coupons_obj->get_discount_type() != 'percent' ) {
//							$oderList[ $nextCount ]['Tracking'] = array(
//								'TrackingCategory' => array(
//									'Name'   => esc_attr( $tracking_category ),
//									'Option' => $add_tracking,
//								),
//							);
//						}

						$nextCount ++;
					}
				}
			}

			// Fee tax
			$fee_tax = array();
			if ( $fetch_tax_method && wc_tax_enabled() ) {
				$fee_class            = reset( $orderDetails );
				$fee_tax['tax_class'] = $fee_class['tax_class'];
			}

			// Add fee if exists
			if ( $order->get_items( array( 'fee' ) ) ) {
				$fee_count = count( $oderList );
				foreach ( $order->get_items( array( 'fee' ) ) as $item_type => $item ) {
					$oderList[ $fee_count ]['Description']  = $item->get_name() ? $item->get_name() : "Fee Price";
					$oderList[ $fee_count ]['Quantity']     = 1;
					$oderList[ $fee_count ]['UnitAmount']   = $order->get_item_total( $item, false, true );
					$oderList[ $fee_count ]['DiscountRate'] = 0;
					$oderList[ $fee_count ]['ItemCode']     = "fee_price";
					if ( ! empty( $fee_tax ) ) {
						$oderList[ $fee_count ]['TaxType'] = $fee_tax['tax_class'];
					}

					$oderList[ $fee_count ]['AccountCode'] = $salesAccount;
					$fee_count ++;
				}
			}

			$lineItemsArray = array();
			for ( $i = 0; $i < count( $oderList ); $i ++ ) {
				$lineItemsArray[] = array( "LineItem" => array( $oderList[ $i ] ) );
			}

			$currentDate       = date( 'Y-m-d H:i:s' );
			$xero_contact_name = get_xero_option( 'xero_contact_name' );

			if ( 'xeroom_use_company' == $xero_contact_name ) {
				$xero_add_contact_name = $shipAddress['company'];
			} elseif ( 'xeroom_use_email' == $xero_contact_name ) {
				$xero_add_contact_name = $shipAddress['email'];
			} else {
				$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
			}

			if ( empty( $xero_add_contact_name ) ) {
				$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
			}

			if ( $lic_method != "" ) {
				$credit_note = array(
					array(
						"Type"            => "ACCRECCREDIT",
						"Contact"         => array(
							"Name"         => $xero_add_contact_name,
							"FirstName"    => $shipAddress['first_name'],
							"LastName"     => $shipAddress['last_name'],
							"EmailAddress" => $shipAddress['email'],
							"Phones"       => array(
								"Phone" => array(
									"PhoneType"   => "MOBILE",
									"PhoneNumber" => $shipAddress['phone']
								)
							)
						),
						"Date"            => $currentDate,
						"Status"          => $default_credit_status,
						"CurrencyCode"    => $order->get_currency(),
						"LineAmountTypes" => "Exclusive",
						"Reference"       => xeroom_generate_invoice_reference( $order_id ),
						"LineItems"       => $lineItemsArray
					)
				);

				$credit_note[0]['Contact']['Addresses']['Address'] = array(
					xeroom_client_address_to_sent( $shipAddress, $stateFullName, $countryFullName, $shipAddress['email'], $shipAddress['first_name'], $shipAddress['last_name'], $xero_add_contact_name, $order )
				);
			} else {
				$credit_note = array(
					array(
						"Type"            => "ACCRECCREDIT",
						"Contact"         => array(
							"Name"         => $xero_add_contact_name,
							"FirstName"    => $shipAddress['first_name'],
							"LastName"     => $shipAddress['last_name'],
							"EmailAddress" => $shipAddress['email'],
							"Addresses"    => array(
								"Address" => array(
									array(
										"AddressType"  => "POBOX",
										"AttentionTo"  => "Created using demo version of Xeroom",
										"AddressLine1" => "",
										"AddressLine2" => "",
										"AddressLine3" => "",
										"City"         => "",
										"Region"       => "",
										"Country"      => "",
										"PostalCode"   => ""
									)
								)
							),
							"Phones"       => array(
								"Phone" => array(
									"PhoneType"   => "MOBILE",
									"PhoneNumber" => $shipAddress['phone']
								)
							)
						),
						"Date"            => $currentDate,
						"Status"          => $default_credit_status,
						"CurrencyCode"    => $orderCurency,
						"LineAmountTypes" => "Exclusive",
						"Reference"       => xeroom_generate_invoice_reference( $order_id ),
						"LineItems"       => $lineItemsArray
					)
				);
			}

			if ( $license_checked != 'expired' ) {

				$creditNotesCreated = $xero->CreditNotes( $credit_note );

				if ( isset( $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
					$errD = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
					returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
				} else if ( isset( $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
					$errD = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
					for ( $e = 0; $e < count( $errD ); $e ++ ) {
						$errorMessage = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
						returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
					}
				} else if ( isset( $creditNotesCreated['Status'] ) && $creditNotesCreated['Status'] != "OK" ) {
					returnErrorMessageByXero( $order_id, 'Credit Note was not created', $xeroTime, $errorType );
				}
				// Allocate Credit Note to Invoice No.
				if ( isset( $creditNotesCreated['Status'] ) && $creditNotesCreated['Status'] == "OK" ) {
					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$order->update_meta_data( 'xeroom_cred_note_generated', 1 );
						$order->update_meta_data( 'xeroom_cred_note_id', $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] );
						$order->save();
					} else {
						update_post_meta( $order_id, 'xeroom_cred_note_generated', 1 );
						update_post_meta( $order_id, 'xeroom_cred_note_id', $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] );
					}

					$message = sprintf( __( 'Xero Credit Note Generated.', 'xeroom' ) );
					$order->add_order_note( $message );

					$total_amount = $order->get_total();

					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$invoice_id = $order->get_meta( '_xero_invoice_id' ) ? $order->get_meta( '_xero_invoice_id' ) : $order->get_meta( 'post_content_filtered' );
					} else {
						$order_details = get_post( $order_id );
						$invoice_id    = get_post_meta( $order_id, '_xero_invoice_id', true ) ? get_post_meta( $order_id, '_xero_invoice_id', true ) : $order_details->post_content_filtered;
					}

					$alocateCreditNote = array(
						"Allocation" => array(
							"Invoice"       => array(
								"InvoiceID" => $invoice_id,
							),
							"AppliedAmount" => $total_amount,
							"Date"          => date( 'Y-m-d' ),
						),
					);

					$sendCreditNoteAllocation = $xero->CreditNotes( $alocateCreditNote, array( 'Allocations' => $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] ) );

					if ( isset( $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
						$errD = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
						returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
					} else if ( isset( $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
						$errD = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
						for ( $e = 0; $e < count( $errD ); $e ++ ) {
							$errorMessage = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
							returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
						}
					} else if ( isset( $sendCreditNoteAllocation['Status'] ) && $sendCreditNoteAllocation['Status'] != "OK" ) {
						returnErrorMessageByXero( $order_id, 'Credit Note was not Allocated', $xeroTime, $errorType );
					}

					if ( isset( $sendCreditNoteAllocation['Status'] ) && $sendCreditNoteAllocation['Status'] == "OK" ) {
						if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
							$order->update_meta_data( 'xeroom_cred_note_allocated', 1 );
							$order->save();
						} else {
							update_post_meta( $order_id, 'xeroom_cred_note_allocated', 1 );
						}

						$message = sprintf( __( 'Xero Credit Note Allocated to Invoice.', 'xeroom' ) );
						$order->add_order_note( $message );
					}

					update_xero_option( 'xero_connection_status', 'active' );
					update_xero_option( 'xero_connection_status_message', '' );
					increment_credit_note_counter();
				}
			}
		} else {
			$mMessage = "Your license has expired, please contact xeroom customer support team.";
			returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
		}
	} else {
		$mMessage = "Please use a premium or regular license!";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}
}

add_action( 'woocommerce_order_status_cancelled', 'xeroom_send_credit_note_on_order_cancellation' );
/**
 * Send Credit Note on Order Cancellation
 *
 * @param $order_id
 */
function xeroom_send_credit_note_on_order_cancellation( $order_id ) {
	$order = new WC_Order( $order_id );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_sent       = $order->get_meta( '_xero_invoice_id' ) ? $order->get_meta( '_xero_invoice_id' ) : $order->get_meta( 'xeroom_order_sent', true );
		$credit_note_sent = $order->get_meta( 'xeroom_cred_note_generated' );
	} else {
		$order_sent       = get_post_meta( $order_id, '_xero_invoice_id', true ) ? get_post_meta( $order_id, '_xero_invoice_id', true ) : get_post_meta( $order_id, 'xeroom_order_sent', true );
		$credit_note_sent = get_post_meta( $order_id, 'xeroom_cred_note_generated', true );
	}

	if ( ! $credit_note_sent && $order_sent ) {
		xeroom_process_generate_credit_note( $order_id );
	}
}

/**
 * Determine if a given string ends with a given substring.
 *
 * @param $haystack
 * @param $needles
 *
 * @return bool
 */
function xeroom_ends_with( $haystack, $needles ) {
	foreach ( (array) $needles as $needle ) {
		if ( (string) $needle === substr( $haystack, - strlen( $needle ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Return Xero Bank Account setting
 *
 * @param $order_gateway
 *
 * @return int
 */
function xeroom_get_bank_account_code( $order_gateway ) {
	$gateway_account = 0;
	$order_gateway   = esc_attr( $order_gateway );
	$woo_gateway     = get_xero_option( 'xero_woo_gateway' );

	if ( $woo_gateway ) {
		$gateway_account = $woo_gateway['xero_default_payment'];
		if ( 'lite' != XEROOM_TYPE ) {
			if ( is_array( $woo_gateway ) && array_key_exists( 'xero_' . $order_gateway . '_payment', $woo_gateway ) && $woo_gateway[ 'xero_' . $order_gateway . '_payment' ] > 0 ) {
				$gateway_account = $woo_gateway[ 'xero_' . $order_gateway . '_payment' ];
			}
		}
	}

	return $gateway_account;
}

/**
 * Display Xero Settings tabs
 *
 * @param string $current ]
 */
function xero_settings_tabs( $current = 'general' ) {
	$tabs = array(
		'general'         => 'General',
		'taxes'           => 'Taxes',
		'sync'            => 'Global Inventory Sync',
		'product_sync'    => 'Global Product Sync',
		'invoice_sync'    => 'Invoice Status Sync',
		'ebay_and_amazon' => 'eBay and Amazon'
	);

	echo '<div id="icon-themes" class="icon32"><br></div>';
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $tab => $name ) {
		$class = ( $tab == $current ) ? ' nav-tab-active' : '';
		if ( ! wc_tax_enabled() && 'taxes' == $tab ) {
			continue;
		}

		$tab_id = '';
		if ( 'general' !== $tab ) {
			$tab_id = 'xeroom-page-' . $tab;
		}

		$premium = '';
		if ( 'general' !== $tab && 'taxes' !== $tab && ( 'lite' === XEROOM_TYPE ) ) {
			$premium = ' <label>Premium</label>';
		}

		echo "<a class='nav-tab$class' id='$tab_id' href='?page=add_xero_api_fields&tab=$tab'>$name$premium</a>";
	} ?>
    <div class="button-primary xero-primery" onclick="submitLicKey();" style="margin-left: 325px !important; margin-top: 3px;"><?php echo __( 'Submit', 'xeroom' ); ?></div>
	<?php echo '</h2>';
}

/**
 * Generate Xero Setting Taxes table
 *
 * @param $display_standard_tax
 */
function generate_xero_settings_tax_table( $display_standard_tax ) {
	$xero_taxes_association = get_xero_option( 'xero_taxes_association' );
	$xero_default_taxes     = get_xero_option( 'xero_defined_tax_methods' );

	echo '<table class="wc_tax_rates wc_input_table widefat">';
	?>
    <thead>
    <tr>
        <th width="8%"><?php _e( 'Country&nbsp;code', 'woocommerce' ); ?></th>
        <th width="8%"><?php _e( 'State code', 'woocommerce' ); ?></th>
        <th width="8%"><?php _e( 'Rate&nbsp;%', 'woocommerce' ); ?></th>
        <th width="8%"><?php _e( 'Tax name', 'woocommerce' ); ?></th>
        <th width="8%"><?php _e( 'Shipping', 'woocommerce' ); ?></th>
        <th><?php _e( 'Postcode / ZIP', 'woocommerce' ); ?></th>
        <th><?php _e( 'City', 'woocommerce' ); ?></th>
        <th width="8%"><?php _e( 'Xero Tax Name', 'woocommerce' ); ?></th>
    </tr>
    </thead>
	<?php
	$xero_value = '';
	foreach ( $display_standard_tax as $taxes ) {
		if ( $xero_taxes_association && is_array( $xero_taxes_association ) && array_key_exists( $taxes->tax_rate_id, $xero_taxes_association ) ) {
			$xero_value = $xero_taxes_association[ $taxes->tax_rate_id ];
		}

		echo '<tr>';
		echo sprintf( '<td>%s</td>', $taxes->tax_rate_country );
		echo sprintf( '<td>%s</td>', $taxes->tax_rate_state );
		echo sprintf( '<td>%s</td>', $taxes->tax_rate );
		echo sprintf( '<td>%s</td>', $taxes->tax_rate_name );
		echo sprintf( '<td>%s</td>', $taxes->tax_rate_shipping );
		echo sprintf( '<td>%s</td>', ( isset( $taxes->postcode[0] ) ) ? $taxes->postcode[0] : '' );
		echo sprintf( '<td>%s</td>', ( isset( $taxes->city[0] ) ) ? $taxes->city[0] : '' );
		echo sprintf( '<td><select class="xero_input" name="xero_name_tax_%d" id="xero_name_tax_%d">', $taxes->tax_rate_id, $taxes->tax_rate_id );
		if ( $xero_default_taxes ) {
			echo sprintf( '<option value="">%s</option>', __( 'Select Xero Tax', 'xeroom' ) );
			foreach ( $xero_default_taxes as $xero_saved_taxes ) {
				$selected = '';
				if ( $xero_saved_taxes['Name'] == esc_attr( $xero_value ) ) {
					$selected = ' selected';
				}
				echo sprintf( '<option value="%s"%s>%s</option>', $xero_saved_taxes['Name'], $selected, $xero_saved_taxes['Name'] );
			}
		} else {
			echo sprintf( '<option value="%s">%s</option>', esc_attr( $xero_value ), esc_attr( $xero_value ) );
		}
		echo '</select></td>';
		echo '</tr>';
	}
	echo '<tr><th colspan="8">&nbsp;</th></tr>';
	echo '</table>';
}

/**
 * Fetch rate_id for Xero Complex Tax Association
 *
 * @param $taxes
 *
 * @return int|string
 */
function xero_fetch_tax_complex_rate_id( $taxes ) {
	$rate_id = '';
	if ( ! empty( $taxes ) ) {
		foreach ( $taxes as $tax ) {
			if ( ! empty( $tax ) ) {
				foreach ( $tax as $rate => $nah ) {
					$rate_id = $rate;
					break;
				}
			}
		}
	}

	return $rate_id;
}

/**
 * Fetch total amount for Xero Complex Tax Association
 *
 * @param $taxes
 *
 * @return string
 */
function xero_fetch_tax_complex_amount( $taxes ) {
	$amount = 0;
	if ( ! empty( $taxes ) ) {
		foreach ( $taxes as $tax ) {
			if ( ! empty( $tax ) ) {
				foreach ( $tax as $rate => $val ) {
					if ( ! empty( $val ) ) {
						$amount = $val;
						break;
					}
				}
			}
		}
	}

	return (float) $amount;
}

/**
 * Fetch Tax Type by Tax Name
 *
 * @param $tax_name
 *
 * @return string
 */
function xero_tax_type_code( $tax_name ) {
	$tax_type  = '';
	$getAllTax = get_xero_option( 'xero_defined_tax_methods' );
	if ( $getAllTax ) {
		foreach ( $getAllTax as $taxname ) {
			if ( $tax_name == $taxname['Name'] ) {
				$tax_type = $taxname['TaxType'];
				break;
			}
		}
	}

	return $tax_type;
}

/**
 * Fetch Tax Amount by Tax Name
 *
 * @param $tax_name
 *
 * @return string
 */
function xero_tax_amount_by_name( $tax_name ) {
	$tax_type  = '';
	$getAllTax = get_xero_option( 'xero_defined_tax_methods' );
	if ( $getAllTax ) {
		foreach ( $getAllTax as $taxname ) {
			if ( $tax_name == $taxname['Name'] ) {
				$tax_type = $taxname['EffectiveRate'];
				break;
			}
		}
	}

	return $tax_type;
}

/**
 * Fetch Tax Amount by Tax Type
 *
 * @param $tax_name
 *
 * @return string
 */
function xero_tax_amount_by_type( $tax_name ) {
	$tax_type  = '';
	$getAllTax = get_xero_option( 'xero_defined_tax_methods' );
	if ( $getAllTax ) {
		foreach ( $getAllTax as $taxname ) {
			if ( $tax_name == $taxname['TaxType'] ) {
				$tax_type = $taxname['EffectiveRate'];
				break;
			}
		}
	}

	return $tax_type;
}

/**
 * Fetch Xero Product Name
 *
 * @param $response
 *
 * @return string
 */
function xero_product_name( $response ) {
	$description = '';

	if ( ! empty( $response ) && is_array( $response ) && array_key_exists( 'Items', $response ) && isset( $response['Items']['Item']['Name'] ) ) {
		$description = $response['Items']['Item']['Name'];
	}

	return $description;
}

/**
 * Fetch Xero Product Description
 *
 * @param $response
 *
 * @return string
 */
function xero_product_description( $response ) {
	$description = '';

	if ( ! empty( $response ) && is_array( $response ) && array_key_exists( 'Items', $response ) && isset( $response['Items']['Item']['Description'] ) ) {
		$description = $response['Items']['Item']['Description'];
	}

	return $description;
}

/**
 * Fetch Xero Product Purchase
 *
 * @param $response
 *
 * @return string
 */
function xero_product_purchase_description( $response ) {
	$description = '';

	if ( ! empty( $response ) && is_array( $response ) && array_key_exists( 'Items', $response ) && isset( $response['Items']['Item']['PurchaseDescription'] ) ) {
		$description = $response['Items']['Item']['PurchaseDescription'];
	}

	return $description;
}

/**
 * Fetch Xero Product Purchase Unit Price
 *
 * @param $response
 *
 * @return string
 */
function xero_product_purchase_unit_price( $response ) {
	$unit_price = '';

	if ( ! empty( $response ) && is_array( $response ) && array_key_exists( 'Items', $response ) && isset( $response['Items']['Item']['PurchaseDetails']['UnitPrice'] ) ) {
		$unit_price = $response['Items']['Item']['PurchaseDetails']['UnitPrice'];
	}

	return $unit_price;
}

/**
 * Fetch Xero Product Sales Unit Price
 *
 * @param $response
 *
 * @return string
 */
function xero_product_sales_unit_price( $response ) {
	$unit_price = '';

	if ( ! empty( $response ) && is_array( $response ) && array_key_exists( 'Items', $response ) && isset( $response['Items']['Item']['SalesDetails']['UnitPrice'] ) ) {
		$unit_price = $response['Items']['Item']['SalesDetails']['UnitPrice'];
	}

	return $unit_price;
}

add_action( 'admin_enqueue_scripts', 'my_admin_enqueue' );
/**
 * Enqueue Admin Xero Style
 *
 * @param $hook_suffix
 */
function my_admin_enqueue( $hook_suffix ) {
	if ( 'toplevel_page_add_xero_api_fields' == $hook_suffix || 'xeroom_page_xeroom_debug_page' == $hook_suffix || 'xeroom_page_xeroom_export_woo_xero' == $hook_suffix || 'xeroom_page_xeroom_log_woo_xero' == $hook_suffix ) {
		$apicsspath = esc_url( XEROOM_CSS_PATH . 'xeroom_style.css' );
		wp_enqueue_style( "xeroom_style", $apicsspath );
	}
}

add_filter( 'woocommerce_tax_round', 'pr14475_changed_rounding' );
/**
 * Add rounding fix
 *
 * @param $in
 *
 * @return float
 */
function pr14475_changed_rounding( $in ) {
	return round( $in, wc_get_price_decimals() );
}

add_filter( 'woocommerce_calc_tax', 'pr14475_rounding_taxes' );
/**
 * @param $taxes
 *
 * @return array
 */
function pr14475_rounding_taxes( $taxes ) {
	$taxes = array_map( 'pr14475_rounding', $taxes );

	return $taxes;
}

;
/**
 * @param $in
 *
 * @return float
 */
function pr14475_rounding( $in ) {
	return round( $in, wc_get_price_decimals() );
}

add_action( 'woocommerce_tax_rate_added', 'xeroom_save_update_tax_rates' );
add_action( 'woocommerce_tax_rate_updated', 'xeroom_save_update_tax_rates' );
/**
 * Create or update Xero Tax Rates if Master is set to WOO
 *
 * @param $rate_id
 * @param $tax_rate
 */
function xeroom_save_update_tax_rates() {
	global $wpdb;

	$xeroCredentialTable    = $wpdb->prefix . "xeroom_credentials";
	$sql                    = "SELECT xero_api_key, xero_api_secret, product_master FROM " . $xeroCredentialTable . " WHERE id=1";
	$xeroCredentialsFromTbl = $wpdb->get_results( $sql );

	$xeroApiKey    = $xeroCredentialsFromTbl[0]->xero_api_key;
	$xeroApiSecret = $xeroCredentialsFromTbl[0]->xero_api_secret;
	$productMaster = $xeroCredentialsFromTbl[0]->product_master;

	if ( 'w' == $productMaster ) {
		$errorType = "orderProduct";
		$xeroTime  = date( 'Y-m-d H:i:s' );
		// Fetch and Save Xero Tax rates if Synch Inventory Master is set on Woocommerce
		include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero_api = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		$rates = $wpdb->get_results( "SELECT tax_rate_name,tax_rate FROM `{$wpdb->prefix}woocommerce_tax_rates`" );

		if ( $rates ) {
			foreach ( $rates as $rate ) {
				$xero_rate = array(
					"TaxRate" => array(
						"Name"          => $rate->tax_rate_name,
						"Status"        => "ACTIVE",
						"ReportTaxType" => 'OUTPUT',
						"TaxComponents" => array(
							"TaxComponent" => array(
								"Name" => $rate->tax_rate_name,
								"Rate" => $rate->tax_rate
							)
						)
					)
				);
				$taxResult = $xero_api->TaxRates( $xero_rate, array( 'TaxRate' => true ) );

				if ( is_array( $taxResult ) ) {
					if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
						$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
						returnErrorMessageByXero( 0, $errD, $xeroTime, $errorType );
					} else if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
						$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
						for ( $er = 0; $er < count( $errD ); $er ++ ) {
							$errorMessage = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $er ]['Message'];
							returnErrorMessageByXero( 0, $errorMessage, $xeroTime, $errorType );
						}
					}
				} else {
					returnErrorMessageByXero( 0, "Adding Woocommerce taxes to Xero failed.", $xeroTime, $errorType );
					update_xero_option( 'xero_connection_status_message', 'Adding Woocommerce taxes to Xero failed.' );
					update_xero_option( 'xero_connection_status', 'failed' );

					return;
				}
			}

			// Update Option entry
			$getAllTax = $xero_api->TaxRates();
			if ( $getAllTax && is_array( $getAllTax ) && array_key_exists( 'TaxRates', $getAllTax ) ) {
				if ( isset( $getAllTax['TaxRates']['TaxRate'] ) ) {
					if ( is_array( $getAllTax['TaxRates']['TaxRate'] ) ) {
						$save_taxes = array();
						foreach ( $getAllTax['TaxRates']['TaxRate'] as $xero_tax ) {
							if ( 'ACTIVE' == $xero_tax['Status'] ) {
								array_push(
									$save_taxes,
									array(
										'Name'          => $xero_tax['Name'],
										'TaxType'       => $xero_tax['TaxType'],
										'EffectiveRate' => $xero_tax['EffectiveRate'],
									)
								);
							}
						}
						update_xero_option( 'xero_defined_tax_methods', $save_taxes );
					} else {
						update_xero_option( 'xero_defined_tax_methods', $getAllTax['TaxRates']['TaxRate'] );
					}
				}
				update_xero_option( 'xero_connection_status', 'active' );
				update_xero_option( 'xero_connection_status_message', '' );
			}
		}
	}
}

/**
 * Fetch tax rate name
 *
 * @param $rate_id
 *
 * @return null|string
 */
function xero_fetch_tax_rate_name( $rate_id ) {
	global $wpdb;
	$tax_rate_name = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d", $rate_id ) );

	return $tax_rate_name;
}

/**
 * Does the Invoice exists in Xero
 *
 * @param $order_id
 * @param $xeroApiKey
 * @param $xeroApiSecret
 *
 * @return bool
 */
function xero_invoice_exists( $order_id, $xeroApiKey, $xeroApiSecret ) {
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order      = new WC_Order( $order_id );
		$invoice_id = $order->get_meta( 'post_content_filtered' );
		$invoice_no = $order->get_meta( 'post_content' );
	} else {
		$order_details = get_post( $order_id );
		$invoice_id    = $order_details->post_content_filtered;
		$invoice_no    = $order_details->post_content;
	}

	if ( '' != trim( $invoice_no ) ) {
		include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero_api = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		if ( ! empty( $invoice_no ) ) {
			$invoice_no = $invoice_no;
		} else {
			$invoice_no = xeroom_invoice_number( $order_id );
		}

		$invoice_info = array(
			array(
				"InvoiceNumber" => $invoice_no
			)
		);

		$xero_check = $xero_api->Invoices( $invoice_info );
		xeroom_check_connection_message( $xero_check, $order_id );

		if ( $xero_check && ! isset( $xero_check['ErrorNumber'] ) ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * Fetch order zone id
 *
 * @param $order
 *
 * @return mixed
 */
function xero_fetch_order_zone_id( $order ) {
	$country  = strtoupper( wc_clean( $order->get_billing_country() ) );
	$state    = strtoupper( wc_clean( $order->get_billing_state() ) );
	$postcode = wc_normalize_postcode( wc_clean( $order->get_billing_postcode() ) );

	$package = array(
		'destination' => array(
			'country'  => $country,
			'state'    => $state,
			'postcode' => $postcode,
		)
	);

	$data_store = WC_Data_Store::load( 'shipping-zone' );

	return $data_store->get_zone_id_from_package( $package );
}

/**
 * Fetch Active Tracking
 *
 * @param $tracking_response
 *
 * @return array|bool
 */
function xeroom_fetch_tracking_categories( $tracking_response ) {
	if ( null === $tracking_response ) {
		return false;
	}

	if ( ! isset( $tracking_response['TrackingCategories'] ) ) {
		return false;
	}

	if ( ! is_array( $tracking_response ) && ! array_key_exists( 'TrackingCategories', $tracking_response ) && ! empty( $tracking_response['TrackingCategories']['TrackingCategory'] ) ) {
		return false;
	}

	$trackings = array();

	if ( isset( $tracking_response['TrackingCategories']['TrackingCategory'][0] ) ) {
		foreach ( $tracking_response['TrackingCategories']['TrackingCategory'] as $tracking_cat ) {
			if ( isset( $tracking_cat['Status'] ) && 'ACTIVE' == $tracking_cat['Status'] ) {
				if ( isset( $tracking_cat['Options']['Option'][0] ) ) {
					foreach ( $tracking_cat['Options']['Option'] as $track_opt ) {
						$trackings[ $tracking_cat['Name'] ][] = $track_opt['Name'];
					}
				} else {
					$trackings[ $tracking_cat['Name'] ][] = $tracking_cat['Options']['Option']['Name'];
				}
			}
		}
	} else {
		if ( isset( $tracking_response['TrackingCategories']['TrackingCategory']['Status'] ) && 'ACTIVE' == $tracking_response['TrackingCategories']['TrackingCategory']['Status'] ) {
			if ( isset( $tracking_response['TrackingCategories']['TrackingCategory']['Options']['Option'][0] ) ) {
				foreach ( $tracking_response['TrackingCategories']['TrackingCategory']['Options']['Option'] as $track_opt ) {
					$trackings[ $tracking_response['TrackingCategories']['TrackingCategory']['Name'] ][] = $track_opt['Name'];
				}
			} else {
				$trackings[ $tracking_response['TrackingCategories']['TrackingCategory']['Name'] ][] = $tracking_response['TrackingCategories']['TrackingCategory']['Options']['Option']['Name'];
			}
		}
	}

	return $trackings;
}

add_action( 'wp_ajax_get_orders_for_xero', 'xeroom_get_orders_for_xero' );
/**
 * Fetch orders ID's
 */
function xeroom_get_orders_for_xero() {
	$batch_size     = ! empty( $_REQUEST['orders_no'] ) ? $_REQUEST['orders_no'] : 100;
	$xeroom_Payment = $_REQUEST['payment'];

	if ( 'Paid' == $xeroom_Payment ) {
		$xeroomOrderStatus = array( 'wc-completed' );
	} else {
		$xeroomOrderStatus = array( 'wc-on-hold', 'wc-processing', 'wc-pending' );
	}

	$args  = array(
		'limit'   => absint( $batch_size ),
		'status'  => $xeroomOrderStatus,
		'orderby' => 'ID',
		'order'   => 'ASC'
	);
	$query = new WC_Order_Query( $args );

	$order_ids = array();
	if ( $query->posts ) {
		foreach ( $query->posts as $key => $xeroomOrdersValue ) {
			$order_ids[] = $xeroomOrdersValue->ID;
		}
	}

	echo wp_json_encode( $order_ids );
	wp_die();
}

add_action( 'woocommerce_order_refunded', 'xero_order_refunded', 10, 2 );
/**
 * Refund the Invoice
 *
 * @param $order_id
 * @param $refund_id
 */
function xero_order_refunded( $order_id, $refund_id ) {
	global $wpdb;
error_log( '$order_id:' . print_r( $order_id, true ) );
error_log( '$refund_id:' . print_r( $refund_id, true ) );
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_details = new WC_Order( $order_id );
		$order_sent    = '';
		$payment_sent  = '';
		$invoice_no    = '';
		$cn_sent       = '';
		if ( $order_details ) {
			$order_sent   = $order_details->get_meta( 'xeroom_order_sent', true );
			$payment_sent = $order_details->get_meta( 'xeroom_payment_sent' );
			$cn_sent      = $order_details->get_meta( 'xeroom_cred_note_generated' );
			$invoice_no   = $order_details->get_meta( 'post_content' );
		}
	} else {
		$order_details = get_post( $order_id );
		$order_sent    = get_post_meta( $order_id, 'xeroom_order_sent', true );
		$payment_sent  = get_post_meta( $order_id, 'xeroom_payment_sent', true );
		$cn_sent       = get_post_meta( $order_id, 'xeroom_cred_note_generated', true );
		$invoice_no    = $order_details->post_content;
	}

	if ( ! $order_sent ) {
		return;
	}

	if ( $cn_sent && is_order_fully_refunded( $order_id ) ) {
		return;
	}


	if ( ! can_post_credit_note_to_xero() ) {
		return esc_html( 'Number of Credit Notes sent daily reached. To increase limit go to Xeroom settings.' );
	}


	$generate_payment_refund = get_xero_option( 'xero_generate_payment_refund' );

	if ( $generate_payment_refund && ! $payment_sent ) {
		xero_send_manually_payment_for_refund( $order_id, $refund_id );
	}

	$errorType = "orderProduct";
	$order     = new WC_Order( $order_id );

	$country  = new WC_Countries();
	$orderTax = new WC_Tax();

	//define Tables
	$exportErrorXero = $xero_send_error = "";
	$xeroDebugTable  = $wpdb->prefix . 'xeroom_debug';
	$xeroLicActive   = $wpdb->prefix . "xeroom_license_key_status";
	$taxTableName    = $wpdb->prefix . "xeroom_tax";
	$xeroTime        = date( 'Y-m-d H:i:s' );

	//License Key
	$sql               = "SELECT * FROM " . $xeroLicActive . " WHERE id=1";
	$xeroLicensekeyAct = $wpdb->get_results( $sql );
	$active            = sanitize_text_field( $xeroLicensekeyAct[0]->status );
	$lic_key           = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );
	$lic_method        = sanitize_text_field( $xeroLicensekeyAct[0]->xero_method );

	// Credit Note Status
	$default_credit_status = 'AUTHORISED';

	//Check License of plugin
	if ( $active == 'active' ) {
		$license_checked = get_xero_option( 'xero_license_status' );

		if ( $license_checked != 'expired' ) {
			$totalItems    = $order->get_item_count();
			$orderCurency  = $order->get_currency();
			$usedCupons    = "Coupon used " . implode( ", ", $order->get_coupon_codes() );
			$shippingPrice = $order->get_shipping_total();
			$totalDiscount = $order->get_total_discount();

			// User Address
			$shipAddress     = apply_filters( 'xeroom_shipping_address', $order->get_address(), $order_id, 'invoice' );
			$allCountry      = $country->get_countries();
			$countryFullName = $allCountry[ $shipAddress['country'] ];
			$allState        = $country->get_states( $shipAddress['country'] );
			$stateFullName   = '';
			if ( $allState ) {
				$stateFullName = $allState[ $shipAddress['state'] ];
			} else {
				$stateFullName = $order->get_billing_state();
			}

			// Prices include tax amount
			$included_tax = false;
			if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) && wc_tax_enabled() ) {
				$included_tax = true;
			}

			// Tax Rate Used
			$getTaxRate     = array(
				'country'   => $shipAddress['country'],
				'state'     => $shipAddress['state'],
				'city'      => $shipAddress['city'],
				'postcode'  => $shipAddress['postcode'],
				'tax_class' => ''
			);
			$taxRatePercent = $orderTax->find_rates( $getTaxRate );
			if ( count( $taxRatePercent ) != 0 && wc_tax_enabled() ) {
				foreach ( $taxRatePercent as $taxKey => $taxValue ) {
					$taxValue = $taxValue;
				}
				$taxOnWholeCart = $taxValue;
			} else {
				$taxOnWholeCart = array();
			}

			// Xero Connectivity Api Credentials------------
			$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
			$getApiCredentials = $wpdb->get_results( $query );
			$xeroApiKey        = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
			$xeroApiSecret     = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
			$salesAccount      = esc_attr( $getApiCredentials[0]->sales_account );
			$BankCode          = esc_attr( $getApiCredentials[0]->bank_code );
			$TaxCode           = sanitize_text_field( $getApiCredentials[0]->tax_code );
			$AssetCode         = esc_attr( $getApiCredentials[0]->asset_code );
			$SoldCode          = esc_attr( $getApiCredentials[0]->sold_code );
			$StockMaster       = sanitize_text_field( $getApiCredentials[0]->stock_master );
			$productMaster     = sanitize_text_field( $getApiCredentials[0]->product_master );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$oder_gateway_code = $order->get_payment_method();
			} else {
				$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
			}

			if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
				$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
			}

			/**
			 * Get shipping cost code
			 */
			$shippingCode = get_xero_option( 'xero_default_shipping_costs_code' );

			/**
			 * Get Use Extra Sales Accounts - Shipping
			 */
			$use_extra_sales_accounts = get_xero_option( 'xero_use_extra_sales_account' );
			if ( $order->get_shipping_methods() && $use_extra_sales_accounts && 'geography_zones' == $use_extra_sales_accounts ) {
				$zone_id = xero_fetch_order_zone_id( $order );
				if ( ! empty( $zone_id ) ) {
					$shipping_associated_code = xeroom_invoice_geography_zone( $zone_id );
					if ( ! empty( $shipping_associated_code ) ) {
						$salesAccount = $shipping_associated_code;
					}
				}
			}

			include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
			if ( ! defined( 'BASE_PATH' ) ) {
				define( 'BASE_PATH', dirname( __FILE__ ) );
			}

			if ( ! defined( 'XERO_KEY' ) ) {
				define( 'XERO_KEY', $xeroApiKey );
			}

			if ( ! defined( 'XERO_SECRET' ) ) {
				define( 'XERO_SECRET', $xeroApiSecret );
			}

			if ( ! defined( 'PUBLIC_KEY' ) ) {
				define( 'PUBLIC_KEY', '' );
			}

			if ( ! defined( 'PRIVATE_KEY' ) ) {
				define( 'PRIVATE_KEY', '' );
			}

			if ( ! defined( 'FORMAT' ) ) {
				define( 'FORMAT', 'json' );
			}
			$oauth2 = get_xero_option( 'xero_oauth_options' );
			xeroom_check_xero_token( $oauth2 );
			$xero = new Xero( $xeroApiKey, $xeroApiSecret, PUBLIC_KEY, PRIVATE_KEY, 'json', $oauth2 );

			$xero_tracking = $xero->TrackingCategories();
			xeroom_check_connection_message( $xero_tracking, $order_id );

			$active_tracking = array();
			if ( is_array( $xero_tracking ) ) {
				$active_tracking = xeroom_fetch_tracking_categories( $xero_tracking );
			}
			$country_name = WC()->countries->countries[ $order->get_billing_country() ];

			$add_tracking      = $tracking_category_id = '';
			$tracking_category = get_xero_option( 'xero_tracking_category' );
			if ( ! empty( $tracking_category ) ) {
				if ( is_array( $active_tracking ) && array_key_exists( esc_attr( $tracking_category ), $active_tracking ) && in_array( $country_name, $active_tracking[ $tracking_category ] ) ) {
					$add_tracking = $country_name;
				} else {
					$add_tracking = apply_filters( 'xeroom_not_tracking_name', __( 'Not tracked' ) );
				}
			}

			// Xero Connectivity Api Credentials------------
			$ordersList   = $order->get_items();
			$orderDetails = array();
			$oKeyValue    = array();
			$countOrder   = 0;

			$fetch_tax_method           = get_xero_option( 'xero_tax_method' );
			$fetch_saved_tax            = get_xero_option( 'xero_tax_methods' );
			$fetch_taxes_association    = get_xero_option( 'xero_taxes_association' );
			$shipping_price_code        = get_xero_option( 'xero_shipping_price_code' );
			$shipping_price_description = get_xero_option( 'xero_shipping_price_description' );
			$xero_show_shipping_details = get_xero_option( 'xero_show_shipping_details' );

			$xero_shipping_price_code = 'shipping_price';
			if ( $shipping_price_code ) {
				$xero_shipping_price_code = esc_attr( $shipping_price_code );
			}

			if ( $xero_show_shipping_details ) {
				$xero_shipping_price_description = strip_tags( $order->get_shipping_method() );
			} else {
				$xero_shipping_price_description = 'Shipping Price';
				if ( $shipping_price_description ) {
					$xero_shipping_price_description = esc_attr( $shipping_price_description );
				}
			}

			$product_tax        = 'standard';
			$item_tax_rate_id   = '';
			$item_tax_rate_rate = 0;

			$refund       = new WC_Order_Refund( $refund_id );
			$products_ids = array();

			if ( $refund ) {
				if ( ! empty( $refund->get_items() ) ) {
					$listed_items = $refund->get_items();
				} else {
					$listed_items = $order->get_items();
				}

				foreach ( $listed_items as $item_id => $singleorders ) {
					if ( $order->get_item_total( $singleorders, false, true ) > 0 ) {
						$orderDetails[ $countOrder ]['item_price']      = abs( $order->get_item_total( $singleorders, false, true ) );
						$orderDetails[ $countOrder ]['item_sale_price'] = abs( $order->get_item_subtotal( $singleorders, false, true ) );
					} else {
						$orderDetails[ $countOrder ]['item_price']      = abs( $singleorders->get_total() );
						$orderDetails[ $countOrder ]['item_sale_price'] = abs( $singleorders->get_subtotal() );
					}

					$orderDetails[ $countOrder ]['name']          = $singleorders['name'];
					$orderDetails[ $countOrder ]['_product_id']   = $singleorders['product_id'];
					$orderDetails[ $countOrder ]['_variation_id'] = $singleorders['variation_id'];
					if ( abs( floatval( $singleorders['quantity'] ) ) > 0 ) {
						$orderDetails[ $countOrder ]['_qty'] = abs( floatval( $singleorders['quantity'] ) );
					} else {
						$orderDetails[ $countOrder ]['_qty'] = 1;
					}

					$products_ids[ $singleorders['product_id'] ] = abs( $singleorders['quantity'] );

					$orderDetails[ $countOrder ]['apply_discount'] = 0;
					if ( $singleorders->get_subtotal() !== $singleorders->get_total() ) {
//							$orderDetails[ $countOrder ]['item_sale_price'] = wc_format_decimal( $singleorders->get_total(), '' );
						$orderDetails[ $countOrder ]['apply_discount'] = wc_format_decimal( $singleorders->get_subtotal() - $singleorders->get_total(), '' );
					}

					if ( 0 != $totalDiscount ) {
						$orderDetails[ $countOrder ]['get_subtotal_tax'] = abs( round( $singleorders->get_total_tax(), wc_get_price_decimals() ) );
					}

					// Fetch Order Item Tax
					if ( $fetch_tax_method && wc_tax_enabled() ) {
						if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
							$simple_tax = $singleorders->get_tax_class();
						} else {
							$simple_tax = get_post_meta( $singleorders['product_id'], '_tax_class', true );
						}

						if ( ! empty( $simple_tax ) ) {
							$product_tax = $simple_tax;
						} else {
							$product_tax = 'standard';
						}

						switch ( $fetch_tax_method ) {
							case "xero_simple_tax" :
								if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $product_tax . '_taxmethods', $fetch_saved_tax ) ) {
									$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
									$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $product_tax . '_taxmethods' ] );
									$orderDetails[ $countOrder ]['total_tax']     = abs( xero_fetch_tax_complex_amount( $singleorders->get_taxes() ) );
								}
								break;
							case "xero_complex_tax" :
								$tax_data = $singleorders->get_taxes( $singleorders );

								foreach ( $order->get_taxes() as $tax_item ) {
									$rate_id = $tax_item->get_rate_id();

									$item_total_tax = $tax_data['total'][ $rate_id ];
									if ( isset( $tax_data['total'][ $rate_id ] ) ) {
										if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
											$item_tax_rate_id = $rate_id;
											break;
										}
									} else {
										$item_tax_rate_id = $rate_id;
										break;
									}

								}

								if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {

									$orderDetails[ $countOrder ]['tax_class']     = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
									$orderDetails[ $countOrder ]['tax_class_new'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
									$orderDetails[ $countOrder ]['total_tax']     = abs( xero_fetch_tax_complex_amount( $singleorders->get_taxes() ) );

								}
								break;
						}
					}

					if ( 'w' == $productMaster ) {
						if ( wc_tax_enabled() ) {
							if ( ! $fetch_tax_method ) {
								$tax_data         = $singleorders->get_taxes();
								$item_tax_rate_id = $tax_name = '';
								foreach ( $order->get_taxes() as $tax_item ) {
									$rate_id = $tax_item->get_rate_id();

									if ( ! empty( $tax_data['total'][ $rate_id ] ) ) {
										$item_tax_rate_rate = $tax_data['total'][ $rate_id ];
										$tax_name           = xero_fetch_tax_rate_name( $rate_id );
										if ( isset( $item_tax_rate_rate ) ) {
											$item_tax_rate_id = xero_tax_type_code( $tax_name );
										}
									}
								}

								if ( ! empty( $item_tax_rate_id ) ) {
									$orderDetails[ $countOrder ]['tax_class'] = $item_tax_rate_id;
									if ( ! empty( $item_tax_rate_id ) ) {
										$orderDetails[ $countOrder ]['total_tax'] = ! empty( $item_tax_rate_rate ) ? abs( $item_tax_rate_rate ) : 0;
									}
								}
							}
						} else {
							$orderDetails[ $countOrder ]['tax_class'] = 'NONE';
							$orderDetails[ $countOrder ]['total_tax'] = 0;
						}
					}

					$countOrder ++;
				}

				$oderList  = array();
				$uniqueTax = "";
				// Create new tax here
				if ( count( $taxOnWholeCart ) != 0 ) {
					$getAllTax = $xero->TaxRates();
					xeroom_check_connection_message( $getAllTax, $order_id );
					if ( isset( $taxOnWholeCart['rate'] ) ) {
						$taxRate = $taxOnWholeCart['rate'];
						if ( isset( $getAllTax['TaxRates']['TaxRate'] ) ) {
							foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
								if ( $allTaxesNow['Name'] == $TaxCode ) {
									$uniqueTax      = $allTaxesNow['TaxType'];
									$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
									break;
								}
							}
						}
					}

					if ( $uniqueTax == "" ) {
						foreach ( $getAllTax['TaxRates']['TaxRate'] as $allTaxesNow ) {
							if ( $allTaxesNow['TaxComponents']['TaxComponent']['Rate'] == $taxRate ) {
								$uniqueTax      = $allTaxesNow['TaxType'];
								$displayTaxRate = $allTaxesNow['DisplayTaxRate'];
								break;
							}
						}
					}
					if ( $uniqueTax == "" ) {
						$taxName     = "Default Xeroom Sales Tax";
						$query       = "SELECT * FROM `" . $taxTableName . "` WHERE `tax_rate` ='" . $taxRate . "'";
						$getTaxRates = $wpdb->get_results( $query );
						if ( count( $getTaxRates ) > 0 ) {
							$uniqueTax = $getTaxRates[0]->tax_type;
						} else {
							$xero_rate = array(
								"TaxRate" => array(
									"Name"          => "$taxName",
									"ReportTaxType" => 'OUTPUT',
									"TaxComponents" => array(
										"TaxComponent" => array(
											"Name" => "VAT",
											"Rate" => $taxRate
										)
									)
								)
							);
							$taxResult = $xero->TaxRates( $xero_rate );
							if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $er = 0; $er < count( $errD ); $er ++ ) {
									$errorMessage = $taxResult['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $er ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							} else if ( isset( $taxResult['Status'] ) && $taxResult['Status'] == "OK" ) {
								if ( isset( $taxResult['TaxRates']['TaxRate'] ) && ! empty( $taxResult['TaxRates']['TaxRate'] ) ) {
									$uniqueTax = $taxResult['TaxRates']['TaxRate']['TaxType'];
									$wpdb->insert( $taxTableName,
										array(
											'tax_name' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['Name'] ),
											'tax_rate' => sanitize_text_field( $taxRate ),
											'tax_type' => sanitize_text_field( $taxResult['TaxRates']['TaxRate']['TaxType'] )
										)
									);
								}

								update_xero_option( 'xero_connection_status', 'active' );
								update_xero_option( 'xero_connection_status_message', '' );
							} else {
								returnErrorMessageByXero( $order_id, $taxResult, $xeroTime, $errorType );
							}
						}
					}
				}

				for ( $i = 0; $i < count( $orderDetails ); $i ++ ) {
					/**
					 * Add product to associated category
					 */
					if ( $use_extra_sales_accounts && 'product_categories' == $use_extra_sales_accounts ) {
						$product_categories = fetch_product_category_id( $orderDetails[ $i ]['_product_id'] );

						$category_associated_account = xeroom_product_category_associated( $product_categories );
						if ( ! empty( $category_associated_account ) ) {
							$salesAccount = $category_associated_account;
						}
					}

					$allProductAttr = "";

					if ( isset( $orderDetails[ $i ]['_variation_id'] ) && $orderDetails[ $i ]['_variation_id'] != 0 ) {
						$unitPrice = get_post_meta( $orderDetails[ $i ]['_variation_id'] );
						$itemId    = $orderDetails[ $i ]['_variation_id'];
						$product   = wc_get_product( $orderDetails[ $i ]['_variation_id'] );

						if ( ! empty( $product->get_attribute_summary() ) ) {
							$allProductAttr .= ', ' . $product->get_attribute_summary();
						}

						if ( empty( $product->get_attribute_summary() ) ) {
							$productAttributes = get_post_meta( $orderDetails[ $i ]['_product_id'], '_product_attributes' );

							if ( $productAttributes ) {
								foreach ( $productAttributes[0] as $key => $value ) {
									$attributeName  = get_taxonomy( $value['name'] );
									$attributeName  = $attributeName->labels->name;
									$slugValue      = get_term_by( 'slug', $attributeName->name, $value['name'] );
									$allProductAttr .= ', ' . $attributeName . ': ' . $slugValue->name;
								}
							}
						}
					} else {
						$unitPrice = get_post_meta( $orderDetails[ $i ]['_product_id'] );
						$itemId    = $orderDetails[ $i ]['_product_id'];
						$product   = wc_get_product( $orderDetails[ $i ]['_product_id'] );
					}

					if ( $unitPrice['_sku'][0] != "" ) {
						$sku = $unitPrice['_sku'][0];
					} else {
						$sku = $orderDetails[ $i ]['_product_id'];
					}

					$sku = xeroom_reduce_sku_length( $sku );

					if ( isset( $unitPrice['_sale_price'][0] ) && $unitPrice['_sale_price'][0] != "" ) {
						$unitPriceNow = $unitPrice['_sale_price'][0];
						if ( $allProductAttr == "" ) {
							$allProductAttr = ', Full Price: ' . $unitPrice['_regular_price'][0];
						} else {
							$allProductAttr .= ', Full Price: ' . $unitPrice['_regular_price'][0];
						}
					} else {
						$unitPriceNow = $unitPrice['_regular_price'][0];
					}

					$checkwithoutStock = $xero->Items( $sku );
					xeroom_check_connection_message( $checkwithoutStock, $order_id );

					$unitPriceNow = $orderDetails[ $i ]['item_price'];
//				$unitPriceNow = $product->get_price_excluding_tax();
					$unitPriceNow = number_format( (float) $unitPriceNow, 2, '.', '' );
					if ( $allProductAttr != "" ) {
						$descriptionName = $orderDetails[ $i ]['name'] . $allProductAttr;
						if ( $productMaster == "w" ) {
							$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'] . $allProductAttr;
						}
					} else {
						$descriptionName = $orderDetails[ $i ]['name'];
						if ( $productMaster == "w" ) {
							$oderList[ $i ]['Description'] = $orderDetails[ $i ]['name'];
						}
					}

					$oderList[ $i ]['Quantity'] = $orderDetails[ $i ]['_qty'];
					if ( $productMaster == "w" || $productMaster == "n" ) {
						$oderList[ $i ]['UnitAmount'] = $unitPriceNow;
					}
					$oderList[ $i ]['ItemCode'] = $sku;
//						$oderList[ $i ]['DiscountRate'] = 0;
					if ( $uniqueTax != "" ) {
						$oderList[ $i ]['TaxType'] = $uniqueTax;
					}

					// Add tax Rate name
					if ( isset( $orderDetails[ $i ]['tax_class'] ) && '' != $orderDetails[ $i ]['tax_class'] ) {
						$oderList[ $i ]['TaxType'] = $orderDetails[ $i ]['tax_class'];
						if ( $productMaster == "w" ) {
							$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['total_tax'];
						}
					}

					// Apply discount rate.
					if ( 0 != $totalDiscount ) {
						$percentage        = 0;
						$coupon_percentage = 0;
						foreach ( $order->get_coupon_codes() as $coupon_name ) {
							if ( ! $coupon_name ) {
								continue;
							}
							$coupons_obj = new WC_Coupon( $coupon_name );

							if ( 'percent' === $coupons_obj->get_discount_type() || 'recurring_percent' === $coupons_obj->get_discount_type() ) {
								$coupon_percentage = $coupons_obj->get_amount();
								$item_subtotal     = number_format( (float) $orderDetails[ $i ]['item_sale_price'], 2, '.', '' );
								$percentage        = round( ( ( $item_subtotal - number_format( (float) $orderDetails[ $i ]['item_price'], 2, '.', '' ) ) / $item_subtotal ) * 100 );
                                
                                if ( $percentage > 100 ) {
											$percentage = 100;
										}
								break;
							} else {
								$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['get_subtotal_tax'];
							}

						}

						if ( $percentage > 0 ) {
							$oderList[ $i ]['DiscountRate'] = $coupon_percentage;
							$oderList[ $i ]['TaxAmount']    = $orderDetails[ $i ]['total_tax'] - ( $orderDetails[ $i ]['total_tax'] * ( $percentage / 100 ) );
						}

						$unitPriceNow = $orderDetails[ $i ]['item_sale_price'];
						if ( $productMaster == "w" || $productMaster == "n" ) {
							$oderList[ $i ]['UnitAmount'] = $unitPriceNow;
							if ( $orderDetails[ $i ]['apply_discount'] > 0 && empty( $order->get_coupon_codes() ) ) {
								$oderList[ $i ]['TaxAmount'] = $orderDetails[ $i ]['get_subtotal_tax'];

								// Add the discount
								$percent                        = ( $orderDetails[ $i ]['apply_discount'] / $unitPriceNow ) * 100;
								$oderList[ $i ]['DiscountRate'] = $percent;
							}
						}
					}

					// Add tracking
					$saved_categories = get_xero_option( 'xero_tracking_categories' );
					if ( ! empty( $saved_categories ) ) {
						foreach ( $saved_categories as $key => $categories ) {
							$item_category = get_post_meta( $orderDetails[ $i ]['_product_id'], '_tracking_category_' . str_replace( ' ', '_', $key ), true );
							$item_category = apply_filters( 'xeroom_invoice_item_tracking_category', $item_category, $orderDetails[ $i ] );
							if ( ! empty( $item_category ) ) {
								$oderList[ $i ]['Tracking'][]['TrackingCategory'] = array(
									'Name'   => esc_attr( $key ),
									'Option' => esc_attr( $item_category ),
								);
							}
						}
					} else {
						if ( ! empty( $add_tracking ) ) {
							$oderList[ $i ]['Tracking'] = array(
								'TrackingCategory' => array(
									'Name'   => esc_attr( $tracking_category ),
									'Option' => $add_tracking,
								),
							);
						}
					}

					$saved_product_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_product_account', true );
					if ( $saved_product_account ) {
						$oderList[ $i ]['AccountCode'] = $saved_product_account;
					} else {
						$oderList[ $i ]['AccountCode'] = $salesAccount;
					}

					$saved_cost_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_cost_account', true );
					if ( $saved_cost_account ) {
						$oderList[ $i ]['COGSAccountCode'] = $saved_cost_account;
					} else {
						$oderList[ $i ]['COGSAccountCode'] = $SoldCode;
					}

					$saved_inventory_account = get_post_meta( $orderDetails[ $i ]['_product_id'], 'xerrom_inventory_account', true );
					if ( $saved_inventory_account ) {
						$oderList[ $i ]['InventoryAssetAccountCode'] = $saved_inventory_account;
					} else {
						$oderList[ $i ]['InventoryAssetAccountCode'] = $AssetCode;
					}

					$post_details = get_post( $orderDetails[ $i ]['_product_id'] );
					$xItemName    = xeroom_reduce_item_name_length( $post_details->post_title );
					if ( $xItemName == "" ) {
						$xItemName = "No Name_" . rand();
					}

					$xItemDesc  = $post_details->post_content;
					$xItemStock = $unitPrice['_stock'][0];
					/* Stock Master */
					if ( $StockMaster == "w" ) {
						if ( $xItemStock == 0 || $xItemStock == "" ) {
							$createItemWithoutStock = array(
								"Item"            => array(
									"Code"        => $sku,
									"Name"        => $xItemName,
									"Description" => $descriptionName
								),
								"SalesDetails"    => array(
									"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
									"AccountCode" => $oderList[ $i ]['AccountCode']
								),
								"PurchaseDetails" => array(
									"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
									"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
								)
							);

							if ( isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = $descriptionName;
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$createItemWithoutStock['Item']['Description'] = $xero_desc;
								$createItemWithoutStock['Item']['Name']        = '' != xero_product_name( $checkwithoutStock ) ? xero_product_name( $checkwithoutStock ) : $xItemName;

								$createItemWithoutStock['Item']['SalesDetails']['UnitPrice']    = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
								$createItemWithoutStock['Item']['PurchaseDetails']['UnitPrice'] = '' != xero_product_purchase_unit_price( $checkwithoutStock ) ? xero_product_purchase_unit_price( $checkwithoutStock ) : $unitPriceNow;
							} else {
								if ( $productMaster == "w" ) {
									$createItemWithoutStock['Item']['PurchaseDetails']['UnitPrice'] = 0;
								}
							}

							$withoutStockXero = $xero->Items( $createItemWithoutStock );
							if ( isset( $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $e = 0; $e < count( $errD ); $e ++ ) {
									$errorMessage = $withoutStockXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							}

							$oderList[ $i ]['Description'] = $descriptionName;
							if ( $productMaster == "x" && isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$oderList[ $i ]['Description'] = $xero_desc;
								$oderList[ $i ]['UnitAmount']  = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
							}

						} else {
							$newItemsCode = array(
								"Item" => array(
									"Code"                      => $sku,
									"Name"                      => $xItemName,
									"Description"               => $descriptionName,
									"PurchaseDescription"       => $xItemName,
									"IsTrackedAsInventory"      => false,
									"IsSold"                    => true,
									"IsPurchased"               => true,
									"InventoryAssetAccountCode" => $AssetCode,
									"QuantityOnHand"            => $xItemStock,
									"TotalCostPool"             => $xItemStock * $unitPriceNow,
									"InventoryAssetAccountCode" => $oderList[ $i ]['InventoryAssetAccountCode'],
									"SalesDetails"              => array(
										"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
										"AccountCode" => $oderList[ $i ]['AccountCode']
									),
									"PurchaseDetails"           => array(
										"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
										"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
									)
								)
							);

							if ( isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$newItemsCode['Item']['Description']                  = $xero_desc;
								$newItemsCode['Item']['PurchaseDescription']          = '' != xero_product_purchase_description( $checkwithoutStock ) ? xero_product_purchase_description( $checkwithoutStock ) : $xItemName;
								$newItemsCode['Item']['Name']                         = '' != xero_product_name( $checkwithoutStock ) ? xero_product_name( $checkwithoutStock ) : $xItemName;
								$newItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
								$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != xero_product_purchase_unit_price( $checkwithoutStock ) ? xero_product_purchase_unit_price( $checkwithoutStock ) : $unitPriceNow;
							} else {
								if ( $productMaster == "w" ) {
									$newItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
								}
							}

							$createItem = $xero->Items( $newItemsCode );
							if ( isset( $createItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $createItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $createItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $createItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $e = 0; $e < count( $errD ); $e ++ ) {
									$errorMessage = $createItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							} else {
								update_xero_option( 'xero_connection_status', 'active' );
								update_xero_option( 'xero_connection_status_message', '' );
							}
						}
					} else if ( $StockMaster == "x" ) {
						$xeroInventryStock = $xero->Items( $sku );
						if ( is_array( $xeroInventryStock ) ) {
							$simpleItemCode = array(
								"Item"            => array(
									"Code"        => $sku,
									"Name"        => $xItemName,
									"Description" => $descriptionName
								),
								"SalesDetails"    => array(
									"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
									"AccountCode" => $oderList[ $i ]['AccountCode']
								),
								"PurchaseDetails" => array(
									"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
									"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
								)
							);

							if ( isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$simpleItemCode['Item']['Description'] = $xero_desc;
								$simpleItemCode['Item']['Name']        = '' != xero_product_name( $checkwithoutStock ) ? xero_product_name( $checkwithoutStock ) : $xItemName;

								$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
								$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != xero_product_purchase_unit_price( $checkwithoutStock ) ? xero_product_purchase_unit_price( $checkwithoutStock ) : $unitPriceNow;
							} else {
								if ( $productMaster == "w" ) {
									$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
								}
							}

							$simpleXeroItem = $xero->Items( $simpleItemCode );
							if ( isset( $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $e = 0; $e < count( $errD ); $e ++ ) {
									$errorMessage = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							} else {
								update_xero_option( 'xero_connection_status', 'active' );
								update_xero_option( 'xero_connection_status_message', '' );
							}

							$oderList[ $i ]['Description'] = $descriptionName;
							$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
							if ( $productMaster == "x" && isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$oderList[ $i ]['Description'] = $xero_desc;
								$oderList[ $i ]['UnitAmount']  = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
							}

						} else {
							$simpleItemCode = array(
								"Item"            => array(
									"Code"        => $sku,
									"Name"        => $xItemName,
									"Description" => $descriptionName
								),
								"SalesDetails"    => array(
									"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
									"AccountCode" => $oderList[ $i ]['AccountCode']
								),
								"PurchaseDetails" => array(
									"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
									"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
								)
							);

							if ( isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$simpleItemCode['Item']['Description'] = $xero_desc;
								$simpleItemCode['Item']['Name']        = '' != xero_product_name( $checkwithoutStock ) ? xero_product_name( $checkwithoutStock ) : $xItemName;

								$simpleItemCode['Item']['SalesDetails']['UnitPrice']    = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
								$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = '' != xero_product_purchase_unit_price( $checkwithoutStock ) ? xero_product_purchase_unit_price( $checkwithoutStock ) : $unitPriceNow;
							} else {
								if ( $productMaster == "w" ) {
									$simpleItemCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
								}
							}

							$simpleXeroItem = $xero->Items( $simpleItemCode );
							if ( isset( $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
								$errD = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
								returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
							} else if ( isset( $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
								$errD = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
								for ( $e = 0; $e < count( $errD ); $e ++ ) {
									$errorMessage = $simpleXeroItem['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
									returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
								}
							} else {
								update_xero_option( 'xero_connection_status', 'active' );
								update_xero_option( 'xero_connection_status_message', '' );
							}
							$oderList[ $i ]['Description'] = $descriptionName;
							$oderList[ $i ]['UnitAmount']  = $unitPriceNow;
							if ( $productMaster == "x" && isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
								$xero_desc = '-';
								if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
									$xero_desc = xero_product_description( $checkwithoutStock );
								}
								$oderList[ $i ]['Description'] = $xero_desc;
								$oderList[ $i ]['UnitAmount']  = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
							}
						}
					} else {
						$simpleItemsCode = array(
							"Item"            => array(
								"Code"        => $sku,
								"Name"        => $xItemName,
								"Description" => $descriptionName
							),
							"SalesDetails"    => array(
								"UnitPrice"   => wc_format_decimal( $unitPriceNow ),
								"AccountCode" => $oderList[ $i ]['AccountCode']
							),
							"PurchaseDetails" => array(
								"UnitPrice"       => wc_format_decimal( $unitPriceNow ),
								"COGSAccountCode" => $oderList[ $i ]['COGSAccountCode']
							)
						);

						if ( isset( $checkwithoutStock['Items']['Item']['ItemID'] ) ) {
							$xero_desc = '-';
							if ( ! empty( xero_product_description( $checkwithoutStock ) ) ) {
								$xero_desc = xero_product_description( $checkwithoutStock );
							}
							$simpleItemsCode['Item']['Description'] = $xero_desc;
							$simpleItemsCode['Item']['Name']        = '' != xero_product_name( $checkwithoutStock ) ? xero_product_name( $checkwithoutStock ) : $xItemName;

							$simpleItemsCode['Item']['SalesDetails']['UnitPrice']    = '' != xero_product_sales_unit_price( $checkwithoutStock ) ? xero_product_sales_unit_price( $checkwithoutStock ) : $unitPriceNow;
							$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = '' != xero_product_purchase_unit_price( $checkwithoutStock ) ? xero_product_purchase_unit_price( $checkwithoutStock ) : $unitPriceNow;
						} else {
							if ( $productMaster == "w" ) {
								$simpleItemsCode['Item']['PurchaseDetails']['UnitPrice'] = 0;
							}
						}

						$againSimpleXero = $xero->Items( $simpleItemsCode );
						if ( isset( $againSimpleXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
							$errD = $againSimpleXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
							returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
						} else if ( isset( $againSimpleXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
							$errD = $againSimpleXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
							for ( $e = 0; $e < count( $errD ); $e ++ ) {
								$errorMessage = $againSimpleXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
								returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
							}
						} else {
							update_xero_option( 'xero_connection_status', 'active' );
							update_xero_option( 'xero_connection_status_message', '' );
						}
					}

				}

				if ( $order->get_shipping_method() ) {
					$newItemsCodeShip = array(
						"Item" => array(
							"Code"        => $xero_shipping_price_code,
							"Description" => $xero_shipping_price_description
						)
					);
					$xero->Items( $newItemsCodeShip );
				}

				if ( $totalDiscount != 0 ) {
					$new_discounts_items = array();
					foreach ( $order->get_coupon_codes() as $coupon_name ) {
						if ( ! $coupon_name ) {
							continue;
						}
						$coupons_obj = new WC_Coupon( $coupon_name );

						$coupon_description = esc_html__( 'Cart Fixed Discount Coupon' );
						if ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
							$coupon_description = esc_html__( 'Product Fixed Discount coupon' );
						}

						if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
							$coupon_name = esc_html( $coupon_xero_code );
						}

						if ( ! empty( $coupons_obj->get_description() ) ) {
							$coupon_description .= esc_html( $coupons_obj->get_description() );
						}

						$new_discounts_items['Item'] = array(
							"Code"        => xeroom_reduce_coupon_length( $coupon_name ),
							"Description" => $coupon_description
						);
					}

					$xero->Items( $new_discounts_items );
				}

				if ( $order->get_items( array( 'fee' ) ) ) {
					$newItemsCodeDis = array(
						"Item" => array(
							"Code"        => "fee_price",
							"Description" => "Fee Price"
						)
					);
					$xero->Items( $newItemsCodeDis );
				}

				// Fetch shippinh tax class
				$item_tax_rate_id = '';
				$shipping_tax     = array();
				if ( $fetch_tax_method && wc_tax_enabled() ) {
					$shipping_tax_class = 'standard';
					$tax_data           = array();
					foreach ( $order->get_items( array( 'shipping' ) ) as $item ) {
						if ( '' != $item->get_tax_class() ) {
							$shipping_tax_class = $item->get_tax_class();
						}
						$tax_data = $item->get_taxes();
					}

					$simple_tax = $order->get_shipping_tax();

					if ( $simple_tax ) {
						$product_tax = $simple_tax;
					} else {
						$product_tax = 'standard';
					}

					switch ( $fetch_tax_method ) {
						case "xero_simple_tax" :
							if ( $fetch_saved_tax && is_array( $fetch_saved_tax ) && array_key_exists( 'xero_' . $shipping_tax_class . '_taxmethods', $fetch_saved_tax ) ) {
								$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_saved_tax[ 'xero_' . $shipping_tax_class . '_taxmethods' ] );
							}
							break;
						case "xero_complex_tax" :
							foreach ( $order->get_taxes() as $tax_item ) {
								$rate_id = $tax_item->get_rate_id();
//								$item_total_tax = isset( $tax_data['total'][ $rate_id ] ) ? abs( $tax_data['total'][ $rate_id ] ) : '';
								$item_total_tax = isset( $tax_data['total'][ $rate_id ] ) ? $tax_data['total'][ $rate_id ] : '';

								if ( isset( $item_total_tax ) && '' !== $item_total_tax ) {
									$item_tax_rate_id = $rate_id;
									break;
								}
							}

							if ( $fetch_taxes_association && is_array( $fetch_taxes_association ) && array_key_exists( $item_tax_rate_id, $fetch_taxes_association ) ) {
								$shipping_tax['tax_class'] = xero_tax_type_code( $fetch_taxes_association[ $item_tax_rate_id ] );
							}
							break;
					}

					if ( 0 == $simple_tax ) {
						$shipping_tax['tax_class'] = 'NONE';
					}

					if ( 'inherit' == $shipping_tax_class ) {
						$shipping_class            = reset( $orderDetails );
						$shipping_tax['tax_class'] = $shipping_class['tax_class'];
					}

				} else {
					$shipping_tax['tax_class'] = 'NONE';
				}

				$rest_to_refund = $order->get_total() - $order->get_total_refunded();
				if ( $refund->get_shipping_method() ) {
					$oderList[ count( $orderDetails ) ]['Description']  = $xero_shipping_price_description;
					$oderList[ count( $orderDetails ) ]['Quantity']     = 1;
					$oderList[ count( $orderDetails ) ]['UnitAmount']   = $shippingPrice;
					$oderList[ count( $orderDetails ) ]['DiscountRate'] = 0;
					$oderList[ count( $orderDetails ) ]['ItemCode']     = $xero_shipping_price_code;

					if ( ! empty( $shipping_tax ) ) {
						$oderList[ count( $orderDetails ) ]['TaxType'] = $shipping_tax['tax_class'];
					}
					$oderList[ count( $orderDetails ) ]['AccountCode'] = $shippingCode && '' != $shippingCode ? $shippingCode : $salesAccount;
				}

				if ( $lic_method != "" ) {
					if ( $totalDiscount != 0 ) {
						$products_tax_type         = reset( $orderDetails );
						$discount_tax['tax_class'] = $products_tax_type['tax_class'];

						if ( $order->get_shipping_method() ) {
							$nextCount = count( $orderDetails ) + 1;
						} else {
							$nextCount = count( $orderDetails );
						}

						$coupon_quantity = 0;

						foreach ( $order->get_coupon_codes() as $coupon_name ) {
							if ( ! $coupon_name ) {
								continue;
							}
							$coupons_obj = new WC_Coupon( $coupon_name );

							$coupon_order_id = wpr_fetch_coupon_item_id( $order_id, $coupon_name );

							$order_discount_amount     = wc_get_order_item_meta( $coupon_order_id, 'discount_amount', true );
							$order_discount_tax_amount = wc_get_order_item_meta( $coupon_order_id, 'discount_amount_tax', true );

//								$oderList[ $nextCount ]['UnitAmount'] = 0;
							$oderList[ $nextCount ]['Quantity'] = 1;
							if ( $coupons_obj->get_discount_type() == 'fixed_cart' ) {
								$oderList[ $nextCount ]['Description'] = esc_html__( 'Cart Fixed Discount coupon' );
								if ( $included_tax ) {
									$oderList[ $nextCount ]['UnitAmount'] = $order_discount_amount;
								} else {
									$oderList[ $nextCount ]['UnitAmount'] = $coupons_obj->get_amount();
								}
							} elseif ( $coupons_obj->get_discount_type() == 'fixed_product' || $coupons_obj->get_discount_type() === 'recurring_fee' ) {
								$coupon_applied = get_coupons_details_by_order( $order_id );
								$coupon_code    = $coupons_obj->get_code();

								if ( isset( $coupon_applied[ $coupon_code ]['applied_to_products'] ) && count( $coupon_applied[ $coupon_code ]['applied_to_products'] ) > 0 ) {

									if ( array_intersect_key( $products_ids, $coupon_applied[ $coupon_code ]['applied_to_products'] ) ) {
										$oderList[ $nextCount ]['Description'] = esc_html__( 'Fixed product discount' );
										if ( $included_tax ) {
											$oderList[ $nextCount ]['UnitAmount'] = $order_discount_amount;
										} else {
											$oderList[ $nextCount ]['UnitAmount'] = $coupons_obj->get_amount();
										}

										foreach ( $products_ids as $product_id => $quantity ) {
											if ( in_array( $product_id, $coupons_obj->get_product_ids() ) ) {
												$coupon_quantity += $quantity;
											}
										}

										$oderList[ $nextCount ]['Quantity'] = $coupon_quantity;
									}
								}
							} else {
								$coupon_named_description = esc_html__( 'Cart Percent Discount coupon' );
								if ( ! empty( $coupons_obj->get_description() ) ) {
									$coupon_named_description .= ' (' . esc_html( $coupons_obj->get_description() ) . ')';
								}
								$oderList[ $nextCount ]['Description'] = $coupon_named_description;
							}

							if ( $coupon_xero_code = get_post_meta( $coupons_obj->get_id(), 'xero_coupon_code', true ) ) {
								$coupon_name = esc_html( $coupon_xero_code );
							}

							$oderList[ $nextCount ]['ItemCode'] = xeroom_reduce_coupon_length( $coupon_name );

							// Add tax Rate name
							if ( ! empty( $discount_tax ) ) {
								$oderList[ $nextCount ]['TaxType'] = $discount_tax['tax_class'];
							}

							$oderList[ $nextCount ]['AccountCode'] = $salesAccount;

							// Add tracking
							if ( ! empty( $add_tracking ) && $coupons_obj->get_discount_type() != 'percent' && $coupons_obj->get_discount_type() != 'recurring_percent' ) {
								$oderList[ $nextCount ]['Tracking'] = array(
									'TrackingCategory' => array(
										'Name'   => esc_attr( $tracking_category ),
										'Option' => $add_tracking,
									),
								);
							}

							$nextCount ++;
						}
					}
				}


				// Fee tax
				$fee_tax = array();
				if ( $fetch_tax_method && wc_tax_enabled() ) {
					$fee_class            = reset( $orderDetails );
					$fee_tax['tax_class'] = $fee_class['tax_class'];
				}

				// Add fee if exists
				if ( $refund->get_items( array( 'fee' ) ) ) {
					$fee_count = count( $oderList );
					foreach ( $refund->get_items( array( 'fee' ) ) as $item_type => $item ) {
						if ( 'stripe fee' === strtolower( $item->get_name() ) ) {
							continue; // Skip this iteration if it's a Stripe fee
						}

						$oderList[ $fee_count ]['Description']  = $item->get_name() ? $item->get_name() : "Fee Price";
						$oderList[ $fee_count ]['Quantity']     = 1;
						$oderList[ $fee_count ]['UnitAmount']   = abs( $refund->get_item_total( $item, false, true ) );
						$oderList[ $fee_count ]['DiscountRate'] = 0;
						$oderList[ $fee_count ]['ItemCode']     = "fee_price";
						if ( ! empty( $fee_tax ) ) {
							$oderList[ $fee_count ]['TaxType'] = $fee_tax['tax_class'];
						}

						$oderList[ $fee_count ]['AccountCode'] = $salesAccount;
						$fee_count ++;
					}
				}

				$lineItemsArray = array();
				for ( $i = 0; $i < count( $oderList ); $i ++ ) {
					if ( isset( $oderList[ $i ] ) && is_array( $oderList[ $i ] ) ) {
						$lineItemsArray[] = array( "LineItem" => array( $oderList[ $i ] ) );
					}
				}
                
				$lineItemsArray = array_filter( $lineItemsArray );

//					$currentDate       = xeroom_invoice_date( $order );
				$currentDate       = date( 'Y-m-d H:i:s' );
				$xero_contact_name = get_xero_option( 'xero_contact_name' );

				if ( 'xeroom_use_company' == $xero_contact_name ) {
					$xero_add_contact_name = $shipAddress['company'];
				} elseif ( 'xeroom_use_email' == $xero_contact_name ) {
					$xero_add_contact_name = $shipAddress['email'];
				} else {
					$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
				}

				if ( empty( $xero_add_contact_name ) ) {
					$xero_add_contact_name = $shipAddress['first_name'] . ' ' . $shipAddress['last_name'];
				}

				if ( $lic_method != "" ) {
					$credit_note = array(
						array(
							"Type"            => "ACCRECCREDIT",
							"Contact"         => array(
								"Name"         => $xero_add_contact_name,
								"FirstName"    => $shipAddress['first_name'],
								"LastName"     => $shipAddress['last_name'],
								"EmailAddress" => $shipAddress['email'],
								"Addresses"    => array(
									"Address" => array(
										array(
											"AddressType"  => "POBOX",
											"AttentionTo"  => "",
											"AddressLine1" => $shipAddress['address_1'],
											"AddressLine2" => $shipAddress['address_2'],
											"City"         => $shipAddress['city'],
											"Region"       => $stateFullName,
											"Country"      => $countryFullName,
											"PostalCode"   => $shipAddress['postcode']
										)
									)
								),
								"Phones"       => array(
									"Phone" => array(
										"PhoneType"   => "MOBILE",
										"PhoneNumber" => $shipAddress['phone']
									)
								)
							),
							"Date"            => $currentDate,
							"Status"          => $default_credit_status,
							"CurrencyCode"    => $order->get_currency(),
							"LineAmountTypes" => "Exclusive",
							"Reference"       => xeroom_generate_invoice_reference( $order_id ),
							"LineItems"       => $lineItemsArray
						)
					);
				} else {
					$credit_note = array(
						array(
							"Type"            => "ACCRECCREDIT",
							"Contact"         => array(
								"Name"         => $xero_add_contact_name,
								"FirstName"    => $shipAddress['first_name'],
								"LastName"     => $shipAddress['last_name'],
								"EmailAddress" => $shipAddress['email'],
								"Addresses"    => array(
									"Address" => array(
										array(
											"AddressType"  => "POBOX",
											"AttentionTo"  => "Created using demo version of Xeroom",
											"AddressLine1" => "",
											"AddressLine2" => "",
											"AddressLine3" => "",
											"City"         => "",
											"Region"       => "",
											"Country"      => "",
											"PostalCode"   => ""
										)
									)
								),
								"Phones"       => array(
									"Phone" => array(
										"PhoneType"   => "MOBILE",
										"PhoneNumber" => $shipAddress['phone']
									)
								)
							),
							"Date"            => $currentDate,
							"Status"          => $default_credit_status,
							"CurrencyCode"    => $orderCurency,
							"LineAmountTypes" => "Exclusive",
							"Reference"       => xeroom_generate_invoice_reference( $order_id ),
							"LineItems"       => $lineItemsArray
						)
					);
				}

				if ( $license_checked != 'expired' ) {
//					error_log( 'Before Sending:' . print_r( $credit_note, true ) );die();
					$creditNotesCreated = $xero->CreditNotes( $credit_note );
//					error_log( 'Credit Note Response:' . print_r( $creditNotesCreated, true ) );


					if ( isset( $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
						$errD = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
						returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
					} else if ( isset( $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
						$errD = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
						for ( $e = 0; $e < count( $errD ); $e ++ ) {
							$errorMessage = $creditNotesCreated['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
							returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
						}
					} else if ( isset( $creditNotesCreated['Status'] ) && $creditNotesCreated['Status'] != "OK" ) {
						returnErrorMessageByXero( $order_id, 'Credit Note was not created', $xeroTime, $errorType );
					}
					// Allocate Credit Note to Invoice No.
					if ( isset( $creditNotesCreated['Status'] ) && $creditNotesCreated['Status'] == "OK" ) {
						if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
							$order->update_meta_data( 'xeroom_cred_note_generated', 1 );
							$order->update_meta_data( 'xeroom_cred_note_id', $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] );
							$order->save();
							$invoice_id = $order->get_meta( '_xero_invoice_id' ) ? $order->get_meta( '_xero_invoice_id' ) : $order->get_meta( 'post_content_filtered' );
						} else {
							update_post_meta( $order_id, 'xeroom_cred_note_generated', 1 );
							update_post_meta( $order_id, 'xeroom_cred_note_id', $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] );
							$invoice_id = get_post_meta( $order_id, '_xero_invoice_id', true ) ? get_post_meta( $order_id, '_xero_invoice_id', true ) : $order_details->post_content_filtered;
						}

						$message = sprintf( __( 'Xero Credit Note Generated.', 'xeroom' ) );
						$order->add_order_note( $message );

						$total_amount = $order->get_total();

						$alocateCreditNote = array(
							"Allocation" => array(
								"Invoice"       => array(
									"InvoiceID" => $invoice_id,
								),
								"AppliedAmount" => $total_amount,
								"Date"          => date( 'Y-m-d' ),
							),
						);

						$sendCreditNoteAllocation = $xero->CreditNotes( $alocateCreditNote, array( 'Allocations' => $creditNotesCreated['CreditNotes']['CreditNote']['CreditNoteID'] ) );

						if ( isset( $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
							$errD = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
							returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
						} else if ( isset( $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
							$errD = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
							for ( $e = 0; $e < count( $errD ); $e ++ ) {
								$errorMessage = $sendCreditNoteAllocation['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
								returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
							}
						} else if ( isset( $sendCreditNoteAllocation['Status'] ) && $sendCreditNoteAllocation['Status'] != "OK" ) {
							returnErrorMessageByXero( $order_id, 'Credit Note was not Allocated', $xeroTime, $errorType );
						}

						if ( isset( $sendCreditNoteAllocation['Status'] ) && $sendCreditNoteAllocation['Status'] == "OK" ) {
							if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
								$order->update_meta_data( 'xeroom_cred_note_allocated', 1 );
								$order->save();
							} else {
								update_post_meta( $order_id, 'xeroom_cred_note_allocated', 1 );
							}

							$message = sprintf( __( 'Xero Credit Note Allocated to Invoice.', 'xeroom' ) );
							$order->add_order_note( $message );
						}

						update_xero_option( 'xero_connection_status', 'active' );
						update_xero_option( 'xero_connection_status_message', '' );
						increment_credit_note_counter();
					}
				}
			}
		} else {
			$mMessage = "Your license has expired, please contact xeroom customer support team.";
			returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
		}
	} else {
		$mMessage = "Please use a premium or regular license!";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}
}

/**
 * Check if an order is fully refunded.
 *
 * @param int $order_id The ID of the order to check.
 *
 * @return bool Returns true if the order has been fully refunded, false otherwise.
 */
function is_order_fully_refunded( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( $order ) {
		$total_quantity_ordered = 0;
		foreach ( $order->get_items() as $item ) {
			$total_quantity_ordered += $item->get_quantity();
		}

		$refunds = $order->get_refunds();
		if ( ! empty( $refunds ) ) {
			$total_refunded = 0;
			$last_refund_id = end( $refunds )->get_id();

			foreach ( $refunds as $refund ) {
				$refund_items = $refund->get_items();
				foreach ( $refund_items as $refund_item ) {
					$total_refunded += $refund_item->get_quantity();
				}
			}

			if ( $total_refunded == $total_quantity_ordered ) {
				return true;
			} else {
				$last_refund          = wc_get_order( $last_refund_id );
				$last_refund_items    = $last_refund->get_items();
				$last_refund_quantity = 0;

				foreach ( $last_refund_items as $item ) {
					$last_refund_quantity += $item->get_quantity();
				}

				if ( $last_refund_quantity == ( $total_quantity_ordered - $total_refunded + $last_refund_quantity ) ) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * Save log error entry
 *
 * @param $response
 * @param int $order_id
 * @param string $error_type
 */
function xeroom_check_connection_message( $response, $order_id = 0, $error_type = 'connection' ) {
	if ( ! empty( $response ) ) {
		$xeroTime = date( 'Y-m-d H:i:s' );
		if ( isset( $response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
			$errD = $response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
			returnErrorMessageByXero( $order_id, $errD, $xeroTime, $error_type );
		} else if ( isset( $response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
			$errD = $response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
			if ( ! empty( $errD ) ) {
				for ( $er = 0; $er < count( $errD ); $er ++ ) {
					$errorMessage = $response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $er ]['Message'];
					returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $error_type );
				}
			}
		} else if ( isset( $response['Status'] ) && "OK" == $response['Status'] ) {
			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );
		} else {
			returnErrorMessageByXero( $order_id, $response, $xeroTime, $error_type );
		}
	}
}

/**
 * Add order suffix on manual resending
 *
 * @param $order_id
 *
 * @return string
 */
function xeroom_add_invoice_suffix( $order_id ) {
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order       = new WC_Order( $order_id );
		$existing_no = $order->get_meta( 'xeroom_invoice_resend_suffix' );
	} else {
		$existing_no = get_post_meta( $order_id, 'xeroom_invoice_resend_suffix', true );
	}

	$return_suffix = 'A';

	if ( $existing_no ) {
		$letterAscii = ord( $existing_no );
		$letterAscii ++;
		$return_suffix = chr( $letterAscii );
	}
	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order->update_meta_data( 'xeroom_invoice_resend_suffix', $return_suffix );
		$order->save();
	} else {
		update_post_meta( $order_id, 'xeroom_invoice_resend_suffix', $return_suffix );
	}

	return $return_suffix;
}

/**
 * Reduce SKU length to Xero max size
 *
 * @param $sku
 *
 * @return mixed
 */
function xeroom_reduce_sku_length( $sku ) {
	if ( strlen( $sku ) > 30 ) {
		$sku = 'SKUFAIL_' . substr( $sku, 0, 20 );
	}

	return $sku;
}

/**
 * Reduce Coupon length to Xero max size
 *
 * @param $coupon
 *
 * @return mixed
 */
function xeroom_reduce_coupon_length( $coupon ) {
	if ( strlen( $coupon ) > 30 ) {
		$coupon = substr( $coupon, 0, 30 );
	}

	return $coupon;
}

/**
 * Reduce Item Name length to Xero max size
 *
 * @param $item_name
 *
 * @return mixed|string
 */
function xeroom_reduce_item_name_length( $item_name ) {
	if ( strlen( $item_name ) > 50 ) {
		$item_name = substr( $item_name, 0, 50 );
	}

	return $item_name;
}

/**
 * Check for Xero token
 *
 * @param $oauth2
 *
 * @throws Exception
 */
function xeroom_check_xero_token( $oauth2 ) {
	$now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

	if ( ! isset( $oauth2['expires'] ) ) {
		return;
	}

	$expires = new DateTime( '@' . $oauth2['expires'], new DateTimeZone( 'UTC' ) );

	if ( null === $expires || $now > $expires ) {
		xeroom_refresh_token();
	}
}

/**
 * Processes the client's shipping address and returns the formatted address data for Xero.
 *
 * @param array $shipAddress The shipping address array containing details like company, address lines, city, and postcode.
 * @param string $stateFullName The full name of the state or region.
 * @param string $countryFullName The full name of the country.
 * @param string $user_email The email address of the user.
 * @param string $first_name The first name of the user.
 * @param string $last_name The last name of the user.
 * @param string $xero_add_contact_name The contact name to be added in Xero.
 * @param object $order The WooCommerce order object.
 *
 * @return array The formatted client address data for Xero.
 */
function xeroom_client_address_to_sent( $shipAddress, $stateFullName, $countryFullName, $user_email, $first_name, $last_name, $xero_add_contact_name, $order ) {
	$xero_address_info = get_xero_option( 'xero_address_info' );
	$xero_contact_name = get_xero_option( 'xero_contact_name' );

	$woo_client_address = array(
		"AddressType"  => "POBOX",
		"AttentionTo"  => "",
		"AddressLine1" => ( 'xeroom_use_company' != $xero_contact_name ) ? $shipAddress['company'] : '',
		"AddressLine2" => $shipAddress['address_1'],
		"AddressLine3" => $shipAddress['address_2'],
		"City"         => $shipAddress['city'],
		"Region"       => $stateFullName,
		"Country"      => $countryFullName,
		"PostalCode"   => $shipAddress['postcode']
	);

	// Add the shipping address
	$delivery_address         = null;
	$invoice_delivery_address = get_xero_option( 'xero_invoice_delivery_address' );
	if ( ! empty( $order->has_shipping_address() ) && $invoice_delivery_address ) {
		if ( '2' === $invoice_delivery_address || ( '1' === $invoice_delivery_address && $order->get_billing_address_1() !== $order->get_shipping_address_1() ) ) {
			$delivery_address = array(
				"AddressType"  => "DELIVERY",
				"AttentionTo"  => "",
				"AddressLine1" => ( 'xeroom_use_company' != $xero_contact_name ) ? $shipAddress['company'] : '',
				"AddressLine2" => $order->get_shipping_address_1(),
				"AddressLine3" => $order->get_shipping_address_2(),
				"City"         => $order->get_shipping_city(),
				"Region"       => $order->get_shipping_state(),
				"Country"      => $order->get_billing_country(),
				"PostalCode"   => $order->get_billing_postcode()
			);
		}
	}

	if ( $delivery_address ) {
		$addresses = [ $woo_client_address, $delivery_address ];
	} else {
        $addresses = $woo_client_address;
    }
    
	if ( 'xeroom_use_woo_address' == $xero_address_info ) {
		return $addresses;
	} else {
		$user_address = xeroom_check_user_address( $user_email, $first_name, $last_name, $xero_add_contact_name, $xero_contact_name );
		if ( ! empty( $user_address['City'] ) ) {
			return xeroom_check_user_address( $user_email, $first_name, $last_name, $xero_add_contact_name, $xero_contact_name );
		} else {
			return $delivery_address ? [ $woo_client_address, $delivery_address ] : [ $woo_client_address ];
		}
	}
}

/**
 * Get client Xero Address
 *
 * @param $user_email
 *
 * @return array
 */
function xeroom_check_user_address( $user_email, $first_name, $last_name, $xero_add_contact_name, $xero_contact_name ) {
	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xero_tenant_id = $oauth2['tenant_id'];

	if ( 'xeroom_use_company' == $xero_contact_name ) {
		$where = 'Name="' . $xero_add_contact_name . '"';
	} elseif ( 'xeroom_use_email' == $xero_contact_name ) {
		$where = 'EmailAddress="' . $xero_add_contact_name . '"';
	} elseif ( 'xeroom_use_acno' == $xero_contact_name ) {
		$user = get_user_by( 'email', $user_email );
		if ( $user ) {
			$user_id                   = $user->ID;
			$saved_customer_account_no = get_user_meta( $user_id, 'xero_customer_no', true );

			if ( $saved_customer_account_no ) {
				$where = 'AccountNumber="' . $saved_customer_account_no . '"';
			}
		}
	} else {
		$where = 'FirstName="' . $first_name . '&&LastName=' . $last_name . '"';
	}

	$address = array();
	try {
		$result = $apiInstance->getContacts( $xero_tenant_id, null, $where );

		if ( isset( $result ) && ! empty( $result ) ) {
			foreach ( $result as $xero_address ) {
				$user_last_name  = $xero_address->getLastName();
				$user_first_name = $xero_address->getFirstName();
				$user_addresses  = $xero_address->getAddresses();

				foreach ( $user_addresses as $user_address ) {
					if (
						$user_first_name === $first_name &&
						$user_last_name === $last_name &&
						'POBOX' === $user_address['address_type']
					) {
						$address = array(
							"AddressType"  => $user_address['address_type'],
							"AttentionTo"  => $user_address['attention_to'],
							"AddressLine1" => $user_address['address_line1'],
							"AddressLine2" => $user_address['address_line2'],
							"AddressLine3" => $user_address['address_line3'],
							"City"         => $user_address['city'],
							"Region"       => $user_address['region'],
							"Country"      => $user_address['country'],
							"PostalCode"   => $user_address['postal_code'],
						);
						break;
					}
				}
			}
		}
	} catch ( Exception $e ) {
		error_log( 'Exception when calling AccountingApi->getContacts: ' . print_r( $e->getMessage(), true ) );
	}

	return array_filter( $address );
}

/**
 * Add Invoice History Note
 *
 * @param $InvoiceNumber
 * @param $customer_note
 */
function xeroom_add_invoice_history_record( $InvoiceNumber, $customer_note ) {
	if ( empty( $customer_note ) ) {
		return;
	}
	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xero_tenant_id = $oauth2['tenant_id'];

	$historyRecord = new XeroAPI\XeroPHP\Models\Accounting\HistoryRecord;
	$historyRecord->setDetails( wp_kses_post( $customer_note ) );

	$historyRecords      = new XeroAPI\XeroPHP\Models\Accounting\HistoryRecords;
	$arr_history_records = [];
	array_push( $arr_history_records, $historyRecord );
	$historyRecords->setHistoryRecords( $arr_history_records );

	try {
		$result = $apiInstance->createInvoiceHistory( $xero_tenant_id, $InvoiceNumber, $historyRecords );
	} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
		error_log( 'Exception when calling AccountingApi->updateOrCreateItems: ' . print_r( $e->getResponseBody(), true ) );
	}
}

/**
 * Fetch the last Invoice ID
 *
 * @return array|false|object|void
 */
function xeroom_get_last_invoice_ID( $invoice_prefix = '' ) {
	global $wpdb;

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$order_meta = $wpdb->prefix . 'wc_orders_meta';
	} else {
		$order_meta = $wpdb->postmeta;
	}


	if ( empty( $invoice_prefix ) ) {
		$get_meta = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT max( cast( meta_value as unsigned ) ) as invoice_no FROM " . $order_meta . " WHERE meta_key=%s",
				'xeroom_invoice_no_sent'
			)
		);
	} else {
		$get_meta = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MAX(CAST(SUBSTR(TRIM(meta_value),%d) AS UNSIGNED)) as invoice_no FROM " . $order_meta . " WHERE meta_key=%s AND meta_value LIKE %s",
				strlen( $invoice_prefix ) + 2,
				'xeroom_invoice_no_sent',
				'%' . $invoice_prefix . '%'
			)
		);
	}

	if ( $get_meta ) {
		return $get_meta->invoice_no;
	} else {
		return false;
	}
}

/**
 * Generate Xero Invoice Number
 *
 * @param $order_id
 * @param string $xeroom_invoice_no_sent
 *
 * @return array|int|mixed|object|string|void
 */
function xeroom_invoice_number( $order_id, $xeroom_invoice_no_sent = '' ) {
	$invoice_prefix        = get_xero_option( 'xero_invoice_prefix' );
	$invoice_start_no      = get_xero_option( 'xero_invoice_start_no' );
	$invoice_next_no       = get_xero_option( 'xero_invoice_next_no' );
	$invoice_prefix_active = get_xero_option( 'xeroom_invoice_no_active' );

	if ( 'xero' == $invoice_prefix_active ) {
		return '';
	}

	$xero_invoice_no = $order_id;

	$order = new WC_Order( $order_id );

	$woo_pdf_inv = $order->get_meta( '_invoice_number_display' );
	if ( $woo_pdf_inv ) {
		return esc_html( $woo_pdf_inv );
	}

	$invoice_meta_no = get_xero_option( 'xeroom_inv_number_meta_key' );
	if ( ! empty( $invoice_meta_no ) && 'meta' == $invoice_prefix_active ) {
		$custom_order_no_meta = $order->get_meta( $invoice_meta_no );

		if ( $custom_order_no_meta ) {
			$xero_invoice_no = esc_attr( $custom_order_no_meta );
		}
	} elseif ( $invoice_start_no || $invoice_prefix ) {
		if ( $invoice_start_no && ! empty( $invoice_start_no ) ) {
			if ( $invoice_next_no && $invoice_next_no >= $invoice_start_no ) {
				$xero_invoice_no = sanitize_text_field( $invoice_next_no );
			} else {
				$xero_invoice_no = sanitize_text_field( $invoice_start_no );
			}
		}

		if ( ! $invoice_next_no ) {
			if ( ! empty( $invoice_prefix ) ) {
				$last_invoice_no = xeroom_get_last_invoice_ID( $invoice_prefix );
				if ( $last_invoice_no && $last_invoice_no >= $invoice_start_no && strcmp( $invoice_prefix . '-' . $xero_invoice_no, $last_invoice_no ) ) {
					$get_int         = explode( '-', $last_invoice_no );
					$xero_invoice_no = end( $get_int ) + 1;
				}
				$xero_invoice_no = $invoice_prefix . '-' . $xero_invoice_no;
			} else {
				$last_invoice_no = xeroom_get_last_invoice_ID();
				if ( $last_invoice_no && $last_invoice_no <= $xero_invoice_no && $invoice_next_no >= $invoice_start_no ) {
					$xero_invoice_no = 1 + (int) $last_invoice_no;
				}
			}
		} else {
			if ( ! empty( $invoice_prefix ) ) {
				$last_invoice_no = xeroom_get_last_invoice_ID( $invoice_prefix );
				if ( $last_invoice_no && $last_invoice_no >= $invoice_start_no && strcmp( $invoice_prefix . '-' . $xero_invoice_no, $last_invoice_no ) ) {
					$get_int         = explode( '-', $last_invoice_no );
					$xero_invoice_no = end( $get_int ) + 1;
				}
				$xero_invoice_no = $invoice_prefix . '-' . $xero_invoice_no;
			} else {
				$last_invoice_no = xeroom_get_last_invoice_ID();
				if ( $last_invoice_no && $last_invoice_no <= $xero_invoice_no ) {
					$xero_invoice_no = 1 + (int) $last_invoice_no;
				}
			}
		}

		if ( ! empty( $invoice_prefix ) && empty( $invoice_start_no ) ) {
			$xero_invoice_no = $invoice_prefix . '-' . $order->get_order_number();
		}

		if ( ! empty( $xeroom_invoice_no_sent ) ) {
			$xero_invoice_no = sanitize_text_field( $xeroom_invoice_no_sent );
		}
	}

	return $xero_invoice_no;
}

/**
 * Generate Invoice Reference No.
 *
 * @param $order_id
 *
 * @return mixed|string
 */
function xeroom_generate_invoice_reference( $order_id ) {
	$invoice_reference          = get_xero_option( 'xero_invoice_reference_prefix' );
	$xero_include_payment_ref   = get_xero_option( 'xero_include_payment_ref' );
	$xero_include_customer_name = get_xero_option( 'xero_include_customer_name' );
	$xero_reference_select      = get_xero_option( 'xero_reference_select' );
	$order                      = new WC_Order( $order_id );

	$reference_no = $order->get_order_number();
	if ( ! $xero_reference_select || ( $xero_reference_select && 'xero_ref_order_no' === $xero_reference_select ) ) {
		if ( $invoice_reference ) {
			$reference_no = $invoice_reference . '-' . $order->get_order_number();
		}

		if ( ! empty( $order->get_payment_method() ) ) {
			$reference_no .= '-' . strtoupper( $order->get_payment_method() );
		}

		$transaction_info = '';
		if ( $order->get_transaction_id() ) {
			$transaction_info = mb_substr( $order->get_transaction_id(), - 7, null, 'UTF-8' );
		}

		$woo_pdf_inv = $order->get_meta( '_invoice_number_display' );
		if ( $woo_pdf_inv ) {
			$reference_no = $order->get_order_number();
		}

		if ( $xero_include_payment_ref ) {
			$reference_no .= '-' . $transaction_info;
		}

		if ( $xero_include_customer_name ) {
			$customer_name = $order->get_billing_first_name();
			if ( $order->get_billing_last_name() ) {
				$customer_name .= ' ' . $order->get_billing_last_name();
			}

			$reference_no .= '-' . $customer_name;
		}
	} elseif ( $xero_reference_select && 'xero_ref_purchase_order_no' === $xero_reference_select ) {
		$invoice_meta_no = get_xero_option( 'xeroom_inv_number_meta_key' );

		if ( $invoice_meta_no ) {
			$custom_order_no_meta = $order->get_meta( $invoice_meta_no );

			if ( $custom_order_no_meta ) {
				$reference_no = $custom_order_no_meta;
			}
		}
	} elseif ( $xero_reference_select && 'xero_invoice_purchase_order_no' === $xero_reference_select ) {
		$invoice_meta_no = get_xero_option( 'xeroom_inv_reference_meta_key' );

		if ( $invoice_meta_no ) {
			$custom_order_no_meta = $order->get_meta( $invoice_meta_no );

			if ( $custom_order_no_meta ) {
				$reference_no = $custom_order_no_meta;
			}
		}
	} else {
		$purchase_no = $order->get_meta( '_purchase_order_number' );
		if ( $purchase_no ) {
			$reference_no = esc_attr( $purchase_no );
		}
	}


	return $reference_no;
}

/**
 * Xeroom add Order Notes
 *
 * @return mixed|void
 */
function xeroom_add_order_notes() {
	return get_xero_option( 'xero_order_notes' );
}

function xeroom_update_items_accounts( $oderList ) {
	if ( ! $oderList ) {
		return;
	}

	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xeroTenantId    = $oauth2['tenant_id'];
	$summarizeErrors = true;

	$arr_items = array();
	foreach ( $oderList as $woo_product ) {
		$product_sku = $woo_product['ItemCode'];
		$product_id  = $woo_product['_product_id'];

		if ( '' == $product_sku ) {
			$product_sku = sprintf( '%s %s', __( 'Product ID ' ), $product_id );
		}
		$product_sku = xeroom_reduce_sku_length( $product_sku );

		$sales    = new XeroAPI\XeroPHP\Models\Accounting\Purchase;
		$purchase = new XeroAPI\XeroPHP\Models\Accounting\Purchase;

		$sales->setUnitPrice( $woo_product['UnitAmount'] )
		      ->setAccountCode( $woo_product['AccountCode'] );

		$purchase->setUnitPrice( $woo_product['UnitAmount'] )
		         ->setCOGSAccountCode( $woo_product['COGSAccountCode'] );

		$item = new XeroAPI\XeroPHP\Models\Accounting\Item;
		$item->setName( xeroom_reduce_item_name_length( $woo_product['Description'] ) )
		     ->setCode( $product_sku )
//		     ->setInventoryAssetAccountCode( $woo_product['InventoryAssetAccountCode'] )
             ->setSalesDetails( $sales )
		     ->setIsTrackedAsInventory( false )
		     ->setPurchaseDetails( $purchase );
		array_push( $arr_items, $item );
	}

	$items = new XeroAPI\XeroPHP\Models\Accounting\Items;
	$items->setItems( $arr_items );

	try {
		$result = $apiInstance->updateOrCreateItems( $xeroTenantId, $items, $summarizeErrors );
	} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
		error_log( 'Exception when calling AccountingApi->updateOrCreateItems: ' . print_r( $e->getResponseBody(), true ) );
	}
}


/**
 * Generate PrePayment
 *
 * @param $order_id
 */
function xeroom_generate_prepayment( $order_id ) {
	if ( ! $order_id ) {
		return;
	}

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->get_id();
	}

	$order = new WC_Order( $order_id );

	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xeroTenantId = $oauth2['tenant_id'];

	$where      = 'EmailAddress="' . $order->get_billing_email() . '"';
	$getContact = $apiInstance->getContacts( $xeroTenantId, null, $where );
	$contactId  = $getContact->getContacts()[0]->getContactId();

	global $wpdb;
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );
	$BankCode          = esc_attr( $getApiCredentials[0]->bank_code );

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$oder_gateway_code = $order->get_payment_method();
	} else {
		$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
	}

	if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
		$oder_gateway_code = 'accountfunds';
	}

	if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
		$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
	}

	$lineitems        = array();
	$countOrder       = 0;
	$allocation_total = 0;
	foreach ( $order->get_items() as $singleorderskey => $singleorders ) {
		$lineitems[ $countOrder ] = array(
			'Name'        => $singleorders['name'],
			'Description' => $singleorders['name'],
			'Quantity'    => $singleorders['quantity'],
			'AccountCode' => $BankCode,
			'UnitAmount'  => $order->get_item_total( $singleorders ),
			'TaxType'     => 'NONE',
			'TaxAmount'   => 0,
		);

		$allocation_total += $order->get_item_total( $singleorders );

		$countOrder ++;
	}

	$where      = 'Status=="' . \XeroAPI\XeroPHP\Models\Accounting\Account::STATUS_ACTIVE . '" AND Type=="' . \XeroAPI\XeroPHP\Models\Accounting\Account::BANK_ACCOUNT_TYPE_BANK . '"';
	$getAccount = $apiInstance->getAccounts( $xeroTenantId, null, $where );
	$accountId  = $getAccount->getAccounts()[0]->getAccountId();

	$xero_default_accounts = get_xero_option( 'xero_default_accounts' );

	$bank_index = array_search( $BankCode, array_column( $xero_default_accounts, 'Code' ) );

	if ( false !== $bank_index ) {
		$contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact->setContactId( $contactId );

		$accountId = $xero_default_accounts[ $bank_index ]['AccountID'];

		$bankAccount = new XeroAPI\XeroPHP\Models\Accounting\Account;
		$bankAccount->setCode( $getAccount->getAccounts()[0]->getCode() )
		            ->setAccountId( $accountId );

		$prepayment = new XeroAPI\XeroPHP\Models\Accounting\BankTransaction;
		$prepayment->setReference( xeroom_generate_invoice_reference( $order_id ) )
		           ->setDate( date( 'Y-m-d' ) )
		           ->setType( XeroAPI\XeroPHP\Models\Accounting\BankTransaction::TYPE_RECEIVE_PREPAYMENT )
		           ->setLineItems( $lineitems )
		           ->setContact( $contact )
		           ->setLineAmountTypes( "NoTax" )
		           ->setBankAccount( $bankAccount );

		$result       = $apiInstance->createBankTransactions( $xeroTenantId, $prepayment );
		$prepaymentId = $result->getBankTransactions()[0]->getPrepaymentId();

		if ( $prepaymentId ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$invoice_id = $order->get_meta( 'post_content_filtered' );
			} else {
				$order_details = get_post( $order_id );
				$invoice_id    = $order_details->post_content_filtered;
			}

			$invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
			$invoice->setInvoiceID( $invoice_id );

			$arr_allocations = [];

			$allocation_1 = new XeroAPI\XeroPHP\Models\Accounting\Allocation;
			$allocation_1->setInvoice( $invoice )
			             ->setAmount( $allocation_total )
			             ->setDate( date( 'Y-m-d' ) );
			array_push( $arr_allocations, $allocation_1 );

			$allocations = new XeroAPI\XeroPHP\Models\Accounting\Allocations;
			$allocations->setAllocations( $arr_allocations );

			$all_result = $apiInstance->createOverpaymentAllocations( $xeroTenantId, $prepaymentId, $allocations );

			if ( $all_result->getAllocations()[0]->getAmount() ) {
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order->update_meta_data( 'xeroom_payment_sent', 'Pre-Payment Sent to Xero' );
					$order->save();
				} else {
					update_post_meta( $order_id, 'xeroom_payment_sent', 'Pre-Payment Sent to Xero' );
				}

				// Add order note for successfully sent data to Xero
				$xero_order_note = __( 'The Pre-Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_success_message_order',
					$xero_order_note,
					$order
				);
				$order->add_order_note( $xero_order_note );

				esc_html_e( 'Account Funds Top-up successful!' );
			} else {
				esc_html_e( 'Account Funds Top-up failed! Please try again.' );
			}
			die();
		}
	}
}

add_action( 'woocommerce_order_status_completed', 'xeroom_generate_partial_payment', 5, 1 );
add_action( 'woocommerce_order_status_processing', 'xeroom_generate_partial_payment', 5, 1 );

/**
 * Generate Partial Payment
 *
 * @param $order_id
 */
function xeroom_generate_partial_payment( $order_id ) {
	if ( ! $order_id ) {
		return;
	}

	if ( is_object( $order_id ) ) {
		$order_id = $order_id->get_id();
	}

	$order = wc_get_order( $order_id );

	if ( defined( 'AWCDP_POST_TYPE' ) && $order->get_type() !== AWCDP_POST_TYPE ) {
		return;
	}

	$xero_wc_partial_deposit = get_xero_option( 'xero_wc_partial_deposit' );
	if ( ! $xero_wc_partial_deposit ) {
		return;
	}

	if ( $order->get_meta( 'xeroom_payment_sent', true ) ) {
		return;
	}

	if ( ! $order->get_parent_id() ) {
		return;
	}

	$parent_order = new WC_Order( $order->get_parent_id() );
	$order_sent   = $parent_order->get_meta( 'xeroom_order_sent', true );
	if ( ! $order_sent ) {
		xeroom_sendWooInvoiceToXero( $order->get_parent_id(), 'orderProduct' );
	}

	$oauth2 = get_xero_option( 'xero_oauth_options' );
	xeroom_check_xero_token( $oauth2 );

	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xeroTenantId = $oauth2['tenant_id'];

	$contactId = '';
	if ( $parent_order->get_billing_email() ) {
		$where      = 'EmailAddress="' . $parent_order->get_billing_email() . '"';
		$getContact = $apiInstance->getContacts( $xeroTenantId, null, $where );

		if ( $getContact->getContacts() ) {
			$contactId = $getContact->getContacts()[0]->getContactId();
		}
	}

	global $wpdb;
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );
	$salesAccount      = sanitize_text_field( $getApiCredentials[0]->sales_account );
	$BankCode          = esc_attr( $getApiCredentials[0]->bank_code );

	$has_deposit = $parent_order->get_meta( '_awcdp_deposits_order_has_deposit', true );

	if ( $has_deposit !== 'yes' ) {
		return;
	}

	if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$oder_gateway_code = $order->get_payment_method();
	} else {
		$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
	}

	$lineitems        = array();
	$countOrder       = 0;
	$allocation_total = 0;

	foreach ( $order->get_items( 'fee' ) as $singleorderskey => $singleorders ) {
		$lineitems[ $countOrder ] = array(
			'Name'        => $singleorders['name'],
			'Description' => $singleorders['name'],
			'Quantity'    => 1,
			'AccountCode' => $salesAccount,
			'UnitAmount'  => $order->get_item_total( $singleorders ),
			'TaxType'     => 'NONE',
			'TaxAmount'   => 0,
		);

		$allocation_total += $order->get_item_total( $singleorders );

		$countOrder ++;
	}

	$where      = 'Status=="' . \XeroAPI\XeroPHP\Models\Accounting\Account::STATUS_ACTIVE . '" AND Type=="' . \XeroAPI\XeroPHP\Models\Accounting\Account::BANK_ACCOUNT_TYPE_BANK . '"';
	$getAccount = $apiInstance->getAccounts( $xeroTenantId, null, $where );
	$accountId  = $getAccount->getAccounts()[0]->getAccountId();

	$xero_default_accounts = get_xero_option( 'xero_default_accounts' );

	if ( $BankCode && $contactId ) {
		$contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact->setContactId( $contactId );

		$search_account = array_search( $BankCode, $xero_default_accounts, true );
		$accountId      = $xero_default_accounts[ $search_account ]['AccountID'];

		$bankAccount = new XeroAPI\XeroPHP\Models\Accounting\Account;
		$bankAccount->setCode( $getAccount->getAccounts()[0]->getCode() )
		            ->setAccountId( $accountId );

		$prepayment = new XeroAPI\XeroPHP\Models\Accounting\BankTransaction;
		$prepayment->setReference( xeroom_generate_invoice_reference( $order_id ) )
		           ->setDate( date( 'Y-m-d' ) )
		           ->setType( XeroAPI\XeroPHP\Models\Accounting\BankTransaction::TYPE_RECEIVE_PREPAYMENT )
		           ->setLineItems( $lineitems )
		           ->setContact( $contact )
		           ->setLineAmountTypes( "NoTax" )
		           ->setBankAccount( $bankAccount );

		$result       = $apiInstance->createBankTransactions( $xeroTenantId, $prepayment );
		$prepaymentId = $result->getBankTransactions()[0]->getPrepaymentId();

		if ( $prepaymentId ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$parent_order = new WC_Order( $order->get_parent_id() );
				$invoice_id   = $parent_order->get_meta( 'post_content_filtered' );
			} else {
				$order_details = get_post( $order->get_parent_id() );
				$invoice_id    = $order_details->post_content_filtered;
			}

			$invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
			$invoice->setInvoiceID( $invoice_id );

			$arr_allocations = [];

			$allocation_1 = new XeroAPI\XeroPHP\Models\Accounting\Allocation;
			$allocation_1->setInvoice( $invoice )
			             ->setAmount( $allocation_total )
			             ->setDate( date( 'Y-m-d' ) );
			array_push( $arr_allocations, $allocation_1 );

			$allocations = new XeroAPI\XeroPHP\Models\Accounting\Allocations;
			$allocations->setAllocations( $arr_allocations );

			$all_result = $apiInstance->createOverpaymentAllocations( $xeroTenantId, $prepaymentId, $allocations );

			if ( $all_result->getAllocations()[0]->getAmount() ) {
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order->update_meta_data( 'xeroom_payment_sent', 'Pre-Payment Sent to Xero' );
					$order->save();
				} else {
					update_post_meta( $order_id, 'xeroom_payment_sent', 'Pre-Payment Sent to Xero' );
				}

				// Add order note for successfully sent data to Xero
				$xero_order_note = __( 'The Pre-Payment has been successfully sent to Xero using Xeroom', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_success_message_order',
					$xero_order_note,
					$order
				);
				$order->add_order_note( $xero_order_note );
			} else {
//				esc_html_e( 'Account Funds Top-up failed! Please try again.' );
			}
		}
	}
}

/**
 * Add the Stripe fee, if any!
 *
 * @param $order_id
 */
function xeroom_add_xero_invoice_payment_fee( $order_id ) {
	if ( ! $order_id ) {
		return;
	}

	$xero_send_stripe_fee = get_xero_option( 'xero_send_stripe_fee' );

	if ( ! $xero_send_stripe_fee ) {
		return;
	}

	$summarizeErrors     = true;
	$order               = new WC_Order( $order_id );
	$stripe_bank_account = get_xero_option( 'xeroom_stripe_fee_account' );

	if ( $order->get_meta( '_stripe_fee' ) && $stripe_bank_account ) {

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

		$config->setHost( "https://api.xero.com/api.xro/2.0" );

		$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
			new GuzzleHttp\Client(),
			$config
		);

		$xeroTenantId = $oauth2['tenant_id'];

		// Calculate Stripe Fee with no tax
		$xero_default_accounts = get_xero_option( 'xero_default_accounts' );
		$bank_index            = array_search( $stripe_bank_account, array_column( $xero_default_accounts, 'Code' ) );
		$tax_type              = $xero_default_accounts[ $bank_index ]['TaxType'];
		$tax_amount            = xero_tax_amount_by_type( $tax_type );
		$stripe_fee            = $order->get_meta( '_stripe_fee' );
		$stripe_fee            = $stripe_fee / ( ( $tax_amount / 100 ) + 1 );
		$stripe_fee            = round( $stripe_fee, wc_get_price_decimals() );

		$lineitems = array(
			array(
				'Description' => __( 'Stripe Fee', 'xeroom' ),
				'Quantity'    => 1,
				'UnitAmount'  => $stripe_fee,
				'AccountCode' => $stripe_bank_account,
			)
		);

		$arr_contacts = [];
		$contact_1    = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact_1->setName( 'Stripe Fees' );
		array_push( $arr_contacts, $contact_1 );

		$contacts = new XeroAPI\XeroPHP\Models\Accounting\Contacts;
		$contacts->setContacts( $arr_contacts );

		$getContact = $apiInstance->updateOrCreateContacts( $xeroTenantId, $contacts, false );
		$contactId  = $getContact->getContacts()[0]->getContactId();

		$contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact->setContactId( $contactId );

		$invoice_no = xeroom_invoice_number( $order_id );

		$invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
		$invoice->setReference( xeroom_generate_invoice_reference( $order_id ) )
		        ->setInvoiceNumber( $invoice_no )
		        ->setDueDate( date( 'Y-m-d' ) )
		        ->setStatus( XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_AUTHORISED )
		        ->setType( XeroAPI\XeroPHP\Models\Accounting\Invoice::TYPE_ACCPAY )
		        ->setLineItems( $lineitems )
		        ->setContact( $contact );

		try {
			$result = $apiInstance->createInvoices( $xeroTenantId, $invoice );

			if ( $result->getInvoices()[0]->getInvoiceId() ) {
				// Add order note for successfully sent data to Xero
				$xero_order_note = __( 'The Stripe Fee has been successfully sent to Xero using Xeroom', 'xeroom' );

				$xero_order_note = apply_filters(
					'xero_add_success_message_order',
					$xero_order_note,
					$order
				);
				$order->add_order_note( $xero_order_note );

				xeroom_send_bill_payment( $result->getInvoices()[0]->getInvoiceId(), $stripe_bank_account, $order->get_meta( '_stripe_fee' ), $order_id );
			}
		} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
			error_log( 'Exception when calling AccountingApi->Invoice: ' . print_r( $e->getResponseBody(), true ) );
			$line_items_update = array();
		}
	}
}

/**
 * Send the Stripe Fee payment
 *
 * @param $invoice_id
 * @param $bank
 * @param $amount
 *
 * @throws XeroException
 */
function xeroom_send_bill_payment( $invoice_id, $bank, $amount, $order_id ) {
	if ( ! $invoice_id ) {
		return;
	}

	$xero_send_stripe_fee = get_xero_option( 'xero_send_stripe_fee' );

	if ( ! $xero_send_stripe_fee ) {
		return;
	}

	include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
	global $wpdb;
	$errorType = "productOrder";

	$xeroTime          = date( 'Y-m-d H:i:s' );
	$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
	$getApiCredentials = $wpdb->get_results( $query );

	if ( $getApiCredentials ) {
		$xeroApiKey    = sanitize_text_field( $getApiCredentials[0]->xero_api_key );
		$xeroApiSecret = sanitize_text_field( $getApiCredentials[0]->xero_api_secret );
		$salesAccount  = sanitize_text_field( $getApiCredentials[0]->sales_account );
		$sendOrNot     = $getApiCredentials[0]->payment_master;
		$BankCode      = sanitize_text_field( $getApiCredentials[0]->bank_code );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order             = new WC_Order( $order_id );
			$oder_gateway_code = $order->get_payment_method();
		} else {
			$oder_gateway_code = get_post_meta( $order_id, '_payment_method', true );
		}

		if ( class_exists( 'WC_Account_Funds_Order_Manager' ) && WC_Account_Funds_Order_Manager::order_contains_deposit( $order_id ) ) {
			$oder_gateway_code = 'accountfunds';
			xeroom_generate_prepayment( $order_id );

			return;
		}
		if ( xeroom_get_bank_account_code( $oder_gateway_code ) > 0 ) {
			$BankCode = xeroom_get_bank_account_code( $oder_gateway_code );
		}

		$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
		if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
			return;
		}

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'XERO_KEY' ) ) {
			define( 'XERO_KEY', $xeroApiKey );
		}

		if ( ! defined( 'XERO_SECRET' ) ) {
			define( 'XERO_SECRET', $xeroApiSecret );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', '' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', '' );
		}

		if ( ! defined( 'FORMAT' ) ) {
			define( 'FORMAT', 'json' );
		}

		$xero_timezone = new DateTimeZone( 'UTC' );
		$xero_time     = date( 'Y-m-d H:i:s' );
		$xero_date     = new DateTime( $xero_time, $xero_timezone );

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );
		$xero = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

		$invoice_no   = xeroom_invoice_number( $order_id );
		$reference_no = xeroom_generate_invoice_reference( $order_id );

		$new_payment = array(
			array(
				"Invoice"   => array(
					"InvoiceID"     => $invoice_id,
					"InvoiceNumber" => $invoice_no,
				),
				"Account"   => array(
					"Code" => $BankCode
				),
				"Date"      => $xero_date,
				"Amount"    => $amount,
				"Reference" => $reference_no
			)
		);
		$paymentXero = $xero->Payments( $new_payment );

		if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
			$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] . ". Please Create your bank account on xero and update bank code in xeroom setting's bank code.";
			returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
		} else if ( isset( $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
			$errD = array();
			$errD = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
			for ( $e = 0; $e < count( $errD ); $e ++ ) {
				$errorMessage = $paymentXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
				returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
			}
		}
	} else {
		$mMessage = "Xeroom credentials are not set or invalid, Please contact Xeroom support team.";
		returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
	}
}

/**
 * Send Items as Batch before Invoice generation
 *
 * @param $invoice_items
 */
function xeroom_send_batch_items_before_invoice( $invoice_items ) {
	if ( $invoice_items ) {
		global $wpdb;
		$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
		$getApiCredentials = $wpdb->get_results( $query );

		$StockMaster = '';
		if ( $getApiCredentials ) {
			$StockMaster = sanitize_text_field( $getApiCredentials[0]->stock_master );
		}

		if ( ! \function_exists( 'GuzzleHttp\json_encode' ) ) {
			require XEROOM_ROOT_PATH . 'vendor/guzzlehttp/guzzle/src/functions.php';
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

		$config->setHost( "https://api.xero.com/api.xro/2.0" );

		$apiInstance     = new XeroAPI\XeroPHP\Api\AccountingApi(
			new GuzzleHttp\Client(),
			$config
		);
		$xeroTenantId    = $oauth2['tenant_id'];
		$summarizeErrors = true;

		$arr_items = array();
		foreach ( $invoice_items as $send_xero_item ) {
			$sales    = new XeroAPI\XeroPHP\Models\Accounting\Purchase;
			$purchase = new XeroAPI\XeroPHP\Models\Accounting\Purchase;
			$item     = new XeroAPI\XeroPHP\Models\Accounting\Item;

			if ( ! mb_check_encoding( $send_xero_item, 'UTF-8' ) ) {
				// Convert data to UTF-8 if it's not already in UTF-8
				$send_xero_item = mb_convert_encoding( $send_xero_item, 'UTF-8', 'auto' );
			}

			if ( isset( $send_xero_item['SalesDetails']['UnitPrice'] ) ) {
				if ( ! empty( $send_xero_item['SalesDetails']['TaxType'] ) ) {
					$sales->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['SalesDetails']['UnitPrice'] ) ) )
					      ->setTaxType( $send_xero_item['SalesDetails']['TaxType'] )
					      ->setAccountCode( $send_xero_item['SalesDetails']['AccountCode'] );
				} else {
					$sales->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['SalesDetails']['UnitPrice'] ) ) )
					      ->setAccountCode( $send_xero_item['SalesDetails']['AccountCode'] );
				}

				$item->setSalesDetails( $sales );
			} elseif ( isset( $send_xero_item['Item']['SalesDetails']['UnitPrice'] ) ) {
				if ( ! empty( $send_xero_item['Item']['SalesDetails']['TaxType'] ) ) {
					$sales->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['Item']['SalesDetails']['UnitPrice'] ) ) )
					      ->setTaxType( $send_xero_item['Item']['SalesDetails']['TaxType'] )
					      ->setAccountCode( $send_xero_item['Item']['SalesDetails']['AccountCode'] );
				} else {
					$sales->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['Item']['SalesDetails']['UnitPrice'] ) ) )
					      ->setAccountCode( $send_xero_item['Item']['SalesDetails']['AccountCode'] );
				}

				$item->setSalesDetails( $sales );
			}

			if ( isset( $send_xero_item['PurchaseDetails']['UnitPrice'] ) ) {
				$COGSAccountCode = isset( $send_xero_item['PurchaseDetails']['AccountCode'] ) ? $send_xero_item['PurchaseDetails']['AccountCode'] : $send_xero_item['PurchaseDetails']['COGSAccountCode'];
				$purchase->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['PurchaseDetails']['UnitPrice'] ) ) );

				$item->setPurchaseDetails( $purchase );
			} elseif ( isset( $send_xero_item['Item']['PurchaseDetails']['UnitPrice'] ) ) {
				$COGSAccountCode = isset( $send_xero_item['Item']['PurchaseDetails']['AccountCode'] ) ? $send_xero_item['Item']['PurchaseDetails']['AccountCode'] : $send_xero_item['Item']['PurchaseDetails']['COGSAccountCode'];
				$purchase->setUnitPrice( wc_format_decimal( floatval( $send_xero_item['Item']['PurchaseDetails']['UnitPrice'] ) ) );

				$item->setPurchaseDetails( $purchase );
			}

			if ( isset( $send_xero_item['Code'] ) ) {
				$item->setCode( $send_xero_item['Code'] );
			} else if ( isset( $send_xero_item['Item']['Code'] ) ) {
				$item->setCode( $send_xero_item['Item']['Code'] );
			}

			if ( isset( $send_xero_item['Name'] ) ) {
				$item->setName( $send_xero_item['Name'] );
			} else if ( isset( $send_xero_item['Item']['Name'] ) ) {
				$item->setName( $send_xero_item['Item']['Name'] );
			}

			if ( isset( $send_xero_item['Description'] ) ) {
				$item->setDescription( $send_xero_item['Description'] );
			} else if ( isset( $send_xero_item['Item']['Description'] ) ) {
				$item->setDescription( $send_xero_item['Item']['Description'] );
			}

			if ( isset( $send_xero_item['PurchaseDescription'] ) ) {
				$item->setPurchaseDescription( $send_xero_item['PurchaseDescription'] );
			} else if ( isset( $send_xero_item['Item']['PurchaseDescription'] ) ) {
				$item->setPurchaseDescription( $send_xero_item['Item']['PurchaseDescription'] );
			}

			$item->setIsTrackedAsInventory( false );

			if ( isset( $send_xero_item['IsSold'] ) ) {
				$item->setIsSold( $send_xero_item['IsSold'] );
			} else if ( isset( $send_xero_item['Item']['IsSold'] ) ) {
				$item->setIsSold( $send_xero_item['Item']['IsSold'] );
			}

			if ( isset( $send_xero_item['IsPurchased'] ) ) {
				$item->setIsPurchased( $send_xero_item['IsPurchased'] );
			} else if ( isset( $send_xero_item['Item']['IsPurchased'] ) ) {
				$item->setIsPurchased( $send_xero_item['Item']['IsPurchased'] );
			}

			array_push( $arr_items, $item );
		}

		$items = new XeroAPI\XeroPHP\Models\Accounting\Items;
		$items->setItems( $arr_items );
		try {
			$result = $apiInstance->updateOrCreateItems( $xeroTenantId, $items, $summarizeErrors );
		} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
			error_log( 'Exception when calling AccountingApi->updateOrCreateItems: ' . print_r( $e->getResponseBody(), true ) );
		}
	}
}


/**
 * Send Batch Stock for Items Before Invoice
 *
 * This function sends a batch stock for items before creating an invoice in Xeroom.
 * It expects an array of items as input.
 * Each item in the array should contain the following properties:
 *   - sku: The SKU (stock keeping unit) of the item.
 *   - quantity: The quantity of the item to be sent.
 *
 * @param array $items An array of items with SKU and quantity.
 *
 * @return void
 */
function xeroom_send_batch_stock_for_items_before_invoice( $items ) {
	if ( $items ) {
		global $wpdb;
		$query             = "SELECT *  FROM `" . $wpdb->prefix . "xeroom_credentials` WHERE `id`=1";
		$getApiCredentials = $wpdb->get_results( $query );

		$StockMaster = '';
		if ( $getApiCredentials ) {
			$StockMaster = sanitize_text_field( $getApiCredentials[0]->stock_master );
		}

		if ( empty( $StockMaster ) && 'x' == $StockMaster ) {
			return;
		}

		if ( ! \function_exists( 'GuzzleHttp\json_encode' ) ) {
			require XEROOM_ROOT_PATH . 'vendor/guzzlehttp/guzzle/src/functions.php';
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

		$config->setHost( "https://api.xero.com/api.xro/2.0" );

		$apiInstance  = new XeroAPI\XeroPHP\Api\AccountingApi(
			new GuzzleHttp\Client(),
			$config
		);
		$xeroTenantId = $oauth2['tenant_id'];

		$add_Stock    = [];
		$remove_Stock = [];
		foreach ( $items as $send_xero_item ) {
			$lineitem       = new XeroAPI\XeroPHP\Models\Accounting\LineItem;
			$adjustlineitem = new XeroAPI\XeroPHP\Models\Accounting\LineItem;
			if ( isset( $send_xero_item['Invoice']['LineItems']['LineItem']['QuantityOnHand'] ) && $send_xero_item['Invoice']['LineItems']['LineItem']['Quantity'] > $send_xero_item['Invoice']['LineItems']['LineItem']['QuantityOnHand'] ) {
				if ( isset( $send_xero_item['Invoice'] ) ) {
					// This is for the item entry.
					$lineitem->setItemCode( $send_xero_item['Invoice']['LineItems']['LineItem']['ItemCode'] )
					         ->setDescription( $send_xero_item['Invoice']['LineItems']['LineItem']['Description'] )
					         ->setQuantity( $send_xero_item['Invoice']['LineItems']['LineItem']['Quantity'] )
					         ->setUnitAmount( $send_xero_item['Invoice']['LineItems']['LineItem']['UnitAmount'] )
					         ->setAccountCode( $send_xero_item['Invoice']['LineItems']['LineItem']['AccountCode'] );

					array_push( $add_Stock, $lineitem );

					// This is for negative quantity.
					$adjustlineitem->setDescription( 'Inventory Adjustment' )
					               ->setQuantity( $send_xero_item['Invoice']['LineItems']['LineItem']['Quantity'] )
					               ->setUnitAmount( - $send_xero_item['Invoice']['LineItems']['LineItem']['UnitAmount'] )
					               ->setAccountCode( $send_xero_item['Invoice']['LineItems']['LineItem']['COGSAccountCode'] );

					array_push( $add_Stock, $adjustlineitem );
				}
			}
			if ( isset( $send_xero_item['CreditNote']['LineItems']['LineItem']['QuantityOnHand'] ) && $send_xero_item['CreditNote']['LineItems']['LineItem']['Quantity'] > $send_xero_item['CreditNote']['LineItems']['LineItem']['QuantityOnHand'] ) {
				if ( isset( $send_xero_item['CreditNote'] ) ) {
					$lineitem->setItemCode( $send_xero_item['CreditNote']['LineItems']['LineItem']['ItemCode'] )
					         ->setDescription( $send_xero_item['CreditNote']['LineItems']['LineItem']['Description'] )
					         ->setQuantity( $send_xero_item['CreditNote']['LineItems']['LineItem']['Quantity'] )
					         ->setUnitAmount( $send_xero_item['CreditNote']['LineItems']['LineItem']['UnitAmount'] )
					         ->setAccountCode( $send_xero_item['CreditNote']['LineItems']['LineItem']['AccountCode'] );

					array_push( $remove_Stock, $lineitem );

					$adjustlineitem->setDescription( $send_xero_item['CreditNote']['LineItems']['LineItem']['Description'] )
					               ->setQuantity( $send_xero_item['CreditNote']['LineItems']['LineItem']['Quantity'] )
					               ->setUnitAmount( - $send_xero_item['CreditNote']['LineItems']['LineItem']['UnitAmount'] )
					               ->setAccountCode( $send_xero_item['CreditNote']['LineItems']['LineItem']['COGSAccountCode'] );

					array_push( $remove_Stock, $lineitem );
				}
			}
		}

		$arr_contacts = [];
		$contact_1    = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact_1->setName( 'Inventory Adjustments' );
		array_push( $arr_contacts, $contact_1 );

		$contacts = new XeroAPI\XeroPHP\Models\Accounting\Contacts;
		$contacts->setContacts( $arr_contacts );

		$getContact = $apiInstance->updateOrCreateContacts( $xeroTenantId, $contacts, false );
		$contactId  = $getContact->getContacts()[0]->getContactId();

		$contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
		$contact->setContactId( $contactId );

		if ( $add_Stock ) {
			$invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;

			$invoice->setDueDate( date( 'Y-m-d' ) )
			        ->setContact( $contact )
			        ->setLineItems( $add_Stock )
			        ->setStatus( XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_AUTHORISED )
			        ->setType( XeroAPI\XeroPHP\Models\Accounting\Invoice::TYPE_ACCPAY )
			        ->setLineAmountTypes( \XeroAPI\XeroPHP\Models\Accounting\LineAmountTypes::NO_TAX );

			try {
				$result = $apiInstance->createInvoices( $xeroTenantId, $invoice );
			} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
				error_log( 'Exception when calling Inventory Adjustments AccountingApi->createInvoices: ' . print_r( $e->getResponseBody(), true ) );
			}
		}

		if ( $remove_Stock ) {
			$creditnote = new XeroAPI\XeroPHP\Models\Accounting\CreditNote;

			$creditnote->setDate( date( 'Y-m-d' ) )
			           ->setContact( $contact )
			           ->setLineItems( $remove_Stock )
			           ->setStatus( XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_AUTHORISED )
			           ->setType( XeroAPI\XeroPHP\Models\Accounting\CreditNote::TYPE_ACCPAYCREDIT )
			           ->setLineAmountTypes( \XeroAPI\XeroPHP\Models\Accounting\LineAmountTypes::NO_TAX );
			try {
				$result = $apiInstance->createCreditNotes( $xeroTenantId, $creditnote );
			} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
				error_log( 'Exception when calling Inventory Adjustments AccountingApi->createCreditNotes: ' . print_r( $e->getResponseBody(), true ) );
			}
		}
	}
}

/**
 * Get coupon details by order ID
 *
 * @param int $order_id The ID of the order
 *
 * @return array An array containing coupon details
 */
function get_coupons_details_by_order( $order_id ) {
	$order          = wc_get_order( $order_id );
	$coupons        = $order->get_coupon_codes();
	$coupon_details = [];

	if ( ! empty( $coupons ) ) {
		foreach ( $coupons as $coupon_code ) {
			$coupon             = new WC_Coupon( $coupon_code );
			$discount_type      = $coupon->get_discount_type();
			$product_ids        = $coupon->get_product_ids();
			$product_categories = $coupon->get_product_categories();

			// Initialize arrays to store details
			$applied_products   = [];
			$applied_categories = [];

			// Check all items in the order
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();

				// Check if this product is directly in the product_ids list
				if ( in_array( $product_id, $product_ids ) ) {
					$applied_products[ $product_id ] = $product_id;
				}

				// Check if this product's category is in the product_categories list
				$item_product = $item->get_product();
				if ( $item_product ) {
					$item_categories = $item_product->get_category_ids();
					if ( array_intersect( $item_categories, $product_categories ) ) {
						$applied_categories[] = $item_id;
					}
				}
			}

			// Save details in the array
			$coupon_details[ $coupon_code ] = [
				'discount_type'         => $discount_type,
				'applied_to_products'   => $applied_products,
				'applied_to_categories' => $applied_categories
			];
		}
	}

	return $coupon_details;
}

/**
 * Calculate the due date based on the provided invoice date, day, and type.
 *
 * @param string $invoiceDate The date of the invoice in 'Y-m-d' format.
 * @param int $day The day or number of days to calculate the due date.
 * @param string $type The type of due date calculation. Supported types are 'DAYSAFTERBILLMONTH', 'DAYSAFTERBILLDATE', and 'OFFOLLOWINGMONTH'.
 *
 * @return string The calculated due date in 'Y-m-d' format.
 */
function calculate_xero_duedate( $invoiceDate, $day, $type ) {
	$invoiceDate = new DateTime( $invoiceDate );

	if ( $type == 'DAYSAFTERBILLMONTH' ) {
		$nextMonth = ( clone $invoiceDate )->modify( 'first day of next month' );
		$dueDate   = $nextMonth->modify( '+' . ( $day - 1 ) . ' days' );
	} elseif ( $type == 'DAYSAFTERBILLDATE' ) {
		$dueDate = $invoiceDate->modify( '+' . $day . ' days' );
	} elseif ( $type == 'OFFOLLOWINGMONTH' ) {
		$nextMonth = ( clone $invoiceDate )->modify( 'first day of next month' );
		$dueDate   = $nextMonth->modify( 'first day of this month' )->modify( '+' . ( $day - 1 ) . ' days' );
	} else {
		error_log( "Unsupported payment term type: " . $type );
	}

	return $dueDate->format( 'Y-m-d' );
}

/**
 * Retrieves the Xero set date before invoice is issued.
 *
 * The method fetches organization details from Xero and extracts payment terms, specifically
 * the day and type for sales payment terms, before the invoice is generated.
 *
 * @return void
 */
function xeroom_get_xero_set_date_before_invoice( $invoiceDate, $order ) {
	if ( ! function_exists( 'GuzzleHttp\json_encode' ) ) {
		require XEROOM_ROOT_PATH . 'vendor/guzzlehttp/guzzle/src/functions.php';
	}

	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance  = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);
	$xeroTenantId = $oauth2['tenant_id'];

	try {
		$where = 'EmailAddress="' . $order->get_billing_email() . '"';
		$where .= ' AND Name="' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '"';
		$where .= ' AND FirstName="' . $order->get_billing_first_name() . '"';
		$where .= ' AND LastName="' . $order->get_billing_last_name() . '"';

		$getContact = $apiInstance->getContacts( $xeroTenantId, null, $where );

		// Check if a contact with the given email was found
		if ( empty( $getContact->getContacts() ) ) {
			error_log( "Contact not found for email: " . $order->get_billing_email() );
		} else {
			$contact   = $getContact->getContacts()[0]; // Access the first contact object
			$contactId = $contact->getContactId();

			$paymentTerms = $contact->getPaymentTerms();

			if ( ! empty( $paymentTerms ) && ! empty( $paymentTerms->getSales() ) ) {
				$salesTerms = $paymentTerms->getSales();

				if ( ! empty( $salesTerms->getDay() ) && ! empty( $salesTerms->getType() ) ) {
					$day     = $salesTerms->getDay();
					$type    = $salesTerms->getType();
					$dueDate = calculate_xero_duedate( $invoiceDate, $day, $type );

					return [
						'DueDate'             => $dueDate,
						'ExpectedPaymentDate' => $dueDate,
					];
				}
			}
		}

		// If no contact-specific terms found, fall back to organization settings
		$organisations = $apiInstance->getOrganisations( $xeroTenantId );
		$paymentTerms  = $organisations[0]->getPaymentTerms();

		if ( ! empty( $paymentTerms ) && ! empty( $paymentTerms->getSales() ) ) {
			$salesTerms = $paymentTerms->getSales();

			if ( ! empty( $salesTerms->getDay() ) && ! empty( $salesTerms->getType() ) ) {
				$day  = $salesTerms->getDay();
				$type = $salesTerms->getType();

				// Calculate the due date and expected payment date
				$dueDate = calculate_xero_duedate( $invoiceDate, $day, $type );

				return [
					'DueDate'             => $dueDate,
					'ExpectedPaymentDate' => $dueDate,
				];
			}
		}

		// If no sales terms found, log the issue
		error_log( 'No sales payment terms found for organization or contact.' );

		return [
			'DueDate'             => null,
			'ExpectedPaymentDate' => null,
		];

	} catch ( Exception $e ) {
		error_log( 'Exception: ' . $e->getMessage() . PHP_EOL );

		// Handle the exception appropriately
		return [
			'DueDate'             => null,
			'ExpectedPaymentDate' => null,
		];
	}
}

