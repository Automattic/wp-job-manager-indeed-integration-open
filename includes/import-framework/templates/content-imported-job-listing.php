<li class="<?php echo esc_attr( $source ); ?>_job_listing job_listing" data-longitude="<?php echo esc_attr( $job->lng ); ?>" data-latitude="<?php echo esc_attr( $job->lat ); ?>">
	<a href="<?php echo esc_url( $job->permalink ); ?>" <?php foreach ( $job->link_attributes as $key => $value ) echo esc_attr( $key ) . '="' . esc_attr( $value ) . '"'; ?> target="_blank">

		<img class="company_logo" src="<?php echo esc_url( apply_filters( 'job_manager_default_company_logo', JOB_MANAGER_PLUGIN_URL . '/assets/images/company.png' ) ); ?>" alt="Logo" />

		<div class="position">
			<h3><?php echo esc_html( $job->title ); ?></h3>
			<div class="company">
				<strong><?php echo esc_html( $job->company ); ?></strong>
				<small class="tagline"><?php echo esc_html( trim( $job->tagline ) ); ?></small>
			</div>
		</div>

		<div class="location">
			<?php echo esc_html( $job->location ); ?>
		</div>

		<ul class="meta">
			<li class="job-type <?php echo esc_attr( $job->type_slug ); ?>"><?php echo esc_html( $job->type ); ?></li>
			<li class="date"><date><?php printf( __( 'Posted %s ago', 'wp-job-manager' ), human_time_diff( $job->timestamp, current_time( 'timestamp' ) ) ); ?></date></li>
		</ul>
	</a>
</li>