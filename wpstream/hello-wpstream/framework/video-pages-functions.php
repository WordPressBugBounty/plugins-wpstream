<?php
/**
 * Video item unit selection.
 */

if ( ! function_exists( 'wpstream_video_item_card_selector' ) ) {
	/**
	 * Wpstream video item card selector.
	 *
	 * @param int $type    Type of video item card.
	 * @param int $is_grid Whether the video item card is for a grid layout.
	 *
	 * @return string       Path to the selected template file.
	 */
	function wpstream_video_item_card_selector( $type = 0, $is_grid = 0 ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if ( intval( $type ) === 0 ) {
			$type = intval( get_theme_mod( 'wpstream_theme_video_unit_design_type', 1 ) );
		}

		if ( 1 === $type ) {
			$template = 'video-cards/video-card-v1.php';
		} elseif ( 2 === $type ) {
			$template = 'video-cards/video-card-v2.php';
		}

		return '/template-parts/single/' . $template;
	}
}
