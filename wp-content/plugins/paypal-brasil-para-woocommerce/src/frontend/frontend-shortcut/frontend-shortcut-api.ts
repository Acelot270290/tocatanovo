import {PaypalPayments} from "../frontend-shared";

declare const paypal_brasil_settings: any;
declare const paypal_brasil_shortcut_settings: any;

export const paymentShortcut = {

    miniCart: {

        create: () => {
            return new Promise((resolve, reject) => {
                PaypalPayments.makeRequest('shortcut', {
                    nonce: paypal_brasil_settings.nonce,
                }).done(function (response) {
                    resolve(response.data.ec);
                }).fail(function (jqXHR) {
                    reject(jqXHR.responseJSON);
                });
            });
        },

        approve: (data) => {
            // Redirect to page review.
            window.location = PaypalPayments.replaceVars(paypal_brasil_settings.checkout_review_page_url, {
                PAY_ID: data.paymentID,
                PAYER_ID: data.payerID,
            });
        },

        error: (response) => {
            // Only do that if there's a JSON response.
            if (response) {
                // Add the notices.
                PaypalPayments.setNotices(response.data.error_notice);
                // Scroll screen to top.
                PaypalPayments.scrollTop();
            }
        },

        cancel: () => {
            // Add notices.
            PaypalPayments.setNotices(paypal_brasil_shortcut_settings.cancel_message);
            // Scroll screen to top.
            PaypalPayments.scrollTop();
        }

    },

    cart: {

        create: () => {
            return new Promise((resolve, reject) => {
                PaypalPayments.makeRequest('shortcut-cart', {
                    nonce: paypal_brasil_settings.nonce,
                }).done(function (response) {
                    resolve(response.data.ec);
                }).fail(function (jqXHR) {
                    reject(jqXHR.responseJSON);
                });
            });
        },

        approve: (data) => {
            // Redirect to page review.
            window.location = PaypalPayments.replaceVars(paypal_brasil_settings.checkout_review_page_url, {
                PAY_ID: data.paymentID,
                PAYER_ID: data.payerID,
            });
        },

        error: (response) => {
            // Only do that if there's a JSON response.
            if (response) {
                // Add the notices.
                PaypalPayments.setNotices(response.data.error_notice);
                // Scroll screen to top.
                PaypalPayments.scrollTop();
            }
        },

        cancel: () => {
            // Update the cart to render button again.
            PaypalPayments.triggerUpdateCart();
            // Add notices.
            PaypalPayments.setNotices(paypal_brasil_shortcut_settings.cancel_message);
            // Scroll screen to top.
            PaypalPayments.scrollTop();
        }

    }

};
