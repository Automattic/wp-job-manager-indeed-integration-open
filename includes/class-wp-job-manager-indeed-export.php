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
}

new WP_Job_Manager_Indeed_Export();