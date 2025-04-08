const upgradeWrapper = document.getElementById( 'edd_sl_upgrade_paths_wrapper' );

// When an upgrade path is cloned, update the price field and labels.
const upgradePathAdded = function ( mutationsList, observer ) {
	for ( var mutation of mutationsList ) {
		if ( mutation.type === 'childList' ) {
			for ( var node of mutation.addedNodes ) {
				if ( node.classList.contains( 'edd-repeatable-upgrade-wrapper' ) ) {
					var priceField = node.querySelector( '.edd-sl-upgrade-price-control' ),
						dataKey = node.getAttribute( 'data-key' );
					priceField.innerHTML = edd_sl.no_prices;

					node.querySelectorAll( 'label' ).forEach( function ( label ) {
						var forAttr = label.getAttribute( 'for' );
						if ( undefined !== forAttr ) {
							var string = forAttr.replace( /(\d+)/, parseInt( dataKey ) );
							label.setAttribute( 'for', string );
						}
					} );

					var prorateField = node.querySelector( '.sl-upgrade-prorate' );
					prorateField.querySelector( 'input' ).checked = false;
				}
			}
		}
	}
};

if ( upgradeWrapper ) {
	const observer = new MutationObserver( upgradePathAdded );
	observer.observe( upgradeWrapper, { childList: true } );
}
