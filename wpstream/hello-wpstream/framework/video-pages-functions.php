<?php
/**
 * Video item unit selection.
 *
 * Resolves which video "card" template partial should be used to render an item
 * in a listing. The design variant can be forced per call, or fall back to the
 * theme-wide Customizer setting.
 *
 * @package wpstream-theme
 */

// Guard against redeclaration.
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

		// Type 0 means "not specified": fall back to the Customizer design setting (default 1).
		if ( intval( $type ) === 0 ) {
			$type = intval( get_theme_mod( 'wpstream_theme_video_unit_design_type', 1 ) );
		}

		// Map the design variant to its card template file.
		if ( 1 === $type ) {
			$template = 'video-cards/video-card-v1.php';
		} elseif ( 2 === $type ) {
			$template = 'video-cards/video-card-v2.php';
		}

		// Return the path relative to the theme's single template parts directory.
		return '/template-parts/single/' . $template;
	}
}
