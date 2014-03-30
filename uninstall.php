<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor <steve@sltaylor.co.uk>
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */

// If uninstall, not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

