<?php
/**
 * Missing WooCommerce notice.
 *
 * @package WooCommerce_PagSeguro_Assinaturas/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_installed = false;

if ( function_exists( 'get_plugins' ) ) {
	$all_plugins  = get_plugins();
	$is_installed = ! empty( $all_plugins['woocommerce-subscriptions/woocommerce-subscriptions.php'] );
}

?>

<div class="error">
	<p><strong><?php esc_html_e( 'WooCommerce PagSeguro Recorrente', 'woocommerce-pagseguro-assinaturas' ); ?></strong> <?php esc_html_e( 'depends on the last version of WooCommerce Subscriptions to work!', 'woocommerce-pagseguro-assinaturas' ); ?></p>

	<?php if ( $is_installed && current_user_can( 'install_plugins' ) ) : ?>
		<p><a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=woocommerce-subscriptions/woocommerce-subscriptions.php&plugin_status=active' ), 'activate-plugin_woocommerce-subscriptions/woocommerce-subscriptions.php' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Active WooCommerce Subscriptions', 'woocommerce-pagseguro-assinaturas' ); ?></a></p>
	<?php else : ?>
		<p><a href="https://woocommerce.com/products/woocommerce-subscriptions/?aff=10217&cid=1068958" target="_blank" class="button button-primary"><?php esc_html_e( 'Purchase WooCommerce Subscriptions', 'woocommerce-pagseguro-assinaturas' ); ?></a></p>
	<?php endif; ?>
</div>
