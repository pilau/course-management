<?php

/**
 * Represents the view for the email notifications
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor <steve@sltaylor.co.uk>
 * @license   GPL-2.0+
 * @copyright 2014 Public Life
 */
$pcm_email_notifications = get_option( 'pcm_email_notifications' );

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php if ( isset( $_GET['msg'] ) ) { ?>
		<div id="message" class="updated">
			<?php
			switch ( $_GET['msg'] ) {
				case 'updated':
					echo '<p>' . __( 'Settings updated.', $this->plugin_slug ) . '</p>';
					break;
			}
			?>
		</div>
	<?php } ?>

	<form method="post" action="">

		<h3><?php _e( 'Booking alert' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="pcm-booking-alert"><?php _e( 'Send notification when a booking is made' ); ?></label></th>
					<td><input type="checkbox" name="booking-alert" id="pcm-booking-alert" value="1"<?php checked( $pcm_email_notifications['booking-alert'] ); ?>></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="pcm-booking-email"><?php _e( 'Send to email address' ); ?></label></th>
					<td><input type="text" name="booking-email" id="pcm-booking-email" value="<?php esc_attr_e( $pcm_email_notifications['booking-email'] ); ?>" class="regular-text"></td>
				</tr>
			</tbody>
		</table>

		<?php wp_nonce_field( 'email-notifications' ); ?>

		<p class="submit">
			<input type="hidden" name="pcm-admin-form" value="email-notifications">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
		</p>

	</form>

</div>
