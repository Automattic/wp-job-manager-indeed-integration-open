<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_API
 */
class WP_Job_Manager_Indeed_API {
	private $endpoint     = "http://api.indeed.com/ads/apisearch?";
	private $default_args = array(
		'v'         => 2,
		'format'    => 'json',
		'sort'      => 'relevance',
		'radius'    => 25,
		'start'     => 0,
		'limit'     => 10
	);
	public $total_results = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->default_args['publisher'] = get_option( 'job_manager_indeed_publisher_id' );
		$this->default_args['userip']    = $this->get_user_ip();
		$this->default_args['useragent'] = $this->get_user_agent();
		$this->default_args['jt']        = get_option( 'job_manager_indeed_default_type' );
		$this->default_args['st']        = get_option( 'job_manager_indeed_site_type' );
		$this->default_args['co']        = get_option( 'job_manager_indeed_default_country' );
	}

	/**
	 * Get the user IP address
	 * @return string
	 */
	private function get_user_ip() {
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
	private function get_user_agent() {
		return $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Get jobs from the API
	 * @return array()
	 */
	public function get_jobs( $args ) {
		$args           = wp_parse_args( $args, $this->default_args );
		$transient_name = 'indeed_' . md5( json_encode( $args ) );

		if ( false === ( $results = get_transient( $transient_name ) ) ) {
			$results = array();
			$result  = wp_remote_get( $this->endpoint . http_build_query( $args, '', '&' ) );
			
			if ( ! is_wp_error( $result ) && ! empty( $result['body'] ) ) {
				$results = (array) json_decode( $result['body'] );
			}

			// Cache for 1 day
			if ( $results ) {
				set_transient( $transient_name, $results, ( 60 * 60 * 24 ) );
			}
		}

		if ( isset( $results['totalResults'] ) ) {
			$this->total_results = absint( $results['totalResults'] );
		}

		return isset( $results['results'] ) ? $results['results'] : $results;
	}
}