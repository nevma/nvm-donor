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
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_timologio_fields' ), 10, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'timologio_checkout_field_update_order_meta' ) );
	}

	/**
	 * Initialize actions for the checkout page.
	 */
	public function initiate_checkout_actions() {

		if ( is_checkout() && $this->initiate_redirect_template() ) {
			add_filter( 'woocommerce_locate_template', array( $this, 'redirect_wc_template' ), 10, 3 );

			add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_donors_ways' ) );

			add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 20 );

			add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_woocommerce_billing_fields' ), 30 );

			// remove Coupon if had donor product
			add_filter( 'woocommerce_coupons_enabled', array( $this, 'remove_coupon_code_field_cart' ), 10 );

			add_action( 'woocommerce_checkout_process', array( $this, 'timologio_process' ) );

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
				const donationRadios = document.querySelectorAll('input[name="type_of_donation"]');

				function updateDisplay() {
					// Get the selected donation type
					const selectedValue = document.querySelector('input[name="type_of_donation"]:checked').value;

					// Hide all donation-specific elements
					document.querySelectorAll('.donation-section').forEach(el => el.style.display = 'none');

					// Show all elements that match the selected type
					document.querySelectorAll(`.donation-${selectedValue}`).forEach(el => el.style.display = 'block');
				}

				// Check initially before any clicks
				updateDisplay();

				// Update display on radio button change
				donationRadios.forEach(radio => radio.addEventListener('click', updateDisplay));
			});
		</script>
		<?php
	}


	public function customize_checkout_fields( $fields ) {

		$fields['billing']['billing_email']['priority'] = 35;
		// $fields['billing']['billing_phone']['priority'] = 38;

		$fields['billing']['billing_country']['priority']  = 75;
		$fields['billing']['billing_postcode']['priority'] = 66;

		$fields['billing']['billing_country']['class'][0]  = 'form-row-first';
		$fields['billing']['billing_postcode']['class'][0] = 'form-row-last';

		unset( $fields['billing']['billing_company'] );
		unset( $fields['billing']['billing_address_2'] );
		unset( $fields['billing']['billing_state'] );

		return $fields;
	}


	/**
	 * Customizes WooCommerce billing fields.
	 *
	 * @param array $fields The existing billing fields.
	 * @return array Modified billing fields.
	 */
	public function custom_woocommerce_billing_fields( $fields ) {

		// Define new fields.
		$new_fields = array(
			'billing_tax_office'  => array(
				'label'    => __( 'ΔΟΥ *', 'nevma' ),
				'required' => false,
				'clear'    => false,
				'type'     => 'text',
				'class'    => array( 'donation-section', 'form-row-wide', 'donation-corporate', 'donation-memoriam' ),
			),
			'billing_company_nvm' => array(
				'label'    => __( 'Επωνυμία Εταιρίας *', 'nevma' ),
				'required' => false,
				'clear'    => false,
				'type'     => 'text',
				'class'    => array( 'donation-section', 'form-row-wide', 'donation-corporate', 'donation-memoriam' ),
			),
			'billing_activity'    => array(
				'label'    => __( 'Δραστηριότητα *', 'nevma' ),
				'required' => false,
				'clear'    => false,
				'type'     => 'text',
				'class'    => array( 'donation-section', 'form-row-last', 'donation-corporate', 'donation-memoriam' ),
			),
			'billing_vat_id'      => array(
				'label'    => __( 'ΑΦΜ *', 'nevma' ),
				'required' => false,
				'clear'    => false,
				'type'     => 'text',
				'class'    => array( 'donation-section', 'form-row-first', 'donation-corporate', 'donation-memoriam' ),
			),
			'billing_person_gone' => array(
				'label'    => __( 'ΑΦΜ *', 'nevma' ),
				'required' => false,
				'clear'    => false,
				'type'     => 'text',
				'class'    => array( 'donation-section', 'form-row-first', 'donation-corporate', 'donation-memoriam' ),
			),
		);

		// Merge new fields with existing billing fields.
		$fields['billing'] = array_merge( $new_fields, $fields['billing'] );

		return $fields;
	}



	/**
	 *
	 * This file contains the 'timologio_process' function, which is hooked to the 'woocommerce_checkout_process' action.
	 * The function is responsible for validating and processing orders with the 'timologio' type.
	 * It checks if the required fields for 'timologio' orders are filled in and displays error notices if any field is missing.
	 * It also checks if the shipping method is 'wc_pickup_store' and displays an error notice if the pickup store is not selected.
	 *
	 * @since 1.0.0
	 */
	public function timologio_process() {
		global $woocommerce;

		if ( $_POST['type_of_order'] == 'timologio' ) {
			if ( $_POST['billing_vat_id'] == '' ) {
				wc_add_notice( __( 'Συμπληρώστε το πεδίο ΑΦΜ' ), 'error' );
			}
			if ( $_POST['billing_activity'] == '' ) {
				wc_add_notice( __( 'Συμπληρώστε την Δραστηριότητα' ), 'error' );
			}
			if ( $_POST['billing_company-nvm'] == '' ) {
				wc_add_notice( __( 'Συμπληρώστε το πεδίο Επωνυμία εταιρίας' ), 'error' );
			}
		}
	}



	public function timologio_checkout_field_update_order_meta( $order_id ) {

		update_post_meta( $order_id, '_billing_company', esc_attr( $_POST['billing_company-nvm'] ) );
		update_post_meta( $order_id, '_type_of_order', esc_attr( $_POST['type_of_order'] ) );
	}

	public function show_timologio_fields( $order ) {
		if ( get_post_meta( $order->id, '_billing_vat_id', true ) != '' ) {
			echo '<p><strong>AFM:</strong> ' . get_post_meta( $order->id, '_billing_vat_id', true ) . '</p>';
			echo '<p><strong>Δραστηριότητα:</strong> ' . get_post_meta( $order->id, '_billing_activity', true ) . '</p>';
			echo '<p><strong>Επωνυμία Εταιρίας:</strong> ' . get_post_meta( $order->id, '_billing_company', true ) . '</p>';
		}
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
