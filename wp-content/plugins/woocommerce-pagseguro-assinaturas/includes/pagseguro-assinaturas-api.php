<?php
/**
 * WooCommerce PagSeguro Assinaturas API class
 *
 * @package WooCommerce_PagSeguro_Assinaturas/Classes/API
 * @version 1.0
 */

class WC_PagSeguro_Assinaturas_API {
	
	/**
	 * Gateway class.
	 *
	 * @var WC_PagSeguro_Gateway
	 */
	protected $gateway;

	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get the API environment.
	 *
	 * @return string
	 */
	protected function get_environment() {
		return ( 'yes' == $this->gateway->sandbox ) ? 'sandbox.' : '';
	}

	/**
	 * Get the direct payment URL.
	 *
	 * @return string.
	 */
	public function get_direct_payment_url() {
		return 'https://stc.' . $this->get_environment() . 'pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
	}

	/**
	 * Get the sessions URL.
	 *
	 * @return string.
	 */
	protected function get_sessions_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/v2/sessions';
	}

	/**
	 * Get the Pre Approvals Request URL.
	 *
	 * @return string.
	 */
	protected function get_pre_approvals_request_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals/request';
	}

	/**
	 * Get the Pre Approvals URL.
	 *
	 * @return string.
	 */
	protected function get_pre_approvals_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals';
	}

	/**
	 * Get the Pre Approvals URL.
	 *
	 * @return string.
	 */
	protected function get_pre_approvals_payment_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals/payment';
	}

	/**
	 * Get the Pre Approvals URL.
	 *
	 * @return string.
	 */
	protected function get_pre_approval_status_url( $pre_approval_code ) {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals/' . urlencode( $pre_approval_code );
	}

	/**
	 * Get the notification URL.
	 *
	 * @return string.
	 */
	protected function get_notification_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/v2/transactions/notifications/';
	}

	/**
	 * Get the notification URL.
	 *
	 * @return string.
	 */
	protected function get_pre_approval_notification_url() {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals/notifications/';
	}

	/**
	 * Get the Pre Approvals URL for updating payment method.
	 *
	 * @return string.
	 */
	protected function get_pre_approvals_update_payment_url( $plan ) {
		return 'https://ws.' . $this->get_environment() . 'pagseguro.uol.com.br/pre-approvals/' . $plan . '/payment-method';
	}

	/**
	 * Money format.
	 *
	 * @param  int/float $value Value to fix.
	 *
	 * @return float            Fixed value.
	 */
	protected function money_format( $value ) {
		return number_format( $value, 2, '.', '' );
	}

	/**
	 * Sanitize the item description.
	 *
	 * @param  string $description Description to be sanitized.
	 *
	 * @return string
	 */
	protected function sanitize_description( $description ) {
		return sanitize_text_field( substr( $description, 0, 95 ) );
	}

	/**
	 * Safe load XML.
	 *
	 * @param  string $source  XML source.
	 * @param  int    $options DOMDpocment options.
	 *
	 * @return SimpleXMLElement|bool
	 */
	protected function safe_load_xml( $source, $options = 0 ) {
		$old = null;

		if ( '<' !== substr( $source, 0, 1 ) ) {
			return false;
		}

		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old = libxml_disable_entity_loader( true );
		}

		$dom    = new DOMDocument();
		$return = $dom->loadXML( $source, $options );

		if ( ! is_null( $old ) ) {
			libxml_disable_entity_loader( $old );
		}

		if ( ! $return ) {
			return false;
		}

		if ( isset( $dom->doctype ) ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Unsafe DOCTYPE Detected while XML parsing' );
			}

			return false;
		}

		return simplexml_import_dom( $dom );
	}

	/**
	 * Get order items.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return array           Items list, extra amount and shipping cost.
	 */
	protected function get_order_items( $order ) {
		$items         = array();
		$extra_amount  = 0;

		// Force only one item.
		if ( 'yes' == $this->gateway->send_only_total ) {
			$items[] = array(
				'description' => $this->sanitize_description( sprintf( __( 'Order %s', 'woocommerce-pagseguro-assinaturas' ), $order->get_order_number() ) ),
				'amount'      => $this->money_format( $order->get_total() ),
				'quantity'    => 1,
			);
		} else {

			// Products.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $order_item ) {
					if ( $order_item['qty'] ) {
						$item_total = $order->get_item_total( $order_item, false );
						if ( 0 >= (float) $item_total ) {
							continue;
						}

						$item_name = $order_item['name'];

						if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
							if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.4.0', '<' ) ) {
								$item_meta = new WC_Order_Item_Meta( $order_item['item_meta'] );
							} else {
								$item_meta = new WC_Order_Item_Meta( $order_item );
							}

							if ( $meta = $item_meta->display( true, true ) ) {
								$item_name .= ' - ' . $meta;
							}
						}

						$items[] = array(
							'description' => $this->sanitize_description( str_replace( '&ndash;', '-', $item_name ) ),
							'amount'      => $this->money_format( $item_total ),
							'quantity'    => $order_item['qty'],
						);
					}
				}
			}

			// Fees.
			if ( 0 < count( $order->get_fees() ) ) {
				foreach ( $order->get_fees() as $fee ) {
					if ( 0 >= (float) $fee['line_total'] ) {
						continue;
					}

					$items[] = array(
						'description' => $this->sanitize_description( $fee['name'] ),
						'amount'      => $this->money_format( $fee['line_total'] ),
						'quantity'    => 1,
					);
				}
			}

			// Taxes.
			if ( 0 < count( $order->get_taxes() ) ) {
				foreach ( $order->get_taxes() as $tax ) {
					$tax_total = $tax['tax_amount'] + $tax['shipping_tax_amount'];
					if ( 0 >= (float) $tax_total ) {
						continue;
					}

					$items[] = array(
						'description' => $this->sanitize_description( $tax['label'] ),
						'amount'      => $this->money_format( $tax_total ),
						'quantity'    => 1,
					);
				}
			}

			// Shipping Cost.
			if ( 0 < $order->get_total_shipping() ) {
				$items[] = array(
					'description' => __( 'Shipping', 'woocommerce'),
					'amount'      => $this->money_format( $order->get_total_shipping() ),
					'quantity'    => 1,
				);
			}

			// Discount.
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '<' ) ) {
				if ( 0 < $order->get_order_discount() ) {
					$extra_amount = '-' . $this->money_format( $order->get_order_discount() );
				}
			}
		}

		return array(
			'items'         => $items,
			'extra_amount'  => $extra_amount,
		);
	}

	/**
	 * Do requests in the PagSeguro API.
	 *
	 * @param  string $url      URL.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $url, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'  => $method,
			'timeout' => 60,
		);

		if ( ( 'POST' == $method || 'PUT' == $method ) && ! empty( $data ) ) {
			$params['body'] = $data;
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		}

		return wp_safe_remote_post( $url, $params );
	}



	/**
	 * Get the pre approval status xml.
	 *
	 * @param string   $status Pagseguro statuses para assinaturas.
	 *
	 * @return string
	 */
	protected function get_pre_approval_status_xml( $status ) {

		// Creates the xml.
		$xml = new WC_PagSeguro_Assinaturas_XML( '<?xml version="1.0"?><directPreApproval></directPreApproval>' );
		$xml->addChild( 'status', $status );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_pagseguro_pre_approval_status_xml', $xml, $status );

		return $xml->render();
	}	

	/**
	 * Get the pre approval request xml.
	 *
	 * @param string 	$period WooCommere period string (month, week).
	 * @param int 		$interval WooCommerce Subsscriptions interval (1, 2, 3)
	 *
	 * @return string
	 */
	protected function get_pre_approvals_request_xml( $period, $interval ) {

		$period = $this->gateway->get_pagseguro_period( $period, $interval );

		// Creates the xml.
		$xml = new WC_PagSeguro_Assinaturas_XML( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><preApprovalRequest></preApprovalRequest>' );

		// WooCommerce 3.0 or later.
		$xml->add_reference( $this->gateway->invoice_prefix . $period ); 
		$preApproval = $xml->addChild( 'preApproval' );
		$preApproval->addChild( 'name', 'Plano (' . $this->gateway->get_pagseguro_period_label( $period ) . ')' );
		$preApproval->addChild( 'charge', 'MANUAL' );
		$preApproval->addChild( 'period', $period );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_pagseguro_pre_approvals_request_xml', $xml, $period );

		return $xml->render();
	}

	/**
	 * Get the pre approvals xml.
	 *
	 * @param string 	$code Código do plano no PAgseguro.
	 * @param WC_Order 	$order Order data.
	 * @param array    	$posted Posted data.
	 *
	 * @return string
	 */
	protected function get_pre_approvals_xml( $code, $order, $posted ) {
		$ship_to = isset( $posted['ship_to_different_address'] ) ? true : false;
		$hash    = isset( $posted['pagseguro_assinaturas_sender_hash'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_sender_hash'] ) : '';

		// Creates the xml.
		$xml = new WC_PagSeguro_Assinaturas_XML( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><directPreApproval></directPreApproval>' );
		$xml->addChild('plan', $code );
		$xml->add_reference( $this->gateway->invoice_prefix . $order->get_id() );
		$xml->add_sender_data_for_subscription( $order, $ship_to, $hash, $this->gateway->sandbox );

		$credit_card_token = isset( $posted['pagseguro_assinaturas_credit_card_hash'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_credit_card_hash'] ) : '';

		$holder_data = $this->get_holder_data( $order, $posted );

		$xml->add_credit_card_data_for_subscription( $order, $credit_card_token, $holder_data );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_pagseguro_pre_approvals_xml', $xml, $order );

		return $xml->render();
	}

	/**
	 * Get the pre approvals xml.
	 *
	 * @param string 	$code Código da assinatura no PAgseguro.
	 * @param WC_Order 	$order Order data.
	 *
	 * @return string
	 */
	protected function get_pre_approvals_payment_xml( $code, $order) {
		$data    = $this->get_order_items( $order );

		// Creates the payment xml.
		$xml = new WC_PagSeguro_Assinaturas_XML( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><payment></payment>' );
		$xml->addChild('preApprovalCode', $code );
		$xml->add_reference( $this->gateway->invoice_prefix . $order->get_id() );
		
		$senderHash = $order->get_meta( '_sender_hash' );
		if( '' != $senderHash ) {
			$xml->addChild('senderHash', $senderHash );
		}
		
		$xml->addChild('senderIp', $order->get_customer_ip_address() );
		$xml->add_items( $data['items'] );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_pagseguro_pre_approvals_payment_xml', $xml, $order );
		return $xml->render();
	}


	/**
	 * Get the pre approvals xml.
	 *
	 * @param string 	$code Código do plano no PAgseguro.
	 * @param WC_Order 	$order Order data.
	 * @param array    	$posted Posted data.
	 *
	 * @return string
	 */
	protected function get_pre_approvals_xml_for_payment_method_update( $user, $posted ) {
		$hash    = isset( $posted['pagseguro_assinaturas_sender_hash'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_sender_hash'] ) : '';
		
		// Creates the xml.
		$xml = new WC_PagSeguro_Assinaturas_XML( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><paymentMethod></paymentMethod>' );
		
		$credit_card_token = isset( $posted['pagseguro_assinaturas_credit_card_hash'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_credit_card_hash'] ) : '';

		$holder_data       = array(
			'name'       => isset( $posted['pagseguro_assinaturas_card_holder_name'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_name'] ) : '',
			'cpf'        => isset( $posted['pagseguro_assinaturas_card_holder_cpf'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_cpf'] ) : '',
			'birth_date' => isset( $posted['pagseguro_assinaturas_card_holder_birth_date'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_birth_date'] ) : '',
			'phone'      => isset( $posted['pagseguro_assinaturas_card_holder_phone'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_phone'] ) : '',
		);

		// WooCommerce 3.0 or later.
		$xml->add_sender_data_for_payment_method_update( $user, $holder_data, $hash, $this->gateway->sandbox );
		$xml->add_credit_card_data_for_payment_method_update( $user, $credit_card_token, $holder_data );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_pagseguro_pre_approvals_method_update_xml', $xml, $order );

		return $xml->render();
	}

	/**
	 * Creaste a Manual Plan on PagSeguro
	 *
	 * @param string 	$period WooCommere period string (month, week).
	 * @param int 		$interval WooCommerce Subsscriptions interval (1, 2, 3)
	 *
	 * @return string
	 */
	public function create_manual_plan( $period, $interval ) {

		// Sets the xml.
		$xml = $this->get_pre_approvals_request_xml( $period, $interval );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Creating a manual plan for with  '.$period.' recurring period with the following data: ' . $xml );
		}

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approvals_request_url() );
		$response = $this->do_request( $url, 'POST', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		if ( is_wp_error( $response ) ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in creating a plan: ' . $response->get_error_message() );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'WP_Error in creating a plan.', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		
		} else if ( 401 === $response['response']['code'] ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'The user does not have permissions to use the PagSeguro Transparent Checkout!' );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'You are not allowed to use the PagSeguro Transparent Checkout. Looks like you neglected to installation guide of this plugin. This is not pretty, do you know?', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		} else {
			
			try {
				$data = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$data = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if( isset( $data->code ) ) {

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Plan created successfuly: ' . print_r( $response, true ) );
				}

				return array(
					'code'   => (string) $data->code,
					'error' => array(),
				);

			}

			if ( isset( $data->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'An error occurred while creating the PagSeguro plan: ' . print_r( $response, true ) );
				}

				foreach ( $data->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'data'   => '',
					'error' => $errors,
				);
			}

		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'An error occurred while generating the PagSeguro direct payment: ' . print_r( $response, true ) );
		}

		// Return error message.
		return array(
			'url'   => '',
			'data'  => '',
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' ) ),
		);
	}

	/**
	 * Add a customer to a plan
	 *
	 * @param string 	$plan Código do plano para adesão
	 * @param WC_Order 	$order Order data.
	 * @param array 	$posted Posted data
	 *
	 * @return string
	 */
	public function add_customer_to_plan( $plan, $order, $posted ) {

		// Sets the xml.
		$xml = $this->get_pre_approvals_xml( $plan, $order, $posted );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Adding customer to plan: ' . $xml );
		}

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approvals_url() );
		$response = $this->do_request( $url, 'POST', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		if ( is_wp_error( $response ) ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in adding customer to plan: ' . $response->get_error_message() );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'WP_Error in adding customer to plan.', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		
		} else if ( 401 === $response['response']['code'] ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'The user does not have permissions to use the PagSeguro Transparent Checkout!' );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'You are not allowed to use the PagSeguro Transparent Checkout. Looks like you neglected to installation guide of this plugin. This is not pretty, do you know?', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		} else {
			
			try {
				$data = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$data = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if( isset( $data->code ) ) {

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Customer added to plan successfuly: ' . print_r( $response, true ) );
				}

				return array(
					'code'   => (string) $data->code,
					'error' => array(),
				);

			}

			if ( isset( $data->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'An error occurred while adding customer to plan: ' . print_r( $response, true ) );
				}

				foreach ( $data->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'data'   => '',
					'error' => $errors,
				);
			}

		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'An error occurred while generating the PagSeguro direct payment: ' . print_r( $response, true ) );
		}

		// Return error message.
		return array(
			'url'   => '',
			'data'  => '',
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' ) ),
		);
	}


	/**
	 * Add a customer to a plan
	 *
	 * @param string 	$plan Código do plano para adesão
	 * @param WC_Order 	$order Order data.
	 * @param array 	$posted Posted data
	 *
	 * @return string
	 */
	public function update_payment_method( $user, $plan, $posted, $subscription ) {

		// Sets the xml.
		$xml = $this->get_pre_approvals_xml_for_payment_method_update( $user, $posted );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Updating Credit Card for customer: ' . $xml );
		}

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approvals_update_payment_url( $plan ) );

		$response = $this->do_request( $url, 'PUT', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		if ( is_wp_error( $response ) ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in updating credit card: ' . $response->get_error_message() );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'Error updating credit card.', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		
		} else if ( 401 === $response['response']['code'] ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'The user does not have permissions to use the PagSeguro Transparent Checkout!' );
			}

			return array(
				'data'  => '',
				'error' => array( __( 'You are not allowed to use the PagSeguro Transparent Checkout. Looks like you neglected to installation guide of this plugin. This is not pretty, do you know?', 'woocommerce-pagseguro-assinaturas' ) ),
			);
		} else {
			
			try {
				$data = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$data = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if( empty( $data ) ) {

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Credit Cart Updated successfuly: ' . print_r( $response, true ) );
				}

				return array(
					'success' => true,
				);

			}

			if ( isset( $data->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					// $this->gateway->log->add( $this->gateway->id, 'Error updating credit card: ' . print_r( $response, true ) );
					$this->gateway->log->add( $this->gateway->id, 'Error updating credit card: ' . print_r( $data->error, true ) );
				}

				foreach ( $data->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'data'   => '',
					'url'   => '',
					'error' => $errors,
				);
			}

		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'An error occurred while updating credit card on pagseguro: ' . print_r( $response, true ) );
		}

		// Return error message.
		return array(
			'url'   => '',
			'data'  => '',
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error occurred while updating credit card on pagseguro.', 'woocommerce-pagseguro-assinaturas' ) ),
		);
	}


	/**
	 * Process payment on a subscription
	 *
	 * @param string 	$code Código da assinatura
	 * @param WC_Order 	$order Order data.
	 *
	 * @return string
	 */
	public function make_subscription_payment( $code, $order) {

		$xml = $this->get_pre_approvals_payment_xml( $code, $order );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Making payment for order ' . $order->get_order_number() . ' with the following data: ' . $xml );
		}

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approvals_payment_url() );
		$response = $this->do_request( $url, 'POST', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Subscription Payment Response: ' . print_r($response, true) );
		}

		if ( is_wp_error( $response ) ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in adding payment to a plan: ' . $response->get_error_message() );
			}

			return array(
				'data'  => '',
				'error' => 'WP_Error in adding payment to a plan',
			);
	
		} else {
			
			try {
				$data = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$data = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if ( isset( $data->transactionCode ) ) {
		
				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Payment added to the plan successfully!' );
				}

				return array(
					'transactionCode'  => (string) $data->transactionCode,
					'error' => '',
				);


			}

			if ( isset( $data->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'An error occurred while making a payment: ' . print_r( $response, true ) );
				}

				foreach ( $data->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'data'   => '',
					'error' => $errors,
				);
			}

		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'An error occurred while generating the PagSeguro direct payment: ' . print_r( $response, true ) );
		}

		// Return error message.
		return array(
			'url'   => '',
			'data'  => '',
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' ) ),
		);

	}


	/**
	 * Process the subscription request from checkout
	 *
	 * @param  WC_Order $order  Order data.
	 * @param  array    $posted Posted data.
	 *
	 * @return array
	 */
	public function do_subscription_request( $order, $posted ) {
		
		$is_payment_change = WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
		$order_contains_failed_renewal = false;

		// Payment method changes act on the subscription not the original order
		if ( $is_payment_change ) {

			$subscription = wcs_get_subscription( wcs_get_objects_property( $order, 'id' ) );
			$order        = $subscription->get_parent();

			$current_user = wp_get_current_user();
			$plan = get_post_meta($subscription->get_id(), '_pagseguro_assinatura', true);
			$response = $this->update_payment_method( $current_user, $plan, $posted, $subscription );

			if( isset( $response['success'] ) ) {

				return array(
					'result' => 'success',
					'redirect' => get_permalink( get_option('woocommerce_myaccount_page_id') )
				);
			
			} else {

				wc_add_notice( $response['error'][0], 'error' );

				$url = wp_nonce_url( add_query_arg( array( 'change_payment_method' => $subscription->get_id() ), $subscription->get_checkout_payment_url() ) );
				wc_add_notice( sprintf( __('Payment method update failed. <a href="%s">Try Again</a>', 'woocommerce-pagseguro-assinaturas'), $url ), 'error' );
				wp_redirect( $subscription->get_view_order_url() );
				exit;
			
			}
		
		} else {

			// Otherwise the order is the $order
			if ( $cart_item = wcs_cart_contains_failed_renewal_order_payment() || false !== WC_Subscriptions_Renewal_Order::get_failed_order_replaced_by( wcs_get_objects_property( $order, 'id' ) ) ) {
				$subscriptions                 = wcs_get_subscriptions_for_renewal_order( $order );
				$order_contains_failed_renewal = true;
			} else {
				$subscriptions                 = wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'switch', 'parent', 'renewal' ) ) );
			}

			// Only one subscription allowed per order with PayPal
			$subscription = array_pop( $subscriptions );
		}

		
		if ( $order_contains_failed_renewal || ! empty( $subscription ) ) {
		
			// wc_set_time_limit( 60 );

			// Get billing period
			$period 	= $subscription->get_billing_period();
			$interval 	= $subscription->get_billing_interval();
			$plan 		= $this->gateway->get_pagseguro_plan( $period, $interval );
			$response 	= $this->add_customer_to_plan( $plan, $order, $posted );

			if( isset( $response['code'] ) ) {
				$assinatura = $response['code'];
			} else {
				return array(
					'error' => $response['error']
				);
			}

			$status = 'PENDING';
			$tracker = '';

			// If not a free trial, charge subscription now
			if( $order->get_total() > 0 ) {

				if('ACTIVE' != $status ) {
					return array(
						'url' => $this->gateway->get_return_url( $order ),
						'data' => array(
							'status' 		=> (string) $status,
							'assinatura'	=> (string) $assinatura,
							'tracker'		=> (string) $tracker
						)
					);
				}
				
				$response = $this->make_subscription_payment( $assinatura, $order );
			
			} else {
				$response = array(
					'transactionCode' => 0
				);
			}

			if( isset( $response['transactionCode']) ) { 
				
				return array(
					'url' => $this->gateway->get_return_url( $order ),
					'data' => array(
						'transactionCode' => (string) $response['transactionCode'],
						'assinatura'	 => (string) $assinatura,
						'tracker'		=> (string) $tracker
					)
				);

			}

			if( isset( $response['error']) && !empty( $response['error'] ) ) {

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'An error occurred while generating the PagSeguro payment: ' . print_r( $response, true ) );
				}

				return array(
					'error' => $response['error'],
				);

			}


		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Could not process subscription on do_subscription_request()' );
		}

		// Return error message.
		return array(
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' ) ),
		);
	}

	/**
	 * Suspend pre-approval.
	 *
	 * @param string $assinatura (Pre Approval Code)
	 * @param string $status PagSeguro Status string
	 * @param int 	 $order_id Subscription Order Id
	 *
	 * @return boolean
	 */
	public function pagseguro_update_subscription_status( $assinatura = '', $status = 'SUSPENDED', $order_id ) {

		if( $assinatura == '')
			return false;

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( __('Suspending Subscription (%s) for order: %s'), $assinatura, $order_id ) );
		} 

		$xml = $this->get_pre_approval_status_xml( $status );

		$request_url = $this->get_pre_approvals_url() . '/' . $assinatura . '/status';

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $request_url );
		$this->do_request( $url, 'PUT', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		return true;

	}

	/**
	 * Cancel pre-approval.
	 *
	 * @param string $assinatura (Pre Approval Code)
	 * @param string $status PagSeguro Status string
	 * @param int 	 $order_id Subscription Order Id
	 *
	 * @return boolean
	 */
	public function pagseguro_cancel_subscription( $assinatura = '', $order_id ) {

		if( $assinatura == '')
			return false;

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, sprintf( __('Cancelling Subscription (%s) for order: %s'), $assinatura, $order_id ) );
		} 

		$request_url = $this->get_pre_approvals_url() . '/' . $assinatura . '/cancel/';

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $request_url );
		$this->do_request( $url, 'PUT', '', array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		return true;

	}	

	/**
	 * Get pre-approval status
	 *
	 * @param string $assinatura (Pre Approval Code)
	 *
	 * @return boolean
	 */
	public function get_pre_approval_status( $assinatura = '' ) {

		if( '' == $assinatura )
			return false;

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approval_status_url( $assinatura ) );
		$response = $this->do_request( $url, 'GET', $xml, array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Pre approval status response: ' . print_r( $response, true) );
		}

		if ( is_wp_error( $response ) ) {

			return array(
				'data'  => '',
				'error' => 'Error in getting status of pre approval.',
			);
	
		} else {
			
			try {
				
				$data = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			
			} catch ( Exception $e ) {
				
				$data = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Pre-approval parsed XML: ' . print_r( $data, true) );
			}

			if ( isset( $data->status ) ) {

				return array(
					'status'  => (string) $data->status,
					'tracker'  => (string) $data->tracker
				);
			}

			if ( isset( $data->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'An error occurred while getting pre-approval status for: ' . $assinatura );
				}

				foreach ( $data->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'data'   => '',
					'error' => $errors,
				);
			}

		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'An error occurred while getting pre-approval status' );
		}

		// Return error message.
		return array(
			'url'   => '',
			'data'  => '',
			'error' => array( '<strong>' . __( 'PagSeguro', 'woocommerce-pagseguro-assinaturas' ) . '</strong>: ' . __( 'An error occurred while getting pre-approval status.', 'woocommerce-pagseguro-assinaturas' ) ),
		);

	}	

	/**
	 * Process the IPN.
	 *
	 * @param  array $data IPN data.
	 *
	 * @return bool|SimpleXMLElement
	 */
	public function get_ipn_notification( $data ) {

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Checking IPN request...:' . print_r( $data, true ));
		}

		// Valid the post data.
		if ( ! isset( $data['notificationCode'] ) && ! isset( $data['notificationType'] ) ) {
			
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Invalid IPN request: ' . print_r( $data, true ) );
			}	

		}

		if( 'transaction' == $data['notificationType'] ) {
			
			// Gets the PagSeguro response.
			$url = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_notification_url() . esc_attr( $data['notificationCode'] ) );
			$response = $this->do_request( $url, 'GET' );
		
		} elseif ( 'preApproval' == $data['notificationType']) {
			
			$url = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_pre_approval_notification_url() . esc_attr( $data['notificationCode'] ) );
			$response = $this->do_request( $url, 'GET', '', array( 'Content-Type' => 'application/xml;charset=UTF-8', 'Accept' => 'application/vnd.pagseguro.com.br.v3+xml;charset=ISO-8859-1' ) );
		
		} else {
			return false;
		}

		// Check to see if the request was valid.
		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in IPN: ' . $response->get_error_message() );
			}
		} else {
			try {
				$body = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$body = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro IPN response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if ( isset( $body->code ) ) {
				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'PagSeguro IPN is valid! The return is: ' . print_r( $body, true ) );
				}

				return $body;
			}
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'IPN Response: ' . print_r( $response, true ) );
		}

		return false;
	}

	/**
	 * Get error message.
	 *
	 * @param  int $code Error code.
	 *
	 * @return string
	 */
	public function get_error_message( $code ) {
		$code = (string) $code;

		$messages = array(
			'10003' => __( 'Email invalid value.', 'woocommerce-pagseguro-assinaturas' ),
			'10005' => __( 'The accounts of the vendor and buyer can not be related to each other.', 'woocommerce-pagseguro-assinaturas' ),
			'10009' => __( 'Method of payment currently unavailable.', 'woocommerce-pagseguro-assinaturas' ),
			'10020' => __( 'Invalid payment method.', 'woocommerce-pagseguro-assinaturas' ),
			'10021' => __( 'Error fetching vendor data from the system.', 'woocommerce-pagseguro-assinaturas' ),
			'10023' => __( 'Payment Method unavailable.', 'woocommerce-pagseguro-assinaturas' ),
			'10024' => __( 'Unregistered buyer is not allowed.', 'woocommerce-pagseguro-assinaturas' ),
			'10025' => __( 'senderName cannot be blank.', 'woocommerce-pagseguro-assinaturas' ),
			'10026' => __( 'senderEmail cannot be blank.', 'woocommerce-pagseguro-assinaturas' ),
			'10049' => __( 'senderName mandatory.', 'woocommerce-pagseguro-assinaturas' ),
			'10050' => __( 'senderEmail mandatory.', 'woocommerce-pagseguro-assinaturas' ),
			'11002' => __( 'receiverEmail invalid length: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11006' => __( 'redirectURL invalid length: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11007' => __( 'redirectURL invalid value: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11008' => __( 'reference invalid length: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11013' => __( 'senderAreaCode invalid value: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11014' => __( 'senderPhone invalid value: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11027' => __( 'Item quantity out of range: {0}', 'woocommerce-pagseguro-assinaturas'),
			'11028' => __( 'Item amount is required. (e.g. "12.00")', 'woocommerce-pagseguro-assinaturas' ),
			'11040' => __( 'maxAge invalid pattern: {0}. Must be an integer.', 'woocommerce-pagseguro-assinaturas' ),
			'11041' => __( 'maxAge out of range: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11042' => __( 'maxUses invalid pattern: {0}. Must be an integer.', 'woocommerce-pagseguro-assinaturas' ),
			'11043' => __( 'maxUses out of range: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11054' => __( 'abandonURL/reviewURL invalid length: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11055' => __( 'abandonURL/reviewURL invalid value: {0}', 'woocommerce-pagseguro-assinaturas' ),
			'11071' => __( 'preApprovalInitialDate invalid value.', 'woocommerce-pagseguro-assinaturas'),
			'11072' => __( 'preApprovalFinalDate invalid value.', 'woocommerce-pagseguro-assinaturas'),
			'11084' => __( 'seller has no credit card payment option.', 'woocommerce-pagseguro-assinaturas'),
			'11101' => __( 'preApproval data is required.', 'woocommerce-pagseguro-assinaturas'),
			'11163' => __( 'You must configure a transactions notifications (Notificação de Transações) URL before using this service.', 'woocommerce-pagseguro-assinaturas'),
			'11211' => __( 'pre-approval cannot be paid twice on the same day.', 'woocommerce-pagseguro-assinaturas'),
			'13005' => __( 'initialDate must be lower than allowed limit.', 'woocommerce-pagseguro-assinaturas'),
			'13006' => __( 'initialDate must not be older than 180 days.', 'woocommerce-pagseguro-assinaturas'),
			'13007' => __( 'initialDate must be lower than or equal finalDate.', 'woocommerce-pagseguro-assinaturas'),
			'13008' => __( 'search interval must be lower than or equal 30 days.', 'woocommerce-pagseguro-assinaturas'),
			'13009' => __( 'finalDate must be lower than allowed limit.', 'woocommerce-pagseguro-assinaturas'),
			'13010' => __( 'initialDate invalid format use "yyyy-MM-ddTHH:mm" (eg. 2010-01-27T17:25).', 'woocommerce-pagseguro-assinaturas'),
			'13011' => __( 'finalDate invalid format use "yyyy-MM-ddTHH:mm" (eg. 2010-01-27T17:25).  | 13013 | page invalid value.', 'woocommerce-pagseguro-assinaturas'),
			'13014' => __( 'maxPageResults invalid value (must be between 1 and 1000).', 'woocommerce-pagseguro-assinaturas'),
			'13017' => __( 'initialDate and finalDate are required on searching by interval.', 'woocommerce-pagseguro-assinaturas'),
			'13018' => __( 'interval must be between 1 and 30.', 'woocommerce-pagseguro-assinaturas'),
			'13019' => __( 'notification interval is required.', 'woocommerce-pagseguro-assinaturas'),
			'13020' => __( 'page is greater than the total number of pages returned.', 'woocommerce-pagseguro-assinaturas'),
			'13023' => __( 'Invalid minimum reference length (1-255)', 'woocommerce-pagseguro-assinaturas'),
			'13024' => __( 'Invalid maximum reference length (1-255)', 'woocommerce-pagseguro-assinaturas'),
			'17008' => __( 'pre-approval not found.', 'woocommerce-pagseguro-assinaturas'),
			'17022' => __( 'invalid pre-approval status to execute the requested operation. Pre-approval status is {0}.', 'woocommerce-pagseguro-assinaturas'),
			'17023' => __( 'seller has no credit card payment option.', 'woocommerce-pagseguro-assinaturas'),
			'17024' => __( 'pre-approval is not allowed for this seller {0}', 'woocommerce-pagseguro-assinaturas'),
			'17032' => __( 'invalid receiver for checkout: {0} verify receiver\'s account status and if it is a seller\'s account.', 'woocommerce-pagseguro-assinaturas'),
			'17033' => __( 'preApproval.paymentMethod isn\'t {0} must be the same from pre-approval.', 'woocommerce-pagseguro-assinaturas'),
			'17035' => __( 'Due days format is invalid: {0}.', 'woocommerce-pagseguro-assinaturas'),
			'17036' => __( 'Due days value is invalid: {0}. Any value from 1 to 120 is allowed.', 'woocommerce-pagseguro-assinaturas'),
			'17037' => __( 'Due days must be smaller than expiration days.', 'woocommerce-pagseguro-assinaturas'),
			'17038' => __( 'Expiration days format is invalid: {0}.', 'woocommerce-pagseguro-assinaturas'),
			'17039' => __( 'Expiration value is invalid: {0}. Any value from 1 to 120 is allowed.', 'woocommerce-pagseguro-assinaturas'),
			'17061' => __( 'Plan not found.', 'woocommerce-pagseguro-assinaturas'),
			'17063' => __( 'Hash is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17065' => __( 'Documents required.', 'woocommerce-pagseguro-assinaturas'),
			'17066' => __( 'Invalid document quantity.', 'woocommerce-pagseguro-assinaturas'),
			'17067' => __( 'Payment method type is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17068' => __( 'Payment method type is invalid.', 'woocommerce-pagseguro-assinaturas'),
			'17069' => __( 'Phone is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17070' => __( 'Address is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17071' => __( 'Sender is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17072' => __( 'Payment method is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17073' => __( 'Credit card is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17074' => __( 'Credit card holder is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'17075' => __( 'Credit card token is invalid.', 'woocommerce-pagseguro-assinaturas'),
			'17078' => __( 'Expiration date reached.', 'woocommerce-pagseguro-assinaturas'),
			'17079' => __( 'Use limit exceeded.', 'woocommerce-pagseguro-assinaturas'),
			'17080' => __( 'Pre-approval is suspended.', 'woocommerce-pagseguro-assinaturas'),
			'17081' => __( 'pre-approval payment order not found.', 'woocommerce-pagseguro-assinaturas'),
			'17082' => __( 'invalid pre-approval payment order status to execute the requested operation. Pre-approval payment order status is {0}.', 'woocommerce-pagseguro-assinaturas'),
			'17083' => __( 'Pre-approval is already {0}.', 'woocommerce-pagseguro-assinaturas'),
			'17093' => __( 'Sender hash or IP is required.', 'woocommerce-pagseguro-assinaturas'),
			'17094' => __( 'There can be no new subscriptions to an inactive plan.', 'woocommerce-pagseguro-assinaturas'),
			'19001' => __( 'postalCode invalid Value: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19002' => __( 'addressStreet invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19003' => __( 'addressNumber invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19004' => __( 'addressComplement invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19005' => __( 'addressDistrict invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19006' => __( 'addressCity invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19007' => __( 'addressState invalid value: {0} must fit the pattern: \w{2} (e. g. "SP")', 'woocommerce-pagseguro-assinaturas'),
			'19008' => __( 'addressCountry invalid length: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19014' => __( 'senderPhone invalid value: {0}', 'woocommerce-pagseguro-assinaturas'),
			'19015' => __( 'addressCountry invalid pattern: {0}', 'woocommerce-pagseguro-assinaturas'),
			'50103' => __( 'postal code can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'50105' => __( 'address number can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'50106' => __( 'address district can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'50107' => __( 'address country can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'50108' => __( 'address city can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'50131' => __( 'The IP address does not follow a valid pattern', 'woocommerce-pagseguro-assinaturas'),
			'50134' => __( 'address street can not be empty', 'woocommerce-pagseguro-assinaturas'),
			'53037' => __( 'credit card token is required.', 'woocommerce-pagseguro-assinaturas'),
			'53042' => __( 'credit card holder name is required.', 'woocommerce-pagseguro-assinaturas'),
			'53047' => __( 'credit card holder birthdate is required.', 'woocommerce-pagseguro-assinaturas'),
			'53048' => __( 'credit card holder birthdate invalid value: {0}', 'woocommerce-pagseguro-assinaturas'),
			'53151' => __( 'Discount value cannot be blank.', 'woocommerce-pagseguro-assinaturas'),
			'53152' => __( 'Discount value out of range. For DISCOUNT_PERCENT type the value must be greater than or equal to 0.00 and less than or equal to 100.00.', 'woocommerce-pagseguro-assinaturas'),
			'53153' => __( 'not found next payment for this preApproval.', 'woocommerce-pagseguro-assinaturas'),
			'53154' => __( 'Status cannot be blank.', 'woocommerce-pagseguro-assinaturas'),
			'53155' => __( 'Discount type is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'53156' => __( 'Discount type invalid value. Valid values are: DISCOUNT_AMOUNT and DISCOUNT_PERCENT.', 'woocommerce-pagseguro-assinaturas'),
			'53157' => __( 'Discount value out of range. For DISCOUNT_AMOUNT type the value must be greater than or equal to 0.00 and less than or equal to the maximum amount of the correspondin payment.', 'woocommerce-pagseguro-assinaturas'),
			'5315' => __( 'Discount value is mandatory.', 'woocommerce-pagseguro-assinaturas'),
			'57038' => __( 'address state is required.', 'woocommerce-pagseguro-assinaturas'),
			'61007' => __( 'document type is required.', 'woocommerce-pagseguro-assinaturas'),
			'61008' => __( 'document type is invalid: {0}', 'woocommerce-pagseguro-assinaturas'),
			'61009' => __( 'document value is required.', 'woocommerce-pagseguro-assinaturas'),
			'61010' => __( 'document value is invalid: {0}', 'woocommerce-pagseguro-assinaturas'),
			'61011' => __( 'cpf is invalid: {0}', 'woocommerce-pagseguro-assinaturas'),
			'61012' => __( 'cnpj is invalid: {0}', 'woocommerce-pagseguro-assinaturas')
		);

		if ( isset( $messages[ $code ] ) ) {
			return $messages[ $code ];
		}

		return __( 'An error has occurred while processing your payment, please review your data and try again. Or contact us for assistance.', 'woocommerce-pagseguro-assinaturas' );
	}

	/**
	 * Get session ID.
	 *
	 * @return string
	 */
	public function get_session_id() {

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Requesting session ID...' );
		}

		$url      = add_query_arg( array( 'email' => $this->gateway->get_email(), 'token' => $this->gateway->get_token() ), $this->get_sessions_url() );
		$response = $this->do_request( $url, 'POST' );

		// Check to see if the request was valid.
		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error requesting session ID: ' . $response->get_error_message() );
			}
		} else {
			try {
				$session = $this->safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$session = '';

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Error while parsing the PagSeguro session response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if ( isset( $session->id ) ) {
				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'PagSeguro session is valid! The return is: ' . print_r( $session, true ) );
				}

				return (string) $session->id;
			}
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Session Response: ' . print_r( $response, true ) );
		}

		return false;
	}

	protected function get_holder_data( $order, $posted ) {

		$holder_data       = array(
			'name'       => isset( $posted['pagseguro_assinaturas_card_holder_name'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_name'] ) : ''
		);

		if( true === $this->gateway->cc_hide_cpf_field ) { 
			
			$wcbcf_settings = get_option( 'wcbcf_settings' );
			$wcbcf_settings = isset( $wcbcf_settings['person_type'] ) ? intval( $wcbcf_settings['person_type'] ) : 0;

			if ( ( 0 === $wcbcf_settings || 2 === $wcbcf_settings ) && '' !== $order->get_meta( '_billing_cpf' ) ) {
				$holder_data['cpf'] = $order->get_meta( '_billing_cpf' );
			} else if ( ( 0 === $wcbcf_settings || 3 === $wcbcf_settings ) && '' !== $order->get_meta( '_billing_cnpj' ) ) {
				$holder_data['cnpj'] = $order->get_meta( '_billing_cnpj' );
			} else if ( '' !== $order->get_meta( '_billing_persontype' ) ) {
				if ( 1 === intval( $order->get_meta( '_billing_persontype' ) ) && '' !== $order->get_meta( '_billing_cpf' ) ) {
					$holder_data['cpf'] = $order->get_meta( '_billing_cpf' );
				} else if ( 2 === intval( $order->get_meta( '_billing_persontype' ) ) && '' !== $order->get_meta( '_billing_cnpj' ) ) {
					$holder_data['cnpj'] = $order->get_meta( '_billing_cnpj' );
				}
			}

		} else {
			$holder_data['cpf'] = isset( $posted['pagseguro_assinaturas_card_holder_cpf'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_cpf'] ) : '';
		}

		if( true === $this->gateway->cc_hide_birthdate_field ) { 
			$holder_data['birth_date'] = $order->get_meta('_billing_birthdate');
		} else {
			$holder_data['birth_date'] = isset( $posted['pagseguro_assinaturas_card_holder_birth_date'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_birth_date'] ) : '';
		}

		if( true === $this->gateway->cc_hide_phone_field ) { 
			$holder_data['phone'] = $order->get_billing_phone();
		} else {
			$holder_data['phone'] = isset( $posted['pagseguro_assinaturas_card_holder_phone'] ) ? sanitize_text_field( $posted['pagseguro_assinaturas_card_holder_phone'] ) : '';
		}

		return $holder_data;
	}


}