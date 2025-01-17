<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use GuzzleHttp\Client;
use GuzzleHttp\json_encode;
use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! defined( 'XEROOM_PLUGIN_PATH' ) ) {
	define( 'XEROOM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';


if ( ! function_exists( 'GuzzleHttp\json_encode' ) ) {
	require XEROOM_ROOT_PATH . '/vendor/guzzlehttp/guzzle/src/functions.php';
}

require_once( 'xeroom_indexInit.php' );
require_once( 'xeroom_bulk_action.php' );
if ( 'lite' != XEROOM_TYPE ) {
	require_once( 'xeroom_cancel_order_by_paypal.php' );
	require_once( 'xeroom-tracking-category.php' );
	require_once( 'xeroom_sync.php' );
	require_once( 'xeroom_customer_no.php' );
	$xeroom_bulk = new Xeroom_Batch_Request();
}

add_action( 'admin_menu', 'xeroom_menu_icon_under_admin' );
add_action( 'admin_head', 'xeroom_display_message_if_licence_not_valid', 40 );

/**
 * Send data to Xero based on Xeroom settings
 */
$send_invoice_method = get_xero_option( 'xero_send_invoice_method' );
$send_payment_method = get_xero_option( 'xero_send_payment_method' );

if ( $send_invoice_method && 'on_creation' == $send_invoice_method ) {
	add_action( 'woocommerce_checkout_order_processed', 'xeroom_invoice_sending', 99, 1 );
} else {
	add_action( 'woocommerce_order_status_' . $send_invoice_method, 'xeroom_invoice_sending', 99, 1 );
}

/**
 * Send Invoice by cron job
 *
 * @param $order_id
 */
function xeroom_invoice_sending( $order_id ) {
	$xeroom_send_by_cron = get_xero_option( 'xeroom_send_by_cron' );

	if ( $xeroom_send_by_cron ) {
		$order = new WC_Order( $order_id );
		$order->update_meta_data( 'xeroom_api_try_no', 1 );
		$order->save();

		$send_it = new Xeroom_Batch_Request();
		$send_it->data(
			array(
				'invoice' => $order_id,
			)
		)->save()->dispatch()->xeroom_generate_error();
	} else {
		xeroom_sendWooInvoiceToXero( $order_id );
	}
}

add_action( 'woocommerce_order_status_completed', 'xeroom_paymentDoneOnCheckoutManually', 99 );
add_action( 'woocommerce_order_edit_status', 'xeroom_send_payment_on_manually_complete', 10, 2 );
add_action( 'woocommerce_payment_complete', 'xeroom_paymentDoneOnPayment', 10, 1 );

$xero_email_invoice = get_xero_option( 'xero_email_invoice' );
if ( $xero_email_invoice && 'xeroom_paid' === $xero_email_invoice ) {
	add_action( 'woocommerce_payment_complete', function ( $order_id ) {
		$send_it = new Xeroom_Batch_Request();
		$send_it->xero_send_invoice_to_client( absint( $order_id ) );
	}, 100 );
}

add_action( 'woocommerce_thankyou', 'xeroom_update_reference_no', 100, 1 );
/**
 * Updates reference number for an order in Xero.
 *
 * @param int $order_id The ID of the order to update.
 *
 * @return void
 */
function xeroom_update_reference_no( $order_id ) {
	$order = new WC_Order( $order_id );

	if ( $order->get_meta( 'xeroom_payment_sent' ) ) {
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

		if ( xero_invoice_exists( $order_id, $xeroApiKey, $xeroApiSecret ) ) {
			$oder_gateway_code           = $order->get_payment_method();
			$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
			if ( $woo_gateway_payment_sending && ! array_key_exists( 'xero_' . $oder_gateway_code . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
//				return;
			}

			$invoice_reference = get_xero_option( 'xero_invoice_reference_prefix' );

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

			$invoice_no   = xeroom_invoice_number( $order_id );
			$reference_no = xeroom_generate_invoice_reference( $order_id );

			$invoice_info = array(
				array(
					"InvoiceID"     => $order->get_meta( 'post_content_filtered' ),
					"InvoiceNumber" => $invoice_no,
					"Reference"     => $reference_no,
				),
			);

			$xero_response = $xero->Invoices( $invoice_info );

			if ( ! empty( $xero_response ) ) {
				if ( isset( $xero_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
					$errD = $xero_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
					returnErrorMessageByXero( $order_id, $errD, $xeroTime, $errorType );
				} else if ( isset( $xero_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
					$errD = $xero_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
					for ( $e = 0; $e < count( $errD ); $e ++ ) {
						$errorMessage = $xero_response['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
						returnErrorMessageByXero( $order_id, $errorMessage, $xeroTime, $errorType );
					}
				} else if ( isset( $xero_response['Status'] ) && $xero_response['Status'] != "OK" ) {
					returnErrorMessageByXero( $order_id, $xero_response, $xeroTime, $errorType );
				}
			} else {
				$mMessage = "Xero Server Response is empty.";
				returnErrorMessageByXero( $order_id, $mMessage, $xeroTime, $errorType );
			}
		}
	}
}

add_action( 'rest_api_init', 'xeroom_register_endpoint' );
/**
 * API End-point
 */
function xeroom_register_endpoint() {
	register_rest_route(
		'xeroom/v2',
		'oauth_callback',
		array(
			'methods'             => 'GET',
			'callback'            => 'xeroom_authorize',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'xeroom/v2',
		'invoice_callback',
		array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => 'xeroom_send_response',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Add/Update the Xero Webhook data
 *
 * @param $data
 */
function xeroom_update_invoice_data( $data ) {
	$data_saved = get_option( 'xeroom_update_order_status' );

	if ( ! $data_saved ) {
		$data_to_saved = array();
		foreach ( $data->events as $event ) {
			$data_to_saved[ sanitize_key( $event->resourceId ) ] = json_decode( json_encode( $event ), true );
		}

		update_option( 'xeroom_update_order_status', $data_to_saved );
	} else {
		foreach ( $data->events as $event ) {
			$data_saved[ sanitize_key( $event->resourceId ) ] = json_decode( json_encode( $event ), true );
		}
		update_option( 'xeroom_update_order_status', $data_saved );
	}
}

/**
 * Remove the entry
 *
 * @param $resource_id
 */
function xeroom_remove_invoice_entry( $resource_id ) {
	$data_saved = get_option( 'xeroom_update_order_status' );

	if ( $data_saved ) {
		unset( $data_saved[ sanitize_key( $resource_id ) ] );
		if ( ! empty( $data_saved ) ) {
			update_option( 'xeroom_update_order_status', $data_saved );
		} else {
			delete_option( 'xeroom_update_order_status' );
		}
	}
}

/**
 * @param WP_REST_Request $req
 */
function xeroom_send_response( WP_REST_Request $req ) {
	$raw_payload = file_get_contents( "php://input" );
	$webhook_key = get_xero_option( 'sync_invoice_data' );

	$computed_signature_key = base64_encode(
		hash_hmac( 'sha256', $raw_payload, $webhook_key, true )
	);

	$xero_signature_key = isset( $_SERVER['HTTP_X_XERO_SIGNATURE'] ) ? $_SERVER['HTTP_X_XERO_SIGNATURE'] : '';

	if ( ! empty( $xero_signature_key ) && hash_equals( $computed_signature_key, $xero_signature_key ) ) {
		http_response_code( 200 );
		$data = json_decode( $raw_payload );

		if ( $data ) {
			if ( ! wp_next_scheduled( 'update_order_status' ) ) {
				wp_schedule_event(
					time() + 60,
					'per_minute',
					'update_order_status',
				);
			}

			xeroom_update_invoice_data( $data );
		}
		exit();
	} else {
		http_response_code( 401 );
		exit();
	}
}

add_action( 'update_order_status', 'xeroom_invoice_status' );
/**
 * Update Woo order status when the Xero invoice it's paid
 *
 * @param $data
 */
function xeroom_invoice_status() {
	require_once( ABSPATH . "/wp-load.php" );

	$xero_enable_invoice_sync = get_xero_option( 'xero_enable_invoice_sync' );
	$data_saved               = get_option( 'xeroom_update_order_status' );

	if ( ! empty( $data_saved ) && $xero_enable_invoice_sync ) {
		global $wpdb;

		foreach ( $data_saved as $event ) {
			if ( is_object( $event ) ) {
				$eventType     = $event->eventType;
				$eventCategory = $event->eventCategory;
				$resourceId    = $event->resourceId;
			} else {
				$eventType     = $event['eventType'];
				$eventCategory = $event['eventCategory'];
				$resourceId    = $event['resourceId'];
			}
			if ( 'UPDATE' === $eventType && 'INVOICE' === $eventCategory ) {
				require_once( XEROOM_PLUGIN_PATH . '/vendor/autoload.php' );

				$oauth2 = get_xero_option( 'xero_oauth_options' );
				xeroom_check_xero_token( $oauth2 );

				$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

				$apiInstance  = new XeroAPI\XeroPHP\Api\AccountingApi(
					new GuzzleHttp\Client(),
					$config
				);
				$xeroTenantId = $oauth2['tenant_id'];

				$user_invoice = $apiInstance->getInvoices( $xeroTenantId, null, null, null, $resourceId );

				if ( count( $user_invoice->getInvoices() ) > 0 ) {
					$xero_complete_when_paid                 = get_xero_option( 'xero_complete_when_paid' );
					$xero_complete_when_paid_virtual_product = get_xero_option( 'xero_complete_when_paid_virtual_product' );
					foreach ( $user_invoice->getInvoices() as $invoice_data ) {
						if ( 'PAID' === $invoice_data['status'] ) {

							if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
								$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
								$result           = $wpdb->get_results( "SELECT * FROM {$order_meta_table} WHERE meta_key='post_content_filtered' AND meta_value='" . $invoice_data['invoice_id'] . "'" );
							} else {
								$result = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_content_filtered='" . $invoice_data['invoice_id'] . "'" );
							}

							if ( $result ) {
								foreach ( $result as $order_z ) {

									if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
										$order_id = $order_z->order_id;
									} else {
										$order_id = $order_z->ID;
									}

									if ( 0 === absint( $order_id ) ) {
										continue;
									}

									$order = new WC_Order( $order_id );

									if ( ! $order ) {
										continue;
									}

									if ( $order->get_meta( 'xeroom_payment_sent' ) ) {
										continue;
									}

									if ( ! $order->has_status( array( 'processing' ) ) ) {
										continue;
									}

									$payment_method     = $order->get_payment_method();
									$automatic_gateways = array( 'paypal', 'stripe' ); // Add your automatic gateways here

									// Check if the payment method is an automatic gateway
									if ( in_array( $payment_method, $automatic_gateways ) ) {
										continue;
									}

									$order_status = 'processing';
									if ( $xero_complete_when_paid_virtual_product && xeroom_order_has_virtual_products( $order ) ) {
										$order_status = 'completed';
									} else if ( $xero_complete_when_paid ) {
										$order_status = 'completed';
									}

									if ( $order && ( xeroom_sanitize_the_price( $invoice_data['amount_paid'] ) === xeroom_sanitize_the_price( $order->get_total() ) ) ) {
										$order = new WC_Order( $order_id );
										$order->update_meta_data( 'xeroom_payment_sent', 'Sent to Xero' );
										$order->update_meta_data( 'xero_payment_webhook', 1 );
										$order->save();
									}

									if ( ! $order->has_status( $order_status ) && ( $xero_complete_when_paid_virtual_product || $xero_complete_when_paid ) ) {
										$order_note = esc_html__( 'Invoice Paid in Xero' );
										$order->update_status( $order_status, $order_note, true );
									}
								}
							}
						}
					}
				}
			}
			xeroom_remove_invoice_entry( $resourceId );
		}
	} else {
		wp_unschedule_hook( 'update_order_status' );
	}
}

/**
 * Sanitize the price
 *
 * @param $price
 *
 * @return float
 */
function xeroom_sanitize_the_price( $price ) {
	return floatval( preg_replace( '/[^0-9.]/', '', $price ) );
}

/**
 * Check if order has virtual products
 *
 * @param $order
 *
 * @return false
 */
function xeroom_order_has_virtual_products( $order ) {
	$is_virtual = false;
	if ( ( count( $order->get_items() ) > 0 ) ) {
		foreach ( $order->get_items() as $item ) {
			if ( $item->is_type( 'line_item' ) ) {
				$product = $item->get_product();

				if ( ! $product ) {
					continue;
				}

				if ( $product->is_downloadable() || $product->is_virtual() ) {
					$is_virtual = true;
					break;
				}
			}
		}
	}

	return $is_virtual;
}


function xeroom_authorize() {
	$xero_oauth_client_id     = get_xero_option( 'xero_oauth_client_id' );
	$xero_oauth_client_secret = get_xero_option( 'xero_oauth_client_secret' );

	$provider = new \League\OAuth2\Client\Provider\GenericProvider(
		[
			'clientId'                => $xero_oauth_client_id,
			'clientSecret'            => $xero_oauth_client_secret,
			'redirectUri'             => get_rest_url( null, 'xeroom/v2/oauth_callback' ),
			'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
			'urlAccessToken'          => 'https://identity.xero.com/connect/token',
			'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation',
		]
	);

	// If we don't have an authorization code then get one
	if ( ! isset( $_GET['code'] ) ) {
		update_xero_option( 'xero_connection_status', 'failed' );
		update_xero_option( 'xero_api_connection_status', 'failed' );

		echo 'There was an error on authorization process! Redirect in 3 seconds!';
		sleep( 3 );
		exit( header( "Location: " . admin_url( 'admin.php?page=add_xero_api_fields' ) ) );
	} else {
		try {
			// Try to get an access token using the authorization code grant.
			$accessToken = $provider->getAccessToken(
				'authorization_code',
				[
					'code' => $_GET['code'],
				]
			);

			update_xero_option( 'xero_access_token_get', (array) $_GET );
			update_xero_option( 'xero_access_token_data', (array) $accessToken );

			$config      = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( (string) $accessToken->getToken() );
			$identityApi = new XeroAPI\XeroPHP\Api\IdentityApi(
				new GuzzleHttp\Client(),
				$config
			);

			$result = $identityApi->getConnections();

			$xero_oauth_options = [
				'token'         => $accessToken->getToken(),
				'expires'       => $accessToken->getExpires(),
				'tenant_id'     => $result[0]->getTenantId(),
				'refresh_token' => $accessToken->getRefreshToken(),
			];
			update_xero_option( 'xero_oauth_options', $xero_oauth_options );

			if ( ! class_exists( 'Xero' ) ) {
				include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
			}
			// Run the accounts import.
			$xero_api = new Xero(
				$xero_oauth_client_id,
				$xero_oauth_client_secret,
				XEROOM_ROOT_PATH . '/library/certs/publickey.cer',
				XEROOM_ROOT_PATH . '/library/certs/privatekey.pem',
				'json',
				$xero_oauth_options
			);

			$bank_codes      = array();
			$getAllBankCodes = $xero_api->Accounts();

			if ( is_array( $getAllBankCodes ) && array_key_exists( 'Accounts', $getAllBankCodes ) ) {
				foreach ( $getAllBankCodes['Accounts']['Account'] as $account_entry ) {
					array_push( $bank_codes, $account_entry['Code'] );
				}
				update_xero_option( 'xero_default_accounts', $getAllBankCodes['Accounts']['Account'] );
			}

			$getAllTax = $xero_api->TaxRates();

			if ( is_array( $getAllTax ) && $getAllTax && array_key_exists( 'TaxRates', $getAllTax ) ) {
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
			}

			if ( $accessToken->getToken() && $result[0]->getTenantId() ) {
				xeroom_get_organisation( $accessToken->getToken(), $result[0]->getTenantId() );
			}

			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );

			header( 'Location: ' . admin_url( 'admin.php?page=add_xero_api_fields' ) );
			exit();

		} catch ( \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e ) {
			echo 'Your Xero connection credentials have failed please check and try again.';

			update_xero_option( 'xero_connection_status', 'failed' );
			update_xero_option( 'xero_connection_status_message', 'Cannot connect to Xero - Please check your settings or Xero service status.' );

			exit();
		}
	}
}

/**
 * Get the Organisation Name
 *
 * @param $token
 * @param $tenant_id
 *
 * @throws \XeroAPI\XeroPHP\ApiException
 */
function xeroom_get_organisation( $token, $tenant_id ) {
	if ( ! $token && ! $tenant_id ) {
		return;
	}

	$connection_status = get_xero_option( 'xero_connection_status' );

	if ( 'active' !== $connection_status ) {
		return;
	}

	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $token );
	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	try {
		$result_o = $apiInstance->getOrganisations( $tenant_id );

		if ( $result_o->getOrganisations()[0]->getName() ) {
			update_xero_option( 'xero_organisation_name', esc_attr( $result_o->getOrganisations()[0]->getName() ) );
		}
	} catch ( Exception $e ) {

	}
}

add_filter( 'cron_schedules', 'xeroom_custom_cron_schedule' );
/**
 * Custom cron job timer
 *
 * @param $schedules
 *
 * @return mixed
 */
function xeroom_custom_cron_schedule( $schedules ) {
	$schedules['fifteen_minutes'] = array(
		'interval' => 15 * 60,
		'display'  => esc_html__( 'Every Fifteen Minutes' ),
	);

	$schedules['five_minutes'] = array(
		'interval' => 5 * 60,
		'display'  => esc_html__( 'Every Five Minutes' ),
	);

	$schedules['per_minute'] = array(
		'interval' => 60,
		'display'  => esc_html__( 'Every One Minute' ),
	);

	return $schedules;
}

add_action( 'init', 'xeroom_register_custom_cron_schedule' );
/**
 *
 */
function xeroom_register_custom_cron_schedule() {
	/**
	 * Schedule license check
	 */
	if ( ! wp_next_scheduled( 'xeroom_refresh_the_token' ) ) {
		wp_schedule_event( time(), 'fifteen_minutes', 'xeroom_refresh_the_token' );
	}

	/**
	 * Schedule license check
	 */
	if ( ! wp_next_scheduled( 'xeroom_check_license' ) ) {
		wp_schedule_event( time(), 'daily', 'xeroom_check_license' );
	}
}

add_action( 'xeroom_refresh_the_token', 'xeroom_refresh_token' );
/**
 * Do token refresh
 *
 * @return mixed
 * @throws Exception
 */
function xeroom_refresh_token() {
	$xero_oauth_client_id     = get_xero_option( 'xero_oauth_client_id' );
	$xero_oauth_client_secret = get_xero_option( 'xero_oauth_client_secret' );
	$xero_oauth_options       = get_xero_option( 'xero_oauth_options' );

	if ( ! $xero_oauth_options ) {
		update_xero_option( 'xero_connection_status', 'failed' );
		update_xero_option( 'xero_connection_status_message', 'Cannot connect to Xero - Please check your settings or Xero service status.' );

		throw new Exception( 'There was a problem connecting to the API.' );
	}

	$token_refresh = wp_remote_request(
		'https://identity.xero.com/connect/token',
		array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'  => 'Basic ' . base64_encode( $xero_oauth_client_id . ':' . $xero_oauth_client_secret ),
				'Xero-tenant-id' => $xero_oauth_options['tenant_id'],
				'Content-Type'   => 'application/x-www-form-urlencoded',
				'User-Agent'     => 'Xero',
			),
			'timeout' => 70,
			'body'    => 'grant_type=refresh_token&refresh_token=' . $xero_oauth_options['refresh_token'],
		)
	);

	if ( is_wp_error( $token_refresh ) ) {
		update_xero_option( 'xero_connection_status', 'failed' );
		update_xero_option( 'xero_connection_status_message', 'Cannot connect to Xero - Please check your settings or Xero service status.' );

		throw new Exception( 'There was a problem connecting to the API.' );
	}

	if ( isset( $token_refresh['body'] ) && 0 === strpos( $token_refresh['body'], 'oauth_problem=' ) ) {

		// Parse error string.
		parse_str( $token_refresh['body'], $oauth_error );

		// Find OAuth advise.
		$oauth_advise = ( ( isset( $oauth_error['oauth_problem_advice'] ) ) ? $oauth_error['oauth_problem_advice'] : '' );

		update_xero_option( 'xero_connection_status', 'failed' );
		update_xero_option( 'xero_connection_status_message', sprintf( 'Request failed due OAuth error: %s | %s', $oauth_error['oauth_problem'], $oauth_advise ) );

		// Throw new exception.
		throw new Exception( sprintf( 'Request failed due OAuth error: %s | %s', $oauth_error['oauth_problem'], $oauth_advise ) );
	}

	$xero_response = json_decode( wp_remote_retrieve_body( $token_refresh ), true );

	if ( isset( $xero_response['access_token'] ) ) {
		$xero_oauth_options_update = [
			'token'         => $xero_response['access_token'],
			'expires'       => time() + $xero_response['expires_in'],
			'tenant_id'     => $xero_oauth_options['tenant_id'],
			'refresh_token' => $xero_response['refresh_token'],
		];

		update_xero_option( 'xero_oauth_options', $xero_oauth_options_update );

		update_xero_option( 'xero_connection_status', 'active' );
		update_xero_option( 'xero_connection_status_message', '' );

		return $xero_response['access_token'];
	} else {
		update_xero_option( 'xero_connection_status', 'failed' );
		update_xero_option( 'xero_connection_status_message', sprintf( 'Request failed due OAuth error: %s', $xero_response['error'] ) );

		// Throw new exception.
		return '';
	}

	return '';
}

add_action( 'xeroom_check_license', 'xeroom_check_license_method' );
/**
 *
 */
function xeroom_check_license_method() {
	global $wpdb;

	$xeroLicActive     = $wpdb->prefix . 'xeroom_license_key_status';
	$sql               = 'SELECT * FROM ' . $xeroLicActive . ' WHERE id=1';
	$xeroLicensekeyAct = $wpdb->get_results( $sql );

	$xero_plugin_license = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );

	if ( ! empty( $xero_plugin_license ) ) {
		$url          = 'https://www.xeroom.com/apidata.php?license_key=' . $xero_plugin_license;
		$result       = wp_remote_fopen( esc_url( $url ) );
		$validLicense = json_decode( $result, true );

		if ( $validLicense && count( $validLicense ) > 0 ) {
			$license_expire_date = esc_attr( $validLicense[0]['date_expiry'] );
			if ( $license_expire_date ) {
				update_xero_option( 'xero_license_expire_date', $license_expire_date );
			} else {
				$license_expire_date = get_xero_option( 'xero_license_expire_date' );
			}

			if ( $validLicense[0]['lic_status'] == 'active' ) {
				update_xero_option( 'xero_connection_status', 'active' );
				update_xero_option( 'xero_connection_status_message', '' );
				update_xero_option( 'xero_license_status', 1 );
				// Delete the first check date.
				delete_xero_option( 'xero_license_check_date' );

				$wpdb->update(
					$xeroLicActive,
					array(
						'status'      => 'active',
						'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
					),
					array( 'id' => 1 )
				);
			} elseif ( $validLicense[0]['lic_status'] == 'expired' ) {
				if ( $license_expire_date ) {
					$after_7_days = strtotime( '+7 day', strtotime( $license_expire_date ) );
					$today        = date( 'Y-m-d' );
					$datetime1    = new DateTime( $today );
					$datetime2    = new DateTime( date( 'Y-m-d', $after_7_days ) );
					$difference   = $datetime2->diff( $datetime1 )->format( '%a' );

					if ( $after_7_days >= strtotime( date( 'Y-m-d' ) ) ) {
						$licenseErrMessage = "Your licence cannot validate.  It has either expired or cannot reach our server.  Xeroom will stop working in $difference days.  Please renew or contact us if this message remains after 2 days.";
						update_xero_option( 'xero_connection_status', 'failed' );
						update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
						update_xero_option( 'xero_license_status', 1 );

						$wpdb->update(
							$xeroLicActive,
							array(
								'status'      => 'active',
								'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
							),
							array( 'id' => 1 )
						);
					} else {
						$licenseErrMessage = 'Your key is not active or is expired. Please contact Xeroom support for assistance.';
						update_xero_option( 'xero_connection_status', 'expired' );
						update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
						update_xero_option( 'xero_license_status', 0 );

						$wpdb->update(
							$xeroLicActive,
							array(
								'status'      => 'expired',
								'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
							),
							array( 'id' => 1 )
						);
					}
				} else {
					$first_try = date( 'Y-m-d' );
					update_xero_option( 'xero_license_check_date', $first_try );

					$licenseErrMessage = 'Your licence cannot validate.  It has either expired or cannot reach our server.  Xeroom will stop working in 7 days.  Please renew or contact us if this message remains after 2 days.';
					update_xero_option( 'xero_connection_status', 'failed' );
					update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
					update_xero_option( 'xero_license_status', 0 );

					$wpdb->update(
						$xeroLicActive,
						array(
							'license_key' => $xero_plugin_license,
							'status'      => 'active',
							'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
						),
						array( 'id' => 1 )
					);
				}
			} else {
				$licenseErrMessage = 'Your key is not active or is expired. Please contact Xeroom support for assistance.';
				update_xero_option( 'xero_connection_status', 'expired' );
				update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
				update_xero_option( 'xero_license_status', 0 );

				$wpdb->update(
					$xeroLicActive,
					array(
						'status'      => 'expired',
						'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
					),
					array( 'id' => 1 )
				);
			}

			if ( ! isset( $validLicense[0]['type'] ) && isset( $validLicense[0]['product_sku'] ) && 'XERPREM' !== $validLicense[0]['product_sku'] && 'PREM' !== $validLicense[0]['product_sku'] && '17' !== $validLicense[0]['product_id'] ) {

				$wpdb->update(
					$xeroLicActive,
					array(
						'xero_method' => 'lite'
					),
					array( 'id' => 1 )
				);
			}
		} else {
			$first_try = get_xero_option( 'xero_license_check_date' );
			if ( ! $first_try ) {
				$first_try = date( 'Y-m-d' );
				update_xero_option( 'xero_license_check_date', $first_try );
			}

			$after_7_days = strtotime( '+7 day', strtotime( $first_try ) );
			$today        = date( 'Y-m-d' );
			$datetime1    = new DateTime( $today );
			$datetime2    = new DateTime( date( 'Y-m-d', $after_7_days ) );
			$difference   = $datetime2->diff( $datetime1 )->format( '%a' );

			if ( $after_7_days >= strtotime( date( 'Y-m-d' ) ) ) {
				$licenseErrMessage = "";
				update_xero_option( 'xero_connection_status', 'failed' );
				update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
				update_xero_option( 'xero_license_status', 1 );

				$wpdb->update(
					$xeroLicActive,
					array(
						'status'      => 'active',
						'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
					),
					array( 'id' => 1 )
				);
			} else {
				$licenseErrMessage = 'Your key is not active or is expired. Please contact Xeroom support for assistance.';
				update_xero_option( 'xero_connection_status', 'expired' );
				update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
				update_xero_option( 'xero_license_status', 0 );

				$wpdb->update(
					$xeroLicActive,
					array(
						'status'      => 'expired',
						'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
					),
					array( 'id' => 1 )
				);
			}
		}
	}

	/**
	 * Check connection status
	 */
	$xeroCredentialTable    = $wpdb->prefix . 'xeroom_credentials';
	$sql                    = 'SELECT xero_api_key, xero_api_secret FROM ' . $xeroCredentialTable . ' WHERE id=1';
	$xeroCredentialsFromTbl = $wpdb->get_results( $sql );
	if ( $xeroCredentialsFromTbl ) {
		$xeroApiKey    = $xeroCredentialsFromTbl[0]->xero_api_key;
		$xeroApiSecret = $xeroCredentialsFromTbl[0]->xero_api_secret;

		if ( ! defined( 'BASE_PATH' ) ) {
			define( 'BASE_PATH', dirname( __FILE__ ) );
		}

		if ( ! defined( 'PUBLIC_KEY' ) ) {
			define( 'PUBLIC_KEY', BASE_PATH . '/library/certs/publickey.cer' );
		}

		if ( ! defined( 'PRIVATE_KEY' ) ) {
			define( 'PRIVATE_KEY', BASE_PATH . '/library/certs/privatekey.pem' );
		}

		$oauth2 = get_xero_option( 'xero_oauth_options' );
		xeroom_check_xero_token( $oauth2 );

		if ( ! class_exists( 'Xero' ) ) {
			include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
		}

		$xero = new Xero( $xeroApiKey, $xeroApiSecret, PUBLIC_KEY, PRIVATE_KEY, 'json', $oauth2 );

		$xero_tracking = $xero->TrackingCategories();
		xeroom_check_connection_message( $xero_tracking );
	}
}

add_action( 'wp_ajax_get_debug_info', 'xeroom_ajax_get_debug_info' );
function xeroom_ajax_get_debug_info() {
	global $wpdb;

	$sql         = "SELECT order_id,debug,created_date FROM `" . $wpdb->prefix . "xeroom_debug` ORDER BY id DESC LIMIT 0,60";
	$getAlldebug = $wpdb->get_results( $sql );

	$return_json = array();
	if ( count( $getAlldebug ) > 0 ) {
		for ( $i = 0; $i < count( $getAlldebug ); $i ++ ) {
			$order_id      = intval( $getAlldebug[ $i ]->order_id );
			$debug_message = $getAlldebug[ $i ]->debug;
			$created_date  = $getAlldebug[ $i ]->created_date;

			$order_no = '';
			$xero_no  = '';
			if ( $order_id > 0 ) {
				$order = new WC_Order( $order_id );
				if ( $order ) {
					$xeroom_invoice_no_sent = '';
					if ( $order->get_meta( 'xeroom_invoice_no_sent' ) ) {
						$xeroom_invoice_no_sent = $order->get_meta( 'xeroom_invoice_no_sent' );
					}

					$buyer = '';

					if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
						/* translators: 1: first name 2: last name */
						$buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
					} elseif ( $order->get_billing_company() ) {
						$buyer = trim( $order->get_billing_company() );
					} elseif ( $order->get_customer_id() ) {
						$user  = get_user_by( 'id', $order->get_customer_id() );
						$buyer = ucwords( $user->display_name );
					}

					/**
					 * Filter buyer name in list table orders.
					 *
					 * @param string $buyer Buyer name.
					 * @param WC_Order $order Order data.
					 *
					 * @since 3.7.0
					 *
					 */
					$buyer = apply_filters( 'woocommerce_admin_order_buyer_name', $buyer, $order );

					$order_no = esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer );

					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$xero_no = $order->get_meta( 'post_content' );
					} else {
						$order_details = get_post( $order_id );
						$xero_no       = $order_details->post_content;
					}
				}
			}

			$row           = array(
				'id'       => $order_id,
				'order_no' => $order_no,
				'xero_no'  => $xero_no,
				'message'  => $debug_message,
				'date'     => $created_date,
			);
			$return_json[] = $row;
		}
	}

	//return the result to the ajax request and die
	echo json_encode( array( 'data' => $return_json ) );
	wp_die();
}

add_filter( 'xeroom_new_invoice_data', 'xeroom_add_xero_client_account', 10, 2 );
/**
 * Add Xero account client
 *
 * @param $xero_invoice
 * @param $order_id
 *
 * @return mixed
 */
function xeroom_add_xero_client_account( $xero_invoice, $order_id ) {
	if ( ! $order_id ) {
		return $xero_invoice;
	}
	$current_get_order = wc_get_order( $order_id );
	if ( ! $current_get_order ) {
		return $xero_invoice;
	}
	if ( $current_get_order->get_customer_id() ) {
		$user_id = $current_get_order->get_customer_id();
	}

	if ( ! $user_id ) {
		return $xero_invoice;
	}

	$xero_address_info = get_xero_option( 'xero_address_info' );
	$xero_contact_name = get_xero_option( 'xero_contact_name' );

	$customer_account_no       = get_xero_option( 'xeroom_customer_account_no' );
	$saved_customer_account_no = get_user_meta( $user_id, 'xero_customer_no', true );

	if ( 'xeroom_use_woo_address' == $xero_address_info ) {
		if ( $customer_account_no ) {
			$customer_number = 0;

			if ( get_user_meta( $user_id, $customer_account_no, true ) ) {
				$customer_number = get_user_meta( $user_id, $customer_account_no, true );
			}

			if ( $current_get_order->get_meta( $customer_account_no ) ) {
				$customer_number = $current_get_order->get_meta( $customer_account_no );
			}

			if ( $customer_number ) {
				$xero_invoice[0]['Contact']['ContactNumber'] = esc_attr( $customer_number );
				$xero_invoice[0]['Contact']['AccountNumber'] = esc_attr( $customer_number );
			}

			$customer_info = xeroom_get_client_from_xero( $customer_number, $xero_contact_name, $current_get_order );
			if ( $customer_info ) {
				$xero_invoice[0]['Contact']['FirstName'] = esc_attr( $customer_info['FirstName'] );
				$xero_invoice[0]['Contact']['LastName']  = esc_attr( $customer_info['LastName'] );

				$xero_invoice[0]['Contact']['Addresses']['Address'] = $customer_info['Address'];
			}

			if ( $saved_customer_account_no ) {
				$xero_invoice[0]['Contact']['ContactNumber'] = esc_attr( $saved_customer_account_no );
				$xero_invoice[0]['Contact']['AccountNumber'] = esc_attr( $saved_customer_account_no );
			}
		}
	} else {
		$customer_info = xeroom_get_client_from_xero( $saved_customer_account_no, $xero_contact_name, $current_get_order );

		if ( $customer_info ) {
			$xero_invoice[0]['Contact']['Name']         = esc_attr( $customer_info['Name'] );
			$xero_invoice[0]['Contact']['FirstName']    = esc_attr( $customer_info['FirstName'] );
			$xero_invoice[0]['Contact']['LastName']     = esc_attr( $customer_info['LastName'] );
			$xero_invoice[0]['Contact']['EmailAddress'] = esc_attr( $customer_info['EmailAddress'] );

			$xero_invoice[0]['Contact']['Addresses']['Address'] = $customer_info['Address'];
		}

		if ( $saved_customer_account_no && 'xeroom_use_acno' == $xero_contact_name ) {
			$xero_invoice[0]['Contact']['ContactNumber'] = esc_attr( $saved_customer_account_no );
			$xero_invoice[0]['Contact']['AccountNumber'] = esc_attr( $saved_customer_account_no );
		}
	}

	return $xero_invoice;
}

/**
 * @param $get_customer_id
 *
 * @return array
 */
function xeroom_get_client_from_xero( $get_customer_id, $xero_contact_name, $current_get_order ) {
	$oauth2 = get_xero_option( 'xero_oauth_options' );
	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$config->setHost( "https://api.xero.com/api.xro/2.0" );

	$apiInstance = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);

	$xero_tenant_id = $oauth2['tenant_id'];

	$shipAddress = $current_get_order->get_address();

	if ( 'xeroom_use_company' == $xero_contact_name ) {
		$where = 'Name="' . $shipAddress['company'] . '"';
	} elseif ( 'xeroom_use_email' == $xero_contact_name ) {
		$where = 'EmailAddress="' . $shipAddress['email'] . '"';
	} elseif ( 'xeroom_use_acno' == $xero_contact_name && $get_customer_id ) {
		$where = 'AccountNumber="' . $get_customer_id . '"';
	} else {
		$where = 'FirstName="' . $shipAddress['first_name'] . '&&LastName=' . $shipAddress['last_name'] . '"';
	}

	$address = array();
	try {
		$result = $apiInstance->getContacts( $xero_tenant_id, null, $where );

		if ( isset( $result ) && ! empty( $result ) ) {
			foreach ( $result as $xero_address ) {
				$address['Name']         = $xero_address->getName();
				$address['LastName']     = $xero_address->getLastName();
				$address['FirstName']    = $xero_address->getFirstName();
				$address['EmailAddress'] = $xero_address->getEmailAddress();
				$user_addresses          = $xero_address->getAddresses();

				foreach ( $user_addresses as $user_address ) {
					if (
						'POBOX' === $user_address['address_type']
					) {
						$address['Address'] = array(
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

	}

	return array_filter( $address );
}

/**
 * Add coupon feature
 */
add_action( 'woocommerce_coupon_options', 'xeroom_add_coupon_name_field', 10, 2 );
/**
 * Add coupon fields
 *
 * @param $coupon_id
 * @param $coupon
 */
function xeroom_add_coupon_name_field( $coupon_id, $coupon ) {
	woocommerce_wp_text_input(
		array(
			'id'          => 'xero_coupon_code',
			'label'       => __( 'Xero coupon Code', 'woocommerce' ),
			'placeholder' => esc_attr__( 'Add Xero coupon Code', 'woocommerce' ),
			'description' => __( 'Add coupon Xero Code to avoid multiple entries.', 'woocommerce' ),
			'desc_tip'    => true,
			'class'       => '',
			'value'       => get_post_meta( $coupon_id, 'xero_coupon_code', true ) ? esc_html( get_post_meta( $coupon_id, 'xero_coupon_code', true ) ) : '',
		)
	);
}

add_action( 'woocommerce_coupon_options_save', 'xeroom_save_coupon_data', 10, 1 );
/**
 * Save coupon data
 *
 * @param $coupon_id
 */
function xeroom_save_coupon_data( $coupon_id ) {
	$include_stats = isset( $_POST['xero_coupon_code'] ) ? esc_attr( $_POST['xero_coupon_code'] ) : '';
	$coupon        = new WC_Coupon( $coupon_id );
	$coupon->update_meta_data( 'xero_coupon_code', $include_stats );
	$coupon->save();
}

add_action( 'restrict_manage_posts', 'render_custom_orders_filters' );
/**
 * Add the Xero statuses drop-down
 */
function render_custom_orders_filters() {
	if ( ! isset( $_GET['post_type'] ) || 'shop_order' !== $_GET['post_type'] ) {
		return;
	}
	?>
    <select name="xero_status">
        <option value=""><?php echo esc_html__( 'All Xero Statuses', 'xeroom' ); ?></option>
        <option value="xero_not_sent"<?php if ( isset( $_GET['xero_status'] ) && 'xero_not_sent' == $_GET['xero_status'] ) {
			echo ' selected';
		} ?>><?php echo esc_html__( 'Not sent', 'xeroom' ); ?></option>
        <option value="xero_sent_unpaid"<?php if ( isset( $_GET['xero_status'] ) && 'xero_sent_unpaid' == $_GET['xero_status'] ) {
			echo ' selected';
		} ?>><?php echo esc_html__( 'Sent unpaid', 'xeroom' ); ?></option>
        <option value="xero_sent_and_paid"<?php if ( isset( $_GET['xero_status'] ) && 'xero_sent_and_paid' == $_GET['xero_status'] ) {
			echo ' selected';
		} ?>><?php echo esc_html__( 'Sent and paid', 'xeroom' ); ?></option>
        <option value="xero_paid_credit_note"<?php if ( isset( $_GET['xero_status'] ) && 'xero_paid_credit_note' == $_GET['xero_status'] ) {
			echo ' selected';
		} ?>><?php echo esc_html__( 'Paid & Credit Note', 'xeroom' ); ?></option>
        <option value="xero_paid_in_xero"<?php if ( isset( $_GET['xero_status'] ) && 'xero_paid_in_xero' == $_GET['xero_status'] ) {
			echo ' selected';
		} ?>><?php echo esc_html__( 'Paid in Xero', 'xeroom' ); ?></option>
    </select>
	<?php
}

add_action( 'pre_get_posts', 'filter_woocommerce_orders_in_the_table', 99, 1 );
/**
 * @param $query
 *
 * @return mixed|void
 */
function filter_woocommerce_orders_in_the_table( $query ) {
	if ( ! is_admin() ) {
		return;
	}

	global $pagenow;

	if ( 'edit.php' === $pagenow && 'shop_order' === $query->query['post_type'] ) {

		if ( ! isset( $_GET['xero_status'] ) || empty( $_GET['xero_status'] ) ) {
			return $query;
		}

		if ( 'xero_not_sent' == $_GET['xero_status'] ) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => 'xeroom_order_sent',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key'     => 'xeroom_cred_note_generated',
					'compare' => 'NOT EXISTS'
				)
			);
		}

		if ( 'xero_sent_unpaid' == $_GET['xero_status'] ) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => 'xeroom_order_sent',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => 'xeroom_payment_sent',
					'compare' => 'NOT EXISTS'
				)
			);
		}

		if ( 'xero_sent_and_paid' == $_GET['xero_status'] ) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => 'xeroom_payment_sent',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => 'xeroom_cred_note_generated',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key'     => 'xero_payment_webhook',
					'compare' => 'NOT EXISTS'
				)
			);
		}

		if ( 'xero_paid_credit_note' == $_GET['xero_status'] ) {
			$meta_query = array(
				array(
					'key'     => 'xeroom_cred_note_generated',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => 'xeroom_order_sent',
					'compare' => 'NOT EXISTS'
				)
			);
		}

		if ( 'xero_paid_in_xero' == $_GET['xero_status'] ) {
			$meta_query = array(
				array(
					'key'     => 'xero_payment_webhook',
					'compare' => 'EXISTS'
				)
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	return;
}

add_filter( 'woocommerce_payment_complete_order_status', 'xeroom_autocomplete_orders', - 1, 2 );
/**
 * @param $order_status
 * @param $order_id
 *
 * @return mixed|string
 */
function xeroom_autocomplete_orders( $order_status, $order_id ) {
	$xero_autocomplete_orders = get_xero_option( 'xero_autocomplete_orders' );

	if ( $xero_autocomplete_orders ) {
		switch ( $xero_autocomplete_orders ) {
			case '0':
				$order_status = 'processing';
				break;
			case 'all_orders':
				$order_status = 'completed';
				break;
			case 'virtual_products':
				$order = new WC_Order( $order_id );

				if ( $order && $order_status === 'processing' && in_array( $order->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) ) {

					$virtual = false;
					$items   = $order->get_items();

					if ( count( $items ) > 0 ) {
						foreach ( $items as $item ) {
							if ( is_callable( array( $item, 'get_product' ) ) ) {
								$product = $item->get_product();
							} else {
								$product = null;
							}

							if ( ! $product->is_virtual() ) {
								$virtual = false;
								break;
							}
							$virtual = true;
						}
					}
					if ( $virtual ) {
						$order_status = 'completed';
					}
				}
				break;
			case 'virtual_products_downloadable':
				$order_status = 'completed' === $order_status ? 'completed' : 'processing';
				break;
		}

	}

	return $order_status;
}

/**
 * Generates the invoice date based on the given day in the settings.
 *
 * @param int $dayInSettings The day in the settings.
 *
 * @return string The generated invoice date in the format "Y-m-d".
 */
function generate_invoice_date( $dayInSettings ) {
	// Get current date
	$currentDay   = date( 'j' );
	$currentMonth = date( 'n' );
	$currentYear  = date( 'Y' );

	// If the current day is greater than or equal to the setting day,
	// invoice date will be setting day of next month
	if ( $currentDay >= $dayInSettings ) {
		// If current month is December, invoice date will be setting day of January next year
		if ( $currentMonth == 12 ) {
			$invoiceDate = date( 'Y-m-d', mktime( 0, 0, 0, 1, $dayInSettings, $currentYear + 1 ) );
		} else {
			$invoiceDate = date( 'Y-m-d', mktime( 0, 0, 0, $currentMonth + 1, $dayInSettings, $currentYear ) );
		}
	} else {
		// If the current day is less than the setting day, invoice date will be setting day of this month
		$invoiceDate = date( 'Y-m-d', mktime( 0, 0, 0, $currentMonth, $dayInSettings, $currentYear ) );
	}

	return $invoiceDate;
}
