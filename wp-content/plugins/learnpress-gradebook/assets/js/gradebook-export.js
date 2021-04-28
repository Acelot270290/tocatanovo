;(function($){

	$(document).ready( function(){
		$('input[name="date-from"], input[name="date-to"]').datepicker({
			dateFormat: "yy-mm-dd"
		});
		$('input#clear-submit').click(function(event){
			$('#post-search-input, input[name="date-from"], input[name="date-to"]').val('');
		});
		function ajax_gradebook_export_to_csv( data ) {

			if( typeof data.action == "undefined" ) {
				return;
			}

			$.post( ajaxurl, data, function( response ) {
				if( typeof response.step == "undefined" ) {
					return;
				}
				change_gradebook_progress_bar( response );
				if( response.step == 'done' ) {
					// ajax_gradebook_export_to_csv( response );
					var csvhref = ajaxurl+'?'+jQuery.param( response );
					document.location.href = csvhref;
				} else {
					ajax_gradebook_export_to_csv( response );
				}
			}, 'json');
		}



		$('a.gradebook-export-to-csv').click( function( even ) {
			even.preventDefault();
			// disable export button
			if($(this).attr('data-status')!=''){
				return;
			};
			if( $(this).hasClass( 'disabled' ) ){
				return;
			}
			$(this).attr( 'data-status', 'processing' );
			$(this).addClass( 'disabled' );
			// show progress bar
			$('.gradebook-export.progress-wrap').show('slow');
			// caculate progressbar
			var data = {
				'action': 'gradebook_export',
				'step': 'init',
				'href': $(this).attr('href')
			};
			ajax_gradebook_export_to_csv(data);
		});


		function change_gradebook_progress_bar( response ) {
			if( typeof response.step !== "undefined" && response.step == 'done' ){
				$( '.gradebook-export.progress-bar' ).css('width','100%');
				var completed_txt = $( '.gradebook-export.progress-bar .process-txt' ).attr( 'data-txtcompleted' );
				$('.gradebook-export.progress-bar .process-txt').text( completed_txt );
				$('a.gradebook-export-to-csv').attr( 'data-status', '' );
				$('a.gradebook-export-to-csv').removeClass('disabled');
			} else if( typeof response.page !== "undefined" 
				&& typeof response.total !== "undefined" 
					&& typeof response.limit !== "undefined") {
				var page 	= parseInt(response.page);
				var total 	= parseInt(response.total);
				var limit 	= parseInt(response.limit);
				if( page >= 2 ) {
					var percent = ( ( page - 1 ) * limit ) * 100 / total;
					$('.gradebook-export.progress-bar').css( 'width', percent + '%' );
				}
			}
			
		}


	});

})(jQuery);