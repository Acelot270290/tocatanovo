(function ( $ ) {
	'use strict';

	$( function () {

		/**
		 * Awitch user data for sandbox and production.
		 *
		 * @param {String} checked
		 */
		function pagSeguroAssinaturasSwitchUserData( checked ) {
			var email = $( '#woocommerce_pagseguro_assinaturas_email' ).closest( 'tr' ),
				token = $( '#woocommerce_pagseguro_assinaturas_token' ).closest( 'tr' ),
				weeklyPlan = $( '#woocommerce_pagseguro_assinaturas_weekly_plan' ).closest( 'tr' ),
				monthlyPlan = $( '#woocommerce_pagseguro_assinaturas_monthly_plan' ).closest( 'tr' ),
				bimonthlyPlan = $( '#woocommerce_pagseguro_assinaturas_bimonthly_plan' ).closest( 'tr' ),
				trimonthlyPlan = $( '#woocommerce_pagseguro_assinaturas_trimonthly_plan' ).closest( 'tr' ),
				semiannuallyPlan = $( '#woocommerce_pagseguro_assinaturas_semiannually_plan' ).closest( 'tr' ),
				yearlyPlan = $( '#woocommerce_pagseguro_assinaturas_yearly_plan' ).closest( 'tr' ),
				sandboxEmail = $( '#woocommerce_pagseguro_assinaturas_sandbox_email' ).closest( 'tr' ),
				sandboxToken = $( '#woocommerce_pagseguro_assinaturas_sandbox_token' ).closest( 'tr' ),
				sandboxWeeklyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_weekly_plan' ).closest( 'tr' ),
				sandboxMonthlyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_monthly_plan' ).closest( 'tr' ),
				sandboxbiMonthlyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_bimonthly_plan' ).closest( 'tr' ),
				sandboxtriMonthlyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_trimonthly_plan' ).closest( 'tr' ),
				sandboxSemiannuallyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_semiannually_plan' ).closest( 'tr' ),
				sandboxYearlyPlan = $( '#woocommerce_pagseguro_assinaturas_sandbox_yearly_plan' ).closest( 'tr' );

			if ( checked ) {
				email.hide();
				token.hide();
				weeklyPlan.hide();
				monthlyPlan.hide();
				bimonthlyPlan.hide();
				trimonthlyPlan.hide();
				semiannuallyPlan.hide();
				yearlyPlan.hide();
				sandboxEmail.show();
				sandboxToken.show();
				sandboxWeeklyPlan.show();
				sandboxbiWeeklyPlan.show();
				sandboxMonthlyPlan.show();
				sandboxbiMonthlyPlan.show();
				sandboxtriMonthlyPlan.show();
				sandboxSemiannuallyPlan.show();
				sandboxYearlyPlan.show();
			} else {
				email.show();
				token.show();
				weeklyPlan.show();
				monthlyPlan.show();
				bimonthlyPlan.show();
				trimonthlyPlan.show();
				semiannuallyPlan.show();
				yearlyPlan.show();
				sandboxEmail.hide();
				sandboxToken.hide();
				sandboxWeeklyPlan.hide();
				sandboxbiWeeklyPlan.hide();
				sandboxMonthlyPlan.hide();
				sandboxbiMonthlyPlan.hide();
				sandboxtriMonthlyPlan.hide();
				sandboxSemiannuallyPlan.hide();
				sandboxYearlyPlan.hide();
			}
		}

		pagSeguroAssinaturasSwitchUserData( $( '#woocommerce_pagseguro_assinaturas_sandbox' ).is( ':checked' ) );
		$( 'body' ).on( 'change', '#woocommerce_pagseguro_assinaturas_sandbox', function () {
			pagSeguroAssinaturasSwitchUserData( $( this ).is( ':checked' ) );
		});
	});

}( jQuery ));
