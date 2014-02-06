<?php
/*
Plugin Name: WP Job Manager - Indeed Integration
Plugin URI: http://mikejolley.com
Description: Query and show sponsored results from Indeed when listing jobs, or list only Indeed jobs via a shortcode. Note: Jobs will be displayed in list format linking offsite (without detailed views/descriptions). Uses https://ads.indeed.com/jobroll/xmlfeed
Version: 1.0.0
Author: Mike Jolley
Author URI: http://mikejolley.com
Requires at least: 3.5
Tested up to: 3.8

	Copyright: 2013 Mike Jolley
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

if ( ! class_exists( 'MJ_Updater' ) )
	include( 'includes/updater/class-mj-updater.php' );

/**
 * WP_Job_Manager_Indeed_Integration class.
 */
class WP_Job_Manager_Indeed_Integration extends MJ_Updater {

	/**
	 * __construct function.
	 */
	public function __construct() {
		// Define constants
		define( 'JOB_MANAGER_INDEED_VERSION', '1.0.0' );
		define( 'JOB_MANAGER_INDEED_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_INDEED_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Includes
		include_once( 'includes/class-wp-job-manager-indeed-api.php' );

		// Add actions
		add_action( 'init', array( $this, 'init' ), 12 );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_filter( 'job_manager_settings', array( $this, 'settings' ) );
		add_filter( 'job_manager_get_listings_result', array( $this, 'job_manager_get_listings_result' ) );

		// Add shortcodes
		add_shortcode( 'indeed_jobs', array( $this, 'output_indeed_jobs' ) );

		// Init updates
		$this->init_updates( __FILE__, true );
	}

	/**
	 * Localisation
	 */
	public function init() {
		load_plugin_textdomain( 'job_manager_indeed', false, dirname( plugin_basename( __FILE__ ) ) );
	}

	/**
	 * Enqueue scripts
	 */
	public function scripts() {
		wp_enqueue_style( 'job-manager-indeed', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/css/frontend.css' );
		wp_enqueue_script( 'indeed-click-tracking', '//gdc.indeed.com/ads/apiresults.js', array(), '1.0' );
	}

	/**
	 * Add Settings
	 * @param  array $settings
	 * @return array
	 */
	public function settings( $settings = array() ) {
		$settings['indeed_integration'] = array(
			__( 'Indeed Integration', 'job_manager_indeed' ),
			apply_filters(
				'wp_job_manager_indeed_integration_settings',
				array(
					array(
						'name' 		=> 'job_manager_indeed_publisher_id',
						'std' 		=> '',
						'label' 	=> __( 'Publisher ID', 'job_manager_indeed' ),
						'desc'		=> __( 'To show search results from Indeed you will need a publisher account. Obtain this here: https://ads.indeed.com/jobroll/signup', 'job_manager_indeed' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_site_type',
						'std' 		=> '',
						'options'   => array(
							''         => __( 'All web sites', 'job_manager_indeed' ),
							'jobsite'  => __( 'Job boards only', 'job_manager_indeed' ),
							'employer' => __( 'Employer websites only', 'job_manager_indeed' ),
						),
						'label' 	=> __( 'Site type', 'job_manager_indeed' ),
						'desc'		=> __( 'Choose where results should come from.', 'job_manager_indeed' ),
						'type'      => 'select'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_query',
						'std' 		=> 'Web Developer',
						'label' 	=> __( 'Default query', 'job_manager_indeed' ),
						'desc'		=> __( 'Enter terms to search for by default. By default terms are ANDed.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_location',
						'std' 		=> '',
						'label' 	=> __( 'Default location', 'job_manager_indeed' ),
						'desc'		=> __( 'Enter a location to search for by default.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_default_type',
						'std' 		=> 'fulltime',
						'label' 	=> __( 'Default job type', 'job_manager_indeed' ),
						'desc'		=> __( 'Choose which type of job to query by default.', 'job_manager_indeed' ),
						'type'      => 'select',
						'options'   => array(
							'fulltime'   => __( 'Full time', 'job_manager_indeed' ),
							'parttime'   => __( 'Part time', 'job_manager_indeed' ),
							'contract'   => __( 'Contract', 'job_manager_indeed' ),
							'internship' => __( 'Internship', 'job_manager_indeed' ),
							'temporary'  => __( 'Temporary', 'job_manager_indeed' ),
						),
					),
					array(
						'name' 		=> 'job_manager_indeed_default_country',
						'std' 		=> 'us',
						'label' 	=> __( 'Default country', 'job_manager_indeed' ),
						'desc'		=> __( 'Choose a default country to show jobs from. See https://ads.indeed.com/jobroll/xmlfeed for the full list of supported country codes.', 'job_manager_indeed' ),
						'type'      => 'input'
					),

					array(
						'name' 		=> 'job_manager_indeed_backfill',
						'std' 		=> 10,
						'label'     => __( 'Backfilling (no results)', 'job_manager_indeed' ),
						'desc'		=> __( 'If there are no jobs found, backfill with X jobs from Indeed instead. Leave blank or set to 0 to disable.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_before_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill before jobs', 'job_manager_indeed' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed above your job listings. Leave blank or set to 0 to disable.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_after_jobs',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill after jobs', 'job_manager_indeed' ),
						'desc'		=> __( 'Show a maximum of X jobs from Indeed after the last page of your job listings. Leave blank or set to 0 to disable.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					array(
						'name' 		=> 'job_manager_indeed_per_page',
						'std' 		=> '0',
						'label' 	=> __( 'Backfill per page', 'job_manager_indeed' ),
						'desc'		=> __( 'For each page of jobs loaded, show a maximum of X jobs from Indeed. Leave blank or set to 0 to disable.', 'job_manager_indeed' ),
						'type'      => 'input'
					),
					
					array(
						'name' 		=> 'job_manager_indeed_show_attribution',
						'std' 		=> 1,
						'label' 	=> __( 'Attribution', 'job_manager_indeed' ),
						'cb_label' 	=> __( 'Automatically show attribution', 'job_manager_indeed' ),
						'desc'		=> __( 'Indeed require attribution when including their listings. Enable this to automatically display the attribution image.', 'job_manager_indeed' ),
						'type'      => 'checkbox'
					)
				)
			)
		);
		return $settings;
	}

	/**
	 * Get the type we are querying
	 * @return string
	 */
	public function get_mapped_job_type( $type ) {
		if ( ! $type ) {
			$type = get_option( 'job_manager_indeed_default_type' );
		}
		switch ( $type ) {
			case 'fulltime' :
				$type = 'full-time';
			break;
			case 'parttime' :
				$type = 'part-time';
			break;
			case 'contract' :
				$type = 'freelance';
			break;
		}
		return $type;
	}

	/**
	 * When getting results via ajax, show indeed listings
	 * @param  array $result
	 * @return array
	 */
	public function job_manager_get_listings_result( $result ) {
		global $indeed_job, $indeed_query_count;

		ob_start();

		$types            = get_job_listing_types();
		$filter_job_types = isset( $_POST['filter_job_type'] ) ? array_filter( array_map( 'sanitize_title', (array) $_POST['filter_job_type'] ) ) : null;
		$search_location  = sanitize_text_field( stripslashes( $_POST['search_location'] ) );
		$search_keywords  = sanitize_text_field( stripslashes( $_POST['search_keywords'] ) );
		$return_jobs      = false;
		$insert_jobs      = 'before';
		$job_start        = 0;

		// If local jobs were found
		if ( $result['found_jobs'] ) {

			$page = isset( $_POST['page'] ) ? $_POST['page'] : 1;

			if ( $page == 1 && get_option( 'job_manager_indeed_before_jobs' ) ) {

				$return_jobs = get_option( 'job_manager_indeed_before_jobs' );

			} elseif ( $page == $result['max_num_pages'] && get_option( 'job_manager_indeed_after_jobs' ) ) {

				$insert_jobs = 'after';
				$job_start   = get_option( 'job_manager_indeed_before_jobs' ) + ( get_option( 'job_manager_indeed_per_page' ) * $page );
				$return_jobs = get_option( 'job_manager_indeed_after_jobs' );

			} elseif ( $page > 1 && $page != $result['max_num_pages'] && get_option( 'job_manager_indeed_per_page' ) ) {

				$job_start   = get_option( 'job_manager_indeed_before_jobs' ) + ( get_option( 'job_manager_indeed_per_page' ) * $page );
				$return_jobs = get_option( 'job_manager_indeed_per_page' );

			}

		// No jobs were found
		} elseif ( get_option( 'job_manager_indeed_backfill' ) ) {
			$return_jobs = get_option( 'job_manager_indeed_backfill' );
		}

		if ( ! $return_jobs ) {
			return $result;
		}

		if ( sizeof( $filter_job_types ) !== sizeof( $types ) ) {
			$type = $filter_job_types[ 0 ];
			switch ( $type ) {
				case 'full-time' :
					$type = 'fulltime';
				break;
				case 'part-time' :
					$type = 'parttime';
				break;
				case 'intership' :
				case 'temporary' :
				break;
				case 'freelance' :
					$type = 'contract';
				break;
				default :
					$type = get_option( 'job_manager_indeed_default_type' );
				break;
			}
		} else {
			$type = get_option( 'job_manager_indeed_default_type' );
		}

		$api_args = array(
			'limit' => $return_jobs,
			'sort'  => $search_location || $search_keywords ? 'relevance' : 'date',
			'q'     => $search_keywords ? $search_keywords : get_option( 'job_manager_indeed_default_query' ),
			'l'     => $search_location ? $search_location : get_option( 'job_manager_indeed_default_location' ),
			'jt'    => $type,
			'start' => $job_start
		);
		$api      = new WP_Job_Manager_Indeed_API();
		$jobs     = $api->get_jobs( $api_args );

		if ( $jobs ) {

			if ( get_option( 'job_manager_indeed_show_attribution' ) ) {
				get_job_manager_template_part( 'content', 'indeed-attribution', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}

			foreach ( $jobs as $indeed_job ) {
				$indeed_job           = (object) $indeed_job;
				$indeed_job->job_type = $this->get_mapped_job_type( $type );

				$term = get_term_by( 'slug', $indeed_job->job_type, 'job_listing_type' );
				if ( $term && ! is_wp_error( $term ) ) {
					$indeed_job->job_type_name = $term->name;
				} else {
					$indeed_job->job_type_name = $indeed_job->job_type;
				}

				get_job_manager_template_part( 'content', 'indeed-job-listing', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}

			if ( $result['found_jobs'] ) {
				if ( $insert_jobs == 'before' ) {
					$result['html'] = ob_get_clean() . $result['html'];
				} else {
					$result['html'] .= ob_get_clean();
				}
			} else {
				$result['html'] = ob_get_clean();
			}
		}
		return $result;
	}

	/**
	 * Indeed jobs shortcode
	 *
	 * @param mixed $atts
	 */
	public function output_indeed_jobs( $atts ) {
		global $indeed_job;
		ob_start();

		$api_args = shortcode_atts( apply_filters( 'job_manager_output_indeed_jobs_defaults', array(
			'limit'  => 10, // Limit results
			'sort'   => 'date', // relevance or date
			'q'      => get_option( 'job_manager_indeed_default_query' ), // Keywords
			'l'      => get_option( 'job_manager_indeed_default_location' ), // Location
			'jt'     => get_option( 'job_manager_indeed_default_type' ), // type
			'start'  => 0, // Offset
			'radius' => 25,
		) ), $atts );

		$api      = new WP_Job_Manager_Indeed_API();
		$jobs     = $api->get_jobs( $api_args );

		if ( $jobs ) {
			echo '<ul class="job_listings">';
			foreach ( $jobs as $indeed_job ) {
				$indeed_job           = (object) $indeed_job;
				$indeed_job->job_type = $this->get_mapped_job_type( $api_args['jt'] );

				$term = get_term_by( 'slug', $indeed_job->job_type, 'job_listing_type' );
				if ( $term && ! is_wp_error( $term ) ) {
					$indeed_job->job_type_name = $term->name;
				} else {
					$indeed_job->job_type_name = $indeed_job->job_type;
				}

				get_job_manager_template_part( 'content', 'indeed-job-listing', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}
			if ( get_option( 'job_manager_indeed_show_attribution' ) ) {
				get_job_manager_template_part( 'content', 'indeed-attribution', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}
			echo '</ul>';
		}

		return '<div class="indeed_job_listings job_listings">' . ob_get_clean() . '</div>';
	}	
}

$GLOBALS['job_manager_indeed'] = new WP_Job_Manager_Indeed_Integration();