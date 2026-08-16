(function( $ ) {
	'use strict';

	window.SilverArrow = window.SilverArrow || {};

	window.SilverArrow.trackConversion = function( goalId ) {
		if ( typeof silverArrowData === 'undefined' || ! silverArrowData.restUrl ) {
			return;
		}

		var payload = {
			goal_id: goalId || 'generic_cta'
		};

		if ( navigator.sendBeacon ) {
			var blob = new Blob( [ JSON.stringify( payload ) ], { type: 'application/json' } );
			navigator.sendBeacon( silverArrowData.restUrl, blob );
		} else {
			fetch( silverArrowData.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': silverArrowData.nonce || ''
				},
				body: JSON.stringify( payload ),
				keepalive: true
			} ).catch( function() {} );
		}
	};

	$( function() {
		// Event delegation for CTA buttons, links, or custom converters
		$( document ).on( 'click', '[data-sa-convert], .sa-convert', function() {
			var goalId = $( this ).data( 'sa-convert' ) || $( this ).attr( 'id' ) || 'cta_click';
			window.SilverArrow.trackConversion( goalId );
		} );
	} );

})( jQuery );
