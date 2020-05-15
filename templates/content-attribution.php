<?php
/**
 * Adds attribution for Indeed records.
 *
 * This template can be overridden by copying it to yourtheme/indeed/content-attribution.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager - Indeed Integration
 * @category    Template
 * @version     2.1.10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="wp-job-manager-attribution-row job_listing">
	<?php
		printf(
			wp_kses(
				__( '<a href="%1s">jobs by <img src="%2s" style="%3s" alt="Indeed job search" /></a>' ),
				array(
					'a' => array(
						'href' => array()
					),
					'img' => array(
						'src' => array(),
						'style' => array(),
						'alt' => array()
					)
				)
			),
			'https://www.indeed.com',
			JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/images/jobsearch.gif',
			'border: 0; vertical-align: middle;'
		);
	?>
</li>
