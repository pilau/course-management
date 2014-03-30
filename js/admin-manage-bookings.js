/* JS for all plugin pages */


/* Trigger when DOM has loaded */
jQuery( document ).ready( function( $ ) {

	// Submit conditions
	$( 'form#pcm-bookings' ).on( 'submit', function( e ) {
		var submit = true;
		var el = $( this );
		var clicked = $( 'input[type=submit].clicked', el );

		if ( clicked.hasClass( 'needs-checked' ) && ! el.find( 'input[type=checkbox]:checked' ).length ) {
			alert( 'Please select something by checking some of the checkboxes.' );
			submit = false;
		}

		return submit;
	});

});


