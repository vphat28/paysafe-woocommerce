<?php

class Paysafe_Simple_Http_Client {
	public function request($method, $url, $options) {
		if ($method === 'POST') {
			return $this->simplePost($url, $options['headers']['Authorization'], $options['json']);
		}
	}

	public function simplePost($url, $header, $json) {
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => is_array($json) ? json_encode($json) : $json,
			CURLOPT_HTTPHEADER     => array(
				'Authorization: ' . $header,
				'Content-Type: application/json',
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		return $response;
	}
}