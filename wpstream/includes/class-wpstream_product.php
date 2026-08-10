<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/**
 * Registers the WpStream custom post types and their taxonomies.
 *
 * Defines the Free-To-View Live Channel, Free-To-View VOD, and Video
 * Collection post types together with the wpstream_actors / wpstream_category /
 * wpstream_movie_rating taxonomies, wires custom capabilities, renders the
 * taxonomy term meta fields (page id, featured image, tagline), and maintains
 * cached term-value lists used by shortcodes.
 *
 * @author cretu
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */
class Wpstream_Product {

    /**
     * Hook term add/edit/delete to cache rebuilds and adjust the product list columns.
     */
    public function __construct() {
        // Any term create/edit/delete invalidates and rebuilds the cached term lists.
        add_action( 'create_term', array($this,'wpstream_redo_transient') );
        add_action( 'edit_term', array($this,'wpstream_redo_transient') );
        add_action( 'delete_term', array($this,'wpstream_redo_transient') );
        // Hide the WpStream taxonomy columns from the WooCommerce products table.
        add_filter('manage_edit-product_columns', array($this,'remove_wpstream_category_column') ) ;
    }


    /**
     * Flush the cached taxonomy value transients and regenerate them.
     */
    public function wpstream_redo_transient(){
        // Drop the stale cached term-value lists.
        delete_transient('wpstream_woo_movie_category_values');
        delete_transient('wpstream_woo_actors_category_values');
        delete_transient('wpstream_woo_product_cat');
        delete_transient('wpstream_woo_movie_rating_category_values');
        // Rebuild each list so subsequent reads hit a warm cache.
        $this->wpstream_generate_woo_movie_category_values_shortcode();
        $this->wpstream_generate_actors_category_values_shortcode();
        $this->wpstream_generate_woo_product_tax_values_shortcode();
        $this->wpstream_generate_movie_rating_category_values_shortcode();
    }
    
    
    
    /**
    * Register custom post type
    *
    * @link https://codex.wordpress.org/Function_Reference/register_post_type
    *
    * @param array $fields Post type definition (slug, singular/plural labels, supports, caps, taxonomies, ...).
    */
    private function register_single_post_type( $fields ) {

        // Build the full labels array from the singular/plural names supplied in $fields.
        $labels = array(
            'name'                  => $fields['plural'],
            'singular_name'         => $fields['singular'],
            'menu_name'             => $fields['menu_name'],
            'new_item'              => sprintf( __( 'New %s', 'wpstream' ), $fields['singular'] ),
            'add_new_item'          => sprintf( __( 'Add new %s', 'wpstream' ), $fields['singular'] ),
            'edit_item'             => sprintf( __( 'Edit %s', 'wpstream' ), $fields['singular'] ),
            'view_item'             => sprintf( __( 'View %s', 'wpstream' ), $fields['singular'] ),
            'view_items'            => sprintf( __( 'View %s', 'wpstream' ), $fields['plural'] ),
            'search_items'          => sprintf( __( 'Search %s', 'wpstream' ), $fields['plural'] ),
            'not_found'             => sprintf( __( 'No %s found', 'wpstream' ), strtolower( $fields['plural'] ) ),
            'not_found_in_trash'    => sprintf( __( 'No %s found in trash', 'wpstream' ), strtolower( $fields['plural'] ) ),
            'all_items'             => sprintf( __( 'All %s', 'wpstream' ), $fields['plural'] ),
            'archives'              => sprintf( __( '%s Archives', 'wpstream' ), $fields['singular'] ),
            'attributes'            => sprintf( __( '%s Attributes', 'wpstream' ), $fields['singular'] ),
            'insert_into_item'      => sprintf( __( 'Insert into %s', 'wpstream' ), strtolower( $fields['singular'] ) ),
            'uploaded_to_this_item' => sprintf( __( 'Uploaded to this %s', 'wpstream' ), strtolower( $fields['singular'] ) ),

            /* Labels for hierarchical post types only. */
            'parent_item'           => sprintf( __( 'Parent %s', 'wpstream' ), $fields['singular'] ),
            'parent_item_colon'     => sprintf( __( 'Parent %s:', 'wpstream' ), $fields['singular'] ),

            /* Custom archive label.  Must filter 'post_type_archive_title' to use. */
			'archive_title'        => $fields['plural'],
        );

        // Assemble register_post_type() args, falling back to sensible defaults for anything not in $fields.
        $args = array(
            'labels'             => $labels,
            'description'        => ( isset( $fields['description'] ) ) ? $fields['description'] : '',
            'public'             => ( isset( $fields['public'] ) ) ? $fields['public'] : true,
            'publicly_queryable' => ( isset( $fields['publicly_queryable'] ) ) ? $fields['publicly_queryable'] : true,
            'exclude_from_search'=> ( isset( $fields['exclude_from_search'] ) ) ? $fields['exclude_from_search'] : false,
            'show_ui'            => ( isset( $fields['show_ui'] ) ) ? $fields['show_ui'] : true,
            'show_in_menu'       => ( isset( $fields['show_in_menu'] ) ) ? $fields['show_in_menu'] : true,
            'query_var'          => ( isset( $fields['query_var'] ) ) ? $fields['query_var'] : true,
            'show_in_admin_bar'  => ( isset( $fields['show_in_admin_bar'] ) ) ? $fields['show_in_admin_bar'] : true,
            'capability_type'    => ( isset( $fields['capability_type'] ) ) ? $fields['capability_type'] : 'post',
            'has_archive'        => ( isset( $fields['has_archive'] ) ) ? $fields['has_archive'] : true,
            'hierarchical'       => ( isset( $fields['hierarchical'] ) ) ? $fields['hierarchical'] : true,
            'supports'           => ( isset( $fields['supports'] ) ) ? $fields['supports'] : array(
                    'title',
                    'editor',
                    'excerpt',
                    'author',
                    'thumbnail',
                    'comments',
                    'trackbacks',
                    'custom-fields',
                    'revisions',
                    'page-attributes',
                    'post-formats',
            ),
            'menu_position'      => ( isset( $fields['menu_position'] ) ) ? $fields['menu_position'] : 21,
            'menu_icon'          => ( isset( $fields['menu_icon'] ) ) ? $fields['menu_icon']: 'dashicons-admin-generic',
            'show_in_nav_menus'  => ( isset( $fields['show_in_nav_menus'] ) ) ? $fields['show_in_nav_menus'] : true,
            'taxonomies'          => array( 'category','post_tag' ),
        );

        // Apply a custom permalink rewrite rule when the definition provides one.
        if ( isset( $fields['rewrite'] ) ) {

            /**
             *  Add $this->plugin_name as translatable in the permalink structure,
             *  to avoid conflicts with other plugins which may use customers as well.
             */
            $args['rewrite'] = $fields['rewrite'];
        }

        // When custom capabilities are requested, map meta caps and assign them to roles.
        if ( $fields['custom_caps'] ) {

            /**
             * Provides more precise control over the capabilities than the defaults.  By default, WordPress
             * will use the 'capability_type' argument to build these capabilities.  More often than not,
             * this results in many extra capabilities that you probably don't need.  The following is how
             * I set up capabilities for many post types, which only uses three basic capabilities you need
             * to assign to roles: 'manage_examples', 'edit_examples', 'create_examples'.  Each post type
             * is unique though, so you'll want to adjust it to fit your needs.
             *
             * @link https://gist.github.com/creativembers/6577149
             * @link http://justintadlock.com/archives/2010/07/10/meta-capabilities-for-custom-post-types
             */
            // Explicit capability map keyed off the singular/plural names.
            $args['capabilities'] = array(

                // Meta capabilities
                'edit_post'                 => 'edit_' . strtolower( $fields['singular'] ),
                'read_post'                 => 'read_' . strtolower( $fields['singular'] ),
                'delete_post'               => 'delete_' . strtolower( $fields['singular'] ),

                // Primitive capabilities used outside of map_meta_cap():
                'edit_posts'                => 'edit_' . strtolower( $fields['plural'] ),
                'edit_others_posts'         => 'edit_others_' . strtolower( $fields['plural'] ),
                'publish_posts'             => 'publish_' . strtolower( $fields['plural'] ),
                'read_private_posts'        => 'read_private_' . strtolower( $fields['plural'] ),

                // Primitive capabilities used within map_meta_cap():
                'delete_posts'              => 'delete_' . strtolower( $fields['plural'] ),
                'delete_private_posts'      => 'delete_private_' . strtolower( $fields['plural'] ),
                'delete_published_posts'    => 'delete_published_' . strtolower( $fields['plural'] ),
                'delete_others_posts'       => 'delete_others_' . strtolower( $fields['plural'] ),
                'edit_private_posts'        => 'edit_private_' . strtolower( $fields['plural'] ),
                'edit_published_posts'      => 'edit_published_' . strtolower( $fields['plural'] ),
                'create_posts'              => 'edit_' . strtolower( $fields['plural'] )

            );

            /**
             * Adding map_meta_cap will map the meta correctly.
             * @link https://wordpress.stackexchange.com/questions/108338/capabilities-and-custom-post-types/108375#108375
             */
            $args['map_meta_cap'] = true;

            /**
             * Assign capabilities to users
             * Without this, users - also admins - can not see post type.
             */
            // Grant the mapped capabilities to the configured roles.
            $this->assign_capabilities( $args['capabilities'], $fields['custom_caps_users'] );
        }

        // Register the post type itself with WordPress.
        register_post_type( $fields['slug'], $args );

        /**
         * Register Taxnonmies if any
         * @link https://codex.wordpress.org/Function_Reference/register_taxonomy
         */
        // Register each associated taxonomy declared for this post type.
        if ( isset( $fields['taxonomies'] ) && is_array( $fields['taxonomies'] ) ) {

            foreach ( $fields['taxonomies'] as $taxonomy ) {

                $this->register_single_post_type_taxnonomy( $taxonomy );

            }

        }

        // Warm the cached term-value lists now that the taxonomies exist.
        $this->wpstream_generate_woo_movie_category_values_shortcode();
        $this->wpstream_generate_actors_category_values_shortcode();
        $this->wpstream_generate_woo_product_tax_values_shortcode();
        $this->wpstream_generate_movie_rating_category_values_shortcode();

    }

    
    
    
    /**
     * Build (and cache for 4 hours) the wpstream_category term list for shortcodes.
     *
     * @return array List of ['label' => name, 'value' => term_id] entries.
     */
    public function  wpstream_generate_woo_movie_category_values_shortcode(){

        // Parallel map of term_id => name, cached alongside the value list.
        $all_tax_labels=array();
        // Serve from cache when available; a false result means the cache is cold.
        $property_action_category_values = get_transient('wpstream_woo_movie_category_values');
        if($property_action_category_values===false){
            $property_action_category_values=array();
            // Fetch every term (including empty ones) in the media-category taxonomy.
            $terms_category = get_terms( array(
                'taxonomy' => 'wpstream_category',
                'hide_empty' => false,
            ) );

            if( is_array($terms_category) ){
                // Convert each term into a label/value pair for the shortcode select.
                foreach($terms_category as $term){

                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_tax[]=$temp_array;
                    $action_array[]=$temp_array;
                    $all_tax_labels[$term->term_id]=  $term->name;
                    // tax based_array
                    $property_action_category_values[] = $temp_array;

                }
            }
            // Cache both the id=>name labels and the value list for 4 hours.
            set_transient('wpstream_woo_movie_category_values_label',$all_tax_labels,60*60*4);
            set_transient('wpstream_woo_movie_category_values',$property_action_category_values,60*60*4);
        }
        return $property_action_category_values;
    }
    
    
    
    
    /**
     * Build (and cache for 4 hours) the wpstream_actors term list for shortcodes.
     *
     * @return array List of ['label' => name, 'value' => term_id] entries.
     */
    public  function wpstream_generate_actors_category_values_shortcode(){
        // Parallel map of term_id => name, cached alongside the value list.
        $all_tax_labels=array();
        // Serve from cache when available; false means rebuild.
        $movie_actors_values = get_transient('wpstream_woo_actors_category_values');

        if($movie_actors_values===false){
            $movie_actors_values=array();
            // Fetch every actor term, including empty ones.
            $terms_actors= get_terms( array(
                'taxonomy' => 'wpstream_actors',
                'hide_empty' => false,
            ) );


            if( is_array($terms_actors) ){
                // Convert each term into a label/value pair for the shortcode select.
                foreach($terms_actors as $term){
                    $places[$term->name]= $term->term_id;
                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_tax[]=$temp_array;

                    $all_tax_labels[$term->term_id]=  $term->name;
                    $movie_actors_values[] = $temp_array;
                }
            }


            // Cache both the id=>name labels and the value list for 4 hours.
            set_transient('wpstream_woo_actors_category_values_label',$all_tax_labels,60*60*4);
            set_transient('wpstream_woo_actors_category_values',$movie_actors_values,60*60*4);
        }
        return $movie_actors_values;
    }
    
    
    /**
     * Build (and cache for 4 hours) the WooCommerce product_cat term list for shortcodes.
     *
     * @return array List of ['label' => name, 'value' => term_id] entries.
     */
    public function wpstream_generate_woo_product_tax_values_shortcode(){
        // Parallel map of term_id => name, cached alongside the value list.
        $all_tax_labels=array();
        // Serve from cache when available; false means rebuild.
        $product_categ_values = get_transient('wpstream_woo_product_cat');

        if($product_categ_values===false){
            // Fetch every WooCommerce product category, including empty ones.
            $product_cat= get_terms( array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ) );
            $product_categ_values=array();
            if( is_array($product_cat) ){
                // Convert each term into a label/value pair for the shortcode select.
                foreach($product_cat as $term){
                    $places[$term->name]= $term->term_id;
                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_places[]=$temp_array;
                    $area_array[]=$temp_array;
                    $all_tax[]=$temp_array;
                    $all_tax_labels[$term->term_id]=  $term->name;
                    // tax based_array
                    $product_categ_values[] = $temp_array;

                }
            }
            // Cache both the id=>name labels and the value list for 4 hours.
            set_transient('wpstream_woo_product_cat_label',$all_tax_labels,60*60*4);
            set_transient('wpstream_woo_product_cat',$product_categ_values,60*60*4);
        }

        return $product_categ_values;
    }


    /**
     * Build (and cache for 4 hours) the wpstream_movie_rating term list for shortcodes.
     *
     * @return array List of ['label' => name, 'value' => term_id] entries.
     */
    public function wpstream_generate_movie_rating_category_values_shortcode(){
       // Parallel map of term_id => name, cached alongside the value list.
       $all_tax_labels=array();
        // Serve from cache when available; false means rebuild.
        $movie_rating_values = get_transient('wpstream_woo_movie_rating_category_values');
        if($movie_rating_values===false){
            // Fetch every movie-rating term, including empty ones.
            $movie_ratiog= get_terms( array(
                'taxonomy' => 'wpstream_movie_rating',
                'hide_empty' => false,
            ) );
            $movie_rating_values=array();
            if( is_array($movie_ratiog) ){
                // Convert each term into a label/value pair for the shortcode select.
                foreach($movie_ratiog as $term){
                    $places[$term->name]= $term->term_id;
                    $temp_array=array();
                    $temp_array['label'] = $term->name;
                    $temp_array['value'] = $term->term_id;
                    $all_places[]=$temp_array;
                    $area_array[]=$temp_array;
                    $all_tax[]=$temp_array;
                    $all_tax_labels[$term->term_id]=  $term->name;
                    // tax based_array
                    $movie_rating_values[] = $temp_array;

                }
            }
            // Cache both the id=>name labels and the value list for 4 hours.
            set_transient('wpstream_woo_movie_rating_category_values_label',$all_tax_labels,60*60*4);
            set_transient('wpstream_woo_movie_rating_category_values',$movie_rating_values,60*60*4);
        }

        return $movie_rating_values;

    }



    
    /**
    * Register taxonomy custom post type
    *
    * @link https://codex.wordpress.org/Function_Reference/register_taxonomy
    *
    * @param array $tax_fields Taxonomy definition (taxonomy, single/plural, post_types, rewrite, ...).
    */

    private function register_single_post_type_taxnonomy( $tax_fields ) {

        // Build the taxonomy admin labels from its single/plural names.
        $labels = array(
            'name'                       => $tax_fields['plural'],
            'singular_name'              => $tax_fields['single'],
            'menu_name'                  => $tax_fields['plural'],
            'all_items'                  => sprintf( __( 'All %s' , 'wpstream' ), $tax_fields['plural'] ),
            'edit_item'                  => sprintf( __( 'Edit %s' , 'wpstream' ), $tax_fields['single'] ),
            'view_item'                  => sprintf( __( 'View %s' , 'wpstream' ), $tax_fields['single'] ),
            'update_item'                => sprintf( __( 'Update %s' , 'wpstream' ), $tax_fields['single'] ),
            'add_new_item'               => sprintf( __( 'Add New %s' , 'wpstream' ), $tax_fields['single'] ),
            'new_item_name'              => sprintf( __( 'New %s Name' , 'wpstream' ), $tax_fields['single'] ),
            'parent_item'                => sprintf( __( 'Parent %s' , 'wpstream' ), $tax_fields['single'] ),
            'parent_item_colon'          => sprintf( __( 'Parent %s:' , 'wpstream' ), $tax_fields['single'] ),
            'search_items'               => sprintf( __( 'Search %s' , 'wpstream' ), $tax_fields['plural'] ),
            'popular_items'              => sprintf( __( 'Popular %s' , 'wpstream' ), $tax_fields['plural'] ),
            'separate_items_with_commas' => sprintf( __( 'Separate %s with commas' , 'wpstream' ), $tax_fields['plural'] ),
            'add_or_remove_items'        => sprintf( __( 'Add or remove %s' , 'wpstream' ), $tax_fields['plural'] ),
            'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s' , 'wpstream' ), $tax_fields['plural'] ),
            'not_found'                  => sprintf( __( 'No %s found' , 'wpstream' ), $tax_fields['plural'] ),
        );

        // Assemble register_taxonomy() args, defaulting anything the definition omits.
        $args = array(
        	'label'                 => $tax_fields['plural'],
        	'labels'                => $labels,
        	'hierarchical'          => ( isset( $tax_fields['hierarchical'] ) )          ? $tax_fields['hierarchical']          : true,
        	'public'                => ( isset( $tax_fields['public'] ) )                ? $tax_fields['public']                : true,
        	'show_ui'               => ( isset( $tax_fields['show_ui'] ) )               ? $tax_fields['show_ui']               : true,
        	'show_in_nav_menus'     => ( isset( $tax_fields['show_in_nav_menus'] ) )     ? $tax_fields['show_in_nav_menus']     : true,
        	'show_tagcloud'         => ( isset( $tax_fields['show_tagcloud'] ) )         ? $tax_fields['show_tagcloud']         : true,
        	'meta_box_cb'           => ( isset( $tax_fields['meta_box_cb'] ) )           ? $tax_fields['meta_box_cb']           : null,
        	'show_admin_column'     => ( isset( $tax_fields['show_admin_column'] ) )     ? $tax_fields['show_admin_column']     : true,
        	'show_in_quick_edit'    => ( isset( $tax_fields['show_in_quick_edit'] ) )    ? $tax_fields['show_in_quick_edit']    : true,
        	'update_count_callback' => ( isset( $tax_fields['update_count_callback'] ) ) ? $tax_fields['update_count_callback'] : '',
        	'show_in_rest'          => ( isset( $tax_fields['show_in_rest'] ) )          ? $tax_fields['show_in_rest']          : true,
        	'rest_base'             => $tax_fields['taxonomy'],
        	'rest_controller_class' => ( isset( $tax_fields['rest_controller_class'] ) ) ? $tax_fields['rest_controller_class'] : 'WP_REST_Terms_Controller',
        	'query_var'             => $tax_fields['taxonomy'],
        	'rewrite'               => ( isset( $tax_fields['rewrite'] ) )               ? $tax_fields['rewrite']               : true,
        	'sort'                  => ( isset( $tax_fields['sort'] ) )                  ? $tax_fields['sort']                  : '',
        );

        // Let integrators tweak the args via a per-taxonomy filter before registration.
        $args = apply_filters( $tax_fields['taxonomy'] . '_args', $args );

        // Register the taxonomy against its target post types.
        register_taxonomy( $tax_fields['taxonomy'], $tax_fields['post_types'], $args );

    }

    /**
     * Assign capabilities to users
     *
     * @link https://codex.wordpress.org/Function_Reference/register_post_type
     * @link https://typerocket.com/ultimate-guide-to-custom-post-types-in-wordpress/
     *
     * @param array $caps_map Map of WordPress cap key => custom capability string.
     * @param array $users    Role slugs to receive the custom capabilities.
     */
    public function assign_capabilities( $caps_map, $users  ) {

        // Walk each target role.
        foreach ( $users as $user ) {

            // Resolve the WP_Role object for this role slug.
            $user_role = get_role( $user );

            // Add every mapped custom capability to that role.
            foreach ( $caps_map as $cap_map_key => $capability ) {

                $user_role->add_cap( $capability );

            }

        }

    }

    /**
     * Render the extra term-meta fields on the Edit Term screen.
     *
     * CUSTOMIZE CUSTOM POST TYPE AS YOU WISH.
     *
     * @param object|string $tag      Term object being edited (or a string on add screens).
     * @param string        $taxonomy Taxonomy slug the term belongs to.
     */
    public   function wpstream_category_callback_function($tag, $taxonomy){
            // Editing an existing term: load its saved meta values.
            if(is_object ($tag)){
                $t_id                       =   $tag->term_id;
                $term_meta                  =   get_option( "taxonomy_$t_id");
                $pagetax                    =   $term_meta['pagetax'] ? $term_meta['pagetax'] : '';
                $category_featured_image    =   $term_meta['category_featured_image'] ? $term_meta['category_featured_image'] : '';
                $category_tagline           =   $term_meta['category_tagline'] ? $term_meta['category_tagline'] : '';
                $category_tagline           =   stripslashes($category_tagline);
                $category_attach_id         =   $term_meta['category_attach_id'] ? $term_meta['category_attach_id'] : '';
            }else{
                // No term object: start with empty defaults.
                $pagetax                    =   '';
                $category_featured_image    =   '';
                $category_tagline           =   '';
                $category_attach_id         =   '';
            }

            // Output the edit-screen form rows pre-filled with the values above.
            print'
            <table class="form-table">
            <tbody>    
                <tr class="form-field">
                    <th scope="row" valign="top"><label for="term_meta[pagetax]">'.esc_html__( 'Display the content for page id for this term','wpstream').'</label></th>
                    <td> 
                        <input type="text" name="term_meta[pagetax]" class="postform" value="'.$pagetax.'">  
                        <p class="description">'.esc_html__( 'Display the content for page id for this term','wpstream').'</p>
                    </td>

                    <tr valign="top">
                        <th scope="row"><label for="category_featured_image">'.esc_html__( 'Featured Image','wpstream').'</label></th>
                        <td>
                            <input id="category_featured_image" type="text" class="postform wpestate_landing_upload" size="36" name="term_meta[category_featured_image]" value="'.$category_featured_image.'" />
                            <input id="category_featured_image_button" type="button"  class="upload_button button category_featured_image_button" value="'.esc_html__( 'Upload Image','wpstream').'" />
                            <input id="category_attach_id"  class="wpestate_landing_upload_id" type="hidden" size="36" name="term_meta[category_attach_id]" value="'.$category_attach_id.'" />
                        </td>
                    </tr> 

                    <tr valign="top">
                        <th scope="row"><label for="term_meta[category_tagline]">'. esc_html__( 'Category Tagline','wpstream').'</label></th>
                        <td>
                            <input id="category_tagline" type="text" size="36" name="term_meta[category_tagline]" value="'.$category_tagline.'" />
                        </td>
                    </tr> 



                    <input id="category_tax" type="hidden" size="36" name="term_meta[category_tax]" value="'.$taxonomy.'" />


                </tr>
            </tbody>
            </table>';
    }

    
    
    
    
    
     /**
     * Render the extra term-meta fields on the Add New Term screen.
     *
     * CUSTOMIZE CUSTOM POST TYPE AS YOU WISH.
     *
     * @param object|string $tag Term object, or (on the add screen) the taxonomy string.
     */
    public function wpstream_category_callback_add_function($tag){
        // Editing an existing term: load its saved meta values.
        if(is_object ($tag)){
            $t_id                       =   $tag->term_id;
            $term_meta                  =   get_option( "taxonomy_$t_id");
            $pagetax                    =   $term_meta['pagetax'] ? $term_meta['pagetax'] : '';
            $category_featured_image    =   $term_meta['category_featured_image'] ? $term_meta['category_featured_image'] : '';
            $category_tagline           =   $term_meta['category_tagline'] ? $term_meta['category_tagline'] : '';
            $category_attach_id         =   $term_meta['category_attach_id'] ? $term_meta['category_attach_id'] : '';
        }else{
            // Add screen (no term yet): start with empty defaults.
            $pagetax                    =   '';
            $category_featured_image    =   '';
            $category_tagline           =   '';
            $category_attach_id         =   '';

        }

        // Output the add-screen form fields pre-filled with the values above.
        print'
        <div class="form-field">
        <label for="term_meta[pagetax]">'. esc_html__( 'Page id for this term','wpstream').'</label>
            <input type="text" name="term_meta[pagetax]" class="postform" value="'.$pagetax.'">  
        </div>

        <div class="form-field">
            <label for="term_meta[pagetax]">'. esc_html__( 'Featured Image','wpstream').'</label>
            <input id="category_featured_image" class="wpestate_landing_upload" type="text" size="36" name="term_meta[category_featured_image]" value="'.$category_featured_image.'" />
            <input id="category_featured_image_button" type="button"  class="upload_button button category_featured_image_button" value="'.esc_html__( 'Upload Image','wpstream').'" />
           <input id="category_attach_id" type="hidden" class="wpestate_landing_upload_id" size="36" name="term_meta[category_attach_id]" value="'.$category_attach_id.'" />

        </div>     

        <div class="form-field">
        <label for="term_meta[category_tagline]">'. esc_html__( 'Category Tagline','wpstream').'</label>
            <input id="category_tagline" type="text" size="36" name="term_meta[category_tagline]" value="'.$category_tagline.'" />
        </div> 
        <input id="category_tax" type="hidden" size="36" name="term_meta[category_tax]" value="'.$tag.'" />
        ';
    }

    /**
     * Persist the extra term-meta fields when a term is created or updated.
     *
     * CUSTOMIZE CUSTOM POST TYPE AS YOU WISH.
     *
     * @param int $term_id ID of the term being saved.
     */
    function wpstream_category_save_extra_fields_callback($term_id ){
        // Only act when the edit/add form actually submitted term_meta data.
        if ( isset( $_POST['term_meta'] ) ) {
            // Load any existing meta so unsubmitted keys are preserved.
            $t_id = $term_id;
            $term_meta = get_option( "taxonomy_$t_id");
            $cat_keys = array_keys($_POST['term_meta']);
            // Empty allowed-HTML list: values are stripped of all markup.
            $allowed_html   =   array();
                // Sanitize each submitted key/value before storing it.
                foreach ($cat_keys as $key){
                    $key=sanitize_key($key);
                    if (isset($_POST['term_meta'][$key])){
                        $term_meta[$key] =  wp_kses( $_POST['term_meta'][$key],$allowed_html);
                    }
                }
            //save the option array
             update_option( "taxonomy_$t_id", $term_meta );
        }
    }

    
    
    
   

    /**
     * Create post types
     */
    public function create_custom_post_type() {

        /**
         * This is not all the fields, only what I find important. Feel free to change this function ;)
         *
         * @link https://codex.wordpress.org/Function_Reference/register_post_type
         *
         * For more info on fields:
         * @link https://github.com/JoeSz/WordPress-Plugin-Boilerplate-Tutorial/blob/9fb56794bc1f8aebfe04e99b15881db0c4bc61bd/wpstream/includes/class-wpstream-post_types.php#L230
         */
        
        
        // Live-channel permalink base: admin-configured slug, or 'wpstream' when unset.
        $custom_slug =  esc_html( get_option('wpstream_free_media_slug','') );
        if($custom_slug==''){
            $custom_slug='wpstream';
        }

        // VOD permalink base: admin-configured slug, or 'wpstream_vod' when unset.
        $custom_slug_vod =  esc_html( get_option('wpstream_free_media_slug_vod','') );
        if($custom_slug_vod==''){
            $custom_slug_vod='wpstream_vod';
        }


        // First post type: Free-To-View Live Channel, with its three shared taxonomies.
        $post_types_fields = array(
            array(
                'slug'                  =>  'wpstream_product',
                'singular'              => __( 'Free-To-View Live Channel','wpstream'),
                'plural'                => __( 'Free-To-View Live Channels','wpstream'),
                'menu_name'             => __( 'Free-To-View Live Channels','wpstream'),
                'description'           => __( 'Free-To-View Live Channels','wpstream'),
                'has_archive'           => true,
                'hierarchical'          => false,
                'menu_icon'             => WPSTREAM_PLUGIN_DIR_URL.'img/wpstream-icon-menu_2.png',
                'rewrite'               => array(
                                            'slug'                  => $custom_slug,
                                            'with_front'            => true,
                                            'pages'                 => true,
                                            'feeds'                 => true,
                                            'ep_mask'               => EP_PERMALINK,
                                        ),
                'menu_position'         => 20,
                'public'                => true,
                'publicly_queryable'    => true,
                'exclude_from_search'   => false,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'query_var'             => true,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => true,
                'supports'              => array(
                    'title',
                    'editor',
                    'excerpt',
                    'thumbnail',
                    'comments',
                  
                ),
                'custom_caps'           => true,
                'custom_caps_users'     => array(
                    'administrator',
                ),
                'taxonomies'            => array(

                   
                    array(
                        'taxonomy'          => 'wpstream_actors',
                        'plural'            => esc_html__('Actors','wpstream'),
                        'single'            => esc_html__('Actor','wpstream'),
                        'post_types'        =>  array('wpstream_product','product','wpstream_product_vod','wpstream_bundles'),
                        'hierarchical'      => true,
                        'query_var'         => true,
                        'rewrite'           => array( 'slug' => 'actors' )
                    ),
                    
                    array(
                        'taxonomy'          => 'wpstream_category',
                        'plural'            => esc_html__('Media Categories','wpstream'),
                        'single'            => esc_html__('Media Category','wpstream'),
                        'post_types'        =>  array('wpstream_product','product','wpstream_product_vod','wpstream_bundles'),
                        'hierarchical'      => true,
                        'query_var'         => true,
                        'rewrite'           => array( 'slug' => 'media_category' )
                    ),
                    
                    array(
                        'taxonomy'          => 'wpstream_movie_rating',
                        'plural'            => esc_html__('Movie Ratings','wpstream'),
                        'single'            => esc_html__('Movie Rating','wpstream'),
                        'post_types'        =>  array('wpstream_product','product','wpstream_product_vod','wpstream_bundles'),
                        'hierarchical'      => true,
                        'query_var'         => true,
                        'rewrite'           => array( 'slug' => 'rating' )
                    ),

                ),
            ),
        );


        // Second post type: Free-To-View VOD (reuses the taxonomies registered above).
        $post_types_fields[] = array(

                'slug'                  =>  'wpstream_product_vod',
                'singular'              => __( 'Free-To-View VOD','wpstream'),
                'plural'                => __( 'Free-To-View VODs','wpstream'),
                'menu_name'             => __( 'Free-To-View VODs','wpstream'),
                'description'           => __( 'Free-To-View VODs','wpstream'),
                'has_archive'           => true,
                'hierarchical'          => false,
                'menu_icon'             => WPSTREAM_PLUGIN_DIR_URL.'img/wpstream-icon-menu_2.png',
                'rewrite'               => array(
                                            'slug'                  => $custom_slug_vod,
                                            'with_front'            => true,
                                            'pages'                 => true,
                                            'feeds'                 => true,
                                            'ep_mask'               => EP_PERMALINK,
                                        ),
                'menu_position'         => 20,
                'public'                => true,
                'publicly_queryable'    => true,
                'exclude_from_search'   => false,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'query_var'             => true,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => true,
                'supports'              => array(
                    'title',
                    'editor',
                    'excerpt',
                    'thumbnail',
                    'comments',
                  
                ),
                'custom_caps'           => true,
                'custom_caps_users'     => array(
                    'administrator',
                ),
               
        );

        // Third post type: Video Collection (bundles) — only when the theme customizer helper is present.
        if( function_exists('wpstream_custom_theme_customizer')){
            $post_types_fields[] = array(

                'slug'                  =>  'wpstream_bundles',
                'singular'              => __( 'Video Collection','wpstream'),
                'plural'                => __( 'Video Collections','wpstream'),
                'menu_name'             => __( 'Video Collections','wpstream'),
                'description'           => __( 'Video Collections','wpstream'),

                'labels'               => array(
					'name'                  => esc_html__( 'Video Collections', 'wpstream' ),
					'singular_name'         => esc_html__( 'Video Collection', 'wpstream' ),
					'add_new'               => esc_html__( 'Add New Video Collection', 'wpstream' ),
					'add_new_item'          => esc_html__( 'Add Video Collection', 'wpstream' ),
					'edit'                  => esc_html__( 'Edit', 'wpstream' ),
					'edit_item'             => esc_html__( 'Edit Video Collection', 'wpstream' ),
					'new_item'              => esc_html__( 'New Video Collection', 'wpstream' ),
					'view'                  => esc_html__( 'View', 'wpstream' ),
					'view_item'             => esc_html__( 'View Video Collection', 'wpstream' ),
					'search_items'          => esc_html__( 'Search Video Collection By Name or ID', 'wpstream' ),
					'not_found'             => esc_html__( 'No Video Collection found', 'wpstream' ),
					'not_found_in_trash'    => esc_html__( 'No Video Collection found in Trash', 'wpstream' ),
					'parent'                => esc_html__( 'Parent Video Collection', 'wpstream' ),
					'featured_image'        => esc_html__( 'Featured Image', 'wpstream' ),
					'set_featured_image'    => esc_html__( 'Set Featured Image', 'wpstream' ),
					'remove_featured_image' => esc_html__( 'Remove Featured Image', 'wpstream' ),
					'use_featured_image'    => esc_html__( 'Use Featured Image', 'wpstream' ),
				),
               
                'has_archive'           => true,
                'hierarchical'          => false,
                'menu_icon'             => WPSTREAM_PLUGIN_DIR_URL.'img/wpstream-icon-menu_2.png',
                'rewrite'              => array( 'slug' => 'wpstream_bundles' ),
                'menu_position'         => 20,
                'public'                => true,
                'publicly_queryable'    => true,
                'show_in_rest'         => true,
                'exclude_from_search'   => false,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'query_var'             => true,
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => true,
                'supports'              => array(
                    'title',
                    'editor',
                    'excerpt',
                    'thumbnail',
                    'comments',
                  
                ),
                'custom_caps'           => true,
                'custom_caps_users'     => array(
                    'administrator',
                ),
               
        );
        }

        
        // loop torugh custom post type array and register
        foreach ( $post_types_fields as $fields ) {
            $this->register_single_post_type( $fields );
        }

        // One-time 5.0 data migration: run once until the flag option is set.
        if( get_option('wpstream_updated_50')!=='yes' ){
            $this->wpstream_50_post_update();
        }


    }


    /**
     * One-time 5.0 migration: move VOD-typed legacy posts to the wpstream_product_vod type.
     */
    public function wpstream_50_post_update(){
        // Query every legacy wpstream_product post regardless of status.
        $arg=array(
            'post_type'     =>'wpstream_product',
            'post_status'   => 'any',
            'posts_per_page'=> -1
        );


        $the_query = new WP_Query($arg);

        if($the_query->have_posts()){
            // Inspect each post's stored product type.
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                $post_id=get_the_ID();
                $wpstream_product_type =    esc_html(get_post_meta($post_id, 'wpstream_product_type', true));

                // Types 2 and 3 were VOD entries: convert them to the dedicated VOD post type.
                if($wpstream_product_type==2 || $wpstream_product_type==3){
                    // print 'will update '.$post_id.' - '.get_the_title($post_id).'</br>'.PHP_EOL;
                    set_post_type(  $post_id, 'wpstream_product_vod' );
                }

            }
        // Rewrite rules changed with the moved posts, so flush them.
        global $wp_rewrite;
        $wp_rewrite->flush_rules( true );

        // Mark the migration complete so it never runs again.
        update_option('wpstream_updated_50','yes');
        }
    }
    
  
    
         
    /*
    *Remove the custom taxonomy column from the WooCommerce product list
     */
     /**
      * Drop the WpStream taxonomy columns from the WooCommerce products admin table.
      *
      * @param array $columns Existing column id => label map.
      * @return array Filtered columns without the WpStream taxonomy columns.
      */
     function remove_wpstream_category_column($columns) {
        // Only strip them when the media-category column is present (WooCommerce product list).
        if (isset($columns['taxonomy-wpstream_category'])) {
            unset($columns['taxonomy-wpstream_category']);
            unset($columns['taxonomy-wpstream_actors']);
            unset($columns['taxonomy-wpstream_movie_rating']);
        }
        return $columns;
    }
     
     

       
}
