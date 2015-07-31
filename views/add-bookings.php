<?php

/**
 * Represents the view for the add bookings page
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */
global $wpdb, $wp_roles;
$PCM = Pilau_Course_Management::get_instance();
$pcm_admin_user_settings = $PCM->get_admin_user_settings();

// Defaults for options that aren't stored
$pcm_date_select_format = 'Y-m-d';
$pcm_users_select_date_start_timestamp = strtotime( '-5 days' );
$pcm_users_select_date_start = date( $pcm_date_select_format, $pcm_users_select_date_start_timestamp );
$pcm_users_select_date_end_timestamp = time();
$pcm_users_select_date_end = date( $pcm_date_select_format, $pcm_users_select_date_end_timestamp );
if ( ! empty( $_REQUEST['users-select-date-start'] ) ) {
	$pcm_users_select_date_start = $_REQUEST['users-select-date-start'];
	$pcm_users_select_date_start_timestamp = strtotime( $pcm_users_select_date_start );
}
if ( ! empty( $_REQUEST['users-select-date-end'] ) ) {
	$pcm_users_select_date_end = $_REQUEST['users-select-date-end'];
	$pcm_users_select_date_end_timestamp = strtotime( $pcm_users_select_date_end );
}
$pcm_courses_select_year = (int) date( 'Y' );
if ( ! empty( $_REQUEST['courses-select-year'] ) ) {
	$pcm_courses_select_year = $_REQUEST['courses-select-year'];
}
$pcm_courses_select_type = '';
if ( ! empty( $_REQUEST['courses-select-type'] ) ) {
	$pcm_courses_select_type = $_REQUEST['courses-select-type'];
}

// Default arguments for gettings users
// pcm_* arguments are custom
$pcm_users_to_book_args = apply_filters( 'pcm_users_to_book_args', array(
	'role'					=> array( 'subscriber', 'pcm-course-participant' ),
	'orderby'				=> $pcm_admin_user_settings['add-bookings-users-orderby'],
	'order'					=> $pcm_admin_user_settings['add-bookings-users-orderby'] == 'registered' ? 'DESC' : 'ASC',
	'pcm_registered_before'	=> $pcm_users_select_date_end_timestamp,
	'pcm_registered_after'	=> $pcm_users_select_date_start_timestamp,
));
if ( isset( $pcm_users_to_book_args['role'] ) ) {
	if ( ! is_array( $pcm_users_to_book_args['role'] ) ) {
		$pcm_users_to_book_args['role'] = array( $pcm_users_to_book_args['role'] );
	}
} else {
	$pcm_users_to_book_args['role'] = array( '' );
}

// Have to get multiple roles separately
$pcm_user_roles = $pcm_users_to_book_args['role'];
unset( $pcm_users_to_book_args['role'] );
$pcm_users_to_book = array();
foreach ( $pcm_user_roles as $pcm_role ) {
	// Each time, copy args and set single role for query
	$pcm_this_role_args = $pcm_users_to_book_args;
	$pcm_this_role_args['role'] = $pcm_role;
	if ( $pcm_users = get_users( $pcm_this_role_args ) ) {
		$pcm_users_to_book[ $pcm_role ] = $pcm_users;
	}
}

// Get courses
$pcm_courses_to_book_args = array(
	'post_type'			=> 'pcm-course-instance',
	'posts_per_page'	=> -1,
);
if ( function_exists( 'slt_cf_field_key' ) ) {
	$pcm_courses_to_book_args['meta_query'] = array(
		array(
			'key'		=> slt_cf_field_key( 'pcm-course-date-start' ),
			'value'		=> $pcm_courses_select_year . '/',
			'compare'	=> 'LIKE'
		)
	);
	$pcm_courses_to_book_args['meta_key'] = slt_cf_field_key( 'pcm-course-date-start' );
	$pcm_courses_to_book_args['orderby'] = 'meta_value';
	$pcm_courses_to_book_args['order'] = 'ASC';
	if ( $pcm_courses_select_type ) {
		$pcm_courses_to_book_args['meta_query'][] = array(
			'key'		=> slt_cf_field_key( 'pcm-course-type' ),
			'value'		=> $pcm_courses_select_type
		);
	}
}
$pcm_courses_to_book = new WP_Query( apply_filters( 'pcm_courses_to_book_args', $pcm_courses_to_book_args ) );
//echo '<pre>'; print_r( $pcm_courses_to_book ); echo '</pre>'; exit;

?>

<div class="wrap">

	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( isset( $_GET['msg'] ) ) { ?>
		<div id="message" class="updated">
			<?php
			switch ( $_GET['msg'] ) {
				case 'added':
					echo '<p>' . __( 'Booking(s) successfully added.', $PCM->plugin_slug ) . '</p>';
					break;
				case 'problems':
					echo '<p>' . __( 'There were problems with the following booking(s):', $PCM->plugin_slug ) . '</p>';
					echo '<ul>';
					$pcm_problems = explode( '-', $_GET['problems'] );
					foreach ( $pcm_problems as $pcm_problem ) {
						$pcm_problem_parts = explode( '|', $pcm_problem );
						$pcm_problem_user = get_userdata( $pcm_problem_parts[0] );
						echo '<li>User ID ' . $pcm_problem_parts[0] . ' (' . $pcm_problem_user->display_name . '), course instance ID ' . $pcm_problem_parts[1] . ' (' . get_the_title( $pcm_problem_parts[1] ) . '): ';
						switch ( $pcm_problem_parts[2] ) {
							case 'error':
								echo __( 'There was an error with the booking.', $PCM->plugin_slug );
								break;
							case 'already-booked':
								echo __( 'That user already has a booking for that course.', $PCM->plugin_slug );
								break;
						}
						echo '</li>';
					}
					echo '</ul>';
					break;
				case 'updated':
					echo '<p>' . __( 'Options updated.', $PCM->plugin_slug ) . '</p>';
					break;
			}
			?>
		</div>
	<?php } ?>

	<form action="" method="post" id="pcm-add-booking-options" class="pcm-form">

		<h3><?php _e( 'Selection options', $PCM->plugin_slug ); ?></h3>

		<div class="pcm-options-group">
			<div class="pcm-option pcm-option-users-multiple-selection">
				<div class="pcm-option-label">
					<label for="pcm-add-bookings-users-multiple-select"><?php _e( 'Users multiple selection', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<input type="checkbox" name="add-bookings-users-multiple-select" id="pcm-add-bookings-users-multiple-select" value="1"<?php checked( $pcm_admin_user_settings['add-bookings-users-multiple-select'] ); ?>>
				</div>
			</div>
			<div class="pcm-option pcm-option-users-date-start">
				<div class="pcm-option-label">
					<label for="pcm-users-select-date-start"><?php _e( 'User registered date range start', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<input type="date" name="users-select-date-start" id="pcm-users-select-date-start" value="<?php echo $pcm_users_select_date_start; ?>" max="<?php echo date( $pcm_date_select_format ); ?>">
				</div>
			</div>
			<div class="pcm-option pcm-option-users-date-end">
				<div class="pcm-option-label">
					<label for="pcm-users-select-date-end"><?php _e( 'User registered date range end', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<input type="date" name="users-select-date-end" id="pcm-users-select-date-end" value="<?php echo $pcm_users_select_date_end; ?>" max="<?php echo date( $pcm_date_select_format ); ?>">
				</div>
			</div>
			<div class="pcm-option pcm-option-users-order">
				<div class="pcm-option-label">
					<label for="pcm-add-bookings-users-orderby"><?php _e( 'Users ordered by', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<select name="add-bookings-users-orderby" id="pcm-add-bookings-users-orderby">
						<option value="registered"<?php selected( $pcm_admin_user_settings['add-bookings-users-orderby'], 'registered' ); ?>><?php _e( 'Date registered', $PCM->plugin_slug ); ?></option>
						<option value="display_name"<?php selected( $pcm_admin_user_settings['add-bookings-users-orderby'], 'display_name' ); ?>><?php _e( 'Name', $PCM->plugin_slug ); ?></option>
					</select>
				</div>
			</div>
		</div>

		<div class="pcm-options-group">
			<div class="pcm-option pcm-option-courses-multiple-selection">
				<div class="pcm-option-label">
					<label for="pcm-add-bookings-courses-multiple-select"><?php _e( 'Courses multiple selection', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<input type="checkbox" name="add-bookings-courses-multiple-select" id="pcm-add-bookings-courses-multiple-select" value="1"<?php checked( $pcm_admin_user_settings['add-bookings-courses-multiple-select'] ); ?>>
				</div>
			</div>
			<div class="pcm-option pcm-option-courses-year">
				<div class="pcm-option-label">
					<label for="pcm-courses-select-year"><?php _e( 'Courses year', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<select name="courses-select-year" id="pcm-courses-select-year">
						<?php for ( $y = ( ( (int) date( 'Y' ) ) + 1 ); $y >= 2008; $y-- ) { ?>
							<option value="<?php echo $y; ?>"<?php selected( $y, $pcm_courses_select_year ); ?>><?php echo $y; ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
			<div class="pcm-option pcm-option-courses-type">
				<div class="pcm-option-label">
					<label for="pcm-courses-select-type"><?php _e( 'Course type', $PCM->plugin_slug ); ?></label>
				</div>
				<div class="pcm-option-input">
					<select name="courses-select-type" id="pcm-courses-select-type">
						<option value=""<?php selected( '', $pcm_courses_select_type ); ?>>[<?php _e( 'All course types' ); ?>]</option>
						<?php foreach ( $PCM->get_course_types() as $pcm_course_type ) { ?>
							<option value="<?php echo $pcm_course_type->ID; ?>"<?php selected( $pcm_course_type->ID, $pcm_courses_select_type ); ?>><?php echo $pcm_course_type->post_title; ?></option>
						<?php } ?>
					</select>
				</div>
				<p class="description clear"><?php _e( 'Select the type of courses you want to book users onto.', $PCM->plugin_slug ) ?></p>
			</div>
		</div>

		<div class="buttons clear">
			<?php wp_nonce_field( 'add-booking-options' ); ?>
			<input type="hidden" name="pcm-admin-form" value="add-booking-options">
			<input type="submit" value="<?php _e( 'Update options' ); ?>" class="button-primary">
		</div>

	</form>

	<br>

	<form action="" method="post" id="pcm-add-booking" class="pcm-form">

		<h3><?php _e( 'Select user(s) and course(s) for booking', $PCM->plugin_slug ); ?></h3>

		<table class="form-table">
			<tr>
				<th><label for="pcm-user-id"><?php _e( 'User(s)', $PCM->plugin_slug ); ?></label></th>
				<td>
					<?php if ( empty( $pcm_users_to_book ) ) { ?>
						<p><em><?php _e( 'No users registered in the selected date range.', $PCM->plugin_slug ); ?></em></p>
					<?php } else { ?>
						<select name="user-id<?php if ( $pcm_admin_user_settings['add-bookings-users-multiple-select'] ) { ?>[]<?php } ?>" id="pcm-user-id"<?php if ( ! $pcm_admin_user_settings['add-bookings-users-multiple-select'] ) { ?> class="pcm-combobox"<?php } else { ?> multiple="multiple" size="10"<?php } ?>>
							<?php foreach ( $pcm_user_roles as $pcm_role ) { ?>
								<?php if ( ! empty( $pcm_users_to_book[ $pcm_role ] ) ) { ?>
									<?php if ( $pcm_admin_user_settings['add-bookings-users-multiple-select'] ) { ?>
										<optgroup label="<?php echo $wp_roles->roles[ $pcm_role ]['name']; ?>">
									<?php } ?>
									<?php foreach ( $pcm_users_to_book[ $pcm_role ] as $pcm_user_to_book ) { ?>
										<option value="<?php echo $pcm_user_to_book->ID; ?>"><?php echo $pcm_user_to_book->display_name . ' (ID: ' . $pcm_user_to_book->ID . ')'; ?></option>
									<?php } ?>
									<?php if ( $pcm_admin_user_settings['add-bookings-users-multiple-select'] ) { ?>
										</optgroup>
									<?php } ?>
								<?php } ?>
							<?php } ?>
						</select>
					<?php } ?>
				</td>
			</tr>
			<tr>
				<th><label for="pcm-course-id"><?php _e( 'Course(s)', $PCM->plugin_slug ); ?></label></th>
				<td>
					<?php if ( ! $pcm_courses_to_book->have_posts() ) { ?>
						<p><em><?php _e( 'No courses available in the selected year.', $PCM->plugin_slug ); ?></em></p>
					<?php } else { ?>
						<select name="course-id<?php if ( $pcm_admin_user_settings['add-bookings-courses-multiple-select'] ) { ?>[]<?php } ?>" id="pcm-course-id"<?php if ( ! $pcm_admin_user_settings['add-bookings-courses-multiple-select'] ) { ?> class="pcm-combobox"<?php } else { ?> multiple="multiple" size="10"<?php } ?>>
							<?php while ( $pcm_courses_to_book->have_posts() ) { ?>
								<?php $pcm_courses_to_book->the_post(); ?>
								<option value="<?php the_ID(); ?>"><?php
									the_title();
									if ( function_exists( 'slt_cf_all_field_values' ) ) {
										$pcm_metadata = slt_cf_all_field_values( 'post', get_the_ID() );
										echo ' (' . $PCM->format_course_date( $pcm_metadata['pcm-course-date-start'],$pcm_metadata['pcm-course-date-end'] ) . ')';
									}
									?></option>
							<?php } ?>
						</select>
					<?php } ?>
					<?php wp_reset_postdata(); ?>
				</td>
			</tr>
			<tr>
				<th><label for="pcm-approve-booking"><?php _e( 'Approve booking(s)', $PCM->plugin_slug ); ?></label></th>
				<td><input type="checkbox" name="approve-booking" id="pcm-approve-booking" value="1" checked="checked"></td>
			</tr>
			<tr>
				<th><label for="pcm-send-notification"><?php _e( 'Send email notifications', $PCM->plugin_slug ); ?></label></th>
				<td><input type="checkbox" name="send-notification" id="pcm-send-notification" value="1"></td>
			</tr>
		</table>

		<div class="buttons">
			<?php wp_nonce_field( 'add-booking' ); ?>
			<input type="hidden" name="pcm-admin-form" value="add-booking">
			<input type="submit" value="<?php _e( 'Add booking(s)' ); ?>" class="button-primary">
		</div>

	</form>

</div>
