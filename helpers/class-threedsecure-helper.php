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
}