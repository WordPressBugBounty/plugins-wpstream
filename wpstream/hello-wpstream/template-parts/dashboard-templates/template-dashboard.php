<?php
/**
 * Template dashboard
 *
 * @package wpstream-theme
 */

 $hello_text        = esc_html__( 'Welcome back, ', 'hello-wpstream' );
 $current_user_name = $current_user->display_name;
 $dashboard_desc    = esc_html__( 'Start your day of with some account features.', 'hello-wpstream' );
?>

<div class="wpstream-dashboard-animation-wrap">
	<?php echo wpstream_theme_get_svg_icon( 'dashboard-robot.svg' );?>
</div>

<h1 class="wpstream-dashboard-title">
	<?php echo esc_html( $hello_text); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<span><?php echo esc_html($current_user_name); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
</h1>

<p class="wpstream-dashboard-subtitle"><?php echo esc_html($dashboard_desc); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

<div class="wpstream-dashboard-links-wrap no-woo">
	<?php 	if (  wpstream_check_if_user_can_stream()) { ?>
        <a class="wpstream-dashboard-link-wrap type-2-button-style" href="<?php echo esc_url( wpstream_non_woo_get_account_endpoint_url( 'start-streaming' ) ); ?>">
			<div class="wpstream-dashboard-link-content">
			<?php echo wpstream_theme_get_svg_icon( 'dashboard-start-streaming.svg' );?>
			
		
			<div class="wpstream-dashboard-link-text">
				<span class="link-title"><?php esc_html_e('Start Streaming','hello-wpstream');?></span>
				<span class="link-description"><?php esc_html_e('Go live with WpStream','hello-wpstream');?></span>
			</div>
			</div>

			<div class="wpstream-dashboard-link">
				<?php echo wpstream_theme_get_svg_icon( 'dahboard-arrow.svg' );?>
			</div>
		</a>
	<?php } ?>
</div>
