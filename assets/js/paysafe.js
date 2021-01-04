function cctoken(){}
cctoken.prototype.credit = function(){
	jQuery("#mer_paysafe-token-form").slideUp();
	jQuery("#wc-mer_paysafe-cc-form").slideDown();	
}
cctoken.prototype.token = function(){
	jQuery("#mer_paysafe-token-form").slideDown();
	jQuery("#wc-mer_paysafe-cc-form").slideUp();
	jQuery("input:radio[name=mer_paysafe-token-number]:first").attr('checked', true);
}
var newCredit = new cctoken();
function paysafetoken() {
	newCredit.token();
}
function paysafecc() {
	newCredit.credit();	
}

jQuery(function($) {
    var fullScreenLoader = {
        stopLoader: function () {
            $.unblockUI();
        },
        startLoader: function () {
            $.blockUI({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
    };
	var PaysafeThreedSecure = {
        $checkout_form: $("form.checkout"),
		init: function () {
        },
        creditCardNumber: function () {
            return $('input[name=mer_paysafe-card-number]').val().replace(/ /g, '');
        },
        creditCardExpMonth: function () {
            var expiry = $('input[name=mer_paysafe-card-expiry]').val();
            
            return expiry.substr(0, 2);
        },
        creditCardExpYear: function () {
            var expiry = $('input[name=mer_paysafe-card-expiry]').val();

            return expiry.substr(-4);
        },
        get_test_mode: function () {
            if (window.PaysafeWooCommerceIntegrationOption.testmode == 'yes') {
                return true;
            }

            return false;
        },
        placeOrder: function () {
            if (this.threed_id !== null) {
                $('#paysafe_threed_auth_id').val(this.threed_id);
            }

            $('#place_order').click();
        },

        doChallenge: function (id) {
            var self = this;
            var url = PaysafeWooCommerceIntegrationOption.challenge_endpoint;
            var request = $.ajax({
                url: url,
                method: "POST",
                dataType: "json",
                data: {
                    "id": id,
                }
            })
                .done(function (data) {
                    if (data.status === 'threed2completed') {
                        fullScreenLoader.stopLoader();
                        self.eci = data.dataLoad.eci;
                        self.cavv = data.dataLoad.cavv;
                        self.threed_id = data.dataLoad.id;
                        return self.placeOrder();
                    } else {
                        alert('Error in 3DS version 2');
                        fullScreenLoader.stopLoader();
                    }
                });
        },

        device_finger_printing: function () {
            fullScreenLoader.startLoader();
            var paysafe3ds = window.paysafe;
            var self = this;
            if (this.get_test_mode()) {
                var environment = 'TEST';
            } else {
                var environment = 'LIVE';
            }
            paysafe3ds.threedsecure.start(window.PaysafeWooCommerceIntegrationOption.base64apikey, {
                environment: environment,
                accountId: window.PaysafeWooCommerceIntegrationOption.accountid,
                card: {
                    cardBin: self.creditCardNumber().substring(0, 8)
                }
            }, function (deviceFingerprintingId, error) {
                if (typeof deviceFingerprintingId === 'undefined') {
                    alert(error.detailedMessage);
                    fullScreenLoader.stopLoader();
                    return;
                }
                var url = PaysafeWooCommerceIntegrationOption.auth_endpoint;
                var request = $.ajax({
                        url: url,
                        method: "POST",
                        dataType: "json",
                        beforeSend: function (xhr) {
                            /* Authorization header */
                            xhr.setRequestHeader("Authorization", "Basic " + PaysafeWooCommerceIntegrationOption.base64apikey);
                        },
                        data: {
                            "deviceFingerprintingId": deviceFingerprintingId,
                            "billing_first_name": $('#billing_first_name').val(),
                            "billing_last_name": $('#billing_last_name').val(),
                            "card": {
                                "cardExpiry": {
                                    "month": self.creditCardExpMonth(),
                                    "year": self.creditCardExpYear()
                                },
                                "cardNum": self.creditCardNumber()
                            },
                        }
                    })
                        .done(function (data) {
                            console.log(data);
                            fullScreenLoader.stopLoader();
                            if (data.status === 'threed2completed') {
                                self.cavv = data.dataLoad.cavv;
                                self.threed_id = data.dataLoad.id;
                                self.eci = data.dataLoad.eci;

                                return self.placeOrder();
                            } else if (data.status === 'threed2pending') {
                                paysafe3ds.threedsecure.challenge(PaysafeWooCommerceIntegrationOption.base64apikey, {
                                    environment: environment,
                                    sdkChallengePayload: data.three_d_auth.sdkChallengePayload
                                }, function (id, error) {
                                    if (id) {
                                        self.doChallenge(id);
                                    }
                                });
                            } else {
                                alert('Error in 3DS version 2');
                                fullScreenLoader.stopLoader();
                            }
                        })
                        .error(function (data, textStatus, errorThrown) {
                            self.submit_error('<div class="woocommerce-error">' + data.responseJSON.message + '</div>');
                            fullScreenLoader.stopLoader();
                        })
                ;
            });
        },

        submit_error: function( error_message ) {
            var wc_checkout_form = this.$checkout_form;
            $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
            wc_checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
            wc_checkout_form.removeClass( 'processing' ).unblock();
            wc_checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
            this.scroll_to_notices();
            $( document.body ).trigger( 'checkout_error' , [ error_message ] );
        },

        scroll_to_notices: function() {
            var scrollElement           = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

            if ( ! scrollElement.length ) {
                scrollElement = $( '.form.checkout' );
            }
            $.scroll_to_notices( scrollElement );
        },
        showPaysafeButton: function () {
            $('#place_order').hide();
            $('#paysafe_place_order').show();
        },

        hidePaysafeButton: function () {
            $('#paysafe_place_order').hide();
            $('#place_order').show();
        },
        addEventListeners: function () {
            var self = this;

            $('.checkout.woocommerce-checkout').on('checkout_place_order', function () {
                if (jQuery('[name=payment_method]:checked').val() == 'mer_paysafe') {
                    if (self.threed_id == null || PaysafeWooCommerceIntegrationOption.threedsecurepaysafe == 'no') {
                        $('#paysafe_place_order').click();
                        return false;
                    }
                }

                return true;
            });

            $(document).on('payment_method_selected', function () {
                var selected_payment = jQuery('.woocommerce-checkout input[name="payment_method"]:checked').attr("id");

                if (selected_payment == 'payment_method_paysafe_checkout_woocommerce') {
                    self.showPaysafeButton();
                } else {
                    self.hidePaysafeButton();
                }
            });

            $(document).on('click', '#paysafe_place_order', function () {
                var checkout_form = jQuery("form.checkout");
                $('#customer_details .form-row').removeClass('woocommerce-invalid');
                $('.validate-required select, .validate-required input').trigger('validate');
                checkout_form.trigger('validate');

                if (!$('#ship-to-different-address-checkbox').prop('checked')) {
                    $('#customer_details .woocommerce-shipping-fields .form-row').removeClass('woocommerce-invalid');
                }

                if (checkout_form.find('.woocommerce-billing-fields .form-row.woocommerce-invalid, woocommerce-shipping-fields .form-row.woocommerce-invalid').length > 0) {
                    return;
                }
                fullScreenLoader.startLoader();

                if (PaysafeWooCommerceIntegrationOption.threedsecurepaysafe == 'yes') {
                    self.device_finger_printing();
                }
            });
        }
    }

    PaysafeThreedSecure.init();
    PaysafeThreedSecure.addEventListeners();
});