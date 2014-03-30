<?php

/**
 * Represents the view for the bookings management
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */
global $wpdb;
$PCM = Pilau_Course_Management::get_instance();

// Initialize filters (mode-independent cos can used when doing send email)
$pcm_user_id = isset( $_REQUEST['user_id'] ) ? $_REQUEST['user_id'] : 0;
$pcm_booking_status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : 'pending';
$pcm_course_id = isset( $_REQUEST['course_id'] ) ? $_REQUEST['course_id'] : 0;
$pcm_course_instance_id = isset( $_REQUEST['course_instance_id'] ) ? $_REQUEST['course_instance_id'] : 0;

// What mode?
if ( isset( $_REQUEST['email'] ) || ( isset( $_REQUEST['pcm-action'] ) && $_REQUEST['pcm-action'] == 'compose-email' ) ) {
	$pcm_mode = 'send-email';
} else {
	$pcm_mode = 'list-bookings';
}

// Initialize according to mode
switch ( $pcm_mode ) {

	case 'list-bookings': {

		// Get users
		$pcm_users = $PCM->get_users();

		// Get courses
		$pcm_courses_args = array(
			'post_type'			=> 'pcm-course',
			'posts_per_page'	=> -1,
			'orderby'			=> 'title'
		);
		$pcm_courses = get_posts( apply_filters( 'pcm_view_bookings_course_filter_args', $pcm_courses_args ) );

		// Get course instances
		$pcm_course_instances_args = array(
			'post_type'			=> 'pcm-course-instance',
			'posts_per_page'	=> -1
		);
		if ( function_exists( 'slt_cf_field_key' ) ) {
			$pcm_course_instances_args['meta_key'] = slt_cf_field_key( 'pcm-course-date-start' );
			$pcm_course_instances_args['orderby'] = 'meta_value';
		}
		$pcm_course_instances = get_posts( apply_filters( 'pcm_view_bookings_course_instance_filter_args', $pcm_course_instances_args ) );

		// Build SQL for course data
		$pcm_user_filter = $pcm_user_id ? " AND user_id = %d " : '';
		$pcm_sql = "
			SELECT		meta_value, user_id
			FROM		$wpdb->usermeta
			WHERE		meta_key	= 'pcm-courses'
			$pcm_user_filter
			ORDER BY	umeta_id ASC
		";

		// Get user course details
		if ( $pcm_user_id ) {
			$pcm_all_user_courses = $wpdb->get_results( $wpdb->prepare( $pcm_sql, $pcm_user_id ) );
		} else {
			$pcm_all_user_courses = $wpdb->get_results( $pcm_sql );
		}

		// Gather booking details
		$pcm_bookings = array();
		if ( $pcm_all_user_courses ) {

			// Loop through all entries (it'll only be one if there's a user filter)
			foreach ( $pcm_all_user_courses as $pcm_user_course_entry ) {

				// Unserialize data
				$pcm_user_course_data = maybe_unserialize( $pcm_user_course_entry->meta_value );

				if ( is_array( $pcm_user_course_data ) ) {

					// Loop through all courses in user's course data
					foreach ( $pcm_user_course_data as $pcm_user_course ) {

						// Include this booking?
						if (
							( $pcm_booking_status == 'all' || $pcm_booking_status == $pcm_user_course['booking_status'] ) &&
							( $pcm_course_id == 0 || in_array( $pcm_course_id, $pcm_user_course['course_id'] ) ) &&
							( $pcm_course_instance_id == 0 || $pcm_course_instance_id == $pcm_user_course['course_instance_id'] )
						) {
							$pcm_bookings[] = array(
								'user_id'			=> $pcm_user_course_entry->user_id,
								'details'			=> $pcm_user_course
							);
						}

					}

				}

			}

		}

		// Filter
		$pcm_bookings = apply_filters( 'pcm_view_bookings', $pcm_bookings );

		break;
	}

	case 'send-email': {

		// Gather recipients
		$pcm_recipient_emails = array();
		$pcm_recipient_ids = array();
		if ( isset( $_POST['bookings'] ) && $_POST['bookings'] ) {

			// Submitted with checkboxes
			foreach ( $_POST['bookings'] as $pcm_booking ) {
				// Format: {course_instance_id}|{user_id}
				$pcm_booking_parts = explode( '|', $pcm_booking );
				$pcm_userdata = get_userdata( $pcm_booking_parts[1] );
				if ( ! in_array( $pcm_userdata->user_email, $pcm_recipient_emails ) ) {
					$pcm_recipient_emails[] = $pcm_userdata->user_email;
					$pcm_recipient_ids[] = $pcm_booking_parts[1];
				}
			}

		} else if ( isset( $_GET['pcm-action'] ) && $_GET['pcm-action'] == 'compose-email' && isset( $_GET['id'] ) && ctype_digit( $_GET['id'] ) ) {

			// From an "email" link
			$pcm_userdata = get_userdata( $_GET['id'] );
			$pcm_recipient_emails[] = $pcm_userdata->user_email;
			$pcm_recipient_ids[] = $_GET['id'];

		}

		// Initialise subject line
		$pcm_subject = __( 'Regarding your CfHE booking', $PCM->plugin_slug );
		if ( $pcm_course_instance_id ) {
			$pcm_subject .= ': ' . get_the_title( $pcm_course_instance_id );
		} else if ( $pcm_course_id ) {
			$pcm_subject .= ': ' . get_the_title( $pcm_course_id );
		}

		break;
	}

}

?>

<div class="wrap">

	<?php

	switch ( $pcm_mode ) {

		case 'list-bookings': { ?>

			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<?php if ( isset( $_GET['msg'] ) ) { ?>
				<div id="message" class="updated">
					<?php
					switch ( $_GET['msg'] ) {
						case 'approve':
							echo '<p>' . __( 'Booking(s) approved.', $PCM->plugin_slug ) . '</p>';
							break;
						case 'deny':
							echo '<p>' . __( 'Booking(s) denied.', $PCM->plugin_slug ) . '</p>';
							break;
						case 'complete':
							echo '<p>' . __( 'Booking(s) completed.', $PCM->plugin_slug ) . '</p>';
							break;
						case 'delete':
							echo '<p>' . __( 'Booking(s) deleted.', $PCM->plugin_slug ) . '</p>';
							break;
						case 'sent':
							echo '<p>' . __( 'Email(s) sent.', $PCM->plugin_slug ) . '</p>';
							break;
					}
					?>
				</div>
			<?php } ?>

			<form action="" method="get" id="pcm-bookings-filters" class="pcm-cf">
				<div class="filter">
					<label for="pcm-bookings-user"><?php _e( 'User', $PCM->plugin_slug ); ?></label>
					<select name="user_id" id="pcm-bookings-user" class="pcm-combobox">
						<option value="0"<?php selected( $pcm_user_id, 0 ); ?>><?php _e( 'All', $PCM->plugin_slug ) ?></option>
						<?php foreach ( $pcm_users as $pcm_user ) { ?>
							<option value="<?php echo $pcm_user->ID; ?>"<?php selected( $pcm_user_id, $pcm_user->ID ); ?>><?php echo $pcm_user->display_name; ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="filter">
					<label for="pcm-bookings-course"><?php _e( 'Course', $PCM->plugin_slug ); ?></label>
					<select name="course_id" id="pcm-bookings-course">
						<option value="0"<?php selected( $pcm_course_id, 0 ); ?>><?php _e( 'All', $PCM->plugin_slug ) ?></option>
						<?php foreach ( $pcm_courses as $pcm_course ) { ?>
							<option value="<?php echo $pcm_course->ID; ?>"<?php selected( $pcm_course_id, $pcm_course->ID ); ?>><?php echo apply_filters( 'the_title', $pcm_course->post_title ); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="filter">
					<label for="pcm-bookings-course-instance"><?php _e( 'Instance', $PCM->plugin_slug ); ?></label>
					<select name="course_instance_id" id="pcm-bookings-course-instance" class="pcm-combobox">
						<option value="0"<?php selected( $pcm_course_instance_id, 0 ); ?>><?php _e( 'All', $PCM->plugin_slug ) ?></option>
						<?php foreach ( $pcm_course_instances as $pcm_course_instance ) { ?>
							<option value="<?php echo $pcm_course_instance->ID; ?>"<?php selected( $pcm_course_instance_id, $pcm_course_instance->ID ); ?>><?php
								echo apply_filters( 'the_title', $pcm_course_instance->post_title );
								if ( function_exists( 'slt_cf_all_field_values' ) ) {
									$pcm_metadata = slt_cf_all_field_values( 'post', $pcm_course_instance->ID );
									echo ' (' . $PCM->format_course_date( $pcm_metadata['pcm-course-date-start'],$pcm_metadata['pcm-course-date-end'] ) . ')';
								}
								?></option>
						<?php } ?>
					</select>
				</div>
				<div class="filter">
					<label for="pcm-bookings-status"><?php _e( 'Status', $PCM->plugin_slug ); ?></label>
					<select name="status" id="pcm-bookings-status">
						<?php foreach ( array( 'all', 'pending', 'approved', 'denied', 'completed' ) as $pcm_booking_status_name ) { ?>
							<option value="<?php echo $pcm_booking_status_name; ?>"<?php selected( $pcm_booking_status, $pcm_booking_status_name ); ?>><?php echo ucfirst( $pcm_booking_status_name ); ?></option>
						<?php } ?>
					</select>
				</div>
				<div>
					<input type="hidden" name="post_type" value="pcm-course-instance">
					<input type="hidden" name="page" value="pcm-manage-bookings">
					<input type="submit" value="Update list" class="button">
				</div>
			</form>

			<?php if ( $pcm_bookings ) { ?>

				<form action="" method="post" id="pcm-bookings" class="pcm-form">

					<table class="pcm-table">

						<tr>
							<?php if ( ! $pcm_user_id ) { ?>
								<th scope="col"><?php _e( 'User', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php if ( ! $pcm_course_id ) { ?>
								<th scope="col"><?php _e( 'Course', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php if ( ! $pcm_course_instance_id ) { ?>
								<th scope="col"><?php _e( 'Instance', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<th scope="col"><?php _e( 'Date', $PCM->plugin_slug ); ?></th>
							<th scope="col"><?php _e( 'Submitted', $PCM->plugin_slug ); ?></th>
							<?php if ( $pcm_booking_status == 'all' ) { ?>
								<th scope="col"><?php _e( 'Status', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php if ( current_user_can( $PCM->get_cap( 'manage_bookings' ) ) ) { ?>
								<th scope="col"><?php _e( 'Actions', $PCM->plugin_slug ); ?></th>
								<th scope="col">
									<label class="screen-reader-text" for="pcm-select-all"><?php _e( 'Select all', $PCM->plugin_slug ); ?></label>
									<input id="pcm-select-all" type="checkbox">
								</th>
							<?php } ?>
						</tr>

						<?php $alt = 0; ?>
						<?php foreach ( $pcm_bookings as $pcm_booking ) { ?>

							<?php

							$pcm_userinfo = get_userdata( $pcm_booking['user_id'] );

							// Format: {course_instance_id}|{user_id}
							$pcm_item_id = $pcm_booking['details']['course_instance_id'] . '|' . $pcm_booking['user_id'];

							?>

							<tr class="<?php if ( $alt ) echo 'alt'; ?>">
								<?php if ( ! $pcm_user_id ) { ?>
									<td><?php echo $pcm_userinfo->display_name; ?></td>
								<?php } ?>
								<?php if ( ! $pcm_course_id ) { ?>
									<td><?php echo cfhe_multiple_post_titles( $pcm_booking['details']['course_id'] ); ?></td>
								<?php } ?>
								<?php if ( ! $pcm_course_instance_id ) { ?>
									<td><?php echo get_the_title( $pcm_booking['details']['course_instance_id'] ); ?></td>
								<?php } ?>
								<td><?php
									echo implode( '/', array_reverse( explode( '/', $pcm_booking['details']['course_date_start'] ) ) );
									if ( $pcm_booking['details']['course_date_start'] != $pcm_booking['details']['course_date_end'] ) {
										echo ' &#150; ' . implode( '/', array_reverse( explode( '/', $pcm_booking['details']['course_date_end'] ) ) );
									}
								?></td>
								<td><?php echo date( 'd/m/Y', $pcm_booking['details']['booking_submitted'] ); ?></td>
								<?php if ( $pcm_booking_status == 'all' ) { ?>
									<td><?php echo $pcm_booking['details']['booking_status']; ?></td>
								<?php } ?>
								<?php if ( current_user_can( $PCM->get_cap( 'manage_bookings' ) ) ) { ?>
									<td class="actions">
										<?php
										$pcm_action_links = array();
										$pcm_action_links[] = '<a href="' . wp_nonce_url( add_query_arg( array( 'pcm-action' => 'compose-email', 'id' => $pcm_booking['user_id'] ) ), 'pcm-admin-action' ) . '">' . __( 'Email', $PCM->plugin_slug ) . '</a>';
										if ( $pcm_booking['details']['booking_status'] == 'pending' ) {
											$pcm_action_links[] = '<a href="' . wp_nonce_url( add_query_arg( array( 'pcm-action' => 'approve', 'id' => $pcm_item_id ) ), 'pcm-admin-action' ) . '">' . __( 'Approve', $PCM->plugin_slug ) . '</a>';
											$pcm_action_links[] = '<a href="' . wp_nonce_url( add_query_arg( array( 'pcm-action' => 'deny', 'id' => $pcm_item_id ) ), 'pcm-admin-action' ) . '">' . __( 'Deny', $PCM->plugin_slug ) . '</a>';
										} else if ( $pcm_booking['details']['booking_status'] == 'approved' ) {
											$pcm_action_links[] = '<a href="' . wp_nonce_url( add_query_arg( array( 'pcm-action' => 'complete', 'id' => $pcm_item_id ) ), 'pcm-admin-action' ) . '">' . __( 'Complete', $PCM->plugin_slug ) . '</a>';
										}
										$pcm_action_links[] = '<a class="pcm-confirm" href="' . wp_nonce_url( add_query_arg( array( 'pcm-action' => 'delete', 'id' => $pcm_item_id ) ), 'pcm-admin-action' ) . '">' . __( 'Delete', $PCM->plugin_slug ) . '</a>';
										if ( $pcm_action_links ) {
											echo implode( ' | ', $pcm_action_links );
										}
										?>
									</td>
									<td>
										<label class="screen-reader-text" for="pcm-select-<?php echo $pcm_item_id; ?>"><?php _e( 'Select this booking', $PCM->plugin_slug ); ?></label>
										<input type="checkbox" id="pcm-select-<?php echo $pcm_item_id; ?>" name="bookings[]" value="<?php echo $pcm_item_id; ?>">
									</td>
								<?php } ?>
							</tr>

							<?php $alt = 1 - $alt; ?>
						<?php } ?>

					</table>

					<div class="buttons">

						<?php wp_nonce_field( 'manage-bookings' ); ?>
						<input type="hidden" name="pcm-admin-form" value="manage-bookings">

						<?php if ( current_user_can( $PCM->get_cap( 'manage_bookings' ) ) ) { ?>
							<input type="submit" name="email" value="<?php _e( 'Send email to all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked">&nbsp;&nbsp;
							<?php if ( $pcm_booking_status == 'pending' ) { ?>
								<input type="submit" name="approve" value="<?php _e( 'Approve all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked">&nbsp;&nbsp;
								<input type="submit" name="deny" value="<?php _e( 'Deny all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked">&nbsp;&nbsp;
							<?php } else if ( $pcm_booking_status == 'approved' ) { ?>
								<input type="submit" name="complete" value="<?php _e( 'Complete all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked">&nbsp;&nbsp;
							<?php } ?>
							<input type="submit" name="delete" value="<?php _e( 'Delete all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked pcm-confirm">&nbsp;&nbsp;
						<?php } ?>

					</div>

				</form>

			<?php } else { ?>

				<p><?php _e( 'No bookings match the criteria.', $PCM->plugin_slug ); ?></p>

			<?php } ?>

			<?php break; ?>

		<?php }

		case 'send-email': { ?>

			<h2><?php _e( 'Compose email', $PCM->plugin_slug ); ?></h2>

			<form action="" method="post" id="pcm-send-bookings-email" class="pcm-form">

				<div>
					<p><?php echo '<b>' . __( 'This email will be sent to the following recipients:', $PCM->plugin_slug ) . '</b> ' . implode( ', ', $pcm_recipient_emails ); ?></p>
					<input type="hidden" name="recipients" value="<?php echo implode( ',', $pcm_recipient_ids ); ?>">
				</div>

				<div>
					<h3><label for="pcm-email-subject"><?php _e( 'Subject', $PCM->plugin_slug ) ?></label></h3>
					[<?php echo get_bloginfo( 'name' ); ?>] <input type="text" name="email-subject" id="pcm-email-subject" class="regular-text" value="<?php echo esc_attr( $pcm_subject ); ?>">
				</div>

				<div>
					<h3><label for="pcm-email-message"><?php _e( 'Message', $PCM->plugin_slug ) ?></label></h3>
					<textarea name="email-message" id="pcm-email-message" class="regular-text copy"></textarea>
					<p class="description"><?php $PCM->available_email_placeholders( (boolean) $pcm_course_id, (boolean) $pcm_course_instance_id ); ?></p>
				</div>

				<div class="buttons">
					<?php wp_nonce_field( 'send-bookings-email' ); ?>
					<input type="hidden" name="pcm-admin-form" value="send-bookings-email">
					<?php foreach ( array( 'user_id', 'course_id', 'course_instance_id', 'status' ) as $pcm_filter ) { ?>
						<input type="hidden" name="<?php echo $pcm_filter; ?>" value="<?php echo $_REQUEST[ $pcm_filter ]; ?>">
					<?php } ?>
					<input type="submit" value="<?php _e( 'Send email', $PCM->plugin_slug ); ?>" class="button-primary">
				</div>

			</form>

			<?php break; ?>

		<?php } ?>
	<?php } ?>
</div>
