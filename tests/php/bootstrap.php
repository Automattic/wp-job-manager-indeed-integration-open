<?php
/**
 * WPJM Addon Unit Tests Bootstrap
 *
 * @since 1.26.0
 */
class WPJM_Unit_Tests_Bootstrap {
	/** @var \WPJM_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing includes directory */
	public $includes_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	/** @var string WPJM plugin directory */
	public $wpjm_plugin_dir;

	/**
	 * Setup the unit testing environment.
	 *
	 * @since 1.26.0
	 */
	public function __construct() {
		define( 'DOING_AJAX', true );
		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );
		$this->tests_dir    = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'tests';
		$this->includes_dir    = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'includes';
		$this->plugin_dir   = dirname( dirname( dirname( $this->tests_dir ) ) );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
		$this->wpjm_plugin_dir = getenv( 'JOB_MANAGER_PLUGIN_DIR' ) ? getenv( 'JOB_MANAGER_PLUGIN_DIR' ) : dirname( dirname( __FILE__ ) ) .'/wp-job-manager';

		if ( ! is_dir( $this->wpjm_plugin_dir ) ) {
			throw new Exception( 'WP Job Manager was not found in ' . $this->wpjm_plugin_dir );
		}

		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		// load WPJM
		tests_add_filter( 'plugins_loaded', array( $this, 'load_plugin' ) );

		// install WPJM
		tests_add_filter( 'setup_theme', array( $this, 'install_plugin' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		// load WPJM testing framework
		$this->includes();
	}

	/**
	 * Load WPJM.
	 *
	 * @since 1.26.0
	 */
	public function load_plugin() {
		require_once( $this->wpjm_plugin_dir . '/wp-job-manager.php' );
		require_once( $this->plugin_dir . '/wp-job-manager-indeed-integration.php' );
	}

	/**
	 * Install WPJM after the test environment and WPJM have been loaded.
	 *
	 * @since 1.26.0
	 */
	public function install_plugin() {
		global $wp_version;

		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		if ( version_compare( $wp_version, '4.7.0' ) >= 0 ) {
			$GLOBALS['wp_roles'] = new WP_Roles();
		} else {
			$GLOBALS['wp_roles']->reinit();
		}
	}

	/**
	 * Load WPJM-specific test cases and framework.
	 *
	 * @since 1.26.0
	 */
	public function includes() {
		// framework
		require_once( $this->wpjm_plugin_dir . '/tests/php/includes/class-wpjm-base-test.php' );
		require_once( $this->wpjm_plugin_dir . '/tests/php/includes/factories/class-wpjm-factory.php' );
		require_once( $this->includes_dir . '/factories/class-wpjm-plugin-factory.php' );
		require_once( $this->includes_dir . '/class-wpjm-plugin-base-test.php' );
	}

	/**
	 * Get the single class instance.
	 *
	 * @since 1.26.0
	 * @return WPJM_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
WPJM_Unit_Tests_Bootstrap::instance();

