<?php
/**
 * Watch later list template.
 *
 * Single card for one saved "Watch Later" item, rendered inside the loop in
 * template-watch-later.php. Shows the featured image, linked title, publish
 * date and categories, plus view and remove-from-list actions. Relies on the
 * current post being set up in the loop (get_the_ID(), get_the_title(), etc.).
 *
 * @package wpstream-theme
 */

?>

<!-- Watch Later item card. -->
<div class="card-body wpstream-dashboard-card">

	<!-- Featured image, linked to the item's permalink. -->
	<div class="wpstream-dashboard-card-featured-image">

		<?php
		print '<a href="' . esc_url( get_permalink() ) . '">';
		// Output the item's featured image at the card-thumbnail size.
		print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		print '</a>';
		?>

	</div>

	<!-- Title, date and categories. -->
	<div class="wpstream-dashboard-card-title-section">

		<?php
		// Linked item title.
		print '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
		?>

		<!-- Publish date and category list. -->
		<div class="wpstream-dashboard-card-categories">
			<?php
			// Print the item's publish date followed by a separator dot.
			echo esc_html( get_the_date() );
			print ' <span>&#183;</span> ';
			?>

			<?php

			// Fetch the item's categories and print each name, separated by dots.
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

	<!-- Card action buttons: view and remove. -->
	<div class="wpstream-dashboard-card-actions">
		<!-- View action links to the item's permalink. -->
		<a href="<?php echo esc_url( get_permalink() ); ?>" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'View media', 'hello-wpstream' ); ?>">

			<!-- Play icon for the view action. -->
			<?php echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		</a>
		<!-- Remove-from-watch-later button; data-post-id targets this item. -->
		<div class="wpstream_watch-later-remove-btn" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Remove Item from Watch Later list', 'hello-wpstream' ); ?>" data-post-id=<?php echo esc_attr( get_the_ID() ); ?>>

			<!-- Trash icon for the remove action. -->
			<?php echo wpstream_theme_get_svg_icon( 'trash.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		</div>
	</div>

</div>