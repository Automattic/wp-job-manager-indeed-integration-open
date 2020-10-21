<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_Export
 */
class WP_Job_Manager_Indeed_Export {

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( get_option( 'job_manager_indeed_enable_feed', 1 ) ) {
			add_action( 'init', array( $this, 'add_jobs_feed' ) );
			add_filter( 'job_manager_locate_template', array( $this, 'verify_template' ), 10, 2 );
		}
	}

	/**
	 * If we detect the "indeed-job-feed" query variable, load our custom template
	 * file. This will check a child theme so it can be overwritten as well.
	 *
	 * @since WP Job Manager - Advanced Feed 1.0
	 *
	 * @return void
	 */
	public static function template_loader() {
		global $wp_query;
		get_job_manager_template( 'indeed-job-feed.php', array(), 'job_manager_indeed_integration', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
		exit();
	}

	/**
	 * Create the jobs custom feed
	 *
	 * @since WP Job Manager - Advanced Feed 1.0
	 *
	 * @return void
	 */
	public static function add_jobs_feed() {
		add_feed( 'indeed-job-feed', array( 'WP_Job_Manager_Indeed_Export', 'template_loader' ) );
	}

	/**
	 * Locate the Indeed job feed template.
	 *
	 * Template priority is:
	 *
	 *      yourtheme/wp-job-manager-indeed-integration/
	 *      yourtheme/job_manager_indeed_integration/
	 *      wp-job-manager-indeed-integration/templates/
	 *
	 * @since 2.2.2
	 *
	 * @param string $template      Template. Will be either:
	 *                              yourtheme/job_manager_indeed_integration/ OR
	 *                              wp-job-manager-indeed-integration/templates/.
	 * @param string $template_name Template name (i.e. indeed-job-feed.php).
	 * @return string Location of the Indeed job feed template.
	 */
	public static function verify_template( $template, $template_name ) {
		// Check for existence of template in yourtheme/wp-job-manager-indeed-integration/ and use it, if applicable.
		$new_template = locate_template( [ trailingslashit( 'wp-job-manager-indeed-integration' ) . $template_name ] );

		if ( $new_template ) {
			return $new_template;
		}

		return $template;
	}
}

new WP_Job_Manager_Indeed_Export();
