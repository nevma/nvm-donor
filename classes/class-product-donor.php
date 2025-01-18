<?php
/**
 * Custom WooCommerce Product Type: Donor
 */

namespace Nvm\Donor;

use WC_Product_Simple;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access not allowed.' );
}

class Product_Donor extends WC_Product_Simple {

	/**
	 * Constructor for the Donor Product.
	 *
	 * @param mixed $product Product ID or object.
	 */
	public function __construct( $product = 0 ) {
		parent::__construct( $product );
		$this->product_type = 'donor';
	}

	/**
	 * Initialize hooks for the Donor Product.
	 */
	public static function init() {
		add_filter( 'product_type_selector', array( __CLASS__, 'register_donor_product_type' ) );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'load_donor_product_class' ), 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'donor_product_tabs' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'add_minimum_donor_amount_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_minimum_donor_amount_field' ) );
	}

	/**
	 * Return the product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'donor';
	}

	public function is_virtual() {
		return true;
	}

	public function is_downloadable() {
		return false;
	}

	public static function register_donor_product_type( $types ) {
		$types['donor'] = __( 'Donor Product', 'nvm-donor' );
		return $types;
	}

	public static function load_donor_product_class( $class_name, $product_type ) {
		if ( 'donor' === $product_type ) {
			$class_name = __CLASS__;
		}
		return $class_name;
	}

	public static function donor_product_tabs( $tabs ) {
		$tabs['inventory']['class'][] = 'show_if_donor';
		return $tabs;
	}

	public static function add_minimum_donor_amount_field() {
		echo '<div class="options_group show_if_donor">';

		echo '<h4>' . 'Επιλογές για τις Δωρεές' . '</h4>';
		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_first_price',
				'label'    => __( 'Τιμή Α', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_second_price',
				'label'    => __( 'Τιμή B', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_third_price',
				'label'    => __( 'Τιμή Γ', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_fourth_price',
				'label'    => __( 'Τιμή D', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		// Donor Message Textarea Field
		woocommerce_wp_textarea_input(
			array(
				'id'          => '_donor_message',
				'label'       => __( 'Κείμενα μετά την Δωρεά', 'nvm-donor' ),
				'description' => __( 'Optional message from the donor.', 'nvm-donor' ),
				'desc_tip'    => true,
				'placeholder' => __( 'Enter a message for the donation...', 'nvm-donor' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_donor_minimum_amount',
				'label'             => __( 'Ελάχιστο ποσό Δωρεάς', 'nvm-donor' ),
				'description'       => __( 'Set the minimum donation amount.', 'nvm-donor' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		echo '</div>';
	}

	public static function save_minimum_donor_amount_field( $post_id ) {

		// Prevent saving during autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$array = array(
			'_donor_first_price',
			'_donor_second_price',
			'_donor_third_price',
			'_donor_fourth_price',
			'_donor_message',
			'_donor_minimum_amount',
		);

		foreach ( $array as $meta ) {
			$meta_field = isset( $_POST[ $meta ] ) ? wc_clean( $_POST[ $meta ] ) : '';
			if ( ! empty( $meta_field ) ) {
				update_post_meta( $post_id, $meta, $meta_field );
			} else {
				delete_post_meta( $post_id, $meta ); // Optional: clean up empty fields.
			}
		}
	}


	/**
	 * Get donor message.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_donor_message( $product ) {

		if ( ! $product ) {
			return '';
		}
		return $product->get_meta( '_donor_message' );
	}

	/**
	 * Get Mimnimum Amount.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_donor_minimum_amount( $product_id ) {
		$product = \wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}
		return $product->get_meta( '_donor_minimum_amount' );
	}

	/**
	 * Get Donor Prices.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function get_donor_prices( $product ) {

		if ( ! $product ) {
			return '';
		}
		$array_price = array(
			$product->get_meta( '_donor_first_price' ),
			$product->get_meta( '_donor_second_price' ),
			$product->get_meta( '_donor_third_price' ),
			$product->get_meta( '_donor_fourth_price' ),
		);

		return $array_price;
	}

	/**
	 * Check if product is donor type.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_donor_product( $product ) {
		if ( ! $product ) {
			return false;
		}
		return 'donor' === $product->get_type();
	}
}

// Initialize hooks.
add_action( 'init', array( 'Nvm\Donor\Product_Donor', 'init' ) );
