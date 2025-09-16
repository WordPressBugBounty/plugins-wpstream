<div class="notice notice-warning is-dismissible" id="wpstream-theme-update-notice">
	<p>
		<strong><?php esc_html__('Hello WPStream theme update available.', 'hello-wpstream') ?></strong>
		<?php printf(
			esc_html__( 'A new version of the Hello WPStream theme is available.
			 To make sure that all the theme features are working properly, please update to version %s.', 'hello-wpstream' ),
			esc_html( $data['new_version'] ),
		); ?>
	</p>
	<p>
		<a href="<?php echo esc_url( admin_url('themes.php') ); ?>" class="button button-primary">
			<?php esc_html_e( 'Update Now', 'hello-wpstream' ); ?>
		</a>
	</p>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		$(document).on('click', '#wpstream-theme-update-notice .notice-dismiss', function() {
			$.post(ajaxurl, {
				action: 'wpstream_dismiss_notice',
				nonce: '<?php echo wp_create_nonce('wpstream_dismiss_notice'); ?>'
			});
		});
	})
</script>