<?php
/**
 * WooCommerce PagSeguro Assinaturas API class
 *
 * @package WooCommerce_PagSeguro_Assinaturas/Classes/Gateway
 * @version 1.0
 */

class WC_PagSeguro_Gateway_Assinaturas extends WC_Payment_Gateway_CC {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
			
		self::$instance = $this;

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			$this->id                 = 'pagseguro_assinaturas';
			$this->icon               = apply_filters( 'woocommerce_pagseguro_assinaturas_icon', plugins_url( 'assets/images/pagseguro.png', plugin_dir_path( __FILE__ ) ) );
			$this->method_title       = __( 'PagSeguro Recorrente', 'woocommerce-pagseguro-assinaturas' );
			$this->method_description = __( 'Accept payments by credit card using the PagSeguro Recorrente.', 'woocommerce-pagseguro-assinaturas' );
			$this->order_button_text  = __( 'Proceed to payment', 'woocommerce-pagseguro-assinaturas' );
			// Subscriptions
			$this->supports = apply_filters('pagseguro_assinaturas_supports_array', array( 
				'subscriptions',
				'subscription_cancellation', 
				'subscription_suspension', 
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_admin',
				'subscription_payment_method_change_customer',
				'default_credit_card_form' 
			) );
			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables.
			$this->title             = $this->get_option( 'title' );
			$this->description       = $this->get_option( 'description' );
			$this->email             = $this->get_option( 'email' );
			$this->token             = $this->get_option( 'token' );
			$this->sandbox_email     = $this->get_option( 'sandbox_email' );
			$this->sandbox_token     = $this->get_option( 'sandbox_token' );
			$this->invoice_prefix    = $this->get_option( 'invoice_prefix', 'WC-' );
			$this->sandbox           = $this->get_option( 'sandbox', 'no' );
			$this->debug             = $this->get_option( 'debug' );
			$this->send_only_total	 = $this->get_option( 'send_only_total' );
			$this->weekly_plan	 	 = $this->get_option( 'weekly_plan', '' );
			$this->monthly_plan	 	 = $this->get_option( 'monthly_plan', '' );
			$this->bimonthly_plan	 = $this->get_option( 'bimonthly_plan', '' );
			$this->trimonthly_plan	 = $this->get_option( 'trimonthly_plan', '' );
			$this->semiannually_plan = $this->get_option( 'semiannually_plan', '' );
			$this->yearly_plan	 	 = $this->get_option( 'yearly_plan', '' );
			$this->sandbox_weekly_plan	 = $this->get_option( 'sandbox_weekly_plan', '' );
			$this->sandbox_monthly_plan	 = $this->get_option( 'sandbox_monthly_plan', '' );
			$this->sandbox_bimonthly_plan	 = $this->get_option( 'sandbox_bimonthly_plan', '' );
			$this->sandbox_trimonthly_plan	 = $this->get_option( 'sandbox_trimonthly_plan', '' );
			$this->sandbox_semiannually_plan = $this->get_option( 'sandbox_semiannually_plan', '' );
			$this->sandbox_yearly_plan	 = $this->get_option( 'sandbox_yearly_plan', '' );


			// Added in version 1.6.5
			$this->cc_hide_cpf_field 		= apply_filters('pagseguro_assinaturas_hide_cpf_field', false);
			$this->cc_hide_birthdate_field 	= apply_filters('pagseguro_assinaturas_hide_birthdate_field', false);
			$this->cc_hide_phone_field 		= apply_filters('pagseguro_assinaturas_hide_phone_field', false);


			// Active logs.
			if ( 'yes' == $this->debug ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					$this->log = wc_get_logger();
				} else {
					$this->log = new WC_Logger();
				}
			}

			$this->api = new WC_PagSeguro_Assinaturas_API( $this );
			
			$this->init_actions();

		}
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'pagseguro-assinaturas-admin', plugins_url( 'assets/js/admin/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_PagSeguro_Assinaturas::VERSION, true );

		include dirname( __FILE__ ) . '/admin/views/html-admin-page.php';
	}

	function init_actions() {

		add_action( 'woocommerce_api_wc_pagseguro_assinaturas_gateway', 	array( $this, 'pagseguro_ipn_handler' ) );
		add_action( 'valid_pagseguro_assinaturas_ipn_request',				array( $this, 'process_ipn_request') );
		add_action( 'wp_enqueue_scripts', 									array( $this, 'checkout_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );
		add_filter( 'woocommerce_credit_card_form_fields', 					array( $this, 'filter_cc_fields' ), 10, 2 );
		add_filter( 'woocommerce_subscription_payment_meta', 				array( $this, 'add_subscription_payment_meta' ), 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', 		array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		add_action( 'woocommerce_subscription_status_updated', 				array( $this, 'subscription_status_changed' ), 10, 3 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_new-payment-method', array( $this, 'can_subscription_be_updated_to_new_payment_method'), 20, 2 );
		add_filter('woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'can_update_all_subscriptions' ), 20, 3 );
	}	


	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' === get_woocommerce_currency();
	}

	/**
	 * Get email.
	 *
	 * @return string
	 */
	public function get_email() {
		return 'yes' === $this->sandbox ? $this->sandbox_email : $this->email;
	}

	/**
	 * Get token.
	 *
	 * @return string
	 */
	public function get_token() {
		return 'yes' === $this->sandbox ? $this->sandbox_token : $this->token;
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = 'yes' === $this->get_option( 'enabled' ) && '' !== $this->get_email() && '' !== $this->get_token() && $this->using_supported_currency() && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() || WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment );

		if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-pagseguro-assinaturas' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-pagseguro-assinaturas' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PagSeguro Recorrente', 'woocommerce-pagseguro-assinaturas' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-pagseguro-assinaturas' ),
				'desc_tip'    => true,
				'default'     => __( 'PagSeguro Recorrente', 'woocommerce-pagseguro-assinaturas' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => __( 'Pay via PagSeguro', 'woocommerce-pagseguro-assinaturas' ),
			),
			'integration' => array(
				'title'       => __( 'Integration', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'title',
				'description' => '',
			),
			'sandbox' => array(
				'title'       => __( 'PagSeguro Sandbox', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable PagSeguro Sandbox', 'woocommerce-pagseguro-assinaturas' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'PagSeguro Sandbox can be used to test the payments.', 'woocommerce-pagseguro-assinaturas' ),
			),
			'email' => array(
				'title'       => __( 'PagSeguro Email', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Please enter your PagSeguro email address. This is needed in order to take payment.', 'woocommerce-pagseguro-assinaturas' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'token' => array(
				'title'       => __( 'PagSeguro Token', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter your PagSeguro token. This is needed to process the payment and notifications. Is possible generate a new token %s.', 'woocommerce-pagseguro-assinaturas' ), '<a href="https://pagseguro.uol.com.br/integracao/token-de-seguranca.jhtml">' . __( 'here', 'woocommerce-pagseguro-assinaturas' ) . '</a>' ),
				'default'     => '',
			),

			'weekly_plan' => array(
				'title'       => __( 'Weekly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Weekly Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'monthly_plan' => array(
				'title'       => __( 'Monthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Monthly Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'bimonthly_plan' => array(
				'title'       => __( 'Bimonthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Bimonthly Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'trimonthly_plan' => array(
				'title'       => __( 'Trimonthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Trimonthly Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'semiannually_plan' => array(
				'title'       => __( 'Semiannually Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Semiannually Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'yearly_plan' => array(
				'title'       => __( 'Yearly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Yearly Plan Code (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_email' => array(
				'title'       => __( 'PagSeguro Sandbox Email', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter your PagSeguro sandbox email address. You can get your sandbox email %s.', 'woocommerce-pagseguro-assinaturas' ), '<a href="https://sandbox.pagseguro.uol.com.br/vendedor/configuracoes.html">' . __( 'here', 'woocommerce-pagseguro-assinaturas' ) . '</a>' ),
				'default'     => '',
			),
			'sandbox_token' => array(
				'title'       => __( 'PagSeguro Sandbox Token', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter your PagSeguro sandbox token. You can get your sandbox token %s.', 'woocommerce-pagseguro-assinaturas' ), '<a href="https://sandbox.pagseguro.uol.com.br/vendedor/configuracoes.html">' . __( 'here', 'woocommerce-pagseguro-assinaturas' ) . '</a>' ),
				'default'     => '',
			),
			'sandbox_weekly_plan' => array(
				'title'       => __( 'Sandbox Weekly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Weekly Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_monthly_plan' => array(
				'title'       => __( 'Sandbox Monthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Monthly Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_bimonthly_plan' => array(
				'title'       => __( 'Sandbox Bimonthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Bimonthly Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_trimonthly_plan' => array(
				'title'       => __( 'Sandbox Trimonthly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Trimonthly Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_semiannually_plan' => array(
				'title'       => __( 'Sandbox Semiannually Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Semiannually Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),
			'sandbox_yearly_plan' => array(
				'title'       => __( 'Sandbox Yearly Plan', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Yearly Plan Code on Sandbox (personalizado).', 'woocommerce-pagseguro-assinaturas' ) . ' ' . __( 'You can specify the code here, or let the plugin automatically create one for you.', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => '',
			),

			'behavior' => array(
				'title'       => __( 'Integration Behavior', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'title',
				'description' => '',
			),
			'send_only_total' => array(
				'title'   => __( 'Send only the order total', 'woocommerce-pagseguro-assinaturas' ),
				'type'    => 'checkbox',
				'label'   => __( 'If this option is enabled will only send the order total, not the list of items.', 'woocommerce-pagseguro-assinaturas' ),
				'default' => 'no',
			),
			'invoice_prefix' => array(
				'title'       => __( 'Invoice Prefix', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your PagSeguro account for multiple stores ensure this prefix is unqiue as PagSeguro will not allow orders with the same invoice number.', 'woocommerce-pagseguro-assinaturas' ),
				'desc_tip'    => true,
				'default'     => 'WC-',
			),
			'testing' => array(
				'title'       => __( 'Gateway Testing', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-pagseguro-assinaturas' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-pagseguro-assinaturas' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log PagSeguro events, such as API requests, inside %s', 'woocommerce-pagseguro-assinaturas' ), $this->get_log_view() ),
			)
		);
	}


	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		
		$load_scripts = false;

		if ( is_checkout() ) {
			$load_scripts = true;
		}
		if ( $this->is_available() ) {
			$load_scripts = true;
		}

		if ( false === $load_scripts ) {
			return;
		}
				
		$session_id = $this->api->get_session_id();
		$suffix     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'pagseguro-assinatura-checkout', plugins_url( 'assets/css/frontend/transparent-checkout' . $suffix . '.css', plugin_dir_path( __FILE__ ) ), array(), WC_PagSeguro_Assinaturas::VERSION );
		wp_enqueue_script( 'pagseguro-library', $this->api->get_direct_payment_url(), array(), null, true );
		wp_enqueue_script( 'pagseguro-assinatura-checkout', plugins_url( 'assets/js/frontend/transparent-checkout' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'pagseguro-library', 'woocommerce-extra-checkout-fields-for-brazil-front' ), WC_PagSeguro_Assinaturas::VERSION, true );

		wp_localize_script(
			'pagseguro-assinatura-checkout',
			'wc_pagseguro_assinatura_params',
			array(
				'session_id'         => $session_id,
				'session_error'      => __( 'Ocorreu um error. Por favor, atualize a página', 'woocommerce-pagseguro-assinaturas' ),
				'interest_free'      => __( 'interest free', 'woocommerce-pagseguro-assinaturas' ),
				'invalid_card'       => __( 'Invalid credit card number.', 'woocommerce-pagseguro-assinaturas' ),
				'invalid_expiry'     => __( 'Invalid expiry date, please use the MM / YYYY date format.', 'woocommerce-pagseguro-assinaturas' ),
				'expired_date'       => __( 'Please check the expiry date and use a valid format as MM / YYYY.', 'woocommerce-pagseguro-assinaturas' ),
				'general_error'      => __( 'Unable to process the data from your credit card on the PagSeguro, please try again or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' ),
				'empty_installments' => __( 'Select a number of installments.', 'woocommerce-pagseguro-assinaturas' ),
			)
		);

	}

	/**
	 * Process the payment
	 * Function called by WooCommerce to process the payment
	 * 
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// Processing subscription
		if ( function_exists( 'wcs_order_contains_subscription' )  ) {
			
			return $this->process_subscription( $order_id );

		}
	}


	function process_subscription( $order_id ) {

		$order = wc_get_order( $order_id );
		
		if ( isset( $_POST['pagseguro_assinaturas_sender_hash'] ) ) {
			
			$this->save_sender_hash( $order_id, $_POST['pagseguro_assinaturas_sender_hash'] );

			$response = $this->api->do_subscription_request( $order, $_POST );

			if ( isset( $response['data'] ) ) {
				
				$this->finalize_order( $order, $response['data'] );

				// Remove cart.
				WC()->cart->empty_cart();

				if ( isset( $response['url'] ) ) {
					return array(
						'result'   => 'success',
						'redirect' => $response['url'],
					);
				}

			} elseif( isset( $response['result'] ) && 'success' == $response['result'] ) {

				return array(
					'result'   => 'success',
					'redirect' => $response['redirect'],
				);
			
			} else {

				foreach ( $response['error'] as $error ) {
					wc_add_notice( $error, 'error' );
				}

				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}
		
		} else {
				
			wc_add_notice( __('Your session may have expired. Please refresh the page.', 'woocommerce-pagseguro-assinaturas'), 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

	}

	/**
	 * Update order status.
	 *
	 * @param array $posted PagSeguro post data.
	 */
	public function finalize_order( $order, $data = array() ) {
		
		$this->save_pagseguro_assinatura( $order->get_id(), $data['assinatura'], $data['tracker'] );

		if ( isset( $data['transactionCode'] ) ) {

			if( $order->get_total() > 0 ) {
				
				update_post_meta($order->get_id(), '_pagseguro_card_valid', 'yes' );
				$this->save_transaction_code( $order, $data['transactionCode'] ); 

			
			} else {
				// if order has free trial, value is 0.00, so finish it.
				$order->payment_complete();
			}

		} else {

			$order->update_status( 'on-hold', sprintf( __( 'Pagseguro: Waiting for credit card to be validated.', 'woocommerce-pagseguro-assinaturas' ),  $status ) );

		}

		return;

	}

	/**
	 * process_subscription_payment function for initial and recurrent payments.
	 *
	 * @access public
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 */
	public function process_subscription_payment( $order, $amount = 0 ) {

		if ( $amount <= 0 ) {
			
			if ( 'yes' === $this->debug ) {
				$this->log->add( $this->id, 'process_subscription_payment(): Sorry, the minimum allowed order total is 0.01 to use this payment method. Amount: ' . $amount );
			}

			return new WP_Error( 'pagseguro_error', __( 'Sorry, the minimum allowed order total is 0.01 to use this payment method.', 'woocommerce-pagseguro-assinaturas' ) );
		}

		$assinatura = get_post_meta( $order->get_id(), '_pagseguro_assinatura', true);
		// Or fail :(
		if ( ! $assinatura || '' == $assinatura ) {

			if ( 'yes' === $this->debug ) {
				$this->log->add( $this->id, 'process_subscription_payment(): Subscription on Pagseguro not found' );
			}

			return new WP_Error( 'pagseguro_error', __( 'Subscription on Pagseguro not found', 'woocommerce-pagseguro-assinaturas' ) );
		}

		$response = $this->api->make_subscription_payment( $assinatura, $order );

		// $response['transactionCode'] = 'TESTING';

		if( isset( $response['transactionCode']) ) { 

			return $this->save_transaction_code( $order, $response['transactionCode'] );

		} 

		if ( 'yes' === $this->debug ) {
			$this->log->add( $this->id, 'process_subscription_payment(): Error trying to make payment for order: ' . $order->get_id() );
		}

		return new WP_Error( 'pagseguro_error', __( 'Error trying to make payment for order', 'woocommerce-pagseguro-assinaturas' ) );

	}


	/**
	 * Process initial payment, after receiving notification of validation transaction
	 *
	 * @param string $order WC_Order
	 */
	public function process_initial_payment( WC_Order $order ) {

		if(  wcs_order_contains_subscription( $order, array( 'parent', 'renewal' ) ) && 'yes' != $order->get_meta('_pr_processing') ) {
			
			$order->update_meta_data('_pr_processing', 'yes');
			$order->save();

			$response = $this->process_subscription_payment( $order, $order->get_total() );

			if ( 'yes' === $this->debug ) {
				$this->log->add( $this->id, 'Processing payment on pending order: ' . $order->get_id() . '. Response:' . print_r($response, true) );
			}
		}

		return;
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 * @access public
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		if ( 'yes' === $this->debug ) {
			$this->log->add( $this->id, 'called scheduled_subscription_payment(). Order: ' . $renewal_order->get_id() );
		}

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Pagseguro Transaction Failed (%s)', 'woocommerce-pagseguro-assinaturas' ), $response->get_error_message() ) );
		}

		return;
	}

	/**
	 * Save transaction code and out order on-hold. Wait for IPN
	 *
	 * @access public
	 * @param string $code
	 */
	protected function save_transaction_code( $order, $transactionCode ) {
		// Received transactio code. Now waiting for IPN to update status of order.
		update_post_meta( $order->get_id(), '_pagseguro_transaction_id', sanitize_text_field( $transactionCode ) );
		
		$order->update_status( 'on-hold' );
		$order->add_order_note( __( 'PagSeguro: The buyer initiated the transaction, but so far the PagSeguro not received any payment information.', 'woocommerce-pagseguro-assinaturas' ) );

		if ( 'yes' === $this->debug ) {
			$this->log->add( $this->id, 'PagSeguro payment code for order ' . $order->get_id() . ' is: ' . $transactionCode  );
		}

		return $transactionCode;
	}


	/**
	 * IPN handler.
	 */
	public function pagseguro_ipn_handler() {
		@ob_clean();

		$ipn = $this->api->get_ipn_notification( $_POST );

		if ( $ipn ) {
			header( 'HTTP/1.1 200 OK' );
			do_action( 'valid_pagseguro_assinaturas_ipn_request', $ipn );
			exit();
		} else {
			wp_die( esc_html__( 'PagSeguro Request Unauthorized', 'woocommerce-pagseguro-assinaturass' ), esc_html__( 'PagSeguro Request Unauthorized', 'woocommerce-pagseguro-assinaturas' ), array( 'response' => 401 ) );
		}
	}


	/**
	 * IPN handler hooked to valid_pagseguro_assinaturas_ipn_request.
	 *
	 * @param $ipn Posted notification data from pagseguro.
	 */
	public function process_ipn_request( $ipn ) {

		if( defined('WC_PagSeguro_Assinaturas_IPN') )
			return;

		define('WC_PagSeguro_Assinaturas_IPN', true);

		// make sure we wait until the checkout process is over.
		// potentially remove this on next versions
		// sleep(2);

		if( isset( $_POST['notificationCode'] ) && isset( $_POST['notificationType'] ) ) {

			// if( get_transient( 'wcpgrn_' . $_POST['notificationCode']  ) ) {
			// 	return;
			// } 

			if( 'transaction' == $_POST['notificationType'] ) {
				//update order
				$this->update_order_status( $ipn );
			
			} elseif ( 'preApproval' == $_POST['notificationType']) {
				//update subscriptions
				$this->update_preapproval_status( $ipn );
			}

			// set_transient( 'wcpgrn_' . $_POST['notificationCode'], '1', HOUR_IN_SECONDS );

		}
		
		return;
	}

	/*
	 * Change status of assinatura no pagseguro
	 *
	*/
	function subscription_status_changed( $order, $new_status, $old_status ) {

		if( $new_status != 'cancelled')
			return;

		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$assinatura = get_post_meta( $order_id, '_pagseguro_assinatura', true);

		if( $assinatura != '') {

			// $response = $this->api->pagseguro_update_subscription_status( $assinatura, $order_id );
			$response = $this->api->pagseguro_cancel_subscription( $assinatura, $order_id );

			if( $response == true ) {

				$order->add_order_note( sprintf( __( 'PagSeguro: Subscription Cancelled (%s).', 'woocommerce-pagseguro-assinaturas' ), $assinatura ) );

			} else {

				$order->add_order_note( sprintf( __( 'PagSeguro: Problem Cancelling Subscription (%s).', 'woocommerce-pagseguro-assinaturas' ), $assinatura ) );

			}
		}
	} 


	/**
	 * Update order status.
	 *
	 * @param array $posted PagSeguro post data.
	 */
	public function update_order_status( $posted ) {
		if ( isset( $posted->reference ) ) {
			$id    = (int) str_replace( $this->invoice_prefix, '', $posted->reference );
			$order = wc_get_order( $id );

			// Check if order exists.
			if ( ! $order ) {
				return;
			}

			if( '' == get_post_meta($order->get_id(), '_pagseguro_assinatura', true ) ) {
				return;
			}

			// Checks whether the invoice number matches the order.
			// If true processes the payment.
			if ( $order->get_id() === $id ) {

				if ( 'yes' === $this->debug ) {
					$this->log->add( $this->id, 'PagSeguro payment status for order ' . $order->get_id() . ' is: ' . intval( $posted->status ) );
				}

				$transaction_id = get_post_meta($order->get_id(), '_pagseguro_transaction_id', true);
				$posted_transaction_id = str_replace('-', '', $posted->code );

				if( $posted_transaction_id == $transaction_id ) {

					switch ( intval( $posted->status ) ) {
						case 1:
							$order->update_status( 'on-hold' );
							$order->add_order_note( __( 'PagSeguro: The buyer initiated the transaction. Waiting for payment confirmation.', 'woocommerce-pagseguro-assinaturas' ) );
							break;
						case 2:
							$order->update_status( 'on-hold' );
							$order->add_order_note( __( 'PagSeguro: Payment under review.', 'woocommerce-pagseguro-assinaturas' )  );
							// Reduce stock for billets.
							if ( function_exists( 'wc_reduce_stock_levels' ) ) {
								wc_reduce_stock_levels( $order->get_id() );
							}

							break;
						case 3:
							// Sometimes PagSeguro should change an order from cancelled to paid, so we need to handle it.
							if ( method_exists( $order, 'get_status' ) && 'cancelled' === $order->get_status() ) {
								$order->update_status( 'processing', __( 'PagSeguro: Payment approved.', 'woocommerce-pagseguro-assinaturas' ) );
								wc_reduce_stock_levels( $order->get_id() );
							} else {
								$order->add_order_note( __( 'PagSeguro: Payment approved.', 'woocommerce-pagseguro-assinaturas' ) );

								// Changing the order for processing and reduces the stock.
								$order->payment_complete( sanitize_text_field( (string) $posted->code ) );
							}

							break;
						case 4:
							$order->add_order_note( __( 'PagSeguro: Payment completed and credited to your account.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 5:
							$order->update_status( 'on-hold' );
							$order->add_order_note( __( 'PagSeguro: Payment came into dispute.', 'woocommerce-pagseguro-assinaturas' ) );
							$this->send_email(
								/* translators: %s: order number */
								sprintf( __( 'Payment for order %s came into dispute', 'woocommerce-pagseguro-assinaturas' ), $order->get_id() ),
								__( 'Payment in dispute', 'woocommerce-pagseguro-assinaturas' ),
								/* translators: %s: order number */
								sprintf( __( 'Order %s has been marked as on-hold, because the payment came into dispute in PagSeguro.', 'woocommerce-pagseguro-assinaturas' ), $order->get_id() )
							);

							break;
						case 6:
							$order->update_status( 'refunded', __( 'PagSeguro: Payment refunded.', 'woocommerce-pagseguro-assinaturas' ) );
							$this->send_email(
								/* translators: %s: order number */
								sprintf( __( 'Payment for order %s refunded', 'woocommerce-pagseguro-assinaturas' ), $order->get_id() ),
								__( 'Payment refunded', 'woocommerce-pagseguro-assinaturas' ),
								/* translators: %s: order number */
								sprintf( __( 'Order %s has been marked as refunded by PagSeguro.', 'woocommerce-pagseguro-assinaturas' ), $order->get_id() )
							);

							if ( function_exists( 'wc_increase_stock_levels' ) ) {
								wc_increase_stock_levels( $order->get_id() );
							}

							break;
						case 7:

							if( wcs_order_contains_renewal( $order ) ) {

								// Maybe actvate this to test retry payments.
								add_filter('wcs_is_scheduled_payment_attempt', '__return_true' );

								$order->update_status( 'failed', __( 'PagSeguro: Payment cancelled.', 'woocommerce-pagseguro-assinaturas' ) );


								if ( function_exists( 'wc_increase_stock_levels' ) ) {
									wc_increase_stock_levels( $order->get_id() );
								}

							} else {

								$order->update_status( 'cancelled', __( 'PagSeguro: Payment canceled.', 'woocommerce-pagseguro-assinaturas' ) );

								if ( function_exists( 'wc_increase_stock_levels' ) ) {
									wc_increase_stock_levels( $order->get_id() );
								}

							}

							break;

						default:
							break;
					}
				} else {

					// If got to this point, it's probably a validation transaction
					if( (int) $posted->type == 1 && (int) $posted->status == 6 ) {
						
						$order->add_order_note( __( 'PagSeguro IPN: Credit Card Validated.', 'woocommerce-pagseguro-assinaturas' ) );
							
						$this->process_initial_payment( $order );
					
					} elseif( (int) $posted->type == 7 ) {

						$order->update_status( 'cancelled', __( 'PagSeguro: Payment canceled.', 'woocommerce-pagseguro-assinaturas' ) );
					
					} else {
						if ( 'yes' === $this->debug ) {
							$this->log->add( $this->id, '$posted_transaction_id ('.$posted_transaction_id.') and $transaction_id ('.$transaction_id.') no not match.' );
						}
					}
				}
			} else {
				if ( 'yes' === $this->debug ) {
					$this->log->add( $this->id, 'Error: Order Key does not match with PagSeguro reference.' );
				}
			}
		}
		return;
	}

	/**
	 * Update pre-approval status.
	 *
	 * @param array $posted PagSeguro post data.
	 */
	public function update_preapproval_status( $posted ) {
		
		if ( isset( $posted->reference ) && isset( $posted->code ) ) {
			$id    = (int) str_replace( $this->invoice_prefix, '', $posted->reference );
			$order = wc_get_order( $id );

			// Check if order exists.
			if ( ! $order ) {
				return;
			}

			$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

			// Checks whether the invoice number matches the order.
			// If true processes the payment.
			if ( $order_id === $id ) {

				if ( wcs_order_contains_subscription( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				} elseif ( wcs_order_contains_renewal( $order_id ) ) {
					$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				} else {
					$subscriptions = array();
				}

				if( empty( $subscriptions ) )
					return;

				$subscription = array_pop( $subscriptions );

				$assinatura = get_post_meta( $subscription->get_id(), '_pagseguro_assinatura', true);

				if( $assinatura == $posted->code ) {

					if ( 'yes' === $this->debug ) {
						$this->log->add( $this->id, 'PagSeguro preaproval status for subscription ' . $subscription->get_id() . ' is: ' . $posted->status );
					}

					if( isset( $posted->tracker ) ) {
						$tracker = (string) $posted->tracker;
						update_post_meta( $subscription->get_id(), '_pagseguro_tracker', sanitize_text_field( $tracker) );
					}

					switch ( $posted->status ) {
						case 'INITIATED':
							//O comprador iniciou o processo de pagamento, mas abandonou o checkout e não concluiu a compra.
							$subscription->add_order_note( __( 'PagSeguro: The buyer initiated the transaction, but so far the PagSeguro not received any payment information.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'PENDING':
							//O processo de pagamento foi concluído e transação está em análise ou aguardando a confirmação da operadora.
							$subscription->add_order_note( __( 'PagSeguro: Payment under review.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'ACTIVE':
							// A criação da recorrência, transação validadora ou transação recorrente foi aprovada.
							// And we don't update the status, because we want to make sure the the status is updated bases on the orders.
							$subscription->add_order_note( __( 'PagSeguro: The preApproval, authorization or recurring transaction was approved.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'PAYMENT_METHOD_CHANGE':
							// Uma transação retornou como "Cartão Expirado, Cancelado ou Bloqueado" e o cartão da recorrência precisa ser substituído pelo comprador.
							$subscription->add_order_note( __( 'PagSeguro: Credit card needs to be updated by buyer.', 'woocommerce-pagseguro-assinaturas' ) );
							$subscription->add_order_note( __( 'Your credit card needs to be updated', 'woocommerce-pagseguro-assinaturas' ), 1 );

							break;
						case 'SUSPENDED':
							// A recorrência foi suspensa pelo vendedor.
							$subscription->update_status( 'on-hold', __( 'PagSeguro: The preapproval was suspended.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'CANCELLED':
							// A criação da recorrência foi cancelada pelo PagSeguro
							$subscription->update_status( 'cancelled', __( 'PagSeguro: Preapproval was cancelled by Pagseguro.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'CANCELLED_BY_RECEIVER':
							// A recorrência foi cancelada a pedido do vendedor.
							$subscription->update_status( 'cancelled', __( 'PagSeguro: Preapproval was cancelled by shop owner.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'CANCELLED_BY_SENDER':
							// A recorrência foi cancelada a pedido do comprador.
							$subscription->update_status( 'cancelled', __( 'PagSeguro: Preapproval was cancelled by buyer.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						case 'EXPIRED':
							// A recorrência expirou por atingir a data limite da vigência ou por ter atingido o valor máximo de cobrança definido na cobrança do plano.
							$subscription->update_status( 'cancelled', __( 'PagSeguro: Preapproval expired.', 'woocommerce-pagseguro-assinaturas' ) );

							break;
						default:
							break;
					}
				} else {
					if ( 'yes' === $this->debug ) {
						$this->log->add( $this->id, 'Error: Preapproval code does not match with PagSeguro code.' );
					}
				}
			} else {
				if ( 'yes' === $this->debug ) {
					$this->log->add( $this->id, 'Error: Order Key does not match with PagSeguro reference.' );
				}
			}
		}
		return;
	}


	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	protected function send_email( $subject, $title, $message ) {
		$mailer = WC()->mailer();

		$mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
	}

	/**
	 * Get the Pagseguro plan for pre-approval of a customer
	 *
	 * @param string $period WooCommerce Subscriptions period
	 * @param int $interval WooCommerce Subscriptions interval
	 * @return string $plan Pagseguro Plan code for pre-approval of a customer
	 */
	public function get_pagseguro_plan( $period = 'month', $interval = 1 ) {

		$plan = '';
		
		if ( 'yes' === $this->sandbox ) {

			switch($period) {
				case 'month':
					if( 2 == $interval ) {
						$plan = $this->sandbox_bimonthly_plan;
					} elseif( 3 == $interval ) {
						$plan = $this->sandbox_trimonthly_plan;
					} elseif( 6 == $interval ) {
						$plan = $this->sandbox_semiannually_plan;
					} else {
						$plan = $this->sandbox_monthly_plan;
					}
					break;
				case 'week':
					$plan = $this->sandbox_weekly_plan;
					break;
				case 'year':
					$plan = $this->sandbox_yearly_plan;
					break;
			}

		} else {

			switch($period) {
				case 'month':
					if( 2 == $interval ) {
						$plan = $this->bimonthly_plan;
					} elseif( 3 == $interval ) {
						$plan = $this->trimonthly_plan;
					} elseif( 6 == $interval ) {
						$plan = $this->semiannually_plan;
					} else {
						$plan = $this->monthly_plan;
					}
					break;
				case 'week':
					$plan = $this->weekly_plan;
					break;
				case 'year':
					$plan = $this->yearly_plan;
					break;
			}

		}

		if( '' == $plan ) {

			$reponse = $this->api->create_manual_plan( $period, $interval );

			
			if(isset( $reponse['code'] ) ) {

				$option = '';

				if ( 'yes' === $this->sandbox ) {

					switch($period) {
						case 'month':
							if( 2 == $interval ) {
								$option = 'sandbox_bimonthly_plan';
							} elseif( 3 == $interval  ) {
								$option = 'sandbox_trimonthly_plan';
							} elseif( 6 == $interval  ) {
								$option = 'sandbox_semiannually_plan';
							} else {
								$option = 'sandbox_monthly_plan';
							}
							break;
						case 'week':
							$option = 'sandbox_weekly_plan';
							break;
						case 'year':
							$option = 'sandbox_yearly_plan';
							break;
					}

				} else {

					switch($period) {
						case 'month':
							if( 2 == $interval ) {
								$option = 'bimonthly_plan';
							} elseif( 3 == $interval  ) {
								$option = 'trimonthly_plan';
							} elseif( 6 == $interval  ) {
								$option = 'semiannually_plan';
							} else {
								$option = 'monthly_plan';
							}
							break;
						case 'week':
							$option = 'weekly_plan';
							break;
						case 'year':
							$option = 'yearly_plan';
							break;
					}

				}

				if( '' != $option ) {
					if( version_compare( WC_VERSION, '3.4.0', '>=') ) {
						$this->update_option( $option, $reponse['code'] );
					} else {
						$this->settings[ $option ] = $reponse['code'];
						update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
					}
				}

				return $reponse['code'];

			}

		}

		return $plan;
		
	}

	/**
	 * Get the corresponding period identifier in Pagseguro.
	 *
	 * @since 2.4
	 * @param string $period WooCommerce Subscriptions period
	 * @param int $interval WooCommerce Subscriptions interval
	 * @return string
	 */
	public function get_pagseguro_period( $period = 'month', $interval = 1 ) {

		$interval = intval( $interval ); 

		switch( $period ) {
			case 'month' :
				if( 2 == $interval ) {
					$period = 'bimonthly';
				} elseif( 3 == $interval  ) {
					$period = 'trimonthly';
				} elseif( 6 == $interval  ) {
					$period = 'semiannually';
				} else {
					$period = 'monthly';
				}
				break;
			case 'year' :
				$period = 'yearly';
				break;
			case 'week' :
				$period = 'weekly';
				break;
			default:
				$period = 'monthly';

		}

		return strtoupper( $period );
	}

	/**
	 * Get label for Pagseguro period identifier
	 *
	 * @since 2.4
	 * @param string $pagseguro_period Pagseguro pediod identifier
	 * @return string
	 */
	public function get_pagseguro_period_label( $pagseguro_period = 'MONTLHY' ) {
		// Register for translation purposes
		$array = array(
			'WEEKLY' 		=> __('WEEKLY', 'woocommerce-pagseguro-assinaturas' ),
			'MONTHLY' 		=> __('MONTHLY', 'woocommerce-pagseguro-assinaturas' ),
			'BIMONTHLY' 	=> __('BIMONTHLY', 'woocommerce-pagseguro-assinaturas' ),
			'TRIMONTHLY' 	=> __('TRIMONTHLY', 'woocommerce-pagseguro-assinaturas' ),
			'SEMIANNUALLY' 	=> __('SEMIANNUALLY', 'woocommerce-pagseguro-assinaturas' ),
			'YEARLY' 		=> __('YEARLY', 'woocommerce-pagseguro-assinaturas' )
		);

		if( isset( $array[ $pagseguro_period ] ) ) {
			$pagseguro_period = $array[ $pagseguro_period ];
		} 

		return $pagseguro_period;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @since 2.4
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
 		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_pagseguro_assinatura' => array(
					'value' => get_post_meta( $subscription->get_id(), '_pagseguro_assinatura', true ),
					'label' => 'Código do Cliente - PagSeguro Assinatura ',
				),
			),
		);
 		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can.
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @since  2.4
	 * @param  string $payment_method_id The ID of the payment method to validate
	 * @param  array  $payment_meta associative array of meta data required for automatic payments
	 * @throws Exception
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_pagseguro_assinatura']['value'] ) || empty( $payment_meta['post_meta']['_pagseguro_assinatura']['value'] ) ) {
				throw new Exception( 'A "_pagseguro_assinatura" value is required.' );
			}
		}
	}

	/**
	 * Saves the pre-approval code on the main subscription.
	 *
	 * @since 2.4
	 * @param int $order_id 
	 * @param string $assinatura Pre-approval code from Pagseguro
	 * @return null
	 */
	protected function save_pagseguro_assinatura( $order_id, $assinatura, $tracker = '' ) {
		
		update_post_meta( $order_id, '_pagseguro_assinatura', $assinatura );
		if( '' != $tracker ) {
			update_post_meta( $order_id, '_pagseguro_tracker', $tracker );
		} 

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach( $subscriptions as $subscription ) {
			update_post_meta( $subscription->get_id(), '_pagseguro_assinatura', $assinatura );
			if( '' != $tracker ) {
				update_post_meta( $subscription->get_id(), '_pagseguro_tracker', $tracker );
			} 
		}
		return;
	}

	/**
	 * Saves sender hash for later use.
	 *
	 * @since 2.4
	 * @param int $order_id 
	 * @param string $hash Sender hash generated with Javascript on front end.
	 * @return null
	 */	
	protected function save_sender_hash( $order_id, $hash ) {
		
		update_post_meta( $order_id, '_sender_hash', sanitize_text_field( $hash ) );

		if ( wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = array();
		}

		foreach( $subscriptions as $subscription ) {
			update_post_meta( $subscription->get_id(), '_sender_hash', $hash );
		}
		return;

	}


	/**
	 * Update the _pagseguro_assinatura for a subscription after using Pagseguro Recorrente to complete a payment to make up for.
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->get_id(), '_pagseguro_assinatura', get_post_meta( $renewal_order->get_id(), '_pagseguro_assinatura', true ) );
		update_post_meta( $subscription->get_id(), '_pagseguro_tracker', get_post_meta( $renewal_order->get_id(), '_pagseguro_tracker', true ) );
	}


	/**
	 * Payment Fields Hook.
	 *
	 */
	public function payment_fields() {

		if ( ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) 
			|| wcs_cart_contains_resubscribe() 
			|| wcs_cart_contains_renewal() 
			|| WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment ) {
			
			// $this->subscription_payment_fields();

			$description = $this->get_description();

			if ( 'yes' == $this->sandbox ) {
				$description .= ' ' . sprintf( __( 'TEST MODE ENABLED. Use a test card: %s', 'woocommerce-pagseguro-assinaturas' ), 'VISA: 4111 1111 1111 1111, EXP: 12/2030, CVC: 123' );
			}

			if ( $description ) {
				echo wpautop( wptexturize( trim( $description ) ) );
			}

			
			parent::payment_fields();
			

		}


	}

	/*
	 * Filter Credit card form fields
	 *
	*/
	function filter_cc_fields( $fields, $id ) {
		
		if( $id == $this->id ) {

			$is_update_payment_method = is_wc_endpoint_url('order-pay');

			$fields['card-expiry-field'] = '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YYYY)', 'woocommerce-pagseguro-assinaturas' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YYYY', 'woocommerce-pagseguro-assinaturas' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
			</p>';
			
			$fields['card-name'] = '<p class="form-row form-row-wide">
					<label for="' . esc_attr( $this->id ) . '_card_holder_name">' . esc_html__( 'Card Holder Name', 'woocommerce-pagseguro-assinaturas' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '_card_holder_name" name="' . esc_attr( $this->id ) . '_card_holder_name" class="input-text wc-credit-card-form_card_holder_name" type="text"/>
				</p>';

			if( false === $this->cc_hide_cpf_field || $is_update_payment_method ) { 
				
				$fields['card-cpf'] = '<p class="form-row form-row-wide">
						<label for="' . esc_attr( $this->id ) . '_card_holder_cpf">' . esc_html__( 'CPF', 'woocommerce-pagseguro-assinaturas' ) . '&nbsp;<span class="required">*</span></label>
						<input id="' . esc_attr( $this->id ) . '_card_holder_cpf" name="' . esc_attr( $this->id ) . '_card_holder_cpf" class="input-text wc-credit-card-form_card_holder_cpf" type="tel"/>
					</p>';
			}
			
			if( false === $this->cc_hide_birthdate_field || $is_update_payment_method ) { 
				
				$fields['card-birth-date'] = '<p class="form-row form-row-first">
					<label for="' . esc_attr( $this->id ) . '_card_holder_birth_date">' . esc_html__( 'Card Holder Birth Date', 'woocommerce-pagseguro-assinaturas' ) . '&nbsp;<span class="required">*</span></label>
					<input id="' . esc_attr( $this->id ) . '_card_holder_birth_date" name="' . esc_attr( $this->id ) . '_card_holder_birth_date" class="input-text wc-credit-card-form_card_holder_birth_date" type="tel" placeholder="'. esc_html__( 'DD / MM / YYYY', 'woocommerce-pagseguro-assinaturas' ).'"/>
				</p>';
			
			}

			if( false === $this->cc_hide_phone_field || $is_update_payment_method ) { 

				$fields['card-phone'] = '<p class="form-row ' . ( 'yes' == $this->cc_hide_birthdate_field ? 'form-row-first' : 'form-row-last' ) . '">
						<label for="' . esc_attr( $this->id ) . '_card_holder_phone">' . esc_html__( 'Card Holder Phone', 'woocommerce-pagseguro-assinaturas' ) . '&nbsp;<span class="required">*</span></label>
						<input id="' . esc_attr( $this->id ) . '_card_holder_phone" name="' . esc_attr( $this->id ) . '_card_holder_phone" class="input-text wc-credit-card-form_card_holder_phone" type="tel" placeholder="'. esc_html__( '(xx) xxxx-xxxx', 'woocommerce-pagseguro-assinaturas' ).'" />
					</p>';
			}

			// For updating subscriptions in the future.
			// if ( is_wc_endpoint_url('add-payment-method') ) {
			// 	$fields['sub-notice'] = '<p class="form-row form-row-wide">Clique em adicionar método de pagamento para concluir a alteração de cobrança.</p>';
			// 	$fields['sub'] = '<input id="' . esc_attr( $this->id ) . '_sub" name="' . esc_attr( $this->id ) . '_sub" class="input-text wc-credit-card-form_sub" type="hidden" value="'. absint($_GET['sub']) .'" />';
			// }
		}

		return $fields;
	}	

	/**
	 *
	 * For the recurring payment method to be changeable, the subscription must be active, have future (automatic) payments
	 * and use a payment gateway which allows the subscription to be cancelled.
	 *
	 * @param bool            $subscription_can_be_changed Flag of whether the subscription can be changed.
	 * @param WC_Subscription $subscription The subscription to check.
	 * @return bool Flag indicating whether the subscription payment method can be updated.
	 */
	function can_subscription_be_updated_to_new_payment_method($subscription_can_be_changed, $subscription) {
		
		if( $this->id == $subscription->get_payment_method() ) {
			
			if( $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
				return true;
			}
		}
		
		return $subscription_can_be_changed;
	}

	/**
	 *
	 * Return false because pagseguro does not support multiple subscriptions
	 *
	 * @param bool            $subscription_can_be_changed Flag of whether the subscription can be changed.
	 * @param string $gateway_id The gateway id.
	 * @param WC_Subscription $subscription The subscription to check.
	 * @return bool Flag indicating whether the other subscriptions can be updated.
	 */
	function can_update_all_subscriptions( $can_update, $gateway_id, $subscription ) {

		if( $this->id == $gateway_id ) {
			return false;
		}

		return $can_update;
	}

}

