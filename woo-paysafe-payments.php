<?php
/*
Plugin Name: Wocommerce Paysafe Payments Gateway
Plugin URI: https://www.paysafe.com
Description: Payment using Paysafe (Credit Card & Tokenisation) method.
Version: 1.0
Author: Collinsharper
Author URI: #
*/
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
/** Define the Urls  **/
define( 'MER_PAYSAFE_BASE', plugin_basename( __FILE__ ) );
define( 'MER_PAYSAFE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MER_PAYSAFE_URL', plugin_dir_url( __FILE__ ) );
define( 'MER_PAYSAFE_AST', plugin_dir_url( __FILE__ ).'assets/' );
define( 'MER_PAYSAFE_IMG', plugin_dir_url( __FILE__ ).'assets/images' );
define( 'MER_PAYSAFE_CSS', plugin_dir_url( __FILE__ ).'assets/css' );
define( 'MER_PAYSAFE_JS', plugin_dir_url( __FILE__ ).'assets/js' );
/**----- End -----  **/
require 'controllers/class-paysafe-payments-main.php';
register_activation_hook( __FILE__, array('Paysafe_Payments_Main', 'install') );
