<?php
/**
 * This file belongs to the YIT Framework.
 *
 * This source file is subject to the GNU GENERAL PUBLIC LICENSE (GPL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @package YITH\PreOrder\Includes
 * @author YITH <plugins@yithemes.com>
 */

if ( ! defined( 'YITH_WCPO_VERSION' ) ) {
	exit( 'Direct access forbidden.' );
}

if ( ! class_exists( 'YITH_Pre_Order_Edit_Product_Page' ) ) {
	/**
	 * Class YITH_Pre_Order_Edit_Product_Page
	 */
	class YITH_Pre_Order_Edit_Product_Page {

		/**
		 * Main Instance
		 *
		 * @var YITH_Pre_Order_Edit_Product_Page
		 * @since  1.0
		 * @access public
		 */
		public static $instance = null;

		/**
		 * Returns single instance of the class
		 *
		 * @return YITH_Pre_Order_Edit_Product_Page
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Construct
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'pre_order_tab' ), 5 );
			add_action( 'woocommerce_product_data_panels', array( $this, 'pre_order_tab_content' ) );
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'pre_order_variation_content' ), 10, 3 );
			add_action( 'woocommerce_process_product_meta', array( $this, 'update_product_post_meta' ) );
			add_action( 'woocommerce_save_product_variation', array( $this, 'update_variation_post_meta' ), 10, 2 );
			add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_item_meta_data' ), 10, 2 );
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_order_item_meta' ) );
		}

		/**
		 * Enqueue the scripts for Edit product page.
		 */
		public function enqueue_scripts() {
			$current_screen = get_current_screen();

			wp_enqueue_script( 'yith-plugin-fw-fields' );
			wp_enqueue_style( 'yith-plugin-fw-fields' );

			if ( 'product' === $current_screen->id || 'edit-shop_order' === $current_screen->id || 'edit-product' === $current_screen->id ) {
				wp_enqueue_style( 'ywpo-edit-product', YITH_WCPO_ASSETS_URL . 'css/ywpo-edit-product.css', array(), YITH_WCPO_VERSION );
				wp_enqueue_script( 'yith-wcpo-edit-product-page', YITH_WCPO_ASSETS_JS_URL . yit_load_js_file( 'edit-product-page.js' ), array( 'jquery' ), YITH_WCPO_VERSION, 'true' );
				wp_enqueue_script( 'jquery-ui-datetimepicker', YITH_WCPO_ASSETS_JS_URL . yit_load_js_file( 'timepicker.js' ), array( 'jquery' ), YITH_WCPO_VERSION, 'true' );
				wp_enqueue_style( 'jquery-ui-datetimepicker-style', YITH_WCPO_ASSETS_URL . 'css/timepicker.css', array(), YITH_WCPO_VERSION );
			}
		}

		/**
		 * Add the Pre-order settings tab in the product data box.
		 *
		 * @param array $product_data_tabs Edit product page tabs.
		 *
		 * @return array product_data_tabs
		 */
		public function pre_order_tab( $product_data_tabs ) {
			$preorder_tab = array(
				'preorder' => array(
					'label'  => esc_html__( 'Pre-Order', 'yith-pre-order-for-woocommerce' ),
					'target' => 'preorder_product_data',
					'class'  => array( 'show_if_simple' ),
				),
			);

			return array_merge( $product_data_tabs, $preorder_tab );
		}

		/**
		 * Call the template for the settings for the "Pre-Order" tab (Edit product page).
		 */
		public function pre_order_tab_content() {
			global $thepostid;
			$args = self::get_pre_order_post_meta( $thepostid );

			wc_get_template( 'admin/ywpo-edit-product-pre-order-tab.php', $args, '', YITH_WCPO_TEMPLATE_PATH . '/' );
		}

		/**
		 * Call the template for the settings for the "Pre-order options" section inside the "Variations" tab (Edit product page).
		 *
		 * @param int     $loop           Position in the loop.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post $variation      Post data.
		 */
		public function pre_order_variation_content( $loop, $variation_data, $variation ) {
			$args         = self::get_pre_order_post_meta( $variation->ID );
			$args['loop'] = $loop;

			wc_get_template( 'admin/ywpo-edit-product-pre-order-variation.php', $args, '', YITH_WCPO_TEMPLATE_PATH . '/' );
		}

		/**
		 * Collect the pre-order post meta for the edit product page.
		 *
		 * @param string|int $id The product's ID.
		 * @return array
		 */
		private static function get_pre_order_post_meta( $id ) {
			$product   = wc_get_product( $id );

			$pre_order_status = 'yes' === $product->get_meta( '_ywpo_preorder' ) ? 'yes' : 'no';

			// Backward compatibility.
			if ( ! metadata_exists( 'post', $id, '_ywpo_availability_date_mode' ) && metadata_exists( 'post', $id, '_ywpo_for_sale_date' ) ) {
				$availability_date_mode = 'date';
			} else {
				$availability_date_mode = metadata_exists( 'post', $id, '_ywpo_availability_date_mode' ) ? YITH_Pre_Order_Utils::get_availability_date_mode( $product ) : 'no_date';
			}

			$availability_date = YITH_Pre_Order_Utils::get_for_sale_date( $product );

			$preorder_price = metadata_exists( 'post', $id, '_ywpo_preorder_price' ) ? YITH_Pre_Order_Utils::get_pre_order_price( $product ) : 0;

			// Backward compatibility.
			if ( ! metadata_exists( 'post', $id, '_ywpo_price_mode' ) && metadata_exists( 'post', $id, '_ywpo_preorder_price' ) ) {
				$price_mode          = 'default';
				$discount_percentage = '';
				$discount_fixed      = '';
				$increase_percentage = '';
				$increase_fixed      = '';
				if ( metadata_exists( 'post', $id, '_ywpo_price_adjustment' ) && 'manual' === get_post_meta( $id, '_ywpo_price_adjustment', true ) ) {
					$price_mode = 'fixed';
				} elseif (
					metadata_exists( 'post', $id, '_ywpo_price_adjustment' ) &&
					metadata_exists( 'post', $id, '_ywpo_adjustment_type' ) &&
					metadata_exists( 'post', $id, '_ywpo_price_adjustment_amount' )
				) {
					if (
						'discount' === get_post_meta( $id, '_ywpo_price_adjustment', true ) &&
						'percentage' === get_post_meta( $id, '_ywpo_adjustment_type', true )
					) {
						$price_mode          = 'discount_percentage';
						$discount_percentage = get_post_meta( $id, '_ywpo_price_adjustment_amount', true );
					} elseif (
						'discount' === get_post_meta( $id, '_ywpo_price_adjustment', true ) &&
						'fixed' === get_post_meta( $id, '_ywpo_adjustment_type', true )
					) {
						$price_mode     = 'discount_fixed';
						$discount_fixed = get_post_meta( $id, '_ywpo_price_adjustment_amount', true );
					} elseif (
						'mark-up' === get_post_meta( $id, '_ywpo_price_adjustment', true ) &&
						'percentage' === get_post_meta( $id, '_ywpo_adjustment_type', true )
					) {
						$price_mode          = 'increase_percentage';
						$increase_percentage = get_post_meta( $id, '_ywpo_price_adjustment_amount', true );
					} elseif (
						'mark-up' === get_post_meta( $id, '_ywpo_price_adjustment', true ) &&
						'fixed' === get_post_meta( $id, '_ywpo_adjustment_type', true )
					) {
						$price_mode     = 'increase_fixed';
						$increase_fixed = get_post_meta( $id, '_ywpo_price_adjustment_amount', true );
					}
				}
			} else {
				$price_mode          = metadata_exists( 'post', $id, '_ywpo_price_mode' ) ? YITH_Pre_Order_Utils::get_price_mode( $product ) : 'default';
				$discount_percentage = YITH_Pre_Order_Utils::get_discount_percentage( $product );
				$discount_fixed      = YITH_Pre_Order_Utils::get_discount_fixed( $product );
				$increase_percentage = YITH_Pre_Order_Utils::get_increase_percentage( $product );
				$increase_fixed      = YITH_Pre_Order_Utils::get_increase_fixed( $product );
			}

			return apply_filters(
				'ywpo_get_pre_order_post_meta',
				array(
					'product'                => $product,
					'pre_order_status'       => $pre_order_status,
					'availability_date_mode' => $availability_date_mode,
					'availability_date'      => $availability_date,
					'price_mode'             => $price_mode,
					'preorder_price'         => $preorder_price,
					'discount_percentage'    => $discount_percentage,
					'discount_fixed'         => $discount_fixed,
					'increase_percentage'    => $increase_percentage,
					'increase_fixed'         => $increase_fixed,
				)
			);
		}

		/**
		 * Update the pre-order post meta.
		 *
		 * @param string|int $post_id The post ID.
		 */
		public function update_product_post_meta( $post_id ) {
			// Check the nonce.
			if ( empty( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				return;
			}

			$product = wc_get_product( $post_id );
			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				if ( empty( $children ) ) {
					return;
				}
				$count    = count( $children );
				$enabled  = 0;
				$disabled = 0;
				foreach ( $children as $variation_id ) {
					if ( ! metadata_exists( 'post', $variation_id, '_ywpo_preorder' ) || 'no' === get_post_meta( $variation_id, '_ywpo_preorder', true ) ) {
						$disabled++;
					}
					if ( 'yes' === get_post_meta( $variation_id, '_ywpo_preorder', true ) ) {
						$enabled++;
					}
				}
				if ( $enabled > 0 ) {
					update_post_meta( $post_id, '_ywpo_has_variations', 'yes' );
				} elseif ( metadata_exists( 'post', $post_id, '_ywpo_has_variations' ) && $count === $disabled ) {
					update_post_meta( $post_id, '_ywpo_has_variations', 'no' );
				}
			} else {
				if ( isset( $_POST['product-type'] ) && 'simple' !== $_POST['product-type'] ) {
					return;
				}

				$is_pre_order = ! empty( $_POST['_ywpo_preorder'] ) ? 'yes' : 'no';
				YITH_Pre_Order_Utils::set_pre_order_status( $product, $is_pre_order );

				$availability_date_mode = isset( $_POST['_ywpo_availability_date_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_availability_date_mode'] ) ) : '';
				YITH_Pre_Order_Utils::set_availability_date_mode( $product, $availability_date_mode );

				$new_release_date = (string) isset( $_POST['_ywpo_for_sale_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_for_sale_date'] ) ) : '';
				YITH_Pre_Order_Utils::set_for_sale_date( $product, $new_release_date );

				$price_mode = isset( $_POST['_ywpo_price_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_price_mode'] ) ) : '';
				YITH_Pre_Order_Utils::set_price_mode( $product, $price_mode );

				$pre_order_price = isset( $_POST['_ywpo_preorder_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_price'] ) ) ) : '';
				YITH_Pre_Order_Utils::set_pre_order_price( $product, $pre_order_price );

				$discount_percentage = isset( $_POST['_ywpo_preorder_discount_percentage'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_discount_percentage'] ) ) : '';
				YITH_Pre_Order_Utils::set_discount_percentage( $product, $discount_percentage );
				$discount_fixed = isset( $_POST['_ywpo_preorder_discount_fixed'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_discount_fixed'] ) ) : '';
				YITH_Pre_Order_Utils::set_discount_fixed( $product, $discount_fixed );
				$increase_percentage = isset( $_POST['_ywpo_preorder_increase_percentage'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_increase_percentage'] ) ) : '';
				YITH_Pre_Order_Utils::set_increase_percentage( $product, $increase_percentage );
				$increase_fixed = isset( $_POST['_ywpo_preorder_increase_fixed'] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_increase_fixed'] ) ) : '';
				YITH_Pre_Order_Utils::set_increase_fixed( $product, $increase_fixed );
			}
		}

		/**
		 * This function is executed when the variations post meta are being updated in the Edit product page.
		 *
		 * @param string|int $post_id The post's ID.
		 * @param int        $_i      Iterator.
		 */
		public function update_variation_post_meta( $post_id, $_i ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$product = wc_get_product( $post_id );

			$is_pre_order = ! empty( $_POST['_ywpo_preorder'][ $_i ] ) ? 'yes' : 'no';
			YITH_Pre_Order_Utils::set_pre_order_status( $product, $is_pre_order );

			$availability_date_mode = isset( $_POST['_ywpo_availability_date_mode'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_availability_date_mode'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_availability_date_mode( $product, $availability_date_mode );

			$new_release_date = (string) isset( $_POST['_ywpo_for_sale_date'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_for_sale_date'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_for_sale_date( $product, $new_release_date );

			$price_mode = isset( $_POST['_ywpo_price_mode'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_price_mode'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_price_mode( $product, $price_mode );

			$pre_order_price = isset( $_POST['_ywpo_preorder_price'][ $_i ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_price'][ $_i ] ) ) ) : '';
			YITH_Pre_Order_Utils::set_pre_order_price( $product, $pre_order_price );

			$discount_percentage = isset( $_POST['_ywpo_preorder_discount_percentage'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_discount_percentage'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_discount_percentage( $product, $discount_percentage );
			$discount_fixed = isset( $_POST['_ywpo_preorder_discount_fixed'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_discount_fixed'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_discount_fixed( $product, $discount_fixed );
			$increase_percentage = isset( $_POST['_ywpo_preorder_increase_percentage'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_increase_percentage'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_increase_percentage( $product, $increase_percentage );
			$increase_fixed = isset( $_POST['_ywpo_preorder_increase_fixed'][ $_i ] ) ? sanitize_text_field( wp_unslash( $_POST['_ywpo_preorder_increase_fixed'][ $_i ] ) ) : '';
			YITH_Pre_Order_Utils::set_increase_fixed( $product, $increase_fixed );
		}

		/**
		 * Convert the pre-order item meta into a human-readable format.
		 *
		 * @param array $formatted_meta Item meta array.
		 *
		 * @return array
		 */
		public function format_item_meta_data( $formatted_meta ) {
			foreach ( $formatted_meta as $meta ) {
				if ( '_ywpo_item_preorder' === $meta->key ) {
					$meta->display_key   = __( 'Pre-order item', 'yith-pre-order-for-woocommerce' );
					$meta->display_value = __( 'Yes', 'yith-pre-order-for-woocommerce' );
				}
				if ( '_ywpo_item_for_sale_date' === $meta->key ) {
					$meta->display_key   = __( 'Availability date', 'yith-pre-order-for-woocommerce' );
					$meta->display_value = '<p>' . ywpo_print_datetime( $meta->value ) . '</p>';
				}
			}

			return $formatted_meta;
		}

		/**
		 * Hide the unnecessary pre-order item meta.
		 *
		 * @param array $metas Item meta array.
		 *
		 * @return array
		 */
		public function hidden_order_item_meta( $metas ) {
			$metas[] = '_ywpo_new_pre_order_email_sent';
			$metas[] = '_ywpo_item_status';
			return $metas;
		}
	}
}

/**
 * Unique access to instance of YITH_Pre_Order_Edit_Product_Page class
 *
 * @return YITH_Pre_Order_Edit_Product_Page
 */
function YITH_Pre_Order_Edit_Product_Page() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return YITH_Pre_Order_Edit_Product_Page::get_instance();
}
