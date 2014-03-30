<?php

/**
 * Represents the view for the send invitations page
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor <steve@sltaylor.co.uk>
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */
global $wpdb;
$PCM = Pilau_Course_Management::get_instance();

// Get course instances
$pcm_course_instances_args = array(
	'post_type'			=> 'pcm-course-instance',
	'posts_per_page'	=> -1,
);
if ( function_exists( 'slt_cf_field_key' ) ) {
	$pcm_course_instances_args['meta_key'] = slt_cf_field_key( 'pcm-course-date-start' );
	$pcm_course_instances_args['meta_value'] = date( 'Y/m/d' );
	$pcm_course_instances_args['meta_compare'] = '>';
	$pcm_course_instances_args['orderby'] = 'meta_value';
	$pcm_course_instances_args['order'] = 'ASC';
}
$pcm_course_instances = get_posts( apply_filters( 'pcm_send_invitations_course_instances_args', $pcm_course_instances_args ) );

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php if ( isset( $_GET['msg'] ) ) { ?>
		<div id="message" class="<?php if ( $_GET['msg'] == 'no-invitees' ) { echo 'error'; } else { echo 'updated'; } ?>">
			<?php
			switch ( $_GET['msg'] ) {
				case 'sent':
					echo '<p>' . __( 'The invitations have been successfully sent.', $PCM->plugin_slug ) . '</p>';
					break;
				case 'no-invitees':
					echo '<p>' . __( 'Please enter some invitees to send the invitation to.', $PCM->plugin_slug ) . '</p>';
					break;
				case 'template-updated':
					echo '<p>' . __( 'The template has been updated.', $PCM->plugin_slug ) . '</p>';
					break;
			}
			?>
		</div>
	<?php } else { ?>
		<p><strong>NOTE:</strong> You should make any changes to the invitation template (below) <em>before</em> building your invite list.</p>
	<?php } ?>

	<form action="" method="post" id="pcm-send-invitations" class="pcm-form">

		<div>
			<h3><label for="pcm-course-id"><?php _e( 'Select course', $PCM->plugin_slug ); ?></label></h3>
			<select name="course-id" id="pcm-course-id">
				<?php foreach ( $pcm_course_instances as $pcm_course_instance ) { ?>
					<option value="<?php echo $pcm_course_instance->ID; ?>">
						<?php
						echo apply_filters( 'the_title', $pcm_course_instance->post_title );
						if ( function_exists( 'slt_cf_all_field_values' ) ) {
							$pcm_metadata = slt_cf_all_field_values( 'post', $pcm_course_instance->ID );
							echo ' (' . $PCM->format_course_date( $pcm_metadata['pcm-course-date-start'],$pcm_metadata['pcm-course-date-end'] ) . ')';
						}
						?>
					</option>
				<?php } ?>
			</select>
		</div>

		<h3><?php _e( 'Invite list', $PCM->plugin_slug ); ?></h3>
		<ul class="invitees">
			<li class="new-invitee">
				<label for="pcm-invitee-name"><?php _e( 'Name', $PCM->plugin_slug ); ?></label>
				<input type="text" class="regular-text" name="invitee-name" id="pcm-invitee-name">
				<label for="pcm-invitee-email"><?php _e( 'Email', $PCM->plugin_slug ); ?></label>
				<input type="text" class="regular-text" name="invitee-email" id="pcm-invitee-email">
				<input type="button" class="button-secondary" value="<?php _e( 'Add invitee', $PCM->plugin_slug ); ?>">
			</li>
		</ul>

		<div class="buttons">
			<?php wp_nonce_field( 'send-invitations' ); ?>
			<input type="hidden" name="pcm-admin-form" value="send-invitations">
			<?php /*
			<input type="button" id="pcm-preview-invitation" value="<?php _e( 'Preview invitation email' ); ?>" class="button-secondary">&nbsp;&nbsp;&nbsp;
			*/ ?>
			<input type="submit" value="<?php _e( 'Send invitations' ); ?>" class="button-primary">
		</div>

	</form>

	<hr>

	<h2><?php _e( 'Invitation email template' ); ?></h2>

	<form action="" method="post" id="pcm-invitation-template" class="pcm-form">

		<div>
			<h3><label for="pcm-invitation-template-copy"><?php _e( 'Template copy', $PCM->plugin_slug ); ?></label></h3>
			<textarea class="regular-text copy" name="invitation-template-copy" id="pcm-invitation-template-copy"><?php echo esc_textarea( get_option( 'pcm_invitation_template' ) ); ?></textarea>
			<p class="description"><?php $PCM->available_email_placeholders(); ?></p>
		</div>

		<div class="buttons">
			<?php wp_nonce_field( 'invitation-template' ); ?>
			<input type="hidden" name="pcm-admin-form" value="invitation-template">
			<input type="submit" value="<?php _e( 'Update template' ); ?>" class="button-primary">
		</div>

	</form>

</div>

<div id="pcm-overlay"></div>
<div id="pcm-invitation-preview"></div>
