<?php 

require_once MER_PAYSAFE_DIR . '/controllers/class-paysafe-threedsecure.php';

class Paysafe_Payments_Main {
	/**
     * Construct Function of Paysafe_Payments_Main
     * Initialize the hooks, run at the time of plugin initialization.
     * @return      void
    */
	public function __construct() {
		add_action( 'init', array($this, 'mer_paysafe_woo_check'), 0 );
		add_filter( 'plugin_action_links_' . MER_PAYSAFE_BASE, array($this,'mer_paysafe_action_links') );
		add_action( 'wp_footer', array($this,'paysafe_footer_script' ));
	}
	/**
     * Add CSS and JS for Frontend.
     * @return      void
    */
	public static function paysafe_footer_script() {
		wp_enqueue_script( 'custom-paysafe-script', MER_PAYSAFE_JS . '/paysafe.js', array( 'jquery' ) );
	 	wp_enqueue_style( 'slider-paysafe', MER_PAYSAFE_CSS . '/paysafe.css',false,'1.1','all');
    }
	/**
     * Check woocommerce active or not.
     * If Not-active then auto deactive the plugins, with message.  
     * @return      void
    */
	
	public static function install() {
		if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			deactivate_plugins(__FILE__);
			$error_message = __('This plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woocommerce');
			die($error_message);
		}
	}
	/**
     * Check woocommerce active or not.
     * If Active then include the required files.  
     * @return      void
    */
	public static function mer_paysafe_woo_check() {
		if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			deactivate_plugins('woo-paysafe-payments/woo-paysafe-payments.php');
		}
		else{

			require( 'backend/mer-paysafe-back-functions.php');
			require( 'backend/paysafe-gateway-init.php' );
		}
	}
	/**
     * Set Action Links for Action Links.
     * @return      array
    */
	public static function mer_paysafe_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mer_paysafe' ) . '">' . __( 'Settings', 'mer-paysafepayments-aim' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );	
	}

}
new Paysafe_Payments_Main();
new Paysafe_Threedsecure();
