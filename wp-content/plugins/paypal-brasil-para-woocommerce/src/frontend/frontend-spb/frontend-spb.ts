import {PaypalPayments} from "../frontend-shared";
import {paymentSpb} from "./frontend-spb-api";

declare const paypal: any;
declare const paypal_brasil_settings: any;
declare const paypal_brasil_spb_settings: any;

class PaypalPaymentsSpb extends PaypalPayments {

    constructor() {
        // Needs to call super.
        super();
        // Store jQuery variables.
        const $body = jQuery('body');
        // Update checkout button when WooCommerce checkout is updated.
        $body.on('updated_checkout', this.updateCheckoutButton);
        // Update checkout button when payment method is changed.
        jQuery('form.woocommerce-checkout, form#order_review').on('change', '[name=payment_method]', this.updateCheckoutButton);
        // Render button when WooCommerce checkout is updated.
        $body.on('updated_checkout', this.renderPayPalButton);
        // If is order pay page, trigger checkout update.
        if (paypal_brasil_settings.is_order_pay_page) {
            jQuery('body').trigger('updated_checkout');
        }
    }

    /**
     * Update the status of checkout button.
     */
    updateCheckoutButton() {
        // If the Paypal Payments is selected show the PayPal button.
        if (PaypalPayments.isPaypalPaymentsSelected()) {
            PaypalPayments.showPaypalButton();
        } else {
            PaypalPayments.showDefaultButton();
        }
    }

    /**
     * Render PayPal Button.
     */
    renderPayPalButton() {
        document.getElementById('paypal-button').innerHTML = ''
        paypal.Buttons({
            locale: 'pt_BR',
            style: {
                size: 'responsive',
                color: paypal_brasil_settings.style.color,
                shape: paypal_brasil_settings.style.format,
                label: 'pay',
            },
            createOrder: paymentSpb.create,
            onApprove: paymentSpb.approve,
            onError: paymentSpb.error,
            onCancel: paymentSpb.cancel,
        }).render('#paypal-button');
    }

}

new PaypalPaymentsSpb();