# Pilau Course Management

A WordPress plugin providing basic, extensible functionality for managing courses, which include some offline and some online components.

**NOTE:** Depends on the [Developer's Custom Fields](https://github.com/gyrus/WordPress-Developers-Custom-Fields) plugin.

## Installation

Note that the plugin folder should be named `course-management`. This is because if the [GitHub Updater plugin](https://github.com/afragen/github-updater) is used to update this plugin, if the folder is named something other than this, it will get deleted, and the updated plugin folder with a different name will cause the plugin to be silently deactivated.

## Filter hooks

* `pcm_custom_fields_args` - Use to modify the default custom fields arguments (uses [Developer's Custom Fields](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/))
* `pcm_post_type_args` - Use to modify the default custom post type arguments
* `pcm_course_instance_title` - Use to modify the automatically-created title for course instances. The input is an array; if the output is still an array, it will be imploded and joined with commas - implode it yourself to do otherwise.
* `pcm_cap` - Use to modify the default capability for a particular action. The default is `pcm_{action}`. Custom capabilities must be added with a plugin such as [Members](http://wordpress.org/plugins/members/).
* `pcm_role_display_name` - Use to modify the default display names for roles created by the plugin. As well as the default display name, the filter passes the role name, i.e. `pcm-course-participant` or `pcm-tutor`.
* `pcm_view_bookings` - Use to modify the bookings listed on the course bookings admin page.
* `pcm_view_bookings_course_filter_args` - Use to modify the courses filter arguments on the course bookings admin page.
* `pcm_view_bookings_course_instance_filter_args` - Use to modify the course instances filter arguments on the course bookings admin page.
* `pcm_send_invitations_course_instances_args` - Use to modify the course instances arguments for the send invitations admin page.
* `pcm_override_invite_email` - Use this to override the default invitation email sending. Perform your own email sending, then return `false` to stop the default email being sent.
* `pcm_placeholder_searches` - Filter placeholder searches.
* `pcm_placeholder_replacements` - Filter placeholder replacements.
* `pcm_users_to_book_args` - Filter arguments for getting users to list on the 'Add bookings' admin screen. You can specifiy multiple roles in the `role` argument, using an array, and the limitations of `get_users()` will be bypassed!
* `pcm_courses_to_book_args` - Filter arguments for getting course instances to list on the 'Add bookings' admin screen.
* `pcm_no_course_lesson_slug` - Filter the slug for lessons not associated with a course.

## Action hooks

* `pcm_booking_submitted` - Args: `$course_instance_id`, `$user_id`, `$course_instance_meta`, `$suppress_notification`
* `pcm_booking_approved` - Args: `$course_instance_id`, `$user_id`, `$course_bookings`, `$suppress_notification`
* `pcm_booking_denied` - Args: `$course_instance_id`, `$user_id`, `$course_bookings`, `$suppress_notification`
* `pcm_booking_completed` - Args: `$course_instance_id`, `$user_id`, `$course_bookings`, `$suppress_notification`
* `pcm_booking_deleted` - Args: `$course_instance_id`, `$user_id`, `$course_bookings`, `$suppress_notification`
