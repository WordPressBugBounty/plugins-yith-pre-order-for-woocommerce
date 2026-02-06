<?php
/**
 * This file belongs to the YIT Framework.
 *
 * This source file is subject to the GNU GENERAL PUBLIC LICENSE (GPL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @package YITH\PreOrder\Includes\Emails
 * @author YITH <plugins@yithemes.com>
 */

if ( ! defined( 'YITH_WCPO_VERSION' ) ) {
	exit( 'Direct access forbidden.' );
}

if ( ! class_exists( 'YITH_Pre_Order_Email' ) ) {
	/**
	 * Class YITH_Pre_Order_Email
	 */
	class YITH_Pre_Order_Email extends WC_Email {

		/**
		 * Email content.
		 *
		 * @var string $email_body
		 */
		public $email_body;

		/**
		 * Email additional data.
		 *
		 * @var array $data
		 */
		public $data;

		/**
		 * Get dummy data for Email Preview
		 *
		 * @param bool $release_date_changed Whether the dummy data is for the release date changed email or not.
		 *
		 * @return array
		 * @throws WC_Data_Exception Exception.
		 */
		protected function get_dummy_data( $release_date_changed = false ) {
			$order   = $this->get_dummy_order();
			$product = $this->get_dummy_product();
			$item    = new WC_Order_Item_Product();
			$item_id = $order->add_product( $product );

			$item->set_id( $item_id );
			$item->add_meta_data( '_ywpo_item_for_sale_date', time() + 90000 );
			$item->save_meta_data();

			$product_link = '<a href="' . $product->get_permalink() . '">' . $product->get_title() . '</a>';
			$order_link   = '<a href="' . $order->get_view_order_url() . '">#' . $order->get_id() . '</a>';

			$this->placeholders['{customer_name}'] = $order->get_formatted_billing_full_name();
			$this->placeholders['{product_title}'] = $product->get_title();
			$this->placeholders['{product_link}']  = $product_link;
			$this->placeholders['{order_number}']  = $order->get_order_number();
			$this->placeholders['{order_link}']    = $order_link;

			$this->placeholders['{release_date}']     = ywpo_print_date( $item->get_meta( '_ywpo_item_for_sale_date' ) );
			$this->placeholders['{release_datetime}'] = ywpo_print_datetime( $item->get_meta( '_ywpo_item_for_sale_date' ) );
			$this->placeholders['{offset}']           = ywpo_get_timezone_offset_label();

			$this->email_body = $this->format_string( $this->get_option( 'email_body', $this->email_body ) );

			if ( $release_date_changed ) {
				return array(
					'product'          => $product,
					'old_release_date' => strtotime( '+1 month' ),
					'new_release_date' => strtotime( '+2 month' ),
				);
			}

			return array(
				'order'   => $this->get_dummy_order(),
				'product' => $this->get_dummy_product(),
				'item_id' => $item_id,
			);
		}

		/**
		 * Get a dummy order for Email Preview.
		 *
		 * @return WC_Order
		 * @throws WC_Data_Exception Exception.
		 */
		protected function get_dummy_order() {
			$product = $this->get_dummy_product();
			$order   = new WC_Order();
			if ( $product ) {
				$order->add_product( $product, 2 );
			}
			$order->set_id( 12345 );
			$order->set_date_created( time() );
			$order->set_currency( 'USD' );
			$order->set_discount_total( 10 );
			$order->set_shipping_total( 5 );
			$order->set_total( 65 );
			$order->set_payment_method_title( __( 'Direct bank transfer', 'yith-pre-order-for-woocommerce' ) );
			$order->set_customer_note( __( "This is a customer note. Customers can add a note to their order on checkout.\n\nIt can be multiple lines. If there’s no note, this section is hidden.", 'yith-pre-order-for-woocommerce' ) );

			$address = $this->get_dummy_address();
			$order->set_billing_address( $address );
			$order->set_shipping_address( $address );

			return $order;
		}

		/**
		 * Get a dummy product for Email Preview.
		 *
		 * @param bool $out_of_stock Whether to set the dummy product as out of stock or not.
		 *
		 * @return WC_Product
		 */
		protected function get_dummy_product( $out_of_stock = false ) {
			$product = new WC_Product();
			$product->set_name( __( 'Pre-Order Product', 'yith-pre-order-for-woocommerce' ) );
			$product->set_price( 25 );
			if ( $out_of_stock ) {
				$product->set_stock_status( 'outofstock' );
			}

			return $product;
		}

		/**
		 * Get a dummy address for Email Preview.
		 *
		 * @return array
		 */
		private function get_dummy_address() {
			return array(
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'company'    => 'Company',
				'email'      => 'john@company.com',
				'phone'      => '555-555-5555',
				'address_1'  => '123 Fake Street',
				'city'       => 'Faketown',
				'postcode'   => '12345',
				'country'    => 'US',
				'state'      => 'CA',
			);
		}

	}
}
