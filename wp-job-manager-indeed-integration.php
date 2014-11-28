<?php
/*
Plugin Name: WP Job Manager - Indeed Integration
Plugin URI: https://wpjobmanager.com/add-ons/indeed-integration/
Description: Query and show sponsored results from Indeed when listing jobs, list Indeed jobs via a shortcode, and export your job listings to Indeed via XML. Note: Indeed jobs will be displayed in list format linking offsite (without full descriptions).
Version: 2.0.17
Author: Mike Jolley
Author URI: http://mikejolley.com
Requires at least: 3.8
Tested up to: 4.0

	Copyright: 2013 Mike Jolley
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPJM_Updater' ) ) {
	include( 'includes/updater/class-wpjm-updater.php' );
}

/**
 * WP_Job_Manager_Indeed_Integration class.
 */
class WP_Job_Manager_Indeed_Integration extends WPJM_Updater {

	/**
	 * __construct function.
	 */
	public function __construct() {
		// Define constants
		define( 'JOB_MANAGER_INDEED_VERSION', '2.0.17' );
		define( 'JOB_MANAGER_INDEED_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_INDEED_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Add actions
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 12 );
		add_filter( 'job_manager_settings', array( $this, 'settings' ) );
		add_action( 'admin_footer-job_listing_page_job-manager-settings', array( $this, 'settings_js' ) );

		// Includes
		if ( get_option( 'job_manager_indeed_enable_backfill', 1 ) == 1 ) {
			include_once( 'includes/class-wp-job-manager-indeed-import.php' );
		}

		if ( get_option( 'job_manager_indeed_enable_feed', 1 ) == 1 ) {
			include_once( 'includes/class-wp-job-manager-indeed-export.php' );
		}

		// Install and uninstall
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( 'WP_Job_Manager_Indeed_Export', 'add_jobs_feed' ), 10 );
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), 'flush_rewrite_rules', 15 );

		// Init updates
		$this->init_updates( __FILE__ );
	}

	/**
	 * Runs on de-activation
	 */
	public function deactivate() {}

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager-indeed-integration' );
		load_textdomain( 'wp-job-manager-indeed-integration', WP_LANG_DIR . "/wp-job-manager-indeed-integration/wp-job-manager-indeed-integration-$locale.mo" );
		load_plugin_textdomain( 'wp-job-manager-indeed-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add Settings
	 * @param  array $settings
	 * @return array
	 */
	public function settings( $settings = array() ) {
		$settings['indeed_integration'] = array(
			__( 'Indeed Integration', 'wp-job-manager-indeed-integration' ),
			apply_filters(
				'wp_job_manager_indeed_integration_settings',
				array(
					array(
						'name' 		=> 'job_manager_indeed_publisher_id',
						'std' 		=> '',
						'label' 	=> __( 'Publisher ID', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'To show search results from Indeed you will need a publisher account. Obtain this here: https://ads.indeed.com/jobroll/signup', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_enable_feed',
						'std' 		=> 1,
						'label' 	=> __( 'Enable XML Feed', 'wp-job-manager-indeed-integration' ),
						'cb_label' 	=> __( 'Enable Indeed XML feed.', 'wp-job-manager-indeed-integration' ),
						'desc'		=> sprintf( __( 'The generated feed can be used to submit jobs to Indeed (see <a href="http://www.indeed.com/intl/en/xmlinfo.html">here</a>). Your feed will be found at: %s', 'wp-job-manager-indeed-integration' ), '<a href="' . home_url( '/indeed-job-feed/' ) . '">' . home_url( '/indeed-job-feed/' ) . '</a>' ),
						'type'      => 'checkbox'
					),

					array(
						'name' 		=> 'job_manager_indeed_enable_backfill',
						'std' 		=> 1,
						'label' 	=> __( 'Enable Backfill', 'wp-job-manager-indeed-integration' ),
						'cb_label' 	=> __( 'Enable backfilling jobs from Indeed', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Enabling this allows you to show sponsored job listings from Indeed within your own job lists.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'checkbox'
					),

					array(
						'name' 		=> 'job_manager_indeed_site_type',
						'std' 		=> '',
						'options'   => array(
							''         => __( 'All web sites', 'wp-job-manager-indeed-integration' ),
							'jobsite'  => __( 'Job boards only', 'wp-job-manager-indeed-integration' ),
							'employer' => __( 'Employer websites only', 'wp-job-manager-indeed-integration' ),
						),
						'label' 	=> __( 'Site type', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Choose where results should come from.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'select'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_query',
						'std' 		=> 'Web Developer',
						'label' 	=> __( 'Default query', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Enter terms to search for by default. By default terms are ANDed. Search for multiple terms at once by using the "or" keyword between each keyword. e.g. <code>Term1 or Term2</code>', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_location',
						'std' 		=> '',
						'label' 	=> __( 'Default location', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Enter a location to search for by default.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_type',
						'std' 		=> 'fulltime',
						'label' 	=> __( 'Default job type', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Choose which type of job to query by default.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'select',
						'options'   => array(
							'fulltime'   => __( 'Full time', 'wp-job-manager-indeed-integration' ),
							'parttime'   => __( 'Part time', 'wp-job-manager-indeed-integration' ),
							'contract'   => __( 'Contract', 'wp-job-manager-indeed-integration' ),
							'internship' => __( 'Internship', 'wp-job-manager-indeed-integration' ),
							'temporary'  => __( 'Temporary', 'wp-job-manager-indeed-integration' ),
						),
					),
					array(
						'name' 		=> 'job_manager_indeed_default_country',
						'std' 		=> 'us',
						'label' 	=> __( 'Default country', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Choose a default country to show jobs from. See https://ads.indeed.com/jobroll/xmlfeed for the full list of supported country codes.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_backfill',
						'std' 		=> 10,
						'label'     => __( 'Backfilling (no results)', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'If there are no jobs found, backfill with X jobs from Indeed instead. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_before_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill before jobs', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed above your job listings. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_after_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill after jobs', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed after the last page of your job listings. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_per_page',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill per page', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'For each page of jobs loaded, show a maximum of X jobs from Indeed. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_show_attribution',
						'std' 		=> 1,
						'label' 	=> __( 'Attribution', 'wp-job-manager-indeed-integration' ),
						'cb_label' 	=> __( 'Automatically show attribution', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Indeed require attribution when including their listings. Enable this to automatically display the attribution image.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'checkbox'
					),

					array(
						'name' 		=> 'job_manager_indeed_search_title_only',
						'std' 		=> 1,
						'label' 	=> __( 'Query titles', 'wp-job-manager-indeed-integration' ),
						'cb_label' 	=> __( 'Query job titles only', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Enabling this will search within Indeed job titles only. Otherwise the full description will be searched.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'checkbox'
					),
				)
			)
		);
		return $settings;
	}

	/**
	 * Some JS for the settings screen
	 */
	public function settings_js() {
		?>
		<script type="text/javascript">
			jQuery('input#setting-job_manager_indeed_enable_backfill').change(function() {

				var $options = jQuery('#setting-job_manager_indeed_site_type, #setting-job_manager_indeed_default_query, #setting-job_manager_indeed_default_location, #setting-job_manager_indeed_default_type, #setting-job_manager_indeed_default_country, #setting-job_manager_indeed_backfill, #setting-job_manager_indeed_before_jobs, #setting-job_manager_indeed_after_jobs, #setting-job_manager_indeed_per_page, #setting-job_manager_indeed_show_attribution');

				if ( jQuery(this).is(':checked') ) {
					$options.closest('tr').show();
				} else {
					$options.closest('tr').hide();
				}

			}).change();
		</script>
		<?php
	}
}

$GLOBALS['wp-job-manager-indeed-integration'] = new WP_Job_Manager_Indeed_Integration();