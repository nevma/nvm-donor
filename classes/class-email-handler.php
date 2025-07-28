<?php
/**
 * Handles donation-related email content customization.
 *
 * @package Nvm\Donor
 */

namespace Nvm\Donor;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Handler {

	public function __construct() {
		$this->register_hooks();

		add_filter(
			'woocommerce_email_enabled_customer_processing_order',
			function ( $enabled, $order ) {
				if ( $order instanceof WC_Order ) {
					foreach ( $order->get_items() as $item ) {
						$product = $item->get_product();
						if ( $product && \Nvm\Donor\Product_Donor::is_donor_product( $product ) ) {
							return false;
						}
					}
				}
				return $enabled;
			},
			10,
			2
		);
	}

	/**
	 * Register all necessary hooks for email customization.
	 */
	protected function register_hooks() {
		$email_keys = array(
			'new_order',
			'customer_processing_order',
			'customer_completed_order',
			'customer_on_hold_order',
			'customer_invoice',
			'customer_refunded_order',
			// 'cancelled_order',
			// 'failed_order',
		);

		foreach ( $email_keys as $key ) {
			add_filter( "woocommerce_email_subject_{$key}", array( $this, 'replace_order_with_donation_in_subject' ), 10, 2 );
			add_filter( "woocommerce_email_heading_{$key}", array( $this, 'replace_order_with_donation_in_heading' ), 10, 2 );
		}

		add_filter( 'woocommerce_mail_content', array( $this, 'replace_order_with_donation_in_content' ), 10, 1 );
		add_action( 'woocommerce_email_order_meta', array( $this, 'add_donor_message_to_email' ), 20, 4 );

		// Add the wp_mail filter for comprehensive text replacement
		add_filter( 'wp_mail', array( $this, 'nvm_filter_entire_email_content' ), 10, 1 );
	}

	/**
	 * Check if order contains a donor product.
	 *
	 * @param WC_Order $order The order object.
	 * @return bool
	 */
	protected function order_contains_donation( WC_Order $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && Product_Donor::is_donor_product( $product ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Replace Greek phrases related to "order" with "donation".
	 *
	 * @param string $text
	 * @return string
	 */
	protected function replace_order_phrases( $text ) {
		$replacements = array(
			'Παραγγελία'                      => 'Δωρεά',
			'παραγγελίας'                     => 'Δωρεάς',
			'Αριθμός Παραγγελίας'             => 'Αριθμός Δωρεάς',
			'Λεπτομέρειες Παραγγελίας'        => 'Λεπτομέρειες Δωρεάς',
			'Λεπτομέρειες για την παραγγελία' => 'Λεπτομέρειες για τη Δωρεά σας',
			'Λεπτομέρειες της παραγγελίας'    => 'Λεπτομέρειες της Δωρεάς',
			'παραγγελία σας'                  => 'δωρεά σας',
			'Την παραγγελία σας'              => 'Τη δωρεά σας',
			'Προϊόν'                          => 'Δωρεά',
			'Ποσότητα'                        => 'Ποσό',
			'Σύνολο'                          => 'Συνολικό Ποσό Δωρεάς',

			// Συγκεκριμένες φράσεις που αφαιρούνται
			'Έχουμε λάβει τη δωρεά σας! ολοκληρώσει την επεξεργασία της παραγγελίας σας.' => 'Σας ευχαριστούμε θερμά! Η δωρεά σας καταχωρήθηκε με επιτυχία.',
			'Η πληρωμή σας ολοκληρώθηκε.'     => '', // Αφαίρεση τελείως
		);

		return strtr( $text, $replacements );
	}


	/**
	 * Replace Greek phrases related to "order" with "donation".
	 *
	 * @param string $text
	 * @return string
	 */
	protected function replace_order_phrases_on_bank( $text ) {
		$replacements = array(
			'Λεπτομέρειες της παραγγελίας που κάνατε' => 'Λεπτομέρειες της Δωρεάς με πληρωμή μέσω τραπέζας',
		);

		return strtr( $text, $replacements );
	}

	/**
	 * Replace Greek phrases related to "order" with "donation".
	 *
	 * @param string $text
	 * @return string
	 */
	protected function replace_order_phrases_completed( $text ) {

		// i want to remove the line "Η πληρωμή σας ολοκληρώθηκε." only on the second find
		$text = preg_replace( '/Η πληρωμή σας ολοκληρώθηκε\./', '', $text, 2 );

		$replacements = array(
			'Έχουμε ολοκληρώσει την επεξεργασία της Δωρεάς σας.' => 'Έχουμε λάβει τη δωρεά σας! ',
			'Η Πληρωμή σας ολοκληρώθηκε!' => 'Η δωρεά σας στο «Άλμα Ζωής» ολοκληρώθηκε, ευχαριστούμε πολύ!', // Αφαίρεση τελείως
		);

		return strtr( $text, $replacements );
	}


	public function add_donor_message_to_email( $order, $sent_to_admin, $plain_text, $email ) {

		if ( $order instanceof \WC_Order && $this->order_contains_donation( $order ) ) {

			$donor_message = '
			<h2>Ευχαριστούμε πολύ που υποστηρίζετε το έργο του Πανελληνίου Συλλόγου Γυναικών με Καρκίνο Μαστού «Άλμα Ζωής».</h2>
			<p>Μέσα από τη δωρεά σας μας βοηθάτε να υλοποιούμε:</p>
			<ul>
				<li>προγράμματα πρόληψης και έγκαιρης διάγνωσης</li>
				<li>υποστήριξη για γυναίκες που έχουν βιώσει καρκίνο του μαστού</li>
				<li>διεκδίκηση των δικαιωμάτων των ασθενών</li>
			</ul>
			<h3>Όραμά μας: ένας κόσμος χωρίς θανάτους από καρκίνο του μαστού.</h3>
			<p>Ευχαριστούμε που είστε μαζί μας για να το πραγματοποιήσουμε.</p>
		';

			echo $donor_message;
		}
	}

	/**
	 * Removes email sections for donation orders.
	 *
	 * @param WC_Order    $order
	 * @param bool        $sent_to_admin
	 * @param WC_Email    $email
	 * @param string|null $plain_text
	 */
	public static function nvm_maybe_remove_email_sections( $order, $sent_to_admin, $email, $plain_text = null ) {

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			$product_id = $product->get_id();
			$product    = wc_get_product( $product_id );

			if ( $product && self::is_donor_product( $product ) ) {

				// Only modify customer emails
				if ( ! $sent_to_admin ) {
					// remove_action( 'woocommerce_email_customer_details', array( $email, 'customer_details' ), 10 );
					// remove_action( 'woocommerce_email_order_details', array( $email, 'order_details' ), 10 );
					// remove_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10 );
					// remove_action( 'woocommerce_email_order_meta', array( WC()->mailer(), 'order_meta' ), 10 );
				}
				break;
			}
		}
	}

	/**
	 * Replace content in email subject.
	 */
	public function replace_order_with_donation_in_subject( $subject, $order ) {
		if ( $order instanceof WC_Order && $this->order_contains_donation( $order ) ) {

			$subject = $this->replace_order_phrases( $subject );

			// remove_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_details' ), 10 );

			// remove the footer from the email
			remove_action( 'woocommerce_email_footer', array( WC()->mailer(), 'email_footer' ), 10 );

		}
		return $subject;
	}

	/**
	 * Replace content in email heading.
	 */
	public function replace_order_with_donation_in_heading( $heading, $order ) {
		if ( $order instanceof WC_Order && $this->order_contains_donation( $order ) ) {
			$heading = $this->replace_order_phrases( $heading );
		}
		return $heading;
	}

	/**
	 * Replace content in the email body.
	 *
	 * @param string $content The full HTML email content.
	 * @return string
	 */
	public function replace_order_with_donation_in_content( $content ) {
		if ( isset( $GLOBALS['woocommerce_mail_callback_params']['object'] ) ) {
			$order = $GLOBALS['woocommerce_mail_callback_params']['object'];

			if ( $order instanceof \WC_Order && $this->order_contains_donation( $order ) ) {
				return $this->replace_order_phrases( $content );
			}
		}

		return $content;
	}

	/**
	 * Filter all emails at the wp_mail level for comprehensive text replacement
	 * This catches any text that might be missed by the WooCommerce-specific filters
	 * Only processes emails that contain donor products (identified by "Δωρεά" in subject)
	 *
	 * @param array $args Array containing email arguments (to, subject, message, headers, attachments)
	 * @return array Modified email arguments
	 */
	public function nvm_filter_entire_email_content( $args ) {
		// Only process emails that have "Δωρεά" in the subject line
		// This ensures we only modify donor-related emails
		if ( ! isset( $args['subject'] ) || strpos( $args['subject'], 'ωρεά' ) === false ) {
			return $args;
		}

		// Certain replacements only on the email with bank details.
		if ( isset( $args['message'] ) && strpos( $args['message'], 'Τα στοιχεία της τράπεζάς μας' ) !== false ) {
			$args['message'] = $this->replace_order_phrases_on_bank( $args['message'] );
		}

		// Replace in email message body
		if ( isset( $args['message'] ) ) {
			$args['message'] = $this->replace_order_phrases( $args['message'] );
		}

		// Certain replacements only on the email with bank details.
		if ( isset( $args['message'] ) && strpos( $args['message'], 'Η Πληρωμή σας ολοκληρώθηκε!' ) !== false ) {
			$args['message'] = $this->replace_order_phrases_completed( $args['message'] );
		}

		return $args;
	}

	/**
	 * Disable certain emails if the order contains a donor product.
	 *
	 * @param bool     $enabled
	 * @param WC_Order $order
	 * @return bool
	 */
	public function disable_email_for_donation( $enabled, $order ) {
		if ( $order instanceof WC_Order && $this->order_contains_donation( $order ) ) {
			return false;
		}
		return $enabled;
	}
}
