<?php

/**
 * Pilau Course Management.
 *
 * @package   Pilau_Course_Management
 * @author    Steve Taylor
 * @license   GPL-2.0+
 * @copyright 2013 Public Life
 */

/**
 * Plugin class.
 *
 * @package Pilau_Course_Management
 * @author  Steve Taylor
 */
class Pilau_Course_Management {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   0.1
	 * @var     string
	 */
	const VERSION = '0.3.8';

	/**
	 * Unique identifier for your plugin.
	 *
	 * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	 * match the Text Domain file header in the main plugin file.
	 *
	 * @since    0.1
	 * @var      string
	 */
	protected $plugin_slug = 'pilau-course-management';

	/**
	 * Instance of this class.
	 *
	 * @since    0.1
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    0.1
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Default booking statuses
	 *
	 * @since    0.1
	 * @var      string
	 */
	protected $booking_statuses = array( 'pending', 'approved', 'denied', 'completed' );

	/**
	 * Admin user settings
	 *
	 * @since    0.3.6
	 * @var      array
	 */
	protected $admin_user_settings = null;

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 *
	 * @since	0.1
	 * @return	void
	 */
	private function __construct() {

		// Load plugin text domain
		//add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Admin init
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Process admin actions
		add_action( 'admin_init', array( $this, 'admin_processing' ) );

		// Add any admin menus
		add_action( 'admin_menu', array( $this, 'admin_menus' ) );

		// Output on user profiles
		add_action( 'show_user_profile', array( $this, 'user_profile_output' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile_output' ) );

		// Add an action link pointing to the options page.
		// $plugin_basename = plugin_basename( plugin_dir_path( __FILE__ ) . 'pilau-course-management.php' );
		// add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Load public-facing style sheet and JavaScript.
		//add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		//add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'init', array( $this, 'custom_rewrite_rules' ) );
		add_filter( 'post_type_link', array( $this, 'permalinks' ), 10, 3 );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_custom_fields' ) );
		add_filter( 'title_save_pre', array( $this, 'course_instance_title' ) );
		add_action( 'save_post_pcm-course-instance', array( $this, 'default_course_end_date' ), 10, 2 );
		add_action( 'save_post_pcm-course-instance', array( $this, 'synch_course_booking_dates' ), 11, 2 );
		add_filter( 'slt_cf_pre_save_value', array( $this, 'no_course_lessons_id_zero' ), 11, 5 );
		add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.1
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Return arguments for custom roles
	 *
	 * @since	0.1
	 * @param	string	$role
	 * @return	array
	 */
	protected static function role_args( $role ) {
		$args = array();

		switch ( $role ) {

			case 'pcm-course-participant': {
				$args['display_name'] = apply_filters( 'pcm_role_display_name', 'Course participant', 'pcm-course-participant' );
				$args['capabilities'] = array(
					'read'					=> true,
					'read_private_pages'	=> true,
					'read_private_posts'	=> true
				);
				break;
			}

			case 'pcm-tutor': {
				$args['display_name'] = apply_filters( 'pcm_role_display_name', 'Tutor', 'pcm-course-participant' );
				$args['capabilities'] = array(
					'read'					=> true,
					'read_private_pages'	=> true,
					'read_private_posts'	=> true
				);
				break;
			}

		}

		return apply_filters( 'pcm_role_args', $args, $role );
	}

	/**
	 * Change a user's role
	 *
	 * @since	0.1
	 * @param	int		$user_id
	 * @param	string	$new_role
	 * @param	string	$old_role	Optional - if set, new role is set only if current role matches this
	 * @return	void
	 */
	public static function change_user_role( $user_id, $new_role, $old_role = null ) {

		// Get user
		$user = new WP_User( $user_id );

		// Find role
		if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				if ( ! $old_role || $role == $old_role ) {

					// Change role
					wp_update_user( array(
						'ID'	=> $user_id,
						'role'	=> $new_role
					));

				}
			}
		}

	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    0.1
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide  ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}
				restore_current_blog();
			} else {
				self::single_activate();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since	0.1
	 * @param	boolean    $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
	 * @return	void
	 */
	public static function deactivate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_deactivate();
				}
				restore_current_blog();
			} else {
				self::single_deactivate();
			}
		} else {
			self::single_deactivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since	0.1
	 * @param	int	$blog_id ID of the new blog.
	 * @return	void
	 */
	public function activate_new_site( $blog_id ) {
		if ( 1 !== did_action( 'wpmu_new_blog' ) )
			return;

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since	0.1
	 * @return	array|false	The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {
		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
		return $wpdb->get_col( $sql );
	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since	0.1
	 * @return	void
	 */
	private static function single_activate() {

		// Add custom roles
		foreach ( array( 'pcm-course-participant', 'pcm-tutor' ) as $role ) {
			$args = self::role_args( $role );
			add_role( $role, $args['display_name'], $args['capabilities'] );
		}

		// Add custom capabilities
		$roles = get_editable_roles();
		foreach ( $GLOBALS['wp_roles']->role_objects as $key => $role ) {
			if ( isset( $roles[ $key ] ) && $role->has_cap( 'manage_options' ) ) {
				$role->add_cap( 'pcm_send_invitations' );
				$role->add_cap( 'pcm_view_bookings' );
				$role->add_cap( 'pcm_manage_bookings' );
				$role->add_cap( 'pcm_manage_notifications' );
			}
		}

		// Make sure rewrite stuff is done then flush rules
		self::custom_rewrite_rules();
		flush_rewrite_rules();

	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since	0.1
	 * @return	void
	 */
	private static function single_deactivate() {

		// Remove custom capabilities
		$roles = get_editable_roles();
		foreach ( $GLOBALS['wp_roles']->role_objects as $key => $role ) {
			foreach ( array( 'pcm_send_invitations', 'pcm_view_bookings', 'pcm_manage_bookings', 'pcm_manage_notifications' ) as $cap ) {
				if ( isset( $roles[ $key ] ) && $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}

		// Flush rewrite rules
		flush_rewrite_rules();

	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since	0.1
	 * @return	void
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Initialize admin
	 *
	 * @since	0.1
	 * @return	void
	 */
	public function admin_init() {

		// Output dependency notices
		if ( ! defined( 'SLT_CF_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'output_dcf_dependency_notice' ) );
		}

		// Get stored user settings
		$admin_user_settings = get_user_meta( get_current_user_id(), '_pcm_admin_user_settings', true );
		if ( empty( $admin_user_settings ) ) {
			$admin_user_settings = array();
		}
		// Apply defaults
		$admin_user_settings = array_merge(
			array(
				'add-bookings-users-multiple-select'	=> true,
				'add-bookings-users-orderby'			=> 'registered',
				'add-bookings-courses-multiple-select'	=> true,
			),
			$admin_user_settings
		);
		// Apply filters and set
		$this->admin_user_settings = apply_filters( 'pcm_admin_user_settings', $admin_user_settings );

	}

	/**
	 * Process admin actions
	 *
	 * @since	0.1
	 * @return	void
	 */
	public function admin_processing() {
		$redirect = null;

		if ( isset( $_REQUEST['pcm-admin-form'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], $_REQUEST['pcm-admin-form'] ) ) {

			// A form submission
			switch ( $_REQUEST['pcm-admin-form'] ) {

				case 'send-invitations': {
					if ( current_user_can( $this->get_cap( 'send_invitations' ) ) ) {

						if ( $_POST['pcm-invitee'] ) {

							foreach ( $_POST['pcm-invitee'] as $invitee ) {

								// Angle brackets caused problems submitted through the admin form
								// so we need to format the name / email properly here
								$invitee_parts = explode( ' ', $invitee );
								$invitee_email = array_pop( $invitee_parts );
								$invitee_name = implode( ' ', $invitee_parts );
								$invitee_formatted = $invitee_name . ' <' . $invitee_email . '>';
								$invite_subject = '[' . get_bloginfo( 'name' ) . '] ' . __( 'You have been invited to' ) . ' ' . get_the_title( $_POST['course-id'] );

								// Send email? Filter allows override
								if ( apply_filters( 'pcm_override_invite_email', true, $invitee_name, $invitee_email, $invite_subject, $_POST['course-id'] ) ) {
									wp_mail(
										$invitee_formatted,
										$invite_subject,
										$this->parse_email_placeholders( get_option( 'pcm_invitation_template' ), $_POST['course-id'], $invitee_name )
									);
								}

							}

							$redirect = add_query_arg( 'msg', 'sent', $_REQUEST['_wp_http_referer'] );

						} else {

							$redirect = add_query_arg( 'msg', 'no-invitees', $_REQUEST['_wp_http_referer'] );

						}

					}
					break;
				}

				case 'add-booking': {
					if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {
						$msg = 'added';
						$problems = '';
						$redirect = $_REQUEST['_wp_http_referer'];

						// Allow for multiple or single user / course selection
						$user_ids = is_array( $_POST['user-id'] ) ? $_POST['user-id'] : array( $_POST['user-id'] );
						$course_ids = is_array( $_POST['course-id'] ) ? $_POST['course-id'] : array( $_POST['course-id'] );

						// Go through users
						foreach ( $user_ids as $user_id ) {

							// Go through courses
							foreach ( $course_ids as $course_id ) {

								// Submit booking
								$booking = $this->submit_booking( $course_id, $user_id, ! isset( $_POST['send-notification'] ) );

								if ( $booking === true ) {

									// Approve?
									// (retrospective bookings automatically approved)
									if (	isset( $_POST['approve-booking'] ) ||
										( function_exists( 'slt_cf_field_value' ) && slt_cf_field_value( 'pcm-course-date-end', 'post', $_POST['course-id'] ) <= date( 'Y/m/d' ) )
									) {
										$this->approve_booking( $course_id, $user_id, ! isset( $_POST['send-notification'] ) );
									}

								} else {

									// Build up problem details
									$msg = 'problems';
									$problems .= $user_id . '|' . $course_id . '|' . $booking . '-';

								}

							}

						}

						// Problems to add to query string?
						if ( $problems ) {
							$redirect = add_query_arg( 'problems', $problems, $redirect );
						}

						// Redirect
						$redirect = add_query_arg( 'msg', $msg, $redirect );

					}
					break;
				}

				case 'add-booking-options': {
					if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {
						$msg = 'updated';
						$redirect_url = $_REQUEST['_wp_http_referer'];

						// Options that are stored between sessions
						foreach ( array( 'users-multiple-select', 'courses-multiple-select' ) as $option ) {
							$this->set_admin_user_setting( 'add-bookings-' . $option, ! empty( $_POST['add-bookings-' . $option] ) );
						}
						if ( in_array( $_POST['add-bookings-users-orderby'], array( 'registered', 'display_name' ) ) ) {
							$this->set_admin_user_setting( 'add-bookings-users-orderby', $_POST['add-bookings-users-orderby'] );
						}

						// Options that need to be passed on query string
						foreach ( array( 'users-select-date-start', 'users-select-date-end', 'courses-select-year', 'courses-select-type' ) as $option ) {
							$redirect_url = add_query_arg( $option, $_POST[ $option ], $redirect_url );
						}

						// Redirect
						$redirect = add_query_arg( 'msg', $msg, $redirect_url );

					}
					break;
				}

				case 'invitation-template': {
					if ( current_user_can( $this->get_cap( 'send_invitations' ) ) ) {

						// Update the invitation template
						update_option( 'pcm_invitation_template', strip_tags( $_POST['invitation-template-copy'] ) );
						$redirect = add_query_arg( 'msg', 'template-updated', $_REQUEST['_wp_http_referer'] );

					}
					break;
				}

				case 'manage-bookings': {
					if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {
						//echo '<pre>'; print_r( $_REQUEST ); echo '</pre>'; exit;

						// Are there bookings selected?
						if ( is_array( $_REQUEST['bookings'] ) ) {

							// Action?
							$action = null;
							foreach ( array( 'delete', 'change-status' ) as $possible_action ) {
								if ( isset( $_REQUEST[ $possible_action ] ) ) {
									$action = $possible_action;
									break;
								}
							}
							//echo '<pre>'; print_r( $action ); echo '</pre>'; exit;

							if ( $action ) {

								// Gather data
								$bookings = array(); // This array will have user IDs as keys, each containing an array of course instance IDs
								foreach ( $_REQUEST['bookings'] as $booking ) {
									// Format: {course_instance_id}|{user_id}
									$booking_details = explode( '|', $booking );
									if ( ! array_key_exists( $booking_details[1], $bookings ) ) {
										$bookings[ $booking_details[1] ] = array(); // Add user ID as key
									}
									$bookings[ $booking_details[1] ][] = $booking_details[0]; // Add course instance ID
								}
								//echo '<pre>'; print_r( $bookings ); echo '</pre>'; exit;

								// Go through users
								foreach ( $bookings as $user_id => $user_bookings ) {
									switch ( $action ) {
										case 'delete': {
											$this->delete_booking( $user_bookings, $user_id );
											break;
										}
										case 'change-status': {
											switch ( $_REQUEST['new-status'] ) {
												case 'pending': {
													$this->set_booking_status( $user_bookings, $user_id, 'pending' );
													break;
												}
												case 'approved': {
													$this->approve_booking( $user_bookings, $user_id, isset( $_REQUEST['suppress-notifications'] ) );
													break;
												}
												case 'denied': {
													$this->deny_booking( $user_bookings, $user_id, isset( $_REQUEST['suppress-notifications'] ) );
													break;
												}
												case 'completed': {
													$this->complete_booking( $user_bookings, $user_id, isset( $_REQUEST['suppress-notifications'] ) );
													break;
												}
											}
											break;
										}
									}
								}

								// Redirect
								$redirect = add_query_arg( 'msg', $action, $_REQUEST['_wp_http_referer'] );
							}

						}

					}
					break;
				}

				case 'send-bookings-email': {
					if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

						if ( isset( $_POST['recipients'] ) && $_POST['recipients'] ) {
							$recipients = explode( ',', $_POST['recipients'] );
							$course_id = isset( $_REQUEST['course_id'] ) ? $_REQUEST['course_id'] : 0;
							$course_instance_id = isset( $_REQUEST['course_instance_id'] ) ? $_REQUEST['course_instance_id'] : 0;

							// HTML?
							if ( $_POST['email-format'] == 'html' ) {
								add_filter( 'wp_mail_content_type', array( $this, 'set_content_type_html' ) );
							}

							// Attachment?
							$attachment = $attachment_path = array();
							if (
								( ! empty( $_POST['email-attachment'] ) && $attachment_path = get_attached_file( $_POST['email-attachment'] ) ) ||
								( ! empty( $_POST['email-attachment-manual'] ) && $attachment_path = get_attached_file( $_POST['email-attachment-manual'] ) )
							) {
								$attachment = $attachment_path;
							}

							// Go through each recipient
							foreach ( $recipients as $recipient ) {

								// Get user
								$userdata = get_userdata( $recipient );

								// Parse placeholders in message
								$message = $this->parse_email_placeholders( $this->undo_magic_quotes( $_POST['email-message'] ), $course_instance_id, $userdata, null, $course_id );

								// If HTML...
								if ( $_POST['email-format'] == 'html' ) {

									// Do paragraphs
									$message = wpautop( $message );

									// Filter - this is where any HTML templating can be applied
									$message = apply_filters( 'pcm_bookings_email_html', $message, $_POST['email-subject'] );

								}

								// Send email
								wp_mail(
									$userdata->display_name . ' <'  . $userdata->user_email . '>',
									'[' . get_bloginfo( 'name' ) . '] ' . $_POST['email-subject'],
									$message,
									'',
									$attachment
								);

							}

							// Revert to normal email content type?
							if ( $_POST['email-format'] == 'html' ) {
								remove_filter( 'wp_mail_content_type', array( $this, 'set_content_type_html' ) );
							}

							// Pass filters through along with message
							$redirect = add_query_arg( array(
								'msg'					=> 'sent',
								'user_id'				=> $_POST['user_id'],
								'course_id'				=> $_POST['course_id'],
								'course_instance_id'	=> $_POST['course_instance_id'],
								'status'				=> $_POST['status']
							), admin_url( 'edit.php?post_type=pcm-course-instance&page=pcm-manage-bookings' ) );
						}

					}
					break;
				}

				case 'email-notifications': {
					if ( current_user_can( $this->get_cap( 'manage_notifications' ) ) ) {

						// Email notifications
						$email_settings = array();
						$email_settings['booking-alert'] = isset( $_POST['booking-alert'] ) ? 1 : 0;
						$email_settings['booking-email'] = sanitize_email( $_POST['booking-email'] );
						update_option( 'pcm_email_notifications', $email_settings );
						$redirect = add_query_arg( 'updated', 1, $_REQUEST['_wp_http_referer'] );

					}
					break;
				}

			}

		} else if ( isset( $_REQUEST['pcm-action'] ) && in_array( $_REQUEST['pcm-action'], array( 'approve', 'deny', 'complete', 'delete' ) ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'pcm-admin-action' ) ) {

			// A URL-based action
			if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

				if ( isset( $_REQUEST['id'] ) ) {
					$item_details = explode( '|', $_REQUEST['id'] );

					switch ( $_REQUEST['pcm-action'] ) {
						case 'approve': {
							$this->approve_booking( $item_details[0], $item_details[1] );
							break;
						}
						case 'deny': {
							$this->deny_booking( $item_details[0], $item_details[1] );
							break;
						}
						case 'complete': {
							$this->complete_booking( $item_details[0], $item_details[1] );
							break;
						}
						case 'delete': {
							$this->delete_booking( $item_details[0], $item_details[1] );
							break;
						}
					}

					$redirect = add_query_arg( 'msg', $_REQUEST['pcm-action'], remove_query_arg( 'msg', wp_get_referer() ) );
				}

			}

		}

		if ( $redirect ) {
			wp_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Get current admin user settings
	 *
	 * @since	0.3.6
	 * @return	array
	 */
	public function get_admin_user_settings() {
		return $this->admin_user_settings;
	}

	/**
	 * Set current admin user setting
	 *
	 * @since	0.3.6
	 * @param	string	$key
	 * @param	mixed	$value
	 * @return	void
	 */
	public function set_admin_user_setting( $key, $value ) {
		$this->admin_user_settings[ $key ] = $value;
		update_user_meta( get_current_user_id(), '_pcm_admin_user_settings', $this->admin_user_settings );
	}

	/**
	 * For setting the content type of emails to HTML
	 *
	 * @since	0.3.4
	 */
	function set_content_type_html( $content_type ) {
		return 'text/html';
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     0.1
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {
		$screen = get_current_screen();
		//echo '<pre>'; print_r( $screen ); echo '</pre>'; exit;

		if ( $screen->id == $this->plugin_screen_hook_suffix || strpos( $screen->id, 'pcm-course' ) !== false || in_array( $screen->id, array( 'profile', 'user-edit' ) ) ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), self::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     0.1
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();

		if ( $screen->id == $this->plugin_screen_hook_suffix || strpos( $screen->id, 'pcm-course' ) !== false ) {

			// Global admin script
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );

			// Combobox where necessary
			if ( in_array( $screen->id, array( 'pcm-course-instance_page_pcm-manage-bookings', 'pcm-course-instance_page_pcm-add-bookings' ) ) ) {
				wp_enqueue_script( 'jquery-ui-combobox', plugins_url( 'js/jquery.ui.combobox.js', __FILE__ ), array( 'jquery', 'jquery-ui-widget', 'jquery-ui-menu', 'jquery-ui-button', 'jquery-ui-autocomplete' ), '0.1', true );
				wp_enqueue_style( 'jquery-ui-smoothness-css', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.min.css', false, self::VERSION, false );
			}

			// Send invitations script
			if ( $screen->id == 'pcm-course-instance_page_pcm-send-invitations' ) {
				wp_enqueue_script( $this->plugin_slug . '-send-invitations-script', plugins_url( 'js/admin-send-invitations.js', __FILE__ ), array( 'jquery', $this->plugin_slug . '-admin-script' ), self::VERSION, true );
			}

			if ( $screen->id == 'pcm-course-instance_page_pcm-manage-bookings' ) {

				// Manage bookings script
				wp_enqueue_script( $this->plugin_slug . '-manage-bookings-script', plugins_url( 'js/admin-manage-bookings.js', __FILE__ ), array( 'jquery', $this->plugin_slug . '-admin-script', 'jquery-ui-combobox' ), self::VERSION, true );

				// File selection if available, for email attachments
				if ( ! empty( $_REQUEST['pcm-action'] ) && $_REQUEST['pcm-action'] == 'compose-email' && function_exists( 'slt_cf_file_select_button_enqueue' ) ) {
					slt_cf_file_select_button_enqueue();
				}

			}

		}

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}

	/**
	 * Register the administration menus for this plugin
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function admin_menus() {

		/* Options page
		$this->plugin_screen_hook_suffix = add_plugins_page(
			__( 'Pilau Course Management options', $this->plugin_slug ),
			__( 'Pilau Course Management', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);
		*/

		/* Course invitations */
		add_submenu_page(
			'edit.php?post_type=pcm-course-instance',
			__( 'Send invitations', $this->plugin_slug ),
			__( 'Send invitations', $this->plugin_slug ),
			$this->get_cap( 'send_invitations' ),
			'pcm-send-invitations',
			array( $this, 'send_invitations_admin_page' )
		);

		/* Add bookings */
		add_submenu_page(
			'edit.php?post_type=pcm-course-instance',
			__( 'Add bookings', $this->plugin_slug ),
			__( 'Add bookings', $this->plugin_slug ),
			$this->get_cap( 'manage_bookings' ),
			'pcm-add-bookings',
			array( $this, 'add_bookings_admin_page' )
		);

		/* Course bookings management */
		add_submenu_page(
			'edit.php?post_type=pcm-course-instance',
			current_user_can( $this->get_cap( 'manage_bookings' ) ) ? __( 'Manage bookings', $this->plugin_slug ) : __( 'View bookings', $this->plugin_slug ),
			current_user_can( $this->get_cap( 'manage_bookings' ) ) ? __( 'Manage bookings', $this->plugin_slug ) : __( 'View bookings', $this->plugin_slug ),
			$this->get_cap( 'view_bookings' ),
			'pcm-manage-bookings',
			array( $this, 'manage_bookings_admin_page' )
		);

		/* Email notifications */
		add_submenu_page(
			'edit.php?post_type=pcm-course-instance',
			__( 'Email notifications', $this->plugin_slug ),
			__( 'Email notifications', $this->plugin_slug ),
			$this->get_cap( 'manage_notifications' ),
			'pcm-email-notifications',
			array( $this, 'email_notifications_admin_page' )
		);

	}

	/**
	 * Get the capability for an action
	 *
	 * @since	0.1
	 * @param	string	$action
	 * @return	string
	 */
	public function get_cap( $action ) {

		// Default cap matches action
		// Custom caps must be added with a plugin such as Members
		$cap = 'pcm_' . $action;

		// Filter
		$cap = apply_filters( 'pcm_cap', $cap, $action );

		return $cap;
	}

	/**
	 * User profile output
	 *
	 * @since	0.3
	 * @return	void
	 */
	public function user_profile_output() {

		// Course booking details for admins
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {
			echo '<h3>Course booking details</h3>';
			include_once( 'views/list-bookings.php' );
		}

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function display_plugin_admin_page() {
		include_once( 'views/admin.php' );
	}

	/**
	 * Render the send invitations page for this plugin.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function send_invitations_admin_page() {
		include_once( 'views/send-invitations.php' );
	}

	/**
	 * Render the add bookings page for this plugin.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function add_bookings_admin_page() {
		include_once( 'views/add-bookings.php' );
	}

	/**
	 * Render the bookings management page for this plugin.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function manage_bookings_admin_page() {
		include_once( 'views/manage-bookings.php' );
	}

	/**
	 * Render the email notifications page for this plugin.
	 *
	 * @since    0.1
	 * @return	void
	 */
	public function email_notifications_admin_page() {
		include_once( 'views/email-notifications.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since	0.1
	 * @return	array
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'plugins.php?page=pilau-course-management' ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	/**
	 * Output Developer's Custom Fields dependency notice
	 *
	 * @since	0.1
	 * @return	void
	 */
	public function output_dcf_dependency_notice() {
		echo '<div class="error"><p>' . __( 'The Pilau Course Management plugin depends on the <a href="http://wordpress.org/plugins/developers-custom-fields/">Developer\'s Custom Fields</a> plugin, which isn\'t currently activated', $this->plugin_slug ) . '</p></div>';
	}

	/**
	 * User query mods
	 *
	 * @since	0.3.6
	 * @return	void
	 */
	public function pre_user_query( $q ) {
		global $wpdb;

		// Filtering for date registered before
		if ( isset( $q->query_vars['pcm_registered_before'] ) ) {
			$q->query_where .= $wpdb->prepare(
				" AND {$wpdb->users}.user_registered <= FROM_UNIXTIME( %d ) ",
				$q->query_vars['pcm_registered_before']
			);
		}

		// Filtering for date registered after
		if ( isset( $q->query_vars['pcm_registered_after'] ) ) {
			$q->query_where .= $wpdb->prepare(
				" AND {$wpdb->users}.user_registered >= FROM_UNIXTIME( %d ) ",
				$q->query_vars['pcm_registered_after']
			);
		}

	}

	/**
	 * Custom rewrite rules
	 *
	 * @since	0.1
	 * @return	void
	 */
	public static function custom_rewrite_rules() {

		// Register tags
		add_rewrite_tag( '%coursename%', '([a-zA-Z0-9\-]*)' );
		add_rewrite_tag( '%courseyear%', '([0-9]{4})' );
		add_rewrite_tag( '%coursemonth%', '([0-9]{2})' );

		// Lessons rewrite
		add_rewrite_rule(
			apply_filters( 'pcm_lesson_rewrite_regex', '^lessons/([a-zA-Z0-9\-]*)/([^/]*)/?$' ), // lessons/{coursename}/{lessonname}/
			apply_filters( 'pcm_lesson_rewrite_redirect', 'index.php?post_type=pcm-lesson&name=$matches[2]&coursename=$matches[1]' ),
			'bottom'
		);

		// Courses rewrite
		add_rewrite_rule(
			apply_filters( 'pcm_course_rewrite_regex', '^courses/([0-9]{4})/([0-9]{2})/([^/]*)/?$' ), // courses/{courseyear}/{coursemonth}/{coursename}
			apply_filters( 'pcm_course_rewrite_redirect', 'index.php?post_type=pcm-course-instance&name=$matches[3]&courseyear=$matches[1]&coursemonth=$matches[2]' ),
			'bottom'
		);

	}

	/**
	 * Permalinks URLs
	 *
	 * @since		0.1
	 * @param		string	$url
	 * @param		object	$post
	 * @return		string
	 */
	public function permalinks( $url, $post ) {

		// Lesson permalinks
		if ( get_post_type( $post ) == 'pcm-lesson' ) {

			// %coursename%
			if ( strpos( $url, '%coursename%' ) !== false ) {

				// Is there a course for the lesson?
				if ( $course_id = slt_cf_field_value( 'pcm-lesson-course', 'post', $post->ID, '', '', false, false ) ) {
					// If more than one, allow filter to decide on the one to use
					if ( count( $course_id ) > 1 ) {
						$course_id = apply_filters( 'pcm_multiple_course_lesson_which_one', $course_id );
					}
					// Whatever's left, just use the first
					$course_id = $course_id[0];
					$course = get_post( $course_id );
					$course_name = $course->post_name;
				} else {
					$course_name = apply_filters( 'pcm_no_course_lesson_slug', 'no-course', $url, $post );
				}

				// Get the course slug and replace
				$url = str_replace( '%coursename%', urlencode( $course_name ), $url );

			}

		} else if ( get_post_type( $post ) == 'pcm-course-instance' && function_exists( 'slt_cf_field_exists' ) && slt_cf_field_exists( 'pcm-course-date-start', 'post', $post->ID ) ) {

			// Course instance permalink
			$course_date_start = explode( '/', slt_cf_field_value( 'pcm-course-date-start', 'post', $post->ID ) );

			if ( strpos( $url, '%courseyear%' ) !== false ) {
				$url = str_replace( '%courseyear%', $course_date_start[0], $url );
			}
			if ( strpos( $url, '%coursemonth%' ) !== false ) {
				$url = str_replace( '%coursemonth%', $course_date_start[1], $url );
			}

		}

		return $url;
	}

	/**
	 * Return arguments for a post type
	 *
	 * @since	0.1
	 * @param	string	$post_type
	 * @return	array
	 */
	protected function post_type_args( $post_type ) {
		$args = array();

		switch ( $post_type ) {

			case 'pcm-course': {
				// Courses
				$args = array(
					'labels'				=> array(
						'name'					=> __( 'Courses', $this->plugin_slug ),
						'singular_name'			=> __( 'Course', $this->plugin_slug ),
						'add_new'				=> __( 'Add New', $this->plugin_slug ),
						'add_new_item'			=> __( 'Add New Course', $this->plugin_slug ),
						'edit'					=> __( 'Edit', $this->plugin_slug ),
						'edit_item'				=> __( 'Edit Course', $this->plugin_slug ),
						'new_item'				=> __( 'New Course', $this->plugin_slug ),
						'view'					=> __( 'View Course', $this->plugin_slug ),
						'view_item'				=> __( 'View Course', $this->plugin_slug ),
						'search_items'			=> __( 'Search Courses', $this->plugin_slug ),
						'not_found'				=> __( 'No Courses found', $this->plugin_slug ),
						'not_found_in_trash'	=> __( 'No Courses found in Trash', $this->plugin_slug )
					),
					'public'			=> false,
					'show_ui'			=> true,
					'hierarchical'      => true,
					'supports'			=> array( 'title', 'editor', 'custom-fields', 'thumbnail', 'revisions' ),
				);
				break;
			}

			case 'pcm-course-instance': {
				// Course instances
				$args = array(
					'labels'				=> array(
						'name'					=> __( 'Course instances', $this->plugin_slug ),
						'singular_name'			=> __( 'Course instance', $this->plugin_slug ),
						'add_new'				=> __( 'Add New', $this->plugin_slug ),
						'add_new_item'			=> __( 'Add New Course instance', $this->plugin_slug ),
						'edit'					=> __( 'Edit', $this->plugin_slug ),
						'edit_item'				=> __( 'Edit Course instance', $this->plugin_slug ),
						'new_item'				=> __( 'New Course instance', $this->plugin_slug ),
						'view'					=> __( 'View Course instance', $this->plugin_slug ),
						'view_item'				=> __( 'View Course instance', $this->plugin_slug ),
						'search_items'			=> __( 'Search Course instances', $this->plugin_slug ),
						'not_found'				=> __( 'No Course instances found', $this->plugin_slug ),
						'not_found_in_trash'	=> __( 'No Course instances found in Trash', $this->plugin_slug )
					),
					'public'			=> true,
					'supports'			=> array( 'title', 'editor', 'custom-fields', 'thumbnail', 'revisions' ),
					'rewrite'			=> array( 'slug' => 'courses/%courseyear%/%coursemonth%', 'with_front' => false )
				);
				break;
			}

			case 'pcm-lesson': {
				// Lessons
				$args = array(
					'labels'			=> array(
						'name'					=> __( 'Lessons', $this->plugin_slug ),
						'singular_name'			=> __( 'Lesson', $this->plugin_slug ),
						'add_new'				=> __( 'Add New', $this->plugin_slug ),
						'add_new_item'			=> __( 'Add New Lesson', $this->plugin_slug ),
						'edit'					=> __( 'Edit', $this->plugin_slug ),
						'edit_item'				=> __( 'Edit Lesson', $this->plugin_slug ),
						'new_item'				=> __( 'New Lesson', $this->plugin_slug ),
						'view'					=> __( 'View Lesson', $this->plugin_slug ),
						'view_item'				=> __( 'View Lesson', $this->plugin_slug ),
						'search_items'			=> __( 'Search Lesson', $this->plugin_slug ),
						'not_found'				=> __( 'No Lessons found', $this->plugin_slug ),
						'not_found_in_trash'	=> __( 'No Lessons found in Trash', $this->plugin_slug )
					),
					'public'			=> true,
					'hierarchical'		=> true,
					'supports'			=> array( 'title', 'editor', 'custom-fields', 'thumbnail', 'revisions' ),
					'rewrite'			=> array( 'slug' => 'lessons/%coursename%', 'with_front' => false )
				);
				break;
			}

		}

		return apply_filters( 'pcm_post_type_args', $args, $post_type );
	}

	/**
	 * Register post types
	 *
	 * @since	0.1
	 * @return	void
	 */
	public function register_post_types() {

		foreach ( array( 'pcm-course', 'pcm-course-instance', 'pcm-lesson' ) as $post_type ) {
			register_post_type( $post_type, $this->post_type_args( $post_type ) );
		}

	}

	/**
	 * Return arguments for a custom fields box
	 *
	 * @since	0.1
	 * @param	string	$box_id
	 * @return	array
	 */
	protected function custom_fields_box_args( $box_id ) {
		$args = array();

		switch ( $box_id ) {

			case 'pcm-course-type-details-box': {
				// Course type details
				$args = array(
					'type'			=> 'post',
					'title'			=> 'Course type details',
					'id'			=> 'pcm-course-type-details-box',
					'context'		=> 'above-content',
					'fields'	=> array(
						array(
							'name'			=> 'pcm-course-prerequisites',
							'label'			=> 'Prerequisites',
							'description'	=> __( 'Select the course(s) that need to have been completed in order to do this course.', $this->plugin_slug ),
							'type'			=> 'select',
							'multiple'		=> true,
							'single'		=> false,
							'options_type'	=> 'posts',
							'options_query'	=> array(
								'post_type'			=> 'pcm-course',
								'posts_per_page'	=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC'
							),
							'scope'			=> array( 'pcm-course' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						),
					)
				);
				break;
			}

			case 'pcm-course-instance-details-box': {
				// Course instance details
				$args = array(
					'type'			=> 'post',
					'title'			=> 'Course details',
					'id'			=> 'pcm-course-instance-details-box',
					'context'		=> 'above-content',
					'description'	=> __( 'If the title above is left blank, it will be created automatically from the details below.', $this->plugin_slug ),
					'fields'	=> array(
						array(
							'name'			=> 'pcm-course-type',
							'label'			=> 'Type',
							'type'			=> 'select',
							'multiple'		=> true,
							'single'		=> false,
							'options_type'	=> 'posts',
							'options_query'	=> array(
								'post_type'			=> 'pcm-course',
								'posts_per_page'	=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC'
							),
							'required'		=> true,
							'scope'			=> array( 'pcm-course-instance' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'				=> 'pcm-course-non-bookable',
							'label'				=> 'Non-bookable',
							'type'				=> 'checkbox',
							'description'		=> __( 'Check this to exclude this course from the booking system.', $this->plugin_slug ),
							'default'			=> false,
							'scope'				=> array( 'pcm-course-instance' ),
							'capabilities'		=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'				=> 'pcm-course-date-start',
							'label'				=> 'Start date',
							'type'				=> 'date',
							'datepicker_format'	=> 'yy/mm/dd', // Formatted for easy sorting
							'scope'				=> array( 'pcm-course-instance' ),
							'capabilities'		=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'				=> 'pcm-course-time-start',
							'label'				=> 'Start time',
							'type'				=> 'time',
							'scope'				=> array( 'pcm-course-instance' ),
							'capabilities'		=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'				=> 'pcm-course-date-end',
							'label'				=> 'End date',
							'type'				=> 'date',
							'datepicker_format'	=> 'yy/mm/dd', // Formatted for easy sorting
							'scope'				=> array( 'pcm-course-instance' ),
							'capabilities'		=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'				=> 'pcm-course-time-end',
							'label'				=> 'End time',
							'type'				=> 'time',
							'scope'				=> array( 'pcm-course-instance' ),
							'capabilities'		=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'			=> 'pcm-course-location',
							'label'			=> 'Location',
							'type'			=> 'text',
							'scope'			=> array( 'pcm-course-instance' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'			=> 'pcm-course-location-map',
							'label'			=> 'Location map',
							'type'			=> 'gmap',
							'scope'			=> array( 'pcm-course-instance' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						)
					)
				);
				break;
			}

			case 'pcm-lesson-details-box': {
				// Lesson details
				$args = array(
					'type'		=> 'post',
					'title'		=> 'Lesson details',
					'id'		=> 'pcm-lesson-details-box',
					'context'	=> 'above-content',
					'fields'	=> array(
						array(
							'name'			=> 'pcm-lesson-course',
							'label'			=> 'Lesson courses',
							'type'			=> 'select',
							'description'	=> __( 'Select the courses that this lesson is part of.', $this->plugin_slug ),
							'single'		=> false,
							'multiple'		=> true,
							'options_type'	=> 'posts',
							'options_query'	=> array(
								'post_type'			=> 'pcm-course',
								'posts_per_page'	=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC'
							),
							'scope'			=> array( 'pcm-lesson' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						),
						array(
							'name'			=> 'pcm-lesson-type',
							'label'			=> 'Lesson type',
							'type'			=> 'select',
							'options'		=> array(
								'Preparation'	=> 'preparation',
								'Follow-up'		=> 'follow-up'
							),
							'scope'			=> array( 'pcm-lesson' ),
							'capabilities'	=> array( 'edit_posts', 'edit_pages' )
						),
					)
				);
				break;
			}

		}

		return apply_filters( 'pcm_custom_fields_args', $args, $box_id );
	}

	/**
	 * Register custom fields
	 *
	 * @since	0.1
	 * @return	array
	 */
	public function register_custom_fields() {

		if ( function_exists( 'slt_cf_register_box' ) ) {
			foreach ( array( 'pcm-course-type-details-box', 'pcm-course-instance-details-box', 'pcm-lesson-details-box' ) as $box_id ) {
				slt_cf_register_box( $this->custom_fields_box_args( $box_id ) );
			}
		}

	}

	/**
	 * Course ID 0 for lessons with no course
	 *
	 * This enables easier selection of lessons with no associated course
	 *
	 * @since	0.2.3
	 * @param	int		$value			The course ID for the lesson
	 * @param	string	$request_type	Should be 'post'
	 * @param	int		$object_id		The ID of the post being edited
	 * @param	object	$object			The post object
	 * @param	array	$field			The field details
	 * @return	int
	 */
	public function no_course_lessons_id_zero( $value, $request_type, $object_id, $object, $field ) {

		if ( $request_type == 'post' && get_post_type( $object ) == 'pcm-lesson' && $field['name'] == 'pcm-lesson-course' && empty( $value ) ) {
			$value = array( 0 );
		}

		return $value;
	}

	/**
	 * Email placeholder searches
	 *
	 * @since	0.1
	 * @return	array
	 */
	public function email_placeholder_searches() {

		$searches = array(
			'%user-name%',
			'%user-first-name%',
			'%course-type-title%',
			'%course-instance-title%',
			'%course-instance-date%',
			'%course-instance-date-start%',
			'%course-instance-date-end%',
			'%course-instance-location%',
			'%course-instance-url%',
			'%current-date-time%',
			'%my-name%',
		);

		return apply_filters( 'pcm_placeholder_searches', $searches );

	}

	/**
	 * Parse email placeholders
	 *
	 * @since	0.1
	 * @param   string		$copy
	 * @param   int			$course_instance_id
	 * @param   mixed		$user					ID, get_userdata(), or just a name
	 * @param	array		$course_instance_meta
	 * @param	mixed		$course_ids				A single ID or array of IDs of course types associated with the instance
	 * @return	string
	 */
	public function parse_email_placeholders( $copy, $course_instance_id = null, $user = null, $course_instance_meta = null, $course_ids = null ) {
		global $current_user;
		get_currentuserinfo();

		// User
		$user_name = null;
		$user_first_name = null;
		if ( ! is_object( $user ) ) {
			if ( ctype_digit( $user ) ) {
				$userdata = get_userdata( $user );
				$user_name = $userdata->display_name;
				$user_first_name = $userdata->first_name;
			} else if ( is_string( $user ) ) {
				$user_name = $user;
				$user_first_name = array_shift( explode( ' ', $user ) );
			}
		} else {
			$user_name = $user->display_name;
			$user_first_name = $user->first_name;
		}

		// Get course instance meta
		if ( ! $course_instance_meta && function_exists( 'slt_cf_all_field_values' ) && $course_instance_id ) {
			$course_instance_meta = slt_cf_all_field_values( 'post', $course_instance_id, array( 'pcm-course-type' ) );
		}

		// Determine course type?
		if ( ! $course_ids ) {
			$course_ids = $course_instance_meta['pcm-course-type'];
		}
		if ( ! is_array( $course_ids ) ) {
			$course_ids = (array) $course_ids;
		}

		// Course type details
		$course_type_title = null;
		if ( $course_ids ) {
			$course_type_title_parts = array();
			foreach ( $course_ids as $course_id ) {
				$course_type_title_parts[] = get_the_title( $course_id );
			}
			$course_type_title = implode( ' / ', $course_type_title_parts );
		}

		// Course instance details
		$course_instance_title = null;
		$course_instance_url = null;
		if ( $course_instance_id ) {
			$course_instance_title = get_the_title( $course_instance_id );
			$course_instance_url = get_permalink( $course_instance_id );
		}
		$course_instance_date = null;
		$course_instance_date_start = null;
		$course_instance_date_end = null;
		$course_instance_location = null;
		if ( $course_instance_meta ) {

			// Course dates
			$course_instance_date_start = isset( $course_instance_meta['pcm-course-date-start'] ) ? $course_instance_meta['pcm-course-date-start'] : '';
			$course_instance_date_end = isset( $course_instance_meta['pcm-course-date-end'] ) ? $course_instance_meta['pcm-course-date-end'] : '';
			$course_instance_date = $this->format_course_date( $course_instance_date_start, $course_instance_date_end, ' - ' );
			if ( function_exists( 'slt_cf_reverse_date' ) ) {
				if ( $course_instance_date_start ) {
					$course_instance_date_start = slt_cf_reverse_date( $course_instance_date_start );
				}
				if ( $course_instance_date_end ) {
					$course_instance_date_end = slt_cf_reverse_date( $course_instance_date_end );
				}
			}

			// Course location
			$course_instance_location = isset( $course_instance_meta['pcm-course-location'] ) ? $course_instance_meta['pcm-course-location'] : '';

		}

		// Filter replacements
		$replacements = apply_filters( 'pcm_placeholder_replacements', array(
			$user_name,
			$user_first_name,
			$course_type_title,
			$course_instance_title,
			$course_instance_date,
			$course_instance_date_start,
			$course_instance_date_end,
			$course_instance_location,
			$course_instance_url,
			date( 'd/m/Y H:i' ),
			$current_user->display_name
		), $course_instance_id, $user, $course_instance_meta );

		// Process
		//echo '<pre>'; print_r( $copy ); echo '</pre>';
		$copy = str_replace( $this->email_placeholder_searches(), $replacements, $copy );
		//echo '<pre>'; print_r( $this->email_placeholder_searches() ); echo '</pre>';
		//echo '<pre>'; print_r( $replacements ); echo '</pre>';
		//echo '<pre>'; print_r( $copy ); echo '</pre>'; exit;

		return $copy;
	}


	/**
	 * Display available email placeholders
	 *
	 * @since	0.1
	 * @param	bool	$course_placeholders
	 * @param	bool	$course_instance_placeholders
	 * @return	void
	 */
	public function available_email_placeholders( $course_placeholders = true, $course_instance_placeholders = true ) {

		$placeholders = $this->email_placeholder_searches();
		$available_placeholders = array();

		foreach ( $placeholders as $placeholder ) {
			if (	strpos( $placeholder, 'course-' ) === false ||
				( strpos( $placeholder, 'course-' ) !== false && strpos( $placeholder, 'course-instance' ) === false && $course_placeholders ) ||
				( strpos( $placeholder, 'course-instance' ) !== false && $course_instance_placeholders )
			) {
				$available_placeholders[] = $placeholder;
			}
		}

		_e( 'Available placeholders:', $this->plugin_slug );
		echo '<code>' . implode( '</code>, <code>', $available_placeholders ) . '</code>';

	}

	/**
	 * Automatic creation of title for course instances
	 *
	 * @since	0.1
	 * @param	int		$post_id
	 * @param	object	$post
	 */
	public function course_instance_title( $title ) {
		global $post;

		if ( get_post_type( $post ) == 'pcm-course-instance' && empty( $title ) ) {
			$auto_title = array();

			// Course type
			if ( $_POST[ slt_cf_field_key( 'pcm-course-type' ) ] ) {
				// Use first course type for title
				$auto_title[] = get_the_title( $_POST[ slt_cf_field_key( 'pcm-course-type' ) ][0] );
			}

			// Course location
			if ( $_POST[ slt_cf_field_key( 'pcm-course-location' ) ] ) {
				$auto_title[] = $_POST[ slt_cf_field_key( 'pcm-course-location' ) ];
			}

			// Filter
			$auto_title = apply_filters( 'pcm_course_instance_title', $auto_title );
			if ( is_array( $auto_title ) ) {
				$auto_title = implode( ', ', $auto_title );
			}

			// Set
			$title = $auto_title;

		}

		return $title;
	}

	/**
	 * If a course instance is saved without an end date, default it to the start date
	 *
	 * Also checks for end dates prior to start dates
	 *
	 * @since	0.1
	 * @param	int		$post_id
	 * @param	object	$post
	 * @return	void
	 */
	public function default_course_end_date( $post_id, $post ) {
		if (
			isset( $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ] ) &&
			(
				! isset( $_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] ) ||
				! $_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] ||
				$_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] < $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ]
			)
		) {
			// Setting the $_POST var is a bit hacky, but works
			// Necessary because otherwise the DCF plugin code kicks in a deletes the newly-created end date field,
			// because there's no value in $_POST
			$_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] = $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ];
			//update_post_meta( $post_id, slt_cf_field_key( 'pcm-course-date-end' ), $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ] );
		}
	}

	/**
	 * Automatic synching of stored course booking dates when an instance date is changed
	 *
	 * @since	0.1
	 * @param	int		$post_id
	 * @param	object	$post
	 * @return	void
	 */
	public function synch_course_booking_dates( $post_id, $post ) {
		if ( isset( $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ] ) && isset( $_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] ) ) {

			// Get old dates
			$old_metadata = slt_cf_all_field_values( 'post', $post_id );

			// Has there been a change?
			if ( $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ] != $old_metadata['pcm-course-date-start'] || $_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ] != $old_metadata['pcm-course-date-end'] ) {
				global $wpdb;

				// Get all user booking data
				$user_bookings = $wpdb->get_results("
					SELECT		user_id, meta_value
					FROM		$wpdb->usermeta
					WHERE		meta_key	= 'pcm-courses'
				");

				// Looks for matching instances
				foreach ( $user_bookings as $user_booking ) {
					$do_update = false;
					$bookings = maybe_unserialize( $user_booking->meta_value );
					foreach ( $bookings as &$booking ) {
						if ( $booking['course_instance_id'] == $post_id ) {
							$booking['course_date_start'] = $_POST[ slt_cf_field_key( 'pcm-course-date-start' ) ];
							$booking['course_date_end'] = $_POST[ slt_cf_field_key( 'pcm-course-date-end' ) ];
							$do_update = true;
							break;
						}
					}
					// Do update?
					if ( $do_update ) {
						update_user_meta( $user_booking->user_id, 'pcm-courses', $bookings );
					}
				}

			}

		}
	}

	/**
	 * Approve a course booking
	 *
	 * @since		0.1
	 * @param		mixed		$course_instance_id		The ID of the course instance, or array of IDs
	 * @param		int			$user_id				The ID of the user
	 * @param		bool		$suppress_notification
	 * @return		void
	 */
	public function approve_booking( $course_instance_id, $user_id, $suppress_notification = false ) {
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

			$updated = false;
			$course_bookings = array();
			if ( ! is_array( $course_instance_id ) ) {
				$course_instance_id = array( $course_instance_id );
			}
			//echo '<pre>'; print_r( $course_instance_id ); echo '</pre>'; exit;

			// Get user courses
			$courses = $this->get_user_courses( $user_id );
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			// Find instance
			foreach ( $courses as &$course ) {
				if ( in_array( $course['course_instance_id'], $course_instance_id ) ) {
					// Settings for course
					$course['booking_approved']	= time();
					$course['booking_status']	= 'approved';
					// Clear other fields in case this approval is a correction from another, incompatible status
					$course['booking_denied']	= null;
					$course['booking_completed']	= null;
					$updated = true;
					$course_bookings[] = $course;
				}
			}
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			if ( $updated ) {

				// Update user courses
				$this->set_user_courses( $courses, $user_id );

				// Promote user to course participant?
				$this->change_user_role( $user_id, 'pcm-course-participant', 'subscriber' );

				// Do action
				do_action( 'pcm_booking_approved', $course_instance_id, $user_id, $course_bookings, $suppress_notification );

			}

		}
	}

	/**
	 * Deny a course booking
	 *
	 * @since		0.1
	 * @param		mixed		$course_instance_id		The ID of the course instance, or array of IDs
	 * @param		int			$user_id				The ID of the user
	 * @param		bool		$suppress_notification
	 * @return		void
	 */
	public function deny_booking( $course_instance_id, $user_id, $suppress_notification = false ) {
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

			$updated = false;
			$course_bookings = array();
			if ( ! is_array( $course_instance_id ) ) {
				$course_instance_id = array( $course_instance_id );
			}

			// Get current user courses
			$courses = $this->get_user_courses( $user_id );

			// Find instance
			foreach ( $courses as &$course ) {
				if ( in_array( $course['course_instance_id'], $course_instance_id ) ) {
					// Settings for course
					$course['booking_denied']	= time();
					$course['booking_status']	= 'denied';
					// Clear other fields in case this approval is a correction from another, incompatible status
					$course['booking_approved']	= null;
					$updated = true;
					$course_bookings[] = $course;
				}
			}

			if ( $updated ) {

				// Update user courses
				$this->set_user_courses( $courses, $user_id );

				// Do action
				do_action( 'pcm_booking_denied', $course_instance_id, $user_id, $course_bookings, $suppress_notification );

			}

		}
	}

	/**
	 * Complete one or more course bookings for a user
	 *
	 * @since		0.1
	 * @param		mixed		$course_instance_id		The ID of the course instance, or array of IDs
	 * @param		int			$user_id				The ID of the user
	 * @param		bool		$suppress_notification
	 * @return		void
	 */
	public function complete_booking( $course_instance_id, $user_id, $suppress_notification = false ) {
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

			$updated = false;
			$course_bookings = array();
			if ( ! is_array( $course_instance_id ) ) {
				$course_instance_id = array( $course_instance_id );
			}

			// Get current user courses
			$courses = $this->get_user_courses( $user_id );
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			// Find instance
			foreach ( $courses as &$course ) {
				if ( in_array( $course['course_instance_id'], $course_instance_id ) ) {
					// Settings for course
					$course['booking_completed']	= time();
					$course['booking_status']		= 'completed';
					$updated = true;
					$course_bookings[] = $course;
				}
			}
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			if ( $updated ) {

				// Update user courses
				$this->set_user_courses( $courses, $user_id );

				// Do action
				do_action( 'pcm_booking_completed', $course_instance_id, $user_id, $course_bookings, $suppress_notification );

			}

		}
	}

	/**
	 * Set a course booking status outside the normal "status flow"
	 *
	 * Currently handles returning a booking to 'pending'
	 *
	 * @since		0.3
	 * @param		mixed		$course_instance_id		The ID of the course instance, or array of IDs
	 * @param		int			$user_id				The ID of the user
	 * @param		string		$status
	 * @return		void
	 */
	public function set_booking_status( $course_instance_id, $user_id, $status ) {
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

			$updated = false;
			if ( ! is_array( $course_instance_id ) ) {
				$course_instance_id = array( $course_instance_id );
			}
			//echo '<pre>'; print_r( $course_instance_id ); echo '</pre>'; exit;

			// Get user courses
			$courses = $this->get_user_courses( $user_id );
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			// Find instance
			foreach ( $courses as &$course ) {
				if ( in_array( $course['course_instance_id'], $course_instance_id ) ) {
					switch ( $status ) {
						case 'pending':
							$course['booking_approved']		= null;
							$course['booking_denied']		= null;
							$course['booking_completed']	= null;
							$course['booking_status']		= 'pending';
							$updated = true;
							break;
					}
				}
			}
			//echo '<pre>'; print_r( $courses ); echo '</pre>'; exit;

			if ( $updated ) {

				// Update user courses
				$this->set_user_courses( $courses, $user_id );

			}

		}
	}


	/**
	 * Delete a course booking
	 *
	 * @since		0.1
	 * @param		mixed		$course_instance_id		The ID of the course instance, or array of IDs
	 * @param		int			$user_id				The ID of the user
	 * @param		bool		$suppress_notification
	 * @return		void
	 */
	public function delete_booking( $course_instance_id, $user_id, $suppress_notification = false ) {
		if ( current_user_can( $this->get_cap( 'manage_bookings' ) ) ) {

			$updated = false;
			$course_bookings = array();
			if ( ! is_array( $course_instance_id ) ) {
				$course_instance_id = array( $course_instance_id );
			}

			// Get current user courses
			$courses = $this->get_user_courses( $user_id );

			// Re-gather bookings, excluded those deleted
			$new_courses = array();
			foreach ( $courses as $course ) {
				if ( ! in_array( $course['course_instance_id'], $course_instance_id ) ) {
					$new_courses[] = $course;
				} else {
					$updated = true;
					$course_bookings[] = $course;
				}
			}

			if ( $updated ) {

				// Update user courses
				$this->set_user_courses( $new_courses, $user_id );

				// Do action
				do_action( 'pcm_booking_deleted', $course_instance_id, $user_id, $course_bookings, $suppress_notification );

			}

		}
	}

	/**
	 * Submit a course booking
	 *
	 * @since		0.1
	 * @param		int			$course_instance_id		The ID of the course instance
	 * @param		int			$user_id				The ID of the user (defaults to current user)
	 * @param		bool		$suppress_notification
	 * @return		mixed		Returns true if booking was made, or error: 'already-booked' | 'error' | 'non-bookable'
	 */
	public function submit_booking( $course_instance_id, $user_id = 0, $suppress_notification = false ) {
		$booking = 'error';

		// Check that the course is bookable
		if ( $this->is_course_bookable( $course_instance_id ) ) {

			// User ID
			if ( $user_id || $user_id = get_current_user_id() ) {

				// Get current user courses
				$courses = $this->get_user_courses( $user_id );
				//echo '<pre>a1'; print_r( $courses ); echo '</pre>';

				// Check if course is already booked
				foreach ( $courses as $course ) {
					if ( $course_instance_id == $course['course_instance_id'] ) {
						$booking = 'already-booked';
						break;
					}
				}

				if ( $booking != 'already-booked' ) {

					// Get the meta
					$course_instance_meta = slt_cf_all_field_values( 'post', $course_instance_id, array( 'pcm-course-type' ) );

					// Is this retrospective?
					$retrospective_booking = $course_instance_meta['pcm-course-date-start'] <= date( 'Y/m/d' );

					// Add booking
					$courses[] = array(
						'course_instance_id'	=> $course_instance_id,
						'course_date_start'		=> $course_instance_meta['pcm-course-date-start'],
						'course_date_end'		=> $course_instance_meta['pcm-course-date-end'],
						'course_id'				=> $course_instance_meta['pcm-course-type'],
						'booking_submitted'		=> time(),
						'booking_approved'		=> null,
						'booking_denied'		=> null,
						'booking_completed'		=> null,
						'booking_status'		=> 'pending'
					);
					//echo '<pre>a2'; print_r( $courses ); echo '</pre>';

					// Update user courses
					$this->set_user_courses( $courses, $user_id );
					//echo '<pre>a3'; print_r( $this->get_user_courses( $user_id ) ); echo '</pre>'; exit;
					$booking = true;

					// Email notification?
					$email_notifications = get_option( 'pcm_email_notifications' );
					if (	isset( $email_notifications['booking-alert'] ) && $email_notifications['booking-alert'] &&
						isset( $email_notifications['booking-email'] ) && $email_notifications['booking-email'] &&
						function_exists( 'slt_cf_reverse_date' ) &&
						! $suppress_notification &&
						! $retrospective_booking
					) {
						$userdata = get_userdata( $user_id );

						$message = "\nBooking details:\n\nUser: " . $userdata->display_name . "\nCourse(s): " . $this->multiple_post_titles( $course_instance_meta['pcm-course-type'] ) . "\nCourse instance: " . get_the_title( $course_instance_id ) . " (" . slt_cf_reverse_date( $course_instance_meta['pcm-course-date-start'] );
						if ( $course_instance_meta['pcm-course-date-start'] != $course_instance_meta['pcm-course-date-end'] ) {
							$message .= ' - ' . slt_cf_reverse_date( $course_instance_meta['pcm-course-date-end'] );
						}
						$message .= ")\nSubmitted: " . date( 'd/m/Y H:i' ) . "\n\nManage bookings: " . admin_url( 'edit.php?post_type=pcm-course-instance&page=pcm-manage-bookings' );
						wp_mail(
							$email_notifications['booking-email'],
							'[' . get_bloginfo( 'name' ) . '] ' . __( 'Booking submitted' ),
							$message
						);

					}

					// Hook
					do_action( 'pcm_booking_submitted', $course_instance_id, $user_id, $course_instance_meta, $suppress_notification );

				}

			}

		} else {

			$booking = 'non-bookable';

		}

		return $booking;
	}

	/**
	 * Check if a course instance is bookable
	 *
	 * @since		0.3
	 * @param		int			$course_instance_id
	 * @return		bool
	 */
	public function is_course_bookable( $course_instance_id = null ) {
		$bookable = true;

		// If no ID passed, assume we're in the loop or on a course instance page
		if ( ! $course_instance_id ) {
			$course_instance_id = get_the_ID();
		}

		if ( function_exists( 'slt_cf_field_value' ) && slt_cf_field_value( 'pcm-course-non-bookable', 'post', $course_instance_id ) ) {
			$bookable = false;
		}

		return $bookable;
	}

	/**
	 * Get users who are course participants (or applicants)
	 *
	 * @since		0.1
	 * @param		bool		$include_applicants
	 * @param		string		$fields
	 * @return		array
	 */
	public function get_users( $include_applicants = true, $fields = 'all' ) {
		$users = array();

		// Which roles to get?
		$roles = array( 'pcm-course-participant' );
		if ( $include_applicants ) {
			$roles[] = 'subscriber';
		}

		// Get users
		foreach ( $roles as $role ) {
			$results = get_users( array(
				'fields'	=> $fields,
				'role'		=> $role,
				'orderby'	=> 'display_name'
			));
			if ( $results ) {
				$users = array_merge( $users, $results );
			}
		}

		return $users;
	}

	/**
	 * Get course details for a user
	 *
	 * @since		0.1
	 * @param		int			$user_id	The ID of the user (if 0, defaults to current user ID)
	 * @return		array
	 */
	public function get_user_courses( $user_id = 0 ) {
		$courses = array();

		// User ID
		if ( $user_id || $user_id = get_current_user_id() ) {

			// Get courses
			$courses = get_user_meta( $user_id, 'pcm-courses', true );
			//echo '<pre>b1'; print_r( $courses ); echo '</pre>';

			// Sort by date
			if ( is_array( $courses ) ) {
				usort( $courses, array( $this, 'sort_user_courses' ) );
			} else {
				$courses = array();
			}

		}

		return $courses;
	}

	/**
	 * Set course details for a user
	 *
	 * @since		0.1
	 * @param		array		$courses
	 * @param		int			$user_id	The ID of the user (if 0, defaults to current user ID)
	 * @return		mixed
	 */
	public function set_user_courses( $courses, $user_id = 0 ) {
		$result = false;

		// User ID
		if ( $user_id || $user_id = get_current_user_id() ) {

			$result = update_user_meta( $user_id, 'pcm-courses', $courses );

		}

		return $result;
	}

	/**
	 * Get IDs of course types for a user matching a booking status
	 *
	 * @since		0.1
	 * @param		mixed		$user_infos	The ID of the user (if 0, defaults to current user ID);
	 * 										or pass course infos array
	 * @param		string		$status
	 * @return		array
	 */
	public function get_user_courses_booked( $user_infos = 0, $status = 'booked' ) {
		$course_ids = array();
		$courses = array();

		if ( ! is_array( $user_infos ) ) {

			// User ID
			if ( $user_infos || $user_infos = get_current_user_id() ) {
				// Get courses
				$courses = maybe_unserialize( get_user_meta( $user_infos, 'pcm-courses', true ) );
			}

		} else {

			$courses = $user_infos;

		}

		// Gather IDs
		if ( $courses && is_array( $courses ) ) {
			foreach ( $courses as $course ) {
				if ( $course['booking_status'] == $status ) {
					foreach ( (array) $course['course_id'] as $course_id ) {
						if ( ! in_array( $course_id, $course_ids ) ) {
							$course_ids[] = $course_id;
						}
					}
				}
			}
		}

		return $course_ids;
	}

	/**
	 * Date comparison for sorting user courses array of arrays
	 *
	 * @since		0.1
	 * @param		string		$a
	 * @param		string		$b
	 * @return		int
	 */
	protected function sort_user_courses( $a, $b ) {
		return strcmp( $a['course_date_start'], $b['course_date_start'] );
	}

	/**
	 * Get IDs of course instances with the same type as another instance
	 *
	 * @since		0.1
	 * @param		int		$course_instance_id
	 * @return		array
	 */
	public function get_similar_course_instances( $course_instance_id = null ) {
		$similar_ids = array();

		// Course instance ID
		if ( ! $course_instance_id ) {
			$course_instance_id = get_the_ID();
		}

		// Get instances with this type (excluding the one passed)
		if ( function_exists( 'slt_cf_field_key' ) ) {

			// Get instances with this type (excluding the one passed)
			$similar_courses = get_posts( array(
				'post_type'			=> 'pcm-course-instance',
				'posts_per_page'	=> -1,
				'meta_query'		=> array(
					array(
						'key'		=> slt_cf_field_key( 'pcm-course-type' ),
						'value'		=> slt_cf_field_value( 'pcm-course-type', 'post', $course_instance_id, '', '', false, false ),
						'compare'	=> 'IN'
					)
				),
				'post__not_in'		=> array( $course_instance_id )
			));

			// Gather IDs
			foreach ( $similar_courses as $similar_course ) {
				$similar_ids[] = $similar_course->ID;
			}

		}

		return $similar_ids;
	}

	/**
	 * Returns an array of course type IDs that are prerequisites for the course type(s) given
	 *
	 * @since		0.3.1
	 * @param		mixed	$course_ids	The ID(s) of the course - integer or array of integers
	 * @return		array
	 */
	public function get_prerequisites( $course_ids ) {
		$prerequisites = array();
		$course_ids = (array) $course_ids;

		// Gather prerequisites
		if ( $course_ids && function_exists( 'slt_cf_field_value' ) ) {
			foreach ( $course_ids as $course_id ) {
				$this_course_prerequisites = slt_cf_field_value( 'pcm-course-prerequisites', 'post', $course_id, '', '', false, false );
				foreach ( $this_course_prerequisites as $this_course_prerequisite ) {
					if ( ! in_array( $this_course_prerequisite, $prerequisites ) ) {
						$prerequisites[] = $this_course_prerequisite;
					}
				}
			}
		}

		return $prerequisites;
	}

	/**
	 * Returns an array of post objects for course types that are still required
	 * by a user to book a given course
	 *
	 * When multiple course types are given, the remaining prerequisites that
	 * aren't present in those types are returned
	 *
	 * @since		0.1
	 * @param		mixed	$course_ids	The ID(s) of the course - integer or array of integers
	 * @param		mixed	$user_infos	The ID of the user (if 0, defaults to current user ID); or
	 * 									supply the results of get_user_courses_booked()
	 * @return		array
	 */
	public function remaining_prerequisites( $course_ids, $user_infos = 0 ) {
		$remaining_prerequisites = array();
		$completed_courses = array();

		// Array of course type IDs
		$course_ids = (array) $course_ids;

		// User ID
		if ( ! is_array( $user_infos ) ) {

			// Get completed courses
			if ( $user_infos || $user_infos = get_current_user_id() ) {
				$completed_courses = $this->get_user_courses_booked( $user_infos, 'completed' );
			}

		} else {

			// Pass completed courses through
			$completed_courses = $user_infos;

		}

		// Get prerequisites for specified courses
		$prerequisites = $this->get_prerequisites( $course_ids );

		if ( $prerequisites ) {

			// Calculate the remaining course ids - first remove the courses completed
			$remaining_prerequisites_ids = array_diff( $prerequisites, $completed_courses );
			// Now remove the courses that are in the IDs specified
			$remaining_prerequisites_ids = array_diff( $remaining_prerequisites_ids, $course_ids );

			// Get the courses, if any
			if ( $remaining_prerequisites_ids ) {
				$remaining_prerequisites = get_posts( array(
					'post_type'		=> 'pcm-course',
					'post__in'		=> $remaining_prerequisites_ids
				));
			}

		}

		return $remaining_prerequisites;
	}

	/**
	 * Get lessons for a course type
	 *
	 * @since		0.1
	 * @param		int		$course_type_id		The ID of the course type
	 * @param		string	$type					Lesson type
	 * @param		int		$course_instance_id	The ID of the course instance, if known
	 * @return		array
	 */
	public function get_course_lessons( $course_type_id, $type = 'all', $course_instance_id = null ) {
		$lessons = array();

		if ( function_exists( 'slt_cf_field_key' ) ) {

			// Basic arguments
			$args = array(
				'post_type'			=> 'pcm-lesson',
				'posts_per_page'	=> -1,
				'orderby'			=> 'menu_order',
				'order'				=> 'ASC',
				'meta_query'		=> array(
					array(
						'key'		=> slt_cf_field_key( 'pcm-lesson-course' ),
						'value'		=> $course_type_id
					)
				)
			);

			// Filter by type?
			if ( $type != 'all' ) {
				$args['meta_query'][] = array(
					'key'		=> slt_cf_field_key( 'pcm-lesson-type' ),
					'value'		=> $type
				);
			}

			// Allow args to be filtered
			$args = apply_filters( 'pcm_get_course_lessons_args', $args, $course_type_id, $course_instance_id );

			// Get lessons
			$lessons = get_posts( $args );

		}

		return $lessons;
	}

	/**
	 * Get course types (with caching)
	 *
	 * @since		0.1
	 * @param		string	$orderby
	 * @param		string	$order
	 * @param		bool	$cache
	 * @return		array
	 */
	public function get_course_types( $orderby = 'title', $order = 'ASC', $cache = true ) {
		$courses = array();

		if ( function_exists( 'slt_cf_field_key' ) ) {

			// Basic arguments
			$args = array(
				'post_type'			=> 'pcm-course',
				'posts_per_page'	=> -1,
				'orderby'			=> $orderby,
				'order'				=> $order,
			);

			// Get, with caching
			if ( ! $cache || ( false === ( $courses = get_transient( 'pcm_course_types' ) ) || isset( $_GET['refresh'] ) ) ) {
				$courses = get_posts( $args );
				if ( $cache ) {
					set_transient( 'pcm_course_types', $courses, 60*60*24 ); // Cache for 24 hours
				}
			}

		}

		return $courses;
	}

	/**
	 * Check if a user has booked a course (and not been denied)
	 *
	 * @since		0.1
	 * @param		int		$course_id	The ID of the course
	 * @param		int		$user_id	The ID of the user (defaults to current user ID;
	 * 									returns false if user isn't logged in)
	 * @return		bool
	 */
	public function user_has_booked_course( $course_id, $user_id = null ) {
		$course_booked = false;

		if ( ! ctype_digit( $user_id ) ) {

			// If not logged in, don't bother
			if ( is_user_logged_in() ) {

				// Try to get current user
				$user_id = get_current_user_id();

			}

		}

		if ( $user_id ) {

			// Get user's course details
			$courses = $this->get_user_courses( $user_id );

			// Check for booking
			if ( $courses && is_array( $courses ) ) {
				foreach ( $courses as $course ) {
					if ( $course['booking_status'] != 'denied' && in_array( $course_id, $course['course_id'] ) ) {
						$course_booked = true;
						break;
					}
				}
			}

		}

		return $course_booked;
	}

	/**
	 * Check if a user has completed a course
	 *
	 * @since		0.1
	 * @param		int		$course_id	The ID of the course
	 * @param		int		$user_id	The ID of the user (defaults to current user ID;
	 * 									returns false if user isn't logged in)
	 * @return		bool
	 */
	public function user_has_completed_course( $course_id, $user_id = null ) {
		$course_completed = false;

		if ( ! ctype_digit( $user_id ) ) {

			// If not logged in, don't bother
			if ( is_user_logged_in() ) {

				// Try to get current user
				$user_id = get_current_user_id();

			}

		}

		if ( $user_id ) {

			// Get user's course details
			$courses_completed = $this->get_user_courses_booked( $user_id, 'completed' );

			// Match?
			if ( in_array( $course_id, $courses_completed ) ) {
				$course_completed = true;
			}

		}

		return $course_completed;
	}

	/**
	 * Format course date
	 *
	 * @since	0.1
	 * @param	string	$start_date
	 * @param	string	$end_date
	 * @param	string	$sep
	 * @return	string
	 */
	public function format_course_date( $start_date, $end_date = null, $sep = ' &#150; ' ) {
		$date = '';
		if ( function_exists( 'slt_cf_reverse_date' ) ) {
			$date = slt_cf_reverse_date( $start_date );
			if ( $end_date && $start_date != $end_date ) {
				$date .= $sep . slt_cf_reverse_date( $end_date );
			}
		}
		return $date;
	}

	/**
	 * Undo magic quotes
	 *
	 * @since	0.1
	 * @param	string	$string
	 * @return	string
	 */
	public function undo_magic_quotes( $string ) {
		if ( is_string( $string ) ) {
			$string = str_replace( array( "\'", '\"' ), array( "'", '"' ), $string );
		}
		return $string;
	}

	/**
	 * Helper to string together multiple post titles
	 *
	 * @since	0.3
	 * @param	mixed		$ids	Integer or array
	 * @param	string		$sep
	 * @param	mixed		$link	'edit' | 'view' | false
	 * @return	string
	 */
	public function multiple_post_titles( $ids, $sep = ' / ', $link = false ) {
		$titles = array();
		if ( ! is_array( $ids ) ) {
			$ids = (array) $ids;
		}
		foreach ( $ids as $id ) {
			$title = get_the_title( $id );
			if ( $link ) {
				switch ( $link ) {
					case 'edit':
						$title = '<a href="' . get_edit_post_link( $id ) . '" title="' . __( 'Click to edit', $this->plugin_slug ) . '">' . $title . '</a>';
						break;
					case 'view':
						$title = '<a href="' . get_permalink( $id ) . '" title="' . __( 'Click to view', $this->plugin_slug ) . '">' . $title . '</a>';
						break;
				}
			}
			$titles[] = $title;
		}
		return implode( $sep, $titles );
	}

}
