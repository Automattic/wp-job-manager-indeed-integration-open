<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Job_Manager_Importers' ) ) {
	include_once( 'class-wp-job-manager-importers.php' );
}

if ( ! class_exists( 'WP_Job_Manager_Importer' ) ) {
	include_once( 'abstract-class-wp-job-manager-importer.php' );
}

/**
 * WP_Job_Manager_Importer_Integration
 *
 * @version  1.0.0
 */
class WP_Job_Manager_Importer_Integration {

	/**
	 * Init the importer integration
	 */
	public static function init() {
		add_filter( 'job_manager_get_listings_result', array( __CLASS__, 'job_manager_get_listings_result' ) );
	}

	/**
	 * Inject listings for the [jobs] shortcode
	 * @param  array $result
	 * @return array
	 */
	public static function job_manager_get_listings_result( $result ) {
		return $result['found_jobs'] ? self::inject_jobs( $result ) : self::backfill_jobs( $result );
	}

	/**
	 * Inject jobs into the job list
	 * @param  array $result
	 * @return array
	 */
	public static function inject_jobs( $result ) {
		$page          = absint( isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : 1 );
		$offset_before = false;

		if ( 1 === $page ) {
			foreach ( WP_Job_Manager_Importers::get_registered_importers() as $key => $importer ) {
				$jobs                = $importer->get_jobs_for_request( $page, 'before' );
				if ( $jobs['jobs'] ) {
					$result['html'] = self::get_jobs_html( $jobs['jobs'], $key ) . $result['html'];
					$offset_before  = true;
				}
			}
		}

		if ( $page == $result['max_num_pages'] ) {
			foreach ( WP_Job_Manager_Importers::get_registered_importers() as $key => $importer ) {
				$jobs               = $importer->get_jobs_for_request( $page, 'after', $offset_before );
				if ( $jobs['jobs'] ) {
					$result['html'] = $result['html'] . self::get_jobs_html( $jobs['jobs'], $key );
				}
			}
		} elseif ( $page > 1 && $page != $result['max_num_pages'] ) {
			foreach ( WP_Job_Manager_Importers::get_registered_importers() as $key => $importer ) {
				$jobs               = $importer->get_jobs_for_request( $page, 'page', $offset_before );
				if ( $jobs['jobs'] ) {
					$result['html'] = $result['html'] . self::get_jobs_html( $jobs['jobs'], $key );
				}
			}
		}

		return $result;
	}

	/**
	 * Backfill and replace job list
	 * @param  array $result
	 * @return array
	 */
	public static function backfill_jobs( $result ) {
		$page          = absint( isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : 1 );
		$max_page      = 0;
		$backfill_html = '';

		foreach ( WP_Job_Manager_Importers::get_registered_importers() as $key => $importer ) {
			$jobs     = $importer->get_jobs_for_request( $page, 'backfill' );
			$max_page = max( $max_page, $jobs['total_pages'] );

			if ( $jobs['jobs'] ) {
				$backfill_html .= self::get_jobs_html( $jobs['jobs'], $key );
			}
		}

		if ( $backfill_html ) {
			$result['found_jobs']    = true;
			$result['html']          = $backfill_html;
			$result['max_num_pages'] = $max_page;
			$result['pagination']    = get_job_listing_pagination( $result['max_num_pages'], absint( $_REQUEST['page'] ) );
		}

		return $result;
	}

	/**
	 * Get HTML for the list of jobs
	 * @param  array $jobs
	 * @return string
	 */
	public static function get_jobs_html( $jobs, $source ) {
		ob_start();

		if ( $jobs ) {
			do_action( 'job_manager_imported_jobs_start', $source );

			foreach ( $jobs as $job ) {
				// Prepare 'a' args
				$link_attributes = array();
				foreach ( $job->link_attributes as $key => $value ) {
					$link_attributes[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
				get_job_manager_template( 'content-imported-job-listing.php', $args = array(
					'source'          => $source,
					'job'             => $job,
					'logo'            =>  apply_filters( 'job_manager_default_company_logo', JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' ),
					'link_attributes' => implode( ' ', $link_attributes )
				), $source, dirname( __FILE__ ) . '/templates/' );
			}

			do_action( 'job_manager_imported_jobs_end', $source );
		}

		return ob_get_clean();
	}
}

WP_Job_Manager_Importer_Integration::init();