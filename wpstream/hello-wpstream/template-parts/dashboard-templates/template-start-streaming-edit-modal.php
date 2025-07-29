<?php
/**
 * Start streaming edit modal template
 *
 * @package wpstream-theme
 *
 * @var int    $current_selection Post ID
 * @var string $price
 */

?>
<div class="modal fade wpstream-modal" id="wpstream_edit_channel_modal" tabindex="-1" aria-labelledby="wpstream_edit_channel_modal_label" aria-hidden="true">

    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">
					<?php esc_html_e( 'Edit Channel', 'hello-wpstream' ); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body wpstream-modal-body wpstream-modal-edit-chanel">

                <div class="wpstream_channel_change_notification"></div>

				<?php print wpstream_theme_return_image_upload_markup_single( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php print wpstream_theme_return_trailer_upload_markup( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php print wpstream_theme_return_preview_upload_markup( $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="wpstream-modal-body-row-full">
                    <label for="channel_name">
						<?php esc_html_e( 'Name', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>

                    </label>
                    <input type="text" class="form-control" name="channel_name" id="channel_name" value="<?php echo esc_html( get_the_title( $current_selection ) ); ?>"/>

                </div>

                <div class="wpstream-modal-body-row-full">
                    <label for="channel_description"> <?php esc_html_e( 'Description', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span></label>

					<?php

					$submit_description = get_post_field( 'post_content', $current_selection );

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
				if ( function_exists( 'get_woocommerce_currency_symbol' ) && (  wpstream_return_user_can_create_paid() || current_user_can( 'manage_options' ) )  )  :

					$options_value = get_post_type( $current_selection );

					?>

                    <div class="wpstream-modal-edit-chanel__price-settings">
                        <div class="wpstream-modal-body-row-half wpstream-modal-edit-chanel__price-settings-item">
                            <label for="channel_paid">
								<?php esc_html_e( 'Channel is Paid', 'hello-wpstream' ); ?>
                            </label>

                            <label class="wpstream_theme_switch">
                                <input type="hidden" class="" value="0" name="channel_paid">
                                <input type="checkbox" class="" value="1" name="channel_paid" <?php checked( $options_value, 'product' ); ?>><span class="wpstream_theme_slider round"></span>
                            </label>
                        </div>

                        <div class="wpstream-modal-body-row-half wpstream-modal-edit-chanel__price-settings-item">
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

				print wpstream_theme_return_taxonomies_on_edit( $current_user->ID, $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				print wpstream_theme_return_image_upload_markup( $current_user->ID, $current_selection );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				?>

                <input type="hidden" name="wpstream_nonce" id="wpstream_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpstream_edit_channel_nonce' ) ); ?>"/>

            </div>

            <div class="modal-footer">
                <button type="button" class="wpstream-button wpstream-button--transparent" data-bs-dismiss="modal">
					<?php esc_html_e( 'Close', 'hello-wpstream' ); ?>
                </button>

                <button type="button" id="wpstream_edit_channel_save" data-postID="<?php echo esc_attr( $current_selection ); ?>" class="btn btn-primary wpstream-gradient-button type-2-button-style"><?php esc_html_e( 'Save Changes', 'hello-wpstream' ); ?></button>

            </div>
        </div>
    </div>
</div>