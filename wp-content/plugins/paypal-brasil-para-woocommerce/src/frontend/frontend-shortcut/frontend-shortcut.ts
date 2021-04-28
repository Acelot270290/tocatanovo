import {PaypalPayments} from "../frontend-shared";
import {paymentShortcut} from "./frontend-shortcut-api";

declare const paypal: any;
declare const paypal_brasil_settings: any;

class PaypalPaymentsShortcut extends PaypalPayments {

    constructor() {
        super();
        // Render cart button.
        jQuery('body')
            .on('updated_shipping_method', this.renderCartButton)
            .on('updated_wc_div', this.renderCartButton)
            .on('updated_mini_cart', this.renderMiniCartButton);
        // Render cart for the first time.
        this.renderCartButton();
        this.renderMiniCartButton();
    }

    renderMiniCartButton() {
        const $elements = jQuery('.shortcut-button-mini-cart');
        $elements.each((index, element) => {
            paypal.Buttons({
                locale: 'pt_BR',
                style: {
                    size: 'responsive',
                    color: paypal_brasil_settings.style.color,
                    shape: paypal_brasil_settings.style.format,
                    label: 'buynow',
                },
                createOrder: paymentShortcut.miniCart.create,
                onApprove: paymentShortcut.miniCart.approve,
                onError: paymentShortcut.miniCart.error,
                onCancel: paymentShortcut.miniCart.cancel,
            }).render(element);
        });
    }

    renderCartButton() {
        const $elements = jQuery('.wc-proceed-to-checkout .shortcut-button');
        $elements.each((index, element) => {
            paypal.Buttons({
                locale: 'pt_BR',
                style: {
                    size: 'responsive',
                    color: paypal_brasil_settings.style.color,
                    shape: paypal_brasil_settings.style.format,
                    label: 'buynow',
                },
                createOrder: paymentShortcut.cart.create,
                onApprove: paymentShortcut.cart.approve,
                onError: paymentShortcut.cart.error,
                onCancel: paymentShortcut.cart.cancel,
            }).render(element);
        });
    }

}

new PaypalPaymentsShortcut();