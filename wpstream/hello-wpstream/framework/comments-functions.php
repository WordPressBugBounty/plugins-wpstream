<?php
/**
 * Comments functions
 *
 * Theme-side tweaks to the WordPress comment form and comment output:
 * - Re-labels the comment form title with a live approved-comment count.
 * - Swaps the submit button's CSS class to the theme's outline-button style.
 * - Forces links inside comment text to open in a new, safely-rel'd tab.
 *
 * All three are registered as filters on core comment hooks.
 *
 * @package wp-stream
 */

// Replace the comment form title with one that includes the comment count.
add_filter( 'comment_form_defaults', 'wpstream_theme_custom_comment_title', 20 );

if ( ! function_exists( 'wpstream_theme_custom_comment_title' ) ) {
	/**
	 * Customizes the comment section title to display the total number of comments.
	 *
	 * This function modifies the title of the comment section to display the total number
	 * of approved comments for the current post.
	 *
	 * @param array $defaults The default comment section arguments.
	 * @return array The modified comment section arguments.
	 */
	function wpstream_theme_custom_comment_title( $defaults ) {
		// Current post whose comments the form belongs to.
		$post_id        = get_the_ID();
		// Fetch the comment tallies (approved, pending, spam, etc.) for this post.
		$comments_count = wp_count_comments( $post_id );
		// We only surface the approved-comment count in the title.
		$total_comments = $comments_count->approved;
		// translators: %d is replaced with the total number of comments.
		// Build the "Comments (N)" heading.
		$title = sprintf( __( 'Comments (%d)', 'hello-wpstream' ), $total_comments );

		// Override the default reply/title text shown above the comment form.
		$defaults['title_reply'] = $title;

		// Return the (possibly) modified defaults back to WordPress.
		return $defaults;
	}
}


// Restyle the comment form's submit button via its CSS class.
add_filter( 'comment_form_defaults', 'wpstream_theme_comment_button' );

if ( ! function_exists( 'wpstream_theme_comment_button' ) ) {
	/**
	 * Modify the class of the comment submit button.
	 *
	 * @param array $args The arguments for the comment form submit button.
	 * @return array Modified arguments for the comment form submit button.
	 */
	function wpstream_theme_comment_button( $args ) {
		// Apply the theme's outline-button styling to the submit control.
		$args['class_submit'] = 'btn-outline'; // since WP 4.1.
		// Hand the adjusted args back to the comment form.
		return $args;
	}
}

// Rewrite anchors in rendered comment text so they open in a new tab.
add_filter( 'comment_text', 'wpstream_bs_comment_links_in_new_tab' );

if ( ! function_exists( 'wpstream_bs_comment_links_in_new_tab' ) ) {
	/**
	 * Open comment author links in new tab.
	 *
	 * @param string $text The comment text.
	 * @return string Modified comment text with links opened in new tab.
	 */
	function wpstream_bs_comment_links_in_new_tab( $text ) {
		// Inject target/rel attributes on every opening anchor tag in the text.
		// nofollow noopener noreferrer keeps outbound comment links safe.
		return str_replace( '<a', '<a target="_blank" rel="nofollow noopener noreferrer"', $text );
	}
}
