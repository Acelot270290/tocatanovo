declare const pwc: any;
declare const jQuery: any;
declare const PAYPAL: any;
declare const wc_ppp_brasil_data: any;

class WC_PPP_Brasil_Checkout {

    private instance: any;
    private forceSubmit: boolean;

    private $body: any;
    private $form: any;
    private $overlay: any;
    private $loading: any;
    private $inputData: any;
    private $inputResponse: any;
    private $inputError: any;
    private $inputSubmit: any;
    private $containerDummy: any;

    constructor() {
        this.forceSubmit = false;
        this.log('heading', 'PayPal Plus logging enabled\n');
        this.log('info', 'Backend data:');
        this.log('data', wc_ppp_brasil_data);
        // Set the body element.
        this.$body = jQuery(document.body);
        // Log document.body detection.
        if (this.$body.length) {
            this.log('info', 'HTML body detected.');
        } else {
            this.log('error', "HTML body didn't detected.");
        }
        // Set the form element
        this.$form = wc_ppp_brasil_data['order_pay'] ? jQuery('form#order_review') : jQuery('form.checkout.woocommerce-checkout');
        // Log form element
        if (wc_ppp_brasil_data['order_pay']) {
            this.log('info', 'Running script as order pay.');
        } else {
            this.log('info', 'Running script as order review.');
        }
        if (this.$form.length) {
            this.log('info', 'Detected form.checkout.woocommerce-checkout element.');
            this.log('data', this.$form);
        } else {
            this.log('error', "Didn't detect form.checkout.woocommerce-checkout element.");
        }
        // Listen for input/select changes.
        this.listenInputChanges();
        // Listen for updated checkout.
        this.$body.on('updated_checkout', this.onUpdatedCheckout);
        // Listen for the form submit.
        this.$form.on('submit', this.onSubmitForm);
        // Listen for change on payment method change.
        this.$form.on('change', '[name=payment_method]', this.forceUpdateCheckout);
        // Add event listener.
        window.addEventListener('message', this.messageListener, false);
        // Trigger update checkout on order pay page
        if (wc_ppp_brasil_data['order_pay']) {
            jQuery(function ($) {
                jQuery('body').trigger('updated_checkout');
            });
        }
    }

    /**
     * Add event listener for input/select changes and trigger the update checkout.
     */
    listenInputChanges() {
        const keySelectors = [
            '[name=billing_first_name]',
            '[name=billing_last_name]',
            '[name=billing_cpf]',
            '[name=billing_cnpj]',
            '[name=billing_phone]',
            '[name=billing_address_1]',
            '[name=billing_number]',
            '[name=billing_address_2]',
            '[name=billing_neighborhood]',
            '[name=billing_city]',
            '[name=billing_state]',
            '[name=billing_country]',
            '[name=billing_email]',
        ];
        const changeSelectors = [
            '[name=billing_persontype]',
        ];

        jQuery(keySelectors.join(',')).on('keyup', () => this.updateCheckout());
        this.log('info', 'Listening for keyup to following elements:');
        this.log('data', keySelectors);
        jQuery(changeSelectors.join(',')).on('change', () => this.updateCheckout());
        this.log('info', 'Listening for change to following elements:');
        this.log('data', changeSelectors);
    }

    /**
     * Run after form submit to submit the iframe and after submit the form again.
     * @param event
     */
    onSubmitForm = (event: any) => {
        const checked = jQuery('#payment_method_' + wc_ppp_brasil_data.id + ':checked');
        this.log('info', 'Checking if PayPal Payment method is checked...');
        this.log('data', !!checked.length);
        if (!jQuery(`#payment_method_${wc_ppp_brasil_data.id}`).length) {
            this.log('error', `PayPal Plus check button wasn't detected. Should have an element #payment_method_${wc_ppp_brasil_data.id}`)
        }
        // Block the form in order pay, as it isn't default.
        if (wc_ppp_brasil_data['order_pay']) {
            this.$form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
        // Check if is not forced submit and prevent submit before submit PayPal iframe or isn't the payment selected.
        if (this.forceSubmit && checked.length) {
            this.log('info', 'Form will be forced to submit.');
        } else if (checked.length) {
            this.log('info', `Form won't be forced to submit, will try to contact PayPal iframe first.`);
        }
        // Submit the iframe
        if (!this.forceSubmit && checked.length) {
            event.preventDefault();
            event.stopImmediatePropagation();
            // Check if we have any instance
            if (this.instance) {
                this.instance.doContinue();
            } else {
                this.log('error', `We don't have the iframe instance, something wrong may have occurred. May be the fields isn't fulfilled.`);
            }
        }
    };

    /**
     * Trigger the update checkout to reload the checkout.
     */
    updateCheckout = (event: any = null) => {
        if (event) {
            event.preventDefault();
        }
        this.triggerUpdateCheckout();
    };

    /**
     * Force to update checkout.
     *
     * @param event
     */
    forceUpdateCheckout = (event: any = null) => {
        if (event) {
            event.preventDefault();
        }

        this.log('info', 'Updating checkout...');
        this.$body.trigger('update_checkout');
    };

    /**
     * Debounce the trigger checkout.
     *
     * @type {()=>any}
     */
    triggerUpdateCheckout = this.debounce(() => {
        this.forceUpdateCheckout();
    }, 1000);

    /**
     * Create the iframe after update the checkout.
     */
    onUpdatedCheckout = () => {
        this.$inputData = jQuery('#wc-ppp-brasil-data');
        this.$inputResponse = jQuery('#wc-ppp-brasil-response');
        this.$inputError = jQuery('#wc-ppp-brasil-error');
        this.$inputSubmit = jQuery('#place_order');
        if (!this.$inputSubmit.length) {
            this.log('error', `Input submit wasn't found. Should have the #place_order element in the form.`);
        }
        this.$overlay = jQuery('#wc-ppb-brasil-container-overlay');
        this.$loading = jQuery('#wc-ppp-brasil-container-loading');
        this.$containerDummy = jQuery('#wc-ppp-brasil-container-dummy');
        this.$overlay.on('click', '[data-action=update-checkout]', this.updateCheckout);
        this.showOverlay();

        const inputData = this.$inputData.val();

        const phpErrorData = jQuery('#wc-ppp-brasil-api-error-data').val();

        if (phpErrorData) {
            this.log('error', 'There was an error with following data:');
            this.log('data', JSON.parse(phpErrorData));
        }

        try {
            if (inputData) {
                const data = JSON.parse(inputData);
                this.log('info', 'Creating iframe with data:');
                this.log('data', data);
                if (data.invalid.length !== 0) {
                    this.log('error', `There's some invalid data. Iframe will render dummy version:`);
                    this.log('data', data.invalid);

                    let html = '(' + Object.values(data.invalid).join(', ') + ')';
                    this.$overlay.find('div.missing-items').html(html);
                }
                this.createIframe(data);
            }
        } catch (error) {
            this.log('error', 'There was some error creating the iframe.');
            this.log('info', 'Data received:');
            this.log('data', inputData);
            this.log('info', 'Error:');
            this.log('data', error);
        }
    };

    /**
     * Create the iframe with the data.
     * @param data
     */
    createIframe(data: any) {
        // If it's not a dummy data, remove the overlay.
        if (!data.dummy) {
            this.hideOverlay();
            // Show loading.
            this.showLoading();
            // Settings
            let settings = {
                approvalUrl: data.approval_url,
                placeholder: 'wc-ppp-brasil-container',
                mode: wc_ppp_brasil_data['mode'],
                payerFirstName: data.first_name,
                payerLastName: data.last_name,
                payerPhone: data.phone,
                language: wc_ppp_brasil_data.language,
                country: wc_ppp_brasil_data.country,
                payerEmail: data.email,
                rememberedCards: data.remembered_cards,
            };
            if (wc_ppp_brasil_data['form_height']) {
                settings['iframeHeight'] = wc_ppp_brasil_data['form_height'];
            }
            // Fill conditional data
            if (wc_ppp_brasil_data.show_payer_tax_id) {
                settings['payerTaxId'] = data.person_type === '1' ? data.cpf : data.cnpj;
                settings['payerTaxIdType'] = data.person_type === '1' ? 'BR_CPF' : 'BR_CNPJ';
            } else {
                settings['payerTaxId'] = '';
            }
            this.log('info', 'Settings for iframe:');
            this.log('data', settings);
            // Instance the PPP.
            this.instance = PAYPAL.apps.PPP(settings);
            // Force clean everything.
            this.$inputError.val('');
            this.$inputResponse.val('');
            // Reset the force submit
            this.forceSubmit = false;
        } else {
            this.$containerDummy.removeClass('hidden');
        }
    }

    /**
     * Hide the overlay in container.
     */
    hideOverlay() {
        this.$overlay.addClass('hidden');
    }

    showOverlay() {
        this.$overlay.removeClass('hidden');
    }

    hideLoading() {
        // this.$loading.addClass('hidden');
    }

    showLoading() {
        // this.$loading.removeClass('hidden');
    }

    /**
     * Listen for messages in the page.ˆ
     * @param event
     */
    messageListener = (event: any) => {
        try {
            const message = JSON.parse(event.data);
            this.log('info', 'Received a message:');
            this.log('data', message);

            // Check if is iframe error handling or is just an action.
            if (typeof message['cause'] !== 'undefined') {
                this.log('error', 'This message is an iframe error!');
                this.treatIframeError(message);
            } else {
                this.treatIframeAction(message);
            }
        } catch (err) {
        }
    };

    treatIframeAction(message: any) {
        switch (message['action']) {
            // When call to enable the continue button.
            case 'enableContinueButton':
                this.enableSubmitButton();
                break;
            // When call to disable continue button.
            case 'disableContinueButton':
                this.disableSubmitButton();
                break;
            // When the iframe was submitted and we have the payment info.
            case 'checkout':
                const data = {
                    payer_id: message['result']['payer']['payer_info']['payer_id'],
                    remembered_cards_token: message['result']['rememberedCards'],
                };
                this.log('info', ['Continue allowed:', data]);
                this.log('info', 'Success message received from iframe:');
                // Add the data in the inputs
                this.$inputResponse.val(JSON.stringify(data));
                // Submit the form
                this.forceSubmitForm();
                break;
            // In case we get some error.
            case 'onError':
                this.$inputResponse.val('');
                break;
            case 'loaded':
                this.hideLoading();
                break;
        }
    }

    /**
     * Treat the iframe errors.
     * @param message
     */
    treatIframeError(message: any) {
        const cause = message.replace(/[^\sA-Za-z0-9_]+/g, '');
        switch (cause) {
            case 'CHECK_ENTRY':
                this.showMessage('<div class="woocommerce-error">' + wc_ppp_brasil_data['messages']['check_entry'] + '</div>');
                break;
            default:
                this.log(`This message won't be treated, so form will be submitted.`);
                this.$inputError.val(cause);
                this.forceSubmitForm();
                break;
        }
    }

    /**
     * Disable the submit button.
     */
    private disableSubmitButton() {
        this.$inputSubmit.prop('disabled', true);
    }

    /**
     * Enable the submit button.
     */
    private enableSubmitButton() {
        this.$inputSubmit.prop('disabled', false);
    }

    /**
     * Force the form submit.
     */
    private forceSubmitForm() {
        this.forceSubmit = true;
        this.$form.submit();
    }

    private showMessage(messages: string) {
        const $form = jQuery('form.checkout');

        if (!$form.length) {
            this.log('error', `Isn't possible to find the form.checkout element.`);
        }

        // Remove notices from all sources
        jQuery('.woocommerce-error, .woocommerce-message').remove();

        // Add new errors
        if (messages) {
            $form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview">' + messages + '</div>');

            // Lose focus for all fields
            $form.find('.input-text, select, input:checkbox').blur();

            // Scroll to top
            jQuery('html, body').animate({
                scrollTop: ($form.offset().top - 100)
            }, 1000);
        }

    }

    private debounce(func, wait, immediate = false) {
        let timeout;
        return function () {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };

    private log(type: string, ...data) {
        // Only log when debug_mode is enabled.
        if (!wc_ppp_brasil_data.debug_mode) {
            return;
        }
        // Log each type.
        switch (type) {
            case 'heading':
                pwc().color("#003087").size(25).bold().log(data);
                break;
            case 'log':
                pwc().log(data);
                break;
            case 'info':
                pwc().bold().italic().color('#009cde').info(data);
                break;
            case 'warn':
                pwc().warn(data);
                break;
            case 'error':
                pwc().error(data);
                break;
            case 'data':
                data.forEach(item => console.log(item));
                break;
            case 'custom-message':
                pwc().color('#012169').bold().italic().log(data);
                break;
        }
    }

}

new WC_PPP_Brasil_Checkout();
