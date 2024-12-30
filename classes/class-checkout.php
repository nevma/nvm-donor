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
 * Class Admin_Menu.
 */
class Checkout {

	public function __construct() {
		// Checkout settings
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'add_donors_ways' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 10 );
	}

	public function add_donors_ways() {

		$chosen = WC()->session->get( 'donation' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'donation' ) : $chosen;

		$options = array(
			'individual' => __( 'ΑΤΟΜΙΚΗ ΔΩΡΕΑ', 'nevma' ),
			'corporate'  => __( 'ΕΤΑΙΡΙΚΗ ΔΩΡΕΑ', 'nevma' ),
			'memoriam'   => __( 'ΔΩΡΕΑ ΕΙΣ ΜΝΗΜΗ', 'nevma' ),
		);

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide', 'donation-type' ),
			'options' => $options,
			'default' => array_key_first( $options ),
		);

		echo '<div id="donation-choices">';
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
}
