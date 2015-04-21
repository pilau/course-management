# Changelog for Pilau Course Management

## 0.3.5
* Added text input fallback for email attachments

## 0.3.4
* Added HTML format to bookings emails, with `pcm_bookings_email_html` filter for templating, and `pcm_bookings_email_default_format`
* Added option to send an attachment with emails
* Added options for admins to change any status to any status, and optionally suppress notifications

## 0.3.3
* Fixed bug which sees 'send email' on Manage Bookings as 'change status'
* Added filters for rewrites and permalinks
* Added start and end time for course instances

## 0.3.2
* Added jQuery UI theme smoothness for manage bookings
* Added `pcm_manage_bookings_usermeta_fields` filter hook
* Added POT file for translations
* `pcm_course` now hierarchical to allow manual ordering

## 0.3.1
* Added default course instance ID to `is_course_bookable` method
* Added `get_prerequisites` method
* Fixed bug in `get_similar_course_instances`

## 0.3
* Made `change_user_role` public
* Added `no_course_lessons_id_zero()`, so lessons not assigned to a course get lesson ID `0` stored, to enable easier selection of lessons with no course
* Added `pcm_no_course_lesson_slug` filter
* Added `user_has_booked_course` method
* Added option for non-bookable courses
* Added output of booking infos on user profiles for admins
* Changed the way user statuses are changed, and included option to regress status to pending

## 0.2.2
* More testing

## 0.2.1
* More testing

## 0.2
* Version increased just to test GitHub Updater plugin

## 0.1
* First version
