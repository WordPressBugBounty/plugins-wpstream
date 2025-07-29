<?php
/**
 * Start streaming template
 *
 * @package wpstream-theme
 */

if ( ! wpstream_check_if_user_can_stream()) {
	return;
}
?>
    <div class="wpstream-dashboard-start-streaming">
        <div class="wpstream-dashboard-start-streaming__header">
            <h1 class="wpstream-dashboard-start-streaming__title">
				<?php echo esc_html__( 'Start Streaming','hello-wpstream'); ?>
            </h1>

            <div class="wpstream-dashboard-start-streaming__cta-wrapper">
				<?php wpstream_theme_create_channel(); ?>
            </div>
        </div>

        <div class="wpstream-theme-dashboard-select-channel">

            <div id="wpstream-theme-dashboard-select-channel-notification" class="wpstream-theme-dashboard-select-channel-notification"></div>

			<?php
			$current_user      = wp_get_current_user(); //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$current_selection = get_user_meta( $current_user->ID, 'wpstream_start_streaming_channel', true );
			?>
            <label for="wpstream-user-channel-selection">
				<?php
				esc_html_e('Select the channel','hello-wpstream');
				?>
            </label>


            <div class="wpstream_theme_dashboard_channel_selector_wrapper">
				<?php
				print wpstream_theme_return_user_channel_list( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
            </div>

			<?php
			print wpstream_show_live_streaming_controls( $current_user, $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$title              = get_the_title( $current_selection );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post               = get_post( $current_selection ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$description        = $post->post_content;
			$featured_image_url = get_the_post_thumbnail_url( $current_selection, 'wpstream_featured_unit_cards' );

			$price = get_post_meta( $current_selection, '_price', true );
			?>


			<?php require get_template_directory() . '/template-parts/dashboard-templates/template-start-streaming-edit-modal.php'; ?>
        </div>
        <!-- /.wpstream-theme-dashboard-select-channel  -->

        <div class="wpstream-theme-dashboard-chanel-details">
            <div class="wpstream-theme-dashboard-chanel-details__header">
                <h3 class="wpstream-theme-dashboard-chanel-details__title" id="wpstream_channel_title"><?php echo esc_html( $title ); ?></h3>

                <button type="button" class="wpstream-theme-dashboard-chanel-details__edit-button wpstream_theme_button_dashboard" data-bs-toggle="modal" data-bs-target="#wpstream_edit_channel_modal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M15.1369 3.46967C14.9963 3.32902 14.8055 3.25 14.6066 3.25C14.4077 3.25 14.2169 3.32902 14.0763 3.46967L4.88388 12.6621C4.78965 12.7563 4.72223 12.8739 4.68856 13.0028L3.68856 16.8313C3.62127 17.0889 3.69561 17.3629 3.88388 17.5511C4.07215 17.7394 4.34614 17.8138 4.60375 17.7465L8.43218 16.7465C8.56111 16.7128 8.67874 16.6454 8.77297 16.5511L17.9654 7.35876C18.2582 7.06586 18.2582 6.59099 17.9654 6.2981L15.1369 3.46967ZM6.08843 13.5788L14.6066 5.06066L16.3744 6.82843L7.8562 15.3466L5.46344 15.9716L6.08843 13.5788Z" fill="#0F0F0F"/>
                        <path d="M4 19.25C3.58579 19.25 3.25 19.5858 3.25 20C3.25 20.4142 3.58579 20.75 4 20.75H19C19.4142 20.75 19.75 20.4142 19.75 20C19.75 19.5858 19.4142 19.25 19 19.25H4Z" fill="#0F0F0F"/>
                    </svg>

					<?php
					esc_html_e('Edit Channel','hello-wpstream');
					?>
                </button>
            </div>
            <!-- /.wpstream-theme-dashboard-chanel-details__header -->

            <div class="wpstream-theme-dashboard-chanel-details__description wpstream-dashboard-details">
                <div class="wpstream-dashboard-details-header">
					<?php
					esc_html_e('Description','hello-wpstream');
					?>
                </div>
                <div class="wpstream_account_details_value" id="wpstream_channel_description">
					<?php
					echo esc_html($description)
					?>
                </div>
            </div>
            <!-- /.wpstream-dashboard-details -->

			<?php
			if (  function_exists( 'get_woocommerce_currency_symbol' ) ) {
				$extra_style=" display:none ";
				if( 'product' === get_post_type( $current_selection ) ){
					$extra_style ='';
				}
				?>
                <div class="wpstream-dashboard-details wpstream-dashboard-details_price" style="<?php echo esc_attr($extra_style);?>" >
                    <div class="wpstream-dashboard-details-header"><?php esc_html_e( 'Price', 'hello-wpstream' ); ?></div>
                    <div class="wpstream_account_details_value">

                        <div class="wpstream_account_details_value">
							<span id="wpstream_channel_price">
								<?php
								if ( '' === $price ) {
									$to_display = esc_html__('Price is not set','hello-wpstream');
								} else {
									$to_display = get_woocommerce_currency_symbol() . ' ' . esc_html( $price );
								}
								echo trim( $to_display );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</span>
                        </div>

                    </div>
                </div>
				<?php
			}
			?>

            <div class="wpstream-theme-dashboard-chanel-details__taxonomies_wrapper wpstream_taxonomies_wrapper">
				<?php
				$taxonomy_information = wpstream_theme_return_taxoomy_information( $current_selection );
				print trim( $taxonomy_information['html'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
            </div>
            <!-- /.wpstream-theme-dashboard-chanel-details__taxonomies_wrapper -->

			<?php
			$video_trailer = wpstream_theme_return_trailer_video( $current_selection );
			if ( ! empty( $video_trailer ) ) {
				?>
                <div class="wpstream-theme-dashboard-channel-video-trailer">
                    <h4 class="wpstream-theme-dashboard-channel-video-trailer__title">
						<?php esc_html_e('Trailer Video','hello-wpstream'); ?>
                    </h4>
                    <div class="wpstream-theme-dashboard-channel-video-trailer__video">
						<?php echo wpstream_theme_build_html_video_trailer_dashboard( $video_trailer );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
				<?php
			} else {
				?>
                <div class="wpstream-theme-dashboard-channel-video-trailer">
                    <h4 class="wpstream-theme-dashboard-channel-video-trailer__title">
						<?php esc_html_e('Trailer Video','hello-wpstream'); ?>
                    </h4>
                    <div class="wpstream-theme-dashboard-channel-video-trailer__video">
						<?php esc_html_e('There is no trailer video. Edit channel to add.','hello-wpstream'); ?>
                    </div>
                </div>
				<?php
			}
			?>

			<?php
			$video_preview = wpstream_theme_return_preview_video( $current_selection );
			if ( ! empty( $video_preview ) ) {
				?>
                <div class="wpstream-theme-dashboard-channel-video-preview">
                    <h4 class="wpstream-theme-dashboard-channel-video-preview__title">
						<?php esc_html_e('Preview Video','hello-wpstream'); ?>
                    </h4>
                    <div class="wpstream-theme-dashboard-channel-video-preview__video">
						<?php echo wpstream_theme_build_html_video_preview_dashboard( $video_preview );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
				<?php
			} else {
				?>
                <div class="wpstream-theme-dashboard-channel-video-preview">
                    <h4 class="wpstream-theme-dashboard-channel-video-preview__title">
						<?php esc_html_e('Preview Video','hello-wpstream'); ?>
                    </h4>
                    <div class="wpstream-theme-dashboard-channel-video-preview__video">
						<?php esc_html_e('There is no preview video. Edit channel to add.','hello-wpstream'); ?>
                    </div>
                </div>
				<?php
			}
			?>

            <div class="wpstream-theme-dashboard-chanel-gallery">
				<?php
				$gallery_images = wpstream_theme_return_image_gallery( $current_selection );

				?>

				<?php if ( empty( $gallery_images ) ) : ?>
                    <h4 class="wpstream_uploaded_images_">
						<?php
						esc_html_e('There are no images. Edit channel to add.','hello-wpstream');
						?>
                    </h4>
				<?php else : ?>
                    <h4 class="wpstream-theme-dashboard-chanel-gallery__title">
						<?php
						esc_html_e('Image Gallery','hello-wpstream');
						?>
                    </h4>
				<?php endif; ?>

                <div class="wpstream-theme-dashboard-chanel-gallery__list">
					<?php print wpstream_theme_build_html_gallery_dashboard( $gallery_images );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <!-- /.wpstream-theme-dashboard-chanel-gallery__list -->
            </div>
            <!-- /.wpstream-theme-dashboard-chanel-gallery -->

			<?php
			wp_nonce_field( 'wpstream_user_channel_list', 'wpstream_user_channel_list_nonce' );
			?>
        </div>
        <!-- /.wpstream-theme-dashboard-chanel-details -->
    </div>
<?php wpstream_theme_localize_upload_script_images( array( 'aaiu-uploader', 'aaiu-uploader-single', 'aaiu-uploader-trailer', 'aaiu-uploader-preview' ) ); ?>