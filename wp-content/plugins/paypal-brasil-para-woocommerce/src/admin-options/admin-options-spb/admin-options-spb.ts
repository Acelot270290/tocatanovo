import Vue from 'vue'
import Component from "vue-class-component";

declare const jQuery: any;
declare const ajaxurl: string;

// This is the WordPress localized settings.
declare const paypal_brasil_admin_options_spb: {
    template: string,
    enabled: string,
    title: string,
    title_complement: string,
    mode: string,
    client: {
        live: string,
        sandbox: string,
    },
    secret: {
        live: string,
        sandbox: string,
    },
    button: {
        format: string,
        color: string,
    },
    shortcut_enabled: string,
    reference_enabled: string,
    invoice_id_prefix: string,
    debug: string,
    images_path: string;
    woocommerce_settings: {
        enable_checkout_login_reminder: string,
        enable_signup_and_login_from_checkout: string,
        enable_guest_checkout: string,
    }
};

@Component({
    template: paypal_brasil_admin_options_spb.template,
})
export default class AdminOptionsSpb extends Vue {

    enabled = '';
    title = '';
    titleComplement = '';
    mode = '';
    client = {live: '', sandbox: ''};
    secret = {live: '', sandbox: ''};
    button = {format: '', color: ''};
    shortcutEnabled = '';
    referenceEnabled = '';
    invoiceIdPrefix = '';
    debugMode = '';

    imagesPath: string;

    woocommerce_settings = {
        enable_checkout_login_reminder: 'no',
        enable_signup_and_login_from_checkout: 'no',
        enable_guest_checkout: 'no',
    };

    updateSettingsState = {
        executed: false,
        loading: false,
        success: false,
    };

    constructor() {
        super();

        this.$options.el = '#admin-options-spb';

        this.imagesPath = paypal_brasil_admin_options_spb.images_path;

        // Remove default message.
        // jQuery('#message.updated.inline').remove();
    }

    beforeMount() {
        // @ts-ignore
        const options: paypal_brasil_admin_options_spb = JSON.parse(this.$el.getAttribute('data-options'));
        this.enabled = options.enabled || '';
        this.title = options.title || '';
        this.titleComplement = options.title_complement || '';
        this.mode = options.mode || 'live';
        this.client = {
            live: options.client.live || '',
            sandbox: options.client.sandbox || '',
        };
        this.secret = {
            live: options.secret.live || '',
            sandbox: options.secret.sandbox || '',
        };
        this.button = {
            format: options.button.format || 'rect',
            color: options.button.color || 'blue',
        };
        this.shortcutEnabled = options.shortcut_enabled || '';
        this.referenceEnabled = options.reference_enabled || '';
        this.invoiceIdPrefix = options.invoice_id_prefix || '';
        this.debugMode = options.debug || '';
        this.woocommerce_settings = {
            enable_checkout_login_reminder: options.woocommerce_settings.enable_checkout_login_reminder,
            enable_signup_and_login_from_checkout: options.woocommerce_settings.enable_signup_and_login_from_checkout,
            enable_guest_checkout: options.woocommerce_settings.enable_guest_checkout,
        }
    }

    isLive() {
        return this.mode === 'live';
    }

    isEnabled() {
        return this.enabled === '1';
    }

    updateSettings() {
        this.updateSettingsState.executed = true;
        this.updateSettingsState.loading = true;

        return new Promise((resolve, reject) => {
            jQuery.post(
                ajaxurl,
                {
                    'action': 'paypal_brasil_wc_settings',
                    'enable': 'yes',
                }
            ).done((response: object) => {
                this.updateSettingsState.success = true;
                jQuery('#message-reference-transaction-settings').remove();
                setTimeout(() => {
                    resolve(response);
                }, 1000);
            }).fail(() => {
                this.updateSettingsState.success = false;
                reject();
            }).always(() => {
                this.updateSettingsState.loading = false;
            })
        });
    }

}

new AdminOptionsSpb();