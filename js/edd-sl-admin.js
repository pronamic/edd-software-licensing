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
	$('select#_edd_product_type, input#edd_license_enabled, input#edd_sl_beta_enabled').on( 'change', function() {
		var product_type = $('#_edd_product_type').val();
		var license_enabled = $('#edd_license_enabled').is(':checked');
		var beta_enabled = $('#edd_sl_beta_enabled').is(':checked');
		var $toggled_rows = $('.edd_sl_toggled_row');
		var $beta_toggled_rows = $('.edd_sl_beta_toggled_row');
		var $beta_bundle_row = $('.edd_sl_beta_bundle_row');
		var $beta_no_bundle_row = $('.edd_sl_beta_no_bundle_row');

		if ( ! license_enabled ) {
			$toggled_rows.hide();
			$('#edd_sl_upgrade_paths input, #edd_sl_upgrade_paths select').prop('disabled', true).trigger('chosen:updated');
			return;
		}
		
		if ( ! beta_enabled ) {
			$beta_toggled_rows.hide();
		} else {
			$beta_toggled_rows.show();
		}

		if ( 'bundle' == product_type ) {
			$toggled_rows.hide();
			$toggled_rows.not('.edd_sl_nobundle_row').show();
			$beta_toggled_rows.hide();
			$('#edd_sl_beta_enabled').checked = false;
			$beta_no_bundle_row.hide();
			$beta_bundle_row.show();
		} else {
			$toggled_rows.show();
			$beta_no_bundle_row.show();
			$beta_bundle_row.hide();
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

	$('.edd_sl_upgrade_link').on('click', function() {
		$(this).select();
	});

	$( '#edd-sl-license-delete-confirm' ).change( function() {
		var submit_button = $('#edd-sl-delete-license');

		if ( $(this).prop('checked') ) {
			submit_button.attr('disabled', false);
		} else {
			submit_button.attr('disabled', true);
		}
	});

	$('.edd-sl-edit-license-exp-date').on('click', function(e) {
		e.preventDefault();

		var link = $(this);
		var exp_input = $('input.edd-sl-license-exp-date');

		edd_sl_edit_license_exp_date(link, exp_input);

		$('.edd-sl-license-exp-date').toggle();
	});

	$('.edd-sl-license-exp-date').on('change', function() {
		$('#edd_sl_update_license').fadeIn('fast').css('display', 'inline-block');
	});

	function edd_sl_edit_license_exp_date (link, input) {
		if (link.text() === edd_sl.action_edit) {
			link.data('current-value', input.val());
			link.text(edd_sl.action_cancel);
		} else {
			input.val(link.data('current-value'));
			$('#edd_sl_update_license').fadeOut('fast', function () {
				$(this).css('display', 'none');
			});
			link.text(edd_sl.action_edit);
		}
	}

	$('#edd_sl_send_renewal_notice').on('click', function(e) {
		e.preventDefault();

		if ($(this).text() === edd_sl.send_notice) {
			$('.edd-sl-license-card-notices').fadeIn('fast').css('display', 'table-row');
			$(this).text(edd_sl.cancel_notice);
		} else {
			$('.edd-sl-license-card-notices').fadeOut('fast', function () {
				$('.edd-sl-license-card-notices').css('display', 'none');
			});
			$(this).text(edd_sl.send_notice);
		}
	});

	$('.edd-sl-license-card-notices input[type="submit"]').on('click', function(e) {
		e.preventDefault();

		$(this).attr('disabled', true);
		$(this).next('.spinner').css('visibility', 'visible');

		var postData = {
			action : 'edd_sl_send_renewal_notice',
			license_id : $(this).data('license-id'),
			notice_id : $('#edd_sl_renewal_notice').val()
		};

		$.ajax({
			type: "POST",
			data: postData,
			dataType: 'json',
			url: ajaxurl,
			success: function (response) {
				if ( response.success ) {
					window.location = response.url;
				} else {
					return false;
				}
			}
		}).fail(function (data) {
			if ( window.console && window.console.log ) {
				console.log( data );
			}
		});
		return true;
	});

	// WP 3.5+ uploader
	var file_frame;
	window.formfield = '';

	$( document.body ).on('click', '.edd_upload_banner_button', function(e) {
		e.preventDefault();

		var button = $(this);

		window.formfield = $(this).closest('.edd_sl_banner_container');

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media( {
			frame: 'post',
			state: 'insert',
			title: button.data( 'uploader-title' ),
			button: {
				text: button.data( 'uploader-button-text' )
			},
			multiple: false
		} );

		file_frame.on( 'menu:render:default', function( view ) {
			// Store our views in an object.
			var views = {};

			// Unset default menu items
			view.unset( 'library-separator' );
			view.unset( 'gallery' );
			view.unset( 'featured-image' );
			view.unset( 'embed' );

			// Initialize the views in our view object.
			view.set( views );
		} );

		// When an image is selected, run a callback.
		file_frame.on( 'insert', function() {
			var selection = file_frame.state().get('selection');
			selection.each( function( attachment, index ) {
				attachment = attachment.toJSON();

				window.formfield.find( 'input' ).val( attachment.url );
			});
		});

		// Finally, open the modal
		file_frame.open();
	});

	// WP 3.5+ uploader
	var file_frame;
	window.formfield = '';
});
