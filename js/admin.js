/* JS for all plugin pages */


/* Trigger when DOM has loaded */
jQuery( document ).ready( function( $ ) {
	var cacb = $( '#pcm-select-all' );
	var pcb = $( '.pcm-combobox' );

	// Filter comboboxes
	pcb.each( function() {
		var el = $( this );
		if ( el.find( 'option' ).length > 10 ) {
			el.combobox();
		}
	});

	// Confirm
	$( '.pcm-confirm' ).on( 'click', function() {
		return confirm( 'Are you sure?' );
	});

	// Check all checkboxes
	cacb.on( 'change', function() {
		$( 'table.pcm-table td input[type=checkbox]' ).attr( 'checked', cacb.is( ':checked' ) );
	});

	// For when we to record which submit button was clicked submitting a form
	$( 'form.pcm-form' ).on( 'click', 'input[type=submit]', function() {
		$( this ).addClass( 'clicked' );
	});

});


/**
 * Validate an email address
 *
 * @link	http://stackoverflow.com/a/2507043/1087660
 * @param	email
 * @returns {boolean}
 */
function pcm_is_email( email ) {
	var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	return regex.test( email );
}
