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

<h2><?php _e( 'Notifications', 'woocommerce-pagseguro-assinaturas' );  ?></h2>
<p><?php _e( 'IMPORTANT! Use the URL below to configure receive payment notifications from Pagseguro.', 'woocommerce-pagseguro-assinaturas' ); ?> <?php _e( 'Copy and Paste the URL in the Transaction Notifications field in your Pagseguro account.', 'woocommerce-pagseguro-assinaturas' ); ?></p>
<p><strong><?php _e( 'URL', 'woocommerce-pagseguro-assinaturas' ); ?></strong>: <?php echo WC()->api_request_url( 'WC_PagSeguro_Assinaturas_Gateway' ); ?></p>

<h4><?php _e('How to configure?', 'woocommerce-pagseguro-assinaturas'); ?></h4>
<p><strong><?php _e( 'Sandbox', 'woocommerce-pagseguro-assinaturas' ); ?></strong>: <?php echo sprintf(__('Log in to your Pagseguro Sandbox account, then go to Integration Profiles > Seller > %s and enter the URL above.', 'woocommerce-pagseguro-assinaturas'), '<a href="https://sandbox.pagseguro.uol.com.br/vendedor/configuracoes.html" target="_blank">'.__('Transaction Notifications', 'woocommerce-pagseguro-assinaturas').'</a>' ); ?></p>
<p><strong><?php _e( 'Live', 'woocommerce-pagseguro-assinaturas' ); ?></strong>: <?php echo sprintf( __('Log in to your Pagseguro account, then go to Preferences > Integrations > %s and enter the URL above.', 'woocommerce-pagseguro-assinaturas' ), '<a href="https://pagseguro.uol.com.br/preferencias/integracoes.jhtml" target="_blank">'.__('Transaction Notifications','woocommerce-pagseguro-assinaturas').'</a>'); ?> <?php _e('You may also find it under Online Sales > Configurations > Integrations > Transaction Notifications.', 'woocommerce-pagseguro-assinaturas'); ?></p>

			