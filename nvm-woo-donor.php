<?php //phpcs:ignore - \r\n issue

/*
 * Plugin Name: WooCommerce Donor plugin by Nevma
 * Plugin URI:
 * Description: A plugin to handle donations via WooCommerce by nevma team
 * Version: 1.1.2
 * Author: Nevma Team
 * Author URI: https://woocommerce.com/vendor/nevma/
 * Text Domain: nevma
 *
 * Woo:
 * WC requires at least: 4.0
 * WC tested up to: 9.4
*/

/**
 * Set namespace.
 */
namespace Nvm;

/**
 * Check that the file is not accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

/**
 * Class Donor.
 */
class Donor {
	/**
	 * The plugin version.
	 *
	 * @var string $version
	 */
	public static $plugin_version;

	/**
	 * Set namespace prefix.
	 *
	 * @var string $namespace_prefix
	 */
	public static $namespace_prefix;

	/**
	 * The plugin directory.
	 *
	 * @var string $plugin_dir
	 */
	public static $plugin_dir;

	/**
	 * The plugin temp directory.
	 *
	 * @var string $plugin_tmp_dir
	 */
	public static $plugin_tmp_dir;

	/**
	 * The plugin url.
	 *
	 * @var string $plugin_url
	 */
	public static $plugin_url;

	/**
	 * The plugin instance.
	 *
	 * @var null|Donor $instance
	 */
	private static $instance = null;

	/**
	 * Gets the plugin instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {

		// Set the plugin version.
		self::$plugin_version = '0.0.1';

		// Set the plugin namespace.
		self::$namespace_prefix = 'Nvm\\Donor';

		// Set the plugin directory.
		self::$plugin_dir = wp_normalize_path( plugin_dir_path( __FILE__ ) );

		// Set the plugin url.
		self::$plugin_url = plugin_dir_url( __FILE__ );

		// Autoload.
		self::autoload();

		// Scripts & Styles.
		add_action( 'acf/init', array( $this, 'sync_acf_fields_from_json' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_donor_script' ), 10 );

		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_fields_to_product' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_donation_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_donation_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_donation_to_order_items' ), 10, 4 );

		add_filter( 'woocommerce_checkout_fields', array( $this, 'nvm_customize_checkout_fields' ), 10 );
	}

	/**
	 * Autoload.
	 */
	public static function autoload() {
		spl_autoload_register(
			function ( $class ) {

				$prefix = self::$namespace_prefix;
				$len    = strlen( $prefix );

				if ( 0 !== strncmp( $prefix, $class, $len ) ) {
					return;
				}

				$relative_class = substr( $class, $len );
				$path           = explode( '\\', strtolower( str_replace( '_', '-', $relative_class ) ) );
				$file           = array_pop( $path );
				$file           = self::$plugin_dir . 'classes/class-' . $file . '.php';

				if ( file_exists( $file ) ) {
					require $file;
				}

				// add the autoload.php file for the prefixed vendor folder.
				require self::$plugin_dir . '/prefixed/vendor/autoload.php';
			}
		);
	}

	public function declare_hpos_compatibility() {

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Check plugin dependencies.
	 *
	 * Verifies if WooCommerce is active without relying on the folder structure.
	 */
	public static function check_plugin_dependencies() {
		// Check if the WooCommerce class exists.
		if ( ! class_exists( 'WooCommerce' ) ) {
			// Display an admin error message and terminate the script.
			wp_die(
				esc_html__( 'Sorry, but this plugin requires the WooCommerce plugin to be active.', 'your-text-domain' ) .
				' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' .
				esc_html__( 'Return to Plugins.', 'nevma' ) . '</a>'
			);
		}
	}

	/**
	 * Load and sync ACF fields from a JSON file.
	 */
	public function sync_acf_fields_from_json() {
		// Path to your JSON file within the plugin directory.
		$json_file_path = plugin_dir_path( __FILE__ ) . '/acf/donor-acf.json';

		// Check if the file exists.
		if ( ! file_exists( $json_file_path ) ) {
			return;
		}

		// Decode the JSON file.
		$json_content = file_get_contents( $json_file_path );
		$field_groups = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return;
		}

		// Ensure ACF is active before syncing fields.
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Register each field group with ACF.
		foreach ( $field_groups as $field_group ) {
			acf_add_local_field_group( $field_group );
		}
	}

	public function enqueue_donor_script() {
		if ( is_checkout() ) {
			wp_enqueue_style(
				'nvm-donor',
				plugin_dir_url( __FILE__ ) . 'css/style.css',
				array(),
				self::$plugin_version
			);
		}
	}

	// Add a custom donation form to the checkout page
	public function add_donation_type() {
		?>
		<!-- Donation Type -->
		<p>
			<label for="donation-type"><?php esc_html_e( 'Donation Type', 'nevma' ); ?></label>
			<select id="donation-type" name="donation_type">
				<option value="individual"><?php esc_html_e( 'Individual', 'nevma' ); ?></option>
				<option value="corporate"><?php esc_html_e( 'Corporate', 'nevma' ); ?></option>
				<option value="memoriam"><?php esc_html_e( 'In Memoriam', 'nevma' ); ?></option>
			</select>
		</p>
		<?php
	}

	public function get_donor_product() {
		if ( class_exists( 'ACF' ) ) {
			$donor_product_id = get_field( 'product', 'options' );

			return $donor_product_id;
		}

		return false;
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

		echo '<div class="donation-fields">';
		woocommerce_form_field(
			'donation_amount',
			array(
				'type'        => 'number',
				'label'       => __( 'Donation Amount (€)', 'nevma' ),
				'required'    => false,
				'class'       => array( 'form-row-wide' ),
				'placeholder' => __( 'Enter an amount', 'nevma' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save donation data to cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 */
	public function save_donation_data( $cart_item_data, $product_id ) {
		if ( isset( $_POST['donation_amount'] ) && is_numeric( $_POST['donation_amount'] ) ) {
			$cart_item_data['donation_amount'] = floatval( sanitize_text_field( $_POST['donation_amount'] ) );
		}
		return $cart_item_data;
	}

	/**
	 * Display donation in the cart.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item data.
	 */
	public function display_donation_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['donation_amount'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Donation Amount', 'nevma' ),
				'value' => wc_price( $cart_item['donation_amount'] ),
			);
		}
		return $item_data;
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
		if ( isset( $values['donation_amount'] ) ) {
			$item->add_meta_data( __( 'Donation Amount', 'nevma' ), $values['donation_amount'] );
		}
	}


	public function initiate_redirect_template() {

		add_filter( 'woocommerce_locate_template', array( $this, 'redirect_wc_template' ), 10, 3 );

		// remove coupon field on donor checkout
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
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


	public function nvm_customize_checkout_fields( $fields ) {

		if ( is_page() && has_shortcode( get_post()->post_content, 'nevma_donation' ) ) {
			unset( $fields['billing']['billing_company'] );
			unset( $fields['billing']['billing_address_1'] );
			unset( $fields['billing']['billing_address_2'] );
			unset( $fields['billing']['billing_postcode'] );
			unset( $fields['billing']['billing_state'] );
		}
		return $fields;
	}

	public function render_donation_form() {

		ob_start();
		do_action( 'donor_before' );
		do_action( 'donor_product' );
		echo do_shortcode( '[woocommerce_checkout]' );
		?>
		<?php
		do_action( 'nvm_donor_after' );
		return ob_get_clean();
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function on_plugin_activation() {

		self::check_plugin_dependencies();
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function on_plugin_deactivation() {
	}

	/**
	 * Runs on plugin uninstall.
	 */
	public static function on_plugin_uninstall() {
	}

	/**
	 * Φόρτωση των μεταφράσεων
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'nevma',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
}


/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_activation' ) );

/**
 * Dectivation Hook.
 */
register_deactivation_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_deactivation' ) );


/**
 * Uninstall Hook.
 */
register_uninstall_hook( __FILE__, array( '\\Nvm\\Donor', 'on_plugin_uninstall' ) );

/**
 * Load plugin.
 */
add_action( 'plugins_loaded', array( '\\Nvm\\Donor', 'get_instance' ) );
