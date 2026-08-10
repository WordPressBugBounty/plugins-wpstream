<?php
/**
 * Start streaming edit modal template.
 *
 * Bootstrap modal used to edit a channel: thumbnail / trailer / preview
 * uploaders, name and description, an optional paid-channel price section,
 * taxonomy and gallery controls, plus the edit nonce and save button.
 *
 * @package wpstream-theme
 *
 * @var int    $current_selection Post ID
 * @var string $price
 */

?>
<!-- Edit Channel modal (Bootstrap, hidden until opened by the edit button). -->
<div class="modal fade wpstream-modal" id="wpstream_edit_channel_modal" tabindex="-1" aria-labelledby="wpstream_edit_channel_modal_label" aria-hidden="true">

    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal header: title and close button. -->
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">
					<?php esc_html_e( 'Edit Channel', 'hello-wpstream' ); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal body: all editable channel fields. -->
            <div class="modal-body wpstream-modal-body wpstream-modal-edit-chanel">

                <!-- Placeholder where AJAX save success/error notifications are injected. -->
                <div class="wpstream_channel_change_notification"></div>

				<!-- Single featured-image uploader for this channel. -->
				<?php print wpstream_theme_return_image_upload_markup_single( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<!-- Trailer video uploader. -->
				<?php print wpstream_theme_return_trailer_upload_markup( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<!-- Preview video uploader. -->
				<?php print wpstream_theme_return_preview_upload_markup( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="wpstream-modal-body-row-full">
                    <!-- Channel name field (required). -->
                    <label for="channel_name">
						<?php esc_html_e( 'Name', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>

                    </label>
                    <input type="text" class="form-control" name="channel_name" id="channel_name" value="<?php echo esc_html( get_the_title( $current_selection ) ); ?>"/>

                </div>

                <div class="wpstream-modal-body-row-full">
                    <!-- Channel description field (WordPress editor). -->
                    <label for="channel_description"> <?php esc_html_e( 'Description', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span></label>

					<?php

					// Load the channel's stored description as the editor's initial content.
					$submit_description = get_post_field( 'post_content', $current_selection );

					// Render a bare TinyMCE/quicktags-disabled editor for the description.
					wp_editor(
						stripslashes( $submit_description ),
						'wstream_description',
						array(
							'textarea_rows' => 6,
							'textarea_name' => 'wstream_description',
							'wpautop'       => true,
							'media_buttons' => false,
							'tabindex'      => '',
							'editor_css'    => '',
							'editor_class'  => '',
							'teeny'         => false,
							'dfw'           => false,
							'tinymce'       => false,
							'quicktags'     => false,
						)
					);

					?>

                </div>

				<?php
				// Show the price section only when WooCommerce is active and the user may create paid channels.
				if ( function_exists( 'get_woocommerce_currency_symbol' ) && (  wpstream_return_user_can_create_paid() || current_user_can( 'manage_options' ) )  )  :

					// Post type of the selection; 'product' means the channel is currently paid.
					$options_value = get_post_type( $current_selection );

					?>

                    <!-- Paid-channel price settings group. -->
                    <div class="wpstream-modal-edit-chanel__price-settings">
                        <div class="wpstream-modal-body-row-half wpstream-modal-edit-chanel__price-settings-item">
                            <!-- Paid on/off toggle for the channel. -->
                            <label for="channel_paid">
								<?php esc_html_e( 'Channel is Paid', 'hello-wpstream' ); ?>
                            </label>

                            <label class="wpstream_theme_switch">
                                <!-- Hidden 0 paired with checkbox 1 so an unchecked box still submits paid=0. -->
                                <input type="hidden" class="" value="0" name="channel_paid">
                                <input type="checkbox" class="" value="1" name="channel_paid" <?php checked( $options_value, 'product' ); ?>><span class="wpstream_theme_slider round"></span>
                            </label>
                        </div>

                        <div class="wpstream-modal-body-row-half wpstream-modal-edit-chanel__price-settings-item">
                            <!-- Channel price field (shown with the store currency symbol). -->
                            <label for="channel_price">
								<?php
								echo esc_html__( 'Price', 'hello-wpstream' ) . ' ' . get_woocommerce_currency_symbol(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
                                &nbsp;<span class="required">*</span>

                            </label>

                            <input type="text" class="form-control" name="channel_price" id="channel_price" value="<?php echo esc_attr( $price ); ?>"/>
                        </div>
                    </div>
				<?php

				endif;

				?>


				<?php

				// Output the taxonomy (category/tag) selectors for this channel.
				print wpstream_theme_return_taxonomies_on_edit( $current_user->ID, $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				// Output the multi-image gallery uploader for this channel.
				print wpstream_theme_return_image_upload_markup( $current_user->ID, $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				?>

                <!-- Hidden nonce that authenticates the edit-channel save request. -->
                <input type="hidden" name="wpstream_nonce" id="wpstream_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpstream_edit_channel_nonce' ) ); ?>"/>

            </div>

            <!-- Modal footer: Close and Save Changes buttons. -->
            <div class="modal-footer">
                <button type="button" class="wpstream-button wpstream-button--transparent" data-bs-dismiss="modal">
					<?php esc_html_e( 'Close', 'hello-wpstream' ); ?>
                </button>

                <!-- Save button; carries the edited post ID via data-postID. -->
                <button type="button" id="wpstream_edit_channel_save" data-postID="<?php echo esc_attr( $current_selection ); ?>" class="btn btn-primary wpstream-gradient-button type-2-button-style"><?php esc_html_e( 'Save Changes', 'hello-wpstream' ); ?></button>

            </div>
        </div>
    </div>
</div>