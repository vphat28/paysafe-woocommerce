<?php

    if ( ! defined( 'ABSPATH' ) ) {
    	exit;
    }
    require_once('config.php');

    use Paysafe\PaysafeApiClient;
    use Paysafe\Environment;
    use Paysafe\CardPayments\Authorization;
    use Paysafe\CustomerVault\Profile;
    use Paysafe\CustomerVault\Address;
    use Paysafe\CustomerVault\Card;
    use Paysafe\CustomerVault\Mandates;

     class WC_Gateway_Paysafe_Request {       
         /**
         * Payment via credit card request to the gateway.
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
         * @return array
         */
    	public function get_request_paysafe_url_cc( $order_id, $paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $cardNumber, $cardMonth, $cardYear, $cardCvv, $billing_address_1, $billing_country, $billing_city, $billing_postcode, $currencyBaseUnitsMultiplier, $tokenRequest, $fname, $lname, $email,$paysafeMethod, $authCaptureSettlement) {

                $environmentType = $environment=='LIVE' ? Environment::LIVE : Environment::TEST;
                $settleWithAuth = $authCaptureSettlement=='yes' ? true : false;
                $client = new PaysafeApiClient($paysafeApiKeyId, $paysafeApiKeySecret, $environmentType, $paysafeAccountNumber);
            try {
                   if($tokenRequest === "token") {

                              $profile = $client->customerVaultService()->createProfile(new Profile(array(
                                        "merchantCustomerId" => uniqid('cust-' . date('m/d/Y h:i:s a', time())),
                                        "locale" => "en_US",
                                        "firstName" => $fname,
                                        "lastName" => $lname,
                                        "email" => $email
                                    )));
                                 

                                    $address = $client->customerVaultService()->createAddress(new Address(array(
                                        "nickName" => "home",
                                        'street' => $billing_address_1,
                                        'city' => $billing_city,
                                        'country' => $billing_country,
                                        'zip' => $billing_postcode,
                                        "profileID" => $profile->id
                                    )));
                                    
                                    $card = $client->customerVaultService()->createCard(new Card(array(
                                        "nickName" => "Default Card",
                                        'cardNum' => $cardNumber,
                                        'cardExpiry' => array(
                                            'month' => $cardMonth,
                                            'year' => $cardYear
                                        ),
                                        'billingAddressId' => $address->id,
                                        "profileID" => $profile->id
                                    )));
                                     

                                    $auth = $client->cardPaymentService()->authorize(new Authorization(array(
                                        'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                                        'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                                        'settleWithAuth' => $settleWithAuth,
                                        'card' => array(
                                            'paymentToken' => $card->paymentToken
                                        )
                                    )));

                                 $responsearray=array('transaction_id'=>$auth->id,
                    		                     'status'=>$auth->status,
                    		                     'merchantrefnum'=>$auth->merchantRefNum,
                    		                     'txtime'=>$auth->txnTime,
                    		                     'tokenreq'=>$tokenRequest,
                    		                     'tokenkey'=>$profile->paymentToken,
                    		                     'responsecode'=>0
                    			                   );

                              return $responsearray;
                           
                     } else {

                                $auth = $client->cardPaymentService()->authorize(new Authorization(array(
                    				 'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                    				 'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                    				 'settleWithAuth' => $settleWithAuth,
                    				 'card' => array(
                    					  'cardNum' => $cardNumber,
                    					  'cvv' => $cardCvv,
                    					  'cardExpiry' => array(
                    							'month' => $cardMonth,
                    							'year' => $cardYear
                    					 )
                    				 ),
                    			   'billingDetails' => array(
                    	           "street" => $billing_address_1,
                    	           "city" => $billing_city,
                    	           "country" => $billing_country,
                    			   'zip' => $billing_postcode
                    			))));
                                $responsearray=array('transaction_id'=>$auth->id,
                    		                     'status'=>$auth->status,
                    		                     'merchantrefnum'=>$auth->merchantRefNum,
                    		                     'txtime'=>$auth->txnTime,
                    		                     'tokenreq'=>$tokenRequest,
                    		                     'responsecode'=>0
                    			                   );
                                return $responsearray;
                        }      
    		} catch (Paysafe\PaysafeException $e) {

                if($environment!='LIVE'){                   
                     $failedMessage='';                    
                    if ($e->error_message) {
                         $failedMessage.= $e->error_message."<br>";
                    }
                    if ($e->fieldErrors) {
                        foreach ($e->fieldErrors as $message) {
                            $failedMessage.=$message['field']."-->".$message['error']."<br>";               
                        }
                    }
                    if ($e->links) {
                          foreach ($e->links as $message) {
                                $failedMessage.="error_info link --> ".$message['href']."<br>";              
                          }
                    }  
                    $responsearray=array('status'=>"failed",'responsecode'=>1,'errormessage'=>$failedMessage);
                    } else {
                        $responsearray=array('status'=>"failed",'responsecode'=>1);
                      } 
                return $responsearray;
    	   }	   
       }
        /**
         * Payment via token request to the gateway.
         * @param  $paysafeApiKeyId
         * @param  $paysafeApiKeySecret
         * @param  $paysafeAccountNumber
         * @param  $environment
         * @param  $totalAmount
         * @param  $currencyBaseUnitsMultiplier
         * @param  $tokenKeyId
         * @return array
         */
        public function get_request_paysafe_url_token($paysafeApiKeyId, $paysafeApiKeySecret, $paysafeAccountNumber, $environment, $totalAmount, $currencyBaseUnitsMultiplier, $tokenKeyId, $authCaptureSettlement, $order_id) {
        $environmentType = $environment=='LIVE' ? Environment::LIVE : Environment::TEST;
        $settleWithAuth = $authCaptureSettlement=='yes' ? true : false;
        $client = new PaysafeApiClient($paysafeApiKeyId, $paysafeApiKeySecret, $environmentType, $paysafeAccountNumber);
        try {
             $auth = $client->cardPaymentService()->authorize(new Authorization(array(
                        'merchantRefNum' => $order_id.'_'.date('m/d/Y h:i:s a', time()),
                        'amount' => $totalAmount * $currencyBaseUnitsMultiplier,
                        'settleWithAuth' => $settleWithAuth,
                        'card' => array(
                            'paymentToken' => $tokenKeyId
                        )
                    )));
                 $responsearray=array('transaction_id'=>$auth->id,
                             'status'=>$auth->status,
                             'merchantrefnum'=>$auth->merchantRefNum,
                             'txtime'=>$auth->txnTime,
                             'responsecode'=>0
                             );
              return $responsearray;         
        } catch (Paysafe\PaysafeException $e) {
                if($environment!='LIVE'){
                     $failedMessage=''; 
                     if ($e->error_message) {
                         $failedMessage.= $e->error_message;
                         }
                     if ($e->fieldErrors) {
                        foreach ($e->fieldErrors as $message) {
                            $failedMessage.=$message['field']."-->".$message['error']."<br>";               
                        }
                     $responsearray=array('status'=>"failed",'responsecode'=>1,'errormessage'=>$failedMessage);
                    } 
                } else {
                      $responsearray=array('status'=>"failed",'responsecode'=>1);
                    } 
                               
                return $responsearray;
                
             }
        }       


    }
