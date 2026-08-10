<?php
/**
 * Category unit template (type 2): image tile on top with the name/tagline/count
 * details underneath. Expects $term_id (and optionally $item_height,
 * $is_categories_grid, $attributes) to be in scope from the caller.
 *
 * @package wpstream-theme
 */

// Normalise the incoming term ID and initialise all display variables to empty defaults.
$term_id                        =   intval($term_id);
$category_attach_id             =   '';
$category_tax                   =   '';
$category_featured_image        =   '';
$category_name                  =   '';
$category_featured_image_url    =   '';
$category_tagline               =   '';
$category_description           =   '';
$category_count                 =   0;
// Fetch the term object (taxonomy left blank so WP resolves it from the ID).
$term= get_term($term_id, '');
$tagline='';

// When the term resolved successfully, pull its name, count, description and tagline meta.
if (!is_wp_error($term)) {
    $category_name=$term->name;
    $category_count=$term->count;
    $category_description = $term->description;
    $tagline = get_term_meta($term_id, 'category_tagline', true);
}

// Legacy per-term options blob holding the featured image / tagline for this term.
$term_meta                      =   get_option("taxonomy_$term_id");
// Featured image reference stored in the term options.
if (isset($term_meta['category_featured_image'])) {
    $category_featured_image=$term_meta['category_featured_image'];
}

// Resolve the featured image attachment to a URL when an attachment ID is stored.
if (isset($term_meta['category_attach_id'])) {
    $category_attach_id=$term_meta['category_attach_id'];
    
    // Default image size; switch to the larger blog size when rendered in a grid.
    $image_size='wpstream_categories_image';
    if(isset($is_categories_grid) && $is_categories_grid=='yes'){
        $image_size='wpstream_featured_blog_image';
    }

    // Look up the attachment source at the chosen size.
    $category_featured_image= wp_get_attachment_image_src($category_attach_id, $image_size);
    if( isset($category_featured_image[0]) ){
        $category_featured_image_url=$category_featured_image[0];
    }
}


// Tagline stored in the term options (slashes stripped for display).
if (isset($term_meta['category_tagline'])) {
     $category_tagline=  stripslashes($term_meta['category_tagline']);
}

// Build the archive link for the term; blank it out on error.
$term_link =  get_term_link($term_id, $category_tax);
if (is_wp_error($term_link)) {
    $term_link='';
}

// Fall back to the bundled default cover image when no featured image was found.
if ($category_featured_image_url=='') {
    $category_featured_image_url=get_theme_file_uri('/img/default-cover.png');
}


// Assemble the inline background-image style for the card.
$item_height_style  =   '';
$inline_style       =   " background-image: url(".esc_attr($category_featured_image_url).");";

// Apply a caller-provided fixed height to the card when supplied.
if (isset($item_height) && $item_height!='') {
    $item_height=1?$item_height:400;
    $inline_style.="min-height:100px;height:".floatval($item_height)."px;";
}



// Translate the optional "items per row" attribute into the card wrapper class.
$arguments=array();
if(isset($attributes['place_per_row'])){
$arguments['items_per_row']=intval($attributes['place_per_row']);
}

// Compute the responsive column/wrapper class for this card.
$wrapper_class= wpstream_theme_return_categories_card_class($arguments);



?>



<!-- Category card wrapper (type 2): image tile first, text details below. -->
<div class="wpstream_category_unit_wrapper_type2  <?php echo esc_attr($wrapper_class);?> "  <?php echo esc_attr($item_height_style);?> >
    
 


  
    
    <!-- Image tile: clickable featured-image background containing the item count and cover overlay. -->
    <div class="wpstream_category_unit_item wpstream_category_unit_link col" data-link="<?php echo esc_attr($term_link);?>"    style="<?php echo trim($inline_style);?>" >
        <!-- Localised item count overlaid on the image. -->
        <div class="wpstream_category_unit_item_details_listings">
            <?php
            // Print "%d item(s)" using the singular/plural form for the count.
            printf(  _n('%d item', '%d items', $category_count, 'hello-wpstream'), $category_count );
            $protocol = is_ssl() ? 'https' : 'http';
            ?>
        </div>
        <div class="wpstream_category_unit_item_cover" data-link="<?php echo esc_attr($term_link);?>" ></div>
    </div> 

     <!-- Text details column: title and optional tagline. -->
     <div class="wpstream_category_unit_item_details">
        <!-- Category title linking to the archive; name truncated to 44 chars. -->
        <h4>
            <a href="<?php echo esc_url($term_link); ?>">
            <?php
                // Print at most the first 44 characters of the category name.
                echo mb_substr($category_name, 0, 44);
                // Append an ellipsis when the name was longer than 44 characters.
                if (mb_strlen($category_name)>44) {
                    echo '...';
                }
            ?>
            </a>
        </h4> 

     
        <?php 
        if(!empty( $category_tagline)){
            ?>
            <!-- Optional category tagline, output only when one is set. -->
            <div class="wpstream_category_unit_item_details_tagline">
                <?php print esc_html($category_tagline);?>
            </div>
            
            <?php
        }
        ?>
     
    </div>
   
</div>
