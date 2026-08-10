<?php
/**
 * Blog card v1.
 *
 * Renders a single blog post as a grid card: featured image, title, optional
 * metadata line (likes / reads / published duration), optional category list,
 * and — on a single post view — a "read more" arrow link. The grid column class
 * can be overridden by a caller variable or by an $args['class'] value.
 *
 * @package wpstream-theme
 */

// Optional caller args; default to an empty array when not provided.
$args            = $args ?? array();
// Author of the current post (available to the markup below).
$author_id       = wpstream_get_author_id( get_the_ID() );
// Default responsive Bootstrap column classes for the card wrapper.
$card_grid_class = 'col-sm-12 col-md-6 col-lg-4 col-xl-3';
// Customizer toggles: whether to show the view count and the metadata line.
$wpstream_blog_post_card_show_hide_views 	= get_theme_mod( 'wpstream_blog_post_card_show_hide_views', true );
$wpstream_blog_post_card_show_hide_metadata = get_theme_mod( 'wpstream_blog_post_card_show_hide_metadata', true );
// Whether to suppress the category list (see the guarded block further down).
$hide_category = false;

// A caller-provided overwrite variable takes precedence over the default columns.
if ( isset( $card_grid_class_overwrite ) && '' !== $card_grid_class_overwrite ) {
	$card_grid_class = $card_grid_class_overwrite;
}

// An explicit args['class'] value overrides the grid column class as well.
if ( isset( $args['class'] ) ) {
	$card_grid_class = $args['class'];
}

?>

<!-- Card wrapper; grid column class resolved above. -->
<div class="<?php echo esc_attr( $card_grid_class ); ?> gridbox wpstream-blog-card">
	<div class="card wpstream-gridcard">
		<!-- Featured image linking to the post. -->
		<a href="<?php echo esc_url( get_permalink() ); ?>" class="card-img">

			<?php
			// Print the card's featured image (view-count overlay controlled by the toggle).
			print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards','video_preview',false, $wpstream_blog_post_card_show_hide_views );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>

		</a>
		<!-- Card body: title, optional metadata, optional categories. -->
		<div class="card-body-blog-post">
			<!-- Post title linking to the post. -->
			<a class="wpstream-blog-post-card-title" href="<?php echo esc_url( get_permalink() ); ?>">

				<?php
				// Escaped post title text.
				echo esc_html( get_the_title() );
				?>

			</a>
			<?php 
			// Render the metadata line only when the customizer toggle is on.
			if($wpstream_blog_post_card_show_hide_metadata){
			?>
			<!-- Metadata line: like icon + like count, read count, published duration. -->
			<div class="wpstream-blog-post-card-details">
				<?php
				// Like icon followed by the number of likes.
				echo wpstream_theme_get_svg_icon( 'like.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpstream_get_count_like_post( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo ' <span>&#183;</span> ';
				// Read/view count for the post.
				echo wpstream_get_post_read_count_by_id( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo ' <span>&#183;</span> ';
				// Human-readable "published X ago" duration.
				echo wpstream_get_post_published_duration_by_id( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<?php
			}
			// Gather the post's categories for the optional category list below.
			$categories = get_the_category( get_the_ID() );

			// Show the category list only when categories exist, the caller allows it, and it isn't hidden.
			if ( ! empty( $categories ) && ( $args['show_category'] ?? true ) && ( ! isset( $hide_category ) || $hide_category ) ) {
				// Build a <ul> of category links.
				$output = '<div><ul>';

				// One list item linking to each category archive.
				foreach ( $categories as $category ) {
					$url     = get_category_link( $category->term_id );
					$output .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $category->name ) );
				}

				$output .= '</ul></div>';

				// Print the assembled list with post-safe HTML allowed.
				echo wp_kses_post($output);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

		</div>

		<!-- On a single post view, add a "read more" arrow action. -->
		<?php if ( is_singular( 'post' ) ): ?>
			<div class="wpstream-author-blog-card-actions">
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="link-to-post">
					<?php echo wpstream_theme_get_svg_icon( 'arrow-right.svg' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
