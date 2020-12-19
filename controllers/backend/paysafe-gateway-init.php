<?php

/**
 * Paysafe Gateway
 *
 * Provides a Paysafe Payment Gateway.
 *
 * @class       mer_paysafe_Init
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce/Classes/Payment
 * @author      Paysafe
 */
class Paysafe_Gateway_Init extends WC_Payment_Gateway {
    protected $order = null;
    protected $form_data = null;
    protected $transaction_id = null;
    protected $transaction_error_message = null;

    public function __construct() {
        global $mer_paysafe;

        $this->id                 = "mer_paysafe";
        $this->method_title       = __("Paysafe  Payment Gateway", 'mer-paysafepayments-aim');
        $this->method_description = __("Paysafe(Credit Card & Tokenisation) setting fields", 'mer-paysafepayments-aim');
        $this->title              = __("Paysafe", 'mer-paysafepayments-aim');
        $this->icon               = null;
        $this->has_fields         = true;

        /* For Default */
        //$this->supports = array( 'default_credit_card_form','tokenization' );

        /* For Subscription Only */
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'default_credit_card_form',
            'save_cards',
            'refunds',
        );

        // Init settings
        $this->init_form_fields();

        // Use settings
        $this->init_settings();

        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        //add_action( 'admin_notices', array( $this,'do_ssl_check' ) );
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }

    /**
     * @param int $order_id
     * @param null $amount
     * @param string $reason
     *
     * @return bool
     * @throws \Paysafe\PaysafeException
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        include_once(dirname(__FILE__) . '/includes/card-token-payment.php');
        $paysafeApiKeyId             = $this->api_login;
        $paysafeApiKeySecret         = $this->trans_key;
        $paysafeAccountNumber        = $this->acc_number;
        $currencyBaseUnitsMultiplier = $this->currency_base_units_multiplier;
        $environment                 = ($this->environment == "yes") ? \Paysafe\Environment::TEST : \Paysafe\Environment::LIVE;
        $amount                      = $amount * $currencyBaseUnitsMultiplier;
        $Paysafe_api_object          = new \Paysafe\PaysafeApiClient($paysafeApiKeyId, $paysafeApiKeySecret, $environment, $paysafeAccountNumber);
        $settlementID                = get_post_meta($order_id, '_paysafe_transaction_id', true);

        try {
            $response = $Paysafe_api_object->cardPaymentService()->refund(new \Paysafe\CardPayments\Refund(array(
                'merchantRefNum' => "refund_" . $order_id . '_' . time(),
                'settlementID'   => $settlementID,
                'amount'         => $amount,
            )));
        } catch (\Exception $e) {
            return false;
        }

        $refund_message = sprintf(__('Refunded for Order %1$s - Refund ID: %2$s - Reason: %3$s'), $amount, $response->id, $reason);

        $order->add_order_note($refund_message);

        return true;
    }

    /**
     * Get a field name supports
     *
     * @access      public
     *
     * @param       string $name
     *
     * @return      string
     */
    public function field_name($name) {
        return $this->supports('tokenization') ? '' : ' name="' . esc_attr($this->id . '-' . $name) . '" ';
    }

    /**
     * Output payment fields, optional additional fields and woocommerce cc form
     *
     * @access      public
     * @return      void
     */
    public function payment_fields() {
        if ($this->supports('default_credit_card_form') && is_checkout()) {
            $this->form(); // Create Credit Card form
        }
        if ($this->supports('save_cards') && is_checkout() && is_user_logged_in()) {
            $this->form2();  // Create Tokenization form
        }

    }

    /**
     * Form Credit Card
     *
     * @access     public
     * @return      void
     */
    public function form() {
        wp_enqueue_script('wc-credit-card-form');

        $fields = array();

        $cvc_field = '<p class="form-row form-row-last">
				<label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Card code', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-cvc" name="mer_paysafe-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
			</p>';


        $default_fields = array(
            'card-number-field' => '<p class="form-row form-row-wide">
					<label for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Card number', 'woocommerce') . ' <span class="required">*</span></label>
					<input id="' . esc_attr($this->id) . '-card-number" name="mer_paysafe-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
				</p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
					<label for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Expiry (MM/YYYY)', 'woocommerce') . ' <span class="required">*</span></label>
					<input id="' . esc_attr($this->id) . '-card-expiry" name="mer_paysafe-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YYYY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
				</p>',
        );

        if ( ! $this->supports('credit_card_form_cvc_on_saved_method')) {
            $default_fields['card-cvc-field'] = $cvc_field;
        }

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
        ?>
      <input id="payment_method_cc" class="input-radio" name="mer_paysafe_payment_method"
             value="mer_paysafe_credit_card" data-order_button_text="" type="radio" checked="checked"
             onclick="paysafecc()">
      <label for="payment_method_cc" onclick="paysafecc()"><?php echo __('Credit Card'); ?></label>
        <?php
        $customer_orders = get_posts(array(
            'numberposts' => - 1,
            'meta_key'    => '_customer_user',
            'meta_value'  => get_current_user_id(),
            'post_type'   => wc_get_order_types(),
            'post_status' => array_keys(wc_get_order_statuses()),
        ));
        $i               = 0;
        foreach ($customer_orders as $oid) {
            if (metadata_exists('post', $oid->ID, '_paysafe_token_card_info')) {
                $listcard[$i] = get_post_meta($oid->ID, '_paysafe_token_card_info');
            }
            $i ++;
        }

        if (is_user_logged_in() && ! empty($listcard) && $this->saved_cards == "yes") { ?>
          <input id="payment_method_token" class="input-radio" name="mer_paysafe_payment_method"
                 value="mer_paysafe_token" data-order_button_text="" type="radio" onclick="paysafetoken()">
          <label for="payment_method_token" onclick="paysafetoken()"><?php echo __('Saved Card'); ?></label>
        <?php } ?>
      <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
          <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
          <?php
          foreach ($fields as $field) {
              echo $field;
          }
          ?>
          <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
        <div class="clear"></div>
      </fieldset>
        <?php

        if ($this->supports('credit_card_form_cvc_on_saved_method')) {
            echo '<fieldset>' . $cvc_field . '</fieldset>';
        }

        ?>
        <input type="hidden" name="paysafe_threed_auth_id" id="paysafe_threed_auth_id"/>
      <?php
    }

    /**
     * Form Token
     *
     * @access    public
     * @return    void
     */
    public function form2() {
        ?>
    <fieldset id="<?php echo esc_attr($this->id); ?>-token-form" class='wc-token-form wc-payment-form'>
      <table class="shop_table">
        <thead>
        <?php
        $customer_orders = get_posts(array(
            'numberposts' => - 1,
            'meta_key'    => '_customer_user',
            'meta_value'  => get_current_user_id(),
            'post_type'   => wc_get_order_types(),
            'post_status' => array_keys(wc_get_order_statuses()),
        ));
        $i               = 0;
        foreach ($customer_orders as $oid) {
            if (metadata_exists('post', $oid->ID, '_paysafe_token_card_info')) {
                $listcard[$i] = get_post_meta($oid->ID, '_paysafe_token_card_info');
            }
            $i ++;
        }

        if ( ! empty($listcard)) {

            ?>
          <tr>
            <th><?php _e('Select', 'mer-paysafepayments-aim'); ?></th>
            <th><?php _e('Cards', 'mer-paysafepayments-aim'); ?></th>
            <th><?php _e('Date', 'mer-paysafepayments-aim'); ?></th>
            <th></th>
          </tr>
            <?php
            $c = 0;
            foreach ($listcard as $cards) {

                ?>
              <tr>
                <td>

                  <input class="save_card_number" type="radio"
                         name="<?php echo esc_attr($this->id) . "-token-number"; ?>"
                         value='<?php echo esc_html($cards[0]['paysafe_tokenno']); ?>'></td>
                <td><?php echo esc_html($cards[0]['paysafe_cardnum']); ?></td>
                <td><?php echo esc_html($cards[0]['paysafe_date_of_card_used']); ?></td>
              </tr>
                <?php
                $c ++;
            }
        } ?> </thead>
        <tbody></tbody>
      </table>
        <?php do_action('woocommerce_echeck_form_end', $this->id); ?>
      <div class="clear"></div>
      </fieldset><?php
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access      public
     * @return      void
     */
    public function init_form_fields() {
        $prefix = 'sample_';

        $this->form_fields = array(
            'enabled'     => array(
                'title'   => __('Enable / Disable', 'mer-paysafepayments-aim'),
                'label'   => __('Enable this payment gateway', 'mer-paysafepayments-aim'),
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            'title'       => array(
                'title'    => __('Title', 'mer-paysafepayments-aim'),
                'type'     => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'mer-paysafepayments-aim'),
                'default'  => __('Paysafe', 'mer-paysafepayments-aim'),
            ),
            'description' => array(
                'title'    => __('Description', 'mer-paysafepayments-aim'),
                'type'     => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'mer-paysafepayments-aim'),
                'default'  => __('Pay securely using your credit card.', 'mer-paysafepayments-aim'),
                'css'      => 'max-width:350px;'
            ),
            'api_login'   => array(
                'title'    => __('Paysafe API Login ID', 'mer-paysafepayments-aim'),
                'type'     => 'text',
                'desc_tip' => __('This is the API Login provided by Paysafe User id when you signed up for an account.', 'mer-paysafepayments-aim'),
            ),
            'trans_key'   => array(
                'title'    => __(' Paysafe Transaction Key', 'mer-paysafepayments-aim'),
                'type'     => 'password',
                'desc_tip' => __('This is the Transaction Key provided by Paysafe transaction key when you signed up for an account.', 'mer-paysafepayments-aim'),
            ),
            'single_token_username'   => array(
                'title'    => __('Single Use Token Username', 'mer-paysafepayments-aim'),
                'type'     => 'password',
                'desc_tip' => __('This is the public key to use in 3DS secure mode', 'mer-paysafepayments-aim'),
            ),
            'single_token_password'   => array(
                'title'    => __('Single Use Token Password', 'mer-paysafepayments-aim'),
                'type'     => 'password',
                'desc_tip' => __('This is the public key to use in 3DS secure mode', 'mer-paysafepayments-aim'),
            ),


            'acc_number' => array(
                'title'    => __(' Paysafe Account Number', 'mer-paysafepayments-aim'),
                'type'     => 'text',
                'desc_tip' => __('This is the Account Number provided by Paysafe paysafe when you signed up for an account.', 'mer-paysafepayments-aim'),
            ),


            'currency_code' => array(
                'title'    => __(' Currency code', 'mer-paysafepayments-aim'),
                'type'     => 'text',
                'desc_tip' => __('The Currency Code should match the currency of your Paysafe account.', 'mer-paysafepayments-aim'),
            ),

            'currency_base_units_multiplier' => array(
                'title'    => __(' Currency Base Units Multipler', 'mer-paysafepayments-aim'),
                'type'     => 'text',
                'desc_tip' => __('Transactions are actually measured in fractions of the currency specified in the $currencyCode; for example, USD transactions are measured in cents. This multiplier is how many of these smaller units make up one of the specified currency. For example, with the $currencyCode USD the value is 100 but for Japanese YEN the multiplier would be 1 as there is no smaller unit.', 'mer-paysafepayments-aim'),
            ),

            'auth_capture_settlement' => array(
                'title'       => __('Authorization Settlement Yes/No', 'mer-paysafepayments-aim'),
                'label'       => __('Enable Authorization Settlement', 'mer-paysafepayments-aim'),
                'type'        => 'checkbox',
                'description' => __('Checked = Yes
                    This indicates that the transaction will process an Authorization, followed by a Settlement<br>
                    UnChecked = No
                    This indicates that the transaction will only process an Authorization.'),
                'default'     => 'no',
            ),

            'saved_cards' => array(
                'type'        => 'checkbox',
                'title'       => __('Saved Cards', 'mer-paysafepayments-aim'),
                'description' => __('Allow customers to use saved cards for future purchases.', 'mer-paysafepayments-aim'),
                'default'     => 'no',
            ),

            'card_type_field' => array(
                'type'        => 'multiselect',
                'title'       => __('Cards Type 2', 'mer-paysafepayments-aim'),
                'options'     => array(
                    "vi"  => "Visa Card",
                    "mc"  => "MasterCard",
                    "ae"  => "American Express",
                    "di"  => "Discover",
                    "jcb" => "JCB",
                    "dn"  => "Dinner Card"
                ),
                'title'       => __('Cards Type', 'mer-paysafepayments-aim'),
                'description' => __('Press Ctrl and select for multiple Card type', 'mer-paysafepayments-aim'),
            ),

            'environment' => array(
                'title'       => __('Paysafe Test Mode', 'mer-paysafepayments-aim'),
                'label'       => __('Enable Test Mode', 'mer-paysafepayments-aim'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'mer-paysafepayments-aim'),
                'default'     => 'no',
            ),

            'threedsecure' => array(
                'title'       => __('3DS version 2', 'mer-paysafepayments-aim'),
                'label'       => __('Enable 3DS version 2', 'mer-paysafepayments-aim'),
                'type'        => 'checkbox',
                'default'     => 'no',
            ),
        );


    }

    /**
     * Process the payment and return the result
     *
     * @access      public
     *
     * @param       int $order_id
     *
     * @return      array/boolean
     */
    public function process_payment($order_id) {
        if ($this->send_to_paysafe_gateway($order_id)) {
            $this->order_complete();
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            $result = array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($this->order)
            );

            return $result;
        } else {
            $this->payment_failed();
            // Add a generic error message if we don't currently have any others
            wc_add_notice(__('Transaction Error: Could not complete your payment: Please check the Payment Details and try again', 'mer-paysafepayments-aim'), 'error');

            return false;
        }
    }

    /**
     * Send form data to paysafe
     * Handles the ApI, Credit Card Payments, Token
     *
     * @access      protected
     *
     * @param       int $order_id
     *
     * @return      bool
     */
    protected function send_to_paysafe_gateway($order_id) {
        // Get the order based on order_id
        $this->order = new WC_Order($order_id);
        // Include the paysafe payment files.
        include_once(dirname(__FILE__) . '/includes/card-token-payment.php');
        $order                  = wc_get_order($order_id);
        $totalAmount            = (float) str_replace(array(' ', ','), '', $order->total);
        $billing_first_name     = str_replace(array(' ', '-'), '', $_POST['billing_first_name']);
        $billing_last_name      = str_replace(array(' ', '-'), '', $_POST['billing_last_name']);
        $billing_phone          = str_replace(array(' ', '-'), '', $_POST['billing_phone']);
        $billing_email          = str_replace(array(' ', '-'), '', $_POST['billing_email']);
        $billing_country        = str_replace(array(' ', '-'), '', $_POST['billing_country']);
        $billing_city           = str_replace(array(' ', '-'), '', $_POST['billing_city']);
        $billing_postcode       = str_replace(array(' ', '-'), '', $_POST['billing_postcode']);
        $billing_address_1      = $_POST['billing_address_1'];
        $cardNumber             = str_replace(array(' ', '-'), '', $_POST['mer_paysafe-card-number']);
        $cardNumberExpiry       = str_replace(array(' ', '-'), '', $_POST['mer_paysafe-card-expiry']);
        $cardCvv                = str_replace(array(' ', '-'), '', $_POST['mer_paysafe-card-cvc']);
        $paysafeMethod          = $_POST['mer_paysafe_payment_method'];
        $paysafe_request_object = new WC_Gateway_Paysafe_Request;

        $paysafeApiKeyId             = $this->api_login;
        $paysafeApiKeySecret         = $this->trans_key;
        $paysafeAccountNumber        = $this->acc_number;
        $currencyBaseUnitsMultiplier = $this->currency_base_units_multiplier;
        $authCaptureSettlement       = $this->auth_capture_settlement;
        $environment                 = ($this->environment == "yes") ? 'TEST' : 'LIVE';
        if (is_user_logged_in() || isset($_POST['createaccount'])) {
            $tokenRequest = ($this->saved_cards == "yes") ? 'token' : 'cc';
        } else {
            $tokenRequest = 'guestaccount';
        }
        if ($paysafeMethod == "mer_paysafe_credit_card") {

            $expcardexp      = explode('/', $cardNumberExpiry);
            $cardMonth       = (int) str_replace(array(' ', ','), '', $expcardexp[0]);
            $cardYear        = (int) str_replace(array(' ', ','), '', $expcardexp[1]);
            $paysafe_request = $paysafe_request_object->get_request_paysafe_url_cc($order_id, $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $cardNumber, $cardMonth, $cardYear, $cardCvv, $billing_address_1, $billing_country, $billing_city, $billing_postcode, $currencyBaseUnitsMultiplier, $tokenRequest, $billing_first_name, $billing_last_name, $billing_email, $billing_phone, $paysafeMethod, $authCaptureSettlement);

        } else {
            $tokenKeyId      = str_replace(array(' ', '-'), '', $_POST['mer_paysafe-token-number']);
            $paysafe_request = $paysafe_request_object->get_request_paysafe_url_token($paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $order_id);
        }
        //echo "Response Code: ".$paysafe_request['responsecode'];
        if ($paysafe_request['responsecode'] == '0') {
            $transactionID        = $paysafe_request['transaction_id'];
            $this->transaction_id = $transactionID;
            $status               = $paysafe_request['status'];
            update_post_meta($order->get_id(), '_paysafe_status', $status);
            update_post_meta($order->get_id(), '_paysafe_transaction_id', $transactionID);
            if ($tokenRequest === "token" && $paysafeMethod == "mer_paysafe_credit_card") {
                $tokenkeyID          = $paysafe_request['tokenkey'];
                $storeCc             = substr($cardNumber, 0, 4) . str_repeat("*", strlen($cardNumber) - 8) . substr($cardNumber, - 4);
                $paysafe_trasc_store = array(
                    "paysafe_cust_id"           => get_current_user_id(),
                    "paysafe_cardnum"           => $storeCc,
                    "paysafe_tokenno"           => $tokenkeyID,
                    "paysafe_token_request"     => $tokenRequest,
                    "paysafe_date_of_card_used" => date("jS F Y")
                );

                update_post_meta($order->get_id(), '_paysafe_token_card_info', $paysafe_trasc_store);

            }

            return true;
        } else {
            if ($environment != 'LIVE') {
                $erro_info = $paysafe_request['errormessage'];
                wc_add_notice(__("$erro_info"), 'error');
            }

            return false;
        }
    }

    /**
     * Mark the payment as failed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function payment_failed() {
        $this->order->add_order_note(
            sprintf(
                __('Payment failed', 'mer-paysafepayments-aim'),
                get_class($this),
                $this->transaction_error_message
            )
        );
    }

    /**
     * Mark the payment as completed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function order_complete() {

        if ($this->order->status == 'completed') {
            return;
        }
        $update_order_req = array(
            'ID'          => $this->order->get_id(),
            'post_status' => 'wc-processing',
        );

        wp_update_post($update_order_req);

        $this->order->add_order_note(
            sprintf(
                __('%s payment completed with Transaction Id of "%s"', 'stripe-for-woocommerce'),
                get_class($this),
                $this->transaction_id
            )
        );
    }


    /**
     * Validate credit card and Token form fields
     *
     * @access      public
     * @return      bool
     */
    public function validate_fields() {
        $paysafeMethod = $_POST['mer_paysafe_payment_method'];
        if ($paysafeMethod == "mer_paysafe_credit_card") {
            if ($_POST['mer_paysafe-card-number'] != "" && $_POST['mer_paysafe-card-cvc'] != "" && $_POST['mer_paysafe-card-expiry'] != "") {

                $accountNumber = str_replace(array(' ', '-'), '', $_POST['mer_paysafe-card-number']);

                $cardCode = array(
                    "vi"  => "Visa Card",
                    "mc"  => "MasterCard",
                    "ae"  => "American Express",
                    "di"  => "Discover",
                    "jcb" => "JCB",
                    "dn"  => "Dinner Card"
                );

                $cardType = array(
                    "visa"       => "/^4[0-9]{12}(?:[0-9]{3})?$/",
                    "mastercard" => "/^5[1-5][0-9]{14}$/",
                    "amex"       => "/(^3[47])((\d{11}$)|(\d{13}$))/",
                    "discover"   => "/^6(?:011|5[0-9]{2})[0-9]{12}$/",
                    "jcb"        => "/(^(352)[8-9](\d{11}$|\d{12}$))|(^(35)[3-8](\d{12}$|\d{13}$))/",
                    "dn"         => "/(^(30)[0-5]\d{11}$)|(^(36)\d{12}$)|(^(38[0-8])\d{11}$)/"
                );

                if (preg_match($cardType['visa'], $accountNumber)) {
                    $result = 'vi';
                } elseif (preg_match($cardType['mastercard'], $accountNumber)) {
                    $result = 'mc';
                } elseif (preg_match($cardType['amex'], $accountNumber)) {
                    $result = 'ae';
                } elseif (preg_match($cardType['discover'], $accountNumber)) {
                    $result = 'di';
                } elseif (preg_match($cardType['jcb'], $accountNumber)) {
                    $result = 'jcb';
                } elseif (preg_match($cardType['dn'], $accountNumber)) {
                    $result = 'dn';
                } else {
                    wc_add_notice("Wrong card", 'error');

                    return false;
                }
                $cardTypes = $this->card_type_field;
                if ($cardTypes != "") {
                    $active_cards = '';
                    foreach ($cardTypes as $key) {
                        if (array_key_exists($key, $cardCode)) {
                            $active_cards .= $cardCode[$key] . ", ";
                        }
                    }

                    if ( ! in_array($result, $cardTypes)) {
                        $cards_allow = rtrim($active_cards, ', ');
                        wc_add_notice("Card type should be <b> $cards_allow </b>", 'error');

                        return false;
                    }
                }

            } else {
                wc_add_notice("Provide the Card detials", 'error');

                return false;
            }
        }

    }

}
