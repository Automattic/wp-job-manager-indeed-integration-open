<?php
class WPJM_Plugin_BaseTest extends WPJM_BaseTest {
	function setUp() {
		// Skip parent
		WP_UnitTestCase::setUp();
		$this->factory = self::factory();
		$this->enable_manage_job_listings_cap();
	}
}
