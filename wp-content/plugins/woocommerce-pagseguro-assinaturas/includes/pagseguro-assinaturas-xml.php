<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the SimpleXMLElement class to add CDATA element.
 */
class WC_PagSeguro_Assinaturas_XML extends SimpleXMLElement {

	/**
	 * Extract numbers from a string.
	 *
	 * @param  string $string String where will be extracted numbers.
	 *
	 * @return string
	 */
	protected function get_numbers( $string ) {
		return preg_replace( '([^0-9])', '', $string );
	}

	/**
	 * Add CDATA.
	 *
	 * @param string $string Some string.
	 */
	public function add_cdata( $string ) {
		$node = dom_import_simplexml( $this );
		$no   = $node->ownerDocument;

		$node->appendChild( $no->createCDATASection( trim( $string ) ) );
	}

	/**
	 * Add currency.
	 *
	 * @param string $currency Currency code.
	 */
	public function add_currency( $currency ) {
		$this->addChild( 'currency', $currency );
	}

	/**
	 * Add payment method.
	 *
	 * @param string $method Payment method (creditCard, boleto or eft).
	 */
	public function add_method( $method = 'creditCard' ) {
		$this->addChild( 'method', $method );
	}

	/**
	 * Add reference.
	 *
	 * @param string $reference Payment reference.
	 */
	public function add_reference( $reference ) {
		$this->addChild( 'reference' )->add_cdata( $reference );
	}

	/**
	 * Add receiver email.
	 *
	 * @param string $receiver_email Receiver email.
	 */
	public function add_receiver_email( $receiver_email ) {
		$receiver = $this->addChild( 'receiver' );
		$receiver->addChild( 'email', $receiver_email );
	}

	/**
	 * Add CPF.
	 *
	 * @param string $number Document number.
	 * @param SimpleXMLElement $xml Data.
	 */
	protected function add_cpf( $number, $xml ) {
		$documents = $xml->addChild( 'documents' );
		$document  = $documents->addChild( 'document' );
		$document->addChild( 'type', 'CPF' );
		$document->addChild( 'value', $this->get_numbers( $number ) );
	}

	/**
	 * Add CNPJ.
	 *
	 * @param string $number Document number.
	 * @param SimpleXMLElement $xml Data.
	 */
	protected function add_cnpj( $number, $xml ) {
		$documents = $xml->addChild( 'documents' );
		$document  = $documents->addChild( 'document' );
		$document->addChild( 'type', 'CNPJ' );
		$document->addChild( 'value', $this->get_numbers( $number ) );
	}

	/**
	 * Add order items.
	 *
	 * @param array $_items Order items.
	 */
	public function add_items( $_items ) {
		$items = $this->addChild( 'items' );

		foreach ( $_items as $id => $_item ) {
			$item = $items->addChild( 'item' );

			$item->addChild( 'id', $id + 1 );
			$item->addChild( 'description' )->add_cdata( $_item['description'] );
			$item->addChild( 'amount', $_item['amount'] );
			$item->addChild( 'quantity', $_item['quantity'] );
		}
	}


	/**
	 * Add credit card data for subscription.
	 *
	 * @param WC_Order $order           Order data.
	 * @param string   $credit_card_token Credit card token.
	 * @param array    $installment_data  Installment data (quantity and value).
	 * @param array    $holder_data       Holder data (name, cpf, birth_date and phone).
	 */
	public function add_credit_card_data_for_subscription( $order, $credit_card_token, $holder_data ) {
		
		$paymentMethod = $this->addChild('paymentMethod');
		$paymentMethod->addChild('type', 'CREDITCARD');

		$credit_card = $paymentMethod->addChild( 'creditCard' );

		$credit_card->addChild( 'token', $credit_card_token );

		$holder = $credit_card->addChild( 'holder' );
		$holder->addChild( 'name' )->add_cdata( $holder_data['name'] );


		$document = $holder->addChild( 'document' );
		$document->addChild( 'type', 'CPF' );
		$document->addChild( 'value', $this->get_numbers( $holder_data['cpf'] ) );

		$holder->addChild( 'birthDate', str_replace( ' ', '', $holder_data['birth_date'] ) );
		
		// Remove this. Adding phone based on billing information
		$phone_number = $this->get_numbers( $holder_data['phone'] );
		$phone = $holder->addChild( 'phone' );
		$phone->addChild( 'areaCode', substr( $phone_number, 0, 2 ) );
		$phone->addChild( 'number', substr( $phone_number, 2 ) );

		$billing_address = $credit_card->addChild( 'address' );
		$billing_address->addChild( 'street' )->add_cdata( $order->get_billing_address_1() );
		if ( '' !== $order->get_meta( '_billing_number' ) ) {
			$billing_address->addChild( 'number', $order->get_meta( '_billing_number' ) );
		}
		if ( '' !== $order->get_billing_address_2() ) {
			$billing_address->addChild( 'complement' )->add_cdata( $order->get_billing_address_2() );
		}
		if ( '' !== $order->get_meta( '_billing_neighborhood' ) ) {
			$billing_address->addChild( 'district' )->add_cdata( $order->get_meta( '_billing_neighborhood' ) );
		}
		$billing_address->addChild( 'city' )->add_cdata( $order->get_billing_city() );
		$billing_address->addChild( 'state', $order->get_billing_state() );
		$billing_address->addChild( 'country', 'BRA' );
		$billing_address->addChild( 'postalCode', $this->get_numbers( $order->get_billing_postcode() ) );
	}


	

	/**
	 * Add sender data.
	 *
	 * @param WC_Order $order Order data.
	 * @param string   $hash  Sender hash.
	 */
	public function add_sender_data_for_subscription( $order, $ship_to, $hash = '', $sandbox = 'no' ) {
		$name   = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$sender = $this->addChild( 'sender' );
		
		// $sender->addChild( 'email' )->add_cdata( $order->get_billing_email() );
		if('yes' == $sandbox ) {
			$sender->addChild( 'email' )->add_cdata( 'email@sandbox.pagseguro.com.br' );
		} else {
			$sender->addChild( 'email' )->add_cdata( $order->get_billing_email() );
		}

		$wcbcf_settings = get_option( 'wcbcf_settings' );
		$wcbcf_settings = isset( $wcbcf_settings['person_type'] ) ? intval( $wcbcf_settings['person_type'] ) : 0;

		if ( ( 0 === $wcbcf_settings || 2 === $wcbcf_settings ) && '' !== $order->get_meta( '_billing_cpf' ) ) {
			$this->add_cpf( $order->get_meta( '_billing_cpf' ), $sender );
		} else if ( ( 0 === $wcbcf_settings || 3 === $wcbcf_settings ) && '' !== $order->get_meta( '_billing_cnpj' ) ) {
			$name = $order->get_billing_company();
			$this->add_cnpj( $order->get_meta( '_billing_cnpj' ), $sender );
		} else if ( '' !== $order->get_meta( '_billing_persontype' ) ) {
			if ( 1 === intval( $order->get_meta( '_billing_persontype' ) ) && '' !== $order->get_meta( '_billing_cpf' ) ) {
				$this->add_cpf( $order->get_meta( '_billing_cpf' ), $sender );
			} else if ( 2 === intval( $order->get_meta( '_billing_persontype' ) ) && '' !== $order->get_meta( '_billing_cnpj' ) ) {
				$name = $order->get_billing_company();
				$this->add_cnpj( $order->get_meta( '_billing_cnpj' ), $sender );
			}
		}

		$sender->addChild( 'name' )->add_cdata( $name );

		if ( '' !== $order->get_billing_phone() ) {
			$phone_number = $this->get_numbers( $order->get_billing_phone() );
			$phone        = $sender->addChild( 'phone' );
			$phone->addChild( 'areaCode', substr( $phone_number, 0, 2 ) );
			$phone->addChild( 'number', substr( $phone_number, 2 ) );
		}

		if ( '' != $hash ) {
			$sender->addChild( 'hash', $hash );
		}

		if( '' != $order->get_customer_ip_address() ) {
			$sender->addChild( 'ip', $order->get_customer_ip_address() );
		}


		$type = ( $ship_to ) ? 'shipping' : 'billing';

		if ( '' !== $order->{ 'get_' . $type . '_postcode' }() ) {
			
			$address = $sender->addChild( 'address' );
			$address->addChild( 'street' )->add_cdata( $order->{ 'get_' . $type . '_address_1' }() );

			if ( '' !== $order->get_meta( '_' . $type . '_number' ) ) {
				$address->addChild( 'number', $order->get_meta( '_' . $type . '_number' ) );
			}

			if ( '' !== $order->{ 'get_' . $type . '_address_2' }() ) {
				$address->addChild( 'complement' )->add_cdata( $order->{ 'get_' . $type . '_address_2' }() );
			}

			if ( '' !== $order->get_meta( '_' . $type . '_neighborhood' ) ) {
				$address->addChild( 'district' )->add_cdata( $order->get_meta( '_' . $type . '_neighborhood' ) );
			}

			$address->addChild( 'postalCode', $this->get_numbers( $order->{ 'get_' . $type . '_postcode' }() ) );
			$address->addChild( 'city' )->add_cdata( $order->{ 'get_' . $type . '_city' }() );
			$address->addChild( 'state', $order->{ 'get_' . $type . '_state' }() );
			$address->addChild( 'country', 'BRA' );
		}


	}

	/**
	 * Add sender data.
	 *
	 * @param WC_Order $order Order data.
	 * @param string   $hash  Sender hash.
	 */
	public function add_sender_data_for_payment_method_update( $user, $holder_data, $hash, $sandbox = 'no' ) {

		$customer = new WC_Customer( $user->ID );

		$name   = $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name();
		$sender = $this->addChild( 'sender' );
		
		if('yes' == $sandbox) {
			$sender->addChild( 'email' )->add_cdata( 'email@sandbox.pagseguro.com.br' );
		} else {
			$sender->addChild( 'email' )->add_cdata( $customer->get_billing_email() );
		}
		
		$this->add_cpf(  $holder_data['cpf'], $sender );
		$sender->addChild( 'name' )->add_cdata( $name );

		if ( '' !== $customer->get_billing_phone() ) {
			$phone_number = $this->get_numbers( $customer->get_billing_phone() );
			
		} else {
			$phone_number = $this->get_numbers( $holder_data['phone'] );
		}

		$phone        = $sender->addChild( 'phone' );
		$phone->addChild( 'areaCode', substr( $phone_number, 0, 2 ) );
		$phone->addChild( 'number', substr( $phone_number, 2 ) );

		if ( '' != $hash ) {
			$sender->addChild( 'hash', $hash );
		}

		$ip_address = WC_Geolocation::get_ip_address();
		if( '' != $ip_address ) {
			$sender->addChild( 'ip', $ip_address );
		}

		$address = $sender->addChild( 'address' );
		$address->addChild( 'street' )->add_cdata( $customer->get_billing_address_1() );

		if ( '' !== $customer->get_meta( 'billing_number' ) ) {
			$address->addChild( 'number', $customer->get_meta( 'billing_number' ) );
		}

		if ( '' !== $customer->get_billing_address_2() ) {
			$address->addChild( 'complement' )->add_cdata( $customer->get_billing_address_2() );
		}

		if ( '' !== $customer->get_meta( 'billing_neighborhood' ) ) {
			$address->addChild( 'district' )->add_cdata( $customer->get_meta( 'billing_neighborhood' ) );
		}

		$address->addChild( 'postalCode', $this->get_numbers( $customer->get_billing_postcode() ) );
		$address->addChild( 'city' )->add_cdata( $customer->get_billing_city() );
		$address->addChild( 'state', $customer->get_billing_state() );
		$address->addChild( 'country', 'BRA' );


	}
	/**
	 * Add credit card data for subscription.
	 *
	 * @param WC_Order $order           Order data.
	 * @param string   $credit_card_token Credit card token.
	 * @param array    $installment_data  Installment data (quantity and value).
	 * @param array    $holder_data       Holder data (name, cpf, birth_date and phone).
	 */
	public function add_credit_card_data_for_payment_method_update( $user, $credit_card_token, $holder_data ) {
		
		$customer = new WC_Customer( $user->ID );
		$this->addChild('type', 'CREDITCARD');
		$credit_card = $this->addChild( 'creditCard' );
		$credit_card->addChild( 'token', $credit_card_token );
		$holder = $credit_card->addChild( 'holder' );
		$holder->addChild( 'name' )->add_cdata( $holder_data['name'] );
		$document = $holder->addChild( 'document' );
		$document->addChild( 'type', 'CPF' );
		$document->addChild( 'value', $this->get_numbers( $holder_data['cpf'] ) );
		$holder->addChild( 'birthDate', str_replace( ' ', '', $holder_data['birth_date'] ) );
		$phone_number = $this->get_numbers( $holder_data['phone'] );
		$phone = $holder->addChild( 'phone' );
		$phone->addChild( 'areaCode', substr( $phone_number, 0, 2 ) );
		$phone->addChild( 'number', substr( $phone_number, 2 ) );

		$billing_address = $credit_card->addChild( 'address' );
		$billing_address->addChild( 'street' )->add_cdata( $customer->get_billing_address_1() );
		if ( '' !== $customer->get_meta( 'billing_number' ) ) {
			$billing_address->addChild( 'number', $customer->get_meta( 'billing_number' ) );
		}
		if ( '' !== $customer->get_billing_address_2() ) {
			$billing_address->addChild( 'complement' )->add_cdata( $customer->get_billing_address_2() );
		}
		if ( '' !== $customer->get_meta( 'billing_neighborhood' ) ) {
			$billing_address->addChild( 'district' )->add_cdata( $customer->get_meta( 'billing_neighborhood' ) );
		}
		$billing_address->addChild( 'city' )->add_cdata( $customer->get_billing_city() );
		$billing_address->addChild( 'state', $customer->get_billing_state() );
		$billing_address->addChild( 'country', 'BRA' );
		$billing_address->addChild( 'postalCode', $this->get_numbers( $customer->get_billing_postcode() ) );
	
	}

	/**
	 * Render the formated XML.
	 *
	 * @return string
	 */
	public function render() {
		$node = dom_import_simplexml( $this );
		$dom  = $node->ownerDocument;
		$dom->formatOutput = true;

		return $dom->saveXML();
	}
	
}