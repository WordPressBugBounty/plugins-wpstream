<?php
/**
 * General Template Edit Account
 *
 * @var WP_User $user
 * @package wpstream_theme
 */

?>

<div class="wpstream-dashboard-account">
	<div class="modal fade wpstream-modal" id="wpstream_edit_account_modal" tabindex="-1" aria-labelledby="wpstream_edit_account_modal_label" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">
						<?php esc_html_e( 'Edit Account', 'hello-wpstream' ); ?>
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body wpstream-modal-body">

					<div class="wpstream_account_change_notification"></div>

					<div class="wpstream-modal-body-row-half">
						<label for="account_first_name">
							<?php esc_html_e( 'First name', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>
						</label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text form-control"
								name="account_first_name" id="account_first_name" autocomplete="given-name"
								value="<?php echo esc_attr( $user->first_name ?? '' ); ?>"/>
					</div>

					<div class="wpstream-modal-body-row-half">
						<label for="account_last_name">
							<?php esc_html_e( 'Last name', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>
						</label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text form-control"
								name="account_last_name" id="account_last_name" autocomplete="family-name"
								value="<?php echo esc_attr( $user->last_name ?? '' ); ?>"/>
					</div>

					<div class="wpstream-modal-body-row-half">
						<label for="account_display_name">
							<?php esc_html_e( 'Display name', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>
						</label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text form-control"
								name="account_display_name" id="account_display_name"
								value="<?php echo esc_attr( $user->display_name ?? '' ); ?>"/>
					</div>

					<div class="wpstream-modal-body-row-half">
						<label for="account_email">
							<?php esc_html_e( 'Email address', 'hello-wpstream' ); ?>&nbsp;<span class="required">*</span>
						</label>
						<input type="email" class="woocommerce-Input woocommerce-Input--email input-text form-control"
								name="account_email" id="account_email" autocomplete="email"
								value="<?php echo esc_attr( $user->user_email ); ?>"/>
					</div>

                    <div class="wpstream-modal-body-row-half">
                        <label for="account_about_me">
							<?php esc_html_e( 'About me', 'hello-wpstream' ); ?>
                        </label>
                        <textarea class="woocommerce-Input woocommerce-Input--email input-text form-control" name="account_about_me" id="account_about_me" rows="5"><?php echo esc_textarea($user->description); ?></textarea>
                    </div>

					<h5><?php esc_html_e( 'Password change (*You will be logged out on successful password change)', 'hello-wpstream' ); ?></h5>

					<div class="wpstream_passoword_change_notification"></div>

					<div class="wpstream-modal-body-row-half">
						<label for="password_current">
							<?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'hello-wpstream' ); ?>
						</label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text form-control"
								name="password_current" id="password_current" autocomplete="off"/>
					</div>

					<div class="wpstream-modal-body-row-half">
						<label for="password_1">
							<?php esc_html_e( 'New password (leave blank to leave unchanged)', 'hello-wpstream' ); ?>
						</label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text form-control"
								name="password_1" id="password_1" autocomplete="off"/>
					</div>

					<div class="wpstream-modal-body-row-half">
						<label for="password_2">
							<?php esc_html_e( 'Confirm new password', 'hello-wpstream' ); ?>
						</label>
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text form-control"
								name="password_2" id="password_2" autocomplete="off"/>
					</div>

					<input type="hidden" name="wpstream_nonce" value="<?php echo wp_create_nonce( 'wpstream_edit_account_nonce' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"/>

				</div>
				<div class="modal-footer">
					<button type="button" class="wpstream_theme_button_dashboard" data-bs-dismiss="modal">
						<?php esc_html_e( 'Close', 'hello-wpstream' ); ?>
					</button>
					<button type="button" id="wpstream_edit_account_save" class="wpstream_theme_button_dashboard">
						<?php esc_html_e( 'Save Changes', 'hello-wpstream' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<div class="pstream-dashboard-account__header">
		<h1 class="wpstream-dashboard-address__title"><?php echo esc_html__( 'Edit Account', 'hello-wpstream' ); ?></h1>

		<?php if( class_exists( 'Wpstream_Player' ) ) {
			require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-upload-picture.php';
		} ?>
	</div>

	<div class="wpstream-dashboard-account-details">
		<div class="wpstream-dashboard-account-details__header">
			<h2 class="wpstream-dashboard-account-details__title"><?php echo esc_html__( 'Account Details', 'hello-wpstream' ); ?></h2>

			<button type="button" class="wpstream_theme_button_dashboard" data-bs-toggle="modal" data-bs-target="#wpstream_edit_account_modal">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M15.1369 3.46967C14.9963 3.32902 14.8055 3.25 14.6066 3.25C14.4077 3.25 14.2169 3.32902 14.0763 3.46967L4.88388 12.6621C4.78965 12.7563 4.72223 12.8739 4.68856 13.0028L3.68856 16.8313C3.62127 17.0889 3.69561 17.3629 3.88388 17.5511C4.07215 17.7394 4.34614 17.8138 4.60375 17.7465L8.43218 16.7465C8.56111 16.7128 8.67874 16.6454 8.77297 16.5511L17.9654 7.35876C18.2582 7.06586 18.2582 6.59099 17.9654 6.2981L15.1369 3.46967ZM6.08843 13.5788L14.6066 5.06066L16.3744 6.82843L7.8562 15.3466L5.46344 15.9716L6.08843 13.5788Z" fill="#0F0F0F"/>
					<path d="M4 19.25C3.58579 19.25 3.25 19.5858 3.25 20C3.25 20.4142 3.58579 20.75 4 20.75H19C19.4142 20.75 19.75 20.4142 19.75 20C19.75 19.5858 19.4142 19.25 19 19.25H4Z" fill="#0F0F0F"/>
				</svg>

				<?php esc_html_e( 'Edit account', 'hello-wpstream' ); ?>
			</button>
		</div>

		<div class="wpstream-dashboard-account-details__detail-items">
			<?php
			$user_data = array(
				esc_html__('First name','hello-wpstream')    => array(
																	'value'	=>	$user->first_name,
																	'id'	=>	'wpstream_first_name_value'
																),

				esc_html__('Last name', 'hello-wpstream')     => array(
																	'value'	=> 	$user->last_name,
																	'id'	=>	'wpstream_last_name_value'
																),
				esc_html__('Display Name', 'hello-wpstream')   => array(
																	'value' => 	$user->display_name,
																	'id'	=>	'wpstream_display_name_value'
																),
		 		esc_html__('Email Address', 'hello-wpstream') => array(
																	'value'=> $user->user_email,
																	'id'=>	'wpstream_email_value'
																),
				esc_html__('About me', 'hello-wpstream') => array(
					'value'=> $user->description,
					'id'=>	'wpstream_about_me_value'
				),
															);
			?>

			<?php foreach ( $user_data as $label => $item ) : ?>
				
				<div class="wpstream-dashboard-account-details__details-item">
					<span class="item-label"><?php echo esc_html( $label ); ?>:</span>
					<span class="item-value" id="<?php echo esc_attr($item['id']);?>" ><?php echo esc_html( $item['value'] ); ?></span>
				</div>

			<?php endforeach; ?>
		</div>
	</div>

	<div class="wpstream-dashboard-account__password">
		<span class="item-label"><?php echo esc_html__( 'Password', 'hello-wpstream' ); ?>:</span>
		<span class="item-value"><?php echo str_repeat( '*', strlen( $user->user_pass ) ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	</div>
</div>