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
 * Class Nvm Acf.
 */
class Nvm_Acf {

	public function __construct() {

		add_filter( 'acf/settings/load_json/key=ui_options_page_67703a1d20bc6', array( $this, 'acf_json_load_point' ) );
		add_filter( 'acf/settings/save_json/key=ui_options_page_67703a1d20bc6', array( $this, 'acf_json_save_point' ) );
		// Options fields
		add_filter( 'acf/settings/load_json/key=group_67703a308369d', array( $this, 'acf_json_load_point' ) );
		add_filter( 'acf/settings/save_json/key=group_67703a308369d', array( $this, 'acf_json_save_point' ) );
	}

	/**
	 * Defines the JSON loading point for Advanced Custom Fields (ACF).
	 *
	 * This method modifies the path where ACF looks for its JSON field files.
	 * It removes the default path and adds a new path to the plugin's 'acf'
	 * directory.
	 *
	 * @param array $paths An array of existing JSON loading paths.
	 * @return array The modified paths array with the new plugin path.
	 *
	 * @filter acf/settings/load_json
	 * @since 1.0.0
	 */
	public function acf_json_load_point( $paths ) {
		unset( $paths[0] );
		// Append path to load JSON from your plugin
		$paths[] = plugin_dir_path( __FILE__ ) . 'acf/';
		return $paths;
	}

	/**
	 * Defines the JSON saving point for Advanced Custom Fields (ACF).
	 *
	 * This method specifies the directory where ACF will save JSON field files
	 * when fields are updated in the WordPress admin. It sets the save location
	 * to the plugin's 'acf' directory.
	 *
	 * @param string $path The original path where ACF saves JSON files.
	 * @return string The modified path for saving ACF JSON files.
	 *
	 * @filter acf/settings/save_json
	 * @since 1.0.0
	 */
	public function acf_json_save_point( $path ) {
		$path = plugin_dir_path( __FILE__ ) . 'acf/';
		return $path;
	}
}
