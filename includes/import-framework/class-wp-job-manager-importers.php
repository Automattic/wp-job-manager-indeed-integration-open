<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Import_Framework
 *
 * @version  1.0.0
 */
class WP_Job_Manager_Importers {

	/**
	 * Stores importers
	 * @var array
	 */
	private static $importers = array();

	/**
	 * Add an importer
	 * @param  string $source   Name of importer
	 * @param  object $instance Instance of importer
	 */
	public static function register_importer( $source, $instance ) {
		self::$importers[ $source ] = $instance;
	}

	/**
	 * Get all registered importers
	 * @return array
	 */
	public static function get_registered_importers() {
		return self::$importers;
	}
}