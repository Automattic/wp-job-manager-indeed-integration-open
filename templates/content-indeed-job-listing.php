<?php global $indeed_job; ?>
<li class="indeed_job_listing job_listing" data-longitude="<?php echo esc_attr( $indeed_job->longitude ); ?>" data-latitude="<?php echo esc_attr( $indeed_job->latitude ); ?>">
	<a href="<?php echo $indeed_job->url; ?>" target="_blank" onmousedown="<?php echo $indeed_job->onmousedown; ?>">
		<?php echo '<img class="company_logo" src="' . JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' . '" alt="Logo" />'; ?>
		<div class="position">
			<h3><?php echo $indeed_job->jobtitle; ?></h3>
			<div class="company">
				<strong><?php echo $indeed_job->company; ?></strong>
				<span class="tagline"><?php printf( __( 'Source: %s', 'wp-job-manager-indeed-integration' ), $indeed_job->source ); ?></span>
			</div>
		</div>
		<div class="location">
			<?php echo $indeed_job->formattedLocation; ?>
		</div>
		<ul class="meta">
			<li class="job-type <?php echo $indeed_job->job_type; ?>"><?php echo $indeed_job->job_type_name; ?></li>
			<li class="date"><date><?php printf( __( 'Posted %s ago', 'wp-job-manager-indeed-integration' ), human_time_diff( strtotime( $indeed_job->date ), current_time( 'timestamp' ) ) ); ?></date></li>
		</ul>
	</a>
</li>