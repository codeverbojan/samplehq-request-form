/* global jQuery, shqfSampleEdit */
jQuery( function ( $ ) {
	// --- Image gallery ---
	const $gallery = $( '#shqf-image-gallery' );

	$( '#shqf-add-images' ).on( 'click', function ( e ) {
		e.preventDefault();
		const frame = wp.media( {
			title: shqfSampleEdit.selectTitle,
			button: { text: shqfSampleEdit.addButton },
			multiple: true,
		} );
		frame.on( 'select', function () {
			const selection = frame.state().get( 'selection' );
			selection.each( function ( attachment ) {
				const data = attachment.toJSON();
				const url =
					data.sizes && data.sizes.thumbnail
						? data.sizes.thumbnail.url
						: data.url;
				// Skip if already in gallery.
				if ( $gallery.find( '[data-id="' + data.id + '"]' ).length ) {
					return;
				}
				const $item = $(
					'<div class="shqf-gallery-item" data-id="' +
						data.id +
						'">' +
						'<img src="' +
						url +
						'" alt="" />' +
						'<button type="button" class="shqf-gallery-remove" aria-label="' +
						shqfSampleEdit.removeLabel +
						'">&times;</button>' +
						'<input type="hidden" name="image_ids[]" value="' +
						data.id +
						'" />' +
						'</div>'
				);
				$gallery.append( $item );
			} );
		} );
		frame.open();
	} );

	$gallery.on( 'click', '.shqf-gallery-remove', function ( e ) {
		e.preventDefault();
		$( this ).closest( '.shqf-gallery-item' ).remove();
	} );

	// --- Custom fields ---
	$( '#shqf-add-custom-field' ).on( 'click', function () {
		const row =
			'<tr class="shqf-cf-row">' +
			'<td><input type="text" name="cf_keys[]" class="widefat" placeholder="' +
			shqfSampleEdit.namePlaceholder +
			'" /></td>' +
			'<td><input type="text" name="cf_values[]" class="widefat" placeholder="' +
			shqfSampleEdit.valuePlaceholder +
			'" /></td>' +
			'<td><button type="button" class="button shqf-cf-remove">&times;</button></td>' +
			'</tr>';
		$( '#shqf-custom-fields tbody' ).append( row );
	} );

	$( '#shqf-custom-fields' ).on( 'click', '.shqf-cf-remove', function () {
		$( this ).closest( 'tr' ).remove();
	} );

	// --- Inline category creation ---
	const $addForm = $( '#shqf-add-category-form' ),
		$addToggle = $( '#shqf-add-cat-toggle' ),
		$addCancel = $( '#shqf-add-cat-cancel' ),
		$addBtn = $( '#shqf-add-cat-btn' ),
		$nameInput = $( '#shqf-new-cat-name' ),
		$parentSel = $( '#shqf-new-cat-parent' ),
		$spinner = $( '#shqf-add-cat-spinner' ),
		$checklist = $( '#shqf-category-checklist' );

	$addToggle.on( 'click', function ( e ) {
		e.preventDefault();
		$addForm.slideDown( 200 );
		$nameInput.focus();
	} );

	$addCancel.on( 'click', function ( e ) {
		e.preventDefault();
		$addForm.slideUp( 200 );
		$nameInput.val( '' );
		$parentSel.val( '0' );
	} );

	$addBtn.on( 'click', function () {
		const name = $.trim( $nameInput.val() ),
			parentId = parseInt( $parentSel.val(), 10 ) || 0;

		if ( ! name ) {
			$nameInput.focus();
			return;
		}

		$addBtn.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );

		wp.apiRequest( {
			path: shqfSampleEdit.catEndpoint,
			method: 'POST',
			data: { name, parent_id: parentId },
		} )
			.done( function ( cat ) {
				// Add to checklist (checked by default).
				const $li = $(
					'<li><label><input type="checkbox" name="categories[]" value="' +
						cat.id +
						'" checked /> ' +
						$( '<span>' ).text( cat.name ).html() +
						'</label></li>'
				);

				if ( parentId > 0 ) {
					// Find parent li and append as nested ul.
					const $parentLi = $checklist
						.find( 'input[value="' + parentId + '"]' )
						.closest( 'li' );
					let $subUl = $parentLi.children( 'ul.categorychecklist' );
					if ( ! $subUl.length ) {
						$subUl = $(
							'<ul class="categorychecklist"></ul>'
						).appendTo( $parentLi );
					}
					$subUl.append( $li );
				} else {
					let $topUl = $checklist.children( 'ul.categorychecklist' );
					if ( ! $topUl.length ) {
						$checklist.empty();
						$topUl = $(
							'<ul class="categorychecklist"></ul>'
						).appendTo( $checklist );
					}
					$topUl.append( $li );
				}

				// Add to parent dropdown for future subcategories.
				$parentSel.append(
					'<option value="' +
						cat.id +
						'">' +
						$( '<span>' ).text( cat.name ).html() +
						'</option>'
				);

				// Reset.
				$nameInput.val( '' );
				$parentSel.val( '0' );
				$nameInput.focus();
			} )
			.fail( function ( resp ) {
				const msg =
					resp.responseJSON && resp.responseJSON.message
						? resp.responseJSON.message
						: shqfSampleEdit.createCatFail;
				window.alert( msg ); // eslint-disable-line no-alert
			} )
			.always( function () {
				$addBtn.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );
			} );
	} );

	// Allow Enter in name field to submit.
	$nameInput.on( 'keypress', function ( e ) {
		if ( e.which === 13 ) {
			e.preventDefault();
			$addBtn.trigger( 'click' );
		}
	} );
} );
