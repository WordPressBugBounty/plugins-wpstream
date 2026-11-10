<?php
/**
 * The one video source component: source choice, uploader, recording list, URL field, preview and captions.
 *
 * New Video, the classic product editor and the block editor all print this markup. Each screen
 * only passes its own field names and adds its own extras around the component.
 * admin/js/wpstream-video-source.js drives it, and admin/css/wpstream-native-video.css styles it.
 */
defined( 'ABSPATH' ) || exit;

class Wpstream_Video_Source_Panel {
	/**
	 * Build the component.
	 *
	 * @param array $args {
	 *     @type string $mode           Starting source: upload, recording or external.
	 *     @type string $recording      Selected recording name.
	 *     @type array  $recordings     Recording name => data; only the names are used.
	 *     @type string $external       Video URL.
	 *     @type string $mode_name      Field name of the source choice; empty when it is not posted.
	 *     @type string $recording_name Field name of the recording list; empty when it is not posted.
	 *     @type string $external_name  Field name of the video URL.
	 *     @type string $captions       Captions file URL.
	 *     @type string $captions_name  Field name of the captions URL.
	 *     @type string $form           Id of the form these fields belong to, when they sit outside it.
	 * }
	 * @return string Escaped HTML.
	 */
	public static function render( $args ) {
		global $wpstream_plugin;
		$args = wp_parse_args( $args, array( 'mode' => 'upload', 'recording' => '', 'recordings' => array(), 'external' => '', 'mode_name' => '', 'recording_name' => '', 'external_name' => '', 'captions' => '', 'captions_name' => '', 'form' => '' ) );

		// Each field posts only where the screen gives it a name, optionally to a form elsewhere on the page.
		$form = $args['form'] ? ' form="' . esc_attr( $args['form'] ) . '"' : '';
		$field = function ( $name ) use ( $form ) { return ( $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . $form; };

		// A div keeps the uploader valid inside the classic editor's post form; the script posts its fields itself.
		$upload = str_replace( array( '<form ', '</form>' ), array( '<div ', '</div>' ), $wpstream_plugin->admin->wpstream_present_media_upload( true ) );

		// The script reads its messages from here, so every screen says the same thing.
		$html = '<div class="wpstream-video-source" data-video-source'
			. ' data-text-preview-error="' . esc_attr__( 'This video cannot be previewed. Please try downloading it.', 'wpstream' ) . '"'
			. ' data-text-external-error="' . esc_attr__( 'This URL cannot be previewed here. Check it on the video page after publishing.', 'wpstream' ) . '"'
			. ' data-text-invalid-url="' . esc_attr__( 'Enter a valid http or https video URL.', 'wpstream' ) . '"'
			. ' data-text-captions-title="' . esc_attr__( 'Select .vtt captions file', 'wpstream' ) . '"'
			. ' data-text-captions-type="' . esc_attr__( 'Choose a .vtt captions file.', 'wpstream' ) . '">';

		// The source choice comes first, with the controls for that source directly under it.
		$html .= '<label for="video-source-mode">' . esc_html__( 'Video source', 'wpstream' ) . '</label><select id="video-source-mode" data-video-mode' . $field( $args['mode_name'] ) . '>';
		$modes = array( 'upload' => __( 'Upload a video', 'wpstream' ), 'recording' => __( 'Existing recording', 'wpstream' ), 'external' => __( 'Self-hosted or external URL', 'wpstream' ) );
		foreach ( $modes as $mode => $label ) { $html .= '<option value="' . esc_attr( $mode ) . '"' . selected( $args['mode'], $mode, false ) . '>' . esc_html( $label ) . '</option>'; }
		$html .= '</select>';

		$html .= '<div data-video-upload-fields' . ( 'upload' === $args['mode'] ? '' : ' hidden' ) . '><div id="wpstream_media_upload" class="recording-upload-zone"><p>' . esc_html__( 'Drag a video here or choose a file.', 'wpstream' ) . '</p>' . $upload . '</div><p class="description">' . esc_html__( 'Uploaded videos become available after processing.', 'wpstream' ) . '</p></div>';

		$html .= '<div data-video-recording-fields' . ( 'recording' === $args['mode'] ? '' : ' hidden' ) . '><label for="video-source">' . esc_html__( 'Use an existing recording', 'wpstream' ) . '</label><select id="video-source" data-video-recording' . $field( $args['recording_name'] ) . '><option value="">' . esc_html__( 'Select a recording', 'wpstream' ) . '</option>';
		foreach ( array_keys( $args['recordings'] ) as $name ) { $html .= '<option value="' . esc_attr( $name ) . '"' . selected( $args['recording'], $name, false ) . '>' . esc_html( $name ) . '</option>'; }
		$html .= '</select></div>';

		$html .= '<div data-video-external-fields' . ( 'external' === $args['mode'] ? '' : ' hidden' ) . '><label for="video-external-url">' . esc_html__( 'Video URL', 'wpstream' ) . '</label><input id="video-external-url" type="url" data-video-external' . $field( $args['external_name'] ) . ' value="' . esc_attr( $args['external'] ) . '"></div>';

		// The preview follows the controls, then any message about it and the list of files being processed.
		$html .= '<div class="library-video-stage" data-video-stage hidden><video controls playsinline preload="metadata" aria-label="' . esc_attr__( 'Video preview', 'wpstream' ) . '" hidden></video><div data-video-placeholder><h2>' . esc_html__( 'Video preview', 'wpstream' ) . '</h2><p>' . esc_html__( 'Select a recording to preview it.', 'wpstream' ) . '</p></div><p class="wpstream-video-source-loading" data-video-loading role="status" hidden>' . esc_html__( 'Loading preview…', 'wpstream' ) . '</p></div>';
		$html .= '<p class="wpstream-video-source-notice" data-video-source-notice role="status"></p><div id="wpstream_file_management"><div id="video_management_title"></div></div>';

		// Captions belong to the video whichever source it uses, so they always show. Remove only appears once there is a file.
		$html .= '<div class="wpstream-video-source-captions"><label for="wpstream_closed_captions_file">' . esc_html__( 'Captions file (optional)', 'wpstream' ) . '</label><input id="wpstream_closed_captions_file" type="url" data-video-captions' . $field( $args['captions_name'] ) . ' value="' . esc_attr( $args['captions'] ) . '"><div class="wpstream-video-source-captions-actions"><button type="button" class="button" data-caption-picker>' . esc_html__( 'Select .vtt captions file', 'wpstream' ) . '</button><button type="button" class="button" data-caption-remove' . ( $args['captions'] ? '' : ' hidden' ) . '>' . esc_html__( 'Remove captions', 'wpstream' ) . '</button></div></div></div>';
		return $html;
	}
}
