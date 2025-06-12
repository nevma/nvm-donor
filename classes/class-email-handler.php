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
			'cancelled_order',
			'failed_order',
		);

		foreach ( $email_keys as $key ) {
			add_filter( "woocommerce_email_subject_{$key}", array( $this, 'replace_order_with_donation_in_subject' ), 10, 2 );
			add_filter( "woocommerce_email_heading_{$key}", array( $this, 'replace_order_with_donation_in_heading' ), 10, 2 );
		}

		add_filter( 'woocommerce_mail_content', array( $this, 'replace_order_with_donation_in_content' ), 10, 2 );

		// Disable unnecessary emails for donation orders
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'disable_email_for_donation' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( $this, 'disable_email_for_donation' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_invoice', array( $this, 'disable_email_for_donation' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'disable_email_for_donation' ), 10, 2 );
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
			'Παραγγελία'               => 'Δωρεά',
			'παραγγελίας'              => 'δωρεάς',
			'Αριθμός Παραγγελίας'      => 'Αριθμός Δωρεάς',
			'Λεπτομέρειες Παραγγελίας' => 'Λεπτομέρειες Δωρεάς',
			'Η παραγγελία σας'         => 'Η δωρεά σας',
			'παραγγελία σας'           => 'δωρεά σας',
			'Την παραγγελία σας'       => 'Τη δωρεά σας',
		);

		return strtr( $text, $replacements );
	}

	/**
	 * Replace content in email subject.
	 */
	public function replace_order_with_donation_in_subject( $subject, $order ) {
		if ( $order instanceof WC_Order && $this->order_contains_donation( $order ) ) {
			$subject = $this->replace_order_phrases( $subject );
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
	 */
	public function replace_order_with_donation_in_content( $content, $email ) {
		$order = $email->object;

		if ( $order instanceof WC_Order && $this->order_contains_donation( $order ) ) {
			$content = $this->replace_order_phrases( $content );
		}

		return $content;
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
