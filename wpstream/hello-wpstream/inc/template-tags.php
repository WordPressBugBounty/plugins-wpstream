<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package wpstream-theme
 */

// Category Badge.
if ( ! function_exists( 'wpstream_theme_category_badge' ) ) {
	/**
	 * Show category badge
	 */
	function wpstream_theme_category_badge() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {
			// Categories assigned to the current post.
			$categories = get_the_category();
			// Only render the badge wrapper when there is at least one.
			if ( ! empty( $categories ) ) {
				echo '<p class="category-badge">';
				// One linked badge per category.
				foreach ( $categories as $category ) {
					printf(
						'<a href="%1$s" class="badge text-bg-light text-decoration-none">%2$s</a> ',
						esc_url( get_category_link( $category->term_id ) ),
						esc_html( $category->name )
					);
				}
				echo '</p>';
			}
   	 	}
	}
}
// Category Badge End.


// Category.
if ( ! function_exists( 'wpstream_theme_category' ) ) {
	/**
	 * Get category list
	 */
	function wpstream_theme_category() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {
			// Comma-separated list of linked category names.
			$categories_list = get_the_category_list( ', ' );
			// Print the wrapper only when categories exist.
			if ( $categories_list ) {
				/* translators: %s list of categories. */
				printf( '<span class="cat-links">%s</span>', $categories_list ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}
}
// Category End.


// Date.
if ( ! function_exists( 'wpstream_theme_date' ) ) {
	/**
	 * Prints HTML with meta information for the current post-date/time.
	 */
	function wpstream_theme_date() {
		// Default template shows a single (published) timestamp.
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
		// When the post was edited after publishing, show both dates.
		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time> <span class="time-updated-separator">/</span> <time class="updated" datetime="%3$s">%4$s</time>';
		}

		// Fill the template with machine + human readable dates.
		$time_string = sprintf(
			$time_string,
			esc_attr( get_the_date( DATE_W3C ) ),
			esc_html( get_the_date() ),
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);

		// Wrap the time markup in a bookmark span.
		$posted_on = sprintf(
		/* translators: %s: post date. */
			'%s',
			'<span rel="bookmark">' . $time_string . '</span>'
		);

		// Output the assembled posted-on block.
		echo '<span class="posted-on">' . $posted_on . '</span>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
// Date End.


// Author.
if ( ! function_exists( 'wpstream_theme_author' ) ) {
	/**
	 * Show author link
	 */
	function wpstream_theme_author() {
		// Build "by {author}" with a link to the author's archive.
		$byline = sprintf(
		/* translators: %s: post author's name. */
			esc_html_x(
				'by %s',
				'post author',
				'hello-wpstream'
			),
			'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
		);

		// Output the byline block.
		echo '<span class="byline"> ' . $byline . '</span>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
// Author End.


// Comments.
if ( ! function_exists( 'wpstream_theme_comments' ) ) {
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function wpstream_theme_comments() {

		// Show the comments link only off the single view, when not password
		// protected, and when comments are open or some already exist.
		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			// Divider + icon preceding the link.
			echo ' <span class="comment-divider">|</span> <i class="fa-regular fa-comments"></i> <span class="comments-link">';
			// Render the "Leave a Comment" popup link.
			comments_popup_link(
				sprintf(
					wp_kses(
					/* translators: %s: post title */
						__( 'Leave a Comment', 'hello-wpstream' ),
						array(
							'span' => array(
								'class' => array(),
							),
						)
					),
					get_the_title()
				)
			);
			echo '</span>';
		}
	}
}
// Comments End.


// Edit Link.
if ( ! function_exists( 'wpstream_theme_edit' ) ) :
	/**
	 * Prints HTML with the edit link for the current post.
	 */
	function wpstream_theme_edit() {

		// Print the core edit link (only shown to users who can edit).
		edit_post_link(
			sprintf(
				wp_kses(
				/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Edit', 'hello-wpstream' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				get_the_title()
			),
			' | <span class="edit-link">',
			'</span>'
		);
	}
endif;
// Edit Link End.


// Single Comments Count.
if ( ! function_exists( 'wpstream_theme_comment_count' ) ) {
	/**
	 * Prints HTML with the comment count for the current post.
	 */
	function wpstream_theme_comment_count() {
		// Only render when not password protected and comments are enabled/exist.
		if ( ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			// Leading comment icon.
			echo wpstream_theme_get_svg_icon( 'comment.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<span class="comments-link">';

			// Link text varies by count: none / one / many.
			comments_popup_link(
				__( 'Leave a comment', 'hello-wpstream' ),
				/* translators: %d: Name of current post. Only visible to screen readers. */
				sprintf( __( '%d comment', 'hello-wpstream' ), get_comments_number() ),
				/* translators: %d: Name of current post. Only visible to screen readers. */
				sprintf( __( '%d comments', 'hello-wpstream' ), get_comments_number() )
			);

			echo '</span>';
		}
	}
}
// Single Comments Count End.


// Tags.
if ( ! function_exists( 'wpstream_theme_tags' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags and comments.
	 */
	function wpstream_theme_tags() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {

			/* translators: used between list items, there is a space after the comma */
			// Space-separated tag list; used only to test whether any tags exist.
			$tags_list = get_the_tag_list( '', ' ' );
			// Render the tags block only when the post has tags.
			if ( $tags_list ) {
				/* translators: 1: list of tags. */
				// Open the tags wrapper and print the heading.
				echo '<div class="tags-links">';
				echo '<p class="tags-heading mb-2">' . esc_html__( 'Tagged', 'hello-wpstream' ) . '</p>';
				// Print the tag links (badge classes added via the term_links filter).
				echo esc_html( get_the_tag_list() );
				echo '</div>';
			}
		}
	}

	// Add Bootstrap badge classes to each tag link on the front end.
	add_filter( 'term_links-post_tag', 'add_tag_class' );
	/**
	 * Add tag class to link
	 *
	 * @param string $links links string.
	 * @return string
	 */
	function add_tag_class( $links ) {
		// Inject the badge CSS classes into every tag anchor.
		return str_replace( '<a href="', '<a class="badge text-bg-light text-decoration-none me-1" href="', $links );
	}
endif;
// Tags End.


// Featured Image.
if ( ! function_exists( 'wpstream_theme_post_thumbnail' ) ) :
	/**
	 * Displays an optional post thumbnail.
	 *
	 * Wraps the post thumbnail in an anchor element on index views, or a div
	 * element when on single views.
	 */
	function wpstream_theme_post_thumbnail() {
		// Nothing to show when password protected, an attachment, or no thumbnail.
		if ( post_password_required() || is_attachment() || ! has_post_thumbnail() ) {
			return;
		}

		// Single view: plain <div> wrapper (no link).
		if ( is_singular() ) :
			?>

			<!-- Full-size thumbnail on single views. -->
			<div class="post-thumbnail">
				<?php the_post_thumbnail( 'full', array( 'class' => 'rounded mb-3' ) ); ?>
			</div><!-- .post-thumbnail -->

		<?php else : ?>

			<!-- Index/archive view: thumbnail linked to the post, hidden from a11y tree. -->
			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
				<?php
				the_post_thumbnail(
					'post-thumbnail',
					array(
						'alt' => the_title_attribute(
							array(
								'echo' => false,
							)
						),
					)
				);
				?>
			</a>

			<?php
		endif; // End is_singular().
	}
endif;
// Featured Image End.


// Remove in v6.
// Internet Explorer Warning Alert.
if ( ! function_exists( 'wpstream_theme_ie_alert' ) ) :
	/**
	 * Deprecated - functionality is removed already - Code will be removed in a future release.
	 * Replaced with a js solution to prevent page caching
	 *
	 * (Displays an alert if page is browsed by Internet Explorer)
	 *
	 * function stays to not break child themes with the function wpstream_theme_ie_alert() immediately
	 */
	function wpstream_theme_ie_alert() {
	}
endif;
// Internet Explorer Warning Alert End.
