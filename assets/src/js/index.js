import "./upgrade-paths-clone.js";

jQuery(document).ready(function ($) {

	// Show/Hide the Advanced settings for variable prices for Software Licensing according to whether Licensing is enabled
	$( '#edd_license_enabled' )
		.on( 'change', function() {
			$( '.edd-custom-price-option-section--software-licensing' ).toggle( $( this ).is( ':checked' ) );
		} )
		.trigger( 'change' );
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
	} );

	$('input#edd_sl_disable_renewal_discount').on( 'change', function() {
		var checked = $(this).is(':checked');
		var target  = $('#edd_sl_renewal_discount');
		if ( checked ) {
			target.prop('readonly', 'readonly' ).prop('disabled','disabled');
		} else {
			target.removeProp('readonly').removeProp('disabled');
		}
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
				success: function ( prices ) {
					var parent  = $this.parents( '.edd-form-group' ),
						control = parent.next().find( '.edd-form-group__control' );

					if( '' == prices ) {
						control.html( edd_sl.no_prices );
					} else {

						var prev = parent.next().find( '.edd-sl-upgrade-path-price-id' ),
							key = $this.parents( '.edd_repeatable_row' ).data( 'key' ),
							name = 'edd_sl_upgrade_paths[' + key + '][price_id]',
							id = 'edd_sl_upgrade_paths_' + key + '_price_id';

						prices = prices.replace( 'name="edd_price_option"', 'name="' + name + '" id="' + id + '"' );
						prev.remove();
						control.html( prices );
					}
				}
			}).fail(function (data) {
				if ( window.console && window.console.log ) {
					console.log( data );
				}
			});

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

	$('.edd-sl-license-exp-date, #license_price_id').on('change', function() {
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

	$('#edd-sl-regenerate-key').on( 'click', function(e) {

		var response = confirm( edd_sl.regenerate_notice );
		if ( response == false ) {
			return;
		}

		var license_key   = $('#license-key');
		var target        = $(this);
		target.css('color', '#999').css('pointer-events', 'none');

		var postData = {
			action : 'edd_sl_regenerate_license',
			license_id : target.data('license-id'),
			nonce : target.data('nonce')
		};

		$.ajax({
			type: "POST",
			data: postData,
			dataType: 'json',
			url: ajaxurl,
			success: function (response) {
				if ( response.success ) {
					license_key.fadeTo('fast', '.1', function(){
						license_key.text(response.key).fadeTo('fast', '1', function(){
							target.css('color', '').css('pointer-events', '');
						});
					});
				} else {
					target.css('color', '').css('pointer-events', '');
				}
			}
		}).fail(function (data) {
			if ( window.console && window.console.log ) {
				console.log( data );
			}
		});

		return false;
	});

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

	$( '#edd_readme_cache button' ).on( 'click', function( e ) {
		e.preventDefault();
		var postData = {
			action: 'edd_sl_clear_readme',
			download_id: edd_sl.download,
			nonce: edd_sl.readme_nonce,
		};

		$.ajax( {
			type: "POST",
			data: postData,
			url: ajaxurl,
			success: function ( response ) {
				var css_class = 'error inline';
				if ( response.success ) {
					css_class = 'updated inline';
				}

				$( '#edd_readme_cache' ).empty()
					.append( $( '<div />', {
						class: css_class,
					} ).append( $( '<p />', {
						text: response.data.message,
					} )
				) );
			}
		} );
	} );

	$('.edd-sl-license-card-notices input[type="submit"]').on('click', function(e) {
		e.preventDefault();

		var submitButton = $( this );
		submitButton.attr('disabled', true);
		submitButton.next('.spinner').css('visibility', 'visible');

		// Remove any error messages.
		var wrapper = $( this ).parents( 'td' );
		wrapper.find( '.notice.error' ).remove();

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
				} else if ( response.message ) {
					submitButton.attr( 'disabled', false );
					submitButton.next('.spinner' ).css( 'visibility', 'hidden' );
					wrapper.append( '<div class="notice error inline"><p>' + response.message + '</p></div>' );
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

	/**
	 * Staged Rollouts
	 */
	$('input#edd_license_enabled, input#edd_sr_rollouts_enabled, input#edd_sr_batch_enabled, input#edd_sr_version_enabled').on( 'change', function() {
		var license_enabled = $('#edd_license_enabled').is(':checked');
		var rollouts_enabled = $('#edd_sr_rollouts_enabled').is(':checked');
		var batch_enabled = $('#edd_sr_batch_enabled').is(':checked');
		var version_enabled = $('#edd_sr_version_enabled').is(':checked');
		var toggled_rows = $('.edd_sr_toggled_row');
		var batch_row = $('.edd_sr_batch_row');
		var version_row = $('.edd_sr_version_row');
		var batch_max = $('#edd_sr_batch_max');

		if ( ! license_enabled ) {
			$('input#edd_sr_rollouts_enabled').prop('disabled', true).trigger('chosen:updated');
			toggled_rows.hide();
			toggled_rows.hide();
			batch_row.hide();
			version_row.hide();
			batch_max.prop('disabled',true);
			return;
		}

		$('input#edd_sr_rollouts_enabled').prop('disabled', false).trigger('chosen:updated');

		if ( ! rollouts_enabled ) {
			$('input#edd_sr_batch_enabled').prop('disabled', true).trigger('chosen:updated');
			$('input#edd_sr_version_enabled').prop('disabled', true).trigger('chosen:updated');
			toggled_rows.hide();
			batch_row.hide();
			version_row.hide();
			batch_max.prop('disabled',true);
			return;
		}

		toggled_rows.show();
		$('input#edd_sr_batch_enabled').prop('disabled', false).trigger('chosen:updated');
		$('input#edd_sr_version_enabled').prop('disabled', false).trigger('chosen:updated');

		if ( ! batch_enabled ) {
			batch_row.hide();
			batch_max.prop('disabled',true);
		} else {
			batch_row.show();
			batch_max.prop('disabled',false);
		}

		if ( ! version_enabled ) {
			version_row.hide();
		} else {
			version_row.show();
		}
	});
});
