<?php

class WP_Test_WP_Job_Manager_Indeed_Integration extends WPJM_Plugin_BaseTest {
	/**
	 * Tests basic loading of plugin.
	 *
	 * @since 1.5.0
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( isset( $GLOBALS['job_manager_indeed_integration'] ) );
		$this->assertTrue( $GLOBALS['job_manager_indeed_integration'] instanceof WP_Job_Manager_Indeed_Integration );
	}
}