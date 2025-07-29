<?php

/**
 * Dashboard functions
 *
 * @package wpstream-plugin
 */

if ( ! function_exists( 'wpstream_theme_return_user_channel_list' ) ) {
	/**
	 * Return user channel list.
	 *
	 * This function generates and returns HTML markup for a select field containing user channels.
	 *
	 * @param int    $already_selected The ID of the already selected channel.
	 * @param string $return_type The type of return. Default is empty.
	 * @return string|int The HTML markup for the select field or the count of found posts.
	 */
	function wpstream_theme_return_user_channel_list( $already_selected, $return_type = '' ) {
		$current_user  = wp_get_current_user();
		$limit         = 100;
		$return_string = '';

		if ( wpstream_check_woo_active() ) {
			$post_type      = array( 'product', 'wpstream_product' );
			$taxonomy_array = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array( 'live_stream' ),
				),
				array(
					'taxonomy' => 'product_type',
					'operator' => 'NOT EXISTS',
				),
			);
		} else {
			$post_type      = 'wpstream_product';
			$taxonomy_array = array();
		}

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'tax_query'      => $taxonomy_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'author'         => $current_user->ID,
		);

		$query_list = new WP_Query( $args );

		if ( 'found_posts' === $return_type ) {
			return intval( $query_list->found_posts );
		}

		$return_string .= '<select id="wpstream-user-channel-selection" name="wpstream-user-channel-selection" '
			. 'class="wpstream-user-channel-selection">'
			. '<option value="">' . esc_html__( 'select the channel', 'hello-wpstream' ) . '</value>';

		if ( $query_list->have_posts() ) {
			while ( $query_list->have_posts() ) :
				$query_list->the_post();
				$the_id       = get_the_ID();
				$the_title    = get_the_title( $the_id );
				$post_type    = get_post_type( $the_id );
				$product_type = '';

				if ( get_post_type( $the_id ) === 'product' ) {
					$product_type = get_post_meta( $the_id, '_product_type', true );
				}

				$image_url      = '';
				$return_string .= '<option value="' . intval( $the_id ) . '"';
				if ( intval( $already_selected ) === $the_id ) {
					$return_string .= ' selected';
				}
				$return_string .= ' data-thumbnail="' . esc_url( $image_url ) . '">';
				$return_string .= esc_html( $the_title ) . '</option>';

			endwhile;
		}

		$return_string .= '</select>';

		wp_reset_postdata();

		return $return_string;
	}
}

/**
 * Localize the upload script for images.
 *
 * @param string $button_upload The ID of the upload button.
 */
function wpstream_theme_localize_upload_script_images( $button_upload ) {
	$plup_url = add_query_arg(
		array(
			'action' => 'wpstream_me_upload',
			'nonce'  => wp_create_nonce( 'aaiu_allow' ),
		),
		esc_url( admin_url( 'admin-ajax.php' ) )
	);

	$max_file_size = 100 * 1000 * 1000;

	$plupload_values = array(
		'runtimes'         => 'html5,flash,html4',
		'max_file_size'    => $max_file_size . 'b',
		'url'              => $plup_url,
		'file_data_name'   => 'aaiu_upload_file',
		'flash_swf_url'    => includes_url( 'js/plupload/plupload.flash.swf' ),
		'filters'          => array(
			array(
				'title'      => esc_html__( 'Allowed Files', 'hello-wpstream' ),
				'extensions' => 'jpeg,jpg,gif,png,pdf,webp,mp4,mov',
			),
		),
		'multipart'        => true,
		'urlstream_upload' => true,
		'multipart_params' => array( 'button_id' => $button_upload ),
	);

	$tmp_plupload_values = array(
		'browse_button' => $button_upload,
		'container'     => 'aaiu-upload-container',
	);

	$plupload_values                 = wp_parse_args( $plupload_values, $tmp_plupload_values );
	$plupload_values['drop_element'] = 'drag-and-drop';
	$max_images                      = 20;

	wp_localize_script(
		'ajax-upload',
		'ajax_vars',
		array(
			'ajaxurl'        => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonce'          => wp_create_nonce( 'aaiu_upload' ),
			'remove'         => wp_create_nonce( 'aaiu_remove' ),
			'number'         => 1,
			'upload_enabled' => true,
			'warning'        => __( 'Image needs to be at least 500px height  x 500px wide!', 'hello-wpstream' ),
			'max_images'     => $max_images,
			'warning_max'    => __( 'You cannot upload more than', 'hello-wpstream' ) . ' ' . $max_images . ' ' . __( 'images', 'hello-wpstream' ),
			'path'           => trailingslashit( WPSTREAM_PLUGIN_PATH ),
			'confirmMsg'     => esc_html__( 'Are you sure you want to delete this?', 'hello-wpstream' ),
			'plupload'       => $plupload_values,
		)
	);
}

if ( ! function_exists( 'wpstream_theme_return_image_upload_markup' ) ) {
	/**
	 * Return the markup for image upload.
	 *
	 * This function generates and returns the markup for image upload based on the provided post ID.
	 *
	 * @param int $user_id The ID of the user.
	 * @param int $post_id The ID of the post.
	 * @return string The HTML markup for image upload.
	 */
	function wpstream_theme_return_image_upload_markup( $user_id, $post_id ) {
		$post_type      = get_post_type( $post_id );
		$gallery_images = '';

		if ( 'product' === $post_type ) {
			$gallery_images_source = get_post_meta( $post_id, '_product_image_gallery', true );
			$gallery_images        = explode( ',', $gallery_images_source );
		} else {
			if(function_exists('rwmb_meta')){
				$gallery_images = rwmb_meta( 'wpstream_theme_gallery', array(), $post_id );

				if ( is_array( $gallery_images ) ) {
					$gallery_images = array_keys( $gallery_images );
				}
			}
		}

		$gallery_images = wpstream_theme_return_image_gallery( $post_id );

		$thumbid = get_post_thumbnail_id( $post_id );
		$images  = '';

		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {

				$preview = wp_get_attachment_image_src( $attachment_id, 'wpstream_featured_unit_cards' );

				if ( $preview && $preview[0] != '' ) {
					$images .= '<div class="wpstream_uploaded_images" data-imageid="' . esc_attr( $attachment_id ) . '">'
						. '<img src="' . esc_url( $preview[0] ) . '" alt="' . esc_html__( 'thumb', 'hello-wpstream' ) . '" />'
						. '<i class="far fa-trash-alt wpstream_delete_image"></i>';

					$images .= '</div>';
				}
			}
		}

		$gallery_images_string = '';
		if ( is_array( $gallery_images ) ) {
			$gallery_images_string = implode( ',', $gallery_images );
		}

		$return_string = '';
		ob_start();
		?>

		<div id="upload-container" class="upload-container">
			<div id="aaiu-upload-container upload-container__body">
				<h3><?php echo esc_html__( 'Image list', 'hello-wpstream' ); ?></h3>
				<div id="aaiu-upload-imagelist">
					<ul id="aaiu-ul-list" class="aaiu-upload-list"></ul>
				</div>

				<div id="wpstream_imagelist">
					<?php
					$ajax_nonce = wp_create_nonce( 'wpstream_image_upload' );
					print '<input type="hidden" id="wpstream_image_upload" value="' . esc_html( $ajax_nonce ) . '" />    ';
					if ( '' !== $images ) {
						print trim( $images ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>

				<div class="upload-container__actions-wrap upload-container__actions-wrap--row">
					<div id="drag-and-drop" class="rh_drag_and_drop_wrapper ">
						<div id="aaiu-uploader" class="wpstream_theme_button_dashboard">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" clip-rule="evenodd" d="M10.7379 16.6273C9.96427 16.6273 9.31895 16.036 9.2514 15.2654C9.11015 13.6541 9.07441 12.0356 9.14427 10.4203C9.05994 10.4147 8.97563 10.4088 8.89133 10.4026L7.40178 10.2941C6.44973 10.2247 5.91752 9.16309 6.43151 8.35871C7.5277 6.6432 9.53693 4.72314 11.1904 3.53541C11.6742 3.18786 12.3258 3.18786 12.8097 3.53541C14.4631 4.72314 16.4723 6.64319 17.5685 8.35871C18.0825 9.16309 17.5503 10.2247 16.5983 10.2941L15.1087 10.4026C15.0244 10.4088 14.9401 10.4147 14.8558 10.4203C14.9256 12.0356 14.8899 13.6541 14.7486 15.2654C14.6811 16.036 14.0358 16.6273 13.2622 16.6273H10.7379ZM10.6815 9.76253C10.5678 11.5498 10.589 13.3431 10.745 15.1273H13.255C13.411 13.3431 13.4323 11.5498 13.3186 9.76253C13.3058 9.56216 13.3739 9.36505 13.5077 9.21531C13.6414 9.06556 13.8296 8.9757 14.0301 8.96582C14.3535 8.94989 14.6767 8.93015 14.9997 8.90661L16.0815 8.82775C15.1219 7.41445 13.9204 6.1802 12.5313 5.18235L12 4.80071L11.4687 5.18235C10.0796 6.1802 8.87813 7.41446 7.91858 8.82775L9.00038 8.90661C9.32337 8.93015 9.64656 8.94989 9.9699 8.96582C10.1704 8.9757 10.3586 9.06556 10.4924 9.21531C10.6261 9.36505 10.6942 9.56216 10.6815 9.76253Z" fill="#0F0F0F"/>
								<path d="M5.75 17C5.75 16.5858 5.41421 16.25 5 16.25C4.58579 16.25 4.25 16.5858 4.25 17V19C4.25 19.9665 5.0335 20.75 6 20.75H18C18.9665 20.75 19.75 19.9665 19.75 19V17C19.75 16.5858 19.4142 16.25 19 16.25C18.5858 16.25 18.25 16.5858 18.25 17V19C18.25 19.1381 18.1381 19.25 18 19.25H6C5.86193 19.25 5.75 19.1381 5.75 19V17Z" fill="#0F0F0F"/>
							</svg>

							<?php esc_html_e( 'Upload More', 'hello-wpstream' ); ?>
						</div>
					</div>

					<input type="hidden" name="attachid" id="attachid" value="<?php print esc_html( $gallery_images_string ); ?>">
					<input type="hidden" name="attachthumb" id="attachthumb" value="<?php print esc_html( $thumbid ); ?>">
					<p class="full_form full_form_image">
						<?php
						esc_html_e(
							"It's recommended to use a picture that's at least 1280 x 720 pixels and 4MB or less. 
                        Use a PNG or GIF (no animations) file.",
							'hello-wpstream'
						);
						?>
					</p>
				</div>
			</div>
		</div>
		<?php
		$return_string .= ob_get_contents();
		ob_end_clean();

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_return_image_upload_markup_single' ) ) {
	/**
	 * Return the markup for single image upload.
	 *
	 * This function generates and returns the markup for single image upload based on the provided post ID.
	 *
	 * @param int $user_id The ID of the user.
	 * @param int $post_id The ID of the post.
	 * @return string The HTML markup for single image upload.
	 */
	function wpstream_theme_return_image_upload_markup_single( $post_id ) {
		$thumbid = get_post_thumbnail_id( $post_id );
		$images  = '';
		$preview = wp_get_attachment_image_src( $thumbid, 'wpstream_featured_unit_cards' );

		if ( isset( $preview[0] ) && '' !== $preview[0] ) {
			$images .= '<div class="wpstream_uploaded_images"  id="wpstream_uploaded_profile_image" data-imageid="' . esc_attr( $thumbid ) . '">'
				. '<img src="' . esc_url( $preview[0] ) . '" alt="' . esc_html__( 'thumb', 'hello-wpstream' ) . '" /></div>';
		}

		$return_string = '';
		ob_start();
		?>

		<div id="upload-container">
			<div id="aaiu-upload-container" class="upload-container">
				<p class="upload-container__title"><?php echo esc_html__( 'Channel Thumbnail', 'hello-wpstream' ); ?></p>
				<div class="upload-container__row">
					<div class="upload-container__image-wrap">
						<div id="aaiu-upload-imagelist_single">
							<ul id="aaiu-ul-list" class="aaiu-upload-list"></ul>
						</div>

						<div id="wpstream_imagelist_single">
							<?php
							$ajax_nonce = wp_create_nonce( 'wpstream_image_upload' );
							print '<input type="hidden" id="wpstream_image_upload" value="' . esc_html( $ajax_nonce ) . '" />    ';
							if ( '' !== $images ) {
								print trim( $images ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>

						<input type="hidden" name="attachthumb" id="attachthumb" value="<?php print esc_html( $thumbid ); ?>">
					</div>

					<div class="upload-container__actions-wrap upload-container__actions-wrap--column">
						<p class="full_form full_form_image">
							<?php
							esc_html_e(
								"It's recommended to use a picture that's at least 1280 x 720 pixels and 4MB or less. 
                        Use a PNG or GIF (no animations) file.",
								'hello-wpstream'
							);
							?>
						</p>

						<div id="drag-and-drop" class="rh_drag_and_drop_wrapper">
							<div id="aaiu-uploader-single" class="wpstream_theme_button_dashboard">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M10.7379 16.6273C9.96427 16.6273 9.31895 16.036 9.2514 15.2654C9.11015 13.6541 9.07441 12.0356 9.14427 10.4203C9.05994 10.4147 8.97563 10.4088 8.89133 10.4026L7.40178 10.2941C6.44973 10.2247 5.91752 9.16309 6.43151 8.35871C7.5277 6.6432 9.53693 4.72314 11.1904 3.53541C11.6742 3.18786 12.3258 3.18786 12.8097 3.53541C14.4631 4.72314 16.4723 6.64319 17.5685 8.35871C18.0825 9.16309 17.5503 10.2247 16.5983 10.2941L15.1087 10.4026C15.0244 10.4088 14.9401 10.4147 14.8558 10.4203C14.9256 12.0356 14.8899 13.6541 14.7486 15.2654C14.6811 16.036 14.0358 16.6273 13.2622 16.6273H10.7379ZM10.6815 9.76253C10.5678 11.5498 10.589 13.3431 10.745 15.1273H13.255C13.411 13.3431 13.4323 11.5498 13.3186 9.76253C13.3058 9.56216 13.3739 9.36505 13.5077 9.21531C13.6414 9.06556 13.8296 8.9757 14.0301 8.96582C14.3535 8.94989 14.6767 8.93015 14.9997 8.90661L16.0815 8.82775C15.1219 7.41445 13.9204 6.1802 12.5313 5.18235L12 4.80071L11.4687 5.18235C10.0796 6.1802 8.87813 7.41446 7.91858 8.82775L9.00038 8.90661C9.32337 8.93015 9.64656 8.94989 9.9699 8.96582C10.1704 8.9757 10.3586 9.06556 10.4924 9.21531C10.6261 9.36505 10.6942 9.56216 10.6815 9.76253Z" fill="#0F0F0F"/>
									<path d="M5.75 17C5.75 16.5858 5.41421 16.25 5 16.25C4.58579 16.25 4.25 16.5858 4.25 17V19C4.25 19.9665 5.0335 20.75 6 20.75H18C18.9665 20.75 19.75 19.9665 19.75 19V17C19.75 16.5858 19.4142 16.25 19 16.25C18.5858 16.25 18.25 16.5858 18.25 17V19C18.25 19.1381 18.1381 19.25 18 19.25H6C5.86193 19.25 5.75 19.1381 5.75 19V17Z" fill="#0F0F0F"/>
								</svg>

								<?php esc_html_e( 'Change', 'hello-wpstream' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$return_string .= ob_get_contents();
		ob_end_clean();

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_return_trailer_upload_markup' ) ) {
	/**
	 * Return the markup for trailer video upload.
	 *
	 * This function generates and returns the markup for trailer video upload based on the provided post ID.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The HTML markup for trailer video upload.
	 */
	function wpstream_theme_return_trailer_upload_markup( $post_id ) {
		$trailer_id = get_post_meta( $post_id, 'video_trailer', true );
		$attachment_url      = wp_get_attachment_url( $trailer_id );
		$video_html = '';

		if ( $trailer_id && $attachment_url ) {
			$video_html = '<div class="wpstream_uplod_video" id="wpstream_uploaded_trailer" data-videoid="' . esc_attr( $trailer_id ) . '">'
				.'<video height="240" controls><source src="' . esc_url( $attachment_url ) . '" type="video/mp4"></video></div>';
		}

		$return_string = '';
		ob_start();
		?>

        <div id="trailer-upload-container">
            <div id="aaiu-trailer-container" class="upload-container">
                <p class="upload-container__title"><?php echo esc_html__( 'Video Trailer', 'hello-wpstream' ); ?></p>
                <div class="upload-container__row">
                    <div class="upload-container__video-wrap">
                        <div id="aaiu-upload-trailer">
                            <ul id="aaiu-ul-list" class="aaiu-upload-list"></ul>
                        </div>

                        <div id="wpstream_trailerlist">
							<?php
							$ajax_nonce = wp_create_nonce( 'wpstream_trailer_upload' );
							print '<input type="hidden" id="wpstream_trailer_upload" value="' . esc_html( $ajax_nonce ) . '" />    ';
							if ( '' !== $video_html ) {
								print trim( $video_html ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
                        </div>

                        <input type="hidden" id="upload_trailer"  class="wpstream_upload_file" />
                    </div>

                    <div class="upload-container__actions-wrap upload-container__actions-wrap--column">
                        <p class="full_form full_form_trailer">
							<?php
							esc_html_e( "Upload a video trailer for your content. Supported formats: MP4, MOV", 'hello-wpstream' );
							?>
                        </p>

                        <div id="drag-and-drop" class="rh_drag_and_drop_wrapper">
                            <div id="aaiu-uploader-trailer" class="wpstream_theme_button_dashboard">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.7379 16.6273C9.96427 16.6273 9.31895 16.036 9.2514 15.2654C9.11015 13.6541 9.07441 12.0356 9.14427 10.4203C9.05994 10.4147 8.97563 10.4088 8.89133 10.4026L7.40178 10.2941C6.44973 10.2247 5.91752 9.16309 6.43151 8.35871C7.5277 6.6432 9.53693 4.72314 11.1904 3.53541C11.6742 3.18786 12.3258 3.18786 12.8097 3.53541C14.4631 4.72314 16.4723 6.64319 17.5685 8.35871C18.0825 9.16309 17.5503 10.2247 16.5983 10.2941L15.1087 10.4026C15.0244 10.4088 14.9401 10.4147 14.8558 10.4203C14.9256 12.0356 14.8899 13.6541 14.7486 15.2654C14.6811 16.036 14.0358 16.6273 13.2622 16.6273H10.7379ZM10.6815 9.76253C10.5678 11.5498 10.589 13.3431 10.745 15.1273H13.255C13.411 13.3431 13.4323 11.5498 13.3186 9.76253C13.3058 9.56216 13.3739 9.36505 13.5077 9.21531C13.6414 9.06556 13.8296 8.9757 14.0301 8.96582C14.3535 8.94989 14.6767 8.93015 14.9997 8.90661L16.0815 8.82775C15.1219 7.41445 13.9204 6.1802 12.5313 5.18235L12 4.80071L11.4687 5.18235C10.0796 6.1802 8.87813 7.41446 7.91858 8.82775L9.00038 8.90661C9.32337 8.93015 9.64656 8.94989 9.9699 8.96582C10.1704 8.9757 10.3586 9.06556 10.4924 9.21531C10.6261 9.36505 10.6942 9.56216 10.6815 9.76253Z" fill="#0F0F0F"/>
                                    <path d="M5.75 17C5.75 16.5858 5.41421 16.25 5 16.25C4.58579 16.25 4.25 16.5858 4.25 17V19C4.25 19.9665 5.0335 20.75 6 20.75H18C18.9665 20.75 19.75 19.9665 19.75 19V17C19.75 16.5858 19.4142 16.25 19 16.25C18.5858 16.25 18.25 16.5858 18.25 17V19C18.25 19.1381 18.1381 19.25 18 19.25H6C5.86193 19.25 5.75 19.1381 5.75 19V17Z" fill="#0F0F0F"/>
                                </svg>

								<?php esc_html_e( 'Change', 'hello-wpstream' ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php
		$return_string .= ob_get_contents();
		ob_end_clean();

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_return_preview_upload_markup' ) ) {
	/**
	 * Return the markup for preview video upload.
	 *
	 * This function generates and returns the markup for preview video upload based on the provided post ID.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The HTML markup for preview video upload.
	 */
	function wpstream_theme_return_preview_upload_markup( $post_id ) {
		$preview_id = get_post_meta( $post_id, 'video_preview', true );
		$attachment_url      = wp_get_attachment_url( $preview_id );
		$video_html = '';

		if ( $preview_id && $attachment_url ) {
			$video_html = '<div class="wpstream_uplod_video" id="wpstream_uploaded_preview" data-videoid="' . esc_attr( $preview_id ) . '">'
				.'<video height="240" controls><source src="' . esc_url( $attachment_url ) . '" type="video/mp4"></video></div>';
		}

		$return_string = '';
		ob_start();
		?>

        <div id="preview-upload-container">
            <div id="aaiu-preview-container" class="upload-container">
                <p class="upload-container__title"><?php echo esc_html__( 'Video Preview', 'hello-wpstream' ); ?></p>
                <div class="upload-container__row">
                    <div class="upload-container__video-wrap">
                        <div id="aaiu-upload-preview">
                            <ul id="aaiu-ul-list" class="aaiu-upload-list"></ul>
                        </div>

                        <div id="wpstream_previewlist">
							<?php
							$ajax_nonce = wp_create_nonce( 'wpstream_preview_upload' );
							print '<input type="hidden" id="wpstream_preview_upload" value="' . esc_html( $ajax_nonce ) . '" />    ';
							if ( '' !== $video_html ) {
								print trim( $video_html ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
                        </div>

                        <input type="hidden" id="upload_preview"  class="wpstream_upload_file" />
                    </div>

                    <div class="upload-container__actions-wrap upload-container__actions-wrap--column">
                        <p class="full_form full_form_trailer">
							<?php
							esc_html_e( "Upload a video preview for your content. Supported formats: MP4, MOV", 'hello-wpstream' );
							?>
                        </p>

                        <div id="drag-and-drop" class="rh_drag_and_drop_wrapper">
                            <div id="aaiu-uploader-preview" class="wpstream_theme_button_dashboard">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.7379 16.6273C9.96427 16.6273 9.31895 16.036 9.2514 15.2654C9.11015 13.6541 9.07441 12.0356 9.14427 10.4203C9.05994 10.4147 8.97563 10.4088 8.89133 10.4026L7.40178 10.2941C6.44973 10.2247 5.91752 9.16309 6.43151 8.35871C7.5277 6.6432 9.53693 4.72314 11.1904 3.53541C11.6742 3.18786 12.3258 3.18786 12.8097 3.53541C14.4631 4.72314 16.4723 6.64319 17.5685 8.35871C18.0825 9.16309 17.5503 10.2247 16.5983 10.2941L15.1087 10.4026C15.0244 10.4088 14.9401 10.4147 14.8558 10.4203C14.9256 12.0356 14.8899 13.6541 14.7486 15.2654C14.6811 16.036 14.0358 16.6273 13.2622 16.6273H10.7379ZM10.6815 9.76253C10.5678 11.5498 10.589 13.3431 10.745 15.1273H13.255C13.411 13.3431 13.4323 11.5498 13.3186 9.76253C13.3058 9.56216 13.3739 9.36505 13.5077 9.21531C13.6414 9.06556 13.8296 8.9757 14.0301 8.96582C14.3535 8.94989 14.6767 8.93015 14.9997 8.90661L16.0815 8.82775C15.1219 7.41445 13.9204 6.1802 12.5313 5.18235L12 4.80071L11.4687 5.18235C10.0796 6.1802 8.87813 7.41446 7.91858 8.82775L9.00038 8.90661C9.32337 8.93015 9.64656 8.94989 9.9699 8.96582C10.1704 8.9757 10.3586 9.06556 10.4924 9.21531C10.6261 9.36505 10.6942 9.56216 10.6815 9.76253Z" fill="#0F0F0F"/>
                                    <path d="M5.75 17C5.75 16.5858 5.41421 16.25 5 16.25C4.58579 16.25 4.25 16.5858 4.25 17V19C4.25 19.9665 5.0335 20.75 6 20.75H18C18.9665 20.75 19.75 19.9665 19.75 19V17C19.75 16.5858 19.4142 16.25 19 16.25C18.5858 16.25 18.25 16.5858 18.25 17V19C18.25 19.1381 18.1381 19.25 18 19.25H6C5.86193 19.25 5.75 19.1381 5.75 19V17Z" fill="#0F0F0F"/>
                                </svg>

								<?php esc_html_e( 'Change', 'hello-wpstream' ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php
		$return_string .= ob_get_contents();
		ob_end_clean();

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_build_html_gallery_dashboard' ) ) {
	/**
	 * Build HTML for the gallery in the dashboard.
	 *
	 * This function builds HTML for the gallery in the dashboard based on the provided array of image IDs.
	 *
	 * @param array $gallery_images An array of image IDs.
	 * @return string The HTML string for the gallery.
	 */
	function wpstream_theme_build_html_gallery_dashboard( $gallery_images ) {
		$return_string = '';

		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {
				$preview = wp_get_attachment_image_src( $attachment_id, 'wpstream_featured_unit_cards' );

				if ( $preview && '' !== $preview[0] ) {
					$return_string .= '<div class="wpstream_uploaded_images" data-imageid="' . esc_attr( $attachment_id ) . '">';
					$return_string .= '<img src="' . esc_url( $preview[0] ) . '" alt="' . esc_html__( 'thumb', 'hello-wpstream' ) . '" /></div>';
				}
			}
		}

		return $return_string;
	}
}