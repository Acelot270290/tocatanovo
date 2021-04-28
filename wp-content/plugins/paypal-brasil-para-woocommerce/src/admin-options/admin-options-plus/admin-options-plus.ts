import Vue from 'vue'
import Component from "vue-class-component";

declare const jQuery: any;
declare const ajaxurl: string;

// This is the WordPress localized settings.
declare const paypal_brasil_admin_options_plus: {
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
    form_height: string,
    invoice_id_prefix: string,
    debug: string,
};

@Component({
    template: paypal_brasil_admin_options_plus.template,
})
export default class AdminOptionsPlus extends Vue {

    enabled = '';
    title = '';
    titleComplement = '';
    mode = '';
    client = {live: '', sandbox: ''};
    secret = {live: '', sandbox: ''};
    formHeight = '';
    invoiceIdPrefix = '';
    debugMode = '';

    showAlert = true

    constructor() {
        super();
        this.$options.el = '#admin-options-plus';
    }

    beforeMount() {
        // @ts-ignore
        const options: paypal_brasil_admin_options_plus = JSON.parse(this.$el.getAttribute('data-options'));
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
        this.formHeight = options.form_height || '';
        this.invoiceIdPrefix = options.invoice_id_prefix || '';
        this.debugMode = options.debug || '';
    }

    isLive() {
        return this.mode === 'live';
    }

    isEnabled() {
        return this.enabled === '1';
    }

    closeAlert() {
    	this.showAlert = false;
		}

}

new AdminOptionsPlus();
