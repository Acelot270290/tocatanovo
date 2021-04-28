<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayPal_Brasil_API_Checkout_Handler extends PayPal_Brasil_API_Handler {

	public function __construct() {
		add_filter( 'paypal_brasil_handlers', array( $this, 'add_handlers' ) );
	}

	public function add_handlers( $handlers ) {
		$handlers['checkout'] = array(
			'callback' => array( $this, 'handle' ),
			'method'   => 'POST',
		);

		return $handlers;
	}

	/**
	 * Add validators and input fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array(
			array(
				'name'     => __( 'nonce', 'paypal-brasil-para-woocommerce' ),
				'key'      => 'nonce',
				'sanitize' => 'sanitize_text_field',
//				'validation' => array( $this, 'required_nonce' ),
			),
			array(
				'name'       => __( 'nome', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'first_name',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'sobrenome', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'last_name',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'cidade', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'city',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'país', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'country',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_country' ),
			),
			array(
				'name'       => __( 'cep', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'postcode',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_postcode' ),
			),
			array(
				'name'       => __( 'estado', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'state',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_state' ),
			),
			array(
				'name'       => __( 'endereço', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'address_line_1',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'número', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'number',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'     => __( 'complemento', 'paypal-brasil-para-woocommerce' ),
				'key'      => 'address_line_2',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name'     => __( 'bairro', 'paypal-brasil-para-woocommerce' ),
				'key'      => 'neighborhood',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name'       => __( 'telefone', 'paypal-brasil-para-woocommerce' ),
				'key'        => 'phone',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle() {
		try {

			$validation = $this->validate_input_data();

			if ( ! $validation['success'] ) {
				$this->send_error_response(
					__( 'Alguns campos estão faltando para iniciar o pagamento.', 'paypal-brasil' ),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			$posted_data = $validation['data'];

			// Get the wanted gateway.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-spb-gateway' );

			// Force to calculate cart.
			WC()->cart->calculate_totals();

			// Store cart.
			$cart = WC()->cart;

			// Check if there is anything on cart.
			if ( ! $cart->get_totals()['total'] ) {
				$this->send_error_response( __( 'Você não pode fazer o pagamento de um pedido vazio.', 'paypal-brasil-para-woocommerce' ) );
			}

			$cart_totals        = WC()->cart->get_totals();
			$only_digital_items = paypal_brasil_is_cart_only_digital();

			$data = array(
				'intent'        => 'sale',
				'payer'         => array(
					'payment_method' => 'paypal',
				),
				'transactions'  => array(
					array(
						'payment_options' => array(
							'allowed_payment_method' => 'IMMEDIATE_PAY',
						),
						'item_list'       => array(
							'items' => array(
								array(
									'name'     => sprintf( __( 'Pedido Loja %s', 'paypal-brasil-para-woocommerce' ), get_bloginfo( 'name' ) ),
									'currency' => get_woocommerce_currency(),
									'quantity' => 1,
									'price'    => paypal_brasil_math_sub( $cart_totals['total'], $cart_totals['shipping_total'] ),
									'sku'      => 'order-items',
								)
							),
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
			$data['transactions'][0]['amount']['details'] = array(
				'shipping' => paypal_brasil_money_format( $cart_totals['shipping_total'] ),
				'subtotal' => paypal_brasil_math_sub( $cart_totals['total'], $cart_totals['shipping_total'] ),
			);

			// Set total Total
			$data['transactions'][0]['amount']['total'] = paypal_brasil_money_format( $cart_totals['total'] );

			// Prepare address
			$address_line_1 = array();
			$address_line_2 = array();

			if ( $posted_data['address_line_1'] ) {
				$address_line_1[] = $posted_data['address_line_1'];
			}

			if ( $posted_data['number'] ) {
				$address_line_1[] = $posted_data['number'];
			}

			if ( $posted_data['neighborhood'] ) {
				$addres_line_2[] = $posted_data['neighborhood'];
			}

			if ( $posted_data['address_line_2'] ) {
				$addres_line_2[] = $posted_data['address_line_2'];
			}

			// Prepare shipping address.
			$shipping_address = array(
				'recipient_name' => $posted_data['first_name'] . ' ' . $posted_data['last_name'],
				'country_code'   => $posted_data['country'],
				'postal_code'    => $posted_data['postcode'],
				'line1'          => mb_substr( implode( ', ', $address_line_1 ), 0, 100 ),
				'city'           => $posted_data['city'],
				'phone'          => $posted_data['phone'],
				'state' 				 => $posted_data['state'],
			);

			// If is anything on address line 2, add to shipping address.
			if ( $address_line_2 ) {
				$shipping_address['line2'] = mb_substr( implode( ', ', $address_line_2 ), 0, 100 );
			}

			// Add shipping address for non digital goods
			if ( ! $only_digital_items ) {
				$data['transactions'][0]['item_list']['shipping_address'] = $shipping_address;
			}

			// Set the application context
			$data['application_context'] = array(
				'brand_name'          => get_bloginfo( 'name' ),
				'shipping_preference' => $only_digital_items ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
			);

			// Create the payment in API.
			$create_payment = $gateway->api->create_payment( $data, array(), 'ec' );

			// Get the response links.
			$links = $gateway->api->parse_links( $create_payment['links'] );

			// Extract EC token from response.
			preg_match( '/(EC-\w+)/', $links['approval_url'], $ec_token );

			// Separate data.
			$data = array(
				'pay_id' => $create_payment['id'],
				'ec'     => $ec_token[0],
			);

			// Store the requested data in session.
			WC()->session->set( 'paypal_brasil_spb_data', $data );

			// Send success response with data.
			$this->send_success_response( __( 'Pagamento criado com sucesso.', 'paypal-brasil-para-woocommerce' ), $data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}

	// CUSTOM VALIDATORS

	public function required_text( $data, $key, $name ) {
		if ( ! empty( $data ) ) {
			return true;
		}

		return sprintf( __( 'O campo <strong>%s</strong> é obrigatório.', 'paypal-brasil-para-woocommerce' ), $name );
	}

	public function required_country( $data, $key, $name ) {
		return $this->required_text( $data, $key, $name );
	}

	public function required_state($data, $key, $name, $input) {
		$country = isset( $input['country'] ) && !empty( $input['country'] ) ? $input['country'] : '';
		$states = WC()->countries->get_states($country);

		if ( ! $states ) {
			return true;
		}

		if ( empty( $data ) ) {
			return sprintf( __( 'O campo <strong>%s</strong> é obrigatório.', 'paypal-brasil-para-woocommerce' ), $name );
		} else if ( ! isset( $states[ $data ] ) ) {
			return sprintf( __( 'O campo <strong>%s</strong> é inválido.', 'paypal-brasil-para-woocommerce' ), $name );
		}

		return true;
	}

	public function required_postcode( $data, $key, $name ) {
		return $this->required_text( $data, $key, $name );
	}

	public function required_nonce( $data, $key, $name ) {
		if ( wp_verify_nonce( $data, 'paypal-brasil-checkout' ) ) {
			return true;
		}

		return sprintf( __( 'O %s é inválido.', 'paypal-brasil-para-woocommerce' ), $name );
	}

}

new PayPal_Brasil_API_Checkout_Handler();