<?php //phpcs:ignore - \r\n issue

/*
 * Plugin Name: WooCommerce Donor plugin Woo
 * Plugin URI:
 * Description: WooCommerce Donor plugin Woo
 * Version: 0.0.2
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
 * Class Nvm erp bridge.
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
		add_action( 'save_post_product', array( $this, 'update_price' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'Donor_metabox' ) );
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

	/**
	 * Updates the price of a given post.
	 *
	 * This function creates a new instance of the Woo_Price class and calls the
	 * track_price_changes method to update the price of the specified post.
	 *
	 * @param int     $post_id The ID of the post to update.
	 * @param WP_Post $post    The post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function update_price( $post_id, $post, $update ) {

		$update = new Woo_Price();
		$update->track_price_changes( $post_id, $post, $update );
	}

	/**
	 * Adds a metabox to the product edit screen.
	 */
	public function Donor_metabox() {

		add_meta_box(
			'Donor_metabox',
			__( 'Price History', 'nevma' ),
			array( $this, 'display_Donor_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	public function display_Donor_metabox() {

		echo '<h3>' . __( 'Price History', 'nvm-product-price-history-inline' ) . '</h3>';
		global $post;
		$product = wc_get_product( $post->ID );
		$Donor   = $product->get_meta( '_nvm_Donor' );

		if ( ! is_array( $Donor ) || empty( $Donor ) ) {
			echo '<p>' . __( 'No price changes recorded.', 'nvm-product-price-history-inline' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( array_reverse( $Donor ) as $entry ) {
			echo '<li>';
			echo esc_html( date( 'd/m/Y H:i', strtotime( $entry['date'] ) ) );
			echo ' - ' . '<strong>' . wc_price( $entry['sale_price'] ) . '</strong>';
			echo '</li>';
		}
		echo '</ul>';
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
