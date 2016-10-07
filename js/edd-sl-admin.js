jQuery(document).ready(function ($) {

	$('#sl-retro-type').change( function() {
		var type = $(this).val();
		var target = $('#sl-retro-single-wrapper');
		if ( 'all' == type ) {
			target.hide();
		} else {
			target.show();
			target.find( '.edd-select-chosen' ).css( 'width', 'auto' );
		}
	});

	$('.edd-sl-adjust-limit').click(function(e) {
		e.preventDefault();
		var button = $(this),
			direction = button.data('action'),
			data = {
				action: 'edd_sl_' + direction + '_limit',
				license: button.data('id'),
				download: button.data('download')
			};
		button.toggleClass('button-disabled');
		$.post(ajaxurl, data, function(response, status) {
			button.toggleClass('button-disabled');
			$('#edd-sl-' + data.license + '-limit').text( response );
			$('span[data-parent="' + data.license + '"]').text( response );
		});
	});
	$('#the-list .view_log a').click( function() {
		var data = {
			action: 'edd_sl_get_license_logs',
			license_id: $(this).data('license-id')
		};
		var $thickboxLog = $("#license_log_" + data.license_id );

		// do not fetch logs if we already did so
		if( $thickboxLog.data( 'log-state' ) == 'loaded' ) {
			return;
		}

		// fetch the logs
		$.get( ajaxurl, data, function( response, status ) {
			$('#TB_ajaxContent').html( response );
			$thickboxLog.data( 'log-state', 'loaded' );
		});
	});
	$('select#_edd_product_type, input#edd_license_enabled').on( 'change', function() {
		var product_type = $('#_edd_product_type').val();
		var license_enabled = $('#edd_license_enabled').is(':checked');
		var $toggled_rows = $('.edd_sl_toggled_row');

		if ( ! license_enabled ) {
			$toggled_rows.hide();
			$('#edd_sl_upgrade_paths input, #edd_sl_upgrade_paths select').prop('disabled', true).trigger('chosen:updated');
			return;
		}

		if ( 'bundle' == product_type ) {
			$toggled_rows.hide();
			$toggled_rows.not('.edd_sl_nobundle_row').show();
		} else {
			$toggled_rows.show();
		}

		$('#edd_sl_upgrade_paths input, #edd_sl_upgrade_paths select').prop('disabled', false).trigger('chosen:updated');

	});

	if( ! $('#edd_license_enabled').is(':checked')) {
		$('#edd_sl_upgrade_paths input, #edd_sl_upgrade_paths select').prop('disabled', true).trigger('chosen:updated');
	}

	$('input[name="edd_sl_is_lifetime"]').change( function() {
		var unlimited = $(this).val();
		if ( unlimited == 1 ) {
			$('#edd_license_length_wrapper').hide();
		} else {
			$('#edd_license_length_wrapper').show();
		}
	});

	$('#edit_expiration_is_lifetime').change( function() {
		var checked = $(this).is(':checked');

		if ( checked ) {
			$('#edit_expiration_date').attr('disabled', 'disabled');
		} else {
			$('#edit_expiration_date').removeAttr('disabled');
		}
	});

	$('#edd_sl_upgrade_paths_wrapper').on('change', 'select.edd-sl-upgrade-path-download', function() {
		var $this = $(this), download_id = $this.val();

		if(parseInt(download_id) > 0) {
			var postData = {
				action : 'edd_check_for_download_price_variations',
				download_id: download_id
			};

			$.ajax({
				type: "POST",
				data: postData,
				url: ajaxurl,
				success: function (prices) {

					if( '' == prices ) {
						$this.parent().next().html( edd_sl.no_prices );
					} else {

						var prev = $this.parent().next().find('.edd-sl-upgrade-path-price-id');
						var key  = $this.parent().parent().data('key');
						var name = 'edd_sl_upgrade_paths[' + key + '][price_id]'

						prices = prices.replace( 'name="edd_price_option"', 'name="' + name + '"' );
						prev.remove();
						$this.parent().next().html( prices );
					}
				}
			}).fail(function (data) {
				if ( window.console && window.console.log ) {
					console.log( data );
				}
			});

		}
	});

	$('#edd_sl_upgrade_paths_wrapper').on('DOMNodeInserted', function(e) {
		var target = $(e.target);

		if ( target.is('.edd_repeatable_upload_wrapper')) {
			var price_field = target.find('.pricing');
			price_field.html('');

			var prorate_field = target.find('.sl-upgrade-prorate');
			prorate_field.find('input').attr('checked', false);
		}
	});

});
