<?php
/**
 * Watch later list template
 *
 * @package wpstream-theme
 */

?>

<div class="card-body wpstream-dashboard-card">

	<div class="wpstream-dashboard-card-featured-image">

		<?php
		print '<a href="' . esc_url( get_permalink() ) . '">';
		print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		print '</a>';
		?>

	</div>

	<div class="wpstream-dashboard-card-title-section">

		<?php
		print '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
		?>

		<div class="wpstream-dashboard-card-categories">
			<?php
			echo esc_html( get_the_date() );
			print ' <span>&#183;</span> ';
			?>

			<?php

			$categories = get_the_terms( get_the_ID(), 'category' );

			if ( $categories && ! is_wp_error( $categories ) ) {
				foreach ( $categories as $category ) {
					echo esc_html( $category->name );
					print ' <span>&#183;</span> ';
				}
			}

			?>

		</div>

	</div>

	<div class="wpstream-dashboard-card-actions">
		<a href="<?php echo esc_url( get_permalink() ); ?>" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'View media', 'hello-wpstream' ); ?>">

			<?php echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		</a>
		<div class="wpstream_watch-later-remove-btn" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Remove Item from Watch Later list', 'hello-wpstream' ); ?>" data-post-id=<?php echo esc_attr( get_the_ID() ); ?>>

			<?php echo wpstream_theme_get_svg_icon( 'trash.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		</div>
	</div>

</div>