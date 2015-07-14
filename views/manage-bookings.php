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

// Any user meta fields to be used?
$pcm_usermeta_fields = apply_filters( 'pcm_manage_bookings_usermeta_fields', array() );
$pcm_usermeta_selected = array();
if ( $pcm_usermeta_fields ) {
	// Initialize filters
	foreach ( $pcm_usermeta_fields as $key => $label ) {
		$pcm_usermeta_selected[ $key ] = isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : '';
	}
}

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

		// Get any user meta values
		$pcm_usermeta_values = array();
		if ( $pcm_usermeta_fields ) {
			foreach ( $pcm_usermeta_fields as $key => $label ) {
				$pcm_usermeta_values[ $key ] = $wpdb->get_col( $wpdb->prepare("
					SELECT		DISTINCT meta_value
					FROM		$wpdb->usermeta
					WHERE		meta_key	= %s
					AND 		meta_value	<> ''
					AND 		meta_value	IS NOT NULL
					ORDER BY	meta_value ASC
				", $key ) );
			}
		}

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
		$pcm_course_query_from = " $wpdb->usermeta AS um1 ";
		$pcm_course_query_filters = '';
		$pcm_course_query_prepare_vars = array();
		if ( $pcm_user_id ) {
			$pcm_course_query_filters = " AND um1.user_id = %d ";
			$pcm_course_query_prepare_vars[] = $pcm_user_id;
		}
		if ( $pcm_usermeta_fields ) {
			$i = 2;
			foreach ( $pcm_usermeta_fields as $key => $label ) {
				if ( $pcm_usermeta_selected[ $key ] ) {
					$pcm_course_query_from .= " INNER JOIN $wpdb->usermeta AS um$i ON um1.user_id = um$i.user_id ";
					$pcm_course_query_filters .= " AND ( um$i.meta_key = %s AND um$i.meta_value = %s ) ";
					$pcm_course_query_prepare_vars[] = $key;
					$pcm_course_query_prepare_vars[] = $pcm_usermeta_selected[ $key ];
					$i++;
				}
			}
		}
		$pcm_sql = "
			SELECT		um1.meta_value, um1.user_id
			FROM		$pcm_course_query_from
			WHERE		um1.meta_key	= 'pcm-courses'
			$pcm_course_query_filters
			ORDER BY	um1.umeta_id ASC
		";
		//echo '<pre>'; print_r( $pcm_sql ); echo '</pre>'; exit;

		// Get user course details
		if ( $pcm_course_query_prepare_vars ) {
			$pcm_all_user_courses = $wpdb->get_results( $wpdb->prepare( $pcm_sql, $pcm_course_query_prepare_vars ) );
		} else {
			$pcm_all_user_courses = $wpdb->get_results( $pcm_sql );
		}
		//echo '<pre>'; print_r( $pcm_all_user_courses ); echo '</pre>'; exit;

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
						case 'new-status':
							echo '<p>' . __( 'Booking(s) status changed successfully.', $PCM->plugin_slug ) . '</p>';
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
				<div class="filters-main">
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
							<?php foreach ( array_merge( array( 'all' ), $PCM->booking_statuses ) as $pcm_booking_status_name ) { ?>
								<option value="<?php echo $pcm_booking_status_name; ?>"<?php selected( $pcm_booking_status, $pcm_booking_status_name ); ?>><?php echo ucfirst( $pcm_booking_status_name ); ?></option>
							<?php } ?>
						</select>
					</div>
					<div>
						<input type="hidden" name="post_type" value="pcm-course-instance">
						<input type="hidden" name="page" value="pcm-manage-bookings">
						<input type="submit" value="Update list" class="button">
					</div>
				</div>
				<div class="filters-usermeta">
					<?php
					foreach ( $pcm_usermeta_fields as $key => $label ) {
						?>
						<div class="filter">
							<label for="<?php echo $key; ?>"><?php echo $label; ?></label>
							<select name="<?php echo $key; ?>" id="pcm-bookings-<?php echo $key; ?>"<?php if ( count( $pcm_usermeta_values[ $key ] ) > 25 ) echo ' class="pcm-combobox"'; ?>>
								<option value=""<?php selected( $pcm_usermeta_selected[ $key ], '' ); ?>><?php _e( 'All', $PCM->plugin_slug ) ?></option>
								<?php foreach ( $pcm_usermeta_values[ $key ] as $pcm_usermeta_value ) { ?>
									<option value="<?php echo $pcm_usermeta_value; ?>"<?php selected( $pcm_usermeta_selected[ $key ], $pcm_usermeta_value ); ?>><?php echo $pcm_usermeta_value; ?></option>
								<?php } ?>
							</select>
						</div>
					<?php
					}
					?>
				</div>
			</form>

			<?php if ( $pcm_bookings ) { ?>

				<form action="" method="post" id="pcm-bookings" class="pcm-form">

					<table class="pcm-table">

						<tr>
							<?php if ( ! $pcm_user_id ) { ?>
								<th scope="col"><?php _e( 'User', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php if ( $pcm_usermeta_fields ) { ?>
								<?php foreach ( $pcm_usermeta_fields as $key => $label ) { ?>
									<?php if ( ! $pcm_usermeta_selected[ $key ] ) { ?>
										<th scope="col"><?php echo $label; ?></th>
									<?php } ?>
								<?php } ?>
							<?php } ?>
							<?php if ( ! $pcm_course_id ) { ?>
								<th scope="col"><?php _e( 'Course', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php if ( ! $pcm_course_instance_id ) { ?>
								<th scope="col"><?php _e( 'Instance', $PCM->plugin_slug ); ?></th>
							<?php } ?>
							<?php do_action( 'pcm_manage_bookings_extra_cols_headings' ); ?>
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
							$pcm_usermeta = get_user_meta( $pcm_booking['user_id'] );

							// Format: {course_instance_id}|{user_id}
							$pcm_item_id = $pcm_booking['details']['course_instance_id'] . '|' . $pcm_booking['user_id'];

							?>

							<tr class="<?php if ( $alt ) echo 'alt'; ?>">
								<?php if ( ! $pcm_user_id ) { ?>
									<td><?php echo $pcm_userinfo->display_name; ?></td>
								<?php } ?>
								<?php if ( $pcm_usermeta_fields ) { ?>
									<?php foreach ( $pcm_usermeta_fields as $key => $label ) { ?>
										<?php if ( ! $pcm_usermeta_selected[ $key ] ) { ?>
											<td><?php echo $pcm_usermeta[ $key ][0]; ?></td>
										<?php } ?>
									<?php } ?>
								<?php } ?>
								<?php if ( ! $pcm_course_id ) { ?>
									<td><?php echo $this->multiple_post_titles( $pcm_booking['details']['course_id'] ); ?></td>
								<?php } ?>
								<?php if ( ! $pcm_course_instance_id ) { ?>
									<td><?php echo get_the_title( $pcm_booking['details']['course_instance_id'] ); ?></td>
								<?php } ?>
								<?php do_action( 'pcm_manage_bookings_extra_cols_cells', $pcm_userinfo, $pcm_usermeta, $pcm_booking ); ?>
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

							<input type="submit" name="delete" value="<?php _e( 'Delete all checked', $PCM->plugin_slug ); ?>" class="button-primary needs-checked pcm-confirm">&nbsp;&nbsp;&nbsp;&nbsp;

							<?php if ( $pcm_booking_status != 'all' ) { ?>
								<label for="pcm-new-status"><?php _e( 'New status:' ); ?></label>
								<?php
								// If not admin, narrow down possible statuses that can be switched to
								$pcm_can_change_status_to = $PCM->booking_statuses;
								if ( ! current_user_can( 'update_core' ) ) {
									if ( ( $key = array_search( $pcm_booking_status, $pcm_can_change_status_to ) ) !== false ) {
										unset( $pcm_can_change_status_to[ $key ] );
									}
									if ( ! in_array( $pcm_booking_status, array( 'pending', 'denied', 'completed' ) ) && ( $key = array_search( 'approved', $pcm_can_change_status_to ) ) !== false ) {
										unset( $pcm_can_change_status_to[ $key ] );
									}
									if ( $pcm_booking_status != 'approved' && ( $key = array_search( 'completed', $pcm_can_change_status_to ) ) !== false ) {
										unset( $pcm_can_change_status_to[ $key ] );
									}
									if ( $pcm_booking_status == 'completed' && ( $key = array_search( 'denied', $pcm_can_change_status_to ) ) !== false ) {
										unset( $pcm_can_change_status_to[ $key ] );
									}
								}
								?>
								<select name="new-status" id="pcm-new-status">
									<?php foreach ( $pcm_can_change_status_to as $pcm_booking_status_name ) { ?>
										<option value="<?php echo $pcm_booking_status_name; ?>"><?php echo ucfirst( $pcm_booking_status_name ); ?></option>
									<?php } ?>
								</select>&nbsp;&nbsp;
								<input type="submit" name="change-status" value="<?php _e( 'Change all checked to this status', $PCM->plugin_slug ); ?>" class="button-primary needs-checked pcm-change-status">
								&nbsp;&nbsp;<label for="pcm-suppress-notifications"><input type="checkbox" name="suppress-notifications" id="pcm-suppress-notifications" value="1"> <?php _e( 'Suppress notifications', $PCM->plugin_slug ); ?></label>
							<?php } ?>

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

				<div>
					<h3><?php _e( 'Format', $PCM->plugin_slug ) ?></h3>
					<?php $format_default = apply_filters( 'pcm_bookings_email_default_format', 'text' ); ?>
					<label for="pcm-email-format-text"><input type="radio" name="email-format" id="pcm-email-format-text" value="text"<?php checked( $format_default, 'text' ); ?>> <?php _e( 'Plain text', $PCM->plugin_slug ); ?></label>&nbsp;&nbsp;
					<label for="pcm-email-format-html"><input type="radio" name="email-format" id="pcm-email-format-html" value="html"<?php checked( $format_default, 'html' ); ?>> <?php _e( 'HTML', $PCM->plugin_slug ); ?></label>
					<p>If set to <code>HTML</code>, paragraph tags will be added automatically.</p>
				</div>

				<?php if ( function_exists( 'slt_cf_file_select_button' ) ) { ?>
					<div>
						<h3><label for="pcm-email-attachment"><?php _e( 'Attachment', $PCM->plugin_slug ) ?></label></h3>
						<?php slt_cf_file_select_button( 'email-attachment', 0, __( 'Select file', $PCM->plugin_slug ), 'thumbnail', false, false  ); ?>
						<p class="description"><?php _e( 'If for some reason this button doesn\'t work, go to the Media Library, switch to list view if it\'s in grid view, copy the file\'s numeric ID from the column towards the left, then paste it in here:' ); ?></p>
						<label for="pcm-email-attachment-manual"><?php _e( 'File ID:', $PCM->plugin_slug ); ?> <input type="text" name="email-attachment-manual" id="pcm-email-attachment-manual" class="regular-text"></label>
					</div>
				<?php } ?>

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
