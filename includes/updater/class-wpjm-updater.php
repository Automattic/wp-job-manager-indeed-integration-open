<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPJM_Updater
 *
 * @version 3.0
 * @author  Mike Jolley
 */
class WPJM_Updater {
	private $plugin_name = '';
	private $plugin_file = '';
	private $plugin_slug = '';
	private $errors      = array();
	private $plugin_data = array();

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
		$this->plugin_slug = str_replace( '.php', '', basename( $this->plugin_file ) );
		$this->plugin_name = basename( dirname( $this->plugin_file ) ) . '/' . $this->plugin_slug . '.php';

		register_activation_hook( $this->plugin_name, array( $this, 'plugin_activation' ), 10 );
		register_deactivation_hook( $this->plugin_name, array( $this, 'plugin_deactivation' ), 10 );

		add_filter( 'block_local_requests', '__return_false' );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		include_once( 'class-wpjm-updater-api.php' );
		include_once( 'class-wpjm-updater-key-api.php' );
	}

	/**
	 * Ran on WP admin_init hook
	 */
	public function admin_init() {
		global $wp_version;

		$this->load_errors();

		add_action( 'shutdown', array( $this, 'store_errors' ) );
		add_action( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );

		$this->api_key          = get_option( $this->plugin_slug . '_licence_key' );
		$this->activation_email = get_option( $this->plugin_slug . '_email' );
		$this->plugin_data      = get_plugin_data( $this->plugin_file );

		if ( current_user_can( 'update_plugins' ) ) {
			$this->admin_requests();
			$this->init_key_ui();
		}
	}

	/**
	 * Process admin requests
	 */
	private function admin_requests() {
		if ( ! empty( $_POST[ $this->plugin_slug . '_licence_key' ] ) ) {
			$this->activate_licence_request();
		} elseif ( ! empty( $_GET[ 'dismiss-' . sanitize_title( $this->plugin_slug ) ] ) ) {
			update_option( $this->plugin_slug . '_hide_key_notice', 1 );
		} elseif ( ! empty( $_GET['activated_licence'] ) && $_GET['activated_licence'] === $this->plugin_slug ) {
			$this->add_notice( array( $this, 'activated_key_notice' ) );
		} elseif ( ! empty( $_GET['deactivated_licence'] ) && $_GET['deactivated_licence'] === $this->plugin_slug ) {
			$this->add_notice( array( $this, 'deactivated_key_notice' ) );
		} elseif ( ! empty( $_GET[ $this->plugin_slug . '_deactivate_licence' ] ) ) {
			$this->deactivate_licence_request();
		}
	}

	/**
	 * Deactivate a licence request
	 */
	private function deactivate_licence_request() {
		$this->deactivate_licence();
		wp_redirect( remove_query_arg( array( 'activated_licence', $this->plugin_slug . '_deactivate_licence' ), add_query_arg( 'deactivated_licence', $this->plugin_slug ) ) );
		exit;
	}

	/**
	 * Activate a licence request
	 */
	private function activate_licence_request() {
		$licence_key = sanitize_text_field( $_POST[ $this->plugin_slug . '_licence_key' ] );
		$email       = sanitize_text_field( $_POST[ $this->plugin_slug . '_email' ] );
		$this->activate_licence( $licence_key, $email );
	}

	/**
	 * Init keys UI
	 */
	private function init_key_ui() {
		if ( ! $this->api_key ) {
			add_action( 'admin_print_styles-plugins.php', array( $this, 'styles' ) );
			add_action( 'after_plugin_row', array( $this, 'key_input' ) );
			$this->add_notice( array( $this, 'key_notice' ) );
		} else {
			add_action( 'after_plugin_row_' . $this->plugin_name, array( $this, 'multisite_updates' ), 10, 2 );
			add_filter( 'plugin_action_links_' . $this->plugin_name, array( $this, 'action_links' ) );
		}
		add_action( 'admin_notices', array( $this, 'error_notices' ) );
	}

	/**
	 * Add notices
	 */
	private function add_notice( $callback ) {
		add_action( 'admin_notices', $callback );
		add_action( 'network_admin_notices', $callback );
	}

	/**
	 * Add an error message
	 *
	 * @param string $message Your error message
	 * @param string $type    Type of error message
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
		if ( sizeof( $this->errors ) > 0 ) {
			update_option( $this->plugin_slug . '_errors', $this->errors );
		} else {
			delete_option( $this->plugin_slug . '_errors' );
		}
	}

	/**
	 * Output errors
	 */
	public function error_notices() {
		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $key => $error ) {
				include( 'views/html-error-notice.php' );
				if ( $key !== 'invalid_key' && did_action( 'all_admin_notices' ) ) {
					unset( $this->errors[ $key ] );
				}
			}
		}
	}

	/**
	 * Ran on plugin-activation
	 */
	public function plugin_activation() {
		delete_option( $this->plugin_slug . '_hide_key_notice' );
	}

	/**
	 * Ran on plugin-deactivation
	 */
	public function plugin_deactivation() {
		$this->deactivate_licence();
	}

	/**
	 * Try to activate a licence
	 */
	public function activate_licence( $licence_key, $email ) {
		try {

			if ( empty( $licence_key ) ) {
				throw new Exception( 'Please enter your licence key' );
			}

			if ( empty( $email ) ) {
				throw new Exception( 'Please enter the email address associated with your license' );
			}

			$activate_results = json_decode( WPJM_Updater_Key_API::activate( array(
				'email'          => $email,
				'licence_key'    => $licence_key,
				'api_product_id' => $this->plugin_slug
			) ), true );

			if ( false === $activate_results ) {
				throw new Exception( 'Connection failed to the License Key API server - possible server issue.' );

			} elseif ( isset( $activate_results['error_code'] ) ) {
				throw new Exception( $activate_results['error'] );

			} elseif ( ! empty( $activate_results['activated'] ) ) {
				$this->api_key          = $licence_key;
				$this->activation_email = $email;
				$this->errors           = array();

				update_option( $this->plugin_slug . '_licence_key', $this->api_key );
				update_option( $this->plugin_slug . '_email', $this->activation_email );
				delete_option( $this->plugin_slug . '_errors' );

				return true;
			}

			throw new Exception( 'License could not activate. Please contact support.' );

		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Deactivate a licence
	 */
	public function deactivate_licence() {
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
		$links[] = '<a href="' . remove_query_arg( array( 'deactivated_licence', 'activated_licence' ), add_query_arg( $this->plugin_slug . '_deactivate_licence', 1 ) ) . '">' . 'Deactivate License' . '</a>';
		return $links;
	}

	/**
	 * Show a notice prompting the user to update
	 */
	public function key_notice() {
		if ( sizeof( $this->errors ) === 0 && ! get_option( $this->plugin_slug . '_hide_key_notice' ) ) {
			include( 'views/html-key-notice.php' );
		}
	}

	/**
	 * Activation success notice
	 */
	public function activated_key_notice() {
		include( 'views/html-activated-key.php' );
	}

	/**
	 * Dectivation success notice
	 */
	public function deactivated_key_notice() {
		include( 'views/html-deactivated-key.php' );
	}

	/**
	 * Enqueue admin styles
	 */
	public function styles() {
		if ( ! wp_style_is( 'wpjm-updater-styles', 'enqueued' ) ) {
			wp_enqueue_style( 'wpjm-updater-styles', plugins_url( basename( plugin_dir_path( $this->plugin_file ) ), basename( $this->plugin_file ) ) . '/includes/updater/assets/css/admin.css' );
		}
	}

	/**
	 * Show the input for the licence key
	 */
	public function key_input( $file ) {
		if ( strtolower( basename( dirname( $file ) ) ) === strtolower( $this->plugin_slug ) ) {
			include( 'views/html-key-input.php' );
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

		// Set version variables
		if ( $response = $this->get_plugin_version() ) {
			// If there is a new version, modify the transient to reflect an update is available
			if ( $response !== false && version_compare( $response->new_version, $this->plugin_data['Version'], '>' ) ) {
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

		if ( ! isset( $args->slug ) || ( $args->slug !== $this->plugin_slug ) ) {
			return $false;
		}

		if ( $response = $this->get_plugin_info() ) {
			return $response;
		}
	}

	/**
	 * Get plugin version info from API
	 * @return array|bool
	 */
	public function get_plugin_version() {
		$response = WPJM_Updater_API::plugin_update_check( array(
			'plugin_name'    => $this->plugin_name,
			'version'        => $this->plugin_data['Version'],
			'api_product_id' => $this->plugin_slug,
			'licence_key'    => $this->api_key,
			'email'          => $this->activation_email
		) );

		if ( isset( $response->errors ) ) {
			$this->handle_errors( $response->errors );
		}

		// Set version variables
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			return $response;
		}

		return false;
	}

	/**
	 * Get plugin info from API
	 * @return array|bool
	 */
	public function get_plugin_info() {
		$response = WPJM_Updater_API::plugin_information( array(
			'plugin_name'    => $this->plugin_name,
			'version'        => $this->plugin_data['Version'],
			'api_product_id' => $this->plugin_slug,
			'licence_key'    => $this->api_key,
			'email'          => $this->activation_email
		) );

		if ( isset( $response->errors ) ) {
			$this->handle_errors( $response->errors );
		}

		// If everything is okay return the $response
		if ( isset( $response ) && is_object( $response ) && $response !== false ) {
			return $response;
		}

		return false;
	}

	/**
	 * Handle errors from the API
	 * @param  array $errors
	 */
	public function handle_errors( $errors ) {
		if ( ! empty( $errors['no_key'] ) ) {
			$this->add_error( sprintf( 'A license key for %s could not be found. Maybe you forgot to enter a license key when setting up %s.', esc_html( $this->plugin_data['Name'] ), esc_html( $this->plugin_data['Name'] ) ) );
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
     * show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
     *
     * Based on code by Pippin Williamson
     *
     * @param string  $file
     * @param array   $plugin
     */
    public function multisite_updates( $file, $plugin ) {
        if ( ! is_multisite() || is_network_admin() ) {
            return;
		}

		// Remove our filter on the site transient
		remove_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		$update_cache = get_site_transient( 'update_plugins' );

		// Check if we have no version info, or every hour
		if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->plugin_name ] ) || empty( $update_cache->last_checked ) || $update_cache->last_checked < strtotime( '-1 hour' ) ) {
			// Get plugin version info
			if ( $version_info = $this->get_plugin_version() ) {
				//if ( version_compare( $this->plugin_data['Version'], $version_info->new_version, '<' ) ) {
				$update_cache->response[ $this->plugin_name ] = $version_info;
				//}
				$update_cache->last_checked                  = time();
				$update_cache->checked[ $this->plugin_name ] = $this->plugin_data['Version'];

				set_site_transient( 'update_plugins', $update_cache );
			}
		} else {
			$version_info = $update_cache->response[ $this->plugin_name ];
		}

		// Restore our filter
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

        if ( ! empty( $version_info->new_version ) && version_compare( $this->plugin_data['Version'], $version_info->new_version, '<' ) ) {

			$wp_list_table  = _get_list_table( 'WP_Plugins_List_Table' );
			$changelog_link = network_admin_url( 'plugin-install.php?tab=plugin-information&amp;plugin=' . $this->plugin_name . '&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=597' );

            include( 'views/html-ms-update.php' );
        }
    }

}
