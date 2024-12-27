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
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_donor_script' ), 10 );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_donation_form_to_checkout' ), 10 );
		add_shortcode( 'nevma_donation', array( $this, 'render_donation_form' ), 10 );
		add_action( 'donor_before', array( $this, 'initiate_redirect_template' ), 10 );
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
	public function add_donation_form_to_checkout() {
		?>
	<div class="donation-form">
		<h3><?php esc_html_e( 'Make a Donation', 'text-domain' ); ?></h3>
		<p><?php esc_html_e( 'Your generosity supports our cause. Choose a donation type and amount below.', 'text-domain' ); ?></p>

		<!-- Donation Type -->
		<p>
			<label for="donation-type"><?php esc_html_e( 'Donation Type', 'text-domain' ); ?></label>
			<select id="donation-type" name="donation_type">
				<option value="individual"><?php esc_html_e( 'Individual', 'text-domain' ); ?></option>
				<option value="corporate"><?php esc_html_e( 'Corporate', 'text-domain' ); ?></option>
				<option value="memoriam"><?php esc_html_e( 'In Memoriam', 'text-domain' ); ?></option>
			</select>
		</p>

		<!-- Donation Amount -->
		<p>
			<label><?php esc_html_e( 'Donation Amount', 'text-domain' ); ?></label><br>
			<label><input type="radio" name="donation_amount" value="5"> €5</label><br>
			<label><input type="radio" name="donation_amount" value="10"> €10</label><br>
			<label><input type="radio" name="donation_amount" value="25"> €25</label><br>
			<label><input type="radio" name="donation_amount" value="50"> €50</label><br>
			<label>
				<input type="radio" name="donation_amount" value="custom">
				<?php esc_html_e( 'Other Amount', 'text-domain' ); ?>
				<input type="number" id="custom-donation-amount" name="custom_donation_amount" min="1" step="0.01" placeholder="<?php esc_attr_e( 'Enter amount', 'text-domain' ); ?>" disabled>
			</label>
		</p>
	</div>

	<script>
		// Enable custom donation amount input when selected
		document.addEventListener('DOMContentLoaded', function () {
			const customAmountRadio = document.querySelector('input[name="donation_amount"][value="custom"]');
			const customAmountInput = document.getElementById('custom-donation-amount');

			customAmountRadio.addEventListener('change', function () {
				if (this.checked) {
					customAmountInput.disabled = false;
					customAmountInput.focus();
				}
			});

			document.querySelectorAll('input[name="donation_amount"]').forEach(function (radio) {
				if (radio.value !== 'custom') {
					radio.addEventListener('change', function () {
						customAmountInput.disabled = true;
						customAmountInput.value = '';
					});
				}
			});
		});
	</script>
		<?php
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
	 * @param string $template_name Template file slug.
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
