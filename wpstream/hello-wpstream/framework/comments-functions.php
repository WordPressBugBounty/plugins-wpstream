<?php
/**
 * Comments functions
 *
 * @package wp-stream
 */

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
		$post_id        = get_the_ID();
		$comments_count = wp_count_comments( $post_id );
		$total_comments = $comments_count->approved;
		// translators: %d is replaced with the total number of comments.
		$title = sprintf( __( 'Comments (%d)', 'hello-wpstream' ), $total_comments );

		$defaults['title_reply'] = $title;

		return $defaults;
	}
}


add_filter( 'comment_form_defaults', 'wpstream_theme_comment_button' );

if ( ! function_exists( 'wpstream_theme_comment_button' ) ) {
	/**
	 * Modify the class of the comment submit button.
	 *
	 * @param array $args The arguments for the comment form submit button.
	 * @return array Modified arguments for the comment form submit button.
	 */
	function wpstream_theme_comment_button( $args ) {
		$args['class_submit'] = 'btn-outline'; // since WP 4.1.
		return $args;
	}
}

add_filter( 'comment_text', 'wpstream_bs_comment_links_in_new_tab' );

if ( ! function_exists( 'wpstream_bs_comment_links_in_new_tab' ) ) {
	/**
	 * Open comment author links in new tab.
	 *
	 * @param string $text The comment text.
	 * @return string Modified comment text with links opened in new tab.
	 */
	function wpstream_bs_comment_links_in_new_tab( $text ) {
		return str_replace( '<a', '<a target="_blank" rel="nofollow noopener noreferrer"', $text );
	}
}
