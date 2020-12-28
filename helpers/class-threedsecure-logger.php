<?php

class Paysafe_Threedsecure_Logger {
	public function debug( $message, $context ) {
		file_put_contents( WP_CONTENT_DIR . '/uploads/paysafe.log', $message . print_r( $context, 1 ), FILE_APPEND );
	}
}