<?php
/**
 * Admin View: Notice - Currency not supported.
 *
 * @package WooCommerce_PagSeguro_Assinaturas/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error inline">
	<p><strong><?php _e( 'PagSeguro Disabled', 'woocommerce-pagseguro-assinaturas' ); ?></strong>: <?php printf( __( 'Currency <code>%s</code> is not supported. Works only with Brazilian Real.', 'woocommerce-pagseguro-assinaturas' ), get_woocommerce_currency() ); ?>
	</p>
</div>
