<!--
	My Account > Event list (WpStream template override).
	Lists the current customer's purchased live-stream and subscription live events.
	Prefers the theme's own renderer when present, otherwise rebuilds the list from WooCommerce orders.
-->
<h3><?php 
// Heading text: "Your Subscription" on global-subscription sites, otherwise "Event list".
if(function_exists('wpstream_is_global_subscription') && wpstream_is_global_subscription()){
      esc_html_e('Your Subscription','wpstream');
}else{
    esc_html_e('Event list','wpstream');
}
?></h3>
<?php
// Prefer the theme's own purchased-event renderer when it is available.
if (function_exists('wpstream_theme_purchased_event_list')) {
    // Call the theme function
    wpstream_theme_purchased_event_list();

}else{
	// Fallback: no theme renderer, so query every order belonging to this customer.
	$customer_orders = wc_get_orders(array(
			'customer_id' => get_current_user_id(),
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array_keys(wc_get_order_statuses()),
	));

    // Remember product ids already rendered so each product is listed only once.
    $orders_array=array();
    // Walk every order belonging to the customer.
    foreach( $customer_orders as $order_data){

        // Load the full order object and its purchased line items.
        $order  =   new WC_Order( $order_data->ID );
        $items  =   $order->get_items();
        // Inspect each purchased line item.
        foreach ( $items as $item ) {
            $product_name   =   $item['name'];
            $product_id     =   $item['product_id'];

            // Skip products already listed; otherwise record this one and continue.
            if(in_array($product_id, $orders_array)){
                continue;
            }else{
                $orders_array[]=$product_id;
            }

            // Determine the product type and whether a subscription covers a live event.
            $term_list      =   wp_get_post_terms($product_id, 'product_type');
            $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));

            // For subscription products, skip unless the user holds an active subscription.
            if(class_exists ('WC_Subscription')){
                if($term_list[0]->name=='subscription' && !wcs_user_has_subscription(  get_current_user_id(), $product_id ,'active')){
                    continue;
                }
            }

            // Render only live-stream products (or subscriptions flagged as live events).
            if( $term_list[0]->name=='live_stream' || ( $term_list[0]->name=='subscription' && $is_subscription_live_event=='yes')){
                // Gather the event URI and the product permalink for the card links.
                $live_event_uri     =   get_post_meta($product_id,'live_event_uri',true);
                $url                =   get_permalink($product_id);

                // Output the product card: thumbnail, title and (usually) a "see the event" link.
                echo    '<div class="wpstream_product_front">';
                echo    '<a class="wpstream_product_image_wrapper" href="'.$url.'">'.get_the_post_thumbnail($product_id,'thumb').'</a>';
                echo    '<div class="wpstream_product_wrapper">';
                echo    '<a class="wpstream_product_name_front" href="'.$url.'">'.$product_name.'</a>';
                // On global-subscription sites, omit the per-event "see the event" link.
                if(function_exists('wpstream_is_global_subscription') && wpstream_is_global_subscription()){

                }else{
                    echo    '<a class="wpstream_product_see" href="'.$url.'">'.__('see the event','wpstream').'</a>';
                }
                echo    '</div>';
                echo    '</div>';
            }


        }
    }            

    
}