<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * MJ_Updater
 */
class MJ_Updater {

	private $plugin_slug = '';
	private $api_url = 'http://mikejolley.com/api/';

	/**
	 * init_updates
	 */
	public function init_updates( $file ) {
		$this->plugin_slug = basename( dirname( $file ) );

		add_action( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
	}

	/**
	 * Check for plugin updates
	 */
	public function check_for_updates( $checked_data ) {
		global $wp_version;

		if ( empty( $checked_data->checked ) )
			return $checked_data;

		$args = array(
			'slug'    => $this->plugin_slug,
			'version' => $checked_data->checked[ $this->plugin_slug . '/' . $this->plugin_slug .'.php' ],
		);

		$request_string = array(
			'body' => array(
				'action'  => 'basic_check',
				'request' => serialize( $args ),
				'api-key' => md5( get_bloginfo( 'url' ) )
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
		);

		// Start checking for an update
		$raw_response = wp_remote_post( $this->api_url, $request_string );

		if ( ! is_wp_error( $raw_response ) && ( $raw_response['response']['code'] == 200 ) )
			$response = unserialize( $raw_response['body'] );

		if ( is_object( $response ) && ! empty( $response ) ) // Feed the update data into WP updater
			$checked_data->response[ $this->plugin_slug . '/' . $this->plugin_slug .'.php'] = $response;

		return $checked_data;
	}

	/**
	 * Take over the Plugin info screen
	 */
	public function plugin_api_call( $def, $action, $args ) {
		global $wp_version;

		if ( ! isset( $args->slug ) || ( $args->slug != $this->plugin_slug ) )
			return false;

		// Get the current version
		$plugin_info     = get_site_transient('update_plugins');
		$current_version = $plugin_info->checked[ $this->plugin_slug . '/' . $this->plugin_slug .'.php' ];
		$args->version   = $current_version;

		$request_string = array(
			'body' => array(
				'action'  => $action,
				'request' => serialize( $args ),
				'api-key' => md5( get_bloginfo( 'url' ) )
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
		);

		$request = wp_remote_post( $this->api_url, $request_string );

		if ( is_wp_error( $request ) ) {
			$res = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $request->get_error_message() );
		} else {
			$res = unserialize( $request['body'] );

			if ($res === false)
				$res = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $request['body'] );
		}

		return $res;
	}

}