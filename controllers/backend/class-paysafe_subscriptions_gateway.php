<?php
/**
 * Paysafe Subscription Gateway
 *
 * Provides a Paysafe Payment Gateway for Subscriptions.
 *
 * @class       Paysafe_Subscriptions_Gateway
 * @extends     Paysafe_Gateway_Init
 * @package     WooCommerce/Classes/Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Paysafe_Subscriptions_Gateway extends Paysafe_Gateway_Init {

	public $wc_pre_30;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Hooks
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
			$this,
			'scheduled_subscription_payment'
		), 10, 2 );

		add_action( 'woocommerce_payment_complete', [ $this, 'update_subscription_order' ] );
		add_action( 'woocommerce_order_status_processing', array( $this, 'update_subscription_order' ) );
	}

	public function update_subscription_order( $order_id ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return;
		}
		
		$subscriptions   = wcs_get_subscriptions_for_order( $order_id );
		$is_subscription = wcs_order_contains_subscription( $order_id );

		if ( ! $is_subscription ) {
			return;
		}

		foreach ( $subscriptions as $subscription ) {
			if ( !$this->is_renewal( $order_id ) ) {
				/** @var WC_Subscription $subscription */
				$subscription->update_meta_data('paysafe_init_transaction_id', get_post_meta($order_id, '_paysafe_transaction_id', true));
			}
			$subscription->payment_complete();
		}
	}


	/**
	 * Is $order_id a subscription?
	 *
	 * @param  int $order_id
	 *
	 * @return boolean
	 */
	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Process the payment based on type.
	 *
	 * @param  int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {

		if ( $this->is_subscription( $order_id ) ) {
			// Regular payment with force customer enabled
			return parent::process_payment( $order_id, true, true );
		} else {
			return parent::process_payment( $order_id, $retry, $force_customer );
		}

	}


	/**
	 * Process the subscription payment and return the result
	 *
	 * @access      public
	 *
	 * @param       int $amount
	 *
	 * @return      array
	 */
	public function process_subscription_payment( $amount = 0, $order ) {


		$customer_orders = get_posts( array(
			'numberposts' => - 1,
			'meta_key'    => '_customer_user',
			'meta_value'  => $order->get_customer_id(),
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
		) );

		$i = 0;
		foreach ( $customer_orders as $oid ) {
			if ( metadata_exists( 'post', $oid->ID, '_paysafe_token_card_info' ) ) {
				$listcard[ $i ] = get_post_meta( $oid->ID, '_paysafe_token_card_info' );
			}
			$i ++;
		}

		if ( ! empty( $listcard ) ) {

			foreach ( $listcard as $cards ) {
				$tokenKeyId = $cards[0]['paysafe_tokenno'];
			}

			// Include the paysafe.com payment files.
			include_once( dirname( __FILE__ ) . '/includes/card-token-payment.php' );
			$totalAmount = (int) str_replace( array( ' ', ',' ), '', $order->total );

			$paysafe_request_object = new WC_Gateway_Paysafe_Request;

			$corder_id       = $order->get_id();
			$paysafeApiKeyId = $this->api_login;

			$paysafeApiKeySecret         = $this->trans_key;
			$paysafeAccountNumber        = $this->acc_number;
			$currencyBaseUnitsMultiplier = $this->currency_base_units_multiplier;
			$authCaptureSettlement       = $this->auth_capture_settlement;
			$environment                 = ( $this->environment == "yes" ) ? 'TEST' : 'LIVE';

			$paysafe_request = $paysafe_request_object->get_request_paysafe_url_token( $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $corder_id );

			if ( $paysafe_request['responsecode'] == '0' ) {
				$transactionID        = $paysafe_request['transaction_id'];
				$this->transaction_id = $transactionID;
				$status               = $paysafe_request['status'];
				update_post_meta( $order->get_id(), '_paysafe_status', $status );
				update_post_meta( $order->get_id(), '_paysafe_transaction_id', $transactionID );
				$this->order_complete();

				return true;
			} else {

				update_post_meta( $order->get_id(), '_paysafe_status_error', print_r( $paysafe_request, 1 ) );

				return false;
			}

		} else {

			$this->payment_failed_save_cards();

			return false;
		}


	}


	/**
	 * Process a scheduled payment
	 *
	 * @access      public
	 *
	 * @param       float $amount_to_charge
	 * @param       WC_Order $order
	 * @param       int $product_id
	 *
	 * @return      void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id = '' ) {
		$this->order = $order;

		$charge = $this->process_subscription_payment( $amount_to_charge, $order );
		if ( $charge ) {
			$subscription_id = $this->order->get_meta( '_subscription_renewal' );
			if ( ! empty( $subscription_id ) ) {
				$subscription = wcs_get_subscription( $subscription_id );
				$subscription->payment_complete( $this->transaction_id );
			}
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		} else {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		}
	}


	/**
	 * Mark the payment as failed in the order notes
	 *
	 * @access      protected
	 * @return      void
	 */
	protected function payment_failed_save_cards() {
		$this->order->add_order_note(
			sprintf(
				__( 'Save Card Not found for this User', 'mer-paysafepayments-aim' ),
				get_class( $this ),
				$this->transaction_error_message
			)
		);
	}


}
