<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use GuzzleHttp\Client;

/**
 * Parse Xero Items
 */
$line_items = array();
if ( isset( $xero_items['Items']['Item'] ) ) {
	$line_items = $xero_items['Items']['Item'];
}

/**
 * Parse Woo Products Data
 */
$line_items_update = array();
$total_stock_value = 0;

if ( ! empty( $woo_products ) ) {
	require_once( XEROOM_PLUGIN_PATH . '/vendor/autoload.php' );

	$oauth2 = get_xero_option( 'xero_oauth_options' );
	xeroom_check_xero_token( $oauth2 );

	$config = XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( $oauth2['token'] );

	$apiInstance     = new XeroAPI\XeroPHP\Api\AccountingApi(
		new GuzzleHttp\Client(),
		$config
	);
	$xeroTenantId    = $oauth2['tenant_id'];
	$summarizeErrors = true;
	$unitdp          = 4;

	$no        = 0;
	$arr_items = array();
	foreach ( $woo_products as $woo_product ) {
		$product_sku       = $woo_product['product_sku'];
		$product_id        = $woo_product['product_id'];
		$product_stock     = (int) $woo_product['product_stock'];
		$total_stock_value = (int) $woo_product['product_stock'] * wc_format_decimal( floatval( $woo_product['product_price'] ) );

        $product_id_sent = isset( $woo_product['parent_id'] ) ? $woo_product['parent_id'] : $woo_product['product_id'];
		update_post_meta( absint( $product_id_sent ), 'wpr_item_sent', 1 );

		if ( '' == $product_sku ) {
			$product_sku = sprintf( '%s %s', __( 'Product ID ' ), $product_id );
		}

		$product_sku = xeroom_reduce_sku_length( $product_sku );

		$saved_product_account = get_post_meta( $product_id, 'xerrom_product_account', true );
		if ( $saved_product_account ) {
			$salesAccount = $saved_product_account;
		}
        
		$saved_cost_account = get_post_meta( $product_id, 'xerrom_cost_account', true );
		if ( $saved_cost_account ) {
			$SoldCode = $saved_cost_account;
		}

		$saved_inventory_account = get_post_meta( $product_id, 'xerrom_inventory_account', true );
		if ( $saved_inventory_account ) {
			$asset_code = $saved_inventory_account;
		}

		$sales    = new XeroAPI\XeroPHP\Models\Accounting\Purchase;
		$purchase = new XeroAPI\XeroPHP\Models\Accounting\Purchase;
		if ( ! empty( $woo_product['product_tax'] ) ) {
			$sales->setUnitPrice( wc_format_decimal( floatval( $woo_product['product_price'] ) ) )
			      ->setTaxType( $woo_product['product_tax'] )
			      ->setAccountCode( $salesAccount );
		} else {
			$sales->setUnitPrice( wc_format_decimal( floatval( $woo_product['product_price'] ) ) )
			      ->setAccountCode( $salesAccount );
		}

		$item = new XeroAPI\XeroPHP\Models\Accounting\Item;
        
		if ( ! find_item_by_SKU( $line_items, $product_sku ) ) {
            $purchase->setUnitPrice( wc_format_decimal( 0 ) )
			         ->setTaxType( $woo_product['product_tax'] );
            
			$item->setName( xeroom_reduce_item_name_length( $woo_product['product_name'] ) )
			     ->setCode( $product_sku )
			     ->setDescription( $woo_product['product_desc'] )
			     ->setPurchaseDescription( $woo_product['product_name'] )
			     ->setQuantityOnHand( $woo_product['product_stock'] )
			     ->setTotalCostPool( $total_stock_value )
			     ->setIsTrackedAsInventory( false )
			     ->setIsSold( true )
			     ->setIsPurchased( true )
			     ->setSalesDetails( $sales )
			     ->setPurchaseDetails( $purchase );
		} else {
			if ( 'pd' == $what_to_update ) {
				$item->setName( xeroom_reduce_item_name_length( $woo_product['product_name'] ) )
				     ->setCode( $product_sku )
				     ->setDescription( $woo_product['product_desc'] )
				     ->setPurchaseDescription( $woo_product['product_name'] )
				     ->setQuantityOnHand( $woo_product['product_stock'] )
				     ->setTotalCostPool( $total_stock_value )
				     ->setIsTrackedAsInventory( false )
//				     ->setInventoryAssetAccountCode( $asset_code )
				     ->setIsSold( true )
				     ->setIsPurchased( true )
				     ->setSalesDetails( $sales );
			} else {
				$item->setCode( $product_sku )
				     ->setQuantityOnHand( $woo_product['product_stock'] )
				     ->setTotalCostPool( $total_stock_value )
				     ->setIsTrackedAsInventory( false )
//				     ->setInventoryAssetAccountCode( $asset_code )
				     ->setIsSold( true )
				     ->setIsPurchased( true )
				     ->setSalesDetails( $sales );
			}
		}

		array_push( $arr_items, $item );

		$line_items_update[] = array(
			'no'          => $no,
			'sku'         => $product_sku,
			'description' => $woo_product['product_desc'],
			'price'       => $woo_product['product_price'],
			'status'      => 'Updated',
		);
		$no ++;
	}

	$items = new XeroAPI\XeroPHP\Models\Accounting\Items;
	$items->setItems( $arr_items );

	try {
		$result = $apiInstance->updateOrCreateItems( $xeroTenantId, $items, $summarizeErrors );
	} catch ( \XeroAPI\XeroPHP\ApiException $e ) {
		error_log( 'Exception when calling AccountingApi->updateOrCreateItems: ' . print_r( $e->getResponseBody(), true ) );
//		$line_items_update = array();
	}

} else {
	xeroom_reset_sync_products();
}

xeroom_generate_product_sync_log( $line_items_update, 'woo-to-xero-p' );
