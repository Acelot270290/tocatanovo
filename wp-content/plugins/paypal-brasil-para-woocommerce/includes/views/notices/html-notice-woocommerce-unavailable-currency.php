<?php
/**
 * WooCommerce Unavailable Currency.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $woocommerce;
?>

<div class="error">
    <p>
		<?php echo sprintf( __( '<strong>PayPal Brasil para WooCommerce</strong> só é compatível com as moedas %s. Verifique suas configurações <a href="%s">aqui</a>.', 'paypal-brasil-para-woocommerce' ), implode( ', ', PayPal_Brasil::get_allowed_currencies() ), self_admin_url( 'admin.php?page=wc-settings' ) ); ?>
    </p>
</div>