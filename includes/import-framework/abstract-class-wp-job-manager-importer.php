<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Importer abstract
 */
abstract class WP_Job_Manager_Importer {

	/** @var string Importer ID */
	protected $importer_id = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		WP_Job_Manager_Importers::register_importer( $this->importer_id, $this );
	}

	/**
	 * Get jobs for a particular request (page, type, and whether or not results need to be offset)
	 */
	public function get_jobs_for_request( $page, $request_type = 'backfill', $offset_before = false ) {}
}