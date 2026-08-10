<?php
/**
 * Main
 *
 * Bundle-related admin plumbing for the theme's custom post types. Provides the
 * metabox UI that lets an editor drag free/paid stream items into a "bundle",
 * the save handler that persists the selection (and back-references each child
 * item to its parent bundle), the admin script/style enqueues, and the AJAX
 * autocomplete endpoint that powers the item search box in the metabox.
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
 * @return void Outputs HTML directly.
 */
function wpstream_bundle_custom_metabox_callback( $post ) {
	// Default the candidate pool to free items.
	$post_selection = 'free';

	// A WooCommerce "product" bundle pulls from paid items instead.
	if ( get_post_type( $post->ID ) === 'product' ) {
		$post_selection = 'paid';
	}
	// Read the currently saved bundle selection (a comma-separated list of post IDs)...
	$custom_field_values       = get_post_meta( $post->ID, 'wpstream_bundle_selection', true );
	// ...and split it into an array of individual IDs.
	$custom_field_values_array = explode( ',', $custom_field_values );
	// Fetch up to 100 candidate items of the matching (free/paid) kind.
	$options                   = wpstream_get_all_items_list( 100, $post_selection );

	// Remove already-selected items from the candidate pool so they only appear once.
	foreach ( $custom_field_values_array as $key => $value ) {
		unset( $options[ $value ] );
	}

	// Build the "selected" list preserving the saved order of the chosen IDs.
	$options_selected = wpstream_get_all_items_list( count( $custom_field_values_array ), $post_selection, $custom_field_values_array );
	// Output the metabox fields.
	?>



	<!-- Instructional label for the source list of available channels. -->
	<label class="wpstream_full_label" for="custom_field"><?php echo esc_html__( 'Free to View Live Channels - click or drag them to the bundle list', 'hello-wpstream' ); ?></label>
	<!-- Two-column drag-and-drop area: source items (left) and chosen bundle items (right). -->
	<div class="wpstream_selection_wrapper">
		<!-- Left column: searchable pool of items not yet in the bundle. -->
		<div class="wpstream_selection_col_1">
			<!-- Live status/hint text updated by the autocomplete script. -->
			<span id="wpstream_autocomplete_status"><?php esc_html_e( 'Type so search an item', 'hello-wpstream' ); ?></span>
			<!-- Search box that triggers the AJAX item autocomplete. -->
			<input type="text"  class="wpstream_item_autocomplete_search" placeholder="<?php echo esc_attr__( 'search here', 'hello-wpstream' ); ?>"/>
			<!-- Source sortable list, populated with the candidate items. -->
			<ul id="sortable1" class="connectedSortable wpstream_sortable_list wpstream_sortable_list_initial">
				<?php
					// Render the candidate items as <li> rows (already escaped inside the helper).
					print wpstream_show_sortable_list_content( $options ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>
		</div>


		<!-- Right column: the items currently making up the bundle, in saved order. -->
		<div class="wpstream_selection_col_1">        
			<!-- Destination sortable list; its order is synced into the hidden input. -->
			<ul id="sortable2"  class="connectedSortable wpstream_sortable_list wpstream_sortable_list_final" >
				<?php
				// Render the already-selected items as <li> rows.
				print wpstream_show_sortable_list_content( $options_selected ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>
		</div>

		<!-- Hidden field that carries the comma-separated selected IDs back on save. -->
		<input type="hidden" name="wpstream_bundle_selection" id="wpstream_bundle_selection" value="<?php echo esc_attr( $custom_field_values ); ?>">
	</div>
	<?php
	// Emit a nonce so the save handler can verify the request originated here.
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
	// Accumulate the generated <li> markup here.
	$return_string = '';
	// Build one list item per option.
	foreach ( $options as $value => $item ) {
		// Open the list item, tagging it with the post ID and printing the escaped title.
		$return_string .= '<li class="ui-state-default" data-postID="' . intval( $value ) . '">' . esc_html( $item['title'] );
		$return_string .= '<div class="wpstream_product_list_type_wrapper">';

		// Default badges: assume a free "event" item until proven otherwise.
		$meta_free = esc_html__( 'free', 'hello-wpstream' );
		$meta_type = esc_html__( 'event', 'hello-wpstream' );

		// Derive the "type" badge (and free/paid) from the item's post type.
		if ( 'wpstream_product_vod' === $item['type'] ) {
			// Free video-on-demand item.
			$meta_type = esc_html__( 'vod', 'hello-wpstream' );
		} elseif ( 'wpstream_product' === $item['type'] ) {
			// Free live event item.
			$meta_type = esc_html__( 'event', 'hello-wpstream' );
		} elseif ( 'product' === $item['type'] ) {
			// WooCommerce product: inspect its product_type term to classify it.
			$term_list = wp_get_post_terms( $value, 'product_type' );

			// A WooCommerce product is a paid item.
			$meta_free = esc_html__( 'paid', 'hello-wpstream' );

			// Map the WooCommerce product_type term to an event/vod badge.
			if ( isset( $term_list[0]->name ) && 'live_stream' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'event', 'hello-wpstream' );
			} elseif ( isset( $term_list[0]->name ) && 'video_on_demand' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'vod', 'hello-wpstream' );
			}
		}

		// Append the free/paid and type badges, then close the wrapper and list item.
		$return_string .= '<span class="wpstream_product_list_free">' . $meta_free . '</span>';
		$return_string .= '<span class="wpstream_product_list_type">' . $meta_type . '</span>';
		$return_string .= '</div>';
		$return_string .= '</li>';
	}

	// Hand back the assembled list markup.
	return $return_string;
}

// Persist the bundle selection whenever any post is saved.
add_action( 'save_post', 'wpstream_custom_metabox_save' );

/**
 * Save custom metabox data when a post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 * @return void
 */
function wpstream_custom_metabox_save( $post_id ) {
	// Check if the nonce is set.
	// Bail if our metabox nonce was not submitted (not our form).
	if ( ! isset( $_POST['wpstream_custom_metabox_nonce'] ) ) {
		return;
	}

	// Verify the nonce.
	// Bail if the nonce is missing or fails verification.
	if ( ! isset( $_POST['wpstream_custom_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpstream_custom_metabox_nonce'] ) ), 'wpstream_custom_metabox_nonce' ) ) {
		return;
	}

	// Check if the current user has permission to save the post.
	// Bail if the user lacks edit rights on this post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save the custom field data.
	// Only proceed when the hidden selection field was submitted.
	if ( isset( $_POST['wpstream_bundle_selection'] ) ) {
		// Clean the comma-separated list of selected post IDs.
		$custom_field_value = sanitize_text_field( wp_unslash( $_POST['wpstream_bundle_selection'] ) );

		// do a inintial reset.
		// Load the previously saved selection so we can clear its back-references.
		$before_saving_items       = get_post_meta( $post_id, 'wpstream_bundle_selection', true );
		$before_saving_items_array = explode( ',', $before_saving_items );
		// Clear the "part of bundle" pointer on every previously bundled item.
		foreach ( $before_saving_items_array as $item_id ) {
			update_post_meta( $item_id, 'wpstream_part_of_bundle', '' );
		}

		// save the new id's.
		// Point each newly selected item back at this bundle post.
		$custom_field_value_array = explode( ',', $custom_field_value );
		foreach ( $custom_field_value_array as $item_id ) {
			update_post_meta( $item_id, 'wpstream_part_of_bundle', $post_id );
		}
		// Store the new selection list on the bundle post itself.
		update_post_meta( $post_id, 'wpstream_bundle_selection', $custom_field_value );
	}
}

/**
 * Metabox enqueue scripts
 *
 * Loads the CSS and JS for the bundle metabox and passes localized strings /
 * the AJAX URL to the script.
 *
 * @return void
 */
function custom_metabox_enqueue_scripts() {
	// Metabox stylesheet (cache-busted with a random query arg).
	wp_enqueue_style( 'wpstream_custom_metabox_style', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/css/wpstream_custom_metabox_style.css?v=' . wp_rand(), '', '1.0' );

	// Metabox script, depends on jQuery, loaded in the footer.
	wp_enqueue_script( 'wpstream_custom_metabox_script', WPSTREAM_PLUGIN_DIR_URL . 'hello-wpstream/js/wpstream_custom_metabox_script.js?v=' . wp_rand(), array( 'jquery' ), '1.0', true );
	// Expose the AJAX endpoint and translated UI strings to the script.
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
// Enqueue the metabox assets on admin screens.
add_action( 'admin_enqueue_scripts', 'custom_metabox_enqueue_scripts' );

/**
 * Enqueue the jQuery UI slider and gradient-picker script used by the Customizer button controls.
 *
 * @return void
 */
function enqueue_custom_scripts() {
	// jQuery UI slider powers the range controls.
	wp_enqueue_script( 'jquery-ui-slider' );
	// Gradient picker script; depends on jQuery, the color picker and the slider.
	wp_enqueue_script('wpstream_gradient_picker', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/js/wpstream_gradient_picker_script.js', array('jquery', 'wp-color-picker', 'jquery-ui-slider'), false, true);
}
// Enqueue the gradient-picker assets on admin screens.
add_action('admin_enqueue_scripts', 'enqueue_custom_scripts');

// Register the AJAX handler that backs the metabox item search box.
add_action( 'wp_ajax_wpstream_product_autocomplete', 'wpstream_product_autocomplete_callback' );

/**
 * Product autocomplete callback
 *
 * AJAX handler: searches stream/VOD/WooCommerce items by title and returns a
 * JSON list of matches (with free/paid and event/vod badges) for the metabox
 * autocomplete field.
 *
 * @return void Sends a JSON response and exits.
 */
function wpstream_product_autocomplete_callback() {
	// Restrict this endpoint to administrators.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Access denied! Only administrators' ); // Вернуть сообщение об ошибке или выполнить перенаправление, если необходимо.
	}

	// Read and sanitize the search term from the request.
	if ( isset( $_POST['term'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term = sanitize_text_field( wp_unslash( $_POST['term'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	// Query the wpstream_product custom post type.
	// Search across free live, free VOD and WooCommerce product post types.
	$post_type = array( 'wpstream_product_vod', 'wpstream_product', 'product' );
	// Build the search query: match the term, limit to 50, and restrict WooCommerce
	// products to live/vod types while still allowing items with no product_type term.
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

	// Run the search.
	$posts = get_posts( $args );

	// Collected autocomplete result rows.
	$results = array();

	// Build one result row per matched post.
	foreach ( $posts as $post ) {
		$title = $post->post_title;
		$type  = 'wpstream_product'; // Set the default post type
		// Retrieve the custom meta information.
		// The actual post type of this result, used for badge classification.
		$wpstream_post_type = $post->post_type;

		// Assign free/paid and event/vod badges based on the post type.
		// Free VOD item.
		if ( 'wpstream_product_vod' === $wpstream_post_type ) {
			$meta_free = esc_html__( 'free', 'hello-wpstream' );
			$meta_type = esc_html__( 'vod', 'hello-wpstream' );
		} elseif ( 'wpstream_product' === $type ) {
			// Free live event item. (Note: compares against $type, which is hard-coded above.)
			$meta_free = esc_html__( 'free', 'hello-wpstream' );
			$meta_type = esc_html__( 'event', 'hello-wpstream' );
		} elseif ( 'product' === $type ) {
			// WooCommerce paid product: classify via its product_type term.
			$term_list = wp_get_post_terms( $post->ID, 'product_type' );

			$meta_free = esc_html__( 'paid', 'hello-wpstream' );

			// Map the product_type term name to an event/vod badge.
			if ( 'live_stream' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'event', 'hello-wpstream' );
			} elseif ( 'video_on_demand' === $term_list[0]->name ) {
				$meta_type = esc_html__( 'vod', 'hello-wpstream' );
			}
		}

		// Assemble the result row for this post.
		$results[] = array(
			'value'     => $post->ID,
			'label'     => $title,
			'type'      => $wpstream_post_type,
			'meta_free' => $meta_free,
			'meta_type' => $meta_type,
		);
	}

	// Return all matches as a JSON response.
	wp_send_json( $results );

	// Safety net (wp_send_json already exits).
	wp_die();
}
