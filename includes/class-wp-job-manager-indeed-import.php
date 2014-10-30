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
	 * Format search keyword
	 * @param  string $keyword
	 * @return string
	 */
	public function format_keyword( $keyword ) {
		if ( get_option( 'job_manager_indeed_search_title_only', 1 ) ) {
			$keyword = 'title:(' . $keyword . ')';
		}
		return apply_filters( 'job_manager_indeed_import_format_keyword', $keyword );
	}

	/**
	 * When getting results via ajax, show indeed listings
	 * @param  array $result
	 * @return array
	 */
	public function job_manager_get_listings_result( $result ) {
		$types            = get_job_listing_types();
		$filter_job_types = isset( $_POST['filter_job_type'] ) ? array_filter( array_map( 'sanitize_title', (array) $_POST['filter_job_type'] ) ) : null;
		$search_location  = sanitize_text_field( stripslashes( $_POST['search_location'] ) );
		$search_keywords  = sanitize_text_field( stripslashes( $_POST['search_keywords'] ) );
		$return_jobs      = false;
		$job_start        = 0;
		$found_jobs       = $result['found_jobs'];
		$backfilling      = false;
		$page             = isset( $_POST['page'] ) ? $_POST['page'] : 1;

		// Regions integration
		if ( isset( $_POST['form_data'] ) && taxonomy_exists( 'job_listing_region' ) ) {
			parse_str( $_POST['form_data'], $post_data );
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

		if ( $search_location ) {
			$address_data = WP_Job_Manager_Geocode::get_location_data( $search_location );
			if ( ! empty( $address_data['country_short'] ) ) {
				$search_country = $address_data['country_short'];
			}
		}

		if ( ! $search_keywords ) {
			$search_keywords = get_option( 'job_manager_indeed_default_query' );
		}

		$found_indeed_jobs = false;
		$api_args          = array(
			'sort'  => 'relevance',
			'q'     => $search_keywords ? $this->format_keyword( $search_keywords ) : '',
			'l'     => $search_location ? $search_location : get_option( 'job_manager_indeed_default_location' ),
			'co'    => $search_country,
			'jt'    => $type
		);

		// If local jobs were found...
		if ( $result['found_jobs'] ) {

			if ( 1 == $page && ( $limit = get_option( 'job_manager_indeed_before_jobs' ) ) ) {
				$indeed_jobs_html = $this->get_jobs_html( array_merge( $api_args, array(
					'limit' => $limit,
					'start' => $job_start
				) ) );

				if ( $indeed_jobs_html ) {
					$found_indeed_jobs    = true;
					$result['found_jobs'] = true;
					$result['html']       = $indeed_jobs_html . $result['html'];
				}
			}

			if ( ( $page == $result['max_num_pages'] && ( $limit = get_option( 'job_manager_indeed_after_jobs' ) ) ) || ( $page > 1 && $page != $result['max_num_pages'] && ( $limit = get_option( 'job_manager_indeed_per_page' ) ) ) ) {
				$indeed_jobs_html = $this->get_jobs_html( array_merge( $api_args, array(
					'limit' => $limit,
					'start' => get_option( 'job_manager_indeed_before_jobs' ) + ( get_option( 'job_manager_indeed_per_page' ) * $page )
				) ) );

				if ( $indeed_jobs_html ) {
					$found_indeed_jobs    = true;
					$result['found_jobs'] = true;
					$result['html']       = $result['html'] . $indeed_jobs_html;
				}
			}

		// No jobs were found
		} elseif ( $limit = get_option( 'job_manager_indeed_backfill' ) ) {
			$indeed_jobs_html = $this->get_jobs_html( array_merge( $api_args, array(
				'limit' => $limit,
				'start' => $limit * ( $page - 1 )
			) ) );

			if ( $indeed_jobs_html ) {
				$backfilling          = true;
				$found_indeed_jobs    = true;
				$result['found_jobs'] = true;
				$result['html']       = $indeed_jobs_html;
			}
		}

		// Pagination setup
		if ( $backfilling && $found_indeed_jobs ) {
			$result['max_num_pages'] = ceil( WP_Job_Manager_Indeed_API::$total_results / get_option( 'job_manager_indeed_backfill' ) );

			if ( function_exists( 'get_job_listing_pagination' ) ) {
				$result['pagination'] = get_job_listing_pagination( $result['max_num_pages'], absint( $_POST['page'] ) );
			}
		}

		return $result;
	}

	/**
	 * Query and get jobs html
	 * @param  array $api_args
	 * @return string
	 */
	public function get_jobs_html( $api_args ) {
		global $indeed_job;

		$jobs = WP_Job_Manager_Indeed_API::get_jobs( $api_args );

		if ( $jobs ) {

			ob_start();

			if ( get_option( 'job_manager_indeed_show_attribution' ) ) {
				get_job_manager_template_part( 'content', 'indeed-attribution', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}

			foreach ( $jobs as $indeed_job ) {
				$indeed_job            = (object) $indeed_job;
				$indeed_job->job_type  = $this->get_mapped_job_type( $api_args['jt'] );
				$indeed_job->longitude = isset( $indeed_job->longitude ) ? $indeed_job->longitude : '';
				$indeed_job->latitude  = isset( $indeed_job->latitude ) ? $indeed_job->latitude : '';

				$term = get_term_by( 'slug', $indeed_job->job_type, 'job_listing_type' );
				if ( $term && ! is_wp_error( $term ) ) {
					$indeed_job->job_type_name = $term->name;
				} else {
					$indeed_job->job_type_name = $indeed_job->job_type;
				}

				get_job_manager_template_part( 'content', 'indeed-job-listing', 'indeed', JOB_MANAGER_INDEED_PLUGIN_DIR . '/templates/' );
			}

			return ob_get_clean();
		}

		return '';
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
			'q'      => ( $q = get_option( 'job_manager_indeed_default_query' ) ) ? $this->format_keyword( $q ) : '', // Keywords
			'l'      => get_option( 'job_manager_indeed_default_location' ), // Location
			'jt'     => get_option( 'job_manager_indeed_default_type' ), // type
			'start'  => 0, // Offset
			'radius' => 25,
			'co'     => get_option( 'job_manager_indeed_default_country' )
		) ), $atts );

		$jobs     = WP_Job_Manager_Indeed_API::get_jobs( $api_args );

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

			if ( WP_Job_Manager_Indeed_API::$total_results > $api_args['limit'] ) {

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
		$api_args['q']      = ( $q = sanitize_text_field( isset( $api_args['q'] ) ? $api_args['q'] : '' ) ) ? $this->format_keyword( $q ) : '';
		$api_args['l']      = sanitize_text_field( isset( $api_args['l'] ) ? $api_args['l'] : '' );
		$api_args['jt']     = sanitize_text_field( isset( $api_args['jt'] ) ? $api_args['jt'] : '' );

		$api_args['start']  = $api_args['start'] + ( $api_args['limit'] * ( $page - 1 ) );

		$jobs               = WP_Job_Manager_Indeed_API::get_jobs( $api_args );

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
		$result['max_num_pages'] = ceil( WP_Job_Manager_Indeed_API::$total_results / $api_args['limit'] );

		echo '<!--WPJM-->';
		echo json_encode( apply_filters( 'job_manager_get_indeed_listings_result', $result ) );
		echo '<!--WPJM_END-->';

		die();
	}
}

new WP_Job_Manager_Indeed_Import();