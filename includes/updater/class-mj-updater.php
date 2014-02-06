<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * MJ_Updater
 */
class MJ_Updater {
	private $plugin_file = '';
	private $plugin_slug = '';
	private $api_url     = 'http://mikejolley.com/api/';
	private $errors      = array();

	/**
	 * Constructor
	 */
	public function __construct( $file, $key_required = false ) {
		$this->init_updates( $file, $key_required );
	}

	/**
	 * Init the updater
	 */
	public function init_updates( $file, $key_required = false ) {
		global $wp_version;

		if ( ! is_admin() ) {
			return;
		}
		$this->plugin_slug = basename( dirname( $file ) );
		$this->plugin_file = $file;

		add_action( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );

		if ( $key_required ) {
			$valid_key = get_option( $this->plugin_slug . '_licence_key' );
			if ( ! $valid_key ) {

				// Posted key?
				if ( ! empty( $_POST[ $this->plugin_slug . '_licence_key' ] ) ) {
					$licence_key    = sanitize_text_field( $_POST[ $this->plugin_slug . '_licence_key' ] );
					$request_string = array(
						'body' => array(
							'action'      => 'validate_licence_key',
							'licence_key' => $licence_key,
							'plugin_slug' => $this->plugin_slug,
							'api-key'     => md5( get_bloginfo( 'url' ) )
						),
						'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
					);
					$request         = wp_remote_post( $this->api_url, $request_string );

					if ( is_wp_error( $request ) ) {
						$this->errors[] = __( 'Unable to reach licence key API. Please try again later.' );
					} elseif ( $request['body'] == 'true' ) {





					} else {
						$this->errors[] = sanitize_text_field( $request['body'] );
					}
				}

				add_action( 'admin_notices', array( $this, 'key_notice' ) );
				add_action( 'admin_print_styles-plugins.php', array( $this, 'styles' ) );
				add_action( 'after_plugin_row', array( $this, 'key_input' ) );
			}
		}
	}

	/**
	 * Show a notice prompting the user to update
	 */
	public function key_notice() {
		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $error ) {
				?><div class="error">
					<p><?php echo esc_html( $error ); ?></p>
				</div><?php
			}
		} else {
			?><div class="updated">
				<p><?php printf( __( 'To recieve updates for <code>%s</code>, please enter your licence key <a href="%s">on the plugin page</a>.' ), esc_html( $this->plugin_slug ), esc_url( admin_url( 'plugins.php' ) ) ); ?></p>
			</div><?php
		}
	}

	/**
	 * Enqueue admin styles
	 */
	public function styles() {
		wp_enqueue_style( 'mj-updater-styles', plugins_url( basename( plugin_dir_path( $this->plugin_file ) ), basename( $this->plugin_file ) ) . '/includes/updater/assets/css/admin.css' );
	}

	/**
	 * Show the input for the licence key
	 */
	public function key_input( $file ) {
		if ( basename( dirname( $file ) ) == $this->plugin_slug ) {
			?><tr id="<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key_row" class="active plugin-update-tr mj-updater-licence-key-tr">
				<td class="plugin-update" colspan="3">
					<div class="mj-updater-licence-key">
						<label><?php _e( 'Licence key' ); ?>:</label>
						<input type="text" name="<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key" />
						<span class="description"><?php _e( 'Enter your key and hit return. A valid licence key is required for automatic updates.' ); ?></span>
					</div>
				</td>
				<script>
					jQuery(function(){
						jQuery('tr#<?php echo esc_attr( $this->plugin_slug ); ?>').addClass('mj-updater-licenced');
					});
				</script>
			</tr><?php
		}
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
			$response = maybe_unserialize( $raw_response['body'] );

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