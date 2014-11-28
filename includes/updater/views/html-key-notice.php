<div class="updated">
	<p class="wpjm-updater-dismiss" style="float:right;"><a href="<?php echo esc_url( add_query_arg( 'dismiss-' . sanitize_title( $this->plugin_slug ), '1' ) ); ?>"><?php _e( 'Hide notice' ); ?></a></p>
	<p><?php printf( '<a href="%s">Please enter your licence key</a> in the plugin list below to get updates for "%s".', '#' . sanitize_title( $this->plugin_slug . '_licence_key_row' ), esc_html( $this->plugin_data['Name'] ) ); ?></p>
	<p><small class="description"><?php printf( 'Lost your key? <a href="%s">Retrieve it here</a>.', esc_url( 'https://wpjobmanager.com/lost-licence-key/' ) ); ?></small></p>
</div>