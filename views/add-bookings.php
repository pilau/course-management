<?php

/**
 * Represents the view for the add bookings page
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */
global $wpdb;
$PCM = Pilau_Course_Management::get_instance();

// Get users
$pcm_users_to_book_args = apply_filters( 'pcm_users_to_book_args', array(
	'role'		=> array( 'subscriber', 'pcm-course-participant' ),
	'orderby'	=> 'display_name'
));
if ( isset( $pcm_users_to_book_args['role'] ) ) {
	if ( ! is_array( $pcm_users_to_book_args['role'] ) ) {
		$pcm_users_to_book_args['role'] = array( $pcm_users_to_book_args['role'] );
	}
} else {
	$pcm_users_to_book_args['role'] = array( '' );
}
$pcm_roles = $pcm_users_to_book_args['role'];
unset( $pcm_users_to_book_args['role'] );
$pcm_users_to_book = array();
foreach ( $pcm_roles as $pcm_role ) {
	$pcm_this_role_args = $pcm_users_to_book_args;
	if ( $pcm_role ) {
		$pcm_this_role_args['role'] = $pcm_role;
	}
	$pcm_users = get_users( $pcm_this_role_args );
	if ( $pcm_users ) {
		$pcm_users_to_book = array_merge( $pcm_users_to_book, $pcm_users );
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
			'value'		=> date( 'Y/m/d' ),
			'compare'	=> '>'
		)
	);
	$pcm_courses_to_book_args['meta_key'] = slt_cf_field_key( 'pcm-course-date-start' );
	$pcm_courses_to_book_args['orderby'] = 'meta_value';
	$pcm_courses_to_book_args['order'] = 'ASC';
}
$pcm_courses_to_book = get_posts( apply_filters( 'pcm_courses_to_book_args', $pcm_courses_to_book_args ) );

?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php if ( isset( $_GET['msg'] ) ) { ?>
		<div id="message" class="updated">
			<?php
			switch ( $_GET['msg'] ) {
				case 'added':
					echo '<p>' . __( 'Booking added.', $PCM->plugin_slug ) . '</p>';
					break;
				case 'error':
					echo '<p>' . __( 'There was an error with the booking.', $PCM->plugin_slug ) . '</p>';
					break;
				case 'already-booked':
					echo '<p>' . __( 'That user already has a booking for that course.', $PCM->plugin_slug ) . '</p>';
					break;
			}
			?>
		</div>
	<?php } ?>

	<form action="" method="post" id="pcm-add-booking" class="pcm-form">

		<div>
			<h3><label for="pcm-user-id"><?php _e( 'User', $PCM->plugin_slug ); ?></label></h3>
			<select name="user-id" id="pcm-user-id" class="pcm-combobox">
				<?php foreach ( $pcm_users_to_book as $pcm_user_to_book ) { ?>
					<option value="<?php echo $pcm_user_to_book->ID; ?>"><?php echo $pcm_user_to_book->display_name . ' (ID: ' . $pcm_user_to_book->ID . ')'; ?></option>
				<?php } ?>
			</select>
		</div>

		<div>
			<h3><label for="pcm-course-id"><?php _e( 'Course', $PCM->plugin_slug ); ?></label></h3>
			<select name="course-id" id="pcm-course-id" class="pcm-combobox">
				<?php foreach ( $pcm_courses_to_book as $pcm_course_to_book ) { ?>
					<option value="<?php echo $pcm_course_to_book->ID; ?>"><?php
						echo apply_filters( 'the_title', $pcm_course_to_book->post_title );
						if ( function_exists( 'slt_cf_all_field_values' ) ) {
							$pcm_metadata = slt_cf_all_field_values( 'post', $pcm_course_to_book->ID );
							echo ' (' . $PCM->format_course_date( $pcm_metadata['pcm-course-date-start'],$pcm_metadata['pcm-course-date-end'] ) . ')';
						}
					?></option>
				<?php } ?>
			</select>
		</div>

		<p>
			<input type="checkbox" name="approve-booking" id="pcm-approve-booking" value="1" checked="checked"> <label for="pcm-approve-booking"><?php _e( 'Approve booking', $PCM->plugin_slug ); ?></label>
		</p>

		<p>
			<input type="checkbox" name="send-notification" id="pcm-send-notification" value="1"> <label for="pcm-send-notification"><?php _e( 'Send email notifications', $PCM->plugin_slug ); ?></label>
		</p>

		<div class="buttons">
			<?php wp_nonce_field( 'add-booking' ); ?>
			<input type="hidden" name="pcm-admin-form" value="add-booking">
			<input type="submit" value="<?php _e( 'Add booking' ); ?>" class="button-primary">
		</div>

	</form>

</div>
