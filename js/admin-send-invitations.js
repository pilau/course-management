/* JS for Send Invitations screen */


/* Trigger when DOM has loaded */
jQuery( document ).ready( function( $ ) {
	var fsi = $( 'form#pcm-send-invitations' );
	var ni = $( 'li.new-invitee', fsi );
	var o = $( '#pcm-overlay' );
	var ip = $( '#pcm-invitation-preview' );

	// Adding an invitee
	ni.find( 'input[type=button]' ).on( 'click', function() {
		var name = $( '#pcm-invitee-name' );
		var email = $( '#pcm-invitee-email' );

		if ( ! name.val() ) {

			alert( 'Please enter a name.' );

		} else if ( ! email.val() ) {

			alert( 'Please enter an email address.' );

		} else if ( ! pcm_is_email( email.val() ) ) {

			alert( 'Please enter a valid email address.' );

		} else {

			ni.before( '<li class="invitee"><input type="text" class="regular-text" readonly="readonly" name="pcm-invitee[]" value="' + name.val() + ' ' + email.val() + '"> <a href="#" title="Remove" class="remove">Remove</a></li>' );
			name.val( '' );
			email.val( '' );

		}

	});

	// Form events
	fsi.on( 'click', 'a.remove', function( e ) {
		e.preventDefault();

		// Removing an invitee
		$( this ).parent().remove();

	}).on( 'click', '#pcm-preview-invitation', function( e ) {

		// Preview invite email
		o.fadeIn();
		ip.fadeIn();

	}).on( 'submit', function( e ) {
		var submit = true;
		var i = $( '.invitee' );

		// Make sure there's an invitee
		if ( ! i.length ) {
			alert( 'Please add at least one invitee.' );
			submit = false;
		}

		return submit;
	});

});