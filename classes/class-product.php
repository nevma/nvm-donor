<?php //phpcs:ignore - \r\n issue

/**
 * Set namespace.
 */
namespace Nvm\Donor;

/**
 * Check that the file is not accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}
/**
 * Class Product donor view & settings.
 */
class Product {

	public function __construct() {

		add_action( 'wp_header', array( $this, 'initiate_redirect_template' ) );

		// Change add to cart text on single product page
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_button_text_single' ) );
		// Add Fields to product
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_fields_to_product' ) );
		// Add text field to product
		add_action( 'woocommerce_product_meta_start', array( $this, 'add_content_after_addtocart_button' ), 20 );
		// remove quantity
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'remove_quantity_input_field' ), 10, 2 );

		// Save and calculate costs
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_donation_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_donation_to_order_items' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_product_price_based_on_choice' ) );

		// Redirect to check out after add to cart
		add_action( 'woocommerce_add_to_cart', array( $this, 'redirect_to_checkout_for_specific_product' ), 50, 6 );
	}


	public function initiate_redirect_template() {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$has_donor_product = false;
		$target_product_id = $this->get_donor_product();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $target_product_id ) {
				$has_donor_product = true;
				break;
			}
		}

		// If the cart contains the "donor" product, remove specific billing fields
		if ( $has_donor_product ) {

			add_filter( 'woocommerce_locate_template', array( $this, 'redirect_wc_template' ), 10, 3 );

			// remove coupon field on donor checkout
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 10 );

		}
	}

	public function remove_quantity_input_field( $return, $product ) {

		if ( is_product() ) {

			$target_product_id = $this->get_donor_product();

			if ( $product->get_id() === $target_product_id ) {
				return true;
			}
		}
		return $return;
	}


	public function get_donor_product() {
		if ( class_exists( 'ACF' ) ) {
			$donor_product_id = get_field( 'product', 'options' );

			return $donor_product_id;
		}

		return false;
	}

	public function get_donor_prices() {
		$chosen  = WC()->session->get( 'radio_chosen' );
		$chosen  = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_radio_choice' ) : $chosen;
		$options = array();
		$minimum = 1;

		if ( class_exists( 'ACF' ) ) {
			$minimum = get_field( 'minimun_amount', 'options' );

			$array_donor = get_field( 'donor_prices', 'options' );
			if ( ! empty( $array_donor ) ) {
				foreach ( $array_donor as $donor ) {
					$donor_amount             = $donor['amount'];
					$options[ $donor_amount ] = $donor_amount . '€';
				}
			}
		}

		if ( empty( $options ) ) {
			$options = array(
				'5'  => '5€',
				'10' => '10€',
				'25' => '25€',
				'50' => '50€',
			);
		}

		$options['custom'] = esc_html__( 'Custom Amount', 'nevma' );
		$chosen            = empty( $chosen ) ? array_key_first( $options ) : $chosen;

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide' ),
			'options' => $options,
			'default' => $chosen,
		);

		echo '<div id="donation-choices">';
		woocommerce_form_field( 'nvm_radio_choice', $args, $chosen );
		echo '</div>';

		echo '<div class="donation-fields">';
		woocommerce_form_field(
			'donation_amount',
			array(
				'type'              => 'number',
				'label'             => __( 'Donation Amount (€)', 'nevma' ),
				'required'          => false,
				'class'             => array( 'form-row-wide' ),
				'placeholder'       => __( 'Enter an amount', 'nevma' ),
				'custom_attributes' => array(
					'min' => $minimum,
				),
			)
		);
		echo '<span>' . __( 'Minimun Amount:', 'nevma' ) . ' ' . $minimum . '€</span>';
		echo '</div>';

		wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
	}

	public function add_to_cart_button_text_single() {
		return __( 'Donor Amount', 'nevma' );
	}

	public function add_content_after_addtocart_button() {

		if ( class_exists( 'ACF' ) ) {
			$donor_text = get_field( 'text_after', 'options' );

			echo '<span class="safe">';
			echo $donor_text;
			echo '</span>';
		}
	}


	/**
	 * Add donation fields to the product page.
	 */
	public function add_donation_fields_to_product() {
		global $product;

		// Target specific product for donations.
		$target_product_id = $this->get_donor_product();

		if ( $product->get_id() !== $target_product_id ) {
			return;
		}

		$this->get_donor_prices();
		?>
		<style>
			.woocommerce form .form-row label,
			.woocommerce-page form .form-row label {
				display: inline-block;
			}
		</style>
		<?php
	}

		/**
		 * Save donation data to cart item.
		 *
		 * @param array $cart_item_data Cart item data.
		 * @param int   $product_id Product ID.
		 */
	public function save_donation_data( $cart_item_data, $product_id ) {

		if ( ! isset( $_POST['donation_form_nonce_field'] ) || ! wp_verify_nonce( $_POST['donation_form_nonce_field'], 'donation_form_nonce' ) ) {
			wp_die();
		}

		if ( isset( $_POST['nvm_radio_choice'] ) ) {

			if ( 'custom' === $_POST['nvm_radio_choice'] ) {
				if ( isset( $_POST['donation_amount'] ) && is_numeric( $_POST['donation_amount'] ) ) {
					$cart_item_data['nvm_radio_choice'] = floatval( $_POST['donation_amount'] );
				}
			}

			if ( 'custom' !== $_POST['nvm_radio_choice'] ) {

				$cart_item_data['nvm_radio_choice'] = floatval( sanitize_text_field( $_POST['nvm_radio_choice'] ) );
			}
		}

		return $cart_item_data;
	}

		/**
		 * Adjust product price based on radio choice.
		 *
		 * @param WC_Cart $cart The WooCommerce cart object.
		 */
	public function adjust_product_price_based_on_choice( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! WC()->cart->is_empty() ) {

			foreach ( $cart->get_cart() as $cart_item ) {

				if ( isset( $cart_item['nvm_radio_choice'] ) ) {
					$new_price = $cart_item['nvm_radio_choice']; // The new price from radio choice.
					$cart_item['data']->set_price( $new_price );
				}
			}
		}
	}

		/**
		 * Add donation data to order items.
		 *
		 * @param \WC_Order_Item $item Order item.
		 * @param string         $cart_item_key Cart item key.
		 * @param array          $values Cart item data.
		 * @param \WC_Order      $order Order object.
		 */
	public function add_donation_to_order_items( $item, $cart_item_key, $values, $order ) {

		if ( isset( $values['nvm_radio_choice'] ) ) {

			$item->add_meta_data( __( 'nvm_radio_choice', 'nevma' ), $values['nvm_radio_choice'] );
		}
	}


		/**
		 * Filter the cart template path to use cart.php in this plugin instead of the one in WooCommerce.
		 *
		 * @param string $template      Default template file path.
		 * @param string $template_name Template file slug. @phpcs:ignore
		 * @param string $template_path Template file name.
		 *
		 * @return string The new Template file path.
		 */
	public function redirect_wc_template( $template, $template_name, $template_path ) { // phpcs:ignore WordPress.UnusedFunctionParameter.Found

		if ( 'form-checkout.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/form-checkout.php';
		} elseif ( 'payment.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/payment.php';
		} elseif ( 'review-order.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'woocommerce/checkout/review-order.php';
		}

		return $template;
	}

	public function redirect_to_checkout_for_specific_product( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		// Target specific product for donations.
		$target_product_id = $this->get_donor_product();

		if ( $product_id === $target_product_id ) {
			// Get the checkout URL
			$checkout_url = wc_get_checkout_url();

			// Redirect to the checkout page
			wp_safe_redirect( $checkout_url );
			exit;
		}
	}
}
