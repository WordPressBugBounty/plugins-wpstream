<?php

class WpStream_Ajax {

	/**
	 * Store plugin main class to allow public access.
	 *
	 * @since    20180622
	 * @var object      The main class.
	 */
	public $main;

	/**
	 * Constructor.
	 *
	 * @param object $plugin_main The main class.
	 */
	public function __construct( $plugin_main ) {
		$this->main = $plugin_main;

		add_action( 'wp_ajax_wpstream_get_videos_list',  [$this,'wpstream_get_videos_list'] );
		
		// Add the dashboard AJAX actions
		add_action( 'wp_ajax_wpstream_dashboard_save_channel_data', [$this, 'wpstream_dashboard_save_channel_data'] );
		add_action( 'wp_ajax_wpstream_dashboard_save_user_address', [$this, 'wpstream_dashboard_save_user_address'] );
		add_action( 'wp_ajax_wpstream_delete_profile_attachment', [$this, 'wpstream_delete_profile_attachment'] );
		add_action( 'wp_ajax_wpstream_dashboard_save_user_data', [$this, 'wpstream_dashboard_save_user_data'] );
		add_action( 'wp_ajax_wpstream_handle_channel_selection', [$this, 'wpstream_handle_channel_selection'] );
		add_action( 'wp_ajax_wpstream_handle_channel_creation', [$this, 'wpstream_handle_channel_creation'] );
		add_action( 'wp_ajax_wpstream_handle_channel_details_saving', [$this, 'wpstream_handle_channel_details_saving'] );
		add_action( 'wp_ajax_wpstream_remove_post_id', [$this, 'wpstream_remove_post_id_callback'] );
		
		// Enqueue dashboard scripts
		add_action( 'wp_enqueue_scripts', [$this, 'wpstream_enqueue_dashboard_scripts'] );
	}

	/**
	 * Enqueue dashboard scripts
	 */
	public function wpstream_enqueue_dashboard_scripts() {
		if ( function_exists('wpstream_is_dashboard_page') && wpstream_is_dashboard_page() ) {

			wp_enqueue_script(
				'wpstream-dashboard-script',
				plugin_dir_url( dirname( __FILE__ ) ) . 'js/dashboard-script.js',
				array( 'jquery' ),
				$this->main->get_version(),
				true
			);
			
			wp_localize_script( 'wpstream-dashboard-script', 'wpstream_dashboard_script_vars', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'currentPassEmpty' => esc_html__( 'Please enter your current password.', 'wpstream' ),
				'passNoMatch' => esc_html__( 'Passwords do not match!', 'wpstream' ),
			));
		}
	}

	/**
	 * Get videos list from WPStream API.
	 */
	public function wpstream_get_videos_list() {
		check_ajax_referer( 'wpstream_onboarding_video_list_nonce', 'security' );

		$token = $this->main->wpstream_live_connection->wpstream_get_token();
		$videos_list = $this->main->wpstream_live_connection->wpstream_get_videos();

		// cleanup any previous echo before sending json
		ob_end_clean();

		if ( $token ) {
			echo json_encode( array(
				'success' => true,
				'videos' => $videos_list,
			));
		} else {
			echo json_encode( array(
				'success' => false,
				'error' => 'Token not found',
			));
		}
		die();
	}
	
	/**
	 * Saves channel data from the dashboard.
	 *
	 * Handles the saving of channel data from the dashboard, including title, description,
	 * thumbnail ID, images, category terms, and whether the channel is paid.
	 */
	public function wpstream_dashboard_save_channel_data() {
		// Verify the nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpstream_edit_channel_nonce' ) ) {
			die( 'Permission denied.' );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
			die();
		}

		$thumb_id     = isset( $_POST['thumb_id'] ) ? intval( $_POST['thumb_id'] ) : 0;
		$title        = sanitize_text_field( $_POST['title'] );
		$description  = sanitize_text_field( $_POST['description'] );
		$channel_paid = intval( $_POST['channel_paid'] );
		$images       = sanitize_text_field( $_POST['images'] );
	        $images       = trim($images,',');
	        $channel_price=0;
		if ( isset( $_POST['channel_price'] ) ) {
			$channel_price = floatval( $_POST['channel_price'] );
		}
		$postID = intval( $_POST['postID'] );

		$new_post_type = 'wpstream_product';
		if ( $channel_paid == 1 ) {
			$new_post_type = 'product';
			update_post_meta( $postID, '_price', $channel_price );
			update_post_meta( $postID, '_regular_price', $channel_price );

		}

		if ( $postID != '0' ) {
			$post_data = array(
				'ID'           => $postID,
				'post_title'   => $title,
				'post_content' => $description,
				'post_type'    => $new_post_type,
			);
			wp_update_post( $post_data );
			set_post_thumbnail( $postID, $thumb_id );

		
			/*
			* Manage images
			*/
			if ( $channel_paid == 1 ) {
				update_post_meta( $postID, '_product_type', 'live_stream' );
				wp_set_post_terms( $postID, 'live_stream', 'product_type' );
				update_post_meta( $postID, '_product_image_gallery', $images );
			} else {
				
				$images_array = explode( ',', $images );
	                        delete_post_meta( $postID, 'wpstream_theme_gallery' );
				foreach ( $images_array as $key => $value ) :
	                            add_post_meta( $postID, 'wpstream_theme_gallery', $value, false );
				endforeach;
			}
			$gallery_images = $this->wpstream_return_image_gallery( $postID );


			/*
			* Manage categories
			*/
			if(isset( $_POST['selected_categories'])):
				$categories = $_POST['selected_categories'];
				foreach ( $categories as $taxonomy => $term_ids ) {

					if ( ! is_array( $term_ids ) ) {
						$term_ids = array( $term_ids );
					}
					$term_ids = array_map( 'intval', $term_ids );

					wp_set_object_terms( $postID, $term_ids, $taxonomy );
				}
			endif;

			$taxonomy_information = $this->wpstream_return_taxoomy_information( $postID );

			$video_trailer = $this->wpstream_theme_return_trailer_video( $postID );

			$video_preview = $this->wpstream_theme_return_preview_video( $postID );

			wp_send_json_success(
				array(
					'succes'     => true,
					's'          => $images,
					'thumburl'   => get_the_post_thumbnail_url( $postID, 'wpstream_featured_unit_cards' ),
					'images'     => $this->wpstream_build_html_gallery_dashboard( $gallery_images ),
					'taxonomies' => $taxonomy_information['html'],
	                                'channel_paid'=>$channel_paid,
	                                'channel_price'=>$channel_price,
					'video_trailer' => $video_trailer,
					'video_preview' => $video_preview,
					'message'    => esc_html__( 'Changes saved successfully.', 'wpstream' ),
				)
			);
			die();
		}
	}
	
	/**
	 * Return the image gallery for a post.
	 *
	 * This function returns the image gallery for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array The array of image gallery for the post.
	 */
	public function wpstream_return_image_gallery( $post_id ) {
		$post_type      = get_post_type( $post_id );
		$gallery_images = array();

		if ( 'product' === $post_type ) {
			$gallery_images_source = get_post_meta( $post_id, '_product_image_gallery', true );
			$gallery_images        = explode( ',', $gallery_images_source );
		} else {
			if(function_exists('rwmb_meta')){
				$gallery_images = rwmb_meta( 'wpstream_theme_gallery', array(), $post_id );

				if ( is_array( $gallery_images ) ) {
					$gallery_images = array_keys( $gallery_images );
				} elseif ( ! empty( $gallery_images ) ) {
					$gallery_images = array( $gallery_images );
				} else {
					$gallery_images = array();
				}
			}
		}

		return array_filter( $gallery_images );
	}
	
	/**
	 * Returns information about taxonomies for the specified post.
	 *
	 * @param int $post_id The post ID.
	 * @return array An array containing information about taxonomies and HTML markup.
	 */
	public function wpstream_return_taxoomy_information( $post_id ) {
		$post_type    = get_post_type( $post_id );
		$taxonomies   = get_object_taxonomies( $post_type );
		$all_terms    = array();
		$return_array = array();

		// Loop through each taxonomy and get terms attached to the post.
		foreach ( $taxonomies as $taxonomy_slug ) {
			if ( 'product_type' !== $taxonomy_slug && 'product_visibility' !== $taxonomy_slug ) {
				$taxonomy_obj  = get_taxonomy( $taxonomy_slug );
				$taxonomy_name = $taxonomy_obj->labels->name; // This fetches the name of the taxonomy.
				$terms         = wp_get_post_terms( $post_id, $taxonomy_slug, array( 'fields' => 'all' ) ); // fetch all fields of the term.

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$all_terms[ $taxonomy_name ] = $terms;
				}
			}
		}

		$return_html = '';

		foreach ( $all_terms as $taxonomy => $terms ) {
			$return_html .= ' <div  class="wpstream-dashboard-details" >';
			$return_html .= '<div class="wpstream-dashboard-details-header">' . $taxonomy . '</div>';
			$return_html .= '<div class="wpstream_account_details_value" id="wpstream_' . sanitize_key( $taxonomy ) . '">';

			foreach ( $terms as $term ) {
				$return_html .= '<span class="wpstream_term_selected">' . $term->name . '</span>';
			}

			$return_html .= ' </div>
 
	        </div>  ';
		}

		$return_array['tax_information'] = $all_terms;
		$return_array['html']            = $return_html;

		return $return_array;
	}

	/**
	 * Return the trailer video for a post.
	 *
	 * This function returns the trailer video for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The URL of the trailer video.
	 */
	function wpstream_theme_return_trailer_video( $post_id ) {
		$trailer_video_id = get_post_meta( $post_id, 'video_trailer', true );
		$attachment_url   = wp_get_attachment_url( $trailer_video_id );

		if ( ! empty( $attachment_url ) ) {;
			return $attachment_url;
		}

		return '';
	}

	/**
	 * Build HTML for the video trailer in the dashboard.
	 *
	 * This function builds HTML for the video trailer in the dashboard based on the provided video URL.
	 *
	 * @param string $video_url The URL of the video.
	 * @return string The HTML string for the video trailer.
	 */
	function wpstream_theme_build_html_video_trailer_dashboard( $video_url ) {
		if ( ! empty( $video_url ) ) {
			return '<div class="wpstream-video-trailer" id="wpstream-video-trailer"><video height="240" controls><source src="' . esc_url( $video_url ) . '" type="video/mp4"></video></div>';
		}

		return '';
	}

	/**
	 * Return the trailer video for a post.
	 *
	 * This function returns the trailer video for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The URL of the trailer video.
	 */
	function wpstream_theme_return_preview_video( $post_id ) {
		$preview_video_id = get_post_meta( $post_id, 'video_preview', true );
		$attachment_url   = wp_get_attachment_url( $preview_video_id );

		if ( ! empty( $attachment_url ) ) {;
			return $attachment_url;
		}

		return '';
	}

	/**
	 * Build HTML for the video preview in the dashboard.
	 *
	 * This function builds HTML for the video preview in the dashboard based on the provided video URL.
	 *
	 * @param string $video_url The URL of the video.
	 * @return string The HTML string for the video preview.
	 */
	function wpstream_theme_build_html_video_preview_dashboard( $video_url ) {
		if ( ! empty( $video_url ) ) {
			return '<div class="wpstream-video-preview" id="wpstream-video-preview"><video height="240" controls><source src="' . esc_url( $video_url ) . '" type="video/mp4"></video></div>';
		}

		return '';
	}

	/**
	 * Build HTML for the gallery in the dashboard.
	 *
	 * This function builds HTML for the gallery in the dashboard based on the provided array of image IDs.
	 *
	 * @param array $gallery_images An array of image IDs.
	 * @return string The HTML string for the gallery.
	 */
	public function wpstream_build_html_gallery_dashboard( $gallery_images ) {
		$return_string = '';

		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {
				$preview = wp_get_attachment_image_src( $attachment_id, 'wpstream_featured_unit_cards' );

				if ( $preview && '' !== $preview[0] ) {
					$return_string .= '<div class="wpstream_uploaded_images" data-imageid="' . esc_attr( $attachment_id ) . '">';
					$return_string .= '<img src="' . esc_url( $preview[0] ) . '" alt="' . esc_html__( 'thumb', 'wpstream' ) . '" /></div>';
				}
			}
		}

		return $return_string;
	}
	
	/**
	 * Save user address data from dashboard.
	 *
	 * This function handles saving user address data from the dashboard.
	 */
	public function wpstream_dashboard_save_user_address() {
		// Verify the nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpstream_edit_addr_nonce' ) ) {
			die( 'Permission denied.' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
		}

	    $userID = get_current_user_id();
	    foreach ($_POST['inputData'] as $item){
	        if(isset( $item['id'])){
	            update_user_meta($userID, sanitize_text_field( $item['id']) , sanitize_text_field( $item['value']) );
	        }
	    }
	    

		wp_send_json_success(
			array(
				'succes'  => true,
				'message' => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);

		die();
	}
	
	/**
	 * Delete profile attachment
	 */
	public function wpstream_delete_profile_attachment() {
		// Verify the nonce for security.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'wpstream_profile_image_upload' ) ) {
			wp_send_json_error(
				array(
					'succes'  => false,
					'message' => esc_html__( 'Permission denied nonce', 'wpstream' ),
				)
			);

			die();
		}

		if ( isset( $_POST['image_id'] ) ) {
			$image_id = intval( $_POST['image_id'] );
		}
		$user_id = get_current_user_id();

		// Get the attachment author.
		$attachment = get_post( $image_id );

		if ( empty( $attachment ) || intval( $attachment->post_author ) !== intval( $user_id ) ) {
			wp_send_json_error(
				array(

					'succes'  => false,

					'author'  => $attachment->post_author,

					'userid'  => $user_id,

					'message' => esc_html__( 'Permission denied!!!', 'wpstream' ),

				)
			);

			die();

		}

		// Delete the attachment (you can customize this part).
		wp_delete_attachment( $image_id, true );
		delete_user_meta( $user_id, 'custom_picture' );
		delete_user_meta( $user_id, 'custom_picture_small' );

		wp_send_json_success(
			array(
				'succes'  => true,
				'default' => function_exists('wpstream_get_author_profile_image_url_by_author_id') ? wpstream_get_author_profile_image_url_by_author_id($user_id) : '',
				'message' => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);

		die();
	}
	
	/**
	 * Dashboard save user data
	 */
	public function wpstream_dashboard_save_user_data() {
		// Verify the nonce for security.

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpstream_edit_account_nonce' ) ) {
			die( 'Permission denied.' );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
		}

		// Get the user's ID.
		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();

		// Get the data from the AJAX request.
		if ( isset( $_POST['firstName'] ) ) {
			$first_name = sanitize_text_field( wp_unslash( $_POST['firstName'] ) );
		}
		if ( isset( $_POST['lastName'] ) ) {
			$last_name = sanitize_text_field( wp_unslash( $_POST['lastName'] ) );
		}
		if ( isset( $_POST['displayName'] ) ) {
			$display_name = sanitize_text_field( wp_unslash( $_POST['displayName'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
	    if (isset( $_POST['aboutMe'])){
	        $description = sanitize_textarea_field( wp_unslash( $_POST['aboutMe'] ) );
	    }
		if ( isset( $_POST['newPassword1'] ) ) {
			$new_password1 = sanitize_text_field( wp_unslash( $_POST['newPassword1'] ) );
		}
		if ( isset( $_POST['newPassword2'] ) ) {
			$new_password2 = sanitize_text_field( wp_unslash( $_POST['newPassword2'] ) );
		}
		if ( isset( $_POST['currentPassword'] ) ) {
			$current_password = sanitize_text_field( wp_unslash( $_POST['currentPassword'] ) );
		}

		$passwordchanged = false;

		// Only update fields that are not empty.
		$user_data = array();

		if ( ! empty( $first_name ) ) {
			$user_data['first_name'] = $first_name;
		}

		if ( ! empty( $last_name ) ) {
			$user_data['last_name'] = $last_name;
		}

	    if ( !empty( $description ) ){
		    $user_data['description'] = $description;
	    }

		if ( ! empty( $display_name ) ) {
			$user_data['display_name'] = $display_name;
		}

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user && $existing_user->ID !== $user_id ) {
			wp_send_json_error(
				array(
					'succes'      => false,
					'failaccount' => esc_html__( 'Email already exists.', 'wpstream' ),
				)
			);
		}

		if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			wp_send_json_error(
				array(
					'succes'      => false,
					'failaccount' => esc_html__( 'Invalid Email Format', 'wpstream' ),
				)
			);
		}

		if ( ! empty( $email ) ) {
			$user_data['user_email'] = $email;
		}

		// Update the user's data.
		if ( ! empty( $user_data ) ) {
			$user_data['ID'] = $user_id;
			wp_update_user( $user_data );
		}

		if ( ! empty( $new_password1 ) && ! empty( $new_password2 ) ) {
			if ( $new_password1 !== $new_password2 ) {
				wp_send_json_error(
					array(
						'succes'   => false,
						'failpass' => esc_html__( 'Passwords do not match!', 'wpstream' ),
					)
				);

				die();

			} elseif ( ! wp_check_password( $current_password, $current_user->data->user_pass, $current_user->ID ) ) {
				wp_send_json_error(
					array(
						'succes'   => false,
						'failpass' => esc_html__( 'Current Password is not right!', 'wpstream' ),
					)
				);

				die();

			} else {
				wp_set_password( $new_password1, $user_id );
				$passwordchanged = true;
			}
		}

		// Send a response to the client.
		wp_send_json_success(
			array(
				'succes'          => true,
				'passwordchanged' => $passwordchanged,
				'message'         => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);
	}

	/**
	 * Handle the selection of a channel.
	 *
	 * This function handles the AJAX request for selecting a channel.
	 * It checks if the user is logged in, validates the security nonce,
	 * and updates the user meta with the selected channel if the user is the owner of the channel.
	 * It returns a JSON response indicating success or failure.
	 *
	 * @return void
	 */
	public function wpstream_handle_channel_selection() {
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		check_ajax_referer( 'wpstream_user_channel_list', 'security' );
		if ( isset( $_POST['selected_value'] ) ) {
			$selected_value = intval( $_POST['selected_value'] );
		}
		$current_user   = wp_get_current_user();
		$post_author_id = intval( get_post_field( 'post_author', $selected_value ) );

		if ( $current_user->ID !== $post_author_id ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'You are not the owner of this channel', 'wpstream' ),
				)
			);

		} else {
			update_user_meta( $current_user->ID, 'wpstream_start_streaming_channel', $selected_value );

			echo wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__( 'Channel updated', 'wpstream' ),
				)
			);
		}

		wp_die();
	}
	
	/**
	 * Handle channel creation.
	 */
	public function wpstream_handle_channel_creation() {
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		check_ajax_referer( 'wpstream_user_channel_list', 'security' );
		if ( isset( $_POST['channel_type'] ) ) {
			$channel_type = sanitize_text_field( wp_unslash( $_POST['channel_type'] ) );
		}
		$current_user = wp_get_current_user();
		
		// These functions should be implemented in the plugin or be accessible
		$maxim_channels_per_user = function_exists('wpstream_return_max_channels_per_user') ? wpstream_return_max_channels_per_user() : 100;
		$allow_user_paid_channels = function_exists('wpstream_return_user_can_create_paid') ? wpstream_return_user_can_create_paid() : false;
		$how_many_posts = function_exists('wpstream_theme_return_user_channel_list') ? wpstream_theme_return_user_channel_list( '', 'found_posts' ) : 0;

		if ( ( $how_many_posts < $maxim_channels_per_user ) || current_user_can( 'manage_options' ) ) {
			$post_type = 'wpstream_product';
			$title     = 'My New Free Channel';

			if ( ( $allow_user_paid_channels || current_user_can( 'manage_options' ) ) && 'paid' === $channel_type ) {
				$post_type = 'product';
				$title     = 'My New Paid Channel';
			}

			$post_data = array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => $current_user->ID,
				'post_type'   => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( ! is_wp_error( $post_id ) ) {
				update_user_meta( $current_user->ID, 'wpstream_start_streaming_channel', $post_id );

				echo wp_json_encode(
					array(
						'success' => true,
						'message' => 'Post created with ID: ' . $post_id,
					)
				);
			} else {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => 'Error creating post: ' . $post_id->get_error_message(),
					)
				);
			}
		} else {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Your reached the maximum number of channels', 'wpstream' ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Handle AJAX request to save channel details.
	 *
	 * This function handles the AJAX request to save the details of a channel, including its title, description,
	 * price, images, featured status, and taxonomies.
	 *
	 * @return void Outputs JSON-encoded response indicating success or failure of the operation.
	 */
	public function wpstream_handle_channel_details_saving() {
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		check_ajax_referer( 'wpstream_user_channel_list', 'security' );

		if ( isset( $_POST['postID'] ) ) {
			$post_id = intval( $_POST['postID'] );
		}
		$current_user   = wp_get_current_user();
		$post_author_id = intval( get_post_field( 'post_author', $post_id ) );

		if ( $current_user->ID !== $post_author_id ) {
			echo wp_json_encode(
				array(
					'success'         => false,
					'$postID'         => $post_id,
					'$post_author_id' => $post_author_id,
					'message'         => esc_html__( 'You are not the owner of this channel', 'wpstream' ),
				)
			);
		} else {
			if ( isset( $_POST['title'] ) ) {
				$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
			}
			if ( isset( $_POST['description'] ) ) {
				$sanitized_content = wp_kses_post( wp_unslash( $_POST['description'] ) );
			}
			$price = 0;

			if ( isset( $_POST['price'] ) ) {
				$price = sanitize_text_field( wp_unslash( $_POST['price'] ) );
			}

			if ( isset( $_POST['images'] ) ) {
				$images = sanitize_text_field( wp_unslash( $_POST['images'] ) );
			}

			if ( isset( $_POST['featured'] ) ) {
				$featured = intval( $_POST['featured'] );
			}

			if ( isset( $_POST['taxonomies'] ) ) {
				$taxonomies_raw = sanitize_text_field( wp_unslash( $_POST['taxonomies'] ) );
				$taxonomies     = is_array( $taxonomies_raw ) ? array_map( 'sanitize_text_field', $taxonomies_raw ) : array();
			}
			$images = rtrim( ltrim( trim( $images ), ',' ), ',' );

			if ( get_post_type( $post_id ) === 'product' ) {
				update_post_meta( $post_id, '_product_image_gallery', $images );
				update_post_meta( $post_id, '_regular_price', $price );
			} else {
				$images_array = explode( ',', $images );
				foreach ( $images_array as $key => $value ) :
					add_post_meta( $post_id, 'wpstream_theme_gallery', $value );
				endforeach;
			}

			set_post_thumbnail( $post_id, $featured );

			$post_data = array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $sanitized_content,
			);

			// Update the post.
			wp_update_post( $post_data );

			foreach ( $taxonomies as $taxonomy => $term_ids ) {
				wp_remove_object_terms( $post_id, '', $taxonomy );

				if ( is_array( $term_ids ) ) {
					foreach ( $term_ids as $key => $term_id ) {
						if ( 'product_tag' === $taxonomy || 'post_tag' === $taxonomy ) {
							$tagterm = get_term( $term_id );

							if ( $tagterm && ! is_wp_error( $tagterm ) ) {
								$tag_term_name = $tagterm->name;
								wp_set_post_terms( $post_id, $tag_term_name, $taxonomy, true );
							}
						} elseif ( -1 !== $term_id ) {
							wp_set_post_terms( $post_id, intval( $term_id ), $taxonomy, true );
						}
					}
				}
			}

			echo wp_json_encode(
				array(
					'success'            => true,
					'$price'             => $price,
					'message'            => esc_html__( 'Channel updated', 'wpstream' ),
					'title'              => $title,
					'$sanitized_content' => $sanitized_content,
					'$images'            => $images,
					'$featured'          => $featured,
					'$taxonomies'        => $taxonomies,
				)
			);
		}

		wp_die();
	}
	
	/**
	 * Callback handler to remove a post ID from the "watch later" list.
	 */
	public function wpstream_remove_post_id_callback() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to perform this action.' );
			die();
		}

		// Verify the nonce.
		if ( isset( $_POST['wpstream_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpstream_nonce'] ) ), 'wpstream-watch-later-nonce' ) ) {
			if ( isset( $_POST['postID'] ) ) {
				$post_id_to_remove = intval( $_POST['postID'] );
				$meta_key          = 'wpstream_user_watch_later_items';
				$current_user      = wp_get_current_user();
				$user_id           = $current_user->ID;

				// Get the current array of post IDs.
				$watch_later_item_ids = get_user_meta( $user_id, $meta_key, true );

				// Remove the specific ID from the array.
				$watch_later_item_ids = array_filter(
					$watch_later_item_ids,
					function ( $id ) use ( $post_id_to_remove ) {
						return $id !== $post_id_to_remove;
					}
				);

				// Update the user's metadata with the modified array.
				update_user_meta( $user_id, $meta_key, $watch_later_item_ids );

				$response = array(
					'success' => true,
					'message' => 'Item removed',
				);

			} else {
				$response = array(
					'success' => false,
					'message' => 'Invalid postID format.',
				);
			}
		} else {
			$response = array(
				'success' => false,
				'message' => 'Nonce verification failed.',
			);
		}

		wp_send_json( $response );

		wp_die();
	}
}