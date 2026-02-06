<h3><?php 
if(function_exists('wpstream_is_global_subscription') && wpstream_is_global_subscription()){
      esc_html_e('Your Subscription','wpstream');
}else{
    esc_html_e('Event list','wpstream');
}
?></h3>
<?php
if (function_exists('wpstream_theme_purchased_event_list')) {
    // Call the theme function
    wpstream_theme_purchased_event_list();

}else{
	$customer_orders = wc_get_orders(array(
			'customer_id' => get_current_user_id(),
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array_keys(wc_get_order_statuses()),
	));

    $orders_array=array();
    foreach( $customer_orders as $order_data){

        $order  =   new WC_Order( $order_data->ID );
        $items  =   $order->get_items();
        foreach ( $items as $item ) {
            $product_name   =   $item['name'];
            $product_id     =   $item['product_id'];

            if(in_array($product_id, $orders_array)){
                continue;
            }else{
                $orders_array[]=$product_id;
            }

            $term_list      =   wp_get_post_terms($product_id, 'product_type');
            $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));

            if(class_exists ('WC_Subscription')){
                if($term_list[0]->name=='subscription' && !wcs_user_has_subscription(  get_current_user_id(), $product_id ,'active')){
                    continue;
                }
            }

            if( $term_list[0]->name=='live_stream' || ( $term_list[0]->name=='subscription' && $is_subscription_live_event=='yes')){
                $live_event_uri     =   get_post_meta($product_id,'live_event_uri',true);
                $url                =   get_permalink($product_id);

                echo    '<div class="wpstream_product_front">';
                echo    '<a class="wpstream_product_image_wrapper" href="'.$url.'">'.get_the_post_thumbnail($product_id,'thumb').'</a>';
                echo    '<div class="wpstream_product_wrapper">';
                echo    '<a class="wpstream_product_name_front" href="'.$url.'">'.$product_name.'</a>';
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