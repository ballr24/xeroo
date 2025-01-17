<?php
/**
 * Parse Xero Items
 */
$xero_line_items = array();
if ( isset( $xero_items ) ) {
    $xero_items = array_values(array_reduce($xero_items, function ($carry, $item) {
        $code = $item['Code'];
        if (!isset($carry[$code])) {
            $carry[$code] = $item;
        }
        return $carry;
    }, []));
	foreach ( $xero_items as $xero_item ) {
		if ( array_key_exists( 'QuantityOnHand', $xero_item ) ) {
			$xero_line_items[ $xero_item['Code'] ]['QuantityOnHand'] = isset( $xero_item['QuantityOnHand'] ) ? (int) $xero_item['QuantityOnHand'] : 0;
			$xero_line_items[ $xero_item['Code'] ]['Description']    = $xero_item['Description'];
			$xero_line_items[ $xero_item['Code'] ]['UnitPrice']      = $xero_item['PurchaseDetails']['UnitPrice'];
			$xero_line_items[ $xero_item['Code'] ]['TotalCostPool']  = $xero_item['TotalCostPool'];
		}
	}
}

/**
 * Parse Woo Products Data
 */
$item_action          = '';
$line_items           = array();
$line_items_no_update = array();
$api_items            = $api_items_inc = array();
$new_item_stock       = array();
$total_stock_value    = $decrease_total_value = 0;
$saved_list           = get_xero_option( 'xeroom_blacklisted_sku' );

$woo_products = array_values(array_reduce($woo_products, function ($carry, $product) {
    $sku = $product['product_sku'];
    if (!isset($carry[$sku])) {
        $carry[$sku] = $product;
    }
    return $carry;
}, []));

if ( ! empty( $woo_products ) ) {
	$no = 0;
	foreach ( $woo_products as $woo_product ) {
		$new_quantity = null;
		$product_sku  = xeroom_reduce_sku_length( $woo_product['product_sku'] );

		$product_id_sent = isset( $woo_product['parent_id'] ) ? $woo_product['parent_id'] : $woo_product['product_id'];
		update_post_meta( absint( $product_id_sent ), 'wpr_stock_updated', 1 );

		if ( $saved_list && in_array( $product_sku, $saved_list ) ) {
			continue;
		}

		$product_id    = $woo_product['product_id'];
		$product_stock = (int) $woo_product['product_stock'];
		if ( isset( $xero_line_items[ $product_sku ]['TotalCostPool'] ) ) {
			$total_stock_value = $xero_line_items[ $product_sku ]['TotalCostPool'];
		}

		$xero_stock = '';
		if ( isset( $xero_line_items[ $product_sku ]['QuantityOnHand'] ) && ! empty( $xero_line_items[ $product_sku ]['QuantityOnHand'] ) && $xero_line_items[ $product_sku ]['QuantityOnHand'] >= 0 ) {
			$xero_stock = (int) $xero_line_items[ $product_sku ]['QuantityOnHand'];
		}

		if ( $xero_stock === 0 ) {
			$api_items[] = [
				'no'     => ++$no,
				'sku'    => $product_sku ?: __( 'Product ID ' ) . $product_id,
				'stock'  => '0',
				'status' => 'No change',
			];
			continue;
		}

		if ( is_null( $xero_stock ) ) {
			$api_items[] = [
				'no'     => ++ $no,
				'sku'    => $product_sku ?: __( 'Product ID ' ) . $product_id,
				'stock'  => 'Not in Xero',
				'status' => 'Not updated',
			];
			continue;
		}

		if ( $woo_product['managing_stock'] !== 'yes' ) {
			$api_items[] = [
				'no'     => ++ $no,
				'sku'    => $product_sku ?: __( 'Product ID ' ) . $product_id,
				'stock'  => 'None',
				'status' => 'Not tracked in WC',
			];
			continue;
		} else {
            $api_items[] = [
                'no'     => ++$no,
                'sku'    => $product_sku ?: __( 'Product ID ' ) . $product_id,
                'stock'  => $product_stock,
                'status' => 'Updated',
            ];
        }

		if ( is_numeric( $xero_stock ) && (int) $xero_stock != $product_stock ) {
			if ( $product_stock <= 0 ) {
				$new_quantity = $xero_stock;
				if ( $xero_stock > 1 ) {
					$new_quantity = $xero_stock - 1;
				}

				$item_action                    = 'decrease';
				$new_item_stock[ $product_sku ] = 0;
			} else if ( $product_stock > $xero_stock ) {
				$new_quantity                   = $product_stock - $xero_stock;
				$item_action                    = 'increase';
				$new_item_stock[ $product_sku ] = $product_stock;
			} else if ( $product_stock < $xero_stock ) {
				if ( $xero_stock > 1 && $product_stock < 1 ) {
					$xero_stock = $xero_stock - 1;
				}
				$new_quantity                   = $xero_stock - $product_stock;
				$item_action                    = 'decrease';
				$new_item_stock[ $product_sku ] = $product_stock;
			}

			$description_name = $xero_line_items[ $product_sku ]['Description'];
			$unit_price_now   = $xero_line_items[ $product_sku ]['UnitPrice'];

			if ( $total_stock_value > 0 && 'decrease' == $item_action ) {
				$unit_price_now = ( $total_stock_value / $xero_stock );
				$unit_price_now = number_format( $unit_price_now, 2, '.', '' );
			}

			if ( is_nan( $unit_price_now ) ) {
				if ( $woo_product['product_price'] ) {
					$unit_price_now = $woo_product['product_price'];
				} else {
					$unit_price_now = 0;
				}
			}

			$decrease_total_value = $unit_price_now * $new_quantity;

			if ( is_null( $new_quantity ) ) {
				continue;
			}

			if ( empty( $description_name ) ) {
				$description_name = get_the_title( $product_id );
			}

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

			if ( isset( $xero_line_items[ $product_sku ]['QuantityOnHand'] ) ) {
				$line_items[ $item_action ][] = array(
					'ItemCode'                  => $product_sku,
					'Description'               => $description_name,
					'Quantity'                  => $new_quantity,
					'UnitAmount'                => $unit_price_now,
					'InventoryAssetAccountCode' => $asset_code,
					'SalesDetails'              => array(
						"AccountCode" => $salesAccount,
					),
					'PurchaseDetails'           => array(
						"COGSAccountCode" => $SoldCode,
					),
				);
			} else {
				$line_items[ $item_action ][] = array(
					'ItemCode'    => $product_sku,
					'Description' => $description_name,
					'Quantity'    => $new_quantity,
					'UnitAmount'  => $unit_price_now,
					'AccountCode' => $asset_code,
				);
			}
		}
	}
} else {
	xeroom_reset_products_sync();
}

/**
 * Send New Stock to Xero
 */
$black_listed    = array();
$exportErrorXero = '';
if ( ! empty( $line_items ) ) {
	foreach ( $line_items as $type => $items_stock ) {
		if ( 'decrease' == $type ) {
			$decrease_stock = array(
				'CreditNote' => array(
					'Type'            => 'ACCPAYCREDIT',
					'Contact'         => array(
						'Name' => 'Inventory Adjustments',
					),
					'Date'            => date( 'Y-m-d' ),
					'DueDate'         => date( 'Y-m-d' ),
					'LineAmountTypes' => 'NoTax',
					'Status'          => 'AUTHORISED',
					'LineItems'       => array(
						'LineItem' => $items_stock,
					),
				),
			);

			$decItemXero = $xero_api->creditnotes( $decrease_stock );

			if ( ! empty( $decItemXero ) && ! array_key_exists( 'Elements', $decItemXero ) ) {
				foreach ( $woo_products as $woo_product ) {
					update_post_meta( absint( $woo_product['product_id'] ), 'wpr_stock_updated', 1 );
				}

				$it = 1;
				if ( ! empty( $decItemXero['CreditNotes']['CreditNote']['LineItems']['LineItem'] ) ) {
					if ( isset( $decItemXero['CreditNotes']['CreditNote']['LineItems']['LineItem'][0] ) ) {
						foreach ( $decItemXero['CreditNotes']['CreditNote']['LineItems']['LineItem'] as $item ) {
							$api_items[ $item['ItemCode'] ] = array(
								'no'     => $it,
								'sku'    => $item['ItemCode'],
								'stock'  => $new_item_stock[ $item['ItemCode'] ],
								'status' => 'Updated',
							);
							$it ++;
						}
					} else {
						foreach ( $decItemXero['CreditNotes']['CreditNote']['LineItems'] as $item ) {
							$api_items[ $item['ItemCode'] ] = array(
								'no'     => $it,
								'sku'    => $item['ItemCode'],
								'stock'  => $new_item_stock[ $item['ItemCode'] ],
								'status' => 'Updated',
							);
							$it ++;
						}
					}

				}
			} else {
				if ( isset( $decItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
					$errD = $decItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
					returnErrorMessageByXero( 0, $errD, $xeroTime, $errorType );
					$exportErrorXero .= $errD;
				} else if ( isset( $decItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
					$errD = array();
					$errD = $decItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
					for ( $e = 0; $e < count( $errD ); $e ++ ) {
						$errorMessage = $decItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
						returnErrorMessageByXero( 0, $errorMessage, $xeroTime, $errorType );
						$exportErrorXero .= $errorMessage;
					}
				}

				if ( isset( $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem'] ) ) {
					if ( isset( $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][0] ) ) {
						for ( $e = 0; $e < count( $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem'] ); $e ++ ) {
							if ( isset( $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][ $e ]['ValidationErrors'] ) ) {
								array_push( $black_listed, $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][ $e ]['ItemCode'] );
							}
						}
					} else {
						if ( isset( $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem']['ItemCode'] ) ) {
							array_push( $black_listed, $decItemXero['Elements']['DataContractBase']['LineItems']['LineItem']['ItemCode'] );
						}
					}
				}

				$api_items['decrease'] = array(
					'no'     => 0,
					'sku'    => $exportErrorXero,
					'stock'  => '',
					'status' => 'Error on decrease stock',
				);
			}
		} else {
			$increase_stock = array(
				'Invoice' => array(
					'Type'            => 'ACCPAY',
					'Contact'         => array(
						'Name' => 'Inventory Adjustments',
					),
					'Date'            => date( 'Y-m-d' ),
					'DueDate'         => date( 'Y-m-d' ),
					'LineAmountTypes' => 'NoTax',
					'Status'          => 'AUTHORISED',
					'LineItems'       => array(
						'LineItem' => $items_stock,
					),
				),
			);

			$incrItemXero = $xero_api->Invoices( $increase_stock );

			if ( ! empty( $incrItemXero ) && ! array_key_exists( 'Elements', $incrItemXero ) ) {
				foreach ( $woo_products as $woo_product ) {
					update_post_meta( absint( $woo_product['product_id'] ), 'wpr_stock_updated', 1 );
				}

				$in = 1;
				if ( ! empty( $incrItemXero['Invoices']['Invoice']['LineItems']['LineItem'] ) ) {
					if ( isset( $incrItemXero['Invoices']['Invoice']['LineItems']['LineItem'][0] ) ) {
						foreach ( $incrItemXero['Invoices']['Invoice']['LineItems']['LineItem'] as $itemi ) {
							$api_items_inc[ $itemi['ItemCode'] ] = array(
								'no'     => $in,
								'sku'    => $itemi['ItemCode'],
								'stock'  => $new_item_stock[ $itemi['ItemCode'] ],
								'status' => 'Updated',
							);
							$in ++;
						}
					} else {
						foreach ( $incrItemXero['Invoices']['Invoice']['LineItems'] as $itemi ) {
							$api_items_inc[ $itemi['ItemCode'] ] = array(
								'no'     => $in,
								'sku'    => $itemi['ItemCode'],
								'stock'  => $new_item_stock[ $itemi['ItemCode'] ],
								'status' => 'Updated',
							);
							$in ++;
						}
					}

				}
			} else {
				if ( isset( $incrItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'] ) ) {
					$errD = $incrItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError']['Message'];
					returnErrorMessageByXero( 0, $errD, $xeroTime, $errorType );
					$exportErrorXero .= $errD;
				} else if ( isset( $incrItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0] ) ) {
					$errD = array();
					$errD = $incrItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][0];
					for ( $e = 0; $e < count( $errD ); $e ++ ) {
						$errorMessage = $incrItemXero['Elements']['DataContractBase']['ValidationErrors']['ValidationError'][ $e ]['Message'];
						returnErrorMessageByXero( 0, $errorMessage, $xeroTime, $errorType );
						$exportErrorXero .= $errorMessage;
					}
				}

				if ( isset( $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem'] ) ) {
					if ( isset( $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][0] ) ) {
						for ( $e = 0; $e < count( $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem'] ); $e ++ ) {
							if ( isset( $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][ $e ]['ValidationErrors'] ) ) {
								array_push( $black_listed, $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem'][ $e ]['ItemCode'] );
							}
						}
					} else {
						if ( isset( $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem']['ItemCode'] ) ) {
							array_push( $black_listed, $incrItemXero['Elements']['DataContractBase']['LineItems']['LineItem']['ItemCode'] );
						}
					}
				}

				$api_items_inc['increase'] = array(
					'no'     => 0,
					'sku'    => $exportErrorXero,
					'stock'  => '',
					'status' => 'Error on increase stock',
				);
			}
		}
	}

	if ( $black_listed ) {
		if ( $saved_list ) {
			$black_listed = array_merge( $saved_list, $black_listed );
		}

		update_xero_option( 'xeroom_blacklisted_sku', $black_listed );
	}
}

xeroom_generate_sync_log( $api_items + $api_items_inc + $line_items_no_update, 'woo-to-xero' );
