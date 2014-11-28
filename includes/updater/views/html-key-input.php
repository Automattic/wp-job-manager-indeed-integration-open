<tr id="<?php echo esc_attr( sanitize_title( $this->plugin_slug . '_licence_key_row' ) ); ?>" class="active plugin-update-tr wpjm-updater-licence-key-tr">
	<td class="plugin-update" colspan="3">
		<div class="wpjm-updater-licence-key">
			<label for="<?php echo sanitize_title( $this->plugin_slug ); ?>_licence_key"><?php _e( 'Licence' ); ?>:</label>
			<input type="text" id="<?php echo sanitize_title( $this->plugin_slug ); ?>_licence_key" name="<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key" placeholder="Licence key" />
			<input type="email" id="<?php echo sanitize_title( $this->plugin_slug ); ?>_email" name="<?php echo esc_attr( $this->plugin_slug ); ?>_email" placeholder="Email address" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
			<span class="description"><?php _e( 'Enter your licence key and email and hit return. A valid key is required for automatic updates.' ); ?></span>
		</div>
	</td>
	<script>
		jQuery(function(){
			jQuery('tr#<?php echo esc_attr( $this->plugin_slug ); ?>_licence_key_row').prev().addClass('wpjm-updater-licenced');
		});
	</script>
</tr>