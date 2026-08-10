<?php
/**
 * Product add to cart section.
 *
 * Overlay shown on a single WooCommerce product (video) page above the trailer:
 * optional media logo, product tag, title, short description, category links,
 * and either an add-to-cart control (with quantity stepper for simple products)
 * or a "you already purchased this" notice. A small script keeps the cart URL's
 * quantity in sync with the stepper.
 *
 * @package wpstream-theme
 */

// Current product post and its ID.
global $post;
$product_id = $post->ID;
// Load the WooCommerce product object when WooCommerce is active.
if(function_exists('wc_get_product')){
	$product    = wc_get_product( $product_id );
}
?>

<!-- Product info overlay drawn on top of the trailer video. -->
<div class="wpstream_add_to_cart_over_trailer">

	<?php
	// Optional overlay logo stored as an attachment ID in the media_logo meta.
	$video_media_logo_id = intval( get_post_meta( $post_id, 'media_logo', true) );
	// Only output the logo when a real attachment ID is set.
	if ( $video_media_logo_id !=0 ) {
		 // Resolve the full-size URL for the logo attachment.
		 $video_media_logo = wp_get_attachment_url( $video_media_logo_id, 'full' );
		?>
		<!-- Media logo overlay (hidden while the trailer plays). -->
		<img class="wpstream_theme_media_logo wpstream_hide_on_trailer" src="<?php echo esc_url($video_media_logo); ?>" alt="media logo">
		<?php
	}

	?>

	

	<?php 
	// Render the product's tag/badge (e.g. live, VOD).
	wpstream_theme_products_tag($product_id,$product);
	?>
	
	<!-- Product title heading. -->
	<h1 class="wpstream_hide_on_trailer"><?php echo esc_html( $product->get_title() ); ?></h1>

	<!-- Product short description block. -->
	<div class="wpstream-product-description wpstream_hide_on_trailer">
		<?php
			// Output the short description allowing post-safe HTML.
			echo wp_kses_post( $product->get_short_description() );
		?>
	</div>

	<!-- Product category links, separated by middots. -->
	<div class="wpstream-product-categories-wrapper wpstream_hide_on_trailer">
		<?php
		// Fetch the product's categories and count them for separator logic.
		$categories       = get_the_terms( $product_id, 'product_cat' );
		$total_categories = 0;
		if(is_array($categories))
			$total_categories = count($categories);

	
		// 1-based index used to decide when to print the separator.
		$counter          = 1;

		// Print each category as a link, with a middot between (but not after the last).
		if ( ! empty( $categories ) && is_array( $categories ) && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category ) {
				// Resolve and print the category archive link.
				$category_link = get_term_link( $category );
				print '<a href="' . esc_url( $category_link ) . '">' . esc_html( $category->name ) . '</a>';
				// Separator between categories, skipped after the final one.
				if ( $counter < $total_categories ) {
					print ' <span>&#183;</span> ';
				}
				++$counter;
			}
		}

		?>

	</div>

	<?php
	// Only the buy/purchased UI applies on a single product page.
	if ( is_singular( 'product' ) ) {
		// Unpurchased branch: WooCommerce active and the current user hasn't bought this product.
		if ( function_exists( 'wc_customer_bought_product' ) && ! wc_customer_bought_product( $current_user->user_email, $current_user->ID, $post->ID ) ) {
			?>
			<!-- Add-to-cart controls for an unpurchased product. -->
			<div class="wpstream-author-buttons">

				<?php
				// Formatted price HTML for the button label.
				$wp_stream_product_price   = $product->get_price_html();
				// Cart URL that adds this product to the cart when followed.
				$wp_stream_add_to_cart_url = add_query_arg(
					array(
						'add-to-cart' => $post->ID,
					),
					wc_get_cart_url()
				);
				?>

				<?php
				// Simple products get a quantity stepper; other types add a single unit.
				if ( $product->get_type() === 'simple' ) { ?>
					<!-- Quantity stepper (minus / input / plus) for simple products. -->
					<div class="wpstream-product-quantity">
						<div><?php esc_html_e( 'Quantity', 'hello-wpstream' ); ?></div>
						<div class="wpstream-quantity-controls">
							<button type="button" class="minus">-</button>
							<?php echo woocommerce_quantity_input( array(), $product, false ); ?>
							<button type="button" class="plus">+</button>
						</div>
					</div>
				<?php } ?>
				<!-- Add-to-cart button; JS below keeps its quantity query arg in sync. -->
				<a href="<?php echo esc_url( $wp_stream_add_to_cart_url ); ?>" class="wp-stream-playbtn" id="add_to_cart_button">
					<span class="wp-stream-playbtn__cart-wrap">
						<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M19.5031 27.1253C19.7482 26.6779 20.2164 26.4 20.7249 26.4H30.2888C31.3346 26.4 32.255 25.826 32.7291 24.958L37.4433 16.3626C37.5965 16.0834 37.6441 15.7744 37.5997 15.4818C37.8472 15.2291 38 14.8825 38 14.5C38 13.7268 37.3757 13.1 36.6056 13.1H15.6892C15.6432 13.1 15.5978 13.1022 15.5529 13.1066L15.3151 12.6021C14.8539 11.6238 13.8724 11 12.7943 11H11.3944C10.6243 11 10 11.6268 10 12.4C10 13.1732 10.6243 13.8 11.3944 13.8H11.7084C12.3682 13.8 12.9691 14.1816 13.2519 14.7802L17.1955 23.1278C17.5805 23.9427 17.5509 24.894 17.1159 25.6832L15.9263 27.842C14.9084 29.718 16.247 32 18.3666 32H33.7052C34.4753 32 35.0996 31.3732 35.0996 30.6C35.0996 29.8268 34.4753 29.2 33.7052 29.2H20.7249C19.6643 29.2 18.992 28.0583 19.5031 27.1253ZM31.0848 22.1548L34.529 15.9H16.8699L19.7447 21.998C20.2059 22.9762 21.1874 23.6 22.2654 23.6H28.6441C29.659 23.6 30.5937 23.0464 31.0848 22.1548Z" fill="#F1F1F1"/>
							<path d="M17.9925 33C16.3383 33 15 34.35 15 36C15 37.65 16.3383 39 17.9925 39C19.6466 39 21 37.65 21 36C21 34.35 19.6466 33 17.9925 33Z" fill="#F1F1F1"/>
							<path d="M31.9925 33C30.3383 33 29 34.35 29 36C29 37.65 30.3383 39 31.9925 39C33.6466 39 35 37.65 35 36C35 34.35 33.6466 33 31.9925 33Z" fill="#F1F1F1"/>
						</svg>
				</span>
				<!-- Button label showing the price and the "Add to Cart" call to action. -->
				<div class="single-add-to-cart-button-text">
				<?php
				// translators: %s product price.
				printf( esc_html__( '%s&nbsp;- Add to Cart', 'hello-wpstream' ), $wp_stream_product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				</div>
			</a>

			</div>
			<?php
		}else{
			// Purchased branch: show a confirmation with the order date instead of a buy button.
			?>
			<!-- Notice confirming the current user already owns this product. -->
			<div class="wpstream-product-purchased-section">
    			<?php
    			// Current user and product type, used to look up the purchase record.
    			$current_user           =       wp_get_current_user();
				$product_type 			= 		$product->get_type();
    			// Checkmark icon then the "You have purchased this video on" label.
    			print  wpstream_theme_get_svg_icon( 'check.svg' );
				esc_html_e('You have purchased this video on','hello-wpstream');
				// Append the order date when the purchase record includes one.
				$list  = wpstream_dashboard_get_products_by_user( $current_user->ID, $product_type, 1,-1);
				if( isset($list[$post_id]['order_date']) ){
					print esc_html(' '.$list[$post_id]['order_date']);
				}
				?>
			</div>
			<?php
		}
	}
	?>
</div>

<!-- Quantity stepper behaviour and cart-URL sync. -->
<script>
    /* Wire up the +/- quantity buttons once the DOM is ready. */
    jQuery(document).ready(function($) {
        // Handle clicks on the plus/minus buttons within the quantity control.
        $('.wpstream-product-quantity').on( 'click', 'button.plus, button.minus', function() {
            // Current quantity input and its numeric value plus min/max/step bounds.
            var qty = $(this).closest('.wpstream-product-quantity').find('.qty');
            var val = parseFloat(qty.val());
            var max = parseFloat(qty.attr('max'));
            var min = parseFloat(qty.attr('min'));
            var step = parseFloat(qty.attr('step'));
            // Plus increments up to max; minus decrements down to min (never below 1).
            if ($(this).is('.plus')) {
                qty.val(max && max <= val ? max : val + step);
            } else {
                qty.val(min && min >= val ? min : val > 1 ? val - step : val);
            }
            // Reflect the new quantity into the add-to-cart link.
            add_to_cart_quantity( qty.val() );
        });

        // Update the quantity in the add to cart button URL
        function add_to_cart_quantity( quantity ) {
            // Rewrite the button's href with the chosen quantity query parameter.
            var addToCartButton = document.getElementById('add_to_cart_button');
            var url = new URL(addToCartButton.href);
            url.searchParams.set('quantity', quantity);
            addToCartButton.href = url.toString();
        }
    });
</script>