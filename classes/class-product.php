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
 * Class Product donor view & settings.
 */
class Product {

	public function __construct() {

		// Change add to cart text on single product page
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_button_text_single' ) );
		// Add Fields to product
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_fields_to_product' ) );
		// Add text field to product
		add_action( 'woocommerce_product_meta_start', array( $this, 'add_content_after_addtocart_button' ), 20 );
		// remove quantity
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'remove_quantity_input_field' ), 10, 2 );

		// Save and calculate costs
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_donation_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_donation_to_order_items' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_product_price_based_on_choice' ) );

		// Redirect to check out after add to cart
		add_action( 'woocommerce_add_to_cart', array( $this, 'redirect_to_checkout_for_specific_product' ), 50, 6 );
		add_action( 'save_post', array( $this, 'nvm_set_product_virtual_on_save' ), 10, 2 );
	}


	public function remove_quantity_input_field( $return, $product ) {

		if ( is_product() ) {

			$target_product_id = $this->product_is_donor( $product );

			if ( $product->get_id() === $target_product_id ) {
				return true;
			}
		}
		return $return;
	}


	public function product_is_donor( $product ) {
		if ( class_exists( 'ACF' ) ) {
			$donor_product_id = get_field( 'activate' );

			if ( $donor_product_id ) {
				$donor_product_id = $product->get_id();
				return $donor_product_id;
			}
		}

		return false;
	}

	public function get_donor_type() {
		$chosen  = WC()->session->get( 'radio_chosen' );
		$chosen  = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_donor_type' ) : $chosen;
		$options = array();
		$minimum = 1;

		if ( class_exists( 'ACF' ) ) {

			$array_donor = get_field( 'donor_prices' );
			if ( ! empty( $array_donor ) ) {
				foreach ( $array_donor as $donor ) {
					$donor_amount             = $donor['amount'];
					$options[ $donor_amount ] = $donor_amount . '€';
				}
			}
		}

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

			woocommerce_form_field( 'type_of_donation', $args, $chosen );
	}

	public function get_donor_prices() {
		$chosen  = WC()->session->get( 'nvm_radio_choice' );
		$chosen  = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_radio_choice' ) : $chosen;
		$options = array();
		$minimum = 1;

		if ( class_exists( 'ACF' ) ) {
			$minimum = get_field( 'minimun_amount' );

			$array_donor = get_field( 'donor_prices' );
			if ( ! empty( $array_donor ) ) {
				foreach ( $array_donor as $donor ) {
					$donor_amount             = $donor['amount'];
					$options[ $donor_amount ] = $donor_amount . '€';
				}
			}
		}

		if ( empty( $options ) ) {
			$options = array(
				'5'  => '5€',
				'10' => '10€',
				'25' => '25€',
				'50' => '50€',
			);
		}

		$options['custom'] = esc_html__( 'Άλλο Ποσό', 'nevma' );
		$chosen            = empty( $chosen ) ? 'custom' : $chosen;

		$args = array(
			'type'    => 'radio',
			'class'   => array( 'form-row-wide' ),
			'options' => $options,
			'default' => $chosen,
		);

		echo '<div id="donation-choices">';
		woocommerce_form_field( 'nvm_radio_choice', $args, $chosen );
		echo '</div>';

		echo '<div class="donation-fields">';
		woocommerce_form_field(
			'donation_amount',
			array(
				'type'              => 'number',
				'label'             => __( 'Ποσό Δωρεάς σε ευρώ', 'nevma' ),
				'required'          => false,
				'class'             => array( 'form-row-wide' ),
				'placeholder'       => __( 'Προσθέστε το Ποσό / Eλάχιστό', 'nevma' ) . ' ' . $minimum . ' (€)',
				'custom_attributes' => array(
					'min' => $minimum,
				),
			)
		);
		// echo '<span>' . __( 'Minimun Amount:', 'nevma' ) . ' ' . $minimum . '€</span>';
		echo '</div>';

		wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
	}

	public function get_donor_details() {

		echo '<div class="donation-fields">';

		woocommerce_form_field(
			'nvm_epistoli',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Αν επιθυμείτε ευχαριστήρια επιστολή, παρακαλούμε συμπληρώστε τα στοιχεία του παραλήπτη', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company' ),
			)
		);

		woocommerce_form_field(
			'nvm_name_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Όνομα', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-first', 'company' ),
			)
		);

		woocommerce_form_field(
			'nvm_surname_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Επίθετο', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-last', 'company' ),
			)
		);

		woocommerce_form_field(
			'nvm_space_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Τίτλος', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company' ),
			)
		);

		woocommerce_form_field(
			'nvm_email_company',
			array(
				'type'     => 'email',
				'label'    => __( 'email', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company' ),
			)
		);

		echo __( 'Στοιχεία Δωρητή', 'nevma' );
		woocommerce_form_field(
			'nvm_email',
			array(
				'type'     => 'email',
				'label'    => __( 'email', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_name',
			array(
				'type'     => 'text',
				'label'    => __( 'Όνομα', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'common' ),
			)
		);
		woocommerce_form_field(
			'nvm_surname',
			array(
				'type'     => 'text',
				'label'    => __( 'Επίθετο', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_address',
			array(
				'type'     => 'text',
				'label'    => __( 'Διεύθυνση', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_town',
			array(
				'type'     => 'text',
				'label'    => __( 'Πόλη', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_town',
			array(
				'type'     => 'text',
				'label'    => __( 'Τ.Κ.', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_telephone',
			array(
				'type'     => 'tel',
				'label'    => __( 'Τηλέφωνο', 'nevma' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'common' ),

			)
		);

		woocommerce_form_field(
			'nvm_dead',
			array(
				'type'     => 'text',
				'label'    => __( 'Ονοματεπώνυμο θανόντος/ ούσης', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Eπιθυμείτε αναγγελία', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_relative',
			array(
				'type'     => 'text',
				'label'    => __( 'Όνομα συγγενούς θανόντος/ούσης', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_relative',
			array(
				'type'     => 'textarea',
				'label'    => __( 'Μήνυμα', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_timologio',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Έκδοση Τιμολογίου', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_relative',
			array(
				'type'     => 'text',
				'label'    => __( 'ΕΠΩΝΥΜΙΑ', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_town',
			array(
				'type'     => 'text',
				'label'    => __( 'ΑΦΜ', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-first', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_town',
			array(
				'type'     => 'text',
				'label'    => __( 'ΔΟΥ', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-last', 'common' ),
			)
		);

		echo '</div>';

		wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
	}

	public function add_to_cart_button_text_single( $text ) {

		global $product;

		$product_is_donor = $this->product_is_donor( $product );

		if ( empty( $product_is_donor ) ) {
			return $text;
		}
		return __( 'Donor Amount', 'nevma' );
	}

	public function add_content_after_addtocart_button() {
		global $product;
		if ( class_exists( 'ACF' ) ) {
			$product_is_donor = $this->product_is_donor( $product );

			if ( empty( $product_is_donor ) ) {
				return;
			}

			$donor_text = get_field( 'text_after' );

			echo '<span class="safe">';
			echo $donor_text;
			echo '</span>';
		}
	}


	/**
	 * Add donation fields to the product page.
	 */
	public function add_donation_fields_to_product() {
		global $product;

		// Target specific product for donations.
		$target_product_id = $this->product_is_donor( $product );

		if ( $product->get_id() !== $target_product_id ) {
			return;
		}

		echo '<div id="first-step" class="step active">';

		$this->get_donor_type();
		$this->get_donor_prices();
		echo '<button type="button" class="button steps" onclick="nvm_nextStep()">Επόμενο / Next</button>';
		echo '</div>';

		echo '<div id="second-step" class="step">';
		echo '<button type="button steps" onclick="nvm_prevStep()"><-</button>';
		$this->get_donor_details();

		echo '<button type="button" onclick="nvm_nextStep()">Next</button>';
		echo '</div>';
		?>
		<style>

			#donation_amount{
				text-align: center;
			}
			.button.steps{
				border-radius: 0rem;
				border-color: var(--wp--preset--color--contrast);
				border-width: 2px;

				font-family: inherit;
				font-size: var(--wp--preset--font-size--small);
				font-style: normal;
				font-weight: 500;
				line-height: inherit;
				padding-top: 0.9rem;
				padding-right: 1rem;
				padding-bottom: 0.9rem;
				padding-left: 1rem;
				text-decoration: none;
				width: 100%;
			}

			.button.steps:hover{
				background-color: var(--wp--preset--color--contrast);
				color: var(--wp--preset--color--base);
			}
			.step {
				min-height: 400px;
			}

			form.cart{
				max-width: 450px;
			}
			.step{
				display:none;
			}

			.step.active{
				display:block;
			}
			#donation_amount_field label{
				text-align:center;
				display: block;
			}

			.woocommerce form .form-row label,
			.woocommerce-page form .form-row label {
				display: inline-block;
			}

			#type_of_donation_field > span{
				display: grid;
				grid-template-columns: repeat(3, 1fr); /* Δημιουργεί 3 ίσες στήλες */
				gap: 0px;

			}
			#type_of_donation_field label{
				background-color: #fff;
				color: #eb008b;
				padding: 6px 20px;
				border-radius: 2px;
				box-shadow: 0 0 0 2px #eb008b;
				text-align: center;
			}
			#type_of_donation_field input[type=radio]:checked+label {
				background-color: #eb008b;
				color: #fff;
			}

			#type_of_donation_field input,
			#nvm_radio_choice_field input{
				visibility:hidden;
				position: absolute;
				top: 0px;

			}

			#nvm_radio_choice_field > span{
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				grid-template-rows: auto auto;
				gap: 0px;
			}

			#nvm_radio_choice_field label:last-child {
				grid-column: 1 / -1; /* εκτείνεται σε όλες τις στήλες */
			}

			#nvm_radio_choice_field label{
				background-color: #fff;
				color: #023f88;
				padding: 6px 20px;
				border-radius: 2px;
				box-shadow: 0 0 0 2px #023f88;
				text-align: center;
			}

			#nvm_radio_choice_field input[type=radio]:checked+label {
				background-color: #023f88;
				color: #fff;
			}
			.optional{
				display:none;
			}


		</style>

		<script>
			let nvm_currentStep = 1;

			document.addEventListener('DOMContentLoaded', function () {
				const customAmountRadio = document.getElementById('nvm_radio_choice_custom');
				const donationAmountInput = document.getElementById('donation_amount');
				const donationRadios = document.querySelectorAll('input[name="nvm_radio_choice"]');

				// Set default input value based on the initially checked radio
				const selectedRadio = document.querySelector('input[name="nvm_radio_choice"]:checked');
				if (selectedRadio && selectedRadio.value !== 'custom') {
					donationAmountInput.value = selectedRadio.value;
					donationAmountInput.setAttribute('readonly', 'readonly');
				}

				// Update the input value according to the selected option
				donationRadios.forEach(radio => {
					radio.addEventListener('change', function () {
						if (customAmountRadio.checked) {
							donationAmountInput.value = '';
							donationAmountInput.removeAttribute('readonly');
							donationAmountInput.focus();
						} else {
							donationAmountInput.value = this.value;
							donationAmountInput.setAttribute('readonly', 'readonly');
						}
					});
				});
			});

			function nvm_showStep(step) {
				const steps = document.querySelectorAll('.step');
				steps.forEach((stepDiv, index) => {
					stepDiv.classList.toggle('active', index === step - 1);
				});
			}

			function nvm_nextStep() {
				if (nvm_currentStep === 1) {
					const typeOfDonation = document.querySelector('input[name="type_of_donation"]:checked')?.value;
					const donationChoice = document.querySelector('input[name="nvm_radio_choice"]:checked')?.value;
					const customAmountInput = document.getElementById('donation_amount');
					const customAmount = customAmountInput ? customAmountInput.value.trim() : '';

					if (donationChoice === 'custom') {
						if (customAmount === '' || isNaN(customAmount) || parseFloat(customAmount) < 5) {
							alert('Παρακαλώ προσθέστε ένα ποσό πληρωμής.');
							return;
						}
					}
				}

				if (nvm_currentStep < 3) {
					nvm_currentStep++;
					nvm_showStep(nvm_currentStep);
				}
			}

			function nvm_prevStep() {
				if (nvm_currentStep > 1) {
					nvm_currentStep--;
					nvm_showStep(nvm_currentStep);
				}
			}

			// Initialize the first step
			document.addEventListener("DOMContentLoaded", function () {
				nvm_showStep(nvm_currentStep);
			});
		</script>
		<?php
	}

		/**
		 * Save donation data to cart item.
		 *
		 * @param array $cart_item_data Cart item data.
		 * @param int   $product_id Product ID.
		 */
	public function save_donation_data( $cart_item_data, $product_id ) {

		if ( ! isset( $_POST['donation_form_nonce_field'] ) || ! wp_verify_nonce( $_POST['donation_form_nonce_field'], 'donation_form_nonce' ) ) {
			wp_die();
		}

		if ( isset( $_POST['nvm_radio_choice'] ) ) {

			if ( 'custom' === $_POST['nvm_radio_choice'] ) {
				if ( isset( $_POST['donation_amount'] ) && is_numeric( $_POST['donation_amount'] ) ) {
					$cart_item_data['nvm_radio_choice'] = floatval( $_POST['donation_amount'] );
				}
			}

			if ( 'custom' !== $_POST['nvm_radio_choice'] ) {

				$cart_item_data['nvm_radio_choice'] = floatval( sanitize_text_field( $_POST['nvm_radio_choice'] ) );
			}
		}

		return $cart_item_data;
	}

		/**
		 * Adjust product price based on radio choice.
		 *
		 * @param WC_Cart $cart The WooCommerce cart object.
		 */
	public function adjust_product_price_based_on_choice( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! WC()->cart->is_empty() ) {

			foreach ( $cart->get_cart() as $cart_item ) {

				if ( isset( $cart_item['nvm_radio_choice'] ) ) {
					$new_price = $cart_item['nvm_radio_choice']; // The new price from radio choice.
					$cart_item['data']->set_price( $new_price );
				}
			}
		}
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

		if ( isset( $values['nvm_radio_choice'] ) ) {

			$item->add_meta_data( __( 'nvm_radio_choice', 'nevma' ), $values['nvm_radio_choice'] );
		}
	}

	public function redirect_to_checkout_for_specific_product( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		// Target specific product for donations.
		$product = wc_get_product( $product_id );

		if ( ! empty( $this->product_is_donor( $product ) ) ) {
			// Get the checkout URL
			$checkout_url = wc_get_checkout_url();

			// Redirect to the checkout page
			wp_safe_redirect( $checkout_url );
			exit;
		}
	}

	/**
	 * Automatically set a WooCommerce product as virtual when saved.
	 *
	 * @param int     $post_id The ID of the product being saved.
	 * @param WP_Post $post The product post object.
	 */
	function nvm_set_product_virtual_on_save( $post_id, $post ) {
		// Ensure this is a product and not an autosave or revision.
		if ( 'product' !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Load the product object.
		$product = wc_get_product( $post_id );

		if ( ! empty( $this->product_is_donor( $product ) ) ) {

			// Check if it's not already virtual.
			if ( $product && ! $product->is_virtual() ) {
				// Set the product as virtual.
				$product->set_virtual( true );
				$product->save();
			}
		}
	}
}
