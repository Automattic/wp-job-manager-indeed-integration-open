<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Indeed_Shortcode
 */
class WP_Job_Manager_Indeed_Shortcode {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_job_manager_get_indeed_listings', array( $this, 'get_jobs_for_shortcode' ) );
		add_action( 'wp_ajax_nopriv_job_manager_get_indeed_listings', array( $this, 'get_jobs_for_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_shortcode( 'indeed_jobs', array( $this, 'indeed_jobs_shortcode' ) );
	}

	/**
	 * Enqueue scripts
	 */
	public function wp_enqueue_scripts() {
		wp_enqueue_style( 'job-manager-indeed', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/css/frontend.css' );
		wp_register_script( 'wp-job-manager-indeed-jobs', JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/js/indeed-jobs.js', array( 'jquery', 'wp-job-manager-ajax-filters', 'indeed-click-tracking' ), JOB_MANAGER_INDEED_VERSION, true );
		wp_localize_script( 'wp-job-manager-indeed-jobs', 'job_manager_indeed_jobs', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Get listings via ajax
	 */
	public function get_jobs_for_shortcode() {
		ob_start();

		add_filter( 'job_manager_indeed_show_attribution', '__return_false' );

		$api_args          = array();
		$page              = absint( $_REQUEST['page'] );

		foreach ( (array) $_REQUEST['api_args'] as $key => $value ) {
			$api_args[ $key ] = sanitize_text_field( $value );
		}

		$api_args['start'] = $api_args['start'] + ( max( 25, $api_args['limit'] ) * ( $page - 1 ) );

		if ( ! empty( $_REQUEST['orderby'] ) && 'date' === $_REQUEST['orderby'] ) {
			$api_args['sort'] = 'date';
		} elseif ( ! empty( $_REQUEST['orderby'] ) ) {
			$api_args['sort'] = 'relevance';
		}

		if ( ( $jobs = WP_Job_Manager_Indeed_API::get_jobs( $api_args ) ) && $jobs['jobs'] ) {
			echo WP_Job_Manager_Importer_Integration::get_jobs_html( $jobs['jobs'], 'indeed' );
		}

		$result                  = array();
		$result['html']          = ob_get_clean();
		$result['found_jobs']    = ! empty( $jobs['jobs'] );
		$result['max_num_pages'] = $jobs['total_pages'];

		echo '<!--WPJM-->';
		echo json_encode( $result );
		echo '<!--WPJM_END-->';

		die();
	}

	/**
	 * Indeed jobs shortcode
	 *
	 * @param mixed $atts
	 */
	public function indeed_jobs_shortcode( $atts ) {
		ob_start();

		$api_args = shortcode_atts( apply_filters( 'job_manager_output_indeed_jobs_defaults', array(
			'limit'  => 10,
			'sort'   => 'date',
			'q'      => get_option( 'job_manager_indeed_default_query' ),
			'l'      => get_option( 'job_manager_indeed_default_location' ),
			'jt'     => get_option( 'job_manager_indeed_default_type' ),
			'start'  => 0,
			'radius' => 25,
			'co'     => get_option( 'job_manager_indeed_default_country' )
		) ), $atts );

		$jobs = WP_Job_Manager_Indeed_API::get_jobs( $api_args );

		if ( $jobs['jobs'] ) {
			echo '<ul class="job_listings">';
			echo WP_Job_Manager_Importer_Integration::get_jobs_html( $jobs['jobs'], 'indeed' );
			echo '</ul>';

			if ( $jobs['total_pages'] > 1 ) {
				wp_enqueue_script( 'wp-job-manager-indeed-jobs' );
				echo '<a class="load_more_indeed_jobs load_more_jobs" href="#"><strong>' . __( 'Load more job listings', 'wp-job-manager-indeed-integration' ) . '</strong></a>';
			}
		}

		return '<div class="indeed_job_listings job_listings" data-api_args="' . esc_attr( json_encode( $api_args ) ) . '">' . ob_get_clean() . '</div>';
	}
}

new WP_Job_Manager_Indeed_Shortcode();