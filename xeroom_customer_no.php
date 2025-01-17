<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Xeroom_Customer_No' ) ) {
	/**
	 * Class Xeroom_Customer_No
	 */
	class Xeroom_Customer_No {
		/**
		 * Xeroom_Customer_No constructor.
		 */
		public function __construct() {
			add_action( 'show_user_profile', array( $this, 'xeroom_add_xero_customer_number' ), 10, 1 );
			add_action( 'edit_user_profile', array( $this, 'xeroom_add_xero_customer_number' ), 10, 1 );
			add_action( 'personal_options_update', array( $this, 'xeroom_save_customer_number' ), 10, 1 );
			add_action( 'edit_user_profile_update', array( $this, 'xeroom_save_customer_number' ), 10, 1 );
		}

		/**
		 * Add the extra field
		 *
		 * @param $user
		 */
		public function xeroom_add_xero_customer_number( $user ) { ?>
            <h3><?php esc_html_e( 'Xero Customer No', 'xeroom' ); ?></h3>

            <table class="form-table">
                <tr>
                    <th><label for="xero_customer_no"><?php esc_html_e( 'Xero Customer Number', 'xeroom' ); ?></label></th>
                    <td>
                        <input type="text" name="xero_customer_no" id="xero_customer_no" value="<?php echo esc_attr( get_the_author_meta( 'xero_customer_no', $user->ID ) ); ?>" class="regular-text"/><br/>
                        <span class="description"><?php esc_html_e( 'Xero Customer Number', 'xeroom' ); ?></span>
                    </td>
                </tr>
            </table>
			<?php
		}

		/**
		 * Save the Xero Customer Number
		 *
		 * @param $user_id
		 *
		 * @return false|void
		 */
		public function xeroom_save_customer_number( $user_id ) {
			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return false;
			}

			update_user_meta( $user_id, 'xero_customer_no', $_POST['xero_customer_no'] );
		}
	}
}

$xeroom_customer_no = new Xeroom_Customer_No();
