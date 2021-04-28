import {PaypalPayments} from "../frontend-shared";
import {paymentReferenceTransaction} from "./frontend-reference-transaction-api";

declare const paypal: any;
declare const paypal_brasil_settings: any;
declare const paypal_brasil_reference_transaction_settings: any;

class PaypalPaymentsReferenceTransaction extends PaypalPayments {

    constructor() {
        super();
        const $body = jQuery('body');
        const $form = jQuery('form.woocommerce-checkout, form#order_review');
        // Update checkout button when WooCommerce checkout is updated.
        $body.on('updated_checkout', this.updateCheckoutButton);
        // Update checkout button when payment method is changed.
        $form.on('change', '[name=payment_method]', this.updateCheckoutButton);
        // Update checkout button when billing agreement action is changed.
        $form.on('change', '.paypal-brasil-billing-agreement-option-radio', this.updateCheckoutButton);
        // Render button when WooCommerce checkout is updated.
        $body.on('updated_checkout', this.renderPayPalButton);
        // Insert uuid
        this.insertUuid();
        $body.on('updated_checkout', this.insertUuid);
        // If is order pay page, trigger checkout update.
        if (paypal_brasil_settings.is_order_pay_page) {
            jQuery('body').trigger('updated_checkout');
        }
    }

    /**
     * Insert UUID when checkout is updated.
     */
    insertUuid() {
        const uuid = paypal_brasil_reference_transaction_settings.uuid;
        const $container = jQuery('#paypal-brasil-uuid');

        $container.val(uuid);
    }

    /**
     * Update the status of checkout button.
     */
    updateCheckoutButton() {
        // If the Paypal Payments is selected and is to create a new billing agreement, show the PayPal button.
        if (PaypalPayments.isPaypalPaymentsSelected() && PaypalPaymentsReferenceTransaction.isCreateBillingAgreementSelected()) {
            PaypalPayments.showPaypalButton();
        } else { // Show the default button if is to process billing agreement or isn't selected PayPal.
            PaypalPayments.showDefaultButton();
        }
    }

    /**
     * Get if create billing agreement radio is selected.
     */
    static isCreateBillingAgreementSelected() {
        return !jQuery('.paypal-brasil-billing-agreement-option-radio:checked').val();
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
            createBillingAgreement: paymentReferenceTransaction.create,
            onApprove: paymentReferenceTransaction.approve,
            onError: paymentReferenceTransaction.error,
            onCancel: paymentReferenceTransaction.cancel,
        }).render('#paypal-button');
    }

}

new PaypalPaymentsReferenceTransaction();