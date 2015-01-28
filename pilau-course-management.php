<?php

/**
 * Pilau Course Management
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 *
 * @wordpress-plugin
 * Plugin Name:			Pilau Course Management
 * Description:			Basic, extensible functionality for managing courses.
 * Version:				0.3.4
 * Author:				Steve Taylor
 * Text Domain:			pilau-course-management-locale
 * License:				GPL-2.0+
 * License URI:			http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:			/languages
 * GitHub Plugin URI:	https://github.com/pilau/course-management
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once( plugin_dir_path( __FILE__ ) . 'class-pilau-course-management.php' );

// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
register_activation_hook( __FILE__, array( 'Pilau_Course_Management', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Pilau_Course_Management', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Pilau_Course_Management', 'get_instance' ) );
