( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'notice-dismiss' ) ) {
			return;
		}

		var notice = e.target.closest( '.cbeacon-notice' );

		if ( ! notice ) {
			return;
		}

		var data = new FormData();
		data.append( 'action', 'cbeacon_dismiss_notice' );
		data.append( 'nonce', notice.getAttribute( 'data-nonce' ) );

		fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
	} );
} )();
