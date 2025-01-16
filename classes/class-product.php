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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'remove_checkout_fields' ) );

		// Save and calculate costs
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'save_donation_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_donation_to_order_items' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'adjust_product_price_based_on_choice' ) );

		// Redirect to check out after add to cart
		add_action( 'woocommerce_add_to_cart', array( $this, 'redirect_to_checkout_for_specific_product' ), 50, 6 );
		add_action( 'save_post', array( $this, 'set_product_virtual_on_save' ), 10, 2 );

		// Add body class filter
		add_filter( 'body_class', array( $this, 'add_donor_class_to_checkout' ) );

		add_action( 'woocommerce_checkout_create_order', array( $this, 'update_billing_details_from_donation' ), 10, 2 );

		add_action( 'wp_head', array( $this, 'add_donor_checkout_styles' ) );

		// Add shortcode
		// add_shortcode( 'nvm_donor_form', array( $this, 'donor_form_shortcode' ) );
	}


	public function remove_quantity_input_field( $return, $product ) {

		if ( is_product() ) {

			$donor = $this->product_is_donor( $product );

			if ( $donor ) {
				return true;
			}
		}
		return $return;
	}


	/**
	 * Check if product is a donor product.
	 *
	 * @param WC_Product $product The product object.
	 * @return boolean True if donor product, false otherwise.
	 */
	public function product_is_donor( $product ) {
		if ( class_exists( 'ACF' ) ) {

			$id               = $product->get_id();
			$donor_product_id = get_field( 'activate', $id );

			if ( $donor_product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get and display the donor type selection field.
	 *
	 * Retrieves the selected donor type from session or checkout,
	 * sets up the radio options for individual/corporate/memoriam donations,
	 * and renders the form field.
	 */
	public function get_donor_type() {

		$chosen = WC()->session->get( 'type_of_donation' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'type_of_donation' ) : $chosen;
		$chosen = empty( $chosen ) ? 'individual' : $chosen;

		$options = array();

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
				'default' => $chosen,
			);

			woocommerce_form_field( 'type_of_donation', $args, $chosen );
	}

	public function get_donor_prices() {

		$chosen = WC()->session->get( 'nvm_radio_choice' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_radio_choice' ) : $chosen;
		$chosen = empty( $chosen ) ? 'custom' : $chosen;

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
				// 'label'             => __( 'Ποσό Δωρεάς σε ευρώ', 'nevma' ),
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
	}

	public function get_donor_details() {

		echo '<div class="donation-fields">';

		woocommerce_form_field(
			'nvm_epistoli',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Επιθυμείτε ευχαριστήρια επιστολή', 'nevma' ),
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
				'class'    => array( 'form-row-first', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_surname_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Επίθετο', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-last', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_space_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Τίτλος', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_email_company',
			array(
				'type'     => 'email',
				'label'    => __( 'email', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'epistoli' ),
			)
		);

		echo '<h4>' . __( 'Στοιχεία Δωρητή', 'nevma' ) . '</h4>';
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
			'nvm_postal',
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
			'nvm_dead_name',
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
				'class'    => array( 'form-row-wide' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead_relative',
			array(
				'type'     => 'text',
				'label'    => __( 'Όνομα συγγενούς θανόντος/ούσης', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead_message',
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
				'label'    => __( 'Έκδοση τιμολογίου', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_company',
			array(
				'type'     => 'text',
				'label'    => __( 'Επωνυμία εταιρίας', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'timologio' ),
			)
		);

		woocommerce_form_field(
			'nvm_afm',
			array(
				'type'     => 'text',
				'label'    => __( 'ΑΦΜ', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-first', 'timologio' ),
			)
		);

		woocommerce_form_field(
			'nvm_doy',
			array(
				'type'     => 'text',
				'label'    => __( 'ΔΟΥ', 'nevma' ),
				'required' => false,
				'class'    => array( 'form-row-last', 'timologio' ),
			)
		);

		echo '</div>';

		wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
	}

	/**
	 * Modify the add to cart button text for donor products.
	 *
	 * @param string $text The default button text.
	 * @return string Modified button text.
	 */
	public function add_to_cart_button_text_single( $text ) {
		global $product;

		$product_is_donor = $this->product_is_donor( $product );

		if ( empty( $product_is_donor ) ) {
			return $text;
		}
		return __( 'Ολοκλήρωση Δωρεάς', 'nevma' );
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
		$donor = $this->product_is_donor( $product );

		if ( empty( $donor ) ) {
			return;
		}

		echo '<div id="first-step" class="step active">';

		$this->get_donor_type();
		$this->get_donor_prices();
		echo '<button type="button" class="button steps" onclick="nvm_nextStep()">Επόμενο / Next</button>';
		echo '</div>';

		echo '<div id="second-step" class="step">';
		echo '<button id="second-step-back" type="button" onclick="nvm_prevStep()"><< Προηγούμενο</button>';
		$this->get_donor_details();
		echo '</div>';
		echo wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
		?>
		<script>
			let nvm_currentStep = 1;

			// Initialize all event listeners on page load
			document.addEventListener('DOMContentLoaded', function () {
				nvm_showStep(nvm_currentStep);         // Initialize the first step
				nvm_toggleFields();                    // Initialize visibility based on the selected donation type
				nvm_toggleEpistoliFields();            // Initialize visibility based on the epistoli
				nvm_toggleAnagkeliaFields();           // Initialize visibility Anagkelia
				nvm_setupDonationAmountHandler();      // Initialize donation amount input behavior
				nvm_setupDonationTypeHandler();       // Initialize donation type toggle behavior

				// Add event listener to the "Έκδοση Τιμολογίου" checkbox if it exists
				const invoiceCheckbox = document.getElementById('nvm_timologio');
				if (invoiceCheckbox) {
					invoiceCheckbox.addEventListener('change', nvm_toggleInvoiceFields);
					nvm_toggleInvoiceFields(); // Initialize invoice fields based on the checkbox state
				}

				const epilostoliCheckbox = document.getElementById('nvm_epistoli');
				if (epilostoliCheckbox) {
					epilostoliCheckbox.addEventListener('change', nvm_toggleEpistoliFields);
					nvm_toggleEpistoliFields(); // Initialize invoice fields based on the checkbox state
				}

				const anagkeliaCheckbox = document.getElementById('nvm_dead');
				if (anagkeliaCheckbox) {
					anagkeliaCheckbox.addEventListener('change', nvm_toggleAnagkeliaFields);
					nvm_toggleAnagkeliaFields(); // Initialize invoice fields based on the checkbox state
				}

			});

			/**
			 * Show the specified step
			 * @param {number} step
			 */
			function nvm_showStep(step) {
				const steps = document.querySelectorAll('.step');
				const step2Button = document.querySelector('.single_add_to_cart_button ');

				steps.forEach((stepDiv, index) => {
					stepDiv.classList.toggle('active', index === step - 1);
				});

				// Show the button only on step 2
				if (step === 2 && step2Button) {
					step2Button.style.display = 'block';
				} else if (step2Button) {
					step2Button.style.display = 'none';
				}
			}

			/**
			 * Go to the next step
			 */
			function nvm_nextStep() {
				if (nvm_currentStep === 1) {
					if (!nvm_validateDonationAmount()) {
						return;
					}
				}

				if (nvm_currentStep < 2) {
					nvm_currentStep++;
					nvm_showStep(nvm_currentStep);
				}
			}

			/**
			 * Go to the previous step
			 */
			function nvm_prevStep() {
				if (nvm_currentStep > 1) {
					nvm_currentStep--;
					nvm_showStep(nvm_currentStep);
				}
			}

			/**
			 * Validate the donation amount before proceeding
			 * @returns {boolean}
			 */
			function nvm_validateDonationAmount() {
				const donationChoice = document.querySelector('input[name="nvm_radio_choice"]:checked')?.value;
				const customAmountInput = document.getElementById('donation_amount');
				const customAmount = customAmountInput ? customAmountInput.value.trim() : '';

				if (donationChoice === 'custom') {
					if (customAmount === '' || isNaN(customAmount) || parseFloat(customAmount) < 5) {
						alert('Παρακαλώ προσθέστε ένα ποσό πληρωμής (τουλάχιστον 5€).');
						return false;
					}
				}
				return true;
			}

			/**
			 * Toggle visibility of form fields based on the selected donation type
			 */
			function nvm_toggleFields() {
				const donationType = document.querySelector('input[name="type_of_donation"]:checked').value;

				// Group selectors for fields
				const companyFields = document.querySelectorAll('.company');
				const memoriamFields = document.querySelectorAll('.memoriam');
				const commonFields = document.querySelectorAll('.common');

				// Hide all optional fields first
				companyFields.forEach(field => field.style.display = 'none');
				memoriamFields.forEach(field => field.style.display = 'none');
				commonFields.forEach(field => field.style.display = 'block'); // Always show common fields

				// Show relevant fields based on donation type
				if (donationType === 'corporate') {
					companyFields.forEach(field => field.style.display = 'block');
				} else if (donationType === 'memoriam') {
					memoriamFields.forEach(field => field.style.display = 'block');
				}
			}

			/**
			 * Setup event listeners for donation type radio buttons
			 */
			function nvm_setupDonationTypeHandler() {
				document.querySelectorAll('input[name="type_of_donation"]').forEach(function (radio) {
					radio.addEventListener('change', nvm_toggleFields);
				});
			}

			/**
			 * Setup behavior for donation amount radio buttons and custom input
			 */
			function nvm_setupDonationAmountHandler() {
				const customAmountRadio = document.getElementById('nvm_radio_choice_custom');
				const donationAmountInput = document.getElementById('donation_amount');
				const donationRadios = document.querySelectorAll('input[name="nvm_radio_choice"]');

				// Set the default input value based on the initially checked radio button
				const selectedRadio = document.querySelector('input[name="nvm_radio_choice"]:checked');
				if (selectedRadio && selectedRadio.value !== 'custom') {
					donationAmountInput.value = selectedRadio.value;
					donationAmountInput.setAttribute('readonly', 'readonly');
				}

				// Update input value based on the selected donation option
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
			}

			/**
			 * Show or hide invoice fields based on the checkbox state
			 */
			function nvm_toggleInvoiceFields() {
				const invoiceCheckbox = document.getElementById('nvm_timologio');
				if (!invoiceCheckbox) return; // Exit if checkbox is not found

				// Fields to toggle
				const relativeField = document.getElementById('nvm_company_field');
				const afmField = document.getElementById('nvm_afm_field');
				const douField = document.getElementById('nvm_doy_field');

				// Ensure fields exist before trying to change their display
				if (relativeField && afmField && douField) {
					const displayStyle = invoiceCheckbox.checked ? 'block' : 'none';
					relativeField.style.display = displayStyle;
					afmField.style.display = displayStyle;
					douField.style.display = displayStyle;
				}
			}

			/**
			 * Show or hide invoice fields based on the checkbox state
			 */
			function nvm_toggleEpistoliFields() {
				const invoiceCheckbox = document.getElementById('nvm_epistoli');
				if (!invoiceCheckbox) return; // Exit if checkbox is not found

				// Fields to toggle
				const companyField = document.getElementById('nvm_name_company_field');
				const surnameField = document.getElementById('nvm_surname_company_field');
				const spaceField = document.getElementById('nvm_space_company_field');
				const emailField = document.getElementById('nvm_email_company_field');

				// Ensure fields exist before trying to change their display
				if (companyField && surnameField && spaceField && emailField ) {
					const displayStyle = invoiceCheckbox.checked ? 'block' : 'none';
					companyField.style.display = displayStyle;
					surnameField.style.display = displayStyle;
					spaceField.style.display = displayStyle;
					emailField.style.display = displayStyle;
				}
			}

			/**
			 * Show or hide invoice fields based on the checkbox state
			 */
			function nvm_toggleAnagkeliaFields() {
				const anagkeliaCheckbox = document.getElementById('nvm_dead');
				if (!anagkeliaCheckbox) return;

				const relativeField = document.getElementById('nvm_dead_relative_field');
				const messageField = document.getElementById('nvm_dead_message_field');

				if (relativeField && messageField) {
					const displayStyle = anagkeliaCheckbox.checked ? 'block' : 'none';
					relativeField.style.display = displayStyle;
					messageField.style.display = displayStyle;
				}
			}

		</script>

		<style>

			input {
				background-color: #f5eeee;
			}

			#second-step-back{
				font-size: 14px;
				background: transparent;
				border: 0px;
				color: gray;
				padding-bottom: 15px;
			}

			.wp-block-woocommerce-add-to-cart-form .variations_button, .wp-block-woocommerce-add-to-cart-form form.cart {
				display: block;
			}
			.wp-block-woocommerce-product-price,
			.wp-block-post-title,
			.wp-block-woocommerce-product-meta,
			.woocommerce-tabs.wc-tabs-wrapper{
				display: none;
			}

			.woocommerce div.product form.cart div.quantity{
				display: none;
			}
			.safe {
				font-size:14px;
			}
			#donation_amount{
				text-align: center;
			}
			.button.steps, .single_add_to_cart_button.button{
				background-color: #eb008b;
				border-radius: 40px;
				color: #fff;
				/* border-radius: 0rem; */
				border-color: #eb008b;
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
			.single_add_to_cart_button.button{
				grid-column: span;
			}

			.button.steps:hover, .single_add_to_cart_button.button:hover{
				background-color: #fff;
				color: #eb008b;
			}
			/* .step {
				min-height: 400px;
			} */

			form.cart,
			.safe {
				max-width: 450px;
			}
			.safe {
				display:block;
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

		if ( isset( $_POST['type_of_donation'] ) ) {
			$cart_item_data['type_of_donation'] = $_POST['type_of_donation'];
		}

		if ( isset( $_POST['nvm_epistoli'] ) ) {
			$cart_item_data['epistoli_name']     = $_POST['nvm_name_company'];
			$cart_item_data['epistoli_surname']  = $_POST['nvm_surname_company'];
			$cart_item_data['epistoli_position'] = $_POST['nvm_space_company'];
			$cart_item_data['epistoli_email']    = $_POST['nvm_email_company'];
		}

		if ( isset( $_POST['nvm_email'] ) ) {
			$cart_item_data['user_email'] = $_POST['nvm_email'];
		}

		if ( isset( $_POST['nvm_name'] ) ) {
			$cart_item_data['user_name'] = $_POST['nvm_name'];
		}

		if ( isset( $_POST['nvm_surname'] ) ) {
			$cart_item_data['user_surname'] = $_POST['nvm_surname'];
		}

		if ( isset( $_POST['nvm_address'] ) ) {
			$cart_item_data['user_address'] = $_POST['nvm_address'];
		}

		if ( isset( $_POST['nvm_town'] ) ) {
			$cart_item_data['user_town'] = $_POST['nvm_town'];
		}

		if ( isset( $_POST['nvm_postal'] ) ) {
			$cart_item_data['user_postal'] = $_POST['nvm_postal'];
		}

		if ( isset( $_POST['nvm_telephone'] ) ) {
			$cart_item_data['user_telephone'] = $_POST['nvm_telephone'];
		}

		if ( isset( $_POST['nvm_dead_name'] ) ) {
			$cart_item_data['dead_name'] = $_POST['nvm_dead_name'];
		}

		if ( isset( $_POST['nvm_dead'] ) ) {
			$cart_item_data['dead_relative'] = $_POST['nvm_dead_relative'];
			$cart_item_data['dead_message']  = $_POST['nvm_dead_message'];
		}

		if ( isset( $_POST['nvm_timologio'] ) ) {
			$cart_item_data['timologio_company'] = $_POST['nvm_company'];
			$cart_item_data['timologio_afm']     = $_POST['nvm_afm'];
			$cart_item_data['timologio_doy']     = $_POST['nvm_doy'];
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
	 * Add all custom donation data to order items.
	 *
	 * @param \WC_Order_Item $item Order item.
	 * @param string         $cart_item_key Cart item key.
	 * @param array          $values Cart item data.
	 * @param \WC_Order      $order Order object.
	 */
	public function add_donation_to_order_items( $item, $cart_item_key, $values, $order ) {
		// Define a list of keys you want to save to the order item.
		$custom_keys = array(
			'type_of_donation'  => __( 'Τύπος Δωρεάς', 'nevma' ),
			'epistoli_name'     => __( 'Όνομα Επιστολής', 'nevma' ),
			'epistoli_surname'  => __( 'Επώνυμο Επιστολής', 'nevma' ),
			'epistoli_position' => __( 'Θέση Επιστολής', 'nevma' ),
			'epistoli_email'    => __( 'Email Επιστολής', 'nevma' ),
			'user_email'        => __( 'Email Δωρητή', 'nevma' ),
			'user_name'         => __( 'Όνομα Δωρητή', 'nevma' ),
			'user_surname'      => __( 'Επώνυμο Δωρητή', 'nevma' ),
			'user_address'      => __( 'Διεύθυνση Δωρητή', 'nevma' ),
			'user_town'         => __( 'Πόλη Δωρητή', 'nevma' ),
			'user_postal'       => __( 'Ταχυδρομικός Κώδικας Δωρητή', 'nevma' ),
			'user_telephone'    => __( 'Τηλέφωνο Δωρηρή', 'nevma' ),
			'dead_name'         => __( 'Όνομα Αποθανόντος', 'nevma' ),
			'dead_relative'     => __( 'Συγγένεια Αποθανόντος', 'nevma' ),
			'dead_message'      => __( 'Μήνυμα Αποθανόντος', 'nevma' ),
			'timologio_company' => __( 'Εταιρεία Τιμολογίου', 'nevma' ),
			'timologio_afm'     => __( 'ΑΦΜ Τιμολογίου', 'nevma' ),
			'timologio_doy'     => __( 'ΔΟΥ Τιμολογίου', 'nevma' ),
			'nvm_radio_choice'  => __( 'Ποσό Δωρεάς', 'nevma' ),
		);

		// Loop through custom keys and add them to the order item if they exist.
		foreach ( $custom_keys as $key => $label ) {
			if ( isset( $values[ $key ] ) && ! empty( $values[ $key ] ) ) {
				$item->add_meta_data( $label, sanitize_text_field( $values[ $key ] ) );
			}
		}
	}

	/**
	 * Update billing details with custom donation data when the order is created.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @param array    $data  Posted checkout data.
	 */
	public function update_billing_details_from_donation( $order, $data ) {
		// Get cart items.
		foreach ( WC()->cart->get_cart() as $cart_item ) {

			// Update billing fields only if custom donation data exists.
			if ( isset( $cart_item['user_name'] ) ) {
				$order->set_billing_first_name( sanitize_text_field( $cart_item['user_name'] ) );
			}

			if ( isset( $cart_item['user_surname'] ) ) {
				$order->set_billing_last_name( sanitize_text_field( $cart_item['user_surname'] ) );
			}

			if ( isset( $cart_item['user_email'] ) ) {
				$order->set_billing_email( sanitize_email( $cart_item['user_email'] ) );
			}

			if ( isset( $cart_item['user_telephone'] ) ) {
				$order->set_billing_phone( sanitize_text_field( $cart_item['user_telephone'] ) );
			}

			// If you only want to update the first item, break the loop.
			break;
		}
	}





	public function redirect_to_checkout_for_specific_product( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		// Target specific product for donations.
		$product = wc_get_product( $product_id );

		if ( $this->product_is_donor( $product ) ) {
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
	function set_product_virtual_on_save( $post_id, $post ) {
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
	/**
	 * Remove billing/shipping fields if cart contains donor product
	 */
	public function remove_checkout_fields( $fields ) {
		// Get cart items
		$cart_items = WC()->cart->get_cart();

		// Check if any cart item is a donor product
		$has_donor_product = false;
		foreach ( $cart_items as $cart_item ) {
			$product = $cart_item['data'];
			if ( $this->product_is_donor( $product ) ) {
				$has_donor_product = true;
				break;
			}
		}

		// If cart has donor product, remove shipping fields
		if ( $has_donor_product ) {
			unset( $fields['shipping'] );

			// Remove billing fields we don't need
			unset( $fields['billing'] );
		}

		// Remove order comments/additional information field
		unset( $fields['order']['order_comments'] );

		return $fields;
	}

	/**
	 * Add donor class to checkout if cart contains donor products
	 */
	public function add_donor_class_to_checkout( $classes ) {
		// Get cart items
		$cart_items = WC()->cart->get_cart();

		// Check if any cart item is a donor product
		foreach ( $cart_items as $cart_item ) {
			$product = $cart_item['data'];
			if ( $this->product_is_donor( $product ) ) {
				$classes[] = 'has-donor-product';
				break;
			}
		}

		return $classes;
	}

	/**
	 * Shortcode to display donor form for a specific product.
	 *
	 * @param array $atts Shortcode attributes with 'product_id'.
	 * @return string HTML output of the donor form.
	 */
	public function donor_form_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts
		);

		if ( empty( $atts['product_id'] ) ) {
			return __( 'Product ID is required', 'nevma' );
		}

		$product = wc_get_product( $atts['product_id'] );
		if ( ! $product || ! $this->product_is_donor( $product ) ) {
			return __( 'Invalid donor product', 'nevma' );
		}

		ob_start();
		$this->add_donation_fields_to_product();
		return ob_get_clean();
	}

	public function add_donor_checkout_styles() {
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<style>
			.has-donor-product .woocommerce-billing-fields,
			.has-donor-product .col-2,
			.has-donor-product #order_review_heading {
				display: none;
			}
		</style>
		<?php
	}
}
