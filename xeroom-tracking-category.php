<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Xeroom_Tracking_Category' ) ) {
	/**
	 * Class Xeroom_Tracking_Category
	 */
	class Xeroom_Tracking_Category {
		/**
		 * Xeroom_Tracking_Category constructor.
		 */
		public function __construct() {
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'xeroom_add_product_new_tab' ), 10, 1 );
			add_action( 'woocommerce_product_data_panels', array( $this, 'xeroom_options_product_tab_content' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_tracking_option_field' ), 10, 1 );
			add_action( 'admin_head', array( $this, 'xeroom_custom_style' ) );
		}

		/**
		 * Add a bit of style.
		 */
		function xeroom_custom_style() {

			?>
            <style>
                #woocommerce-product-data ul.wc-tabs li.tracking_options a:before {
                    font-family: WooCommerce;
                    content: '\e010';
                }
            </style><?php

		}

		/**
		 * Add the Tracking Tab
		 *
		 * @param $tabs
		 *
		 * @return mixed
		 */
		public function xeroom_add_product_new_tab( $tabs ) {
			$tabs['tracking'] = array(
				'label'  => __( 'Xero Account Settings', 'xeroom' ),
				'target' => 'tracking_options',
				'class'  => array( 'show_if_simple', 'show_if_variable' ),
			);

			return $tabs;
		}

		/**
		 * Add The Tracking Field
		 */
		public function xeroom_options_product_tab_content() {
            global $post;
            $xero_default_accounts = get_xero_option( 'xero_default_accounts' );
			?>
            <div id='tracking_options' class='panel woocommerce_options_panel'>
                <div class='options_group'>
					<?php
					if ( ! empty( $this->xero_tracking_categories() ) ) {
						foreach ( $this->xero_tracking_categories() as $name => $entries ) {
							woocommerce_wp_select(
								array(
									'id'          => '_tracking_category_' . str_replace( ' ', '_', $name ),
									'label'       => esc_html( $name ),
									'desc_tip'    => 'true',
									'description' => __( 'Select the Xero Tracking Category for ' . $name . '.', 'xeroom' ),
									'options'     => $entries,
								)
							);
						}
					}
					?>
                </div>
                <div class="product-account">
                    <p class=" form-field _product-account_field">
                        <label for="xero-product-account"><?php echo esc_html__( 'Sales Account', 'xeroom' ); ?></label>
						<?php
						$product_account = get_post_meta( $post->ID, 'xerrom_product_account', true );
						?>
                        <select class="select short" name="xero-product-account" id="xero-product-account">
		                    <?php
		                    if ( $xero_default_accounts ) {
                                echo sprintf( '<option value="%s">%s</option>', '', esc_html__( 'Select Category' ) );
			                    foreach ( $xero_default_accounts as $sale_account ) {
				                    if ( 'REVENUE' !== $sale_account['Type'] && 'CURRLIAB' !== $sale_account['Type'] && 'SALES' !== $sale_account['Type'] ) {
					                    continue;
				                    }
				                    $selected = '';
				                    if ( $sale_account['Code'] == $product_account ) {
					                    $selected = ' selected';
				                    }
				                    echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
			                    }
		                    } else {
			                    echo sprintf( '<option value="%s">%s</option>', $product_account, $product_account );
		                    }
		                    ?>
                        </select>
                    </p>
                    <p class=" form-field _cost-account_field">
                        <label for="xero-cost-account"><?php echo esc_html__( 'Cost of Goods Sold Account', 'xeroom' ); ?></label>
						<?php
						$cost_account = get_post_meta( $post->ID, 'xerrom_cost_account', true );
						?>
                        <select class="select short" name="xero-cost-account" id="xero-cost-account">
		                    <?php
		                    if ( $xero_default_accounts ) {
                                echo sprintf( '<option value="%s">%s</option>', '', esc_html__( 'Select Category' ) );
			                    foreach ( $xero_default_accounts as $sale_account ) {
				                    if ( 'DIRECTCOSTS' !== $sale_account['Type'] ) {
					                    continue;
				                    }
				                    $selected = '';
				                    if ( $sale_account['Code'] == $cost_account ) {
					                    $selected = ' selected';
				                    }
				                    echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
			                    }
		                    } else {
			                    echo sprintf( '<option value="%s">%s</option>', $cost_account, $cost_account );
		                    }
		                    ?>
                        </select>
                    </p>
                    <p class=" form-field _inventory-account_field">
                        <label for="xero-inventory-account"><?php echo esc_html__( 'Inventory Asset Account', 'xeroom' ); ?></label>
						<?php
						$inventory_account = get_post_meta( $post->ID, 'xerrom_inventory_account', true );
						?>
                        <select class="select short" name="xero-inventory-account" id="xero-inventory-account">
		                    <?php
		                    if ( $xero_default_accounts ) {
                                echo sprintf( '<option value="%s">%s</option>', '', esc_html__( 'Select Category' ) );
			                    foreach ( $xero_default_accounts as $sale_account ) {
				                    if ( 'INVENTORY' !== $sale_account['Type'] ) {
					                    continue;
				                    }
				                    $selected = '';
				                    if ( $sale_account['Code'] == $inventory_account ) {
					                    $selected = ' selected';
				                    }
				                    echo sprintf( '<option value="%s"%s>%s</option>', $sale_account['Code'], $selected, $sale_account['Code'] . ' - ' . $sale_account['Name'] );
			                    }
		                    } else {
			                    echo sprintf( '<option value="%s">%s</option>', $inventory_account, $inventory_account );
		                    }
		                    ?>
                        </select>
                    </p>
                    <p class=" form-field _synch_with_xero_field">
                        <label for="xero-synch"><?php echo esc_html__( 'Synch with Xero', 'xeroom' ); ?></label>
		                <?php
		                $xerrom_synch_with_xero = get_post_meta( $post->ID, 'xerrom_synch_with_xero', true );
		                ?>
                        <input type="checkbox" id="xero-synch" name="synch_with_xero" value="1"  <?php checked( '1', $xerrom_synch_with_xero ); ?> />
                    </p>
                </div>
            </div>
			<?php
		}

		/**
		 * Save the Tracking Cat
		 *
		 * @param $post_id
		 */
		public function save_tracking_option_field( $post_id ) {
			foreach ( $_POST as $key => $value ) {
				if ( 0 === strpos( $key, '_tracking_category' ) ) {
                    $key = str_replace( ' ', '_', $key );
					update_post_meta( $post_id, $key, esc_attr( $value ) );
				}
                
				if ( 'xero-product-account' === $key ) {
					update_post_meta( $post_id, 'xerrom_product_account', esc_attr( $value ) );
				}

				if ( 'xero-inventory-account' === $key ) {
					update_post_meta( $post_id, 'xerrom_inventory_account', esc_attr( $value ) );
				}

				if ( 'xero-cost-account' === $key ) {
					update_post_meta( $post_id, 'xerrom_cost_account', esc_attr( $value ) );
				}
                
				if ( isset( $_POST['synch_with_xero'] ) ) {
					update_post_meta( $post_id, 'xerrom_synch_with_xero', 1 );
				} else {
					update_post_meta( $post_id, 'xerrom_synch_with_xero', 0 );
				}
			}
		}

		/**
		 * Get the existing Xero Categories
		 */
		public function xero_tracking_categories() {
			$saved_categories = get_xero_option( 'xero_tracking_categories' );
			$output           = array();
			if ( $saved_categories ) {
				foreach ( $saved_categories as $key => $categories ) {
					$track = array( '' => esc_html__( 'Select Category' ) );
					foreach ( $categories as $index => $category ) {
						$track[ $category ] = $category;
					}
					$output[ $key ] = $track;
				}
			}

			return $output;
		}
	}
}

new Xeroom_Tracking_Category();
