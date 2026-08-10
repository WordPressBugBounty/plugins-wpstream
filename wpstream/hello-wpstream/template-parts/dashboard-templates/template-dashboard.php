<?php
/**
 * Template dashboard
 *
 * @package wpstream-theme
 */

 // Greeting text, current user's display name, and dashboard subtitle for the welcome header.
 $hello_text        = esc_html__( 'Welcome back, ', 'hello-wpstream' );
 $current_user_name = $current_user->display_name;
 $dashboard_desc    = esc_html__( 'Start your day of with some account features.', 'hello-wpstream' );
?>

<!-- Decorative animated robot illustration at the top of the dashboard. -->
<div class="wpstream-dashboard-animation-wrap">
	<?php echo wpstream_theme_get_svg_icon( 'dashboard-robot.svg' );?>
</div>

<!-- Welcome heading: greeting text followed by the current user's name. -->
<h1 class="wpstream-dashboard-title">
	<?php echo esc_html( $hello_text); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<span><?php echo esc_html($current_user_name); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
</h1>

<!-- Dashboard subtitle / call to action line. -->
<p class="wpstream-dashboard-subtitle"><?php echo esc_html($dashboard_desc); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

<!-- Quick-action link tiles (non-WooCommerce dashboard variant). -->
<div class="wpstream-dashboard-links-wrap no-woo">
	<!-- Show the "Start streaming" tile only to users permitted to broadcast. -->
	<?php 	if (  wpstream_check_if_user_can_stream()) { ?>
        <!-- "Start streaming" action tile linking to the go-live endpoint. -->
        <a class="wpstream-dashboard-link-wrap type-2-button-style" href="<?php echo esc_url( wpstream_non_woo_get_account_endpoint_url( 'start-streaming' ) ); ?>">
			<div class="wpstream-dashboard-link-content">
			<?php echo wpstream_theme_get_svg_icon( 'dashboard-start-streaming.svg' );?>
			
		
			<!-- Tile label: title and short description. -->
			<div class="wpstream-dashboard-link-text">
				<span class="link-title"><?php esc_html_e('Start Streaming','hello-wpstream');?></span>
				<span class="link-description"><?php esc_html_e('Go live with WpStream','hello-wpstream');?></span>
			</div>
			</div>

			<!-- Trailing arrow icon for the tile. -->
			<div class="wpstream-dashboard-link">
				<?php echo wpstream_theme_get_svg_icon( 'dahboard-arrow.svg' );?>
			</div>
		</a>
	<?php } ?>
</div>
