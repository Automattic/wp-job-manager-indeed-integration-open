<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPJM_Updater
 */
class WPJM_Updater {
	private $plugin_name;
	private $plugin_file;
	private $plugin_slug;
	private $api_url           = 'https://wpjobmanager.com/?wc-api=wp_plugin_licencing_update_api';
	private $errors            = array();
	private $plugin_data;

	/**
	 * Constructor, used if called directly.
	 */
	public function __construct( $file ) {
		$this->init_updates( $file );
	}

	/**
	 * Init the updater
	 */
	public function init_updates( $file ) {
		$this->plugin_file = $file;
		$this->plugin_slug = basename( dirname( $this->plugin_file ) );
		$this->plugin_name = $this->plugin_slug . '/' . $this->plugin_slug . '.php';

		register_activation_hook( basename( dirname( $this->plugin_file ) ) . '/' . basename( $this->plugin_file ), array( $this, 'activation' ), 10 );
		register_deactivation_hook( basename( dirname( $this->plugin_file ) ) . '/' . basename( $this->plugin_file ), array( $this, 'deactivation' ), 10 );

		add_filter( 'block_local_requests', '__return_false' );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	/**
	 * Ran on WP admin_init hook
	 */
	public function admin_init() {
		global $wp_version, $wpjm_updater_runonce;

		$this->load_errors();

		add_action( 'shutdown', array( $this, 'store_errors' ) );
		add_action( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		
		$this->plugin_data      = get_plugin_data( $this->plugin_file );
		$this->api_key          = get_option( $this->plugin_slug . '_licence_key' );
		$this->activation_email = get_option( $this->plugin_slug . '_email' );

		// Activated notice
		if ( ! empty( $_GET['activated_licence'] ) && $_GET['activated_licence'] === $this->plugin_slug ) {
			add_action( 'admin_notices', array( $this, 'activated_key_notice' ) );
		} elseif ( ! empty( $_GET['deactivated_licence'] ) && $_GET['deactivated_licence'] === $this->plugin_slug ) {
			add_action( 'admin_notices', array( $this, 'deactivated_key_notice' ) );
		}

		// de-activate link
		if ( ! empty( $_GET[ $this->plugin_slug . '_deactivate_licence' ] ) ) {
			$this->deactivate_licence();
			wp_redirect( admin_url( 'plugins.php?deactivated_licence=' . $this->plugin_slug ) );
			exit;
		}

		// Posted key?
		if ( ! empty( $_POST[ $this->plugin_slug . '_licence_key' ] ) ) {

			try {

				$licence_key = sanitize_text_field( $_POST[ $this->plugin_slug . '_licence_key' ] );
				$email       = sanitize_text_field( $_POST[ $this->plugin_slug . '_email' ] );

				if ( empty( $licence_key ) ) {
					throw new Exception( 'Please enter your licence key' );
				}

				if ( empty( $email ) ) {
					throw new Exception( 'Please enter the email address associated with your licence' );
				}

				include_once( 'class-wpjm-updater-key-api.php' );

				$activate_results = json_decode( WPJM_Updater_Key_API::activate( array( 
					'email'          => $email, 
					'licence_key'    => $licence_key,
					'api_product_id' => $this->plugin_slug
				) ), true );

				if ( ! empty( $activate_results['activated'] ) ) {

					$this->api_key          = $licence_key;
					$this->activation_email = $email;
					$this->errors           = array();

					update_option( $this->plugin_slug . '_licence_key', $this->api_key );
					update_option( $this->plugin_slug . '_email', $this->activation_email );
					delete_option( $this->plugin_slug . '_errors' );

					wp_redirect( admin_url( 'plugins.php?activated_licence=' . $this->plugin_slug. '#wpwrap' ) );
					exit;
				
				} elseif ( $activate_results === false ) {
					
					throw new Exception( 'Connection failed to the Licence Key API server. Try again later.' );
				
				} elseif ( isset( $activate_results['error_code'] ) ) {

					throw new Exception( $activate_results['error'] );

				}

			} catch ( Exception $e ) {
				$this->add_error( $e->getMessage() );
			}

			wp_redirect( admin_url( 'plugins.php#wpwrap' ) );
			exit;
		}

		if ( ! $this->api_key && sizeof( $this->errors ) === 0 ) {
			add_action( 'admin_notices', array( $this, 'key_notice' ) );
		}

		if ( ! $this->api_key ) {
			add_action( 'after_plugin_row', array( $this, 'key_input' ) );
		}

		if ( ! $wpjm_updater_runonce ) {
			add_action( 'admin_print_styles-plugins.php', array( $this, 'styles' ) );
			$wpjm_updater_runonce = true;
		}

		if ( $this->api_key ) {
			add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'action_links' ) );
		}

		add_action( 'admin_notices', array( $this, 'error_notices' ) );
	}

	/**
	 * Add an error message
	 */
	public function add_error( $message, $type = '' ) {
		if ( $type ) {
			$this->errors[ $type ] = $message;
		} else {
			$this->errors[] = $message;
		}
	}

	/**
	 * Load errors from option
	 */
	public function load_errors() {
		$this->errors = get_option( $this->plugin_slug . '_errors', array() );
	}

	/**
	 * Store errors in option
	 */
	public function store_errors() {
		update_option( $this->plugin_slug . '_errors', $this->errors );
	}

	/**
	 * Output errors
	 */
	public function error_notices() {
		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $key => $error ) {
				?><div class="error">
					<p><?php echo wp_kses_post( $error ); ?></p>
				</div><?php
				if ( $key !== 'invalid_key' ) {
					unset( $this->errors[ $key ] );
				}
			}
		}
	}

	/**
	 * Ran on plugin activation
	 */
	public function activation() {}

	/**
	 * Ran on plugin-deactivation
	 */
	public function deactivation() {
		$this->deactivate_licence();
	}

	/**
	 * Deactivate a licence
	 */
	public function deactivate_licence() {
		include_once( 'class-wpjm-updater-key-api.php' );

		$reset = WPJM_Updater_Key_API::deactivate( array(
				'api_product_id' => $this->plugin_slug,
				'licence_key'    => $this->api_key,
		) );

		delete_option( $this->plugin_slug . '_licence_key' );
		delete_option( $this->plugin_slug . '_email' );
		delete_option( $this->plugin_slug . '_errors' );
		delete_site_transient( 'update_plugins' );
		$this->errors           = array();
		$this->api_key          = '';
		$this->activation_email = '';
	}

	/**
	 * Action links
	 */
	public function action_links( $links ) {
		$links[] = '<a href="' . add_query_arg( $this->plugin_slug . '_deactivate_licence', 1 ) . '">' . 'Deactivate licence' . '</a>';
		return $links;
	}

	/**
	 * Show a notice prompting the user to update
	 */
	public function key_notice() {
		?><div class="updated">
			<p><?php printf( '<a href="%s">Please enter your licence key</a> to get updates for "%s".', esc_url( admin_url( 'plugins.php#' . sanitize_title( $this->plugin_slug ) ) ), esc_html( $this->plugin_data['Name'] ) ); ?></p>
			<p><small class="description"><?php printf( 'Lost your key? <a href="%s">Retrieve it here</a>.', esc_url( 'https://wpjobmanager.com/lost-licence-key/' ) ); ?></small></p>
		</div><?php
	}

	/**
	 * Activation success notice
	 */
	public function activated_key_notice() {
		?><div class="updated">
			<p><?php printf( 'Your licence for <strong>%s</strong> has been activated. Thanks!', esc_html( $this->plugin_data['Name'] ) ); ?></p>
		</div><?php
	}

	/**
	 * Dectivation success notice
	 */
	public function deactivated_key_notice() {
		?><div class="updated">
			<p><?php printf( 'Your licence for <strong>%s</strong> has been deactivated.', esc_html( $this->plugin_data['Name'] ) ); ?></p>
		</div><?php
	}

	/**
	 * Enqueue admin styles
	 */
	public function styles() {
		wp_enqueue_style( 'wpjm-updater-styles', plugins_url( basename( plugin_dir_path( $this->plugin_file ) ), basename( $this->plugin_file ) ) . '/includes/updater/assets/css/admin.css' );
	}

	/**
	 * Show the input for the licence key
	 */
	public function key_input( $file ) {
		if ( basename( dirname( $file ) ) === $this->plugin_slug ) {
			?><tr id="<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key_row" class="active plugin-update-tr wpjm-updater-licence-key-tr">
				<td class="plugin-update" colspan="3">
					<div class="wpjm-updater-licence-key">
						<label for="<?php echo sanitize_title( $this->plugin_slug ); ?>_licence_key"><?php _e( 'Licence' ); ?>:</label>
						<input type="text" id="<?php echo sanitize_title( $this->plugin_slug ); ?>_licence_key" name="<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key" placeholder="Licence key" />
						<input type="email" id="<?php echo sanitize_title( $this->plugin_slug ); ?>_email" name="<?php echo esc_attr( $this->plugin_slug ); ?>_email" placeholder="Email address" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
						<span class="description"><?php _e( 'Enter your licence key and email and hit return. A valid key is required for automatic updates.' ); ?></span>
					</div>
				</td>
				<script>
					jQuery(function(){
						jQuery('tr#<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key_row').prev().addClass('wpjm-updater-licenced');
					});
				</script>
			</tr><?php
		}
	}

	/**
	 * Check for plugin updates
	 */
	public function check_for_updates( $check_for_updates_data ) {
		global $wp_version;

		if ( ! $this->api_key ) {
			return $check_for_updates_data;
		}

		if ( empty( $check_for_updates_data->checked ) ) {
			return $check_for_updates_data;
		}

		$current_ver = $check_for_updates_data->checked[ $this->plugin_name ];

		$args = array(
			'request'        => 'pluginupdatecheck',
			'plugin_name'    => $this->plugin_name,
			'version'        => $current_ver,
			'api_product_id' => $this->plugin_slug,
			'licence_key'    => $this->api_key,
			'email'          => $this->activation_email,
			'instance'       => site_url()
		);

		// Check for a plugin update
		$response = $this->plugin_information( $args );

		if ( isset( $response->errors ) ) {
			$this->handle_errors( $response->errors );
		}

		// Set version variables
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			// New plugin version from the API
			$new_ver = (string) $response->new_version;
		}

		// If there is a new version, modify the transient to reflect an update is available
		if ( isset( $new_ver ) ) {
			if ( $response !== false && version_compare( $new_ver, $current_ver, '>' ) ) {
				$check_for_updates_data->response[ $this->plugin_name ] = $response;
			}
		}

		return $check_for_updates_data;
	}

	/**
	 * Take over the Plugin info screen
	 */
	public function plugins_api( $false, $action, $args ) {
		global $wp_version;

		if ( ! $this->api_key ) {
			return $false;
		}

		if ( ! isset( $args->slug ) || ( $args->slug !== $this->plugin_name ) ) {
			return $false;
		}

		// Get the current version
		$plugin_info = get_site_transient( 'update_plugins' );
		$current_ver = isset( $plugin_info->checked[ $this->plugin_name ] ) ? $plugin_info->checked[ $this->plugin_name ] : '';
		
		$args = array(
			'request'        => 'plugininformation',
			'plugin_name'    => $this->plugin_name,
			'version'        => $current_ver,
			'api_product_id' => $this->plugin_slug,
			'licence_key'    => $this->api_key,
			'email'          => $this->activation_email,
			'instance'       => site_url()
		);

		// Check for a plugin update
		$response = $this->plugin_information( $args );

		if ( isset( $response->errors ) ) {
			$this->handle_errors( $response->errors );
		}

		// If everything is okay return the $response
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			return $response;
		}
	}

	/**
	 */
	public function handle_errors( $errors ) {
		if ( ! empty( $errors['no_key'] ) ) {
			$this->add_error( sprintf( 'A licence key for %s could not be found. Maybe you forgot to enter a licence key when setting up %s.', esc_html( $this->plugin_data['Name'] ), esc_html( $this->plugin_data['Name'] ) ) );
		} elseif ( ! empty( $errors['invalid_request'] ) ) {
			$this->add_error( 'Invalid update request' );
		} elseif ( ! empty( $errors['invalid_key'] ) ) {
			$this->add_error( $errors['invalid_key'], 'invalid_key' );
		} elseif ( ! empty( $errors['no_activation'] ) ) {
			$this->deactivate_licence();
			$this->add_error( $errors['no_activation'] );
		}
	}

	/**
	 * Sends and receives data to and from the server API
	 * @return object $response
	 */
	public function plugin_information( $args ) {
		$request    = wp_remote_get( $this->api_url . '&' . http_build_query( $args, '', '&' ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			return false;
		}

		$response = maybe_unserialize( wp_remote_retrieve_body( $request ) );

		if ( is_object( $response ) ) {
			return $response;
		} else {
			return false;
		}
	}
}