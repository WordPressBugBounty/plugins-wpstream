<?php  

/**
 * Elementor category widgets - rendering helpers.
 *
 * Backs the Categories List / Slider / Grid / Tabs Elementor widgets. Each helper
 * resolves a category-unit template, loops the selected term ids (or fetched terms),
 * buffers the rendered cards, and returns the assembled HTML string. Also provides
 * the bootstrap grid column-class map and the grid-layout position presets.
 *
 * @package wpstream-theme
 */




/*
* Generate and cache an array with all terms for $taxonomies array
*
*/
if ( ! function_exists( 'wpstream_theme_generate_all_taxomy_array' ) ) {
    function wpstream_theme_generate_all_taxomy_array($taxonomies){

        // Accumulator for the flattened label/value pairs gathered across every taxonomy.
        $all_tax=array();
        // Shared get_terms() args: order by name ascending, non-empty terms only.
        $default_args = array(
            'orderby'    => 'name',
            'order'      => 'ASC',
            'hide_empty' => true,
        );



        // Walk each requested taxonomy and collect its terms.
        foreach ($taxonomies as $taxonomy) {
            // Fetch this taxonomy's terms.
            $terms = get_terms($taxonomy, $default_args);

            // Only process a valid term set (skip taxonomies that returned a WP_Error).
            if (!is_wp_error($terms)) {

                // Reduce each term to a label/value pair and append it to the accumulator.
                foreach($terms as $term){
                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_tax[]=$temp_array;

                }

                // Cache the accumulated array for 4 hours when the cache helper exists.
                // NOTE: this cache write lives inside the taxonomy foreach, so it re-writes once per taxonomy.
                if(function_exists('wpstream_set_transient_cache')){
                    wpstream_set_transient_cache('wpstream_all_taxonomies_array',$all_tax,60*60*4);
                }
        
            }
        }

        // Return the combined label/value list for all requested taxonomies.
        return $all_tax;

    }

}


/**
 * Categories unit template.
 *
 * 
 */


if(!function_exists('wpstream_categories_card_selector')):
    /**
     * Map a numeric category design type to its unit-template path.
     *
     * @param int $type    Design variant (1, 2 or 3).
     * @param int $is_grid Unused flag kept for signature compatibility.
     * @return string Template path relative to the theme/plugin template root.
     */
    function wpstream_categories_card_selector($type,$is_grid=0) {
        
        
    
        // Pick the category-unit template file for the chosen design type.
        if($type==1){
            $template = 'category_unit_type1.php';
        }else if($type==2){
            $template = 'category_unit_type2.php';
        }else if($type==3){
            $template = 'category_unit_type3.php';
        }
        

        // Return the template path relative to the template-parts root.
        // NOTE: when $type is not 1-3, $template is undefined at this point.
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

        // Chosen category card design (1-3) from the widget settings.
        $category_type          =   $attributes['design_type'];
        // Resolve the matching category-unit template path.
        $card_type      = wpstream_categories_card_selector($category_type,0);
        // Open the list wrapper markup.
        $return_string = '<div class="wpstream_theme_categories_list_wrapper_widget row">';
            // Start buffering the rendered cards.
            ob_start();
            // Render one card per selected term id ($term_id is in scope for the template).
            foreach( $attributes['place_list'] as $key => $term_id){
	            // Include the card template, resolved through wpstream_get_card_type_path().
	            include wpstream_get_card_type_path( $card_type );
            }
            // Capture the buffered cards HTML...
            $cards = ob_get_contents();
            // ...and discard the buffer.
            ob_end_clean();
            // Append the cards to the wrapper.
            $return_string.=$cards;


        // Close the list wrapper.
        $return_string.='</div>';


        // Return the assembled list HTML.
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
   
        // Default bootstrap column class (three per row).
        $return_class='col-md-4';


        // Translate the requested items-per-row into a bootstrap column-class string.
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

        // Return the resolved column class.
        return $return_class;
    }
endif;


/**
 * Categories slider.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_categories_slider( $attributes,$slider_id ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	
    // Chosen category card design (1-3) from the widget settings.
    $category_type          =   $attributes['design_type'];
    // Resolve the matching category-unit template path.
    $card_type      = wpstream_categories_card_selector($category_type,0);

	$arrow_extra_class			  = '';
    // Optional arrow-position modifier class for the slider controls.
    if(isset($attributes['arrows_position'])){
        $arrow_extra_class="wpstream_arrows_position_".$attributes['arrows_position'];
    }
    // Number of cards visible per row in the slider.
    $items_visible  = $attributes['place_per_row'];
    $is_auto        = false;

    // Open the slider wrapper, embedding card type, per-row count and autoscroll data attributes.
    $return_string = '<div class="wpstream_theme_categories_slider_wrapper_widget wpstream_category_slider wpstream_card_'.esc_attr($category_type).' row  '.esc_attr($arrow_extra_class).' " data-items-per-row="'.intval($items_visible).'" data-auto="' . esc_attr( $attributes['autoscroll'] ) . '"  id="' . esc_attr( $slider_id ) . '"  >';
        // Start buffering the rendered slider cards.
        ob_start();
        // Render one card per selected term id ($term_id is in scope for the template).
        foreach( $attributes['place_list'] as $key => $term_id){
            // Include the card template from the plugin's hello-wpstream path.
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
        }
        // Capture the buffered cards HTML...
        $cards = ob_get_contents();
        // ...and discard the buffer.
        ob_end_clean();
        // Append the cards to the slider wrapper.
        $return_string.=$cards;


    // Close the slider wrapper.
    $return_string.='</div>';


    // Return the assembled slider HTML.
    return $return_string;
}

 



/**
 * Display grids.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_display_grids( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    // Chosen category card design (1-3) from the widget settings.
    $category_type          =   $attributes['design_type'];
    // Resolve the matching category-unit template path.
    $card_type              =   wpstream_categories_card_selector($category_type,0);
    // Load the map of grid layouts (each maps a slot index to a column width).
    $display_grids          =   wpstream_theme_display_grids_setup();
    // Select the layout preset requested by the widget's grid_type.
    $display_grids_selected =   $display_grids[$attributes['grid_type']];

    // Open the grid wrapper markup.
    $return_string = '<div class="wpstream_theme_categories_grid_wrapper_widget  row">';
        // Start buffering the rendered grid cards.
        ob_start();
        // Slot counter used to index into the layout's position map (1-based).
        $grid_index=1;
        // Flag consumed by the included card template to mark grid context (note: value has a non-ASCII first char).
        $is_categories_grid='ýes';
        // Render one card per selected term id, assigning each a column width from the preset.
        foreach( $attributes['place_list'] as $key => $term_id){
           
            // When the preset runs out of positions, wrap back to the first slot.
            if( !isset($display_grids_selected['position'][$grid_index])){
                $grid_index=1;
            }
            // Feed the current slot's column width to the card template via place_per_row.
            $attributes['place_per_row']=$display_grids_selected['position'][$grid_index];
            
            // Include the card template from the plugin's hello-wpstream path.
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' .$card_type;
            // Advance to the next layout slot.
            $grid_index++;
            
        }
        // Capture the buffered cards HTML...
        $cards = ob_get_contents();
        // ...and discard the buffer.
        ob_end_clean();
        // Append the cards to the grid wrapper.
        $return_string.=$cards;


    // Close the grid wrapper.
    $return_string.='</div>';


    // Return the assembled grid HTML.
    return $return_string;
}




/**
 * Display grids Setup.
 *
 * @param array $attributes Attributes.
 */
if( !function_exists('wpstream_theme_display_grids_setup') ):
    /**
     * Grid layout presets.
     *
     * @return array Map of grid_type => ['position' => [slot => bootstrap-column-width]].
     */
    function wpstream_theme_display_grids_setup(){
      // Each numbered preset lists the column width for each successive card slot.
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
      // Return the full set of grid layout presets.
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
    // Read the requested columns-per-row for the tab panels, if provided.
    if ( isset($attributes['place_per_row']) ){
        $row_number        = $attributes['place_per_row'];
    }
    // Cap the per-row count at 6.
    if($row_number>6){
        $row_number=6;
    }
    // Write the (possibly capped) per-row value back into the attributes.
    $attributes['place_per_row']= $row_number;

    // The list of taxonomy tabs to render (label/icon/field_type per entry).
    $all_places_array=$attributes['form_fields'];
    // Seed the tab-strip, tab-content and outer wrapper markup, plus the first-tab active flags.
    $tab_items      =   '<ul class="nav nav-pills mb-3 wpstream_categories_as_tabs_ul"  role="tablist">';
    $tab_content    =   '<div class="tab-content">';
    $return_string  =   '<div class="wpstream_categories_as_tabs_wrapper" >';
    $class_active   =   'active';
    $area_selected  =   'true';
    $show_selected  =   'show';

    // Only build tabs when we actually have an array of tab definitions.
    if(is_array($all_places_array)):
        // Build one nav pill + matching tab panel per taxonomy entry.
        foreach($all_places_array as $key=>$place_tax){
            // Open the nav list item.
            $tab_items.='<li class="nav-item wpstream_categories_as_tabs_item" role="presentation">';
                $item_icon='';
                // Render the optional Elementor icon into the tab button, buffered to a string.
                if(isset($place_tax['icon']) && !empty($place_tax['icon'])){
                    ob_start();
                    \Elementor\Icons_Manager::render_icon( $place_tax['icon'], [ 'aria-hidden' => 'true' ] );
                    $item_icon= ob_get_contents();
                    ob_end_clean();
                    
                }
         
   
                // Append the tab button, wiring Bootstrap pill toggling to the matching panel id.
                $tab_items.='<button class="nav-link '.esc_attr($class_active).'" id="pills-'.sanitize_title(trim($place_tax['field_type'])).'" data-bs-toggle="pill" 
                data-bs-target="#wpstream-pill-tab-'.sanitize_title(trim($place_tax['field_type'])).'" type="button" role="tab" 
                aria-controls="wpstream-pill-tab-'.sanitize_title(trim($place_tax['field_type'])).'" aria-selected="'.esc_attr($area_selected).'">'.$item_icon.esc_html($place_tax['field_label']).'</button>';
            $tab_items.='</li>';
        

            // Append the tab panel, filled with this taxonomy's term cards via wpstream_theme_show_tax_items().
            $tab_content.='<div role="tabpanel" class=" wpstream_categories_as_tabs_panel  tab-pane fade '.esc_attr($show_selected).' '.esc_attr($class_active).'" 
            id="wpstream-pill-tab-'.sanitize_title($place_tax['field_type']).'" aria-labelledby="'.sanitize_title($place_tax['field_type']).'" tabindex="0">
                <div class="row">
                    '.wpstream_theme_show_tax_items($place_tax['field_type'],$row_number,$attributes['show_zero_terms'],$attributes['max_items']).'
                </div>
            </div>';
            // Only the first tab is active/shown; clear the flags for subsequent tabs.
            $class_active='';
            $area_selected='false';
            $show_selected='';
        }
    endif;

    // Close the nav list and tab-content containers.
    $tab_items.='</ul>';    
    $tab_content.='</div>';   
    

    // Optionally hide the tab bar itself, keeping only the panels.
    if($attributes['hide_items_bar']){
        $return_string .=$tab_content.'</div>';
    }else{
        $return_string .=$tab_items.$tab_content.'</div>';
    }



    
    // Return the assembled tabs markup.
    return $return_string;

}
endif;



/**
 * Categories list tabs.
 *
 * @param array $attributes Attributes.
 */



if( !function_exists('wpstream_theme_show_tax_items') ):
    /**
     * Render the term cards for a single taxonomy (used by the tabs widget).
     *
     * @param string     $taxonomy       Taxonomy slug to list terms from.
     * @param string|int $row_number_col Bootstrap column width per card. Default '4'.
     * @param bool       $show_zero      Passed to get_terms as 'hide_empty'.
     * @param int        $max_items      Optional cap on number of terms (0 = no cap).
     * @return string Buffered HTML of the rendered term cards.
     */
    function wpstream_theme_show_tax_items($taxonomy,$row_number_col="4",$show_zero=true,$max_items=0){
        // Output accumulator.
        $return_string='';

        // Base get_terms() args for this taxonomy.
        $arguments= array(
            'taxonomy' => trim($taxonomy),
            'hide_empty' => $show_zero,
        );
        // Apply an optional maximum number of terms.
        if(floatval($max_items)>0){
            $arguments['number']=floatval($max_items);
        }
        
        // Fetch the terms.
        $terms = get_terms($arguments );
        
        // Use category-unit design type 3 for these tab cards.
        $card_type                  =   wpstream_categories_card_selector(3,0);
        // Provide the per-card column width to the template via place_per_row.
        $attributes['place_per_row'] =   $row_number_col;
        
        // Only render when the term query succeeded (no WP_Error).
        if(!is_wp_error($terms)){ 
            // Buffer the rendered term cards.
            ob_start();
            // Render one card per term.
            foreach( $terms as $term ) {
                // Expose the current term id to the included template.
                $term_id                        =   intval($term->term_id);

				// Include the card template from the plugin's hello-wpstream path.
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
            }

            // Capture the buffered cards HTML...
            $return_string=ob_get_contents();
            // ...and discard the buffer.
            ob_end_clean();
        }
        // Return the buffered term cards (empty string on WP_Error).
        return $return_string;
        
       
    }
    endif;

?>