<?php
/**
 * Display Xeroom API Settings Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

$licenseMessage                = esc_html__( 'Awaiting activation – Please enter licence key', 'xeroom' );
$licenseErrMessage             = "";
$message                       = "";
$xeroCredentialTable           = $wpdb->prefix . "xeroom_credentials";
$xeroLicActive                 = $wpdb->prefix . "xeroom_license_key_status";
$shippingCosts                 = '';
$extraSalesAccount             = '';
$associated_product_categories = '';
$display_message               = '';
$gateway_message               = '';

/**
 * Read saved settings
 */
$sql                    = "SELECT xero_api_key, xero_api_secret FROM " . $xeroCredentialTable . " WHERE id=1";
$xeroCredentialsFromTbl = $wpdb->get_results( $sql );

$xeroApiKey    = $xeroCredentialsFromTbl[0]->xero_api_key;
$xeroApiSecret = $xeroCredentialsFromTbl[0]->xero_api_secret;

// Fetch and Save Xero Tax rates if Synch Inventory Master is set on Woocommerce
include_once( XEROOM_ROOT_PATH . "library/xeroom_indexManager.php" );
define( 'BASE_PATH', dirname( __FILE__ ) );
define( 'XERO_KEY', $xeroApiKey );
define( 'XERO_SECRET', $xeroApiSecret );
define( 'PUBLIC_KEY', XEROOM_ROOT_PATH . '/library/certs/publickey.cer' );
define( 'PRIVATE_KEY', XEROOM_ROOT_PATH . '/library/certs/privatekey.pem' );
define( 'FORMAT', 'json' );

if ( isset( $_POST['xero_oauth_client_id'] ) || isset( $_POST['xero_oauth_client_secret'] ) ) {
	$taxTableName = $wpdb->prefix . "xeroom_tax";
	$oauth2       = get_xero_option( 'xero_oauth_options' );
	xeroom_check_xero_token( $oauth2 );
	$xero_api = new Xero( XERO_KEY, XERO_SECRET, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

//	if ( isset( $_POST['xeroom_tax_method'] ) ) {
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
//	}

	if ( isset( $oauth2['token'] ) && isset( $oauth2['tenant_id'] ) ) {
		xeroom_get_organisation( $oauth2['token'], $oauth2['tenant_id'] );
	}

	$xero_tracking   = $xero_api->TrackingCategories();
	$active_tracking = xeroom_fetch_tracking_categories( $xero_tracking );
	update_xero_option( 'xero_tracking_categories', $active_tracking );

	$bank_codes = array();
	if ( isset( $_POST['xero_default_payment'] ) ) {
		$getAllBankCodes = $xero_api->Accounts();

		if ( is_array( $getAllBankCodes ) && array_key_exists( 'Accounts', $getAllBankCodes ) ) {
			foreach ( $getAllBankCodes['Accounts']['Account'] as $account_entry ) {
				array_push( $bank_codes, $account_entry['Code'] );
			}
			update_xero_option( 'xero_default_accounts', $getAllBankCodes['Accounts']['Account'] );
		}
	}

	if ( isset( $_POST['xero_default_payment'] ) ) {
		if ( isset( $_POST['xero_default_payment_auto_send'] ) ) {
			update_xero_option( 'xero_default_payment_auto_send', 1 );
		} else {
			update_xero_option( 'xero_default_payment_auto_send', 0 );
		}
	}

	if ( isset( $_POST['xero_show_shipping_details'] ) ) {
		if ( isset( $_POST['xero_show_shipping_details'] ) ) {
			update_xero_option( 'xero_show_shipping_details', 1 );
		} else {
			update_xero_option( 'xero_show_shipping_details', 0 );
		}
	}

	if ( isset( $_POST['xero_send_invoice_method'] ) ) {

		if ( isset( $_POST['xero_use_custom_meta_inv'] ) ) {
			update_xero_option( 'xero_use_custom_meta_inv', 1 );
		} else {
			update_xero_option( 'xero_use_custom_meta_inv', 0 );
		}

		if ( isset( $_POST['xero_include_payment_ref'] ) ) {
			update_xero_option( 'xero_include_payment_ref', 1 );
		} else {
			update_xero_option( 'xero_include_payment_ref', 0 );
		}

		if ( isset( $_POST['xero_include_customer_name'] ) ) {
			update_xero_option( 'xero_include_customer_name', 1 );
		} else {
			update_xero_option( 'xero_include_customer_name', 0 );
		}

	}

	if ( isset( $_POST['sync_invoice_key'] ) ) {
		if ( isset( $_POST['xero_enable_invoice_sync'] ) ) {
			update_xero_option( 'xero_enable_invoice_sync', 1 );
		} else {
			update_xero_option( 'xero_enable_invoice_sync', 0 );
		}

		if ( isset( $_POST['xero_complete_when_paid'] ) ) {
			update_xero_option( 'xero_complete_when_paid', 1 );
		} else {
			update_xero_option( 'xero_complete_when_paid', 0 );
		}

		if ( isset( $_POST['xero_complete_when_paid_virtual_product'] ) ) {
			update_xero_option( 'xero_complete_when_paid_virtual_product', 1 );
		} else {
			update_xero_option( 'xero_complete_when_paid_virtual_product', 0 );
		}

	}

	if ( isset( $_POST['synch_debug_mode'] ) ) {
		update_xero_option( 'synch_debug_mode', 1 );
	} else {
		update_xero_option( 'synch_debug_mode', 0 );
	}

	/**
	 * Save settings for general tab ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	 */
	if ( isset( $_POST['stockMaster'] ) ) {
		if ( isset( $_POST['xero_order_notes'] ) ) {
			update_xero_option( 'xero_order_notes', 1 );
		} else {
			update_xero_option( 'xero_order_notes', 0 );
		}

		if ( isset( $_POST['xero_wc_partial_deposit'] ) ) {
			update_xero_option( 'xero_wc_partial_deposit', 1 );
		} else {
			update_xero_option( 'xero_wc_partial_deposit', 0 );
		}
		if ( isset( $_POST['xero_autocomplete_orders'] ) ) {
			update_xero_option( 'xero_autocomplete_orders', esc_attr( $_POST['xero_autocomplete_orders'] ) );
		}

		if ( isset( $_POST['xeroom_exclude_zero_value'] ) ) {
			update_xero_option( 'xeroom_exclude_zero_value', 1 );
		} else {
			update_xero_option( 'xeroom_exclude_zero_value', 0 );
		}

		if ( isset( $_POST['xeroom_send_by_cron'] ) ) {
			update_xero_option( 'xeroom_send_by_cron', 1 );
		} else {
			update_xero_option( 'xeroom_send_by_cron', 0 );
		}
// Save Default Invoice Status
		if ( isset( $_POST['invoiceStatus'] ) && '' != $_POST['invoiceStatus'] ) {
			update_xero_option( 'xero_default_invoice_status', esc_attr( $_POST['invoiceStatus'] ) );
		}

		if ( isset( $_POST['shipping_costs'] ) && '' != $_POST['shipping_costs'] ) {
			update_xero_option( 'xero_default_shipping_costs_code', esc_attr( $_POST['shipping_costs'] ) );
		}

		if ( isset( $_POST['use_extra_sales_account'] ) && '' != $_POST['use_extra_sales_account'] ) {
			update_xero_option( 'xero_use_extra_sales_account', esc_attr( $_POST['use_extra_sales_account'] ) );
		}

		if ( isset( $_POST['invoice_prefix'] ) ) {
			update_xero_option( 'xero_invoice_prefix', esc_attr( $_POST['invoice_prefix'] ) );
		}

		if ( isset( $_POST['xeroom_invoice_no_active'] ) ) {
			update_xero_option( 'xeroom_invoice_no_active', esc_attr( $_POST['xeroom_invoice_no_active'] ) );
		}

		if ( isset( $_POST['shipping_price_code'] ) ) {
			update_xero_option( 'xero_shipping_price_code', esc_attr( $_POST['shipping_price_code'] ) );
		}

		if ( isset( $_POST['shipping_price_description'] ) ) {
			update_xero_option( 'xero_shipping_price_description', esc_attr( $_POST['shipping_price_description'] ) );
		}

        if ( isset( $_POST['xeroom_daily_invoice_limit'] ) ) {
            if(!empty($_POST['xeroom_daily_invoice_limit'])) {
                update_xero_option( 'xeroom_daily_invoice_limit', esc_attr( $_POST['xeroom_daily_invoice_limit'] ) );
            } else {
                update_xero_option( 'xeroom_daily_invoice_limit', 50);
            }
			
		}
        
        if ( isset( $_POST['order_number_size'] ) ) {
            if(!empty($_POST['order_number_size'])) {
                update_xero_option( 'xero_order_number_size', esc_attr( $_POST['order_number_size'] ) );
            } else {
                update_xero_option( 'xero_order_number_size', 4);
            }
        }
    
        $save_settings = array();
        
        if ( isset( $_POST['xero_oauth_client_id'] ) && '' != $_POST['xero_oauth_client_id'] ) {
            $save_settings['xero_api_key'] = sanitize_text_field( $_POST['xero_oauth_client_id'] );
        } else {
            $save_settings['xero_api_key'] = '';
        }
        if ( isset( $_POST['xero_oauth_client_secret'] ) && '' != $_POST['xero_oauth_client_secret'] ) {
            $save_settings['xero_api_secret'] = sanitize_text_field( $_POST['xero_oauth_client_secret'] );
        } else {
            $save_settings['xero_api_secret'] = '';
        }
        if ( isset( $_POST['sales_account'] ) && '' != $_POST['sales_account'] ) {
            $save_settings['sales_account'] = esc_attr( $_POST['sales_account'] );
        }
        if ( isset( $_POST['bank_code'] ) && '' != $_POST['bank_code'] ) {
            $save_settings['bank_code'] = esc_attr( $_POST['bank_code'] );
        }
        if ( isset( $_POST['tax_code'] ) && '' != $_POST['tax_code'] ) {
            $save_settings['tax_code'] = sanitize_text_field( $_POST['tax_code'] );
        }
        if ( isset( $_POST['asset_code'] ) && '' != $_POST['asset_code'] ) {
            $save_settings['asset_code'] = esc_attr( $_POST['asset_code'] );
        }
        if ( isset( $_POST['sold_code'] ) && '' != $_POST['sold_code'] ) {
            $save_settings['sold_code'] = esc_attr( $_POST['sold_code'] );
        }
        if ( isset( $_POST['stockMaster'] ) && '' != $_POST['stockMaster'] ) {
            $save_settings['stock_master'] = sanitize_text_field( $_POST['stockMaster'] );
        }
        if ( isset( $_POST['productMaster'] ) && '' != $_POST['productMaster'] ) {
            $save_settings['product_master'] = sanitize_text_field( $_POST['productMaster'] );
        }
        if ( isset( $_POST['paymentMaster'] ) && '' != $_POST['paymentMaster'] ) {
            $save_settings['payment_master'] = sanitize_text_field( $_POST['paymentMaster'] );
        }

        if ( ! empty( $save_settings ) ) {
            $wpdb->update( $xeroCredentialTable, $save_settings, array( 'id' => 1 ) );
        }

        // Save OAuth 2 data
        if ( isset( $_POST['xero_oauth_client_id'] ) && '' != $_POST['xero_oauth_client_id'] ) {
            update_xero_option( 'xero_oauth_client_id', esc_attr( $_POST['xero_oauth_client_id'] ) );
        }

        if ( isset( $_POST['xero_oauth_client_secret'] ) && '' != $_POST['xero_oauth_client_secret'] ) {
            update_xero_option( 'xero_oauth_client_secret', esc_attr( $_POST['xero_oauth_client_secret'] ) );
        }

	}
	/**
	 * Save settings for general tab ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
	 */

	if ( isset( $_POST['invoice_start_no'] ) ) {
		if ( '' != $_POST['invoice_start_no'] ) {
			update_xero_option( 'xero_invoice_start_no', absint( $_POST['invoice_start_no'] ) );
		} else {
			update_xero_option( 'xero_invoice_start_no', '' );
			delete_xero_option( 'xero_invoice_next_no' );
		}
	}

	if ( isset( $_POST['invoice_reference_prefix'] ) ) {
		update_xero_option( 'xero_invoice_reference_prefix', esc_attr( $_POST['invoice_reference_prefix'] ) );
	}

	if ( isset( $_POST['invoice_meta_no'] ) ) {
		update_xero_option( 'xeroom_inv_number_meta_key', esc_attr( $_POST['invoice_meta_no'] ) );
	}

	if ( isset( $_POST['invoice_meta_ref'] ) ) {
		update_xero_option( 'xeroom_inv_reference_meta_key', esc_attr( $_POST['invoice_meta_ref'] ) );
	}

	if ( isset( $_POST['customer_account_no'] ) ) {
		update_xero_option( 'xeroom_customer_account_no', esc_attr( $_POST['customer_account_no'] ) );
	}

	if ( isset( $_POST['rounding_account'] ) ) {
		update_xero_option( 'xeroom_rounding_account', esc_attr( $_POST['rounding_account'] ) );
	}

	if ( isset( $_POST['stripe_fee_account'] ) && '' != $_POST['generate_credit_note'] ) {
		update_xero_option( 'xeroom_stripe_fee_account', esc_attr( $_POST['stripe_fee_account'] ) );

		if ( isset( $_POST['xero_send_stripe_fee'] ) ) {
			update_xero_option( 'xero_send_stripe_fee', 1 );
		} else {
			update_xero_option( 'xero_send_stripe_fee', 0 );
		}
	}

	if ( isset( $_POST['generate_credit_note'] ) && '' != $_POST['generate_credit_note'] ) {
		update_xero_option( 'xero_generate_credit_note', esc_attr( $_POST['generate_credit_note'] ) );
	}

	if ( isset( $_POST['generate_payment_refund'] ) && '' != $_POST['generate_payment_refund'] ) {
		update_xero_option( 'xero_generate_payment_refund', esc_attr( $_POST['generate_payment_refund'] ) );
	}

	if ( isset( $_POST['credit_note_status'] ) && '' != $_POST['credit_note_status'] ) {
		update_xero_option( 'xero_credit_note_status', esc_attr( $_POST['credit_note_status'] ) );
	}

	if ( isset( $_POST['xeroom_tax_method'] ) && '' != $_POST['xeroom_tax_method'] ) {
		update_xero_option( 'xero_tax_method', esc_attr( $_POST['xeroom_tax_method'] ) );
	}

	if ( isset( $_POST['invoice_date'] ) && '' != $_POST['invoice_date'] ) {
		update_xero_option( 'xero_invoice_date', esc_attr( $_POST['invoice_date'] ) );
	}

	if ( isset( $_POST['invoice_delivery_address'] ) && '' != $_POST['invoice_delivery_address'] ) {
		update_xero_option( 'xero_invoice_delivery_address', esc_attr( $_POST['invoice_delivery_address'] ) );
	}

	if ( isset( $_POST['tracking_category'] ) ) {
		update_xero_option( 'xero_tracking_category', esc_attr( $_POST['tracking_category'] ) );
	}

	if ( isset( $_POST['shipping_tracking_category'] ) ) {
		update_xero_option( 'xero_shipping_tracking_category', esc_attr( $_POST['shipping_tracking_category'] ) );
	}

	if ( isset( $_POST['xero_send_invoice_method'] ) && '' != $_POST['xero_send_invoice_method'] ) {
		update_xero_option( 'xero_send_invoice_method', esc_attr( $_POST['xero_send_invoice_method'] ) );
	}

	if ( isset( $_POST['xero_set_invoice_duedate'] ) && '' != $_POST['xero_set_invoice_duedate'] ) {
		update_xero_option( 'xero_set_invoice_duedate', esc_attr( $_POST['xero_set_invoice_duedate'] ) );
	}

	if ( isset( $_POST['xero_due_date_custom_days'] ) && '' != $_POST['xero_due_date_custom_days'] ) {
		update_xero_option( 'xero_due_date_custom_days', esc_attr( $_POST['xero_due_date_custom_days'] ) );
	}

	if ( isset( $_POST['xero_due_date_month_day'] ) && '' != $_POST['xero_due_date_month_day'] ) {
		if ( $_POST['xero_due_date_month_day'] < 1 || $_POST['xero_due_date_month_day'] > 31 ) {
			echo "Invalid day! Please enter a day between 1 and 31.";
		} else {
			update_xero_option( 'xero_due_date_month_day', absint( $_POST['xero_due_date_month_day'] ) );
		}
	}

	if ( isset( $_POST['xero_send_payment_method'] ) && '' != $_POST['xero_send_payment_method'] ) {
		update_xero_option( 'xero_send_payment_method', esc_attr( $_POST['xero_send_payment_method'] ) );
	}

	if ( isset( $_POST['xero_contact_name'] ) && '' != $_POST['xero_contact_name'] ) {
		update_xero_option( 'xero_contact_name', esc_attr( $_POST['xero_contact_name'] ) );
	}

	if ( isset( $_POST['xero_email_invoice'] ) && '' != $_POST['xero_email_invoice'] ) {
		update_xero_option( 'xero_email_invoice', esc_attr( $_POST['xero_email_invoice'] ) );
	}

	if ( isset( $_POST['xero_address_info'] ) && '' != $_POST['xero_address_info'] ) {
		update_xero_option( 'xero_address_info', esc_attr( $_POST['xero_address_info'] ) );
	}

	if ( isset( $_POST['sync_master'] ) && isset( $_POST['sync_schedule'] ) && isset( $_POST['batch_sync_size'] ) ) {
		$sync_data = array(
			'sync_master'     => esc_attr( $_POST['sync_master'] ),
			'sync_schedule'   => esc_attr( $_POST['sync_schedule'] ),
			'batch_sync_size' => absint( $_POST['batch_sync_size'] ),
		);
		update_xero_option( 'sync_stock_data', $sync_data );
	}

	if ( isset( $_POST['sync_prod_master'] ) && isset( $_POST['sync_prod_schedule'] ) ) {
		$sync_prod_data = array(
			'sync_prod_master'        => esc_attr( $_POST['sync_prod_master'] ),
			'what_to_update'          => esc_attr( $_POST['what_to_update'] ),
			'sync_prod_schedule'      => esc_attr( $_POST['sync_prod_schedule'] ),
			'batch_product_sync_size' => isset( $_POST['batch_product_sync_size'] ) ? esc_attr( $_POST['batch_product_sync_size'] ) : 100,
		);

		update_xero_option( 'sync_product_data', $sync_prod_data );
	}

	if ( isset( $_POST['sync_invoice_key'] ) ) {
		update_xero_option( 'sync_invoice_data', esc_attr( $_POST['sync_invoice_key'] ) );
	}

    if ( isset( $_REQUEST['tab'] ) && 'ebay_and_amazon' === $_REQUEST['tab'] ) {
		if ( isset( $_POST['ebay_and_amazon_settings'] ) ) {
			update_xero_option( 'ebay_and_amazon_settings', 1 );
		} else {
			update_xero_option( 'ebay_and_amazon_settings', 0 );
		}

		if ( isset( $_POST['xeroom_emails_lists'] ) ) {
			update_xero_option( 'xeroom_emails_lists', esc_attr( $_POST['xeroom_emails_lists'] ) );
		}
	}

	$save_categories = $save_shipping = $save_sending_gateway = $save_gateway = $save_tax_method = $xero_taxes = array();
	foreach ( $_POST as $key => $value ) {
		if ( 0 === strpos( $key, 'xero_name_tax_', 0 ) ) {
			$tax_id                = explode( '_', $key );
			$tax_id                = end( $tax_id );
			$xero_taxes[ $tax_id ] = esc_attr( $value );
		}
		// Associated WOO Gateway
		if ( xeroom_ends_with( $key, '_payment' ) ) {
			if ( strpos( $key, 'default' ) === false && '' == $_POST['xero_default_payment'] && ! empty( $value ) ) {
				$gateway_message = __( 'Please associate Default Payment Gateways Account', 'xeroom' );
			} else {
				$save_gateway[ $key ] = esc_attr( $value );

				if ( ! empty( $bank_codes ) && ! in_array( $value, $bank_codes ) && ! empty( $value ) ) {
					$gateway_message = sprintf( 'Account code %s could not be found. Please Create your bank account on xero and update bank code in xeroom setting\'s bank code.', $value );
				}
			}
		}

		if ( xeroom_ends_with( $key, '_payment_auto_send' ) ) {
			$save_sending_gateway[ $key ] = esc_attr( $value );
		}

		if ( xeroom_ends_with( $key, '_taxmethods' ) ) {
			$save_tax_method[ $key ] = esc_attr( $value );
		}
	}

	//Check for Product Categories and save them.
	if ( isset( $_POST['use_extra_sales_account'] ) && 'product_categories' === $_POST['use_extra_sales_account'] && ! empty( $_POST['product_account_categories'] ) ) {
		for ( $cx = 0; $cx < count( $_POST['product_account_categories'] ); $cx ++ ) {
			if ( '' != $_POST['product_categories'][ $cx ] && '' != $_POST['product_account_categories'][ $cx ] ) {
				$save_categories[ $cx ] = array( $_POST['product_categories'][ $cx ] => $_POST['product_account_categories'][ $cx ] );
			} elseif ( '' != $_POST['product_categories'][ $cx ] && '' == $_POST['product_account_categories'][ $cx ] ) {
				$display_message = __( 'Please associate Xero account to selected categories', 'xeroom' );
			} elseif ( '' == $_POST['product_categories'][ $cx ] && '' != $_POST['product_account_categories'][ $cx ] ) {
				$display_message = __( 'Please associate category to Xero account', 'xeroom' );
			}
		}
	}

	//Check for Shipping methods and save them.
	if (  isset( $_POST['use_extra_sales_account'] ) && 'geography_zones' === $_POST['use_extra_sales_account'] && ! empty( $_POST['xeroom_account_shipping_zone'] ) ) {
		for ( $s = 0; $s < count( $_POST['xeroom_account_shipping_zone'] ); $s ++ ) {
			if ( '' != $_POST['xeroom_shipping_zone'][ $s ] && '' != $_POST['xeroom_account_shipping_zone'][ $s ] ) {
				$save_shipping[ $s ] = array( $_POST['xeroom_shipping_zone'][ $s ] => $_POST['xeroom_account_shipping_zone'][ $s ] );
			} elseif ( '' != $_POST['xeroom_shipping_zone'][ $s ] && '' == $_POST['xeroom_account_shipping_zone'][ $s ] ) {
				$display_message = __( 'Please associate Xero account to selected shipping zone', 'xeroom' );
			} elseif ( '' == $_POST['xeroom_shipping_zone'][ $s ] && '' != $_POST['xeroom_account_shipping_zone'][ $s ] ) {
				$display_message = __( 'Please associate shipping zone to Xero account', 'xeroom' );
			}
		}
	}

	if ( isset( $_POST['sales_account'] ) ) {
		update_xero_option( 'xero_associate_product_categories', $save_categories );
		update_xero_option( 'xero_associate_shipping_zones', $save_shipping );
		update_xero_option( 'xero_woo_gateway', $save_gateway );
		update_xero_option( 'xero_woo_gateway_payment_send', $save_sending_gateway );

	}

	if ( isset( $_POST['xeroom_tax_method'] ) && 'xero_simple_tax' == $_POST['xeroom_tax_method'] ) {
		update_xero_option( 'xero_tax_methods', $save_tax_method );
	}

	if ( isset( $_POST['xero_reference_select'] ) ) {
		update_xero_option( 'xero_reference_select', $_POST['xero_reference_select'] );
	}

	if ( ! empty( $xero_taxes ) ) {
		update_xero_option( 'xero_taxes_association', $xero_taxes );
	}

	$wpdb->query( "Delete From " . $taxTableName . " where id!=''" );
	$message = "Xero credentials saved successfully!";

	if ( isset( $_POST['productMaster'] ) && 'w' == $_POST['productMaster'] ) {
		xeroom_save_update_tax_rates();
	}
}

$sqlcr                  = "SELECT * FROM " . $xeroCredentialTable . " WHERE id=1";
$xeroCredentialsFromTbl = $wpdb->get_results( $sqlcr );

$xeroApiKey    = $xeroCredentialsFromTbl[0]->xero_api_key;
$xeroApiSecret = $xeroCredentialsFromTbl[0]->xero_api_secret;
$salesAccount  = $xeroCredentialsFromTbl[0]->sales_account;
$bank_Code     = $xeroCredentialsFromTbl[0]->bank_code;
$tax_Code      = $xeroCredentialsFromTbl[0]->tax_code;
$asset_Code    = $xeroCredentialsFromTbl[0]->asset_code;
$sold_Code     = $xeroCredentialsFromTbl[0]->sold_code;
$stockMaster   = $xeroCredentialsFromTbl[0]->stock_master;
$productMaster = $xeroCredentialsFromTbl[0]->product_master;
$paymentMaster = $xeroCredentialsFromTbl[0]->payment_master;

$invoiceStatus               = get_xero_option( 'xero_default_invoice_status' );
$shippingCosts               = get_xero_option( 'xero_default_shipping_costs_code' );
$shipping_price_code         = get_xero_option( 'xero_shipping_price_code' );
$shipping_price_description  = get_xero_option( 'xero_shipping_price_description' );
$xeroom_daily_invoice_limit  = get_xero_option( 'xeroom_daily_invoice_limit' );
$extraSalesAccount           = get_xero_option( 'xero_use_extra_sales_account' );
$credit_note                 = get_xero_option( 'xero_generate_credit_note' );
$generate_payment_refund     = get_xero_option( 'xero_generate_payment_refund' );
$credit_note_status          = get_xero_option( 'xero_credit_note_status' );
$woo_gateway                 = get_xero_option( 'xero_woo_gateway' );
$woo_gateway_payment_sending = get_xero_option( 'xero_woo_gateway_payment_send' );
$invoice_date                = get_xero_option( 'xero_invoice_date' );
$invoice_delivery_address    = get_xero_option( 'xero_invoice_delivery_address' );
$tracking_category           = get_xero_option( 'xero_tracking_category' );
$shipping_tracking_category  = get_xero_option( 'xero_shipping_tracking_category' );
$xero_order_number_size      = get_xero_option( 'xero_order_number_size' );
$send_invoice_method         = get_xero_option( 'xero_send_invoice_method' );
$xero_set_invoice_duedate    = get_xero_option( 'xero_set_invoice_duedate' );
$xero_due_date_custom_days   = get_xero_option( 'xero_due_date_custom_days' );
$xero_due_date_month_day     = get_xero_option( 'xero_due_date_month_day' );
$send_payment_method         = get_xero_option( 'xero_send_payment_method' );
$xero_contact_name           = get_xero_option( 'xero_contact_name' );
$xero_email_invoice          = get_xero_option( 'xero_email_invoice' );
$xero_address_info           = get_xero_option( 'xero_address_info' );
$xero_oauth_client_id        = get_xero_option( 'xero_oauth_client_id' );
$xero_oauth_client_secret    = get_xero_option( 'xero_oauth_client_secret' );
$invoice_prefix              = get_xero_option( 'xero_invoice_prefix' );
$invoice_prefix_active       = get_xero_option( 'xeroom_invoice_no_active' ) ? get_xero_option( 'xeroom_invoice_no_active' ) : 'xero';
$invoice_start_no            = get_xero_option( 'xero_invoice_start_no' );
$invoice_reference           = get_xero_option( 'xero_invoice_reference_prefix' );
$invoice_meta_no             = get_xero_option( 'xeroom_inv_number_meta_key' );
$invoice_meta_ref            = get_xero_option( 'xeroom_inv_reference_meta_key' );
$rounding_account            = get_xero_option( 'xeroom_rounding_account' );
$stripe_fee_account          = get_xero_option( 'xeroom_stripe_fee_account' );
$xero_default_accounts       = get_xero_option( 'xero_default_accounts' );
$xero_default_taxes          = get_xero_option( 'xero_defined_tax_methods' );
$customer_account_no         = get_xero_option( 'xeroom_customer_account_no' );
$xero_send_stripe_fee        = get_xero_option( 'xero_send_stripe_fee' );

$xeroom_tax_method = 'xero_simple_tax';
$fetch_tax_method  = get_xero_option( 'xero_tax_method' );
if ( $fetch_tax_method ) {
	$xeroom_tax_method = $fetch_tax_method;
}

$default_gateway_account = $bank_Code;
if ( $woo_gateway && $woo_gateway['xero_default_payment'] ) {
	$default_gateway_account = $woo_gateway['xero_default_payment'];
}

$default_gateway_payment_sending         = get_xero_option( 'xero_default_payment_auto_send' );
$xero_show_shipping_details         = get_xero_option( 'xero_show_shipping_details' );
$xero_use_custom_meta_inv                = get_xero_option( 'xero_use_custom_meta_inv' );
$xero_order_notes                        = get_xero_option( 'xero_order_notes' );
$xero_wc_partial_deposit                 = get_xero_option( 'xero_wc_partial_deposit' );
$xero_autocomplete_orders                = get_xero_option( 'xero_autocomplete_orders' );
$xeroom_exclude_zero_value               = get_xero_option( 'xeroom_exclude_zero_value' );
$xeroom_send_by_cron                     = get_xero_option( 'xeroom_send_by_cron' );
$synch_debug_mode                        = get_xero_option( 'synch_debug_mode' );
$xero_enable_invoice_sync                = get_xero_option( 'xero_enable_invoice_sync' );
$xero_complete_when_paid                 = get_xero_option( 'xero_complete_when_paid' );
$xero_complete_when_paid_virtual_product = get_xero_option( 'xero_complete_when_paid_virtual_product' );
$xero_include_payment_ref                = get_xero_option( 'xero_include_payment_ref' );
$xero_include_customer_name              = get_xero_option( 'xero_include_customer_name' );
$xero_reference_select                   = get_xero_option( 'xero_reference_select' );


$sql                 = "SELECT * FROM " . $xeroLicActive . " WHERE id=1";
$xeroLicensekeyAct   = $wpdb->get_results( $sql );
$xero_plugin_staus   = sanitize_text_field( $xeroLicensekeyAct[0]->status );
$xero_plugin_license = sanitize_text_field( $xeroLicensekeyAct[0]->license_key );
$xero_plugin_method  = sanitize_text_field( $xeroLicensekeyAct[0]->xero_method );
$wait                = "Yes";

if ( sanitize_text_field( isset( $_POST['zeroom_license_key'] ) ) && sanitize_text_field( $_POST['zeroom_license_key'] ) != "" ) {
	$license_key  = sanitize_text_field( $_POST['zeroom_license_key'] );
	$url          = "https://www.xeroom.com/apidata.php?license_key=" . $license_key;
	$result       = wp_remote_fopen( esc_url( $url ) );
	$validLicense = json_decode( $result, true );

	$wpdb->update( $xeroLicActive,
		array(
			'license_key' => $license_key,
			'status'      => 'expired',
			'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
		),
		array( 'id' => 1 )
	);

	if ( $validLicense && count( $validLicense ) > 0 ) {

		$license_expire_date = esc_attr( $validLicense[0]['date_expiry'] );
		if ( $license_expire_date ) {
			update_xero_option( 'xero_license_expire_date', $license_expire_date );
		} else {
			$license_expire_date = get_xero_option( 'xero_license_expire_date' );
		}

		if ( $validLicense[0]['lic_status'] == 'active' ) {
			$wpdb->update( $xeroLicActive,
				array(
					'status'      => 'active',
					'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
				),
				array( 'id' => 1 )
			);

			$licenseMessage = "Xeroom is Successfully Activated!";
			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );
			update_xero_option( 'xero_license_status', 1 );


			// Delete the first check date.
			delete_xero_option( 'xero_license_check_date' );
		} elseif ( $validLicense[0]['lic_status'] == 'expired' ) {
			if ( $license_expire_date ) {
				$after_7_days = strtotime( "+7 day", strtotime( $license_expire_date ) );
				$today        = date( 'Y-m-d' );
				$datetime1    = new DateTime( $today );
				$datetime2    = new DateTime( date( 'Y-m-d', $after_7_days ) );
				$difference   = $datetime2->diff( $datetime1 )->format( "%a" );

				if ( $after_7_days >= strtotime( date( 'Y-m-d' ) ) ) {
					$licenseErrMessage = "Your licence cannot validate.  It has either expired or cannot reach our server.  Xeroom will stop working in $difference days.  Please renew or contact us if this message remains after 2 days.";
					update_xero_option( 'xero_connection_status', 'failed' );
					update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
					update_xero_option( 'xero_license_status', 1 );

					$wpdb->update( $xeroLicActive,
						array(
							'status'      => 'active',
							'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
						),
						array( 'id' => 1 ) );
				} else {
					$licenseErrMessage = "Your key is not active or is expired. Please contact Xeroom support for assistance.";
					update_xero_option( 'xero_connection_status', 'expired' );
					update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
					update_xero_option( 'xero_license_status', 0 );

					$wpdb->update( $xeroLicActive,
						array(
							'status'      => 'expired',
							'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
						),
						array( 'id' => 1 ) );
				}
			} else {
				$first_try = date( 'Y-m-d' );
				update_xero_option( 'xero_license_check_date', $first_try );

				$licenseErrMessage = "Your licence cannot validate.  It has either expired or cannot reach our server.";
				update_xero_option( 'xero_connection_status', 'failed' );
				update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
				update_xero_option( 'xero_license_status', 0 );

				$wpdb->update( $xeroLicActive,
					array(
						'status'      => 'expired',
						'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
					),
					array( 'id' => 1 ) );
			}
		} else {
			$licenseErrMessage = "Your key is not active or is expired. Please contact Xeroom support for assistance.";
			update_xero_option( 'xero_connection_status', 'expired' );
			update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
			update_xero_option( 'xero_license_status', 0 );

			$wpdb->update( $xeroLicActive,
				array(
					'status'      => 'expired',
					'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
				),
				array( 'id' => 1 ) );
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
		$licenseErrMessage = "Your key is not active or is expired. Please contact Xeroom support for assistance.";
		update_xero_option( 'xero_connection_status', 'expired' );
		update_xero_option( 'xero_connection_status_message', $licenseErrMessage );
		update_xero_option( 'xero_license_status', 0 );

		$wpdb->update( $xeroLicActive,
			array(
				'status'      => 'expired',
				'xero_method' => isset( $validLicense[0]['type'] ) ? esc_attr( $validLicense[0]['type'] ) : 'lite'
			),
			array( 'id' => 1 ) );
	}

	header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
}

if ( $xero_plugin_method == "premium" || $xero_plugin_method == "lite" ) {
	if ( $xero_plugin_staus == "active" ) {
		$xeroMesssage   = "Xeroom Active";
		$licenseMessage = '';
	} else {
		$xeroMesssage = "Xeroom Deactivated";
	}
} else {
	if ( $xero_plugin_staus == "active" ) {
		$xeroMesssage   = "Please enter licence key";
		$licenseMessage = '';
	} else {
		$xeroMesssage = "Awaiting Activation!";
		$wait         = "no";
	}
}
$apijspath = esc_url( XEROOM_JS_PATH . 'xeroom_api_fields.js' );
wp_enqueue_script( 'xeroom_api_fields', $apijspath );

if ( isset( xeroom_read_array( XEROOM_ST, XEROOM_V )['versioning'] ) ) {
	wp_add_inline_script(
		'xeroom_api_fields',
		xeroom_read_array( XEROOM_ST, XEROOM_V )['versioning']
	);
}

// Read WooCommerce Installed Payment Methods
$installed_payment_methods = WC()->payment_gateways->get_available_payment_gateways();

$tax_classes = WC_Tax::get_tax_classes();

// Fetch last log
$sql_debug = "SELECT * FROM `" . $wpdb->prefix . "xeroom_debug` ORDER BY id DESC";
$get_debug = $wpdb->get_row( $sql_debug );

if ( isset( $_POST['xero_oauth_client_id'] ) ) {
	$oauth2 = get_xero_option( 'xero_oauth_options' );
	xeroom_check_xero_token( $oauth2 );
	$xero_api = new Xero( $xeroApiKey, $xeroApiSecret, PUBLIC_KEY, PRIVATE_KEY, FORMAT, $oauth2 );

	$get_currencies = $xero_api->Currencies();

	if ( $get_currencies ) {
		if ( is_array( $get_currencies ) ) {
			update_xero_option( 'xero_connection_status', 'active' );
			update_xero_option( 'xero_connection_status_message', '' );
		} else {
			if ( is_array( $get_currencies ) ) {
				$errD = $get_currencies['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
				update_xero_option( 'xero_connection_status_message', $errD );
				update_xero_option( 'xero_connection_status', 'failed' );
			} else {
				update_xero_option( 'xero_connection_status_message', $get_currencies );
				update_xero_option( 'xero_connection_status', 'failed' );
			}
		}
	} else {
		update_xero_option( 'xero_connection_status_message', 'Cannot connect to Xero - Please check your settings or Xero service status.' );
		update_xero_option( 'xero_connection_status', 'failed' );
	}
}


if ( 'lite' === XEROOM_TYPE ) {
	update_xero_option( 'xero_wc_partial_deposit', 0 );
} else {
	update_xero_option( 'xero_wc_partial_deposit', 1 );
}

$connection_status         = get_xero_option( 'xero_connection_status' );
$connection_status_message = get_xero_option( 'xero_connection_status_message' );

$oauth2 = get_xero_option( 'xero_oauth_options' );
if ( ! $oauth2 ) {
	$connection_status = 'failed';
}

if ( isset( $_GET['authorize'] ) ) {
    require_once( XEROOM_PLUGIN_PATH . '/vendor/autoload.php' );
	require_once( XEROOM_ROOT_PATH . 'storage.php' );
	$storage = new StorageClass();

	$provider = new \League\OAuth2\Client\Provider\GenericProvider( [
		'clientId'                => $xero_oauth_client_id,
		'clientSecret'            => $xero_oauth_client_secret,
		'redirectUri'             => get_rest_url( null, 'xeroom/v2/oauth_callback' ),
		'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
		'urlAccessToken'          => 'https://identity.xero.com/connect/token',
		'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation',
	] );

	$options = [
		'scope' => [ 'openid email profile offline_access accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments' ]
	];

	// This returns the authorizeUrl with necessary parameters applied (e.g. state).
	$authorizationUrl = $provider->getAuthorizationUrl( $options );

	// Save the state generated for you and store it to the session.
	$_SESSION['oauth2state'] = $provider->getState();

	// Redirect the user to the authorization URL.
	header( 'Location: ' . $authorizationUrl );
	exit();
}

?>
    <div id="getstatus"></div>
    <div class="xero_left xero_inputdiv">
        <div class="xeroom-logo"><img src="<?php echo XEROOM_HTTP_PATH; ?>images/logo/<?php if ( 'lite' !== XEROOM_TYPE ) {
				echo 'Xeroom_premium_logo.png';
			} else {
				echo 'xeroom.png';
			} ?>"></div>
        <div style="text-align: center; margin-top: 51px; width: 100%; margin-bottom: -40px;font-size: 24px;margin-left: 135px;">
            <h3><?php if ( 'lite' === XEROOM_TYPE ) {
					printf( '<span style="color:darkmagenta">%s</span>', esc_html( 'Starter ' ) );
				} ?><?php echo sprintf( '%s %s', __( 'Version' ), XEROOM_VERSION ); ?></h3></div>
    </div>
<?php
if ( 'lite' === XEROOM_TYPE ) {
	?>
    <div class="clear"></div>
    <a href="https://www.xeroom.com/licence-upgrade/" target="_blank"
       class="xero_heading xero_upgrade_message xero_upgrade_message_main"><?php echo 'To enable all the features upgrade to Xeroom Premium Version!'; ?></a>
<?php } ?>
    <div class="xero_clear"></div>
<?php if ( sanitize_text_field( ! isset( $_REQUEST['xeroom_debug'] ) ) && sanitize_text_field( ! isset( $_REQUEST['xeroom_upload'] ) ) ) { ?>
    <div class="content">
        <div class="xero_clear"></div>

        <div class="xero_email" id="xero_email">
            <form method="post" name="zeroom_email" action="">
                <div class="xero_left xero_label"><?php echo __( 'Enter Email Address', 'xeroom' ); ?></div>
                <div class="xero_left xero_inputdiv">
                    <label for="zeroom_license_email"><?php echo __( 'Please enter your email and we’ll send you the license', 'xeroom' ); ?></label><br/>
                    <input type="text" value="" class="xero_input" id="zeroom_license_email" name="zeroom_license_email"
                           placeholder="<?php echo __( 'Please enter valid email address!', 'xeroom' ); ?>">
                </div>
                <div class="xero_clear"></div>
                <div class="button-primary xero-primery" onclick="check_zeroom_email();"><?php echo __( 'Send me the license', 'xeroom' ); ?></div>
                <div class="button-primary xero-primery" onclick="hideEmailForm();" style="margin: 12px 0 0 15px !important;"><?php echo __( 'Cancel', 'xeroom' ); ?></div>
            </form>
        </div>
    </div>
    <div class="content">
        <div class="xero_clear"></div>
        <h3><?php echo __( 'Xeroom Licence Key', 'xeroom' ); ?></h3>
        <div class="xero_clear"></div>
		<?php if ( isset( $licenseMessage ) && $licenseMessage != "" ) { ?>
            <div class="xero_heading" style="margin-left:220px"><?php echo $licenseMessage; ?></div>
		<?php } else if ( isset( $licenseErrMessage ) && $licenseErrMessage != "" ) { ?>
            <div class="xero_heading" style="color:red !important;margin-left:220px"><?php echo $licenseErrMessage; ?></div>
		<?php } ?>
        <div class="xero_clear"></div>
        <div class="xero_license">
            <form method="post" name="zeroom_activate_license" id="zeroom_activate_license" action="">
                <div class="xero_clear"></div>
                <div class="xero_left xero_label"><?php echo __( 'Status', 'xeroom' ); ?></div>
                <div class="xero_left xero_inputdiv">
					<?php if ( $xero_plugin_staus == 'active' ) { ?>
                        <div class="xero_premium xero_statusn" style="background-color:<?php if ( $xero_plugin_method == "" ) {
							echo "#00cc00";
						} else {
							echo "#00802B";
						} ?>"><?php echo $xeroMesssage; ?></div>
					<?php } else { ?>
                        <div class="xero_trail xero_statusn" style="background-color:#119DCB;"><?php echo $xeroMesssage; ?></div>
					<?php } ?>
                </div>
                <div class="xero_clear"></div>
                <div class="xero_left xero_label"><?php echo __( 'Xeroom Licence Key', 'xeroom' ); ?></div>
                <div class="xero_left xero_inputdiv">
                    <input type="text" value="<?php echo '' != $xero_plugin_license ? $xero_plugin_license : ''; ?> " class="xero_input" id="zeroom_license_key" name="zeroom_license_key"
                           placeholder="Please enter Xeroom license key!">
                </div>
                <div class="xero_clear"></div>
                <div class="button-primary xero-primery" onclick="check_license_key();"><?php echo __( 'Submit', 'xeroom' ); ?></div>
            </form>
        </div>
    </div>
    <hr>
    <div class="content">
        <div class="xero_clear"></div>
        <h3><?php echo __( 'Xero Settings', 'xeroom' ); ?></h3>
		<?php
		if ( ! empty( $message ) ) {
			?>
            <div class="xero_clear"></div>
            <span class="xero_heading xeroom-message-display" style="margin-left: 220px;"><?php echo $message; ?></span>
		<?php } ?>
		<?php
		if ( ! empty( $display_message ) ) {
			?>
            <div class="clear"></div>
            <span class="xero_heading" style="color:red;margin-left: 220px;"><?php echo $display_message; ?></span>
		<?php } ?>

        <div class="clear"></div>
        <div>
            <form action="" method="post" name="xero_api_key_submit" id="xero_form_settings">

                <div class="clear"></div>
                <div>
                    <div class="xero_left xero_label"><?php echo __( 'Xero OAuth Client ID', 'xeroom' ); ?></div>
                    <div class="xero_left xero_inputdiv">
                        <input type="text" placeholder="Xero OAuth 2.0 credentials Client ID" value="<?php echo $xero_oauth_client_id; ?>" class="xero_input" name="xero_oauth_client_id"
                               id="xero_oauth_client_id">
                    </div>
                </div>
                <div class="clear"></div>
                <div>
                    <div class="xero_left xero_label"><?php echo __( 'Xero OAuth Client Secret', 'xeroom' ); ?></div>
                    <div class="xero_left xero_inputdiv">
                        <input type="text" placeholder="Enter your Xero OAuth 2.0 credentials Client Secret" value="<?php echo $xero_oauth_client_secret; ?>" class="xero_input"
                               name="xero_oauth_client_secret" id="xero_oauth_client_secret">
                    </div>
                </div>
                <div class="clear"></div>
				<?php if ( $connection_status && 'active' !== $connection_status ) { ?>
                    <p style="font-size: 15px"><?php echo esc_html__( 'Xeroom is not connected to Xero. Please create your OAuth 2 credentials in the ', 'xeroom' ); ?> <a
                                href="https://developer.xero.com/app/manage" target="_blank">Xero Developer My Apps Center</a> and copy it here. Then save with Submit.</p>
				<?php } ?>
                <p style="font-size: 15px"><?php echo esc_html__( 'Please use the following URL as your redirect URL when creating a Xero application: ', 'xeroom' ); ?>
                    <span<?php if ( 'active' !== $xero_plugin_staus ) {
						echo ' style="font-weight:bold; color:indigo"';
					} ?>><?php echo get_rest_url( null, 'xeroom/v2/oauth_callback' ); ?></span></p>
				<?php if ( $xero_oauth_client_secret && $xero_oauth_client_id ) {
					$organisation_name = get_xero_option( 'xero_organisation_name' );
					?>
                    <div class="clear"></div>
                    <div>
                        <a href="<?php echo admin_url( 'admin.php?page=add_xero_api_fields&authorize' ); ?>" style="float: left; padding: 5px"
                           class="button btn button-primary"><?php echo __( 'Xero Authorize', 'xeroom' ); ?> <img src="<?php echo XEROOM_HTTP_PATH; ?>images/xero-logo.png"
                                                                                                                  style="height: 30px; margin-left: 10px;vertical-align: middle"/></a>
                        <div class="xero_left xero_label" style="margin-left: 50px; margin-top: -3px"><?php echo __( 'Xero Connection Status', 'xeroom' ); ?></div>
                        <div class="xero_left xero_inputdiv">
							<?php if ( $connection_status ) {

								if ( 'active' == $connection_status ) {
									$display_the_message = __( 'Active', 'xeroom' );

									if ( ! empty( $organisation_name ) ) {
										$display_the_message .= sprintf( ' - You are connected to <strong>%s</strong> organisation', $organisation_name );
									}
								} else {
									$display_the_message = __( 'Failed', 'xeroom' );
								}
								?>
                                <div class="xeroom-connection-status<?php echo ( 'active' == $connection_status ) ? ' xero-active' : ' xero-failed'; ?>"><?php echo $display_the_message; ?></div>
							<?php } else { ?>
                                <div class="xeroom-connection-status grey"><?php echo __( 'Active', 'xeroom' ) . '/' . __( 'Failed', 'xeroom' ); ?></div>
							<?php }
							if ( $connection_status && ( 'expired' == $connection_status || 'failed' == $connection_status ) ) {
								$is_json = json_decode( str_replace( '<br/>', '', $connection_status_message ), true );

								if ( $is_json ) {
									$connection_status_message = $is_json['Detail'];
								}
								?>
                                <div class="xeroom-debug-message"><?php echo "<b>" . __( 'New Debug Message:', 'xeroom' ) . "</b><br/>";
									echo htmlspecialchars_decode( esc_attr( $connection_status_message ) ); ?></div>
							<?php } ?>
                        </div>
                    </div>
				<?php } ?>

                <div class="clear"></div>
                <hr>
                <div class="clear"></div>
				<?php
				if ( isset ( $_GET['tab'] ) ) {
					xero_settings_tabs( $_GET['tab'] );
				} else {
					xero_settings_tabs( 'general' );
				}
				?>
				<?php
				$tab = 'general';
				if ( isset ( $_GET['tab'] ) ) {
					$tab = $_GET['tab'];
				}
				switch ( $tab ) {
					case "taxes" : ?>
                        <div class="clear"></div>
                        <div>
                            <h3><?php echo __( 'Map WC tax classes to Xero methods', 'xeroom' ); ?></h3>
                            <div class="xero_left xero_label"><?php echo __( 'Select tax methods', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="xeroom_tax_method" id="xero_simple_tax"
                                               value="xero_simple_tax" <?php checked( 'xero_simple_tax', $xeroom_tax_method ); ?>> <?php echo __( 'Simple Tax Methods', 'xeroom' ); ?> &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xeroom_tax_method" id="xero_complex_tax"
                                               value="xero_complex_tax" <?php checked( 'xero_complex_tax', $xeroom_tax_method ); ?>> <?php echo __( 'Complex Tax Methods', 'xeroom' ); ?>

                                    </label>
                                    <div style="float: right;margin-left: 20px;"><?php if ( 'lite' === XEROOM_TYPE ) {
											echo sprintf( '<span class="xero_upgrade_message">%s</span>', esc_html( ' Upgrade to Premium to use this feature.' ) );
										} ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="xero_tax_methods_content">
							<?php
							$fetch_saved_tax = get_xero_option( 'xero_tax_methods' );
							?>
                            <div class="clear"></div>
                            <div>
                                <div class="xero_left xero_label"><?php echo sprintf( '%s %s %s', __( 'Xero', 'xeroom' ), __( 'Standard', 'xeroom' ), __( 'Tax Name', 'xeroom' ) ); ?></div>
                                <div class="xero_left xero_inputdiv xero_simple_tax">
                                    <select class="xero_input" name="xero_standard_taxmethods" id="xero_standard_tax">
										<?php
										if ( $xero_default_taxes ) {
											echo sprintf( '<option value="">%s</option>', sprintf( '%s %s %s', __( 'Select Xero', 'xeroom' ), __( 'Standard', 'xeroom' ), __( 'Tax Name', 'xeroom' ) ) );
											foreach ( $xero_default_taxes as $xero_saved_taxes ) {
												$selected = '';
												if ( $xero_saved_taxes['Name'] == $fetch_saved_tax['xero_standard_taxmethods'] ) {
													$selected = ' selected';
												}
												echo sprintf( '<option value="%s"%s>%s</option>', $xero_saved_taxes['Name'], $selected, $xero_saved_taxes['Name'] );
											}
										} else {
											$tax_entry = $fetch_saved_tax && array_key_exists( 'xero_standard_taxmethods', $fetch_saved_tax ) && '' != $fetch_saved_tax['xero_standard_taxmethods'] ? $fetch_saved_tax['xero_standard_taxmethods'] : '';
											echo sprintf( '<option value="%s">%s</option>', $tax_entry, $tax_entry );
										}
										?>
                                    </select>
                                </div>
                                <div class="xero_left xero_inputdiv xero_complex_tax">
									<?php
									$display_standard_tax = WC_Tax::get_rates_for_tax_class( '' );
									if ( $display_standard_tax ) {
										generate_xero_settings_tax_table( $display_standard_tax );
									}
									?>
                                </div>
                            </div>
							<?php
							if ( $tax_classes && get_option( 'woocommerce_calc_taxes' ) === 'yes' ) {
								foreach ( $tax_classes as $class ) {
									$tax_class_slug     = strtolower( str_replace( ' ', '_', $class ) );
									$tax_class_woo_slug = strtolower( str_replace( ' ', '-', $class ) );
									?>
                                    <div class="clear"></div>
                                    <div>
                                        <div class="xero_left xero_label"><?php echo sprintf( '%s %s %s', __( 'Xero', 'xeroom' ), esc_attr( $class ), __( 'Tax Name', 'xeroom' ) ); ?></div>
                                        <div class="xero_left xero_inputdiv xero_simple_tax">
                                            <select class="xero_input" name="xero_<?php echo esc_attr( $tax_class_woo_slug ); ?>_taxmethods" id="xeroom_<?php echo esc_attr( $tax_class_slug ); ?>_tax">
												<?php
												if ( $xero_default_taxes ) {
													echo sprintf( '<option value="">%s</option>', sprintf( '%s %s %s', __( 'Enter Xero', 'xeroom' ), esc_attr( $class ), __( 'Tax Name', 'xeroom' ) ) );
													foreach ( $xero_default_taxes as $xero_saved_taxes ) {
														$selected = '';
														if ( $xero_saved_taxes['Name'] == $fetch_saved_tax[ 'xero_' . esc_attr( $tax_class_woo_slug ) . '_taxmethods' ] ) {
															$selected = ' selected';
														}
														echo sprintf( '<option value="%s"%s>%s</option>', $xero_saved_taxes['Name'], $selected, $xero_saved_taxes['Name'] );
													}
												} else {
													$tax_entry = $fetch_saved_tax && array_key_exists( 'xero_' . esc_attr( $tax_class_woo_slug ) . '_taxmethods', $fetch_saved_tax ) && '' != $fetch_saved_tax[ 'xero_' . esc_attr( $tax_class_woo_slug ) . '_taxmethods' ] ? $fetch_saved_tax[ 'xero_' . esc_attr( $tax_class_woo_slug ) . '_taxmethods' ] : '';
													echo sprintf( '<option value="%s">%s</option>', $tax_entry, $tax_entry );
												}
												?>
                                            </select>
                                        </div>

                                        <div class="xero_left xero_inputdiv xero_complex_tax">
											<?php
											$display_standard_tax = WC_Tax::get_rates_for_tax_class( $tax_class_woo_slug );
											if ( $display_standard_tax ) {
												generate_xero_settings_tax_table( $display_standard_tax );
											}
											?>
                                        </div>
                                    </div>
								<?php }
							}
							?>
                        </div>

                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" onclick="submitLicKey();"><?php echo __( 'Submit', 'xeroom' ); ?></div>
						<?php
						break;
					case "sync" :
						$sync_data = get_xero_option( 'sync_stock_data' );
						?>
                        <h3><?php echo __( 'Global Inventory Sync - Synchronize your inventory to/from Xero ', 'xeroom' ); ?>
                            <div style="display: inline-block;"><?php if ( 'lite' === XEROOM_TYPE ) {
									echo sprintf( '<span class="xero_upgrade_message">%s</span>', esc_html( ' - Upgrade to Premium for this feature.' ) );
								} ?></div>
                        </h3>
                        <p><?php echo __( 'The below setting shows who’s Master when Sync all products. You can update a maximum of 500 items/products stock per batch (Xero recommends 50 to 100 per batch).', 'xeroom' ); ?></p>
                        <p><?php #echo __( 'Please ensure you press button twice to avoid errors on stockouts.', 'xeroom' ); ?></p>
                        <p><?php if ( wp_next_scheduled( 'xeroom_sync_schedule' ) ) {
								#echo sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync job in progress!', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Xeroom logs here', 'xeroom' ) );
							} ?></p>
						<?php
						$other_errors = get_xero_option( 'xero_synch_error_log' );

						if ( ! empty( trim( $other_errors ) ) ) {
							echo sprintf( '<p>%s</p>', esc_html( $other_errors ) );
						}
						?>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                function fetchXeroomSyncStatus() {
                                    $.ajax({
                                        url: "<?php echo admin_url('admin-ajax.php'); ?>",
                                        type: 'POST',
                                        data: {
                                            action: 'get_xeroom_stock_sync_status',
                                            nonce: "<?php echo wp_create_nonce('xeroom-ajax'); ?>"
                                        },
                                        success: function(response) {
                                            if (response.status) {
                                                $('#xeroom_sync_response').html('<p>Status: ' + response.status + '</p>');
                                            } else {
                                                $('#xeroom_sync_response').html('');
                                            }
                                        },
                                        error: function() {
                                            $('#xeroom_sync_response').html('<p>An error occurred.</p>');
                                        }
                                    });
                                }

                                // Trigger the fetch function when the page loads
                                fetchXeroomSyncStatus();

                                // Optionally, you can set an interval to update the status periodically
                                setInterval(fetchXeroomSyncStatus, 60000); // Update every minute
                            });
                        </script>
                        <div>
                            <p id="xeroom_sync_response">
                                <?php
                                $cron_executing = get_option( 'xeroom_stock_synch_completed' );
                                $current_batch  = get_option( 'xeroom_current_batch' );
                                $total_batches  = get_option( 'xeroom_total_batches' );
                                $synch_type     = get_option( 'xeroom_synch_type' );
                                // Check if the cron event exists
                                if ( wp_next_scheduled( 'xeroom_sync_schedule' ) ) {
	                                echo sprintf(
		                                esc_html__('Syncing %d of %d batches at 1 batch/ %s', 'xeroom'),
		                                $current_batch,
		                                $total_batches,
		                                $synch_type
	                                );
                                } elseif ( $cron_executing ) {
	                                echo sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync completed.', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Log Files for the report.', 'xeroom' ) );
	                                delete_option('xeroom_stock_synch_completed');
                                }
                                ?>
                            </p>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Sync all products', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_master'] ) && 'w' == $sync_data['sync_master'] ); ?> type="radio" class="xero_input" name="sync_master"
                                                                                                                                           value="w"/> <?php echo __( 'Woo to Xero', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_master'] ) && 'x' == $sync_data['sync_master'] ); ?> type="radio" class="xero_input" name="sync_master"
                                                                                                                                           value="x"/> <?php echo __( 'Xero to Woo', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Automated sync schedule', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_schedule'] ) && 'n' == $sync_data['sync_schedule'] ); ?> type="radio" class="xero_input" name="sync_schedule"
                                                                                                                                               value="n"/> <?php echo __( 'None', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_schedule'] ) && 'm' == $sync_data['sync_schedule'] ); ?> type="radio" class="xero_input" name="sync_schedule"
                                                                                                                                               value="m"/> <?php echo __( 'Every 5 minutes', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_schedule'] ) && 'h' == $sync_data['sync_schedule'] ); ?> type="radio" class="xero_input" name="sync_schedule"
                                                                                                                                               value="h"/> <?php echo __( 'Hourly', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_data['sync_schedule'] ) && 'd' == $sync_data['sync_schedule'] ); ?> type="radio" class="xero_input" name="sync_schedule"
                                                                                                                                               value="d"/> <?php echo __( 'Daily', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Enable Debug Mode', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="checkbox" value="1" name="synch_debug_mode"
                                       id="synch_debug_mode" <?php isset( $sync_data['debug_mode'] ) ? checked( '1', $sync_data['debug_mode'] ) : ''; ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Batch size for sync', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="number" value="<?php echo isset( $sync_data['batch_sync_size'] ) ? $sync_data['batch_sync_size'] : ''; ?>" class="xero_input" name="batch_sync_size"
                                       id="batch_sync_size" min="1" max="500"/>
                            </div>
                        </div>

                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" id="xeroom_sync_now"><?php echo __( 'Synch Now', 'xeroom' ); ?></div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Black listed SKU\'s', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'SKU lists', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
									<?php
									$saved_list = get_xero_option( 'xeroom_blacklisted_sku' );
									?>
                                    <label>
                                        <textarea name="xero_blacklisted_skus" rows="6" cols="70"><?php echo ! empty( $saved_list ) ? implode( ', ', $saved_list ) : ''; ?></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" id="xeroom_black_list"><?php echo __( 'Update List', 'xeroom' ); ?></div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
						<?php
						break;
					case "product_sync" :
						$sync_prod_data = get_xero_option( 'sync_product_data' );
						?>
                        <h3><?php echo __( 'Global Product Sync - Synchronize your product prices and descriptions to/from Xero', 'xeroom' ); ?>
                            <div style="display: inline-block;"><?php if ( 'lite' === XEROOM_TYPE ) {
									echo sprintf( '<span class="xero_upgrade_message">%s</span>', esc_html( ' - Upgrade to Premium for this feature' ) );
								} ?></div>
                        </h3>
                        <p style="color:red"><?php echo __( 'Update product data in Xero.', 'xeroom' ); ?></p>
                        <p style="color:red"><?php echo __( 'Backup your Xero product data first.', 'xeroom' ); ?></p>
                        <p style="color:red"><?php echo __( 'Use with caution as Xero data is overwritten.', 'xeroom' ); ?></p>
                        <p><?php echo __( 'The below setting shows who’s Master when Sync all products. You can update a maximum of 100 items/products per batch (Xero recommends 50 to 100 per batch).', 'xeroom' ); ?></p>
                        <p><?php if ( wp_next_scheduled( 'xeroom_sync_schedule' ) ) {
								#echo sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync job in progress!', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_debug_page' ), __( 'check Xeroom logs here', 'xeroom' ) );
							} ?></p>
						<?php
						$other_errors = get_xero_option( 'xero_product_synch_error_log' );

						if ( ! empty( trim( $other_errors ) ) ) {
							echo sprintf( '<p>%s</p>', esc_html( $other_errors ) );
						}
						?>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                function fetchXeroomProductSyncStatus() {
                                    $.ajax({
                                        url: "<?php echo admin_url('admin-ajax.php'); ?>",
                                        type: 'POST',
                                        data: {
                                            action: 'get_xeroom_product_sync_status',
                                            nonce: "<?php echo wp_create_nonce('xeroom-ajax'); ?>"
                                        },
                                        success: function(response) {
                                            if (response.status) {
                                                $('#xeroom_sync_response').html('<p>Status: ' + response.status + '</p>');
                                            } else {
                                                $('#xeroom_sync_response').html('');
                                            }
                                        },
                                        error: function() {
                                            $('#xeroom_sync_response').html('<p>An error occurred.</p>');
                                        }
                                    });
                                }

                                // Trigger the fetch function when the page loads
                                fetchXeroomProductSyncStatus();

                                // Optionally, you can set an interval to update the status periodically
                                setInterval(fetchXeroomProductSyncStatus, 60000); // Update every minute
                            });
                        </script>
                        <div>
                            <p id="xeroom_sync_response">
                                <?php
                                $cron_executing = get_option( 'xeroom_product_synch_completed' );
                                $current_batch  = get_option( 'xeroom_current_product_batch' );
                                $total_batches  = get_option( 'xeroom_total_product_batches' );
                                $synch_type     = get_option( 'xeroom_synch_product_type' );
                                // Check if the cron event exists
                                if ( wp_next_scheduled( 'xeroom_sync_product_schedule' ) ) {
	                                echo sprintf(
		                                esc_html__('Syncing %d of %d batches at 1 batch/ %s', 'xeroom'),
		                                $current_batch,
		                                $total_batches,
		                                $synch_type
	                                );
                                } elseif ( $cron_executing ) {
	                                echo sprintf( '%s %s <a href="%s" target="_blank">%s</a>', __( 'Sync completed.', 'xeroom' ), __( 'Please', 'xeroom' ), admin_url( 'admin.php?page=xeroom_log_woo_xero' ), __( 'check Log Files for the report.', 'xeroom' ) );
	                                delete_option('xeroom_product_synch_completed');
                                }
                                ?>
                            </p>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Sync all products', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_master'] ) && 'w' == $sync_prod_data['sync_prod_master'] ); ?> type="radio" class="xero_input"
                                                                                                                                                               name="sync_prod_master"
                                                                                                                                                               value="w"
                                                                                                                                                               checked/> <?php echo __( 'Woo to Xero', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_master'] ) && 'x' == $sync_prod_data['sync_prod_master'] ); ?> type="radio" class="xero_input"
                                                                                                                                                               name="sync_prod_master"
                                                                                                                                                               value="x"/> <?php echo __( 'Xero to Woo', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'What to update', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['what_to_update'] ) && 'p' == $sync_prod_data['what_to_update'] ); ?> type="radio" class="xero_input"
                                                                                                                                                           name="what_to_update"
                                                                                                                                                           value="p"
                                                                                                                                                           checked/> <?php echo __( 'Price Only', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['what_to_update'] ) && 'pd' == $sync_prod_data['what_to_update'] ); ?> type="radio" class="xero_input"
                                                                                                                                                            name="what_to_update"
                                                                                                                                                            value="pd"/> <?php echo __( 'Price & Description', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Automated sync schedule', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_schedule'] ) && 'n' == $sync_prod_data['sync_prod_schedule'] ); ?> type="radio" class="xero_input"
                                                                                                                                                                   name="sync_prod_schedule"
                                                                                                                                                                   value="n"/> <?php echo __( 'None', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_schedule'] ) && 'm' == $sync_prod_data['sync_prod_schedule'] ); ?> type="radio" class="xero_input"
                                                                                                                                                                   name="sync_prod_schedule"
                                                                                                                                                                   value="m"/> <?php echo __( 'Every 5 minutes', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_schedule'] ) && 'h' == $sync_prod_data['sync_prod_schedule'] ); ?> type="radio" class="xero_input"
                                                                                                                                                                   name="sync_prod_schedule"
                                                                                                                                                                   value="h"/> <?php echo __( 'Hourly', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input <?php checked( isset( $sync_prod_data['sync_prod_schedule'] ) && 'd' == $sync_prod_data['sync_prod_schedule'] ); ?> type="radio" class="xero_input"
                                                                                                                                                                   name="sync_prod_schedule"
                                                                                                                                                                   value="d"/> <?php echo __( 'Daily', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Batch size for sync', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="number" value="<?php echo isset( $sync_prod_data['batch_product_sync_size'] ) ? $sync_prod_data['batch_product_sync_size'] : ''; ?>" class="xero_input" name="batch_product_sync_size" id="batch_product_sync_size" min="1" max="500"/>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" id="xeroom_prod_sync_now"><?php echo __( 'Synch Now', 'xeroom' ); ?></div>
						<?php
						break;
					case "invoice_sync" :
						$sync_invoice_data = get_xero_option( 'sync_invoice_data' );
						?>
                        <h3><?php echo __( 'Invoice Paid Sync - Synchronize Invoice Payments in Xero With WooCommerce Orders', 'xeroom' ); ?>
                            <div style="display: inline-block;"><?php if ( 'lite' === XEROOM_TYPE ) {
									echo sprintf( '<span class="xero_upgrade_message">%s</span>', esc_html( ' - Upgrade to Premium for this feature.' ) );
								} ?></div>
                        </h3>
                        <p style="color:red"><?php echo __( 'Update Orders Status in WooCommerce to paid if the Xero invoice has the status Paid.', 'xeroom' ); ?></p>
                        <p style="color:red"><a href="https://developer.xero.com/documentation/guides/webhooks/creating-webhooks/"
                                                target="_blank"><?php echo __( 'Create a Xero Webhook to trigger the Invoice event', 'xeroom' ); ?></a></p>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Enable this feature', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="xero_enable_invoice_sync" <?php checked( '1', $xero_enable_invoice_sync ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Complete Order When Paid', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="xero_complete_when_paid" <?php checked( '1', $xero_complete_when_paid ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Complete Order When Paid - For virtual products only', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="xero_complete_when_paid_virtual_product" <?php checked( '1', $xero_complete_when_paid_virtual_product ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Webhooks key', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="text" class="xero_input" name="sync_invoice_key" value="<?php echo $sync_invoice_data; ?>"/> <?php echo __( 'Webhooks key', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" onclick="submitLicKey();" id="xeroom_invoice_sync_now"><?php echo __( 'Save data', 'xeroom' ); ?></div>
						<?php
						break;
					case "ebay_and_amazon" :
						$ebay_and_amazon_settings = get_xero_option( 'ebay_and_amazon_settings' );
						?>
                        <h3><?php echo __( 'eBay and Amazon', 'xeroom' ); ?><span><?php echo __( ' - Skip order sending for orders with customers emails in the list', 'xeroom' ); ?></span>
                            <div style="display: inline-block;"><?php if ( 'lite' === XEROOM_TYPE ) {
									echo sprintf( '<span class="xero_upgrade_message"> - %s</span>', esc_html( 'Upgrade to Premium for this feature.' ) );
								} ?></div>
                        </h3>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Enable this feature', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="ebay_and_amazon_settings" <?php checked( '1', $ebay_and_amazon_settings ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Email\'s lists', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
									<?php
									$saved_emails_list = get_xero_option( 'xeroom_emails_lists' );
									?>
                                    <label>
                                        <textarea name="xeroom_emails_lists" rows="6" cols="70"><?php echo ! empty( $saved_emails_list ) ? $saved_emails_list : ''; ?></textarea>
                                    </label>
                                    <p><?php echo __( 'When entering the email patterns, add a space after each comma!', 'xeroom' ); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" onclick="submitLicKey();" id="xeroom_invoice_sync_now"><?php echo __( 'Save data', 'xeroom' ); ?></div>
						<?php
						break;
					default:
						?>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Invoice Numbering', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"></div>
                            <div class="xero_left xero_inputdiv" style="text-align: right; width: 495px; margin-bottom:18px">
                                <span class="xero_label"><?php echo __( 'Xero Numbering', 'xeroom' ); ?></span> <input type="radio" name="xeroom_invoice_no_active"
                                                                                                                       value="xero" <?php checked( 'xero', $invoice_prefix_active ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>

                        <div class="xeroom-invoice-prefix">
                            <div>
                                <div class="xero_left xero_label"><?php echo __( 'Order No. Prefix', 'xeroom' ); ?></div>
                                <div class="xero_left xero_inputdiv">
                                    <input type="text" value="<?php echo $invoice_prefix; ?>" class="xero_input" name="invoice_prefix" id="invoice_prefix">
                                </div>
                            </div>
                            <div>
                                <div class="xero_left xero_label"><?php echo __( 'Set Start No', 'xeroom' ); ?></div>
                                <div class="xero_left xero_inputdiv">
                                    <input type="number" placeholder="<?php echo __( 'eg 1000', 'xeroom' ); ?>" value="<?php echo $invoice_start_no; ?>" class="xero_input" name="invoice_start_no"
                                           id="invoice_start_no"> <input type="radio" name="xeroom_invoice_no_active" value="prefix" <?php checked( 'prefix', $invoice_prefix_active ); ?> />
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Custom Meta Invoice No.', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $invoice_meta_no; ?>" class="xero_input" name="invoice_meta_no" id="invoice_meta_no"> <input type="radio"
                                                                                                                                                                  name="xeroom_invoice_no_active"
                                                                                                                                                                  value="meta" <?php checked( 'meta', $invoice_prefix_active ); ?> />
                            </div>
                        </div>

                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_label_section fl-left"><?php echo __( 'Invoice Reference', 'xeroom' ); ?></div>
                            <div class="xero_reference_select">
                                <label>
									<?php echo __( 'Order No.', 'xeroom' ); ?> &nbsp; <input type="radio" class="xero_input" name="xero_reference_select" id="xero_reference_select"
                                                                                             value="xero_ref_order_no" <?php checked( 'xero_ref_order_no', $xero_reference_select ); ?>>
                                </label>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Add Prefix and Gateway', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $invoice_reference; ?>" class="xero_input" name="invoice_reference_prefix" id="invoice_reference_prefix">
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="fl-left">
                            <div class="xero_left xero_label xero_shorter_label"><?php echo __( 'Include payment ref.', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="xero_include_payment_ref" <?php checked( '1', $xero_include_payment_ref ); ?> />
                            </div>
                        </div>
                        <div class="fl-left">
                            <div class="xero_left xero_label xero_shorter_label"><?php echo __( 'Include Customer Name', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv" style="margin-top: 7px">
                                <input type="checkbox" value="1" name="xero_include_customer_name" <?php checked( '1', $xero_include_customer_name ); ?> />
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_label_section fl-left">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                            <div class="xero_reference_select">
                                <label>
									<?php echo __( 'Purchase Order No.', 'xeroom' ); ?> &nbsp; <input type="radio" class="xero_input" name="xero_reference_select" id="xero_reference_select"
                                                                                                      value="xero_ref_purchase_order_no" <?php checked( 'xero_ref_purchase_order_no', $xero_reference_select ); ?>>
                                </label>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Custom Meta Invoice Ref.', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $invoice_meta_ref; ?>" class="xero_input" name="invoice_meta_ref" id="invoice_meta_ref"> &nbsp; &nbsp; <input type="radio"
                                                                                                                                                                                   class="xero_input"
                                                                                                                                                                                   name="xero_reference_select"
                                                                                                                                                                                   id="xero_reference_select"
                                                                                                                                                                                   value="xero_invoice_purchase_order_no" <?php checked( 'xero_invoice_purchase_order_no', $xero_reference_select ); ?>>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Accounts', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Sales Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="sales_account" id="sales_account">
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'REVENUE' !== $sale_account['Type'] && 'CURRENT' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $salesAccount ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $salesAccount, $salesAccount );
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Shipping Revenues Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="shipping_costs" id="shipping_costs">
									<?php
									if ( $xero_default_accounts ) {

										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'REVENUE' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] && 'OTHERINCOME' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $shippingCosts ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $shippingCosts, $shippingCosts );
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Rounding adjustment Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="rounding_account" id="rounding_account">
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'CURRLIAB' !== $sale_account['Type'] && 'OVERHEADS' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $rounding_account ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $rounding_account, $rounding_account );
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Stripe Fee Expense Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="stripe_fee_account" id="stripe_fee_account">
                                    <option value=""><?php echo __( 'Please select Stripe Bank Fee', 'xeroom' ); ?></option>
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'CURRLIAB' !== $sale_account['Type'] && 'OVERHEADS' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $stripe_fee_account ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $stripe_fee_account, $stripe_fee_account );
									}
									?>
                                </select>
                                <div class="xero-send-automatically">
                                    <input type="checkbox" value="1" <?php checked( '1', $xero_send_stripe_fee ); ?> name="xero_send_stripe_fee"> <?php echo __( 'Send Stripe Fee', 'xeroom' ); ?>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Shipping price code and description', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Shipping price code', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $shipping_price_code; ?>" class="xero_input" name="shipping_price_code" id="shipping_price_code">
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Shipping price description', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $shipping_price_description; ?>" class="xero_input" name="shipping_price_description" id="shipping_price_description">
                            </div>
                            <div class="xero-send-automatically" style="margin-top: 15px;">
                                <input type="checkbox" value="1" name="xero_show_shipping_details" id="xero_show_shipping_details" <?php checked( '1', $xero_show_shipping_details ); ?> /> <?php echo __( 'Show Shipping Details', 'xeroom' ); ?>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Map Payment Methods to Bank Accounts', 'xeroom' ); ?>
                                <div style="display: inline-block;"><?php if ( 'lite' === XEROOM_TYPE ) {
										echo sprintf( '<span class="xero_upgrade_message">%s</span>', esc_html( ' - To map more bank accounts upgrade to Premium.' ) );
									} ?></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <span class="xero_heading" style="color:red;margin-left: 220px;"><?php echo $gateway_message; ?></span>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Default Payment Gateway Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv xero-payment-gateway-input">
                                <select class="xero_input" name="xero_default_payment" id="xero_default_payment">
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'BANK' !== $sale_account['Type'] && 'CURRLIAB' !== $sale_account['Type'] && 'CURRENT' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $default_gateway_account ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $default_gateway_account, $default_gateway_account );
									}
									?>
                                </select>
                                <div class="xero-send-automatically">
                                    <input type="checkbox" value="1"
                                           name="xero_default_payment_auto_send" <?php checked( '1', $default_gateway_payment_sending ); ?> /> <?php echo __( 'Send payment automatically', 'xeroom' ); ?>
                                </div>
                            </div>
                        </div>
						<?php
						if ( $installed_payment_methods ) {
							foreach ( $installed_payment_methods as $method ) {
								if ( 'yes' !== $method->settings['enabled'] ) {
									continue;
								}
								$gateway_account = '';
								if ( $woo_gateway && array_key_exists( 'xero_' . $method->id . '_payment', $woo_gateway ) ) {
									$gateway_account = $woo_gateway[ 'xero_' . $method->id . '_payment' ];
								}

								$gateway_send_payment = 0;
								if ( $woo_gateway_payment_sending && array_key_exists( 'xero_' . $method->id . '_payment_auto_send', $woo_gateway_payment_sending ) ) {
									$gateway_send_payment = 1;
								}

								$gateway_title = ! empty( $method->title ) ? esc_attr( $method->title ) : ucfirst( esc_attr( $method->id ) );
								?>
                                <div class="clear"></div>
                                <div>
                                    <div class="xero_left xero_label"><?php echo sprintf( '%s %s', __( 'Enter Xero account for ', 'xeroom' ), esc_attr( $gateway_title ) ); ?></div>
                                    <div class="xero_left xero_inputdiv xero-payment-gateway-input">
                                        <select class="xero_input xeroom_other_payments" name="xero_<?php echo esc_attr( $method->id ); ?>_payment"
                                                id="xeroom_<?php echo esc_attr( $method->id ); ?>_payment">
											<?php
											if ( $xero_default_accounts ) {
												echo sprintf( '<option value="">%s</option>', sprintf( '%s %s', __( 'Enter Xero account for ', 'xeroom' ), esc_attr( $gateway_title ) ) );
												foreach ( $xero_default_accounts as $sale_account ) {
													if ( 'BANK' !== $sale_account['Type'] && 'CURRLIAB' !== $sale_account['Type'] && 'CURRENT' !== $sale_account['Type'] ) {
														continue;
													}

													if ( ! isset( $sale_account['Code'] ) ) {
														continue;
													}

													$selected = '';
													if ( $sale_account['Code'] == $gateway_account ) {
														$selected = ' selected';
													}
													echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
												}
											} else {
												echo sprintf( '<option value="%s">%s</option>', $gateway_account, $gateway_account );
											}
											?>
                                        </select>
                                        <div class="xero-send-automatically">
                                            <input type="checkbox" class="xeroom_other_payments" value="1"
                                                   name="xero_<?php echo esc_attr( $method->id ); ?>_payment_auto_send" <?php checked( '1', $gateway_send_payment ); ?>/> <?php echo __( 'Send payment automatically', 'xeroom' ); ?>
                                        </div>
                                    </div>
                                </div>
							<?php }
						}
						?>
                        <div class="clear">
                            <hr/>
                        </div>
						<?php if ( ! wc_tax_enabled() ) { ?>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Sales Tax Account Name', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $tax_Code; ?>" class="xero_input" name="tax_code" id="tax_account">
                            </div>
                        </div>
					<?php } ?>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Inventory Asset Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="asset_code" id="asset_code">
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'INVENTORY' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $asset_Code ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $asset_Code, $asset_Code );
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Cost of Goods Sold Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xero_input" name="sold_code" id="sold_code">
									<?php
									if ( $xero_default_accounts ) {
										foreach ( $xero_default_accounts as $sale_account ) {
											if ( 'DIRECTCOSTS' !== $sale_account['Type'] ) {
												continue;
											}

											if ( ! isset( $sale_account['Code'] ) ) {
												continue;
											}

											$selected = '';
											if ( $sale_account['Code'] == $sold_Code ) {
												$selected = ' selected';
											}
											echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
										}
									} else {
										echo sprintf( '<option value="%s">%s</option>', $sold_Code, $sold_Code );
									}
									?>
                                </select>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Synch Inventory for Orders', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="stockMaster" value="w" id="wooMaster" <?php if ( $stockMaster == "w" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Woocommerce', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="stockMaster" value="x" id="xeroMaster" <?php if ( $stockMaster == "x" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Xero', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="stockMaster" value="n" id="noneMaster" <?php if ( $stockMaster == "n" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'None', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Create Invoices as', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="invoiceStatus" value="DRAFT" <?php checked( 'DRAFT', $invoiceStatus ); ?>> <?php echo __( 'Draft', 'xeroom' ); ?>
                                        &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="invoiceStatus"
                                               value="AUTHORISED" <?php checked( 'AUTHORISED', $invoiceStatus ); ?>> <?php echo __( 'Awaiting Payment', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Set Invoice Creation Date', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="invoice_date"
                                               value="posting_date" <?php checked( 'posting_date', $invoice_date ); ?>> <?php echo __( 'Date of posting', 'xeroom' ); ?>
                                        &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="invoice_date"
                                               value="order_date" <?php checked( 'order_date', $invoice_date ); ?>> <?php echo __( 'Date of order', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Send Invoice Delivery Address', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="invoice_delivery_address"
                                               value="1" <?php checked( '1', $invoice_delivery_address ); ?>> <?php echo __( 'Send only if different', 'xeroom' ); ?>
                                        &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="invoice_delivery_address"
                                               value="0" <?php checked( '0', $invoice_delivery_address ); ?>> <?php echo __( 'Don\'t Send', 'xeroom' ); ?>
                                    </label> &nbsp;
                                    <label>
                                        <input type="radio" class="xero_input" name="invoice_delivery_address"
                                               value="2" <?php checked( '2', $invoice_delivery_address ); ?>> <?php echo __( 'Send for all orders', 'xeroom' ); ?>
                                        &nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Post Credit Note When Order is Refunded?', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="generate_credit_note" value="1" <?php checked( '1', $credit_note ); ?>> <?php echo __( 'Yes', 'xeroom' ); ?> &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="generate_credit_note" value="0" <?php checked( '0', $credit_note ); ?>> <?php echo __( 'No', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Post Payment When Order is Refunded?', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="generate_payment_refund"
                                               value="1" <?php checked( '1', $generate_payment_refund ); ?>> <?php echo __( 'Yes', 'xeroom' ); ?> &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="generate_payment_refund"
                                               value="0" <?php checked( '0', $generate_payment_refund ); ?>> <?php echo __( 'No', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Send Credit Note Status as', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="credit_note_status" value="1" <?php checked( '1', $credit_note_status ); ?>> <?php echo __( 'Draft', 'xeroom' ); ?>
                                        &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="credit_note_status"
                                               value="0" <?php checked( '0', $credit_note_status ); ?>> <?php echo __( 'Approved', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Use Extra Sales Accounts', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="use_extra_sales_account" id="product_categories"
                                               value="product_categories" <?php checked( 'product_categories', $extraSalesAccount ); ?>> <?php echo __( 'Product Categories', 'xeroom' ); ?> &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="use_extra_sales_account" id="geography_zones"
                                               value="geography_zones" <?php checked( 'geography_zones', $extraSalesAccount ); ?>> <?php echo __( 'Geography Zones/Regions', 'xeroom' ); ?> &nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="use_extra_sales_account" id="none"
                                               value="none" <?php checked( 'none', $extraSalesAccount ); ?>> <?php echo __( 'None', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="xeroom_use_extra_sales_accounts">
                            <div class="product_categories">
                                <div class="xero_left xero_label"><?php echo sprintf( '<div>%s</div>', __( 'Associate Product Categories', 'xeroom' ) ); ?></div>
                                <div class="xero_left xero_inputdiv" id="product_categories_content"><?php include( plugin_dir_path( __FILE__ ) . '../templates/product_categories.php' ); ?></div>
                            </div>
                            <div class="geography_zones">
                                <div class="xero_left xero_label"><?php echo sprintf( '<div>%s</div>', __( 'Associate Geography Zones/Regions', 'xeroom' ) ); ?></div>
                                <div class="xero_left xero_inputdiv" id="geography_zones_content"><?php include( plugin_dir_path( __FILE__ ) . '../templates/geography_zones.php' ); ?></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Send Invoices', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xeroom-select" name="xero_send_invoice_method">
                                    <option value="manually" <?php selected( $send_invoice_method, 'manually' ); ?>><?php echo __( 'Manually', 'xeroom' ); ?></option>
                                    <option value="on_creation" <?php selected( $send_invoice_method, 'on_creation' ); ?>><?php echo __( 'On Creation', 'xeroom' ); ?></option>

									<?php
									$exclude_statuses = array( 'draft', 'checkout-draft', 'failed', 'refunded', 'cancelled' );
									$order_statuses   = wc_get_order_statuses();
									if ( $order_statuses ) {
										foreach ( $order_statuses as $order_status => $order_status_text ) {
											$order_status = str_replace( 'wc-', '', $order_status );
											if ( in_array( $order_status, $exclude_statuses ) ) {
												continue;
											}

											$selected = '';
											if ( $send_invoice_method === $order_status ) {
												$selected = ' selected';
											}

											echo sprintf( '<option value="%s"%s>%s</option>', esc_html( $order_status ), $selected, esc_html( $order_status_text ) );
										}
									}
									?>
                                </select>
                                <div style="display: inline-block; margin-left: 10px"><label for="xeroom_exclude_zero_value"><input type="checkbox" id="xeroom_exclude_zero_value"
                                                                                                                                    name="xeroom_exclude_zero_value"
                                                                                                                                    value="1" <?php checked( '1', $xeroom_exclude_zero_value ); ?> /> <?php echo __( 'Exclude sending orders of zero value', 'xeroom' ); ?>
                                    </label></div>
                                <div style="display: inline-block; margin-left: 10px"><label for="xeroom_send_by_cron"><input type="checkbox" id="xeroom_send_by_cron" name="xeroom_send_by_cron"
                                                                                                                              value="1" <?php checked( '1', $xeroom_send_by_cron ); ?> /> <?php echo __( 'Use Cron Job to send the Invoice to Xero', 'xeroom' ); ?>
                                    </label></div>
                                <p><?php echo __( 'Send Invoice manually (from the order\'s action menu), on creation (when order is created), or on completion (when order status is changed to complete),<br /> or on processing (when order is paid)', 'xeroom' ); ?></p>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Invoice Due Date', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xeroom-select" id="xero_set_invoice_duedate" name="xero_set_invoice_duedate">
                                    <option value="use_xero_due_date" <?php selected( $xero_set_invoice_duedate, 'use_xero_due_date' ); ?>><?php echo __( 'Use Xero Default', 'xeroom' ); ?></option>
                                    <option value="use_woo_due_date" <?php selected( $xero_set_invoice_duedate, 'use_woo_due_date' ); ?>><?php echo __( 'Use Default +3 days', 'xeroom' ); ?></option>
                                    <option value="use_custom_due_date" <?php selected( $xero_set_invoice_duedate, 'use_custom_due_date' ); ?>><?php echo __( 'Use Custom Due Date', 'xeroom' ); ?></option>
                                    <option value="use_specific_month_day" <?php selected( $xero_set_invoice_duedate, 'use_specific_month_day' ); ?>><?php echo __( 'Use Specific Month Day', 'xeroom' ); ?></option>
                                </select>
                                <p id="xero_due_date_custom_days" style="display: none">
                                    <input type="number" name="xero_due_date_custom_days"
                                           value="<?php echo $xero_due_date_custom_days; ?>"/> <?php echo __( 'Insert Custom Number of Days', 'xeroom' ); ?>
                                </p>
                                <p id="xero_due_date_month_day" style="display: none">
                                    <label for="day"><?php echo __( 'Enter a day of the month (1-31):', 'xeroom' ); ?></label>
                                    <input type="number" name="xero_due_date_month_day" min="1" max="31" value="<?php echo $xero_due_date_month_day; ?>"/>
                                </p>
                                <p><?php echo esc_html__( 'Set Invoice Due Date in Xero - Use Custom Date or the +3 Days Xeroom dafault', 'xeroom' ); ?></p>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Send Payments', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <select class="xeroom-select" name="xero_send_payment_method">
                                    <option value="manually" <?php selected( $send_payment_method, 'manually' ); ?>><?php echo __( 'Manually', 'xeroom' ); ?></option>
                                    <option value="automatically" <?php selected( $send_payment_method, 'automatically' ); ?>><?php echo __( 'Automatically', 'xeroom' ); ?></option>
                                </select>

                                <p><?php echo __( 'Send Payments manually when order is completed. This may need to be turned off if you sync via separate integration such as PayPal', 'xeroom' ); ?></p>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Tracking Category', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $tracking_category; ?>" class="xero_input" name="tracking_category" id="tracking_category">
                                <br>
                                <div><?php echo __( 'Insert Xero Tracking Category Name', 'xeroom' ); ?></div>
                            </div>
                        </div>
                        <!--
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Shipping Tracking Category', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $shipping_tracking_category; ?>" class="xero_input" name="shipping_tracking_category" id="shipping_tracking_category">
                                <br>
                                <div><?php echo __( 'Insert Xero Tracking Category Name', 'xeroom' ); ?></div>
                            </div>
                        </div>
-->
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Number of orders sent on bulk request', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="number" value="<?php echo $xero_order_number_size; ?>" placeholder="4" class="xero_input" name="order_number_size"
                                       id="order_number_size" min="1" max="10"/>
                                <br>
                                <div><?php echo __( 'Add the number of order to be sent on bulk request. Default value is 4', 'xeroom' ); ?></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Number of Orders/Credit Notes sent daily', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="number" value="<?php echo $xeroom_daily_invoice_limit; ?>" placeholder="50" class="xero_input" name="xeroom_daily_invoice_limit"
                                       id="xeroom_daily_invoice_limit" min="1" max="10"/>
                                <br>
                                <div><?php echo __( 'Add the number of orders allowed to be sent daily. The default value is 50.', 'xeroom' ); ?></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Xero address mapping', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Xero Contact Name', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_contact_name" value="xeroom_use_first_name" <?php if ( $xero_contact_name == "xeroom_use_first_name" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'First/Last Name', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_contact_name" value="xeroom_use_company" <?php if ( $xero_contact_name == "xeroom_use_company" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Company Name', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_contact_name" value="xeroom_use_email" <?php if ( $xero_contact_name == "xeroom_use_email" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Email address', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label> 
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_contact_name" value="xeroom_use_acno" <?php if ( $xero_contact_name == "xeroom_use_acno" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Account number', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Xero Address', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_address_info"
                                               value="xeroom_use_woo_address" <?php checked( 'xeroom_use_woo_address', $xero_address_info ); ?>> <?php echo __( 'Use WooCommerce User Address', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_address_info"
                                               value="xeroom_use_xero_address" <?php checked( 'xeroom_use_xero_address', $xero_address_info ); ?>> <?php echo __( 'Use Xero User Address', 'xeroom' ); ?>
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Send invoices', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Send invoices to customer from Xero', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_email_invoice" value="xeroom_unpaid" <?php if ( $xero_email_invoice == "xeroom_unpaid" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Send unpaid invoices automatically', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_email_invoice" value="xeroom_paid" <?php if ( $xero_email_invoice == "xeroom_paid" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Send paid invoices automatically', 'xeroom' ); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </label>
                                    <label>
                                        <input type="radio" class="xero_input" name="xero_email_invoice" value="xeroom_none" <?php if ( $xero_email_invoice == "xeroom_none" ) {
											echo 'checked="checked"';
										} ?>> <?php echo __( 'Do not send', 'xeroom' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section"><?php echo __( 'Miscellaneous', 'xeroom' ); ?></div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Add Order Notes', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_order_notes" <?php checked( '1', $xero_order_notes ); ?> />
                                    </label>
                                </div>
                                <p><?php echo __( 'Transfer any order notes onto the invoice.', 'xeroom' ); ?></p>
                            </div>
                        </div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Autocomplete Orders', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="margin-top: 8px;">
                                    <select class="xeroom-select" name="xero_autocomplete_orders">
                                        <option value="0" <?php selected( $xero_autocomplete_orders, '0' ); ?>><?php echo __( 'Off', 'xeroom' ); ?></option>
                                        <option value="all_orders" <?php selected( $xero_autocomplete_orders, 'all_orders' ); ?>><?php echo __( 'All orders', 'xeroom' ); ?></option>
                                        <option value="virtual_products" <?php selected( $xero_autocomplete_orders, 'virtual_products' ); ?>><?php echo __( 'Virtual Products', 'xeroom' ); ?></option>
                                        <option value="virtual_products_downloadable" <?php selected( $xero_autocomplete_orders, 'virtual_products_downloadable' ); ?>><?php echo __( 'Virtual Products & Downloadable', 'xeroom' ); ?></option>
                                    </select>

                                    <p><?php echo __( 'Select what type of Orders to be set as Completed automatically.', 'xeroom' ); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'Add Customer Account Number', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <input type="text" value="<?php echo $customer_account_no; ?>" class="xero_input" name="customer_account_no" id="customer_account_no">
                                <br>
                                <div><?php echo __( 'Add the meta key that holds the Customer Account Number', 'xeroom' ); ?></div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <?php if ( 'lite' === XEROOM_TYPE ) { ?>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div>
                            <div class="xero_label_section">
                                <?php echo __( 'Extra 3rd Party Plugins Compatibility', 'xeroom' ); ?>
                                <?php printf( '<span style="color:darkmagenta"> - %s</span>', esc_html( 'Premium' ) ); ?>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'WC Deposits and Partial Payments Plugin', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_partial_deposit"  />
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="clear"></div>
                        <div>
                            <div class="xero_left xero_label"><?php echo __( 'WooCommerce Invoice PDF plugins - All vendors', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_pdf_invoice" disabled />
                                    </label>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div class="xero_left xero_label"><?php echo __( 'WooCommerce Custom Order Status Manager', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_order_status" disabled />
                                    </label>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div class="xero_left xero_label"><?php echo __( 'Woocommerce Sequential Order Number', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_order_number" disabled />
                                    </label>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div class="xero_left xero_label"><?php echo __( 'PO Number for WooCommerce', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_po_number" disabled />
                                    </label>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div class="xero_left xero_label"><?php echo __( 'WooCommerce Account Funds', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_account_funds" disabled />
                                    </label>
                                </div>
                            </div>
                            <div class="clear"></div>
                            <div class="xero_left xero_label"><?php echo __( 'WooCcommerce Payments on Account', 'xeroom' ); ?></div>
                            <div class="xero_left xero_inputdiv">
                                <div style="font-weight: bold; margin-top: 8px;">
                                    <label>
                                        <input type="checkbox" value="1" name="xero_wc_payment_accounts" disabled />
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="clear"></div>
                        <div class="clear">
                            <hr/>
                        </div>
                        <div class="clear"></div>
                        <div class="button-primary xero-primery" onclick="submitLicKey();"><?php echo __( 'Submit', 'xeroom' ); ?></div>
						<?php break;
				} ?>
                <div class="clear"></div>
            </form>
        </div>
    </div>
<?php } ?>