<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

$options = array(
	'job_manager_indeed_publisher_id',
	'job_manager_indeed_enable_feed',
	'job_manager_indeed_enable_backfill',
	'job_manager_indeed_site_type',
	'job_manager_indeed_default_query',
	'job_manager_indeed_default_location',
	'job_manager_indeed_default_type',
	'job_manager_indeed_default_country',
	'job_manager_indeed_backfill',
	'job_manager_indeed_before_jobs',
	'job_manager_indeed_after_jobs',
	'job_manager_indeed_per_page',
	'job_manager_indeed_show_attribution',
	'job_manager_indeed_search_title_only'
);

foreach ( $options as $option ) {
	delete_option( $option );
}