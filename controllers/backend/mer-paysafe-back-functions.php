<?php
    class Mer_Paysafe_Back_Functions {
    	/**
         * Construct Function of Mer_Paysafe_Back_Functions
         * Initialize the hooks associated with WooCommerce.
         * @return      void
        */
    	public function __construct() {
    		add_filter( 'woocommerce_payment_gateways', array($this,'mer_paysafe_add_gateway' ));
    		add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'mer_checkout_field_display_admin_order_meta'), 10, 1 );
    	}
    	/**
         * Initialize The Payment Gateway
         * Initialize the hooks associated with WooCommerce.
         * @return      array
        */
    	public static function mer_paysafe_add_gateway( $methods ) {

    		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                return;
            }

         if ( class_exists( 'WC_Subscriptions_Order' ) ) {
        
            include_once( 'class-paysafe_subscriptions_gateway.php' );

                $methods[] = 'Paysafe_Subscriptions_Gateway';
            } else {
                $methods[] = 'Paysafe_Gateway_Init';
              }

    		return $methods;
    	}
    	/**
         * Show the Transacion ID details on Order Details meta box.
         * @return      void
        */
    	public static function mer_checkout_field_display_admin_order_meta($order) { 
    		$mer_paysafe_tran_id = get_post_meta( get_the_ID(), '_paysafe_transaction_id', true ); 

    		if ( ! empty( $mer_paysafe_tran_id ) )  { 
    			echo '<p><strong>'. __("Paysafe Transaction key", "mer-paysafepayments-aim").':</strong> <br/>' . get_post_meta( get_the_ID(), '_paysafe_transaction_id', true ) . '</p>'; 
                echo '<p><strong>'. __("Paysafe Status", "mer-paysafepayments-aim").':</strong> <br/>' . get_post_meta( get_the_ID(), '_paysafe_status', true ) . '</p>'; 
    		} 
    	}

    }
    new Mer_Paysafe_Back_Functions;
