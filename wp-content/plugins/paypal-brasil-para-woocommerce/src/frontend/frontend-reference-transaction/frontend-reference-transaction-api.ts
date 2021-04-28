import {PaypalPayments} from "../frontend-shared";

declare const paypal_brasil_settings: any;
declare const paypal_brasil_reference_transaction_settings: any;

export const paymentReferenceTransaction = {

    create: () => {
        return new Promise((resolve, reject) => {
            PaypalPayments.makeRequest('billing-agreement-token', {
                nonce: paypal_brasil_settings.nonce,
                user_id: paypal_brasil_settings.current_user_id,
            }).done(function (response) {
                resolve(response.data.token_id);
            }).fail(function (jqXHR) {
                reject(jqXHR.responseJSON);
            });
        });
    },

    approve: (data) => {
        // Forte update checkout to create billing agreement.
        if (paypal_brasil_settings.is_order_pay_page) {
            // Block with loading.
            jQuery('form#order_review').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
            // Make request to save billing agreement.
            PaypalPayments.makeRequest('save-billing-agreement', {
                billing_agreement_token: data.billingToken,
            }).always(function () {
                // Reload page.
                document.location.reload();
            });
        } else {
            // Fill the input data with the billing agreement token.
            jQuery('[name=paypal_brasil_billing_agreement_token]').val(data.billingToken);
            // Update the checkout.
            PaypalPayments.triggerUpdateCheckout();
        }
    },

    error: (response) => {
        // Update the checkout to render button again.
        PaypalPayments.triggerUpdateCheckout();
        // Only do that if there's a JSON response.
        if (response) {
            // Add the notices.
            PaypalPayments.setNotices(response.data.error_notice);
            // Scroll screen to top.
            PaypalPayments.scrollTop();
        }
    },

    cancel: () => {
        // Update the checkout to render button again.
        PaypalPayments.triggerUpdateCheckout();
        // Add notices.
        PaypalPayments.setNotices(paypal_brasil_reference_transaction_settings.cancel_message);
        // Scroll screen to top.
        PaypalPayments.scrollTop();
    }

};