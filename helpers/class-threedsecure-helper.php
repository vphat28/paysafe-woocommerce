<?php

class Paysafe_Threedsecure_Helper {
	protected $options;

	public function __construct() {
		$this->options = get_option( 'woocommerce_mer_paysafe_settings' );
	}

	public function getAPIUsername() {
		if (isset($this->options['api_login']) &&
		    !empty($this->options['api_login'])
		) {
			return $this->options['api_login'];
		}

		return null;
	}
	public function getAPIPassword() {
		if (isset($this->options['trans_key']) &&
		    !empty($this->options['trans_key'])
		) {
			return $this->options['trans_key'];
		}

		return null;
	}
	public function isTestMode() {
		if (isset($this->options['environment']) &&
		    !empty($this->options['environment'])
		) {
			return $this->options['environment'] == 'yes' ? true : false;
		}

		return true;
	}
	public function getBaseUrl() {
		return home_url();
	}
	public function getAccountNumber() {
		return isset ($this->options['acc_number']) ? $this->options['acc_number'] : null;
	}
	public function getSingleUseToken() {
		$option = $this->options;
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
	public function getThreeDResult($authID) {
		if ($this->isTestMode()) {
			$url = 'https://api.test.paysafe.com/threedsecure/v2/accounts/' . $this->getAccountNumber() . '/authentications';
		} else {
			$url = 'https://api.paysafe.com/threedsecure/v2/accounts/' . $this->getAccountNumber() . '/authentications';
		}

		$header = 'Authorization: Basic ' . base64_encode($this->getSingleUseToken());

		$authResponse = $this->simpleGet($url . '/' . $authID, $header);

		return $authResponse;
	}

	public function simpleGet($url, $auth) {
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'GET',
			CURLOPT_HTTPHEADER     => array(
				$auth,
				'Content-Type: application/json'
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		return json_decode($response, true);
	}
}