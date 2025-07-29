<?php
$term_id                        =   intval($term_id);
$category_attach_id             =   '';
$category_tax                   =   '';
$category_featured_image        =   '';
$category_name                  =   '';
$category_featured_image_url    =   '';
$category_tagline               =   '';
$category_description           =   '';
$category_count                 =   0;
$term= get_term($term_id, '');
$tagline='';

if (!is_wp_error($term)) {
    $category_tax = $term->taxonomy;
    $category_name=$term->name;
    $category_count=$term->count;
    $category_description = $term->description;
    $tagline = get_term_meta($term_id, 'category_tagline', true);
}

$term_meta                      =   get_option("taxonomy_$term_id");
if (isset($term_meta['category_featured_image'])) {
    $category_featured_image=$term_meta['category_featured_image'];
}

if (isset($term_meta['category_attach_id'])) {
    $category_attach_id=$term_meta['category_attach_id'];
    $category_featured_image= wp_get_attachment_image_src($category_attach_id, 'wpstream_user_image');
    if( isset($category_featured_image[0]) ){
        $category_featured_image_url=$category_featured_image[0];
    }
}


if (isset($term_meta['category_tagline'])) {
     $category_tagline=  stripslashes($term_meta['category_tagline']);
}

$term_link =  get_term_link($term_id, $category_tax);
if (is_wp_error($term_link)) {
    $term_link='';
}

if ($category_featured_image_url=='') {
    $category_featured_image_url=get_theme_file_uri('/img/default-cover.png');
}


$item_height_style  =   '';
$inline_style       =   " background-image: url(".esc_attr($category_featured_image_url).");";

if (isset($item_height) && $item_height!='') {
    $item_height=1?$item_height:400;
    $inline_style.="min-height:100px;height:".floatval($item_height)."px;";
}



$arguments=array();
if(isset($attributes['place_per_row'])){
$arguments['items_per_row']=intval($attributes['place_per_row']);
}

$wrapper_class= wpstream_theme_return_categories_card_class($arguments);



?>



<div class="wpstream_category_unit_wrapper_type3 <?php echo esc_attr($wrapper_class);?> "  <?php echo esc_attr($item_height_style);?> >
    
    <div class="wpstream_category_unit_item wpstream_category_unit_link col" data-link="<?php echo esc_attr($term_link);?>"    style="<?php echo trim($inline_style);?>" >
        <div class="wpstream_category_unit_item_cover" data-link="<?php echo esc_attr($term_link);?>" ></div>
    </div> 

    <div class="wpstream_category_unit_item_details">
        <h4>
            <a href="<?php echo esc_url($term_link); ?>">
            <?php
                echo mb_substr($category_name, 0, 44);
                if (mb_strlen($category_name)>44) {
                    echo '...';
                }
            ?>
            </a>
        </h4> 


        <div class="wpstream_category_unit_item_details_listings">
            <?php
            printf(  _n('%d item', '%d items', $category_count, 'hello-wpstream'), $category_count );
            $protocol = is_ssl() ? 'https' : 'http';
            ?>
        </div>
  </div>


   
    
 
   
</div>
