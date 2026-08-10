<div class="notice notice-warning is-dismissible" id="wpstream-theme-update-notice">
	<p>
		<strong><?php esc_html__('Hello WPStream theme update available.', 'hello-wpstream') /* Heading text. NOTE: no echo/_e, so this string is computed then discarded and never actually renders. */ ?></strong>
		<?php printf( /* Body sentence: fills %s with the available theme version. */
			esc_html__( 'A new version of the Hello WPStream theme is available.
			 To make sure that all the theme features are working properly, please update to version %s.', 'hello-wpstream' ),
			esc_html( $data['new_version'] ), /* $data['new_version'] is provided by WPStream_Theme_Notice::render_notice(). */
		); ?>
	</p>
	<p>
		<a href="<?php echo esc_url( admin_url('themes.php') ); /* Link to the Themes screen where the update is applied. */ ?>" class="button button-primary">
			<?php esc_html_e( 'Update Now', 'hello-wpstream' ); /* Translated call-to-action label (correctly echoed via _e). */ ?>
		</a>
	</p>
</div>

<script type="text/javascript">
	jQuery(document).ready(function($) {
		$(document).on('click', '#wpstream-theme-update-notice .notice-dismiss', function() {
			$.post(ajaxurl, {
				action: 'wpstream_dismiss_notice',
				nonce: '<?php echo wp_create_nonce('wpstream_dismiss_notice'); /* CSRF nonce verified by the dismissal AJAX handler. */ ?>'
			});
		});
	})
</script>