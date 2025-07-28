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
	 * Product type.
	 *
	 * @var string
	 */
	protected $product_type = 'donor';


	/**
	 * Constructor for the Donor Product.
	 *
	 * @param mixed $product Product ID or object.
	 */
	public function __construct( $product = 0 ) {

		$this->product_type = 'donor';
		parent::__construct( $product );
		$this::init();
	}

	/**
	 * Initialize hooks for the Donor Product.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'add_minimum_donor_amount_field' ) );
		add_filter( 'product_type_selector', array( __CLASS__, 'register_donor_product_type' ) );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'load_donor_product_class' ), 10, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'donor_product_tabs' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_minimum_donor_amount_field' ) );

		// Restrict cart behavior for donor products.
		// add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'restrict_donor_product_cart' ), 10, 3 );
		// add_action( 'woocommerce_add_cart_item_data', array( __CLASS__, 'ensure_one_donor_product_in_cart' ), 10, 2 );
		// add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'remove_donor_product_from_cart' ), 10, 1 );
	}

	/**
	 * Return the product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'donor';
	}

	/**
	 * Ensure only one donor product can be in cart at a time.
	 *
	 * @return boolean True if cart should be emptied first.
	 */
	public function empty_cart_before_add() {
		return true;
	}

	public function is_virtual() {
		return true;
	}

	public function is_downloadable() {
		return false;
	}

	public function is_purchasable() {
		return true;
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
		global $post;

		echo '<div class="options_group show_if_donor">';

		echo '<h4>' . esc_html__( 'Donation Options', 'nvm-donor' ) . '</h4>';
		echo '[nvm_donor_form product_id=' . esc_attr( $post->ID ) . ']';
		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_first_price',
				'label'    => __( 'Price Α', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_second_price',
				'label'    => __( 'Price B', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_third_price',
				'label'    => __( 'Price Γ', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'       => '_donor_fourth_price',
				'label'    => __( 'Price D', 'nvm-donor' ),
				'type'     => 'number',
				'desc_tip' => true,
			)
		);

		// Donor Message Textarea Field
		woocommerce_wp_textarea_input(
			array(
				'id'          => '_donor_message',
				'label'       => __( 'Texts after the Donation', 'nvm-donor' ),
				'description' => __( 'Optional message from the donor.', 'nvm-donor' ),
				'desc_tip'    => true,
				'placeholder' => __( 'Enter a message for the donation...', 'nvm-donor' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_donor_minimum_amount',
				'label'             => __( 'Minimum Donation Amount', 'nvm-donor' ),
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
	public static function get_donor_minimum_amount( $product ) {

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
			'first_price'  => $product->get_meta( '_donor_first_price' ),
			'second_price' => $product->get_meta( '_donor_second_price' ),
			'third_price'  => $product->get_meta( '_donor_third_price' ),
			'fourth_price' => $product->get_meta( '_donor_fourth_price' ),
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

	public static function restrict_donor_product_cart( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );

		if ( $product && 'donor' === $product->get_type() ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$cart_product = $cart_item['data'];
				if ( $cart_product && 'donor' === $cart_product->get_type() ) {
					wc_add_notice(
						__( 'We are sorry, but you can only have one Donation in your cart. View your cart <a href="' . wc_get_cart_url() . '">εδώ</a>.', 'nevma' ),
						'error'
					);
					return false;
				}
			}
		}

		return $passed;
	}

	public static function ensure_one_donor_product_in_cart( $cart_item_data, $product_id ) {
		$product = wc_get_product( $product_id );

		if ( $product && 'donor' === $product->get_type() ) {
			WC()->cart->empty_cart(); // Empty the cart before adding a donor product.
		}

		return $cart_item_data;
	}

	// If already there is on cart a donor remove it and add the new one
	public static function remove_donor_product_from_cart( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( $product && 'donor' === $product->get_type() ) {
			WC()->cart->remove_cart_item( $product_id );
		}
	}
}
