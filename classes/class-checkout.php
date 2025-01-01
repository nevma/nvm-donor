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

		// add_filter( 'default_option_woocommerce_checkout_phone_field', array( $this, 'remove_fields' ), 20, 1 );

		// add_filter( 'woocommerce_get_country_locale', array( $this, 'remove_fields' ), 10, 1 );

		add_action( 'template_redirect', array( $this, 'initiate_checkout_actions' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_timologio_fields' ), 10, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'timologio_checkout_field_update_order_meta' ) );
	}

	public function remove_fields( $locale ) {

		foreach ( $locale as $key => $value ) {

			$locale[ $key ]['address_1'] = array(
				'required' => false,
				'hidden'   => true,
			);

			$locale[ $key ]['postcode'] = array(
				'required' => false,
				'hidden'   => true,
			);

			$locale[ $key ]['city'] = array(
				'required' => false,
				'hidden'   => true,
			);

			$locale[ $key ]['phone'] = array(
				'required' => false,
				'hidden'   => true,
			);
		}
			return $locale;
	}

	/**
	 * Initialize actions for the checkout page.
	 */
	public function initiate_checkout_actions() {

		// if ( is_checkout() && $this->initiate_redirect_template() ) {

			// add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 20 );

			add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_woocommerce_billing_fields' ), 30 );

			// remove Coupon if had donor product
			add_filter( 'woocommerce_coupons_enabled', array( $this, 'remove_coupon_code_field_cart' ), 10 );

			add_action( 'woocommerce_checkout_process', array( $this, 'timologio_process' ) );

		// }
	}

	public function remove_coupon_code_field_cart() {
		return false;
	}

	public function customize_checkout_fields( $fields ) {

		$fields['billing']['billing_email']['priority']    = 35;
		$fields['billing']['billing_postcode']['priority'] = 75;
		$fields['billing']['billing_country']['priority']  = 76;

		$fields['billing']['billing_postcode']['class'][0] = 'form-row-first';

		// $fields['billing']['billing_postcode']['clear']   = true;
		$fields['billing']['billing_country']['class'][0] = 'form-row-last';

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
		// $fields['billing'] = array_merge( $new_fields, $fields['billing'] );

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
			echo '<p><strong>Ε��ων��μία Ετ��ιρίας:</strong> ' . get_post_meta( $order->id, '_billing_company', true ) . '</p>';
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
}
