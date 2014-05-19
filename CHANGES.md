# Changelog for Pilau Course Management

## 0.3.1
* Added default course instance ID to `is_course_bookable` method
* Added `get_prerequisites` method
* Fixed bug in `get_similar_course_instances`

## 0.3
* Made `change_user_role` public
* Added `no_course_lessons_id_zero()`, so lessons not assigned to a course get lesson ID `0` stored, to enable easier selection of lessons with no course.
* Added `pcm_no_course_lesson_slug` filter.
* Added `user_has_booked_course` method.
* Added option for non-bookable courses.
* Added output of booking infos on user profiles for admins.
* Changed the way user statuses are changed, and included option to regress status to pending.

## 0.2.2
* More testing

## 0.2.1
* More testing

## 0.2
* Version increased just to test GitHub Updater plugin

## 0.1
* First version
