<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_API
 */
class WP_Job_Manager_Indeed_API {

	/** @var string API endpoint */
	private static $endpoint     = "http://api.indeed.com/ads/apisearch?";

	/**
	 * Get default args
	 */
	private static function get_default_args() {
		return array(
			'publisher' => get_option( 'job_manager_indeed_publisher_id' ),
			'userip'    => self::get_user_ip(),
			'useragent' => self::get_user_agent(),
			'jt'        => get_option( 'job_manager_indeed_default_type' ),
			'st'        => get_option( 'job_manager_indeed_site_type' ),
			'co'        => get_option( 'job_manager_indeed_default_country' ),
			'latlong'   => 1,
			'v'         => 2,
			'format'    => 'json',
			'sort'      => 'relevance',
			'radius'    => 25,
			'start'     => 0,
			'limit'     => 10
		);
	}

	/**
	 * Format args before sending them to the api
	 * @param  array $args
	 * @return array
	 */
	private static function format_args( $args ) {
		foreach ( $args as $key => $value ) {
			if ( method_exists( __CLASS__, 'format_arg_' . strtolower( $key ) ) ) {
				$args[ $key ] = call_user_func( __CLASS__ . "::format_arg_" . strtolower( $key ), $value );
			}
		}
		return $args;
	}

	/**
	 * Format search keyword
	 * @param  string $keyword
	 * @return string
	 */
	public static function format_arg_q( $keyword ) {
		if ( get_option( 'job_manager_indeed_search_title_only', 1 ) && $keyword ) {
			$keyword = 'title:(' . $keyword . ')';
		}
		return apply_filters( 'job_manager_indeed_import_format_keyword', $keyword );
	}

	/**
	 * Get the type we are querying
	 * @return string
	 */
	public static function get_mapped_job_type( $type ) {
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
			case 'all' :
				$type = '';
			break;
		}
		return $type;
	}

	/**
	 * Get jobs from the API
	 * @return array()
	 */
	public static function get_jobs( $args ) {
		$args           = self::format_args( apply_filters( 'job_manager_indeed_get_jobs_args', wp_parse_args( $args, self::get_default_args() ) ) );
		$transient_name = 'indeed_' . md5( json_encode( $args ) );
		$total_pages    = 0;
		$total_jobs     = 0;
		$jobs           = array();

		if ( false === ( $results = get_transient( $transient_name ) ) ) {
			$results = array();
			$result  = wp_remote_get( self::$endpoint . http_build_query( $args, '', '&' ), array( 'timeout' => 10 ) );

			if ( ! is_wp_error( $result ) && ! empty( $result['body'] ) ) {
				$results = json_decode( $result['body'], true );
				if ( empty( $results['results'] ) ) {
					return self::response(); // No results - don't cache
				}
				set_transient( $transient_name, $results, ( 60 * 60 * 24 ) );
			}
		}

		if ( $results && ! empty( $results['results'] ) ) {
			foreach ( $results['results'] as $result ) {
				$job            = self::format_job( $result );
				$job->type_slug = self::get_mapped_job_type( $args['jt'] );
				$job->type      = '';

				if ( $job->type_slug ) {
					$term = get_term_by( 'slug', $job->type_slug, 'job_listing_type' );
					if ( $term && ! is_wp_error( $term ) ) {
						$job->type = $term->name;
					} else {
						$job->type = __( $job->type_slug, 'wp-job-manager-indeed-integration' );
					}
				}

				$jobs[] = $job;
			}
			$total_jobs  = absint( $results['totalResults'] );
			$total_pages = ceil( $total_jobs / max( 25, $args['limit'] ) );
		} else {
			delete_transient( $transient_name );
		}

		return self::response( $total_pages, $total_jobs, $jobs );
	}

	/**
	 * Return a response containing jobs
	 * @param  integer $total_pages
	 * @param  integer $total_jobs
	 * @param  array   $jobs
	 * @return array
	 */
	public static function response( $total_pages = 0, $total_jobs = 0, $jobs = array() ) {
		return array(
			'total_pages' => $total_pages,
			'total_jobs'  => $total_jobs,
			'jobs'        => $jobs
		);
	}

	/**
	 * Return job in standard format
	 * @param  array $raw_job
	 * @return object
	 */
	private static function format_job( $raw_job ) {
		$job = array(
			'title'           => sanitize_text_field( $raw_job['jobtitle'] ),
			'company'         => sanitize_text_field( $raw_job['company'] ),
			'logo'            => apply_filters( 'job_manager_default_company_logo', JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' ),
			'tagline'         => sprintf( __( 'Source: %s', 'wp-job-manager-indeed-integration' ), $raw_job['source'] ),
			'url'             => esc_url_raw( $raw_job['url'] ),
			'location'        => sanitize_text_field( $raw_job['formattedLocation'] ),
			'latitude'        => isset( $raw_job['latitude'] ) ? sanitize_text_field( $raw_job['latitude'] ) : '',
			'longitude'       => isset( $raw_job['longitude'] ) ? sanitize_text_field( $raw_job['longitude'] ) : '',
			'timestamp'       => strtotime( $raw_job['date'] ),
			'link_attributes' => array(
				'onmousedown' => sanitize_text_field( $raw_job['onmousedown'] )
			)
		);
		return (object) $job;
	}

	/**
	 * Get the user IP address
	 * @return string
	 */
	private static function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		    $ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
		    $ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	/**
	 * Get the user agent
	 * @return string
	 */
	private static function get_user_agent() {
		return $_SERVER['HTTP_USER_AGENT'];
	}
}