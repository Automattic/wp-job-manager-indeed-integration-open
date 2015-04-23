<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_Import
 *
 * @extends WP_Job_Manager_Importer
 */
class WP_Job_Manager_Indeed_Import extends WP_Job_Manager_Importer {

	/** @var string ID of this importer */
	protected $importer_id = 'indeed';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( get_option( 'job_manager_indeed_enable_backfill', 1 ) ) {
			WP_Job_Manager_Importers::register_importer( $this->importer_id, $this );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		}
	}

	/**
	 * Enqueue scripts
	 */
	public function wp_enqueue_scripts() {
		wp_enqueue_script( 'indeed-click-tracking' );
		wp_enqueue_style( 'job-manager-indeed', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/css/frontend.css' );
	}

	/**
	 * Get jobs for a particular request (page, type, and whether or not results need to be offset)
	 */
	public function get_jobs_for_request( $page, $request_type = 'backfill', $offset_before = false ) {
		// See how many listings to get
		switch ( $request_type ) {
			case "backfill" :
				$limit = get_option( 'job_manager_indeed_backfill' );
			break;
			case "before" :
				$limit = get_option( 'job_manager_indeed_before_jobs' );
			break;
			case "page" :
				$limit = get_option( 'job_manager_indeed_per_page' );
			break;
			case "after" :
				$limit = get_option( 'job_manager_indeed_after_jobs' );
			break;
		}

		if ( ! $limit ) {
			return WP_Job_Manager_Indeed_API::response();
		}

		if ( $limit > 25 ) {
			$limit = 25;
		}

		$types            = get_job_listing_types();
		$filter_job_types = array_filter( array_map( 'sanitize_title', (array) $_REQUEST['filter_job_type'] ) );
		$search_location  = sanitize_text_field( stripslashes( $_REQUEST['search_location'] ) );
		$search_keywords  = sanitize_text_field( stripslashes( $_REQUEST['search_keywords'] ) );
		$page             = $offset_before ? $page + 1 : $page;

		// Category
		$categories        = array();
		$search_categories = isset( $_REQUEST['search_categories'] ) ? array_filter( array_map( 'absint', (array) $_REQUEST['search_categories'] ) ) : array();
		if ( ! empty( $search_categories ) ) {
			foreach ( $search_categories as $term_id ) {
				$term         = get_term_by( 'id', absint( $term_id ), 'job_listing_category' );
				$search_keywords = ( $search_keywords ? $search_keywords . ' AND ' : '' ) . $term->name;
			}
		}

		// Regions integration
		if ( isset( $_REQUEST['form_data'] ) && taxonomy_exists( 'job_listing_region' ) ) {
			parse_str( $_REQUEST['form_data'], $post_data );
			if ( ! empty( $post_data['search_region'] ) ) {
				$term = get_term_by( 'id', absint( $post_data['search_region'] ), 'job_listing_region' );
				$search_location = $term->name;
			}
		}

		// See what type of jobs we are querying
		if ( sizeof( $filter_job_types ) !== sizeof( $types ) ) {
			$type = $filter_job_types[ 0 ];
			switch ( $type ) {
				case 'full-time' :
					$type = 'fulltime';
				break;
				case 'part-time' :
					$type = 'parttime';
				break;
				case 'internship' :
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

		// Before querying indeed, lets ensure the CO variable matches the location by using google geocoding
		$search_country = get_option( 'job_manager_indeed_default_country' );

		if ( $search_location && apply_filters( 'job_manager_indeed_geolocate_country', true ) ) {
			$address_data = WP_Job_Manager_Geocode::get_location_data( $search_location );
			if ( ! empty( $address_data['country_short'] ) ) {
				$search_country = $address_data['country_short'];
			}
		}

		return WP_Job_Manager_Indeed_API::get_jobs( array(
			'sort'  => 'relevance',
			'q'     => $search_keywords ? $search_keywords : get_option( 'job_manager_indeed_default_query' ),
			'l'     => $search_location ? $search_location : get_option( 'job_manager_indeed_default_location' ),
			'co'    => $search_country,
			'jt'    => $type,
			'limit' => $limit,
			'start' => $limit * ( $page - 1 )
		) );
	}
}

new WP_Job_Manager_Indeed_Import();