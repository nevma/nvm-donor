<?php //phpcs:ignore - \r\n issue

/**
 * Set namespace.
 */
namespace Nvm\Donor;

use Nvm\Donor\Product as Nvm_Product;

/**
 * Check that the file is not accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Admin_Menu.
 */
class Checkout {

	public function __construct() {

		add_action( 'template_redirect', array( $this, 'initiate_checkout_actions' ) );
	}

	/**
	 * Initialize actions for the checkout page.
	 */
	public function initiate_checkout_actions() {

		if ( is_checkout() && $this->initiate_redirect_template() ) {
			add_filter( 'woocommerce_locate_template', array( $this, 'redirect_wc_template' ), 10, 3 );

			add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_donors_ways' ) );

			add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 10 );

			// remove Coupon if had donor product
			add_filter( 'woocommerce_coupons_enabled', array( $this, 'remove_coupon_code_field_cart' ), 10 );
		}
	}

	public function remove_coupon_code_field_cart() {
		return false;
	}

	public function add_donors_ways() {

		$chosen = WC()->session->get( 'donation' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'donation' ) : $chosen;

		$options = array(
			'individual' => __( 'ΑΤΟΜΙΚΗ', 'nevma' ),
			'corporate'  => __( 'ΕΤΑΙΡΙΚΗ', 'nevma' ),
			'memoriam'   => __( 'ΕΙΣ ΜΝΗΜΗ', 'nevma' ),
		);

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide', 'donation-type' ),
			'options' => $options,
			'default' => array_key_first( $options ),
		);

		echo '<div id="donation-choices">';
		echo 'Παρακαλούμε επιλέξτε το είδος της Δωρεάς';
		woocommerce_form_field( 'type_of_donation', $args, $chosen );
		echo '</div>';

		?>

		<style>
			.woocommerce form .form-row label,
			.woocommerce-page form .form-row label {
				display: inline-block;
			}
		</style>

	<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function () {
			const orderTypeRadios = document.querySelectorAll('input[name="type_of_donation"]');

			function updateDisplay() {
				const selectedValue = document.querySelector('input[name="type_of_donation"]:checked').value;
				const displayStyle = selectedValue === 'timologio' ? 'block' : 'none';
				document.querySelectorAll('.timologio').forEach(el => el.style.display = displayStyle);
			}

			// Check initially before any clicks
			updateDisplay();

			// Update display on radio button change
			orderTypeRadios.forEach(radio => radio.addEventListener('click', updateDisplay));
		});
	</script>
		<?php
	}

	public function customize_checkout_fields( $fields ) {

			unset( $fields['billing']['billing_company'] );
			unset( $fields['billing']['billing_address_1'] );
			unset( $fields['billing']['billing_address_2'] );
			unset( $fields['billing']['billing_postcode'] );
			unset( $fields['billing']['billing_state'] );

		return $fields;
	}
	public function initiate_redirect_template() {

		$nvm_product = new Nvm_Product();

		$has_donor_product = false;
		$target_product_id = $nvm_product->get_donor_product();

		foreach ( \WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && $cart_item['data']->get_id() === $target_product_id ) {
				$has_donor_product = true;
				break;
			}
		}

		// If the cart contains the "donor" product, remove specific billing fields
		if ( $has_donor_product ) {
			return true;
		}
		return false;
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
			$template = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'woocommerce/checkout/form-checkout.php';
		} elseif ( 'payment.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'woocommerce/checkout/payment.php';
		} elseif ( 'review-order.php' === basename( $template ) ) {
			$template = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'woocommerce/checkout/review-order.php';
		}

		return $template;
	}
}
