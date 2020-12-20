<?php

use \Paysafe_Gateway_Init as Gateway;

require_once MER_PAYSAFE_DIR . '/helpers/class-threedsecure-helper.php';
require_once MER_PAYSAFE_DIR . '/helpers/class-threedsecure-logger.php';

class Paysafe_Threedsecure {
  /** @var Paysafe_Threedsecure_Helper */
  protected $helper;

	public function __construct() {
	  $this->helper = new Paysafe_Threedsecure_Helper();
	  $this->logger = new Paysafe_Threedsecure_Logger();
		$this->load_hooks();
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'woocommerce_after_checkout_form', [ $this, 'define_js_options' ] );
		add_filter( 'woocommerce_order_button_html', [ $this, 'add_paysafe_checkout_button' ] );
	}

	public function getSingleUseToken() {
	  $option = get_option( 'woocommerce_mer_paysafe_settings' );
	  if (empty($option)) return null;

	  if (
		  isset($option['single_token_password'])
		  && isset($option['single_token_username'])
		  && !empty($option['single_token_username'])
		  && !empty($option['single_token_password'])
	  ) {
		  $key = $option['single_token_username'] . ':' . $option['single_token_password'];
	  } else {
	    return null;
    }

	  return $key;
  }

	public function define_js_options() {
		$option = get_option( 'woocommerce_mer_paysafe_settings' );

		if ( empty( $option ) ) {
			return;
		}

		$threedsecure = isset( $option['threedsecure'] ) ? $option['threedsecure'] : 'no';
		$acc_number = isset( $option['acc_number'] ) ? $option['acc_number'] : '';
		$testmode = isset( $option['environment'] ) ? $option['environment'] : 'no';
	  $base64APIKey = base64_encode($this->getSingleUseToken());
		?>
      <script>
          window.PaysafeWooCommerceIntegrationOption = {
              auth_endpoint: <?php echo wp_json_encode(
				  add_query_arg( 'action', 'paysafe_threedsecure_authentication',
					  admin_url( 'admin-ajax.php' ) )
			  ); ?>,
              challenge_endpoint: <?php echo wp_json_encode(
				  add_query_arg( 'action', 'paysafe_threedsecure_challenge',
					  admin_url( 'admin-ajax.php' ) )
			  ); ?>,
          };
          window.PaysafeWooCommerceIntegrationOption.threedsecurepaysafe = '<?php echo $threedsecure; ?>';
          window.PaysafeWooCommerceIntegrationOption.testmode = '<?php echo $testmode; ?>';
          window.PaysafeWooCommerceIntegrationOption.base64apikey = '<?php echo $base64APIKey; ?>';
          window.PaysafeWooCommerceIntegrationOption.accountid = '<?php echo $acc_number; ?>';
      </script>
		<?php
	}

	public function add_paysafe_checkout_button( $button ) {
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( empty( $available_gateways ) ) {
			return $button;
		}

		$selected = false;
		$hidden   = '';

		foreach ( $available_gateways as $gateway ) {
			if ( $gateway instanceof Gateway && $gateway->chosen === true ) {
				$selected = true;
			}
		}

		if ( $selected ) {
			$button .= '<script>document.getElementById("place_order").style.display = "none";</script>';
		} else {
			$hidden = 'style="display: none"';
		}

		$order_button_text = __( 'Place order' );
		$button            .= '<button ' . $hidden . ' type="button" class="button alt" name="woocommerce_paysafe_checkout_place_order" id="paysafe_place_order" value="checkout_with_paysafe_checkout">' . esc_html( $order_button_text ) . '</button>';

		return $button;
	}

	public function enqueue_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_script( 'paysafe3ds2sdk', 'https://hosted.paysafe.com/threedsecure/js/latest/paysafe.threedsecure.min.js', [ 'jquery' ], false );
		}
	}

	public function load_hooks() {
		add_action( 'wp_ajax_nopriv_paysafe_threedsecure_challenge', [$this, 'paysafe_threedsecure_challenge'] );
		add_action( 'wp_ajax_paysafe_threedsecure_challenge', [$this, 'paysafe_threedsecure_challenge'] );
		add_action( 'wp_ajax_nopriv_paysafe_threedsecure_authentication', [$this, 'paysafe_threedsecure_authentication'] );
		add_action( 'wp_ajax_paysafe_threedsecure_authentication', [$this, 'paysafe_threedsecure_authentication'] );
	}

	public function paysafe_threedsecure_challenge() {
		if (isset($_POST)) {


		  wp_send_json($_POST);
    }
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function paysafe_threedsecure_authentication() {
		if (isset($_POST)) {
		  require_once MER_PAYSAFE_DIR . '/helpers/class-simple-client.php';
		$request = $_POST;
		$woocommerce = WC();
    $cart = $woocommerce->cart;
		$wc_order = new \WC_Order();
		$wc_order->set_status('pending');
		$wc_order->set_cart_hash($cart->get_cart_hash());
		$wc_order->save();
		$wc_order_number = $wc_order->get_order_number();
		$woocommerce->session->set('order_awaiting_payment', $wc_order_number);
		$customer = $cart->get_customer();
		$options  = [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($this->getSingleUseToken()),
			],
			'json'    => [
				'amount'                 => $woocommerce->cart->get_total(false) * 100,
				'currency'               => get_woocommerce_currency(),
				'merchantRefNum'         => get_woocommerce_currency() . '-' . $wc_order_number . '-' . time(),
				'merchantUrl'            => $this->helper->getBaseUrl(),
				'card'                   => [
					"cardExpiry" => [
						"month" => $request['card']['cardExpiry']['month'],
						"year"  => $request['card']['cardExpiry']['year']
					],
					"cardNum"    => $request['card']['cardNum'],
					"holderName" => wc_clean($_POST['billing_first_name']) . ' ' . wc_clean($_POST['billing_last_name']),
				],
				'deviceFingerprintingId' => $request['deviceFingerprintingId'],
				'deviceChannel'          => 'BROWSER',
				'messageCategory'        => 'PAYMENT',
				'authenticationPurpose'  => 'PAYMENT_TRANSACTION',
			],
		];

		if ($this->helper->isTestMode()) {
			$url = 'https://api.test.paysafe.com/threedsecure/v2/accounts/' . $this->helper->getAccountNumber() . '/authentications';
		} else {
			$url = 'https://api.paysafe.com/threedsecure/v2/accounts/' . $this->helper->getAccountNumber() . '/authentications';
		}

		$client = new Paysafe_Simple_Http_Client();
		$response = $client->request('POST', $url, $options);

		$object      = json_decode($response, true);

		$this->logger->debug('Got 3ds authentication response', $object);

		if (isset($object["threeDResult"]) &&
		    $object["status"] === 'COMPLETED' &&
		    version_compare($object['threeDSecureVersion'], '2.0') >= 0
		) {
			$options         = [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($this->helper->getAPIUsername() . ':' . $this->helper->getAPIPassword()),
				],
				'json'    => []
			];
			$options['json'] = [
				'merchantRefNum' => get_woocommerce_currency() . '-' . $wc_order_number . time(),
				"amount"         => $cart->get_total(false) * 100,
				"settleWithAuth" => true,
				"billingDetails" => [
					"zip" =>  $customer->get_billing_postcode()
				],
				'card'           => [
					"cardExpiry" => [
						"month" => $request['card']['cardExpiry']['month'],
						"year"  => $request['card']['cardExpiry']['year']
					],
					"cardNum"    => $request['card']['cardNum'],
				],
				"authentication" => [
					"eci"                 => $object["eci"],
					"threeDResult"        => "Y",
					"threeDSecureVersion" => "2.1.0",
				],
			];



			if (isset($object['cavv'])) {
				$options['json']['authentication']['cavv'] = $object['cavv'];
			}

			$options['json']['authentication']['id'] = $object['id'];

			$jsonResult           = new \stdClass();
			$jsonResult->status   = 'threed2completed';
			$jsonResult->dataLoad = $options['json']['authentication'];

		}

		if (
			$object["status"] === 'COMPLETED' &&
			version_compare($object['threeDSecureVersion'], '2.0') < 0
		) {
			$jsonResult           = new \stdClass();
			$jsonResult->status   = 'threed2completed';
			$jsonResult->dataLoad = [
				'id' => $object['id']
			];
		}

		if (
			$object["status"] === 'PENDING' &&
			version_compare($object['threeDSecureVersion'], '2.0') < 0 &&
			$object["threeDEnrollment"] == 'Y'
		) {

			$jsonResult = new \stdClass();
			$jsonResult->status   = 'threed2pending';
			$jsonResult->three_d_auth = $object;
		}

		if (isset($object["threeDResult"]) &&
		    $object["status"] === 'PENDING' &&
		    $object["threeDResult"] === 'C' &&
		    version_compare($object['threeDSecureVersion'], '2.0') >= 0) {
			$jsonResult = new \stdClass();
			$jsonResult->status   = 'threed2pending';
			$jsonResult->three_d_auth = $object;
		}

		  wp_send_json($jsonResult);
    }
	}
}