<?php

// Ignore if access directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PayPal_Brasil_Plus_Gateway.
 *
 * @property string client_live
 * @property string client_sandbox
 * @property string secret_live
 * @property string secret_sandbox
 * @property string format
 * @property string color
 * @property string shortcut_enabled
 * @property string reference_enabled
 * @property string debug
 * @property string invoice_id_prefix
 * @property string form_height
 * @property string title_complement
 */
class PayPal_Brasil_Plus_Gateway extends PayPal_Brasil_Gateway {

	/**
	 * PayPal_Brasil_Plus_Gateway constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Set default settings.
		$this->id                 = 'paypal-brasil-plus-gateway';
		$this->has_fields         = true;
		$this->method_title       = __( 'PayPal Brasil', 'paypal-brasil-para-woocommerce' );
		$this->method_description = __( 'Adicione o checkout transparente do PayPal em sua loja do WooCommerce.', 'paypal-brasil-para-woocommerce' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load settings fields.
		$this->init_form_fields();
		$this->init_settings();

		// Get options in variable.
		$this->enabled          = $this->get_option( 'enabled' );
		$this->title            = __( 'PayPal Brasil - Checkout Transparente', 'paypal-brasil-para-woocommerce' );
		$this->title_complement = $this->get_option( 'title_complement' );
		$this->mode             = $this->get_option( 'mode' );
		$this->client_live      = $this->get_option( 'client_live' );
		$this->client_sandbox   = $this->get_option( 'client_sandbox' );
		$this->secret_live      = $this->get_option( 'secret_live' );
		$this->secret_sandbox   = $this->get_option( 'secret_sandbox' );

		$this->form_height       = $this->get_option( 'form_height' );
		$this->invoice_id_prefix = $this->get_option( 'invoice_id_prefix' );
		$this->debug             = $this->get_option( 'debug' );


		// Instance the API.
		$this->api = new PayPal_Brasil_API( $this->get_client_id(), $this->get_secret(), $this->mode, $this );

		// Handler for IPN.
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook_handler' ) );

		// Now save with the save hook.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		), 10 );

		// Update web experience profile id before actually saving.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'before_process_admin_options'
		), 20 );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	public function before_process_admin_options() {
		// Check first if is enabled
		$enabled = $this->get_field_value( 'enabled', $this->form_fields['enabled'] );
		if ( $enabled !== 'yes' ) {
			return;
		}

		// update credentials
		$this->update_credentials();

		// validate credentials
		$this->validate_credentials();

		// create webhooks
		$this->create_webhooks();
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		if ( ! $this->is_credentials_validated() ) {
			$is_available = false;
		}

		return $is_available;
	}

	public function is_credentials_validated() {
		return get_option( $this->get_option_key() . '_validator' ) === 'yes';
	}

	/**
	 * Init the admin form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'           => array(
				'title'   => __( 'Habilitar/Desabilitar', 'paypal-brasil-para-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar', 'paypal-brasil-para-woocommerce' ),
				'default' => 'no',
			),
			'title_complement'  => array(
				'title' => __( 'Nome de exibição (complemento)', 'paypal-brasil-para-woocommerce' ),
				'type'  => 'text',
			),
			'mode'              => array(
				'title'       => __( 'Modo', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'live'    => __( 'Produção', 'paypal-brasil-para-woocommerce' ),
					'sandbox' => __( 'Sandbox', 'paypal-brasil-para-woocommerce' ),
				),
				'description' => __( 'Utilize esta opção para alternar entre os modos Sandbox e Produção. Sandbox é utilizado para testes e Produção para compras reais.', 'paypal-brasil-para-woocommerce' ),
			),
			'client_live'       => array(
				'title'       => '',
				'type'        => 'text',
				'default'     => '',
				'description' => '',
			),
			'secret_live'       => array(
				'title'       => '',
				'type'        => 'text',
				'default'     => '',
				'description' => '',
			),
			'client_sandbox'    => array(
				'title'       => '',
				'type'        => 'text',
				'default'     => '',
				'description' => '',
			),
			'secret_sandbox'    => array(
				'title'       => '',
				'type'        => 'text',
				'default'     => '',
				'description' => '',
			),
			'debug'             => array(
				'title'       => __( 'Modo depuração', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar', 'paypal-brasil-para-woocommerce' ),
				'desc_tip'    => __( 'Habilite este modo para depurar a aplicação em caso de homologação ou erros.', 'paypal-brasil-para-woocommerce' ),
				'description' => sprintf( __( 'Os logs serão salvos no caminho: %s.', 'paypal-brasil-para-woocommerce' ), $this->get_log_view() ),
			),
			'form_height'       => array(
				'title'       => __( 'Altura do formulário', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => __( 'px', 'paypal-brasil-para-woocommerce' ),
				'description' => __( 'Utilize esta opção para definir uma altura máxima do formulário de cartão de crédito (será considerado um valor em pixels). Será aceito um valor em pixels entre 400 - 550.', 'paypal-brasil-para-woocommerce' ),
			),
			'invoice_id_prefix' => array(
				'title'       => __( 'Prefixo no número do pedido', 'paypal-plus-brasil' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Adicione um prefixo no número do pedido, isto é útil para a sua identificação quando você possui mais de uma loja processando pelo PayPal.', 'paypal-plus-brasil' ),
			),
		);
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		return '<a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'Status do Sistema &gt; Logs', 'paypal-brasil-para-woocommerce' ) . '</a>';
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id
	 *
	 * @param bool $force
	 *
	 * @return null|array
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function process_payment( $order_id, $force = false ) {
		$order      = wc_get_order( $order_id );
		$session    = WC()->session->get( 'wc-ppp-brasil-payment-id' );
		$payment_id = $session['payment_id'];

		// First check if is paying the same cart
		if ( empty( get_query_var( 'order-pay' ) ) ) {
			$current_cart_hash = $order->get_cart_hash();
			$session_cart_hash = WC()->session->get( 'paypal_brasil-cart_hash' );
			if ( $current_cart_hash !== $session_cart_hash ) {
				// Add notice.
				wc_add_notice( __( 'Identificamos uma diferença entre o valor do carrinho e o valor do pedido. Por favor refaça o processo de compra.', 'paypal-brasil-para-woocommerce' ), 'error' );

				// Refresh totals in frontend.
				WC()->session->set( 'refresh_totals', true );

				return null;
			}
		}

		// Check if is a iframe error
		if ( isset( $_POST['wc-ppp-brasil-error'] ) && ! empty( $_POST['wc-ppp-brasil-error'] ) ) {
			switch ( $_POST['wc-ppp-brasil-error'] ) {
				case 'CARD_ATTEMPT_INVALID':
					wc_add_notice( __( 'Número de tentativas excedidas, por favor tente novamente. Se o erro persistir entre em contato.', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
				case 'INTERNAL_SERVICE_ERROR':
				case 'SOCKET_HANG_UP':
				case 'socket hang up':
				case 'connect ECONNREFUSED':
				case 'connect ETIMEDOUT':
				case 'UNKNOWN_INTERNAL_ERROR':
				case 'fiWalletLifecycle_unknown_error':
				case 'Failed to decrypt term info':
					wc_add_notice( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#23)', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
				case 'RISK_N_DECLINE':
				case 'NO_VALID_FUNDING_SOURCE_OR_RISK_REFUSED':
				case 'TRY_ANOTHER_CARD':
				case 'NO_VALID_FUNDING_INSTRUMENT':
					wc_add_notice( __( 'Não foi possível processar o seu pagamento, tente novamente ou entre em contato contato com o PayPal (0800-047-4482).', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
				case 'INVALID_OR_EXPIRED_TOKEN':
					wc_add_notice( __( 'A sua sessão expirou, por favor tente efetuar o pagamento novamente. Se o erro persistir, entre em contato.', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
				default:
					wc_add_notice( __( 'Por favor revise as informações inseridas do cartão de crédito.', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
			}
			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set( 'refresh_totals', true );
			do_action( 'wc_ppp_brasil_process_payment_error', 'IFRAME_ERROR', $order_id, $_POST['wc-ppp-brasil-error'] );

			return null;
		}
		// Prevent submit any dummy data.
		if ( WC()->session->get( 'wc-ppp-brasil-dummy-data' ) === true ) {
			wc_add_notice( __( 'You are not allowed to do that.', 'paypal-brasil-para-woocommerce' ), 'error' );
			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set( 'refresh_totals', true );

			return null;
		}
		// Check the payment id
		/**
		 * This error is caused by multiple requests that
		 */
		if ( ! $payment_id ) {
			wc_add_notice( __( 'Houve um erro interno ao processar o pagamento. Por favor, tente novamente. Se o erro persistir, entre em contato.', 'paypal-brasil-para-woocommerce' ), 'error' );
			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set( 'refresh_totals', true );
			do_action( 'wc_ppp_brasil_process_payment_error', 'SESSION_ERROR', $order_id, null );

			return null;
		}
		try {
			$iframe_data    = isset( $_POST['wc-ppp-brasil-data'] ) ? json_decode( wp_unslash( $_POST['wc-ppp-brasil-data'] ), true ) : null;
			$response_data  = isset( $_POST['wc-ppp-brasil-response'] ) ? json_decode( wp_unslash( $_POST['wc-ppp-brasil-response'] ), true ) : null;
			$payer_id       = $response_data['payer_id'];
			$remember_cards = $response_data['remembered_cards_token'];
			// Check if there is no $response data, so iframe wasn't processed
			if ( empty( $response_data ) ) {
				$this->log( "The iframe could not be intercepted to process payment.\n" );
				wc_add_notice( __( 'Não foi possível finalizar o pagamento através do PayPal, por favor tente novamente. Se o erro persistir, entre em contato.', 'paypal-brasil-para-woocommerce' ), 'error' );
				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set( 'refresh_totals', true );
				do_action( 'wc_ppp_brasil_process_payment_error', 'PAYER_ID', $order_id, null );

				return null;
			}
			// Check if the payment id
			if ( empty( $payer_id ) ) {
				wc_add_notice( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#67)', 'paypal-brasil-para-woocommerce' ), 'error' );
				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set( 'refresh_totals', true );
				do_action( 'wc_ppp_brasil_process_payment_error', 'PAYER_ID', $order_id, null );

				return null;
			}
			// Check if the payment id equal to stored
			if ( $payment_id !== $iframe_data['payment_id'] ) {
				wc_add_notice( __( 'Houve um erro com a sessão do usuário. Por favor, tente novamente. Se o erro persistir, entre em contato.', 'paypal-brasil-para-woocommerce' ), 'error' );
				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set( 'refresh_totals', true );
				do_action( 'wc_ppp_brasil_process_payment_error', 'PAYMENT_ID', $order_id, array(
					'stored_payment_id' => $payment_id,
					'iframe_payment_id' => $iframe_data['payment_id']
				) );

				return null;
			}
			// execute the order here.
			$execution = $this->execute_payment( $order, $payment_id, $payer_id );
			$sale      = $execution["transactions"][0]["related_resources"][0]["sale"];
			// @todo: change to correct meta key
			update_post_meta( $order_id, 'wc_ppp_brasil_sale_id', $sale['id'] );
			update_post_meta( $order_id, 'wc_ppp_brasil_sale', $sale );
			$installments = 1;
			if ( $response_data && $response_data['term'] && $response_data['term']['term'] ) {
				$installments = $response_data['term']['term'];
			}
			update_post_meta( $order_id, 'wc_ppp_brasil_installments', $installments );
			update_post_meta( $order_id, 'wc_ppp_brasil_sandbox', $this->mode );
			$result_success = false;
			switch ( $sale['state'] ) {
				case 'completed';
					$order->add_order_note( sprintf( __( 'Pagamento processado pelo PayPal. ID da transação: %s', 'paypal-brasil-para-woocommerce' ), $sale['id'] ) );
					$order->payment_complete();
					$result_success = true;
					break;
				case 'pending':
					wc_reduce_stock_levels( $order_id );
					$order->update_status( 'on-hold', __( 'O pagamento está em revisão pelo PayPal.', 'paypal-brasil-para-woocommerce' ) );
					$result_success = true;
					break;
			}
			if ( $result_success ) {
				// Remember user cards
				if ( is_user_logged_in() ) {
					update_user_meta( get_current_user_id(), 'wc_ppp_brasil_remembered_cards', $remember_cards );
				}
				do_action( 'wc_ppp_brasil_process_payment_success', $order_id );

				// Return the success URL.s
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		} catch ( PayPal_Brasil_API_Exception $ex ) {
			$data = $ex->getData();
			switch ( $data['name'] ) {
				// Repeat the execution
				case 'INTERNAL_SERVICE_ERROR':
					if ( $force ) {
						wc_add_notice( sprintf( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#34)', 'paypal-brasil-para-woocommerce' ) ), 'error' );
					} else {
						$this->process_payment( $order_id, true );
					}
					break;
				case 'VALIDATION_ERROR':
					wc_add_notice( sprintf( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#45)', 'paypal-brasil-para-woocommerce' ) ), 'error' );
					break;
				case 'PAYMENT_ALREADY_DONE':
					wc_add_notice( __( 'Já existe um pagamento para este pedido.', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
				default:
					wc_add_notice( __( 'O seu pagamento não foi aprovado, por favor tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
					break;
			}
			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set( 'refresh_totals', true );
			do_action( 'wc_ppp_brasil_process_payment_error', 'API_EXCEPTION', $order_id, $data['name'] );

			return null;
		}

		return null;
	}

	/**
	 * Process the refund for an order.
	 *
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return WP_Error|bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$sale_id = get_post_meta( $order_id, 'wc_ppp_brasil_sale_id', true );
		// Check if the amount is bigger than zero
		if ( $amount <= 0 ) {
			$min_price = number_format( 0, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );

			return new WP_Error( 'error', sprintf( __( 'O reembolso não pode ser menor que %s.', 'paypal-brasil-para-woocommerce' ), html_entity_decode( get_woocommerce_currency_symbol() ) . $min_price ) );
		}
		// Check if we got the sale ID
		if ( $sale_id ) {
			try {
				$refund_sale = $this->api->refund_payment( $sale_id, paypal_brasil_money_format( $amount ), get_woocommerce_currency() );
				// Check the result success.
				if ( $refund_sale['state'] === 'completed' ) {
					return true;
				} else {
					return new WP_Error( 'error', $refund_sale->getReason() );
				}
			} catch ( PayPal_Brasil_API_Exception $ex ) { // Catch any PayPal error.
				$data = $ex->getData();

				return new WP_Error( 'error', $data['message'] );
			} catch ( Exception $ex ) {
				return new WP_Error( 'error', __( 'Houve um erro ao tentar realizar o reembolso.', 'paypal-brasil-para-woocommerce' ) );
			}
		} else { // If we don't have the PayPal sale ID.
			return new WP_Error( 'error', sprintf( __( 'Parece que você não tem um pedido para realizar o reembolso.', 'paypal-brasil-para-woocommerce' ) ) );
		}
	}

	/**
	 * Execute a payment.
	 *
	 * @param $order WC_Order
	 * @param $payment_id
	 * @param $payer_id
	 *
	 * @return array|mixed|object
	 * @throws PayPal_Brasil_API_Exception
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function execute_payment( $order, $payment_id, $payer_id ) {
		$patch_data = array(
			array(
				'op'    => 'add',
				'path'  => '/transactions/0/invoice_number',
				'value' => $this->invoice_id_prefix . $order->get_id(),
			),
			array(
				'op'    => 'add',
				'path'  => '/transactions/0/description',
				'value' => sprintf( __( 'Pedido #%s realizado na loja %s', 'paypal-brasil-para-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
			),
			array(
				'op'    => 'add',
				'path'  => '/transactions/0/custom',
				'value' => sprintf( __( 'Pedido #%s realizado na loja %s', 'paypal-brasil-para-woocommerce' ), $order->get_id(), get_bloginfo( 'name' ) ),
			),
		);
		$this->api->update_payment( $payment_id, $patch_data, array(), 'plus' );
		$execution_response = $this->api->execute_payment( $payment_id, $payer_id, array(), 'plus' );

		return $execution_response;
	}

	/**
	 * Render the payment fields in checkout.
	 */
	public function payment_fields() {
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/checkout/plus-html-fields.php';
	}

	/**
	 * Render HTML in admin options.
	 */
	public function admin_options() {
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/admin-options/admin-options-plus/admin-options-plus.php';
	}

	/**
	 * Get the posted data in the checkout.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_posted_data() {
		$execution_time = microtime( true );
		$order_id       = get_query_var( 'order-pay' );
		$order          = $order_id ? new WC_Order( $order_id ) : null;
		$data           = array();
		$defaults       = array(
			'first_name'       => '',
			'last_name'        => '',
			'person_type'      => '',
			'cpf'              => '',
			'cnpj'             => '',
			'phone'            => '',
			'email'            => '',
			'postcode'         => '',
			'address'          => '',
			'number'           => '',
			'address_2'        => '',
			'neighborhood'     => '',
			'city'             => '',
			'state'            => '',
			'country'          => '',
			'approval_url'     => '',
			'payment_id'       => '',
			'dummy'            => false,
			'invalid'          => array(),
			'remembered_cards' => '',
		);
		if ( $order ) {
			$billing_cellphone    = get_post_meta( $order->get_id(), '_billing_cellphone', true );
			$data['postcode']     = $order->get_shipping_postcode();
			$data['address']      = $order->get_shipping_address_1();
			$data['address_2']    = $order->get_shipping_address_2();
			$data['city']         = $order->get_shipping_city();
			$data['state']        = $order->get_shipping_state();
			$data['country']      = $order->get_shipping_country();
			$data['neighborhood'] = get_post_meta( $order->get_id(), '_billing_neighborhood', true );
			$data['number']       = get_post_meta( $order->get_id(), '_billing_number', true );
			$data['first_name']   = $order->get_billing_first_name();
			$data['last_name']    = $order->get_billing_last_name();
			$data['person_type']  = get_post_meta( $order->get_id(), '_billing_persontype', true );
			$data['cpf']          = get_post_meta( $order->get_id(), '_billing_cpf', true );
			$data['cnpj']         = get_post_meta( $order->get_id(), '_billing_cnpj', true );
			$data['phone']        = $billing_cellphone ? $billing_cellphone : $order->get_billing_phone();
			$data['email']        = $order->get_billing_email();
		} else if ( $_POST ) {
			$data['postcode']  = isset( $_POST['s_postcode'] ) ? preg_replace( '/[^0-9]/', '', $_POST['s_postcode'] ) : '';
			$data['address']   = isset( $_POST['s_address'] ) ? sanitize_text_field( $_POST['s_address'] ) : '';
			$data['address_2'] = isset( $_POST['s_address_2'] ) ? sanitize_text_field( $_POST['s_address_2'] ) : '';
			$data['city']      = isset( $_POST['s_city'] ) ? sanitize_text_field( $_POST['s_city'] ) : '';
			$data['state']     = isset( $_POST['s_state'] ) ? sanitize_text_field( $_POST['s_state'] ) : '';
			$data['country']   = isset( $_POST['s_country'] ) ? sanitize_text_field( $_POST['s_country'] ) : '';
			// Now get other post data that other fields can send.
			$post_data = array();
			if ( isset( $_POST['post_data'] ) ) {
				parse_str( $_POST['post_data'], $post_data );
			}
			$billing_cellphone    = isset( $post_data['billing_cellphone'] ) ? sanitize_text_field( $post_data['billing_cellphone'] ) : '';
			$data['neighborhood'] = isset( $post_data['billing_neighborhood'] ) ? sanitize_text_field( $post_data['billing_neighborhood'] ) : '';
			$data['number']       = isset( $post_data['billing_number'] ) ? sanitize_text_field( $post_data['billing_number'] ) : '';
			$data['first_name']   = isset( $post_data['billing_first_name'] ) ? sanitize_text_field( $post_data['billing_first_name'] ) : '';
			$data['last_name']    = isset( $post_data['billing_last_name'] ) ? sanitize_text_field( $post_data['billing_last_name'] ) : '';
			$data['person_type']  = isset( $post_data['billing_persontype'] ) ? sanitize_text_field( $post_data['billing_persontype'] ) : '';
			$data['cpf']          = isset( $post_data['billing_cpf'] ) ? sanitize_text_field( $post_data['billing_cpf'] ) : '';
			$data['cnpj']         = isset( $post_data['billing_cnpj'] ) ? sanitize_text_field( $post_data['billing_cnpj'] ) : '';
			$data['phone']        = $billing_cellphone ? $billing_cellphone : ( isset( $post_data['billing_phone'] ) ? sanitize_text_field( $post_data['billing_phone'] ) : '' );
			$data['email']        = isset( $post_data['billing_email'] ) ? sanitize_text_field( $post_data['billing_email'] ) : '';
		}
		if ( paypal_brasil_needs_cpf() ) {
			// Get wcbcf settings
			$wcbcf_settings = get_option( 'wcbcf_settings' );
			// Set the person type default if we don't have any person type defined
			if ( $wcbcf_settings && ! $data['person_type'] && ( $wcbcf_settings['person_type'] == '2' || $wcbcf_settings['person_type'] == '3' ) ) {
				// The value 2 from person_type in settings is CPF (1) and 3 is CNPJ (2), and 1 is both, that won't reach here.
				$data['person_type']         = $wcbcf_settings['person_type'] == '2' ? '1' : '2';
				$data['person_type_default'] = true;
			}
		}
		// Now set the invalid.
		$data    = wp_parse_args( $data, $defaults );
		$data    = apply_filters( 'wc_ppp_brasil_user_data', $data );
		$invalid = $this->validate_data( $data );
		// if its invalid, return demo data.
		// Also check if we are on our payment method. If not, render demo data.
		if ( ! $order && $post_data['payment_method'] !== $this->id ) {
			$invalid['wrong-payment-method'] = __( 'Não está selecionado o método de pagamento do PayPal Plus.', 'paypal-brasil-para-woocommerce' );
		}

		if ( $invalid ) {
			$data = array(
				'first_name'   => 'PayPal',
				'last_name'    => 'Brasil',
				'person_type'  => '2',
				'cpf'          => '',
				'cnpj'         => '10.878.448/0001-66',
				'phone'        => '(21) 99999-99999',
				'email'        => 'contato@paypal.com.br',
				'postcode'     => '01310-100',
				'address'      => 'Av. Paulista',
				'number'       => '1048',
				'address_2'    => '',
				'neighborhood' => 'Bela Vista',
				'city'         => 'São Paulo',
				'state'        => 'SP',
				'country'      => 'BR',
				'dummy'        => true,
				'invalid'      => $invalid,
			);
		}
		// Add session if is dummy data to check it later.
		WC()->session->set( 'wc-ppp-brasil-dummy-data', $data['dummy'] );
		// Return the data if is dummy. We don't need to process this.
		if ( $invalid ) {
			return $data;
		}
		// Create the payment.
		$payment = $order ? $this->create_payment_for_order( $data, $order, $data['dummy'] ) : $this->create_payment_for_cart( $data, $data['dummy'] );

		// Get old session.
		$old_session = WC()->session->get( 'wc-ppp-brasil-payment-id' );
		// Check if old session exists and it's an array.
		if ( $old_session && is_array( $old_session ) ) {
			// If this execution time is later than old session time, we can ignore this request.
			if ( $execution_time < $old_session['execution_time'] ) {
				return $data;
			}
		}
		// Add session with payment ID to check it later.
		WC()->session->set( 'wc-ppp-brasil-payment-id', array(
			'payment_id'     => $payment['id'],
			'execution_time' => $execution_time,
		) );
		// Add the saved remember card, approval link and the payment URL.
		$data['remembered_cards'] = is_user_logged_in() ? get_user_meta( get_current_user_id(), 'wc_ppp_brasil_remembered_cards', true ) : '';
		$data['approval_url']     = $payment['links'][1]['href'];
		$data['payment_id']       = $payment['id'];

		return $data;
	}

	/**
	 * @param $data
	 * @param $order WC_Order
	 * @param bool $dummy
	 *
	 * @return mixed
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function create_payment_for_order( $data, $order, $dummy = false ) {
		// Get the order if was given order ID.
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		// Don' log if is dummy data.
		if ( $dummy ) {
			$this->debug = false;
		}

		$payment_data = array(
			'intent'        => 'sale',
			'payer'         => array(
				'payment_method' => 'paypal',
			),
			'transactions'  => array(
				array(
					'payment_options' => array(
						'allowed_payment_method' => 'IMMEDIATE_PAY',
					),
					'amount'          => array(
						'currency' => get_woocommerce_currency(),
					),
				),
			),
			'redirect_urls' => array(
				'return_url' => home_url(),
				'cancel_url' => home_url(),
			),
		);

		// Set details
		$payment_data['transactions'][0]['amount']['details'] = array(
			'shipping' => paypal_brasil_money_format( $order->get_shipping_total() ),
			'subtotal' => paypal_brasil_math_sub( $order->get_total(), $order->get_shipping_total() ),
		);

		// Set total Total
		$payment_data['transactions'][0]['amount']['total'] = paypal_brasil_money_format( $order->get_total() );

		// Check if is only digital items.
		$only_digital_items = paypal_brasil_is_order_only_digital( $order );

		// Set the application context
		$payment_data['application_context'] = array(
			'brand_name'          => get_bloginfo( 'name' ),
			'shipping_preference' => $only_digital_items ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
		);

		// Check if is order pay
		$exception_data = array();

		// Create the address.
		if ( ! $dummy ) {
			// Set shipping only when isn't digital
			if ( ! $only_digital_items ) {
				// Prepare empty address_line_1
				$address_line_1 = array();
				// Add the address
				if ( $data['address'] ) {
					$address_line_1[] = $data['address'];
				}
				// Add the number
				if ( $data['number'] ) {
					$address_line_1[] = $data['number'];
				}
				// Prepare empty line 2.
				$address_line_2 = array();
				// Add neighborhood to line 2
				if ( $data['neighborhood'] ) {
					$address_line_2[] = $data['neighborhood'];
				}
				// Add shipping address line 2
				if ( $data['address_2'] ) {
					$address_line_2[] = $data['address_2'];
				}
				$shipping_address = array(
					'recipient_name' => $data['first_name'] . ' ' . $data['last_name'],
					'country_code'   => $data['country'],
					'postal_code'    => $data['postcode'],
					'line1'          => mb_substr( implode( ', ', $address_line_1 ), 0, 100 ),
					'city'           => $data['city'],
					'state'          => $data['state'],
					'phone'          => $data['phone'],
				);
				// If is anything on address line 2, add to shipping address.
				if ( $address_line_2 ) {
					$shipping_address['line2'] = mb_substr( implode( ', ', $address_line_2 ), 0, 100 );
				}
				$payment_data['transactions'][0]['item_list'] = array(
					'shipping_address' => $shipping_address,
				);
			}
		}

		try {
			// Create the payment.
			$result = $this->api->create_payment( $payment_data, array(), 'plus' );

			return $result;
		} catch ( PayPal_Brasil_API_Exception $ex ) { // Catch any PayPal error.
			$error_data = $ex->getData();
			if ( $error_data['name'] === 'VALIDATION_ERROR' ) {
				$exception_data = $error_data['details'];
			}
		}

		$exception       = new Exception( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#56)', 'paypal-brasil-para-woocommerce' ) );
		$exception->data = $exception_data;

		throw $exception;
	}

	/**
	 * Create the PayPal payment.
	 *
	 * @param $data
	 * @param bool $dummy
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function create_payment_for_cart( $data, $dummy = false ) {
		// Don' log if is dummy data.
		if ( $dummy ) {
			$this->debug = false;
		}

		$payment_data = array(
			'intent'        => 'sale',
			'payer'         => array(
				'payment_method' => 'paypal',
			),
			'transactions'  => array(
				array(
					'payment_options' => array(
						'allowed_payment_method' => 'IMMEDIATE_PAY',
					),
					'amount'          => array(
						'currency' => get_woocommerce_currency(),
					),
				),
			),
			'redirect_urls' => array(
				'return_url' => home_url(),
				'cancel_url' => home_url(),
			),
		);

		$cart_totals = WC()->cart->get_totals();

		// Set details
		$payment_data['transactions'][0]['amount']['details'] = array(
			'shipping' => paypal_brasil_money_format( $cart_totals['shipping_total'] ),
			'subtotal' => paypal_brasil_math_sub( $cart_totals['total'], $cart_totals['shipping_total'] ),
		);

		// Set total Total
		$payment_data['transactions'][0]['amount']['total'] = paypal_brasil_money_format( $cart_totals['total'] );

		// Check if is only digital items.
		$only_digital_items = paypal_brasil_is_cart_only_digital();

		// Set the application context
		$payment_data['application_context'] = array(
			'brand_name'          => get_bloginfo( 'name' ),
			'shipping_preference' => $only_digital_items ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
		);

		// Check if is order pay
		$exception_data = array();

		// Create the address.
		if ( ! $dummy ) {
			// Set shipping only when isn't digital
			if ( ! $only_digital_items ) {
				// Prepare empty address_line_1
				$address_line_1 = array();
				// Add the address
				if ( $data['address'] ) {
					$address_line_1[] = $data['address'];
				}
				// Add the number
				if ( $data['number'] ) {
					$address_line_1[] = $data['number'];
				}
				// Prepare empty line 2.
				$address_line_2 = array();
				// Add neighborhood to line 2
				if ( $data['neighborhood'] ) {
					$address_line_2[] = $data['neighborhood'];
				}
				// Add shipping address line 2
				if ( $data['address_2'] ) {
					$address_line_2[] = $data['address_2'];
				}
				$shipping_address = array(
					'recipient_name' => $data['first_name'] . ' ' . $data['last_name'],
					'country_code'   => $data['country'],
					'postal_code'    => $data['postcode'],
					'line1'          => mb_substr( implode( ', ', $address_line_1 ), 0, 100 ),
					'city'           => $data['city'],
					'state'          => $data['state'],
					'phone'          => $data['phone'],
				);
				// If is anything on address line 2, add to shipping address.
				if ( $address_line_2 ) {
					$shipping_address['line2'] = mb_substr( implode( ', ', $address_line_2 ), 0, 100 );
				}
				$payment_data['transactions'][0]['item_list'] = array(
					'shipping_address' => $shipping_address,
				);
			}
		}

		try {
			// Get cart hash
			$cart_hash = WC()->cart->get_cart_hash();

			// Store cart hash in session
			WC()->session->set( 'paypal_brasil-cart_hash', $cart_hash );

			// Create the payment.
			$result = $this->api->create_payment( $payment_data, array(), 'plus' );

			return $result;
		} catch ( PayPal_Brasil_API_Exception $ex ) { // Catch any PayPal error.
			$error_data = $ex->getData();
			if ( $error_data['name'] === 'VALIDATION_ERROR' ) {
				$exception_data = $error_data['details'];
			}
		}

		$exception       = new Exception( __( 'Ocorreu um erro inesperado, por favor tente novamente. Se o erro persistir entre em contato. (#56)', 'paypal-brasil-para-woocommerce' ) );
		$exception->data = $exception_data;

		throw $exception;
	}

	/**
	 * Validate data if contain any invalid field.
	 *
	 * @param $data
	 *
	 * @return array
	 */
	private function validate_data( $data ) {
		$states = WC()->countries->get_states($data['country']);
		$errors = array();

		// Check first name.
		if ( empty( $data['first_name'] ) ) {
			$errors['first_name'] = __( 'Nome', 'paypal-brasil-para-woocommerce' );
		}

		// Check last name.
		if ( empty( $data['last_name'] ) ) {
			$errors['last_name'] = __( 'Sobrenome', 'paypal-brasil-para-woocommerce' );
		}

		// Check phone.
		if ( empty( $data['phone'] ) ) {
			$errors['phone'] = __( 'Telefone', 'paypal-brasil-para-woocommerce' );
		}

		// Check address.
		if ( empty( $data['address'] ) ) {
			$errors['address'] = __( 'Endereço', 'paypal-brasil-para-woocommerce' );
		}

		// Check city.
		if ( empty( $data['city'] ) ) {
			$errors['city'] = __( 'Cidade', 'paypal-brasil-para-woocommerce' );
		}

		// Check country.
		if ( empty( $data['country'] ) || $states === false ) {
			$errors['country'] = __( 'País', 'paypal-brasil-para-woocommerce' );
		}

		// Check state.
		if ( empty( $data['state'] ) && $states ) {
			$errors['state'] = __( 'Estado', 'paypal-brasil-para-woocommerce' );
		}

		// Check postcode.
		if ( empty( $data['postcode'] ) ) {
			$errors['postcode'] = __( 'CEP', 'paypal-brasil-para-woocommerce' );
		}

		// Check email.
		if ( ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Email', 'paypal-brasil-para-woocommerce' );
		}

		// Check CPF/CNPJ.
		// Only if require CPF/CNPJ.
		if ( $data['country'] === 'BR' && paypal_brasil_needs_cpf() ) {
			// Check address number (only with CPF/CPNJ)
			if ( empty( $data['number'] ) ) {
				$errors['number'] = __( 'Número', 'paypal-brasil-para-woocommerce' );
			}
			// Check person type.
			if ( $data['person_type'] !== '1' && $data['person_type'] !== '2' ) {
				$errors['person_type'] = __( 'Tipo de pessoa', 'paypal-brasil-para-woocommerce' );
			}
			// Check the CPF
			if ( $data['person_type'] == '1' && ! $this->is_cpf( $data['cpf'] ) ) {
				$errors['cpf'] = __( 'CPF', 'paypal-brasil-para-woocommerce' );
			}
			// Check the CNPJ
			if ( $data['person_type'] == '2' && ! $this->is_cnpj( $data['cnpj'] ) ) {
				$errors['cnpj'] = __( 'CNPJ', 'paypal-brasil-para-woocommerce' );
			}
		}

		return $errors;
	}

	/**
	 * Enqueue scripts in checkout.
	 */
	public function checkout_scripts() {
		if ( ! $this->is_available() ) {
			return;
		}

		// Just load this script in checkout and if isn't in order-receive.
		if ( is_checkout() && ! get_query_var( 'order-received' ) ) {

			// Remove old plugin scripts
			wp_deregister_script( 'pretty-web-console' );
			wp_deregister_script( 'ppp-script' );
			wp_deregister_script( 'wc-ppp-brasil-script' );
			wp_deregister_style( 'wc-ppp-brasil-style' );

			// Add pretty web console if is debugging
			if ( 'yes' === $this->debug ) {
				wp_enqueue_script( 'pretty-web-console', plugins_url( 'assets/js/libs/pretty-web-console.lib.js', PAYPAL_PAYMENTS_MAIN_FILE ), array(), '0.10.1', true );
			}

			// Enqueue necessary scripts
			wp_enqueue_script( 'ppp-script', '//www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min.js', array(), PAYPAL_PAYMENTS_VERSION, true );
			wp_localize_script( 'ppp-script', 'wc_ppp_brasil_data', array(
				'id'                => $this->id,
				'order_pay'         => ! ! get_query_var( 'order-pay' ),
				'mode'              => $this->mode === 'sandbox' ? 'sandbox' : 'live',
				'form_height'       => apply_filters( 'paypal_brasil_plus_height', $this->get_form_height() ),
				'show_payer_tax_id' => paypal_brasil_needs_cpf(),
				'language'          => apply_filters( 'paypal_brasil_plus_language', get_woocommerce_currency() === 'BRL' ? 'pt_BR' : 'en_US' ),
				'country'           => apply_filters( 'paypal_brasil_plus_country', $this->get_woocommerce_country() ),
				'messages'          => array(
					'check_entry' => __( 'Verifique os dados informados e tente novamente.', 'paypal-brasil-para-woocommerce' ),
				),
				'debug_mode'        => 'yes' === $this->debug,
			) );
			wp_enqueue_script( $this->id . '_script', plugins_url( 'assets/dist/js/frontend-plus.js', PAYPAL_PAYMENTS_MAIN_FILE ), array( 'jquery' ), PAYPAL_PAYMENTS_VERSION, true );
			wp_enqueue_style( $this->id . '_style', plugins_url( 'assets/dist/css/frontend-plus.css', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, 'all' );
		}
	}

	/**
	 * Get the WooCommerce country.
	 *
	 * @return string
	 */
	private function get_woocommerce_country() {
		return get_woocommerce_currency() === 'BRL' ? 'BR' : 'US';
	}

	/**
	 * Get form height.
	 */
	private function get_form_height() {
		$height    = trim( $this->form_height );
		$min_value = 400;
		$max_value = 700;
		$test      = preg_match( '/[0-9]+/', $height, $matches );
		if ( $test && $matches[0] === $height && $height >= $min_value && $height <= $max_value ) {
			return $height;
		}

		return 500;
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_scripts() {
		$screen         = get_current_screen();
		$screen_id      = $screen ? $screen->id : '';
		$wc_screen_id   = sanitize_title( __( 'WooCommerce', 'paypal-brasil-para-woocommerce' ) );
		$wc_settings_id = $wc_screen_id . '_page_wc-settings';
		if ( $wc_settings_id === $screen_id && isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
			wp_enqueue_style( 'wc-ppp-brasil-admin-style', plugins_url( 'assets/dist/css/admin-options-plus.css', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, 'all' );

			// Add shared file if exists.
			if ( file_exists( dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/assets/dist/js/shared.js' ) ) {
				wp_enqueue_script( 'paypal_brasil_admin_options_shared', plugins_url( 'assets/dist/js/shared.js', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, true );
			}

			wp_enqueue_script( $this->id . '_script', plugins_url( 'assets/dist/js/admin-options-plus.js', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, true );
			wp_localize_script( $this->id . '_script', 'paypal_brasil_admin_options_plus', array(
				'template'          => $this->get_admin_options_template(),
				'enabled'           => $this->enabled,
				'form_height'       => $this->shortcut_enabled,
				'mode'              => $this->mode,
				'client'            => array(
					'live'    => $this->client_live,
					'sandbox' => $this->client_sandbox,
				),
				'secret'            => array(
					'live'    => $this->secret_live,
					'sandbox' => $this->secret_sandbox,
				),
				'title'             => $this->title,
				'title_complement'  => $this->title_complement,
				'invoice_id_prefix' => $this->invoice_id_prefix,
				'debug'             => $this->debug,
			) );

		}
	}

	/**
	 * Get the admin options template to render by Vue.
	 */
	private function get_admin_options_template() {
		ob_start();
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/admin-options/admin-options-plus/admin-options-plus-template.php';

		return ob_get_clean();
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		// A description only for admin section.
		if ( is_admin() ) {
			global $pagenow;

			return $pagenow === 'post.php' ? __( 'PayPal - Checkout Transparente', 'paypal-brasil-para-woocommerce' ) : __( 'Checkout Transparente', 'paypal-brasil-para-woocommerce' );
		}

		$title = get_woocommerce_currency() === "BRL" ? __( 'Cartão de Crédito', 'paypal-brasil-para-woocommerce' ) : __( 'Credit Card', 'paypal-brasil-para-woocommerce' );
		if ( ! empty( $this->title_complement ) ) {
			$title .= ' ' . $this->title_complement;
		}

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	private function get_fields_values() {
		return array(
			'enabled'           => $this->enabled,
			'form_height'       => $this->form_height ? $this->form_height : 500,
			'mode'              => $this->mode,
			'client'            => array(
				'live'    => $this->client_live,
				'sandbox' => $this->client_sandbox,
			),
			'secret'            => array(
				'live'    => $this->secret_live,
				'sandbox' => $this->secret_sandbox,
			),
			'title'             => $this->title,
			'title_complement'  => $this->title_complement,
			'invoice_id_prefix' => $this->invoice_id_prefix,
			'debug'             => $this->debug,
		);
	}

}
