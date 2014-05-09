<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_Export
 */
class WP_Job_Manager_Indeed_Import {

	/**
	 * __construct function.
	 */
	public function __construct() {
		// Includes
		include_once( 'class-wp-job-manager-indeed-api.php' );

		// Add actions
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_filter( 'job_manager_get_listings_result', array( $this, 'job_manager_get_listings_result' ) );

		// Add shortcodes
		add_shortcode( 'indeed_jobs', array( $this, 'output_indeed_jobs' ) );
		add_action( 'wp_ajax_job_manager_get_indeed_listings', array( $this, 'ajax_get_indeed_listings' ) );
		add_action( 'wp_ajax_nopriv_job_manager_get_indeed_listings', array( $this, 'ajax_get_indeed_listings' ) );
	}

	/**
	 * Enqueue scripts
	 */
	public function scripts() {
		wp_enqueue_style( 'job-manager-indeed', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/css/frontend.css' );
		wp_enqueue_script( 'indeed-click-tracking', '//gdc.indeed.com/ads/apiresults.js', array(), '1.0' );
		wp_register_script( 'wp-job-manager-indeed-jobs', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/js/indeed-jobs.js', array( 'jquery' ), JOB_MANAGER_INDEED_VERSION, true );

		wp_localize_script( 'wp-job-manager-indeed-jobs', 'job_manager_indeed_jobs', array(
			'ajax_url' => admin_url('admin-ajax.php')
		) );
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
		$found_jobs       = $result['found_jobs'];
		$backfilling      = false;
		$page             = isset( $_POST['page'] ) ? $_POST['page'] : 1;

		// If local jobs were found
		if ( $result['found_jobs'] ) {

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
			$backfilling = true;
			$job_start   = $return_jobs * ( $page - 1 );
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

		if ( $search_location ) {
			$address_data = WP_Job_Manager_Geocode::get_location_data( $search_location );
			if ( ! empty( $address_data['country_short'] ) ) {
				$search_country = $address_data['country_short'];
			}
		} 
		
		$api_args = array(
			'limit' => $return_jobs,
			'sort'  => 'relevance',
			'q'     => 'title:(' . ( $search_keywords ? $search_keywords : get_option( 'job_manager_indeed_default_query' ) ) . ')',
			'l'     => $search_location ? $search_location : get_option( 'job_manager_indeed_default_location' ),
			'co'    => $search_country,
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

			if ( $found_jobs ) {
				if ( $insert_jobs == 'before' ) {
					$result['html'] = ob_get_clean() . $result['html'];
				} else {
					$result['html'] .= ob_get_clean();
				}
			} else {
				$result['html'] = ob_get_clean();
			}
		}

		// Pagination setup
		if ( $backfilling && $return_jobs ) {
			$result['max_num_pages'] = ceil( $api->total_results / $return_jobs );
			$result['found_jobs']    = true;
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
			'q'      => 'title:(' . get_option( 'job_manager_indeed_default_query' ) . ')', // Keywords
			'l'      => get_option( 'job_manager_indeed_default_location' ), // Location
			'jt'     => get_option( 'job_manager_indeed_default_type' ), // type
			'start'  => 0, // Offset
			'radius' => 25,
			'co'     => get_option( 'job_manager_indeed_default_country' )
		) ), $atts );

		$api      = new WP_Job_Manager_Indeed_API();
		$jobs     = $api->get_jobs( $api_args );

		if ( $jobs ) {
			echo '<ul class="job_listings">';
			if ( get_option( 'job_manager_indeed_show_attribution' ) ) {
				get_job_manager_template_part( 'content', 'indeed-attribution', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}
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
			echo '</ul>';

			if ( $api->total_results > $api_args['limit'] ) {

				wp_enqueue_script( 'wp-job-manager-indeed-jobs' );

				echo '<a class="load_more_indeed_jobs load_more_jobs" href="#"><strong>' . __( 'Load more job listings', 'wp-job-manager' ) . '</strong></a>';
			}
		}

		return '<div class="indeed_job_listings job_listings" data-api_args="' . esc_attr( json_encode( $api_args ) ) . '">' . ob_get_clean() . '</div>';
	}

	/**
	 * Get listings via ajax
	 */
	public function ajax_get_indeed_listings() {
		global $indeed_job;
		
		$result             = array();
		$page               = absint( $_POST['page'] );
		$api_args           = (array) $_POST['api_args'];
		
		$api_args['limit']  = absint( isset( $api_args['limit'] ) ? $api_args['limit'] : 10 );
		$api_args['start']  = absint( isset( $api_args['start'] ) ? $api_args['start'] : 0 );
		$api_args['radius'] = absint( isset( $api_args['radius'] ) ? $api_args['radius'] : 25 );
		$api_args['sort']   = sanitize_text_field( isset( $api_args['sort'] ) ? $api_args['sort'] : 'relevance' );
		$api_args['q']      = 'title:(' . sanitize_text_field( isset( $api_args['q'] ) ? $api_args['q'] : '' ) . ')';
		$api_args['l']      = sanitize_text_field( isset( $api_args['l'] ) ? $api_args['l'] : '' );
		$api_args['jt']     = sanitize_text_field( isset( $api_args['jt'] ) ? $api_args['jt'] : '' );
		
		$api_args['start']  = $api_args['start'] + ( $api_args['limit'] * ( $page - 1 ) );
		
		$api                = new WP_Job_Manager_Indeed_API();
		$jobs               = $api->get_jobs( $api_args );

		ob_start();

		$result['found_jobs'] = false;

		if ( $jobs ) {
			$result['found_jobs'] = true;
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
		}

		$result['html']          = ob_get_clean();
		$result['max_num_pages'] = ceil( $api->total_results / $api_args['limit'] );

		echo '<!--WPJM-->';
		echo json_encode( apply_filters( 'job_manager_get_indeed_listings_result', $result ) );
		echo '<!--WPJM_END-->';

		die();
	}
}

new WP_Job_Manager_Indeed_Import();