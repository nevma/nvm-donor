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

		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'nvm_thank_you_message_for_donor' ), 10, 2 );

		// Simple unified translation filter for all donor product texts
		add_filter( 'gettext', array( $this, 'nvm_translate_donor_texts' ), 20, 3 );
		add_filter( 'gettext_with_context', array( $this, 'nvm_translate_donor_texts_with_context' ), 20, 4 );

		// Direct title filters
		add_filter( 'the_title', array( $this, 'nvm_change_page_title' ), 10, 2 );
		add_filter( 'woocommerce_page_title', array( $this, 'nvm_change_woo_page_title' ), 10 );

		// HTML buffer for edge cases only
		add_action( 'template_redirect', array( $this, 'nvm_start_html_replacement_buffer' ) );

		// JavaScript failsafe for checkout page title
		add_action( 'wp_footer', array( $this, 'nvm_js_title_replacement' ) );
	}

	/**
	 * Add donation cart form to product page.
	 */
	public function add_donation_carts( $product ) {

		if ( ! is_product() ) {
			$GLOBALS['product'] = $product;
		}

		global $product;

		if ( ! $this->product_is_donor( $product ) ) {
			return;
		}

		do_action( 'woocommerce_before_add_to_cart_form' );

		?>
		<div class="donor-box">
			<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
				<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

				<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

				<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

				<?php wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' ); ?>
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
		$chosen = '';

		// Safely check if WC()->session exists and is initialized
		if ( WC() && WC()->session && WC()->session->get( 'type_of_donation' ) ) {
			$chosen = WC()->session->get( 'type_of_donation' );
		}

		// Safely check if WC()->checkout exists and is initialized
		if ( empty( $chosen ) && WC() && WC()->checkout ) {
			$chosen = WC()->checkout->get_value( 'type_of_donation' );
		}

		$chosen = empty( $chosen ) ? 'individual' : $chosen;

		$options = array(
			'individual' => __( 'INDIVIDUAL', 'nevma-donor' ),
			'corporate'  => __( 'CORPORATE', 'nevma-donor' ),
			'memoriam'   => __( 'IN MEMORY', 'nevma-donor' ),
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

		$chosen = '';

		// Safely check if WC()->session exists and is initialized
		if ( WC() && WC()->session && WC()->session->get( 'nvm_radio_choice' ) ) {
			$chosen = WC()->session->get( 'nvm_radio_choice' );
		}

		// Safely check if WC()->checkout exists and is initialized
		if ( empty( $chosen ) && WC() && WC()->checkout ) {
			$chosen = WC()->checkout->get_value( 'nvm_radio_choice' );
		}

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

		$options['custom'] = esc_html__( 'Other Amount', 'nevma-donor' );

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
				// 'label'             => __( 'Ποσό Δωρεάς σε ευρώ', 'nevma-donor' ),
				'required'          => false,
				'class'             => array( 'form-row-wide' ),
				'placeholder'       => __( 'Add the Amount / Minimum', 'nevma-donor' ) . ' ' . $minimum . ' (€)',
				'custom_attributes' => array(
					'min' => $minimum,
				),
			)
		);
		// echo '<span>' . __( 'Minimun Amount:', 'nevma-donor' ) . ' ' . $minimum . '€</span>';
		echo '</div>';
	}

	public function get_donor_details() {

		echo '<div class="donation-fields">';

		echo '<h4 class="donor-company-title">' . __( 'Company Information', 'nevma-donor' ) . '</h4>';

		woocommerce_form_field(
			'nvm_company_name',
			array(
				'type'     => 'text',
				'label'    => __( 'Company Name', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'timologio' ),
			)
		);

		woocommerce_form_field(
			'nvm_company_afm',
			array(
				'type'     => 'text',
				'label'    => __( 'Tax ID (AFM)', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-first', 'timologio' ),
			)
		);

		woocommerce_form_field(
			'nvm_company_doy',
			array(
				'type'     => 'text',
				'label'    => __( 'Tax Office (DOY)', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-last', 'timologio' ),
			)
		);

		woocommerce_form_field(
			'nvm_company_address',
			array(
				'type'     => 'text',
				'label'    => __( 'Headquarters Address', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'timologio' ),
			)
		);

		echo '<h4 class="donor-simple-title">' . __( 'Donor Information', 'nevma-donor' ) . '</h4>';
		echo '<h4 class="donor-company-title">' . __( 'Company Representative Information', 'nevma-donor' ) . '</h4>';
		echo '<h4 class="donor-memoriam-title">' . __( '"In Memory" Donation Information', 'nevma-donor' ) . '</h4>';

		woocommerce_form_field(
			'nvm_email',
			array(
				'type'     => 'email',
				'label'    => __( 'email', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_name',
			array(
				'type'     => 'text',
				'label'    => __( 'First Name', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'common' ),
			)
		);
		woocommerce_form_field(
			'nvm_surname',
			array(
				'type'     => 'text',
				'label'    => __( 'Last Name', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_address',
			array(
				'type'              => 'text',
				'label'             => __( 'Address', 'nevma-donor' ),
				'required'          => true,
				'custom_attributes' => array(
					'data-conditional-required' => 'true',
				),
				'class'             => array( 'form-row-wide', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_town',
			array(
				'type'              => 'text',
				'label'             => __( 'City', 'nevma-donor' ),
				'required'          => true,
				'custom_attributes' => array(
					'data-conditional-required' => 'true',
				),
				'class'             => array( 'form-row-first', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_postal',
			array(
				'type'              => 'text',
				'label'             => __( 'Postal Code', 'nevma-donor' ),
				'required'          => true,
				'custom_attributes' => array(
					'data-conditional-required' => 'true',
				),
				'class'             => array( 'form-row-last', 'common' ),
			)
		);

		woocommerce_form_field(
			'nvm_telephone',
			array(
				'type'     => 'tel',
				'label'    => __( 'Phone Number', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'common' ),

			)
		);

		woocommerce_form_field(
			'nvm_dead_name',
			array(
				'type'     => 'text',
				'label'    => __( 'Full name of the deceased', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Do you wish to notify someone', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead_relative',
			array(
				'type'     => 'text',
				'label'    => __( 'Name of the deceased\'s relative', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide' ),
			)
		);

		woocommerce_form_field(
			'nvm_dead_message',
			array(
				'type'     => 'textarea',
				'label'    => __( 'Message', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_epistoli',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Would you like to notify the relative?', 'nevma-donor' ),
				'required' => false,
				'class'    => array( 'form-row-wide', 'company' ),
			)
		);

		echo '<div class="epistoli-fields">';

		woocommerce_form_field(
			'nvm_epistoli_name',
			array(
				'type'     => 'text',
				'label'    => __( 'Relative\'s First Name', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_epistoli_surname',
			array(
				'type'     => 'text',
				'label'    => __( 'Relative\'s Last Name', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_epistoli_email',
			array(
				'type'     => 'email',
				'label'    => __( 'Relative\'s Email', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'epistoli' ),
			)
		);

		woocommerce_form_field(
			'nvm_memoriam_invoice',
			array(
				'type'     => 'checkbox',
				'label'    => __( 'Is the "In Memory" donation made on behalf of a company?', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		echo '</div>';

		echo '<div class="memoriam-invoice-fields">';

		woocommerce_form_field(
			'nvm_memoriam_invoice_name',
			array(
				'type'     => 'text',
				'label'    => __( 'Company Name', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_memoriam_invoice_afm',
			array(
				'type'     => 'text',
				'label'    => __( 'Tax ID (AFM)', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-first', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_memoriam_invoice_doy',
			array(
				'type'     => 'text',
				'label'    => __( 'Tax Office (DOY)', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-last', 'memoriam' ),
			)
		);

		woocommerce_form_field(
			'nvm_memoriam_invoice_address',
			array(
				'type'     => 'text',
				'label'    => __( 'Headquarters Address', 'nevma-donor' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'memoriam' ),
			)
		);

		echo '</div>';

		echo '</div>';

		// wp_nonce_field( 'donation_form_nonce', 'donation_form_nonce_field' );
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

		return __( 'Complete Donation', 'nevma-donor' );
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
		echo '<button id="second-step-back" type="button" onclick="nvm_prevStep()"><< Previous / Προηγούμενο</button>';
		$this->get_donor_details();
		echo '</div>';

		$minimum = Product_Donor::get_donor_minimum_amount( $product );

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

			document.addEventListener('DOMContentLoaded', function () {
				const radios = document.querySelectorAll('input[name="nvm_radio_choice"]');
				radios.forEach(function(radio) {
					radio.addEventListener('change', nvm_checkRadioChoice);
				});
				nvm_checkRadioChoice();
			});

			function nvm_checkRadioChoice() {
				const customAmountField = document.getElementById('donation_amount');

				if (!customAmountField) {
					console.warn('donation_amount field not found');
					return;
				}

				const radioChoice = document.querySelector('input[name="nvm_radio_choice"]:checked');

				if (radioChoice) {
					if (radioChoice.value === 'custom') {
						customAmountField.disabled = false;
						customAmountField.classList.remove('disabled');
					} else {
						customAmountField.disabled = true;
						customAmountField.classList.add('disabled');
						customAmountField.value = '';
					}
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
				const minimunAmount = <?php echo $minimum; ?>;

				if (donationChoice === 'custom') {
					if (customAmount === '' || isNaN( customAmount ) || parseFloat( customAmount ) < minimunAmount) {


						// if lang is el then show the message in greek
						if ( document.documentElement.lang === 'el' ) {
							alert( 'Η ελάχιστη δωρεά είναι ' + minimunAmount + '€' );
						} else {
							alert( 'The minimum donation is ' + minimunAmount + '€' );
						}

						return false;
					}

				}
				return true;
			}

			document.addEventListener('DOMContentLoaded', function () {
				const addToCartButton = document.querySelector('.single_add_to_cart_button');

				if (addToCartButton) {
					addToCartButton.addEventListener('click', function (e) {
						const donorType = document.querySelector('input[name="type_of_donation"]:checked')?.value;
						let requiredFields = ['nvm_email', 'nvm_name', 'nvm_surname', 'nvm_telephone'];

						if (donorType !== 'corporate') {
							requiredFields.push('nvm_address', 'nvm_town', 'nvm_postal');
						}

						if (donorType === 'memoriam') {
							const epistoliChecked = document.getElementById('nvm_epistoli')?.checked;
							if (epistoliChecked) {
								requiredFields.push('nvm_epistoli_name', 'nvm_epistoli_surname', 'nvm_epistoli_email');
							}
						}

						if (donorType === 'memoriam') {
							const invoiceChecked = document.getElementById('nvm_memoriam_invoice')?.checked;
							if (invoiceChecked) {
								requiredFields.push('nvm_memoriam_invoice_name', 'nvm_memoriam_invoice_afm', 'nvm_memoriam_invoice_doy', 'nvm_memoriam_invoice_address');
							}
						}

						let isValid = true;

						requiredFields.forEach(fieldId => {
							const field = document.getElementById(fieldId);
							if (field && field.value.trim() === '') {
								isValid = false;
								field.classList.add('error'); // Add error class for styling

								// translate the alert message based on the language
								if ( document.documentElement.lang === 'el' ) {
									alert('Το πεδίο ' + field.labels[0]?.textContent + ' είναι υποχρεωτικό.');
								} else {
									alert('The field ' + field.labels[0]?.textContent + ' is required.');
								}
							} else if (field) {
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

				//Get the checkbox and the memoriam invoice field
				const timologioCheckbox = document.getElementById('nvm_memoriam_invoice');
				const companyField = document.getElementById('nvm_memoriam_invoice_name_field');
				const companyafm = document.getElementById('nvm_memoriam_invoice_afm_field');
				const companydoy = document.getElementById('nvm_memoriam_invoice_doy_field');
				const companyaddress = document.getElementById('nvm_memoriam_invoice_address_field');

				// Hide the company field by default
				companyField.style.display = 'none';
				companyafm.style.display = 'none';
				companydoy.style.display = 'none';
				companyaddress.style.display = 'none';

				// Add an event listener to the checkbox
				timologioCheckbox.addEventListener('change', function () {
					if (this.checked) {

						console.log('checked');
						// Show the company field when the checkbox is checked
						companyField.style.display = 'block';
						companyafm.style.display = 'block';
						companydoy.style.display = 'block';
						companyaddress.style.display = 'block';
					} else {
						console.log('unchecked');
						// Hide the company field when the checkbox is unchecked
						companyField.style.display = 'none';
						companyafm.style.display = 'none';
						companydoy.style.display = 'none';
						companyaddress.style.display = 'none';
					}
				});


				const epistoliCheckbox = document.getElementById('nvm_epistoli');
				const epistoliName = document.getElementById('nvm_epistoli_name_field');
				const epistoliSurname = document.getElementById('nvm_epistoli_surname_field');
				const epistoliEmail = document.getElementById('nvm_epistoli_email_field');

				// Hide the company field by default
				epistoliName.style.display = 'none';
				epistoliSurname.style.display = 'none';
				epistoliEmail.style.display = 'none';

				// Add an event listener to the checkbox
				epistoliCheckbox.addEventListener('change', function () {
					if (this.checked) {
						// Show the company field when the checkbox is checked
						epistoliName.style.display = 'block';
						epistoliSurname.style.display = 'block';
						epistoliEmail.style.display = 'block';
					} else {
						// Hide the company field when the checkbox is unchecked
						epistoliName.style.display = 'none';
						epistoliSurname.style.display = 'none';
						epistoliEmail.style.display = 'none';
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

			/* Reset all fields */
			.donor-box #nvm_epistoli_field,
			.donor-box #nvm_dead_field,
			.donor-box #nvm_dead_name_field,
			.donor-company-title,
			.donor-memoriam-title,
			.donor-box .epistoli-fields,
			.donor-box .memoriam-invoice-fields{
				display:none;
			}

			/* Hide fields for epistoli donor */
			.donor-box #nvm_epistoli_field,
			.donor-box #nvm_epistoli_name_field,
			.donor-box #nvm_epistoli_surname_field,
			.donor-box #nvm_epistoli_email_field{
				display:none;
			}

			.donor-box #nvm_memoriam_invoice_field,
			.donor-box #nvm_memoriam_invoice_name_field,
			.donor-box #nvm_memoriam_invoice_afm_field,
			.donor-box #nvm_memoriam_invoice_doy_field,
			.donor-box #nvm_memoriam_invoice_address_field{
				display:none;
			}

			.donor-box #nvm_company_field,
			.donor-box #nvm_company_afm_field,
			.donor-box #nvm_company_doy_field,
			.donor-box #nvm_company_name_field,
			.donor-box #nvm_company_address_field{
				display:none;
			}

			/* Show fields for corporate donor */
			.donor-box .donor-corporate .donor-company-title,
			.donor-box .donor-corporate #nvm_company_field,
			.donor-box .donor-corporate #nvm_company_afm_field,
			.donor-box .donor-corporate #nvm_company_doy_field,
			.donor-box .donor-corporate #nvm_company_name_field,
			.donor-box .donor-corporate #nvm_company_address_field{
				display:block;
			}

			/* Hide fields for Corporate donor */
			.donor-box .donor-corporate .donor-simple-title,
			.donor-box .donor-corporate #nvm_timologio_field{
				display:none;
			}

			/* Hide fields for Corporate donor in the main form */
			.donor-box .donor-corporate #nvm_address_field,
			.donor-box .donor-corporate #nvm_town_field,
			.donor-box .donor-corporate #nvm_postal_field{
				display:none;
			}

			/* Show fields for memoriam donor */
			.donor-box .donor-memoriam .donor-memoriam-title,
			.donor-box .donor-memoriam #nvm_memoriam_invoice_field,
			.donor-box .donor-memoriam #nvm_dead_name_field,
			.donor-box .donor-memoriam #nvm_epistoli_field,
			.donor-box .donor-memoriam .epistoli-fields,
			.donor-box .donor-memoriam .memoriam-invoice-fields{
				display:block;
			}

			.donor-box .donor-memoriam .donor-simple-title{
				display:none;
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
				grid-template-columns: repeat(3, 1fr); /* Creates 3 equal columns */
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
				grid-column: 1 / -1; /* Extends to all columns */
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

		$donor_type = isset( $_POST['type_of_donation'] ) ? sanitize_text_field( $_POST['type_of_donation'] ) : 'individual';

		$required_fields = array(
			'nvm_email'     => 'email',
			'nvm_name'      => 'Όνομα',
			'nvm_surname'   => 'Επίθετο',
			'nvm_telephone' => 'Τηλέφωνο',
		);

		if ( 'corporate' !== $donor_type ) {
			$required_fields['nvm_address'] = 'Διεύθυνση';
			$required_fields['nvm_town']    = 'Πόλη';
			$required_fields['nvm_postal']  = 'Ταχυδρομικός Κώδικας';
		}

		foreach ( $required_fields as $field => $error ) {
			if ( empty( $_POST[ $field ] ) ) {
				wc_add_notice( sprintf( __( 'The field "%s" is required.', 'nevma-donor' ), $error ), 'error' );
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

		// if ( isset( $_POST['donation_form_nonce_field'] ) && ! wp_verify_nonce( $_POST['donation_form_nonce_field'], 'donation_form_nonce' ) ) {

		// wc_add_notice( __( 'Η επαλήθευση του nonce απέτυχε. Παρακαλώ δοκιμάστε ξανά.', 'nevma-donor' ), 'error' );
		// return $cart_item_data;
		// }

		if ( ! isset( $_POST['type_of_donation'] ) ) {
			return $cart_item_data;
		}

		if ( isset( $_POST['type_of_donation'] ) ) {

			$cart_item_data['type_of_donation'] = $_POST['type_of_donation'];

			// Validate required fields.
			$required_fields = array(
				'nvm_email'   => __( 'Email is required.', 'nevma-donor' ),
				'nvm_name'    => __( 'Name is required.', 'nevma-donor' ),
				'nvm_surname' => __( 'Surname is required.', 'nevma-donor' ),
			);

			foreach ( $required_fields as $field => $error_message ) {
				if ( ! isset( $_POST[ $field ] ) || empty( $_POST[ $field ] ) ) {
					return $cart_item_data;
				}
			}
		}

		if ( isset( $_POST['nvm_epistoli'] ) ) {
			$cart_item_data['epistoli_name']    = $_POST['nvm_name_company'];
			$cart_item_data['epistoli_surname'] = $_POST['nvm_surname_company'];
			$cart_item_data['epistoli_email']   = $_POST['nvm_email_company'];
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

		// Corporate donor
		if ( isset( $_POST['type_of_donation'] ) && 'corporate' === $_POST['type_of_donation'] ) {
			$cart_item_data['timologio_company'] = $_POST['nvm_company_name'];
			$cart_item_data['timologio_afm']     = $_POST['nvm_company_afm'];
			$cart_item_data['timologio_doy']     = $_POST['nvm_company_doy'];
			$cart_item_data['timologio_address'] = $_POST['nvm_company_address'];
		}

		// Memoriam donor
		if ( isset( $_POST['type_of_donation'] ) && 'memoriam' === $_POST['type_of_donation'] && isset( $_POST['nvm_memoriam_invoice'] ) ) {
			$cart_item_data['memoriam_invoice_name']    = $_POST['nvm_memoriam_invoice_name'];
			$cart_item_data['memoriam_invoice_afm']     = $_POST['nvm_memoriam_invoice_afm'];
			$cart_item_data['memoriam_invoice_doy']     = $_POST['nvm_memoriam_invoice_doy'];
			$cart_item_data['memoriam_invoice_address'] = $_POST['nvm_memoriam_invoice_address'];
		}

		// Memoriam donor
		if ( isset( $_POST['type_of_donation'] ) && 'memoriam' === $_POST['type_of_donation'] && isset( $_POST['nvm_epistoli'] ) ) {
			$cart_item_data['memoriam_name']    = $_POST['nvm_epistoli_name'];
			$cart_item_data['memoriam_surname'] = $_POST['nvm_epistoli_surname'];
			$cart_item_data['memoriam_email']   = $_POST['nvm_epistoli_email'];
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

		if ( WC() && WC()->cart && ! WC()->cart->is_empty() ) {
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
			'type_of_donation'         => __( 'Τύπος Δωρεάς', 'nevma-donor' ),
			'epistoli_name'            => __( 'Όνομα Επιστολής', 'nevma-donor' ),
			'epistoli_surname'         => __( 'Επώνυμο Επιστολής', 'nevma-donor' ),
			'epistoli_position'        => __( 'Θέση Επιστολής', 'nevma-donor' ),
			'epistoli_email'           => __( 'Email Επιστολής', 'nevma-donor' ),
			'user_email'               => __( 'Email Δωρητή', 'nevma-donor' ),
			'user_name'                => __( 'Όνομα Δωρητή', 'nevma-donor' ),
			'user_surname'             => __( 'Επώνυμο Δωρητή', 'nevma-donor' ),
			'user_address'             => __( 'Διεύθυνση Δωρητή', 'nevma-donor' ),
			'user_town'                => __( 'Πόλη Δωρητή', 'nevma-donor' ),
			'user_postal'              => __( 'Ταχυδρομικός Κώδικας Δωρητή', 'nevma-donor' ),
			'user_telephone'           => __( 'Τηλέφωνο Δωρητή', 'nevma-donor' ),
			'dead_name'                => __( 'Όνομα Αποθανόντος', 'nevma-donor' ),
			'dead_relative'            => __( 'Συγγένεια Αποθανόντος', 'nevma-donor' ),
			'dead_message'             => __( 'Μήνυμα Αποθανόντος', 'nevma-donor' ),
			'timologio_company'        => __( 'Εταιρεία Τιμολογίου', 'nevma-donor' ),
			'timologio_afm'            => __( 'ΑΦΜ Τιμολογίου', 'nevma-donor' ),
			'timologio_doy'            => __( 'ΔΟΥ Τιμολογίου', 'nevma-donor' ),
			'timologio_address'        => __( 'Διεύθυνση Τιμολογίου', 'nevma-donor' ),
			'nvm_radio_choice'         => __( 'Ποσό Δωρεάς', 'nevma-donor' ),
			'memoriam_invoice_name'    => __( 'Όνομα Δωρεάς εις μνήμη', 'nevma-donor' ),
			'memoriam_invoice_afm'     => __( 'ΑΦΜ Δωρεάς εις μνήμη', 'nevma-donor' ),
			'memoriam_invoice_doy'     => __( 'ΔΟΥ Δωρεάς εις μνήμη', 'nevma-donor' ),
			'memoriam_invoice_address' => __( 'Διεύθυνση Δωρεάς εις μνήμη', 'nevma-donor' ),
			'memoriam_name'            => __( 'Όνομα συγγενούς', 'nevma-donor' ),
			'memoriam_surname'         => __( 'Επώνυμο συγγενούς', 'nevma-donor' ),
			'memoriam_email'           => __( 'Email συγγενούς', 'nevma-donor' ),
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
		if ( WC() && WC()->cart ) {
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
	}

	public function redirect_to_checkout_for_specific_product( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		// Target specific product for donations.
		$product = wc_get_product( $product_id );

		if ( $this->product_is_donor( $product ) ) {
			// Get the checkout URL
			$checkout_url = wc_get_checkout_url();
			$cart_url     = wc_get_cart_url();

			// if they are more than 1 product in the cart then redirect to the checkout page
			if ( WC()->cart->get_cart_contents_count() > 1 ) {
				wp_safe_redirect( $cart_url );
				exit;
			}

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
		if ( WC() && WC()->cart ) {
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

				// Remove order comments/additional information field
				unset( $fields['order']['order_comments'] );

			}
		}

		return $fields;
	}

	/**
	 * Add donor class to checkout if cart contains donor products
	 */
	public function add_donor_class_to_checkout( $classes ) {
		// Get cart items
		if ( WC() && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();

			// Check if any cart item is a donor product
			foreach ( $cart_items as $cart_item ) {
				$product = $cart_item['data'];
				if ( $this->product_is_donor( $product ) ) {
					$classes[] = 'has-donor-product';
					break;
				}
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
			return __( 'Product ID is required', 'nevma-donor' );
		}

		// Fetch product without affecting global $product.
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $this->product_is_donor( $product ) ) {
			return __( 'Invalid donor product', 'nevma-donor' );
		}

		// Capture the output of the donation form.
		ob_start();
		echo '<div class="container-donor-box">';
		$this->add_donation_carts( $product );
		// echo nonce field
		echo '</div>';
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

	/**
	 * Change the visible <h1> title on the checkout page if donor product is in cart.
	 *
	 * @param string $title The current post/page title.
	 * @param int    $post_id The ID of the post.
	 * @return string The modified title.
	 */
	public function nvm_change_checkout_title_h1( $title, $post_id ) {
		if ( ! is_checkout() || is_wc_endpoint_url() || ! WC() || ! WC()->cart || WC()->cart->is_empty() ) {
			return $title;
		}

		foreach ( WC()->cart->get_cart() as $item ) {

			$product_id = $item['product_id'];
			$product    = wc_get_product( $product_id );

			if ( $this->product_is_donor( $product ) ) {
				// Optional: Confirm it's the checkout page object
				$checkout_page_id = wc_get_page_id( 'checkout' );
				if ( intval( $post_id ) === $checkout_page_id ) {
					return __( 'Complete Donation', 'nevma-donor' );
				}
			}
		}

		return $title;
	}

	/**
	 * Change thank you message if donor product is in the order.
	 *
	 * @param string   $text Original thank you text.
	 * @param WC_Order $order WooCommerce order object.
	 * @return string
	 */
	public function nvm_thank_you_message_for_donor( $text, $order ) {
		if ( ! $order || is_wp_error( $order ) ) {
			return $text;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $this->product_is_donor( $product ) ) {

				// if payment method is bank then return the thank you message
				if ( $order->get_payment_method() === 'bacs' ) {
					return __( 'Ευχαριστούμε πολύ, για να ολοκληρώσετε τη δωρεά σας παρακαλούμε προχωρήστε σε κατάθεση σε έναν από τους παρακάτω τραπεζικούς λογαριασμούς.', 'nevma-donor' );
				}

				if ( $order->get_payment_method() !== 'bacs' ) {
					return __( 'Σας ευχαριστούμε. Έχουμε λάβει την Δωρεά σας.', 'nevma-donor' );
				}
			}
		}

		return $text;
	}

	public function nvm_start_html_replacement_buffer() {

		if ( ! is_admin() ) {
			// if is thank you page or checkout page
			if ( is_wc_endpoint_url( 'order-received' ) || is_checkout() ) {
				ob_start( array( $this, 'nvm_apply_html_replacements' ) );
			}
		}
	}

	public function nvm_apply_html_replacements( $html ) {

		// if is thank you page then get the order id and check if it contains a donor product
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			if ( isset( $_GET['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) );
				$order    = wc_get_order( $order_id );
				if ( $order ) {
					foreach ( $order->get_items() as $item ) {
						$product = $item->get_product();
						if ( $this->product_is_donor( $product ) ) {
							$html = $this->nvm_replace_words_in_html( $html );
							return $html;
						}
					}
				}
			}
		}

		// if is checkout page then check if the cart contains a donor product
		if ( is_checkout() ) {
			if ( $this->cart_has_donor_product() ) {
				$html = $this->nvm_replace_words_in_html( $html );
				return $html;
			}
		}

		return $html;
	}

	/**
	 * Replace specific words in HTML for donor products.
	 *
	 * @param string $html The original HTML content.
	 * @return string The modified HTML content.
	 */

	public function nvm_replace_words_in_html( $html ) {
		// Replacements for hardcoded text that gettext filter doesn't catch

		// Use regex to replace text regardless of HTML structure
		$html = preg_replace( '/Ολοκλήρωση Πληρωμής/u', 'Ολοκλήρωση Δωρεάς', $html );
		$html = preg_replace( '/Checkout/i', 'Complete Donation', $html );

		return $html;
	}

	/**
	 * Simple unified translation function for all donor product texts.
	 * Translates WooCommerce strings when a donor product is in the cart.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $text            Original text.
	 * @param string $domain          Text domain.
	 * @return string Modified translated text.
	 */
	public function nvm_translate_donor_texts( $translated_text, $text, $domain ) {
		// Only run on frontend after cart is loaded
		if ( is_admin() || ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
			return $translated_text;
		}

		// Check if cart has donor product
		if ( ! $this->cart_has_donor_product() ) {
			return $translated_text;
		}

		// Translation map - add all your translations here
		$translations = array(
			// Greek translations
			'Ολοκλήρωση Πληρωμής'                                                          => 'Ολοκλήρωση Δωρεάς',
			'Checkout'                                                                     => 'Ολοκλήρωση Δωρεάς',
			'παραγγελία'                                                                   => 'δωρεά',
			'Παραγγελία'                                                                   => 'Δωρεά',
			'Πληρωμή'                                                                      => 'Δωρεά',
			'Προϊόν'                                                                       => 'Δωρεά',
			'Προϊόντα'                                                                     => 'Δωρεές',
			'Παρακαλούμε διαβάστε και αποδεχτείτε τους όρους και προϋποθέσεις'             => 'Παρακαλούμε διαβάστε και αποδεχτείτε τους όρους και προϋποθέσεις για να συνεχίσετε με τη Δωρεά σας.',

			// English translations
			'Place order'                                                                  => 'Complete Donation',
			'Order'                                                                        => 'Donation',
			'Product'                                                                      => 'Donation',
			'Products'                                                                     => 'Donations',
			'Please read and accept the terms and conditions to proceed with your order.'  => 'Please read and accept the terms and conditions to proceed with your donation.',
		);

		// Return translation if exists
		if ( isset( $translations[ $text ] ) ) {
			return $translations[ $text ];
		}

		// Return original translation if no match
		return $translated_text;
	}

	/**
	 * Translation function with context support.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $text            Original text.
	 * @param string $context         Context.
	 * @param string $domain          Text domain.
	 * @return string Modified translated text.
	 */
	public function nvm_translate_donor_texts_with_context( $translated_text, $text, $context, $domain ) {
		// Use the same logic as nvm_translate_donor_texts
		return $this->nvm_translate_donor_texts( $translated_text, $text, $domain );
	}

	/**
	 * Helper function to check if cart has donor product.
	 *
	 * @return bool True if cart has donor product, false otherwise.
	 */
	private function cart_has_donor_product() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $this->product_is_donor( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Change page title directly for checkout page.
	 *
	 * @param string $title The page title.
	 * @param int    $id The post ID.
	 * @return string Modified title.
	 */
	public function nvm_change_page_title( $title, $id = null ) {
		// Only on checkout page
		if ( ! is_checkout() || is_admin() ) {
			return $title;
		}

		// Check for donor product
		if ( ! $this->cart_has_donor_product() ) {
			return $title;
		}

		// Replace the title
		if ( $title === 'Ολοκλήρωση Πληρωμής' || $title === 'Checkout' ) {
			return get_locale() === 'el_GR' || strpos( get_locale(), 'el' ) !== false
				? 'Ολοκλήρωση Δωρεάς'
				: 'Complete Donation';
		}

		return $title;
	}

	/**
	 * Change WooCommerce page title for checkout.
	 *
	 * @param string $title The page title.
	 * @return string Modified title.
	 */
	public function nvm_change_woo_page_title( $title ) {
		// Only on checkout page
		if ( ! is_checkout() || is_admin() ) {
			return $title;
		}

		// Check for donor product
		if ( ! $this->cart_has_donor_product() ) {
			return $title;
		}

		// Return translated title
		return get_locale() === 'el_GR' || strpos( get_locale(), 'el' ) !== false
			? 'Ολοκλήρωση Δωρεάς'
			: 'Complete Donation';
	}

	/**
	 * JavaScript failsafe to replace checkout page title.
	 * This runs as a last resort if all PHP filters fail.
	 */
	public function nvm_js_title_replacement() {
		// Only on checkout page
		if ( ! is_checkout() || is_admin() ) {
			return;
		}

		// Check for donor product
		if ( ! $this->cart_has_donor_product() ) {
			return;
		}

		$new_title = get_locale() === 'el_GR' || strpos( get_locale(), 'el' ) !== false
			? 'Ολοκλήρωση Δωρεάς'
			: 'Complete Donation';
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			console.log('🔄 NVM Donor: JavaScript title replacement loaded');
			console.log('🎯 NVM Donor: New title will be: <?php echo esc_js( $new_title ); ?>');

			var replacementCount = 0;

			// Replace in all h1 elements
			document.querySelectorAll('h1').forEach(function(h1) {
				console.log('🔍 NVM Donor: Checking h1:', h1.textContent.trim());
				if (h1.textContent.includes('Ολοκλήρωση Πληρωμής') || h1.textContent.includes('Checkout')) {
					console.log('✅ NVM Donor: Found matching h1! Replacing...');
					h1.textContent = '<?php echo esc_js( $new_title ); ?>';
					replacementCount++;
				}
			});

			// Replace in breadcrumbs
			document.querySelectorAll('a, span').forEach(function(el) {
				if (el.textContent === 'Ολοκλήρωση Πληρωμής' || el.textContent === 'Checkout') {
					console.log('✅ NVM Donor: Found matching breadcrumb! Replacing:', el.textContent);
					el.textContent = '<?php echo esc_js( $new_title ); ?>';
					replacementCount++;
				}
			});

			console.log('✨ NVM Donor: Replaced ' + replacementCount + ' elements');
		});
		</script>
		<?php
	}
}
