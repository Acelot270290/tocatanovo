<?php
/**
 * Admin View: Notice - Email missing
 *
 * @package WooCommerce_PagSeguro_Assinaturas/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="notice error wc-ps-assinaturas-notice is-dismissible">
	<p><strong><?php echo __( 'PagSeguro Recorrente:','woocommerce-pagseguro-assinaturas'); ?></strong> <?php echo __('Please configure notifications on your Pagseguro account for the plugin to work properly. You can close this notice once that\'s done.', 'woocommerce-pagseguro-assinaturas' ); ?> <a href="https://wcpagsegurorecorrente.com.br/product/woocommerce-pagseguro-recorrente/" target="_blank"><?php _e( 'Learn more', 'woocommerce-pagseguro-assinaturas'); ?></a>
	</p>
</div>
<script>
	jQuery(document).on( 'click', '.wc-ps-assinaturas-notice .notice-dismiss', function() {
	    jQuery.ajax({
	        url: ajaxurl,
	        data: {
	            action: 'wc_ps_assinaturas_dismiss_notice'
	        }
	    });
	});
</script>