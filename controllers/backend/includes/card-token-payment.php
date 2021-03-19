<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once( 'config.php' );

use Paysafe\PaysafeApiClient;
use Paysafe\Environment;
use Paysafe\CardPayments\Authorization;
use Paysafe\CustomerVault\Profile;
use Paysafe\CustomerVault\Address;
use Paysafe\CustomerVault\Card;
use Paysafe\CustomerVault\Mandates;

class WC_Gateway_Paysafe_Request {


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
	 * Is $order_id a subscription?
	 *
	 * @param  int $order_id
	 *
	 * @return boolean
	 */
	protected function is_renewal( $order_id ) {
		if (!function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return wcs_order_contains_renewal( $order_id );
	}

	/**
	 * Payment via credit card request to the gateway.
	 *
	 * @param  $order
	 * @param  $paysafeApiKeyId
	 * @param  $paysafeApiKeySecret
	 * @param  $paysafeAccountNumber
	 * @param  $environment
	 * @param  $totalAmount
	 * @param  $cardNumber
	 * @param  $cardMonth
	 * @param  $cardYear
	 * @param  $cardCvv
	 * @param  $billing_address_1
	 * @param  $billing_country
	 * @param  $billing_city
	 * @param  $billing_postcode
	 * @param  $currencyBaseUnitsMultiplier
	 * @param  $tokenRequest
	 * @param  $fname
	 * @param  $lname
	 * @param  $email
	 * @param  $paysafeMethod
	 *
	 * @return array
	 */
	public function get_request_paysafe_url_cc( $order_id, $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $cardNumber, $cardMonth, $cardYear, $cardCvv, $billing_address_1, $billing_country, $billing_city, $billing_postcode, $currencyBaseUnitsMultiplier, $tokenRequest, $fname, $lname, $email, $billing_phone, $paysafeMethod, $authCaptureSettlement, $threed_auth_data = null ) {
		$is_subscription = $this->is_subscription( $order_id );
		$environmentType = $environment == 'LIVE' ? Environment::LIVE : Environment::TEST;
		$settleWithAuth  = $authCaptureSettlement == 'yes' ? true : false;
		$client          = new PaysafeApiClient( $paysafeApiKeyId, $paysafeApiKeySecret, $environmentType, $paysafeAccountNumber );
		$responsearray   = [];
		try {
			if ( $tokenRequest === "token" ) {

				$profile = $client->customerVaultService()->createProfile( new Profile( array(
					"merchantCustomerId" => uniqid( 'cust-' . date( 'm/d/Y h:i:s a', time() ) ),
					"locale"             => "en_US",
					"firstName"          => $fname,
					"lastName"           => $lname,
					"email"              => $email
				) ) );


				$address = $client->customerVaultService()->createAddress( new Address( array(
					"nickName"  => "home",
					'street'    => $billing_address_1,
					'city'      => $billing_city,
					'country'   => $billing_country,
					'zip'       => $billing_postcode,
					"profileID" => $profile->id
				) ) );
				$card_request = array(
					"nickName"         => "Default Card",
					"holderName"         => $fname . ' ' . $lname,
					'cardNum'          => $cardNumber,
					'cardExpiry'       => array(
						'month' => $cardMonth,
						'year'  => $cardYear
					),
					'billingAddressId' => $address->id,
					"profileID"        => $profile->id
				);

				$card = $client->customerVaultService()->createCard( new \Paysafe\CustomerVault\Card(
					$card_request
					)
				);


				/*
				$responsearray = array(
					'transaction_id' => $auth->id,
					'status'         => $auth->status,
					'merchantrefnum' => $auth->merchantRefNum,
					'txtime'         => $auth->txnTime,
					'tokenreq'       => $tokenRequest,
					'tokenkey'       => $profile->paymentToken,
					'responsecode'   => 0
				);
				*/
				wc_paysafe_log($profile);
				$responsearray = array_merge( $responsearray, array(
					'tokenreq'     => $tokenRequest,
					'tokenkey'     => $profile->paymentToken,
					'cardbin'     => $card->cardBin,
					'cardpaymenttoken'     => $card->paymentToken,
					'cardid'     => $card->id,
					'profileid'     => $profile->id,
					'responsecode' => 0
				) );
			}
			////
			{
				$auth_params = array(
					'merchantRefNum' => $order_id . '_' . date( 'm/d/Y h:i:s a', time() ),
					'amount'         => $totalAmount * $currencyBaseUnitsMultiplier,
					'settleWithAuth' => $settleWithAuth,
					'card'           => array(
						'cardNum'    => $cardNumber,
						'cvv'        => $cardCvv,
						'cardExpiry' => array(
							'month' => $cardMonth,
							'year'  => $cardYear
						)
					),
					'customerIp'     => $_SERVER['REMOTE_ADDR'],
					'profile'        => array(
						"firstName" => $fname,
						"lastName"  => $lname,
						"email"     => $email,
					),
					'billingDetails' => array(
						"street"  => $billing_address_1,
						"city"    => $billing_city,
						"country" => $billing_country,
						'zip'     => $billing_postcode,
						'phone'   => $billing_phone,
					)
				);

				if ($is_subscription) {
					$auth_params['storedCredential'] = [
						"type" => "RECURRING",
						"occurrence" => "INITIAL"
					];
				}

				// Verify 3ds
				if ( ! empty( $threed_auth_data ) ) {
					$auth_params['authentication'] = $threed_auth_data;
				}

				wc_paysafe_log($auth_params);
				$auth = $client->cardPaymentService()->authorize( new Authorization( $auth_params ) );

				$responsearray = array_merge( $responsearray, array(
					'transaction_id' => $auth->id,
					'status'         => $auth->status,
					'merchantrefnum' => $auth->merchantRefNum,
					'txtime'         => $auth->txnTime,
					'tokenreq'       => $tokenRequest,
					'responsecode'   => 0
				) );

				return $responsearray;
			}
		} catch ( Paysafe\PaysafeException $e ) {

			if ( $environment != 'LIVE' ) {
				$failedMessage = '';
				if ( $e->error_message ) {
					$failedMessage .= $e->error_message . "<br>";
				}
				if ( $e->fieldErrors ) {
					foreach ( $e->fieldErrors as $message ) {
						$failedMessage .= $message['field'] . "-->" . $message['error'] . "<br>";
					}
				}
				if ( $e->links ) {
					foreach ( $e->links as $message ) {
						$failedMessage .= "error_info link --> " . $message['href'] . "<br>";
					}
				}
				$responsearray = array( 'status' => "failed", 'responsecode' => 1, 'errormessage' => $failedMessage );
			} else {
				$responsearray = array( 'status' => "failed", 'responsecode' => 1 );
			}

			return $responsearray;
		}
	}

	/**
	 * Payment via token request to the gateway.
	 *
	 * @param  $paysafeApiKeyId
	 * @param  $paysafeApiKeySecret
	 * @param  $paysafeAccountNumber
	 * @param  $environment
	 * @param  $totalAmount
	 * @param  $currencyBaseUnitsMultiplier
	 * @param  $tokenKeyId
	 * @param  $threed_auth_data
	 *
	 * @return array
	 */
	public function get_request_paysafe_url_token( $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $order_id, $threed_auth_data ) {
		$environmentType = $environment == 'LIVE' ? Environment::LIVE : Environment::TEST;
		$settleWithAuth  = $authCaptureSettlement == 'yes' ? true : false;
		$is_subscription = $this->is_subscription( $order_id );
		$client          = new PaysafeApiClient( $paysafeApiKeyId, $paysafeApiKeySecret, $environmentType, $paysafeAccountNumber );

		try {
			$request_params = array(
				'merchantRefNum'   => $order_id . '_' . date( 'm/d/Y h:i:s a', time() ),
				'amount'           => $totalAmount * $currencyBaseUnitsMultiplier,
				'settleWithAuth'   => $settleWithAuth,
				'card'             => array(
					'paymentToken' => $tokenKeyId
				),
			);

			// Verify 3ds
			if ( ! empty( $threed_auth_data ) ) {
				$request_params['authentication'] = $threed_auth_data;
			}

			if ( $is_subscription ) {
				if ( $this->is_renewal( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
					foreach ($subscriptions as $subscription) {
						/** @var WC_Subscription $subscription */
						$init_transaction = $subscription->get_meta('paysafe_init_transaction_id');
						$request_params['storedCredential'] = [
							"type" => "RECURRING",
							"occurrence" => "SUBSEQUENT",
							"initialTransactionId" => $init_transaction,
						];
					}
				} else {
					$request_params['storedCredential'] = [
						"type" => "RECURRING",
						"occurrence" => "INITIAL"
					];
				}
			}
			wc_paysafe_log($request_params);
			$auth          = $client->cardPaymentService()->authorize( new Authorization( $request_params ) );
			$responsearray = array(
				'transaction_id' => $auth->id,
				'status'         => $auth->status,
				'merchantrefnum' => $auth->merchantRefNum,
				'txtime'         => $auth->txnTime,
				'responsecode'   => 0
			);

			return $responsearray;
		} catch ( Paysafe\PaysafeException $e ) {
			if ( $environment != 'LIVE' ) {
				$failedMessage = '';
				if ( $e->error_message ) {
					$failedMessage .= $e->error_message;
				}
				if ( $e->fieldErrors ) {
					foreach ( $e->fieldErrors as $message ) {
						$failedMessage .= $message['field'] . "-->" . $message['error'] . "<br>";
					}
					$responsearray = array(
						'status'       => "failed",
						'responsecode' => 1,
						'errormessage' => $failedMessage
					);
				}
			} else {
				$responsearray = array( 'status' => "failed", 'responsecode' => 1 );
			}

			return $responsearray;

		}
	}


}
