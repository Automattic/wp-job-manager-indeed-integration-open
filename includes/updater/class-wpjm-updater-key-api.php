<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPJM_Updater_Key_API
 */
class WPJM_Updater_Key_API {
	private static $endpoint = 'http://wpjobmanager.com/?wc-api=wp_plugin_licencing_activation_api';

	/**
	 * Attempt to activate a plugin licence
	 * @return string JSON response
	 */
	public static function activate( $args ) {
		$defaults = array(
			'request'  => 'activate',
			'instance' => site_url(),
		);

		$args    = wp_parse_args( $defaults, $args );
		$request = wp_remote_get( self::$endpoint . '&' . http_build_query( $args, '', '&' ) );

		if ( is_wp_error( $request ) ) {
			return json_encode( array( 'error_code' => $request->get_error_code(), 'error' => $request->get_error_message() ) );
		}

		if ( wp_remote_retrieve_response_code( $request ) != 200 ) {
			return json_encode( array( 'error_code' => wp_remote_retrieve_response_code( $request ), 'error' => 'Error code: ' . wp_remote_retrieve_response_code( $request ) ) );
		}

		return wp_remote_retrieve_body( $request );
	}

	/**
	 * Attempt t deactivate a licence
	 */
	public static function deactivate( $args ) {
		$defaults = array(
			'request'  => 'deactivate',
			'instance' => site_url(),
		);

		$args    = wp_parse_args( $defaults, $args );
		$request = wp_remote_get( self::$endpoint . '&' . http_build_query( $args, '', '&' ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			return false;
		} else {
			return wp_remote_retrieve_body( $request );
		}
	}
}
