/*global wc_pagseguro_assinatura_params, PagSeguroDirectPayment, wc_checkout_params */
(function( $ ) {
	'use strict';

	$( function() {

		var pagseguro_assinaturas_submit = false;

		/**
		 * Set credit card brand.
		 *
		 * @param {string} brand
		 */
		function pagSeguroAssinaturasSetCreditCardBrand( brand ) {
			$( '#wc-pagseguro_assinaturas-cc-form' ).attr( 'data-credit-card-brand', brand );
		}


		/**
		 * Add error message
		 *
		 * @param {string} error
		 */
		function pagSeguroAssinaturasAddErrorMessage( error ) {
			var wrapper = $( '#wc-pagseguro_assinaturas-cc-form' );

			$( '.woocommerce-error', wrapper ).remove();
			wrapper.prepend( '<div class="woocommerce-error" style="margin-bottom: 0.5em !important;">' + error + '</div>' );
		}

		
		/**
		 * Initialize the payment form.
		 */
		function pagSeguroAssinaturasInitPaymentForm() {

			$( '#wc-pagseguro_assinaturas-cc-form' ).show();

			// CPF.
			$( '#pagseguro_assinaturas_card_holder_cpf' ).mask( '000.000.000-00' );

			// Birth Date.
			$( '#pagseguro_assinaturas_card_holder_birth_date' ).mask( '00/00/0000' );

			// Phone.
			var MaskBehavior = function( val ) {
					return val.replace( /\D/g, '' ).length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
				},
				maskOptions = {
					onKeyPress: function( val, e, field, options ) {
						field.mask( MaskBehavior.apply( {}, arguments ), options );
					}
				};

			$( '#pagseguro_assinaturas_card_holder_phone' ).mask( MaskBehavior, maskOptions );
		}

		/**
		 * Form Handler.
		 *
		 * @return {bool}
		 */
		function pagSeguroAssinaturasformHandler() {
			if ( pagseguro_assinaturas_submit ) {
				pagseguro_assinaturas_submit = false;

				return true;
			}

			if ( ! $( '#payment_method_pagseguro_assinaturas' ).is( ':checked' ) ) {
				return true;
			}

			var form = $( 'form.checkout, form#order_review, form#add_payment_method' ),
				creditCardForm  = $( '#wc-pagseguro_assinaturas-cc-form', form ),
				error           = false,
				errorHtml       = '',
				brand           = creditCardForm.attr( 'data-credit-card-brand' ),
				cardNumber      = $( '#pagseguro_assinaturas-card-number', form ).val().replace( /[^\d]/g, '' ),
				cvv             = $( '#pagseguro_assinaturas-card-cvc', form ).val(),
				expirationMonth = $( '#pagseguro_assinaturas-card-expiry', form ).val().replace( /[^\d]/g, '' ).substr( 0, 2 ),
				expirationYear  = $( '#pagseguro_assinaturas-card-expiry', form ).val().replace( /[^\d]/g, '' ).substr( 2 ),
				today           = new Date();

			// Validate the credit card data.
			errorHtml += '<ul>';

			// Validate the card brand.
			if ( typeof brand === 'undefined' || 'error' === brand ) {
				errorHtml += '<li>' + wc_pagseguro_assinatura_params.invalid_card + '</li>';
				error = true;
			}

			// Allow for year to be entered either as 2 or 4 digits
			if ( 2 === expirationYear.length ) {
				var prefix = today.getFullYear().toString().substr( 0, 2 );
				expirationYear = prefix + '' + expirationYear;
			}

			// Validate the expiry date.
			if ( 2 !== expirationMonth.length || 4 !== expirationYear.length ) {
				errorHtml += '<li>' + wc_pagseguro_assinatura_params.invalid_expiry + '</li>';
				error = true;
			}

			if ( ( 2 === expirationMonth.length && 4 === expirationYear.length ) && ( expirationMonth > 12 || expirationYear <= ( today.getFullYear() - 1 ) || expirationYear >= ( today.getFullYear() + 20 ) || ( expirationMonth < ( today.getMonth() + 2 ) && expirationYear.toString() === today.getFullYear().toString() ) ) ) {
				errorHtml += '<li>' + wc_pagseguro_assinatura_params.expired_date + '</li>';
				error = true;
			}


			errorHtml += '</ul>';

			// Create the card token.
			if ( ! error ) {

				PagSeguroDirectPayment.onSenderHashReady(function(response){
				    
				    if(response.status == 'error') {
				        pagSeguroAddErrorMessage( response.message );
				        return false;
				    }

				    PagSeguroDirectPayment.createCardToken({
						brand:           brand,
						cardNumber:      cardNumber,
						cvv:             cvv,
						expirationMonth: expirationMonth,
						expirationYear:  expirationYear,
						success: function( data ) {
							// Remove any old hash input.
							$( 'input[name=pagseguro_assinaturas_credit_card_hash]', form ).remove();

							// Add the hash input.
							form.append( $( '<input name="pagseguro_assinaturas_credit_card_hash" type="hidden" />' ).val( data.card.token ) );
							form.append( $( '<input name="pagseguro_assinaturas_sender_hash" type="hidden" />' ).val( response.senderHash  ) );
							
							var last4 = cardNumber.substr( cardNumber.length - 4 );
							form.append( $( '<input name="pagseguro_assinaturas_last4" type="hidden" />' ).val( last4  ) );
							form.append( $( '<input name="pagseguro_assinaturas_brand" type="hidden" />' ).val( brand  ) );
							form.append( $( '<input name="pagseguro_assinaturas_exp_month" type="hidden" />' ).val( expirationMonth  ) );
							form.append( $( '<input name="pagseguro_assinaturas_exp_year" type="hidden" />' ).val( expirationYear  ) );


							// Submit the form.
							pagseguro_assinaturas_submit = true;
							form.submit();
						},
						error: function() {
							pagSeguroAssinaturasAddErrorMessage( wc_pagseguro_assinatura_params.general_error );
						}
					});

				});
				

			// Display the error messages.
			} else {
				pagSeguroAssinaturasAddErrorMessage( errorHtml );
			}

			return false;
		}

		// Transparent checkout actions.
		if ( wc_pagseguro_assinatura_params.session_id ) {
			// Initialize the transparent checkout.
			PagSeguroDirectPayment.setSessionId( wc_pagseguro_assinatura_params.session_id );

			// Display the payment for and init the input masks.
			if ( "undefined" != typeof wc_checkout_params && '1' === wc_checkout_params.is_checkout ) {
				$( 'body' ).on( 'updated_checkout', function() {
					pagSeguroAssinaturasInitPaymentForm();
				});
			} else {
				pagSeguroAssinaturasInitPaymentForm();
			}

			// Get the credit card brand.
			$( 'body' ).on( 'focusout', '#pagseguro_assinaturas-card-number', function() {
				var bin = $( this ).val().replace( /[^\d]/g, '' ).substr( 0, 6 );

				if ( 6 === bin.length ) {
					// Reset the installments.

					PagSeguroDirectPayment.getBrand({
						cardBin: bin,
						success: function( data ) {
							$( 'body' ).trigger( 'pagseguro_assinaturas_credit_card_brand', data.brand.name );
							pagSeguroAssinaturasSetCreditCardBrand( data.brand.name );
						},
						error: function() {
							$( 'body' ).trigger( 'pagseguro_assinaturas_credit_card_brand', 'error' );
							pagSeguroAssinaturasSetCreditCardBrand( 'error' );
						}
					});
				}
			});
			
			$( 'body' ).on( 'updated_checkout', function() {
				var field = $( 'body #pagseguro_assinaturas-card-number' );

				if ( 0 < field.length ) {
					field.focusout();
				}
			});

			// Set the errors.
			$( 'body' ).on( 'focus', '#pagseguro_assinaturas-card-number, #pagseguro_assinaturas-card-expiry', function() {
				$( '#wc-pagseguro_assinaturas-cc-form .woocommerce-error' ).remove();
			});

			// Get the installments.
			$( 'body' ).on( 'pagseguro_assinaturas_credit_card_brand', function( event, brand ) {
				if ( 'error' == brand ) {
					pagSeguroAssinaturasAddErrorMessage( wc_pagseguro_assinatura_params.invalid_card );
				}
			});

			// Process the credit card data when submit the checkout form.
			$( 'form.checkout' ).on( 'checkout_place_order_pagseguro_assinaturas', function() {
				return pagSeguroAssinaturasformHandler();
			});

			$( 'form#order_review' ).submit( function() {
				return pagSeguroAssinaturasformHandler();
			});

			$( 'form#add_payment_method' ).submit( function() {
				return pagSeguroAssinaturasformHandler();
			});

		} else {
			$( 'body' ).on( 'updated_checkout', function() {
				$( '#wc-pagseguro_assinaturas-cc-form' ).remove();
			});

		}
	});

}( jQuery ));
