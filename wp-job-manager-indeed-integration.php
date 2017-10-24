<?php
/**
 * Plugin Name: WP Job Manager - Indeed Integration
 * Plugin URI: https://wpjobmanager.com/add-ons/indeed-integration/
 * Description: Query and show sponsored results from Indeed when listing jobs, list Indeed jobs via a shortcode, and export your job listings to Indeed via XML. Note: Indeed jobs will be displayed in list format linking offsite (without full descriptions).
 * Version: 2.2.0
 * Author: Automattic
 * Author URI: https://wpjobmanager.com
 * Requires at least: 4.1
 * Tested up to: 4.8
 *
 * WPJM-Product: wp-job-manager-indeed-integration
 *
 * Copyright: 2017 Automattic
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import Framework
if ( ! class_exists( 'WP_Job_Manager_Importer_Integration' ) ) {
	include_once( 'includes/import-framework/class-wp-job-manager-importer-integration.php' );
}

/**
 * WP_Job_Manager_Indeed_Integration class.
 */
class WP_Job_Manager_Indeed_Integration {
	const JOB_MANAGER_CORE_MIN_VERSION = '1.29.0';

	/**
	 * __construct function.
	 */
	public function __construct() {
		// Define constants
		define( 'JOB_MANAGER_INDEED_VERSION', '2.2.0' );
		define( 'JOB_MANAGER_INDEED_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_INDEED_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Install and uninstall
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( 'WP_Job_Manager_Indeed_Export', 'add_jobs_feed' ), 10 );
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), 'flush_rewrite_rules', 15 );

		// Set up startup actions
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ), 12 );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 13 );
		add_action( 'admin_notices', array( $this, 'version_check' ) );
	}

	/**
	 * Initializes plugin.
	 */
	public function init_plugin() {
		if ( ! class_exists( 'WP_Job_Manager' ) ) {
			return;
		}

		// Includes
		include_once( 'includes/class-wp-job-manager-indeed-import.php' );
		include_once( 'includes/class-wp-job-manager-indeed-api.php' );
		include_once( 'includes/class-wp-job-manager-indeed-shortcode.php' );
		include_once( 'includes/class-wp-job-manager-indeed-export.php' );

		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ), 5 );
		add_filter( 'job_manager_settings', array( $this, 'settings' ) );
		add_action( 'admin_footer-job_listing_page_job-manager-settings', array( $this, 'settings_js' ) );
		add_action( 'job_manager_imported_jobs_start', array( $this, 'add_attribution' ) );
	}

	/**
	 * Checks WPJM core version.
	 */
	public function version_check() {
		if ( ! class_exists( 'WP_Job_Manager' ) || ! defined( 'JOB_MANAGER_VERSION' ) ) {
			$screen = get_current_screen();
			if ( null !== $screen && 'plugins' === $screen->id ) {
				$this->display_error( __( '<em>WP Job Manager - Indeed Integration</em> requires WP Job Manager to be installed and activated.', 'wp-job-manager-indeed-integration' ) );
			}
		} elseif (
			/**
			 * Filters if WPJM core's version should be checked.
			 *
			 * @since 2.2.0
			 *
			 * @param bool   $do_check                       True if the add-on should do a core version check.
			 * @param string $minimum_required_core_version  Minimum version the plugin is reporting it requires.
			 */
			apply_filters( 'job_manager_addon_core_version_check', true, self::JOB_MANAGER_CORE_MIN_VERSION )
			&& version_compare( JOB_MANAGER_VERSION, self::JOB_MANAGER_CORE_MIN_VERSION, '<' )
		) {
			$this->display_error(  sprintf( __( '<em>WP Job Manager - Indeed Integration</em> requires WP Job Manager %s (you are using %s).', 'wp-job-manager-indeed-integration' ), self::JOB_MANAGER_CORE_MIN_VERSION, JOB_MANAGER_VERSION ) );
		}
	}

	/**
	 * Display error message notice in the admin.
	 *
	 * @param string $message
	 */
	private function display_error( $message ) {
		echo '<div class="error">';
		echo '<p>' . $message . '</p>';
		echo '</div>';
	}

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager-indeed-integration' );
		load_textdomain( 'wp-job-manager-indeed-integration', WP_LANG_DIR . "/wp-job-manager-indeed-integration/wp-job-manager-indeed-integration-$locale.mo" );
		load_plugin_textdomain( 'wp-job-manager-indeed-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Enqueue scripts
	 */
	public function wp_enqueue_scripts() {
		wp_register_script( 'indeed-click-tracking', '//gdc.indeed.com/ads/apiresults.js', array(), JOB_MANAGER_INDEED_VERSION, true );
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
						'name' 		=> 'job_manager_indeed_feed_limit',
						'std' 		=> '150',
						'label' 	=> __( 'XML Feed Job Limit', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Enter how many jobs should be listed in your feed maximum. Leave blank to show all (if the server has enough memory/resources).', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
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
						'desc'		=> __( 'Enter terms to search for by default. By default terms are ANDed. Search for multiple terms at once by using the "or" keyword between each keyword. e.g. <code>"Term 1" or "Term 2"</code>', 'wp-job-manager-indeed-integration' ),
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
						'std' 		=> 'all',
						'label' 	=> __( 'Default job type', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Choose which type of job to query by default.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'select',
						'options'   => array(
							'all'        => __( 'All', 'wp-job-manager-indeed-integration' ),
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
						'desc'		=> __( 'Choose a default country to show jobs from. See http://www.indeed.com/worldwide for the full list of supported country codes.', 'wp-job-manager-indeed-integration' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_backfill',
						'std' 		=> 10,
						'label'     => __( 'Backfilling (no results)', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'If there are no jobs found, backfill with X jobs from Indeed instead. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'       => version_compare( JOB_MANAGER_VERSION, '1.23.11', '>' ) ? 'number' : 'input',
						'attributes' => array(
							'min'       => 0,
							'max'       => 25
						)
					),
					array(
						'name' 		=> 'job_manager_indeed_before_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill before jobs', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed above your job listings. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'       => version_compare( JOB_MANAGER_VERSION, '1.23.11', '>' ) ? 'number' : 'input',
						'attributes' => array(
							'min'       => 0,
							'max'       => 25
						)
					),
					array(
						'name' 		=> 'job_manager_indeed_after_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill after jobs', 'wp-job-manager-indeed-integration' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed after the last page of your job listings. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'       => version_compare( JOB_MANAGER_VERSION, '1.23.11', '>' ) ? 'number' : 'input',
						'attributes' => array(
							'min'       => 0,
							'max'       => 25
						)
					),
					array(
						'name' 		 => 'job_manager_indeed_per_page',
						'std' 		 => '0',
						'label' 	 => __( 'Backfill per page', 'wp-job-manager-indeed-integration' ),
						'desc'		 => __( 'For each page of jobs loaded, show a maximum of X jobs from Indeed. Leave blank or set to 0 to disable.', 'wp-job-manager-indeed-integration' ),
						'type'       => version_compare( JOB_MANAGER_VERSION, '1.23.11', '>' ) ? 'number' : 'input',
						'attributes' => array(
							'min'       => 0,
							'max'       => 25
						)
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
			jQuery('input#setting-job_manager_indeed_enable_feed').change(function() {

				var $options = jQuery('#setting-job_manager_indeed_feed_limit');

				if ( jQuery(this).is(':checked') ) {
					$options.closest('tr').show();
				} else {
					$options.closest('tr').hide();
				}

			}).change();
		</script>
		<?php
	}

	/**
	 * Add attribution
	 */
	public function add_attribution( $source ) {
		if ( 'indeed' === $source && apply_filters( 'job_manager_indeed_show_attribution', true ) ) {
			get_job_manager_template_part( 'content', 'attribution', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
		}
	}
}

$GLOBALS['job_manager_indeed_integration'] = new WP_Job_Manager_Indeed_Integration();
