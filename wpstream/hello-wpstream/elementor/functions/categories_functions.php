<?php  




/*
* Generate and cache an array with all terms for $taxonomies array
*
*/
if ( ! function_exists( 'wpstream_theme_generate_all_taxomy_array' ) ) {
    function wpstream_theme_generate_all_taxomy_array($taxonomies){

        $all_tax=array();
        $default_args = array(
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => true,
        );



        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms($taxonomy, $default_args);

            if (!is_wp_error($terms)) {

                foreach($terms as $term){
                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_tax[]=$temp_array;

                }

                if(function_exists('wpstream_set_transient_cache')){
                    wpstream_set_transient_cache('wpstream_all_taxonomies_array',$all_tax,60*60*4);
                }
        
            }
        }

        return $all_tax;

    }

}


/**
 * Categories unit template.
 *
 * 
 */


if(!function_exists('wpstream_categories_card_selector')):
    function wpstream_categories_card_selector($type,$is_grid=0) {
        
        
    
        if($type==1){
            $template = 'category_unit_type1.php';
        }else if($type==2){
            $template = 'category_unit_type2.php';
        }else if($type==3){
            $template = 'category_unit_type3.php';
        }
        

        return 'template-parts/category-unit-templates/'.$template;

    }
endif;





/**
 * Categories list.
 *
 * @param array $attributes Attributes. 
 */

if(!function_exists('wpstreamtheme_categories_list_function')):
    function wpstreamtheme_categories_list_function( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

        $category_type          =   $attributes['design_type'];
        $card_type      = wpstream_categories_card_selector($category_type,0);
        $return_string = '<div class="wpstream_theme_categories_list_wrapper_widget row">';
            ob_start();
            foreach( $attributes['place_list'] as $key => $term_id){
	            include wpstream_get_card_type_path( $card_type );
            }
            $cards = ob_get_contents();
            ob_end_clean();
            $return_string.=$cards;


        $return_string.='</div>';


        return $return_string;


    }
endif;




/**
 * return categories wrapper class 
 *
 * @param array $attributes Attributes. 
 */
if(!function_exists('wpstream_theme_return_categories_card_class')):
    function wpstream_theme_return_categories_card_class( $arguments ){
   
        $return_class='col-md-4';


        switch ($arguments['items_per_row']) {
            case 12:
                $return_class='col-md-12';
                break;
            case 9:
                $return_class='col-md-9';
                break;
            case 8:
                $return_class='col-md-8';
                break;
            case 6:
                $return_class='col-xl-2 col-lg-4 col-md-6';
                break;
            case 4:
                $return_class='col-lg-3 col-md-6';
                break;
            case 3:
                $return_class='col-lg-4 col-md-6';
                break;
            case 2:
                $return_class='col-md-6';
                break;
        }

        return $return_class;
    }
endif;


/**
 * Categories slider.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_categories_slider( $attributes,$slider_id ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	
    $category_type          =   $attributes['design_type'];
    $card_type      = wpstream_categories_card_selector($category_type,0);

	$arrow_extra_class			  = '';
    if(isset($attributes['arrows_position'])){
        $arrow_extra_class="wpstream_arrows_position_".$attributes['arrows_position'];
    }
    $items_visible  = $attributes['place_per_row'];
    $is_auto        = false;

    $return_string = '<div class="wpstream_theme_categories_slider_wrapper_widget wpstream_category_slider wpstream_card_'.esc_attr($category_type).' row  '.esc_attr($arrow_extra_class).' " data-items-per-row="'.intval($items_visible).'" data-auto="' . esc_attr( $attributes['autoscroll'] ) . '"  id="' . esc_attr( $slider_id ) . '"  >';
        ob_start();
        foreach( $attributes['place_list'] as $key => $term_id){
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
        }
        $cards = ob_get_contents();
        ob_end_clean();
        $return_string.=$cards;


    $return_string.='</div>';


    return $return_string;
}

 



/**
 * Display grids.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_display_grids( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    $category_type          =   $attributes['design_type'];
    $card_type              =   wpstream_categories_card_selector($category_type,0);
    $display_grids          =   wpstream_theme_display_grids_setup();
    $display_grids_selected =   $display_grids[$attributes['grid_type']];

    $return_string = '<div class="wpstream_theme_categories_grid_wrapper_widget  row">';
        ob_start();
        $grid_index=1;
        $is_categories_grid='ýes';
        foreach( $attributes['place_list'] as $key => $term_id){
           
            if( !isset($display_grids_selected['position'][$grid_index])){
                $grid_index=1;
            }
            $attributes['place_per_row']=$display_grids_selected['position'][$grid_index];
            
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' .$card_type;
            $grid_index++;
            
        }
        $cards = ob_get_contents();
        ob_end_clean();
        $return_string.=$cards;


    $return_string.='</div>';


    return $return_string;
}




/**
 * Display grids Setup.
 *
 * @param array $attributes Attributes.
 */
if( !function_exists('wpstream_theme_display_grids_setup') ):
    function wpstream_theme_display_grids_setup(){
      $setup=array(
        1 =>  array(
                  'position' => array(
                                  1=> '8',
                                  2=> '3',
                                  3=> '3',
                                  4=> '3',
                                  5=> '3',
    
                                )
              ),
          2 =>  array(
                    'position' => array(
                                    1=> '6',
                                    2=> '4',
                                    3=> '4',
                                    4=> '4',
                                    5=> ' 4',
                                    6=> '6',
                                  )
                ),
          3 =>  array(
                    'position' => array(
                                      1=> '3',
                                      2=> '3',
                                      3=> '3',
                                      4=> '3',
                                      5=> '3',
                                      6=> '3',
                                  )
                ),
            4 =>  array(
                      'position' => array(
                                      1=> '3',
                                      2=> '3',
                                      3=> '3',
                                      4=> '6',
                                      5=> '6',
                                    )
                  ),
            5 =>  array(
                      'position' => array(
                                      1=> '3',
                                      2=> '8',
                                      3=> '8',
                                      4=> '3',
                                    )
                  ),
            6 =>  array(
                      'position' => array(
                                      1=> '4',
                                      2=> '4',
                                      3=> '4',
                                      4=> '4',
                                      5=> '4',
                                      6=> '4',
                                      7=> '4',
                                      8=> '4',
                                    )
                  ),
      );
      return $setup;
    }
endif;
    



/**
 * Categories list tabs.
 *
 * @param array $attributes Attributes.
 */
if( !function_exists('wpstream_theme_categories_list_functionas_tabs') ):
function wpstream_theme_categories_list_functionas_tabs( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    if ( isset($attributes['place_per_row']) ){
        $row_number        = $attributes['place_per_row'];
    }
    if($row_number>6){
        $row_number=6;
    }
    $attributes['place_per_row']= $row_number;

    $all_places_array=$attributes['form_fields'];
    $tab_items      =   '<ul class="nav nav-pills mb-3 wpstream_categories_as_tabs_ul"  role="tablist">';
    $tab_content    =   '<div class="tab-content">';
    $return_string  =   '<div class="wpstream_categories_as_tabs_wrapper" >';
    $class_active   =   'active';
    $area_selected  =   'true';
    $show_selected  =   'show';

    if(is_array($all_places_array)):
        foreach($all_places_array as $key=>$place_tax){
            $tab_items.='<li class="nav-item wpstream_categories_as_tabs_item" role="presentation">';
                $item_icon='';
                if(isset($place_tax['icon']) && !empty($place_tax['icon'])){
                    ob_start();
                    \Elementor\Icons_Manager::render_icon( $place_tax['icon'], [ 'aria-hidden' => 'true' ] );
                    $item_icon= ob_get_contents();
                    ob_end_clean();
                    
                }
         
   
                $tab_items.='<button class="nav-link '.esc_attr($class_active).'" id="pills-'.sanitize_title(trim($place_tax['field_type'])).'" data-bs-toggle="pill" 
                data-bs-target="#wpstream-pill-tab-'.sanitize_title(trim($place_tax['field_type'])).'" type="button" role="tab" 
                aria-controls="wpstream-pill-tab-'.sanitize_title(trim($place_tax['field_type'])).'" aria-selected="'.esc_attr($area_selected).'">'.$item_icon.esc_html($place_tax['field_label']).'</button>';
            $tab_items.='</li>';
        

            $tab_content.='<div role="tabpanel" class=" wpstream_categories_as_tabs_panel  tab-pane fade '.esc_attr($show_selected).' '.esc_attr($class_active).'" 
            id="wpstream-pill-tab-'.sanitize_title($place_tax['field_type']).'" aria-labelledby="'.sanitize_title($place_tax['field_type']).'" tabindex="0">
                <div class="row">
                    '.wpstream_theme_show_tax_items($place_tax['field_type'],$row_number,$attributes['show_zero_terms'],$attributes['max_items']).'
                </div>
            </div>';
            $class_active='';
            $area_selected='false';
            $show_selected='';
        }
    endif;

    $tab_items.='</ul>';    
    $tab_content.='</div>';   
    

    if($attributes['hide_items_bar']){
        $return_string .=$tab_content.'</div>';
    }else{
        $return_string .=$tab_items.$tab_content.'</div>';
    }



    
    return $return_string;

}
endif;



/**
 * Categories list tabs.
 *
 * @param array $attributes Attributes.
 */



if( !function_exists('wpstream_theme_show_tax_items') ):
    function wpstream_theme_show_tax_items($taxonomy,$row_number_col="4",$show_zero=true,$max_items=0){
        $return_string='';

        $arguments= array(
            'taxonomy' => trim($taxonomy),
            'hide_empty' => $show_zero,
        );
        if(floatval($max_items)>0){
            $arguments['number']=floatval($max_items);
        }
        
        $terms = get_terms($arguments );
        
        $card_type                  =   wpstream_categories_card_selector(3,0);
        $attributes['place_per_row'] =   $row_number_col;
        
        if(!is_wp_error($terms)){ 
            ob_start();
            foreach( $terms as $term ) {
                $term_id                        =   intval($term->term_id);
           
                include( locate_template($card_type ) );
            }

            $return_string=ob_get_contents();
            ob_end_clean();
        }
        return $return_string;
        
       
    }
    endif;

?>