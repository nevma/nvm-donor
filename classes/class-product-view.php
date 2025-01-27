<?php //phpcs:ignore - \r\n issue

/**
 * Set namespace.
 */
namespace Nvm\Donor;

use Nvm\Donor\Product_Donor;

/**
 * Check that the file is not accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}
/**
 * Class Product donor view & settings.
 */
class Product_View {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {

		// Change add to cart text on single product page
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_button_text_single' ), 10 );

		// Add Fields to product
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_fields_to_product' ) );

		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_custom_fields_before_add_to_cart' ), 10, 3 );

		// Add field to product
		add_action( 'woocommerce_product_meta_start', array( $this, 'add_donation_carts' ), 10 );

		// Check out Fields
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
		add_shortcode( 'nvm_donor_form', array( $this, 'donor_form_shortcode' ) );
	}

	/**
	 * Add donation cart form to product page.
	 */
	public function add_donation_carts( $product ) {

		if ( ! is_product() ) {
			$GLOBALS['product'] = $product;
		}

		global $product;

		do_action( 'woocommerce_before_add_to_cart_form' );

		?>
		<div class="donor-box">
			<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
				<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

				<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
			</form>

			<?php $this->add_content_after_addtocart_button( $product ); ?>
		</div>
		<?php
	}

	/**
	 * Check if product is a donor product.
	 *
	 * @param WC_Product $product The product object.
	 * @return boolean True if donor product, false otherwise.
	 */
	public function product_is_donor( $product ) {

		return Product_Donor::is_donor_product( $product );
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

		global $product;

		$chosen = WC()->session->get( 'nvm_radio_choice' );
		$chosen = empty( $chosen ) ? WC()->checkout->get_value( 'nvm_radio_choice' ) : $chosen;
		$chosen = empty( $chosen ) ? 'custom' : $chosen;

		$options = array();
		$minimum = 1;

		$minimum = Product_Donor::get_donor_minimum_amount( $product );

		$array_donor = Product_Donor::get_donor_prices( $product );
		if ( ! empty( $array_donor ) ) {
			foreach ( $array_donor as $key => $amount_donor ) {
				$options[ $amount_donor ] = $amount_donor . '€';
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
			// 'default' => $chosen,
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

		if ( ! $this->product_is_donor( $product ) ) {
			return $text;
		}

		return __( 'Ολοκλήρωση Δωρεάς', 'nevma' );
	}

	public function add_content_after_addtocart_button() {
		global $product;

		if ( ! $this->product_is_donor( $product ) ) {
			return;
		}

		$donor_text = Product_Donor::get_donor_message( $product );

		echo '<span class="safe">';
		echo wp_kses_post( $donor_text );
		echo '</span>';
	}

	/**
	 * Add donation fields to the product page.
	 */
	public function add_donation_fields_to_product() {

		global $product;

		if ( ! $this->product_is_donor( $product ) ) {
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
					if (customAmount === '' || isNaN(customAmount) || parseFloat(customAmount) < 1) {
						alert('Παρακαλώ προσθέστε ένα ποσό πληρωμής (τουλάχιστον 1€).');
						return false;
					}
				}
				return true;
			}

			document.addEventListener('DOMContentLoaded', function () {
				const addToCartButton = document.querySelector('.single_add_to_cart_button');

				if (addToCartButton) {
					addToCartButton.addEventListener('click', function (e) {
						const requiredFields = [
							'nvm_email',
							'nvm_name',
							'nvm_surname',
							'nvm_address',
							'nvm_town',
							'nvm_postal',
							'nvm_telephone'
						];

						let isValid = true;

						requiredFields.forEach(fieldId => {
							const field = document.getElementById(fieldId);
							if (field && field.value.trim() === '') {
								isValid = false;
								field.classList.add('error'); // Add error class for styling
								alert(`Το πεδίο "${field.labels[0].textContent}" είναι υποχρεωτικό.`);
							} else {
								field.classList.remove('error'); // Remove error class if valid
							}
						});

						if (!isValid) {
							e.preventDefault(); // Prevent form submission if validation fails
						}
					});
				}
			});

			// toggle choices to show when clicked
			document.addEventListener('DOMContentLoaded', function () {
				// Get the checkbox and the company field
				const timologioCheckbox = document.getElementById('nvm_timologio');
				const companyField = document.getElementById('nvm_company_field');
				const companyafm = document.getElementById('nvm_afm_field');
				const companydoy = document.getElementById('nvm_doy_field');

				// Hide the company field by default
				companyField.style.display = 'none';
				companyField.style.display = 'none';
				companyafm.style.display = 'none';
				companydoy.style.display = 'none';

				// Add an event listener to the checkbox
				timologioCheckbox.addEventListener('change', function () {
					if (this.checked) {
						// Show the company field when the checkbox is checked
						companyField.style.display = 'block';
						companyafm.style.display = 'block';
						companydoy.style.display = 'block';
					} else {
						// Hide the company field when the checkbox is unchecked
						companyField.style.display = 'none';
						companyafm.style.display = 'none';
						companydoy.style.display = 'none';
					}
				});


				const epistoliCheckbox = document.getElementById('nvm_epistoli');
				const companyname = document.getElementById('nvm_name_company_field');
				const companysurname = document.getElementById('nvm_surname_company_field');
				const companyspace = document.getElementById('nvm_space_company_field');
				const companyemail = document.getElementById('nvm_email_company_field');

				// Hide the company field by default
				companyname.style.display = 'none';
				companysurname.style.display = 'none';
				companyspace.style.display = 'none';
				companyemail.style.display = 'none';

				// Add an event listener to the checkbox
				epistoliCheckbox.addEventListener('change', function () {
					if (this.checked) {
						// Show the company field when the checkbox is checked
						companyname.style.display = 'block';
						companysurname.style.display = 'block';
						companyspace.style.display = 'block';
						companyemail.style.display = 'block';
					} else {
						// Hide the company field when the checkbox is unchecked
						companyname.style.display = 'none';
						companysurname.style.display = 'none';
						companyspace.style.display = 'none';
						companyemail.style.display = 'none';
					}
				});

				const deadCheckbox = document.getElementById('nvm_dead');
				const userdead = document.getElementById('nvm_dead_relative_field');

				// Hide the company field by default
				userdead.style.display = 'none';

				// Add an event listener to the checkbox
				deadCheckbox.addEventListener('change', function () {
					if (this.checked) {
						// Show the company field when the checkbox is checked
						userdead.style.display = 'block';

					} else {
						// Hide the company field when the checkbox is unchecked
						userdead.style.display = 'none';

					}
				});
			});

			document.addEventListener('DOMContentLoaded', function () {
				const donorTypeRadios = document.querySelectorAll('input[name="type_of_donation"]');
				const step2Element = document.getElementById('second-step');

				donorTypeRadios.forEach(radio => {
					radio.addEventListener('change', function () {
						updateStep2Class(this.value);
					});
				});

				function updateStep2Class(donorType) {
					// Remove any existing donor-type-related class
					step2Element.classList.remove('donor-individual', 'donor-corporate', 'donor-memoriam');

					// Add the new class based on the selected donor type
					const newClass = `donor-${donorType}`;
					step2Element.classList.add(newClass);
				}

				// Initialize the class based on the default selection
				const defaultDonorType = document.querySelector('input[name="type_of_donation"]:checked');
				if (defaultDonorType) {
					updateStep2Class(defaultDonorType.value);
				}
			});


		</script>
		<style>

			.donor-box #nvm_epistoli_field,
			.donor-box #nvm_dead_field,
			.donor-box #nvm_timologio_field,
			.donor-box #nvm_dead_name_field{
				display:none;
			}

			.donor-box .donor-corporate #nvm_timologio_field,
			.donor-box .donor-memoriam #nvm_dead_name_field{
				display:block;
			}

			.donor-box .donor-memoriam #nvm_epistoli_field{
				display:block;
			}

			.donor-box input[type="radio"] + label::after,
			.donor-box input[type="radio"] + label::before{
				display: none!important;
			}

			.donor-box input {
				background-color: #f5eeee;
			}

			.donor-box #second-step input[type="checkbox"] {
				display: inline-block!important;
			}

			.donor-box #second-step-back{
				font-size: 14px;
				background: transparent;
				border: 0px;
				color: gray;
				padding-bottom: 15px;
				border: 0px solid transparent;
				padding: 0px;
				line-height: 1em;
				box-shadow: none;
				color:var(--color-pink-light);
			}
			.donor-box #second-step-back::hover{
				color:var(--color-pink-light);
				text-decoration: underline;
			}

			.donor-box .wp-block-woocommerce-add-to-cart-form .variations_button, .wp-block-woocommerce-add-to-cart-form form.cart {
				display: block;
			}
			.wp-block-woocommerce-product-price,
			.wp-block-post-title,
			.donor-box .wp-block-woocommerce-product-meta,
			.donor-box .woocommerce-tabs.wc-tabs-wrapper{
				display: none;
			}

			.donor-box .woocommerce div.product form.cart div.quantity{
				display: none;
			}
			.donor-box .safe {
				font-size:14px;
			}
			.donor-box #donation_amount{
				text-align: center;
			}
			.donor-box .button.steps,
			.donor-box .single_add_to_cart_button.button{
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
			.donor-box .single_add_to_cart_button.button{
				grid-column: span;
			}

			.donor-box .button.steps:hover, .single_add_to_cart_button.button:hover{
				background-color: #fff;
				color: #eb008b;
			}

			.donor-box form.cart,
			.donor-box .safe {
				max-width: 450px;
			}
			.donor-box .safe {
				display:block;
			}
			.donor-box .step{
				display:none;
			}

			.donor-box .step.active{
				display:block;
			}
			.donor-box #donation_amount_field label{
				text-align:center;
				display: block;
			}

			.donor-box .woocommerce form .form-row label,
			.donor-box .woocommerce-page form .form-row label {
				display: inline-block;
			}

			.donor-box #type_of_donation_field > span{
				display: grid;
				grid-template-columns: repeat(3, 1fr); /* Δημιουργεί 3 ίσες στήλες */
				gap: 0px;

			}
			.donor-box #type_of_donation_field label{
				background-color: #fff;
				color: #eb008b;
				padding: 6px 20px;
				border-radius: 2px;
				box-shadow: 0 0 0 2px #eb008b;
				text-align: center;
			}
			.donor-box #type_of_donation_field input[type=radio]:checked+label {
				background-color: #eb008b;
				color: #fff;
			}

			.donor-box #type_of_donation_field input,
			.donor-box #nvm_radio_choice_field input{
				visibility:hidden;
				position: absolute;
				top: 0px;

			}

			.donor-box #nvm_radio_choice_field > span{
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				grid-template-rows: auto auto;
				gap: 0px;
			}

			.donor-box #nvm_radio_choice_field label:last-child {
				grid-column: 1 / -1; /* εκτείνεται σε όλες ��ις στήλες */
			}

			.donor-box #nvm_radio_choice_field label{
				background-color: #fff;
				color: #023f88;
				padding: 6px 20px;
				border-radius: 2px;
				box-shadow: 0 0 0 2px #023f88;
				text-align: center;
			}

			.donor-box #nvm_radio_choice_field input[type=radio]:checked+label {
				background-color: #023f88;
				color: #fff;
			}
			.donor-box .optional{
				display:none;
			}

		</style>
		<?php
	}

	function validate_custom_fields_before_add_to_cart( $passed, $product_id, $quantity ) {
		// Check if product is donor type
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'donor' ) ) {
			return $passed;
		}

		$required_fields = array(
			'nvm_email'     => 'email',
			'nvm_name'      => 'Όνομα',
			'nvm_surname'   => 'Επίθετο',
			'nvm_address'   => 'Διεύθυνση',
			'nvm_town'      => 'Πόλη',
			'nvm_postal'    => 'Ταχυδρομικός Κώδικας',
			'nvm_telephone' => 'Τηλέφωνο',

		);

		foreach ( $required_fields as $field => $error ) {
			if ( empty( $_POST[ $field ] ) ) {
				wc_add_notice( sprintf( __( 'Το πεδίο "%s" είναι υποχρεωτικό.', 'nevma' ), $error ), 'error' );
				$passed = false;
			}
		}

		return $passed;
	}

	/**
	 * Save donation data to cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 */
	public function save_donation_data( $cart_item_data, $product_id ) {

		if ( isset( $_POST['donation_form_nonce_field'] ) && ! wp_verify_nonce( $_POST['donation_form_nonce_field'], 'donation_form_nonce' ) ) {
			wc_add_notice( __( 'Η επαλήθευση του nonce απέτυχε. Παρακαλώ δοκιμάστε ξανά.', 'nevma' ), 'error' );
			return $cart_item_data;
		}

		if ( ! isset( $_POST['type_of_donation'] ) ) {
			return $cart_item_data;
		}

		if ( isset( $_POST['type_of_donation'] ) ) {

			$cart_item_data['type_of_donation'] = $_POST['type_of_donation'];

			// Validate required fields.
			$required_fields = array(
				'nvm_email'   => __( 'Email is required.', 'nevma' ),
				'nvm_name'    => __( 'Name is required.', 'nevma' ),
				'nvm_surname' => __( 'Surname is required.', 'nevma' ),
			);

			foreach ( $required_fields as $field => $error_message ) {
				if ( ! isset( $_POST[ $field ] ) || empty( $_POST[ $field ] ) ) {
					return $cart_item_data;
				}
			}
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
			'dead_message'      => __( 'Μήνυμα Αποθα��όντος', 'nevma' ),
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

		if ( $this->product_is_donor( $product ) ) {

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
		// Define default attributes.
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'donor_form'
		);

		// Validate product ID.
		$product_id = intval( $atts['product_id'] );
		if ( $product_id <= 0 ) {
			return __( 'Product ID is required', 'nevma' );
		}

		// Fetch product without affecting global $product.
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $this->product_is_donor( $product ) ) {
			return __( 'Invalid donor product', 'nevma' );
		}

		// Capture the output of the donation form.
		ob_start();
		echo $this->add_donation_carts( $product );
		// Add the add to cart button
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
			.woocommerce-checkout-review-order-table th{
				dislay:none;
			}
		</style>
		<?php
	}
}
