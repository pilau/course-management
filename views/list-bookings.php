<?php

/**
 * Lists a user's bookings in tabular format
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */
global $profileuser;
$pcm_user_id = $profileuser->ID;
$PCM = Pilau_Course_Management::get_instance();

if ( $pcm_user_id && $pcm_bookings = $PCM->get_user_courses( $pcm_user_id ) ) { ?>

	<table class="pcm-table">

		<tr>
			<th scope="col"><?php _e( 'Course', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Instance', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Course date', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Submitted', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Approved', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Completed', $PCM->plugin_slug ); ?></th>
			<th scope="col"><?php _e( 'Status', $PCM->plugin_slug ); ?></th>
		</tr>

		<?php $alt = 0; ?>
		<?php foreach ( $pcm_bookings as $pcm_booking ) { ?>

			<?php

			// Format: {course_instance_id}|{user_id}
			$pcm_item_id = $pcm_booking['course_instance_id'] . '|' . $pcm_booking['user_id'];
			//echo '<pre>'; print_r( $pcm_bookings ); echo '</pre>'; exit;

			?>

			<tr class="<?php if ( $alt ) echo 'alt'; ?>">
				<td><?php echo $PCM->multiple_post_titles( $pcm_booking['course_id'], ' / ', 'edit' ); ?></td>
				<td><?php echo $PCM->multiple_post_titles( $pcm_booking['course_instance_id'], ' / ', 'edit' ); ?></td>
				<td>
					<?php
					echo implode( '/', array_reverse( explode( '/', $pcm_booking['course_date_start'] ) ) );
					if ( $pcm_booking['course_date_start'] != $pcm_booking['course_date_end'] ) {
						echo ' &#150; ' . implode( '/', array_reverse( explode( '/', $pcm_booking['course_date_end'] ) ) );
					}
					?>
				</td>
				<td><?php echo date( 'd/m/Y', $pcm_booking['booking_submitted'] ); ?></td>
				<td><?php echo $pcm_booking['booking_approved'] ? date( 'd/m/Y', $pcm_booking['booking_approved'] ) : '-'; ?></td>
				<td><?php echo $pcm_booking['booking_completed'] ? date( 'd/m/Y', $pcm_booking['booking_completed'] ) : '-'; ?></td>
				<td><?php echo $pcm_booking['booking_status']; ?></td>
			</tr>

			<?php $alt = 1 - $alt; ?>
		<?php } ?>

	</table>

<?php } else { ?>

	<p><em><?php _e( 'Currently there are no bookings for this user.', $PCM->plugin_slug ); ?></em></p>

<?php } ?>
