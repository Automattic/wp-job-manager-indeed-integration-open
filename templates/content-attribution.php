<?php
/**
 * Adds attribution for Indeed records.
 *
 * This template can be overridden by copying it to yourtheme/job-manager-indeed-integration/content-attribution.php.
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
	<a href="http://www.indeed.com/">jobs by <img src="<?php echo JOB_MANAGER_INDEED_PLUGIN_URL . '/assets/images/jobsearch.gif'; ?>" style="border: 0; vertical-align: middle;" alt="Indeed job search"></a>
</li>