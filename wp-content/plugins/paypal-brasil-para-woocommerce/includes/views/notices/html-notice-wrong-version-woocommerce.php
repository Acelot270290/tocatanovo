<?php
/**
 * Missing WooCommerce notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $woocommerce;
?>

<div class="error">
    <p>
        <strong><?php esc_html_e( 'PayPal Brasil para WooCommerce', 'paypal-brasil-para-woocommerce' ); ?></strong> <?php echo esc_html( sprintf( __( 'depende da versão do WooCommerce 3.6 ou superior para funcionar! Você está utilizando a versão %s. Por favor atualize.', 'paypal-brasil-para-woocommerce' ), $woocommerce->version ) ); ?>
    </p>
</div>