<?php
/**
 * Blog card v1
 *
 * @package wpstream-theme
 */

$args            = $args ?? array();
$author_id       = wpstream_get_author_id( get_the_ID() );
$card_grid_class = 'col-sm-12 col-md-6 col-lg-4 col-xl-3';
$wpstream_blog_post_card_show_hide_views 	= get_theme_mod( 'wpstream_blog_post_card_show_hide_views', true );
$wpstream_blog_post_card_show_hide_metadata = get_theme_mod( 'wpstream_blog_post_card_show_hide_metadata', true );
$hide_category = false;

if ( isset( $card_grid_class_overwrite ) && '' !== $card_grid_class_overwrite ) {
	$card_grid_class = $card_grid_class_overwrite;
}

if ( isset( $args['class'] ) ) {
	$card_grid_class = $args['class'];
}

?>

<div class="<?php echo esc_attr( $card_grid_class ); ?> gridbox wpstream-blog-card">
	<div class="card wpstream-gridcard">
		<a href="<?php echo esc_url( get_permalink() ); ?>" class="card-img">

			<?php
			print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards','video_preview',false, $wpstream_blog_post_card_show_hide_views );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>

		</a>
		<div class="card-body-blog-post">
			<a class="wpstream-blog-post-card-title" href="<?php echo esc_url( get_permalink() ); ?>">

				<?php
				echo esc_html( get_the_title() );
				?>

			</a>
			<?php 
			if($wpstream_blog_post_card_show_hide_metadata){
			?>
			<div class="wpstream-blog-post-card-details">
				<?php
				echo wpstream_theme_get_svg_icon( 'like.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpstream_get_count_like_post( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo ' <span>&#183;</span> ';
				echo wpstream_get_post_read_count_by_id( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo ' <span>&#183;</span> ';
				echo wpstream_get_post_published_duration_by_id( get_the_ID() );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<?php
			}
			$categories = get_the_category( get_the_ID() );

			if ( ! empty( $categories ) && ( $args['show_category'] ?? true ) && ( ! isset( $hide_category ) || $hide_category ) ) {
				$output = '<div><ul>';

				foreach ( $categories as $category ) {
					$url     = get_category_link( $category->term_id );
					$output .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $category->name ) );
				}

				$output .= '</ul></div>';

				echo wp_kses_post($output);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

		</div>

		<?php if ( is_singular( 'post' ) ): ?>
			<div class="wpstream-author-blog-card-actions">
				<a href="<?php echo esc_url( get_permalink() ); ?>" class="link-to-post">
					<?php echo wpstream_theme_get_svg_icon( 'arrow-right.svg' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>
