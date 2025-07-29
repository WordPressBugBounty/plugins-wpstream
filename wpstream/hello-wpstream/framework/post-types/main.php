<?php
/**
 * Main
 *
 * @package wpstream-theme
 */



/**
 * Callback function for rendering custom metabox content for bundles.
 *
 * This function generates the HTML content for the custom metabox used for bundling
 * free to view live channels. It outputs two sortable lists for selecting and organizing
 * items in the bundle.
 *
 * @param WP_Post $post The post object.
 */
function wpstream_bundle_custom_metabox_callback( $post ) {
	$post_selection = 'free';

	if ( get_post_type( $post->ID ) === 'product' ) {
		$post_selection = 'paid';
	}
	$custom_field_values       = get_post_meta( $post->ID, 'wpstream_bundle_selection', true );
	$custom_field_values_array = explode( ',', $custom_field_values );
	$options                   = wpstream_get_all_items_list( 100, $post_selection );

	foreach ( $custom_field_values_array as $key => $value ) {
		unset( $options[ $value ] );
	}

	$options_selected = wpstream_get_all_items_list( count( $custom_field_values_array ), $post_selection, $custom_field_values_array );
	// Output the metabox fields.
	?>



	<label class="wpstream_full_label" for="custom_field"><?php echo esc_html__( 'Free to View Live Channels - click or drag them to the bundle list', 'hello-wpstream' ); ?></label>
	<div class="wpstream_selection_wrapper">
		<div class="wpstream_selection_col_1">
			<span id="wpstream_autocomplete_status"><?php esc_html_e( 'Type so search an item', 'hello-wpstream' ); ?></span>
			<input type="text"  class="wpstream_item_autocomplete_search" placeholder="<?php echo esc_attr__( 'search here', 'hello-wpstream' ); ?>"/>
			<ul id="sortable1" class="connectedSortable wpstream_sortable_list wpstream_sortable_list_initial">
				<?php
					print wpstream_show_sortable_list_content( $options ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>
		</div>


		<div class="wpstream_selection_col_1">        
			<ul id="sortable2"  class="connectedSortable wpstream_sortable_list wpstream_sortable_list_final" >
				<?php
				print wpstream_show_sortable_list_content( $options_selected ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>
		</div>

		<input type="hidden" name="wpstream_bundle_selection" id="wpstream_bundle_selection" value="<?php echo esc_attr( $custom_field_values ); ?>">
	</div>
	<?php
	wp_nonce_field( 'wpstream_custom_metabox_nonce', 'wpstream_custom_metabox_nonce' );
}

/**
 * Generates HTML content for a sortable list based on the provided options.
 *
 * @param array $options An array containing the options for the sortable list.
 *                       Each element in the array should have a 'title' and 'type' key.
 *                       The 'type' key determines the type of product.
 * @return string HTML content for the sortable list.
 */
function wpstream_show_sortable_list_content( $options ) {
	$return_string = '';
	foreach ( $options as $value => $item ) {
		$return_string .= '<li class="ui-state-default" data-postID="' . intval( $value ) . '">' . esc_html( $item['title'] );
		$return_string .= '<div class="wpstream_product_list_type_wrapper">';

		$meta_free = esc_html__( 'free', 'hello-wpstream' );
		$meta_type = esc_html__( 'event', 'hello-wpstream' );

		if ( 'wpstream_product_vod' === $item['type'] ) {
			$meta_type = esc_html__( 'vod', 'hello-wpstream' );
		} elseif ( 'wpstream_product' === $item['type'] ) {
			$meta_type = esc_html__( 'event', 'hello-wpstream' );
		} elseif ( 'product' === $item['type'] ) {
			$term_list = wp_get_post_terms( $value, 'product_type' );

			$meta_free = esc_html__( 'paid', 'hello-wpstream' );

			if ( isset( $term_list[0]->name ) && 'live_stream' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'event', 'hello-wpstream' );
			} elseif ( isset( $term_list[0]->name ) && 'video_on_demand' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'vod', 'hello-wpstream' );
			}
		}

		$return_string .= '<span class="wpstream_product_list_free">' . $meta_free . '</span>';
		$return_string .= '<span class="wpstream_product_list_type">' . $meta_type . '</span>';
		$return_string .= '</div>';
		$return_string .= '</li>';
	}

	return $return_string;
}

add_action( 'save_post', 'wpstream_custom_metabox_save' );

/**
 * Save custom metabox data when a post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function wpstream_custom_metabox_save( $post_id ) {
	// Check if the nonce is set.
	if ( ! isset( $_POST['wpstream_custom_metabox_nonce'] ) ) {
		return;
	}

	// Verify the nonce.
	if ( ! isset( $_POST['wpstream_custom_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpstream_custom_metabox_nonce'] ) ), 'wpstream_custom_metabox_nonce' ) ) {
		return;
	}

	// Check if the current user has permission to save the post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save the custom field data.
	if ( isset( $_POST['wpstream_bundle_selection'] ) ) {
		$custom_field_value = sanitize_text_field( wp_unslash( $_POST['wpstream_bundle_selection'] ) );

		// do a inintial reset.
		$before_saving_items       = get_post_meta( $post_id, 'wpstream_bundle_selection', true );
		$before_saving_items_array = explode( ',', $before_saving_items );
		foreach ( $before_saving_items_array as $item_id ) {
			update_post_meta( $item_id, 'wpstream_part_of_bundle', '' );
		}

		// save the new id's.
		$custom_field_value_array = explode( ',', $custom_field_value );
		foreach ( $custom_field_value_array as $item_id ) {
			update_post_meta( $item_id, 'wpstream_part_of_bundle', $post_id );
		}
		update_post_meta( $post_id, 'wpstream_bundle_selection', $custom_field_value );
	}
}

/**
 * Metabox enqueue scripts
 */
function custom_metabox_enqueue_scripts() {
	wp_enqueue_style( 'wpstream_custom_metabox_style', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/css/wpstream_custom_metabox_style.css?v=' . wp_rand(), '', '1.0' );

	wp_enqueue_script( 'wpstream_custom_metabox_script', WPSTREAM_PLUGIN_DIR_URL . 'hello-wpstream/js/wpstream_custom_metabox_script.js?v=' . wp_rand(), array( 'jquery' ), '1.0', true );
	wp_localize_script(
		'wpstream_custom_metabox_script',
		'wpstream_custom_metabox_script_vars',
		array(
			'ajaxurl'        => esc_url( admin_url( 'admin-ajax.php' ) ),
			'searching_text' => esc_html__( 'Searching...', 'hello-wpstream' ),
			'please_select'  => esc_html__( 'Please select an item', 'hello-wpstream' ),
			'error_text'     => esc_html__( 'Something is not working', 'hello-wpstream' ),
			'no_items'       => esc_html__( 'No items found', 'hello-wpstream' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'custom_metabox_enqueue_scripts' );

function enqueue_custom_scripts() {
	wp_enqueue_script( 'jquery-ui-slider' );
	wp_enqueue_script('wpstream_gradient_picker', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/js/wpstream_gradient_picker_script.js', array('jquery', 'wp-color-picker', 'jquery-ui-slider'), false, true);
}
add_action('admin_enqueue_scripts', 'enqueue_custom_scripts');

add_action( 'wp_ajax_wpstream_product_autocomplete', 'wpstream_product_autocomplete_callback' );

/**
 * Product autocomplete callback
 */
function wpstream_product_autocomplete_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Access denied! Only administrators' ); // Вернуть сообщение об ошибке или выполнить перенаправление, если необходимо.
	}

	if ( isset( $_POST['term'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term = sanitize_text_field( wp_unslash( $_POST['term'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	// Query the wpstream_product custom post type.
	$post_type = array( 'wpstream_product_vod', 'wpstream_product', 'product' );
	$args      = array(
		's'              => $term,
		'post_type'      => $post_type,
		'posts_per_page' => 50,
		'tax_query'      => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'relation' => 'OR',
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( 'live_stream', 'video_on_demand' ),
			),
			array(
				'taxonomy' => 'product_type',
				'operator' => 'NOT EXISTS',
			),
		),
	);

	$posts = get_posts( $args );

	$results = array();

	foreach ( $posts as $post ) {
		$title = $post->post_title;
		$type  = 'wpstream_product'; // Set the default post type
		// Retrieve the custom meta information.
		$wpstream_post_type = $post->post_type;

		if ( 'wpstream_product_vod' === $wpstream_post_type ) {
			$meta_free = esc_html__( 'free', 'hello-wpstream' );
			$meta_type = esc_html__( 'vod', 'hello-wpstream' );
		} elseif ( 'wpstream_product' === $type ) {
			$meta_free = esc_html__( 'free', 'hello-wpstream' );
			$meta_type = esc_html__( 'event', 'hello-wpstream' );
		} elseif ( 'product' === $type ) {
			$term_list = wp_get_post_terms( $post->ID, 'product_type' );

			$meta_free = esc_html__( 'paid', 'hello-wpstream' );

			if ( 'live_stream' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'event', 'hello-wpstream' );
			} elseif ( 'video_on_demand' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'vod', 'hello-wpstream' );
			}
		}

		$results[] = array(
			'value'     => $post->ID,
			'label'     => $title,
			'type'      => $wpstream_post_type,
			'meta_free' => $meta_free,
			'meta_type' => $meta_type,
		);
	}

	wp_send_json( $results );

	wp_die();
}
