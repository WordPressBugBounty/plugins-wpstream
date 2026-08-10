<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      3.0.1
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream {

        /**
        * Store plugin main class to allow public access.
        *
        * @since             3.0.1
        * @var object      The main class.
        */
        public $main;
        /** @var Wpstream_Admin The admin-area controller instance. */
        public $admin;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      Wpstream_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    3.0.1
	 */
        
        /** @var Wpstream_Live_Api_Connection Cloud API client for channels/quota. */
        public $wpstream_live_connection;
        /** @var Wpstream_Player Player/rendering service. */
        public $wpstream_player;
        /** @var Wpstream_Quota_Manager Quota cache manager. */
        public $quota_manager;
        /** @var object User quota service resolved from the live connection. */
        public $user_quota_service;
        /** @var mixed Unused/reserved property. */
        public $xtest;
        /** @var Wpstream_Admin Admin controller (also stored in $admin). */
        public $plugin_admin;

	/**
	 * Bootstrap the plugin: resolve version/name, load dependencies, then wire
	 * all admin, public, AJAX, template, and service objects together.
	 */
	public function __construct() {
		// Expose this instance as the shared "main" service locator.
		$this->main = $this;

		// Resolve the plugin version from the defined constant, else fall back.
		if ( defined( 'WPSTREAM_PLUGIN_VERSION' ) ) {
            $this->version = WPSTREAM_PLUGIN_VERSION;
		} else {
            $this->version = '3.0.1';
		}

		// Text-domain / unique plugin identifier.
		$this->plugin_name = 'wpstream';

		// Require class files and instantiate the hook loader.
		$this->load_dependencies();
		// Register admin-area hooks (menus, metaboxes, WooCommerce, onboarding).
		$this->define_admin_hooks();
		// Register public-facing hooks (scripts, endpoints, shortcodes).
		$this->define_public_hooks();
        // Register AJAX handlers.
        $this->define_ajax_hooks();
		// Register the front-end page/single template loader.
		$this->wpstream_load_page_templates();
        // Register the "theme update available" admin notice.
        $this->wpstream_load_theme_notice();

        // Instantiate the cloud API connection (and user quota service).
        $this->wpstream_conection();
        // Instantiate the player service.
        $this->wpstream_player();
        // Instantiate the quota cache manager.
        $this->wpstream_init_quota_manager();

	}





        /**
         * Convert a bandwidth figure from megabits to gigabits.
         *
         * @param float|int $megabits Value in megabits.
         * @return float Value in gigabits, rounded to one decimal.
         */
        public function wpstream_convert_band($megabits){
            // 1 gigabit = 1000 megabits.
            $gigabit = $megabits * 0.001;
            // Return with a single decimal place.
            return floatval( sprintf( '%.1f', $gigabit ) );
        }

        /**
         * Floor a number to a given number of decimal places.
         *
         * @param float $value    The number to floor.
         * @param int   $decimals Number of decimal places.
         * @return float
         */
        public function wpstream_floor_decimals( $value, $decimals = 2 ) {
            // Clamp decimals to a non-negative integer.
            $decimals = max( 0, (int) $decimals );
            // Scale factor for the requested precision.
            $factor   = pow( 10, $decimals );

            // Multiply, floor, divide back, then format to fixed precision.
            return floatval( sprintf( '%.' . $decimals . 'f', floor( (float) $value * $factor ) / $factor ) );
        }


        /** @var WpStream_Ajax The AJAX handler service. */
        public $wpstream_ajax;

        /**
         * Load and instantiate the AJAX handler service.
         */
        private function define_ajax_hooks() {
            // Pull in the AJAX class and construct it with the main instance.
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wpstream-ajax.php';
            $this->wpstream_ajax = new WpStream_Ajax( $this->main );
        }


        /**
         * Load the cloud API connection and resolve the user quota service.
         */
        private function wpstream_conection(){
            // Pull in and instantiate the live/cloud API client.
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-live-api-connection.php';
            $this->wpstream_live_connection = new Wpstream_Live_Api_Connection();
            // Cache the user quota service exposed by the connection.
            $this->user_quota_service = $this->wpstream_live_connection->get_user_quota_service();
        }


        /**
         * Load and instantiate the player service.
         */
        private function wpstream_player(){
            // Pull in the player class and build it with the main instance.
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-player.php';
            $this->wpstream_player = new Wpstream_Player($this->main);
        }

        /**
         * Load and instantiate the quota cache manager.
         */
        private function wpstream_init_quota_manager() {
            // Pull in the quota manager and build it with this main instance.
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-quota-manager.php';
            $this->quota_manager = new Wpstream_Quota_Manager( $this );
        }


        /**
         * Load and register the front-end template loader.
         */
        private function wpstream_load_page_templates() {
	        // Pull in the loader and register its hooks by constructing it.
	        require_once WPSTREAM_PLUGIN_PATH . 'includes/class-wpstream-templates.php';
	        new WpStream_Template_Loader();
        }

        /**
         * Load and register the companion-theme update admin notice.
         */
        private function wpstream_load_theme_notice() {
            // Pull in the notice class and register it by constructing it.
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-theme-notice.php';
            new WPStream_Theme_Notice();
        }
        
        
	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wpstream_Loader. Orchestrates the hooks of the plugin.
	 * - Wpstream_i18n. Defines internationalization functionality.
	 * - Wpstream_Admin. Defines all hooks for the admin area.
	 * - Wpstream_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wpstream-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wpstream-public.php';
		/**
		 * The class responsible for custom post type

		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream_product.php';
		// WooCommerce product-type classes are only needed when WooCommerce is active.
		if(  class_exists( 'WooCommerce' ) ){
			// Paid live-stream product type.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc_product_live_stream.php';
			// Paid video-on-demand product type.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc_product_video_on_demand.php';
		}

		// Channel-ownership authorization helper (wpstream_can_manage_channel),
		// used by every live/broadcast handler to enforce per-channel ownership.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-authorization.php';

		// Logging helper classes used across the plugin.
		require_once plugin_dir_path(__FILE__) . 'Helpers/class-wpstream-log-entry.php';
		require_once plugin_dir_path(__FILE__) . 'Logger/class-wpstream-logger.php';

		// Create the hook loader that other define_*_hooks() methods populate.
		$this->loader = new Wpstream_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wpstream_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function set_locale() {

		// Internationalization helper responsible for loading the text domain.
		$plugin_i18n = new Wpstream_i18n();

		// Load the translations early on plugins_loaded.
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain', 1 );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function define_admin_hooks() {

                // Build the admin controller and keep a reference to it.
                $plugin_admin = new Wpstream_Admin( $this->get_plugin_name(), $this->get_version(), $this->main );

		        $this->admin  = $plugin_admin;

			    // Admin CSS/JS and the plugin's admin menu (late priority).
			    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin,  'enqueue_styles' );
		        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin,  'enqueue_scripts' );
                $this->loader->add_action( 'admin_menu',            $plugin_admin,  'wpstream_manage_admin_menu',999);

                // Register the custom post types on init.
                $plugin_post_types = new Wpstream_Product();
                $this->loader->add_action( 'init', $plugin_post_types, 'create_custom_post_type', 999 );

                // save and render metaboxed
                // Register product metaboxes; persist their values on save; and,
                // on publish, create the remote streaming channel.
                $this->loader->add_action( 'add_meta_boxes',    $plugin_admin, 'add_wpstream_product_metaboxes' );
                $this->loader->add_action( 'save_post',     $plugin_admin, 'wpstream_free_product_update_post',1,2 );
                $this->loader->add_action( 'publish_wpstream_product',     $plugin_admin, 'wpstream_publish_wpstream_product',1,2 );
                $this->loader->add_action( 'publish_wpstream_product',     $plugin_admin, 'wpstream_create_remote_channel_on_publish',20,2 );
		        $this->loader->add_action( 'publish_product', $plugin_admin, 'wpstream_create_remote_channel_on_publish', 20, 2 );



                // make product virtual
                $this->loader->add_action( 'save_post',  $plugin_admin, 'wpstream_make_product_virtual',  99999, 2 );



                // show streaming controls on sidebar
                $this->loader->add_action('add_meta_boxes', $plugin_admin, 'wpstream_startstreaming_sidebar_meta');

                // on boarding actions
                // Onboarding wizard markup plus its AJAX channel/VOD/login/register endpoints.
                $this->loader->add_action('admin_footer',$plugin_admin, 'wpstream_admin_footer_onboarding');
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_create_channel',  $plugin_admin,'wpstream_on_board_create_channel' );
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_create_channel_ppv',  $plugin_admin,'wpstream_on_board_create_channel_ppv' );
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_create_free_vod',  $plugin_admin,'wpstream_on_board_create_free_vod' );
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_create_ppv_vod',  $plugin_admin,'wpstream_on_board_create_ppv_vod' );


                // Onboarding login/registration plus the captcha challenge (logged-in and guest).
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_login',  $plugin_admin,'wpstream_on_board_login' );
                $this->loader->add_action( 'wp_ajax_wpstream_on_board_register',  $plugin_admin,'wpstream_on_board_register' );
				$this->loader->add_action( 'wp_ajax_wpstream_get_captcha_challenge', $plugin_admin, 'wpstream_get_captcha_challenge' );
				$this->loader->add_action( 'wp_ajax_nopriv_wpstream_get_captcha_challenge', $plugin_admin, 'wpstream_get_captcha_challenge' );
                
                // Register AJAX actions for multipart uploads
                $this->loader->add_action( 'wp_ajax_wpstream_initiate_multipart_upload', $plugin_admin, 'handle_initiate_multipart_upload' );
                $this->loader->add_action( 'wp_ajax_wpstream_complete_multipart_upload', $plugin_admin, 'handle_complete_multipart_upload' );

                // General and plugin-update admin notices.
                $this->loader->add_action( 'admin_notices',                             $plugin_admin,'wpstream_admin_notice' );
                $this->loader->add_action( 'admin_notices',      $plugin_admin,'wpstream_plugin_update_available_notice' );

                // Dismiss-cache-notice AJAX endpoint.
                $this->loader->add_action( 'wp_ajax_wpstream_update_cache_notice',      $plugin_admin,'wpstream_update_cache_notice' );
//		        $this->loader->add_action( 'wp_ajax_wpstream_get_videos_list',  $plugin_admin,'wpstream_get_videos_list' );


        // Settings-tab "update plugin" AJAX endpoint.
        $this->loader->add_action( 'wp_ajax_wpstream_settings_tab_update_plugin', $plugin_admin, 'wpstream_settings_tab_update_plugin' );

                // add and save category extra fields
                // The same add/edit/create/save callbacks are shared across every taxonomy below.
                $this->loader->add_action( 'category_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'category_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_category',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_category',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);

                // Same extra fields on the WooCommerce product category taxonomy.
                $this->loader->add_action( 'product_cat_edit_form_fields',  $plugin_post_types,  'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'product_cat_add_form_fields',   $plugin_post_types,  'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_product_cat',           $plugin_post_types,  'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_product_cat',            $plugin_post_types,  'wpstream_category_save_extra_fields_callback', 10, 2);


                // Same extra fields on the plugin's own wpstream_category taxonomy.
                $this->loader->add_action( 'wpstream_category_edit_form_fields', $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_category_add_form_fields',  $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_category',          $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_category',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);


                // Same extra fields on the wpstream_actors taxonomy.
                $this->loader->add_action( 'wpstream_actors_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_actors_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_actors',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_actors',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);

                // Same extra fields on the wpstream_movie_rating taxonomy.
                $this->loader->add_action( 'wpstream_movie_rating_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_movie_rating_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_movie_rating',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_movie_rating',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
          
                       
                // WooCommerce-only admin hooks: register the custom product types,
                // their data tabs/fields, pricing visibility, and add-to-cart handling.
                if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

                    // Register product types and map them to their PHP classes.
                    $this->loader->add_action( 'init',                          $plugin_admin, 'wpstream_add_custom_wc_products' );
                    $this->loader->add_filter( 'product_type_selector',         $plugin_admin, 'wpstream_add_products' );
                    $this->loader->add_filter( 'woocommerce_product_class',     $plugin_admin, 'wpstream_add_products_class', 10, 2 );
                    // Pricing-field visibility, tab hiding, and purchasability rules.
                    $this->loader->add_action( 'admin_enqueue_scripts',         $plugin_admin, 'wpstream_enqueue_wc_product_pricing_visibility', 20 );
                    $this->loader->add_filter( 'woocommerce_product_data_tabs', $plugin_admin, 'wpstream_hide_attributes_data_panel',10,1 );
                    $this->loader->add_filter( 'woocommerce_is_purchasable',    $plugin_admin, 'wpstream_hide_buy_now_subscription_mode',10,2);

                    // Custom general-tab fields (render + save) and add-to-cart wiring.
                    $this->loader->add_action( 'woocommerce_product_options_general_product_data', $plugin_admin, 'wpstream_add_custom_general_fields', 20 );
                    $this->loader->add_filter( 'woocommerce_process_product_meta',$plugin_admin, 'wpstream_add_custom_general_fields_save',10,1 );
                    $this->loader->add_action( 'woocommerce_live_stream_add_to_cart', $plugin_admin, 'wpstream_add_to_cart',10,1);
                    $this->loader->add_action( 'woocommerce_video_on_demand_add_to_cart', $plugin_admin, 'wpstream_add_to_cart',10,1);
                    $this->loader->add_filter( 'woocommerce_loop_add_to_cart_link', $plugin_admin,'replacing_add_to_cart_button', 10, 2 );
                }
	}





	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function define_public_hooks() {

		// Build the public-facing controller.
		$plugin_public = new Wpstream_Public( $this->get_plugin_name(), $this->get_version(), $this->main );

		// Front-end styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// Registered on `wp` (not `wp_enqueue_scripts`/`wp_head`) because block themes render
		// the Post Content block - and thus our `the_content` filter that enqueues/localizes
		// these scripts - before `wp_head` ever fires, leaving the handles unregistered.
		$this->loader->add_action( 'wp', $plugin_public, 'enqueue_scripts' );

		// Custom rewrite endpoints and their query vars.
		$this->loader->add_action( 'init', $plugin_public,'wpstream_my_custom_endpoints' );
		$this->loader->add_filter( 'query_vars',$plugin_public, 'wpstream_my_custom_query_vars', 0 );

		//live stream action
		// Streaming cookies plus the RTMP/3rd-party/VOD streaming-key handlers.
		$this->loader->add_action('init',$plugin_public,'wpstream_set_cookies',0);
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key');
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key_for_3rdparty');
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key_vod',10);

		// woo action
		// Product-page content wrappers and order/email extras.
		$this->loader->add_action( 'woocommerce_before_single_product', $plugin_public,'wpstream_non_image_content_wrapper_start', 20 );
		$this->loader->add_action( 'woocommerce_after_single_product', $plugin_public,'wpstream_non_image_content_wrapper_end', 20 );
		$this->loader->add_action( 'woocommerce_thankyou_order_received_text', $plugin_public,'wpstream_thankyou_extra', 20,2 );
		$this->loader->add_action( 'woocommerce_email_order_details', $plugin_public,'wpstream_email_order_details', 20,4 );

		// My Account: add the Events/Videos menu items and their endpoint content.
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public,'wpstream_custom_my_account_menu_items' );
		$this->loader->add_action( 'woocommerce_account_event-list_endpoint', $plugin_public,'wpstream_custom_endpoint_content_event_list' );
		$this->loader->add_action( 'woocommerce_account_video-list_endpoint', $plugin_public,'wpstream_custom_endpoint_video_list' );

		// Flush rewrite rules on theme switch; register plugin and WPBakery shortcodes.
		$this->loader->add_action( 'after_switch_theme', $plugin_public,'wpstream_custom_flush_rewrite_rules' );
		$this->loader->add_action('init', $plugin_public,'wpstream_shortcodes');
		$this->loader->add_action('vc_before_init', $plugin_public,'wpstream_bakery_shortcodes');

		// CORS preflight check for the API (uses a plain function callback).
		$this->loader->add_action('wo_before_api', 'wpstream_cors_check_and_response',10,1);

		// Theme-integration filters/actions: search, sidebars, archives, and
		// author/episode/past-broadcast content resolution by post type.
		$this->loader->add_filter( 'wpstream_search_template_item_post_type', $plugin_public, 'wpstream_search_template_add_item_post_type' );
		$this->loader->add_filter( 'wpstream_sidebar_id_by_post_type', $plugin_public, 'wpstream_sidebar_id_by_post_type' );
		$this->loader->add_filter( 'wpstream_header_search_values', $plugin_public, 'wpstream_header_search_values' );
		$this->loader->add_filter( 'wpstream_extend_category_archive_query_filter', $plugin_public, 'wpstream_extend_category_archive_query_filter_callback' );
		$this->loader->add_filter( 'wpstream_archives_lists_taxonomy_labels', $plugin_public, 'wpstream_archives_lists_taxonomy_labels_callback' );
		$this->loader->add_filter( 'wpstream_author_archive_list_taxonomy_labels', $plugin_public, 'wpstream_author_archive_list_taxonomy_labels_callback' );
		$this->loader->add_action( 'wpstream_vod_attached_to_channel', $plugin_public, 'wpstream_vod_attached_to_channel' );
		$this->loader->add_action( 'wpstream_additional_content_post_type', $plugin_public, 'wpstream_additional_content_post_type_callback' );
		$this->loader->add_action( 'wpstream_post_author_content_post_type_list', $plugin_public, 'wpstream_post_author_content_post_type_list_callback' );
		$this->loader->add_action( 'wpstream_author_content_simple_post_type_message', $plugin_public, 'wpstream_author_content_simple_post_type_message_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_author_content_post_type_message', $plugin_public, 'wpstream_author_content_post_type_message_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_show_sidebar_for_post_type', $plugin_public, 'wpstream_show_sidebar_for_post_type_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_video_episodes_post_type', $plugin_public, 'wpstream_video_episodes_post_type_callback' );
		$this->loader->add_action( 'wpstream_video_past_broadcast_post_type', $plugin_public, 'wpstream_video_past_broadcast_post_type_callback' );
		$this->loader->add_action( 'wpstream_additional_content_post_type_label', $plugin_public, 'wpstream_additional_content_post_type_label_callback', 10, 2 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    3.0.1
	 */
	public function run() {
		// Hand off to the loader, which calls add_action/add_filter for every hook.
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     3.0.1
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		// Return the stored text-domain / identifier string.
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     3.0.1
	 * @return    Wpstream_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		// Return the hook loader instance.
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     3.0.1
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
            // Return the resolved plugin version string.
            return $this->version;
	}

    /**
     * Whether WordPress currently reports a pending update for this plugin.
     *
     * @return bool True when an update is available in the update_plugins transient.
     */
    public function is_plugin_outdated(){
	    // WordPress caches pending plugin updates in this site transient.
	    $update_data = get_site_transient('update_plugins');
	    // The plugin's basename key within that transient.
	    $plugin_path = 'wpstream/wpstream.php';

	    // Presence of a response entry means an update is offered.
	    if (isset($update_data->response[$plugin_path])) {
		    return true;
	    }

	    // No entry -> up to date.
	    return false;
    }


      
        /**
         * Print the account resource summary (data/storage or hours) shown in the UI.
         *
         * @param array $pack_details Quota payload for the current account.
         */
        public function show_user_data($pack_details){
			// Branch A: data/storage (megabyte) plans - when NOT using streaming hours.
			if ( ! isset( $pack_details['use_streaming_hours'] ) || $pack_details['use_streaming_hours'] !== true ) {
				// Only render when both data and storage figures are present.
				if ( isset( $pack_details['available_data_mb'] ) && isset( $pack_details['available_storage_mb'] ) ) {
					// Convert available data MB to GB, clamping negatives to zero.
					$wpstream_convert_band = $this->wpstream_convert_band( $pack_details['available_data_mb'] );
					if ( $wpstream_convert_band < 0 ) {
						$wpstream_convert_band = 0;
					}

					// Convert available storage MB to GB, clamping negatives to zero.
					$wpstream_convert_storage = $this->wpstream_convert_band( $pack_details['available_storage_mb'] );
					if ( $wpstream_convert_storage < 0 ) {
						$wpstream_convert_storage = 0;
					}

					// Output the cloud data + storage summary line.
					print '<div class="pack_details_wrapper">'
						  . '<strong>' . __( 'Your account information: ', 'wpstream' ) . '</strong> '
						  . __( 'You have ', 'wpstream' ) . '<strong id="wpstream_available_data">' . abs( $wpstream_convert_band ) . ' GB</strong> '
						  . __( 'available cloud data and ', 'wpstream' )
						  . '<strong id="wpstream_available_storage">' . abs( $wpstream_convert_storage ) . ' GB</strong> '
						  . __( 'available cloud storage', 'wpstream' ) . '.';

					// Upgrade link plus hidden inputs carrying the raw MB figures for JS.
					print '<a href="https://wpstream.net/pricing/" class="wpstream_upgrade_topbar" target="_blank">' . esc_html__( 'Upgrade Plan', 'wpstream' ) . '</a>';
					print '</div>';
					print '<input type="hidden" id="wpstream_band" value="' . esc_attr( $pack_details['available_data_mb'] ) . '">';
					print '<input type="hidden" id="wpstream_storage" value="' . esc_attr( $pack_details['available_storage_mb'] ) . '">';
				}
			} else {
				// Branch B: streaming-hours plans (viewer/broadcast/storage hours).
				if ( isset( $pack_details['available_viewer_hours'] ) && isset( $pack_details['available_broadcast_hours'] ) && isset( $pack_details['available_storage_hours'] ) ) {
					// Normalize viewer hours to a non-negative float.
					$available_viewer_hours = floatval( $pack_details['available_viewer_hours'] );
					if ( $available_viewer_hours < 0 ) {
						$available_viewer_hours = 0;
					}

					// Normalize broadcast hours to a non-negative float.
					$available_broadcast_hours = floatval( $pack_details['available_broadcast_hours'] );
					if ( $available_broadcast_hours < 0 ) {
						$available_broadcast_hours = 0;
					}

					// Normalize storage hours to a non-negative float.
					$available_storage_hours = floatval( $pack_details['available_storage_hours'] );
					if ( $available_storage_hours < 0 ) {
						$available_storage_hours = 0;
					}

					// Floor each figure to two decimals for display.
					$formatted_viewer_hours    = $this->wpstream_floor_decimals( $available_viewer_hours, 2 );
					$formatted_broadcast_hours = $this->wpstream_floor_decimals( $available_broadcast_hours, 2 );
					$formatted_storage_hours   = $this->wpstream_floor_decimals( $available_storage_hours, 2 );

					// Output the viewer/broadcast/storage hours summary line.
					print '<div class="pack_details_wrapper">'
						  . __( 'Available streaming resources: ', 'wpstream' )
						  . '<strong id="wpstream_available_viewer_hours">' . abs( $formatted_viewer_hours ) . ' viewer</strong> '
						  . __( 'hours, ', 'wpstream' )
						  . '<strong id="wpstream_available_broadcast_hours">' . abs( $formatted_broadcast_hours ) . ' broadcast</strong> '
						  . __( 'hours, ', 'wpstream' )
						  . '<strong id="wpstream_available_storage_hours">' . $formatted_storage_hours . ' storage</strong> '
						  . __( 'hours', 'wpstream' ) . '.';

					// Upgrade link plus hidden inputs carrying the raw hour figures for JS.
					print '<a href="https://wpstream.net/pricing/" class="wpstream_upgrade_topbar" target="_blank">' . esc_html__( 'Upgrade Plan', 'wpstream' ) . '</a>';
					print '</div>';
					print '<input type="hidden" id="wpstream_viewer_hours" value="' . esc_attr( $pack_details['available_viewer_hours'] ) . '">';
					print '<input type="hidden" id="wpstream_broadcast_hours" value="' . esc_attr( $pack_details['available_broadcast_hours'] ) . '">';
					print '<input type="hidden" id="wpstream_storage_hours" value="' . esc_attr( $pack_details['available_storage_hours'] ) . '">';
				}
			}
		}

        
        /**
	 * help function for media list elementor widget
	 *
	 * Builds and renders a grid of media items (free CPTs or WooCommerce
	 * products) based on widget attributes, with ordering, pagination, an
	 * optional live-only filter, and a "no media found" fallback.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes (counts, type, order, labels).
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered HTML markup for the media grid.
	 */

        public function wpstream_media_list_elementor_function($attributes, $content = null){


                // Number of items to show (default 3).
                $media_number=3;
                if ( isset($attributes['media_number']) ){
                    $media_number=$attributes['media_number'];
                }


                // Number of columns per row (default 3, capped at 4).
                $row_number=3;
                if ( isset($attributes['row_number']) ){
                    $row_number=$attributes['row_number'];
                }
                if($row_number>4){
                    $row_number=4;
                }


                // Buffer all output so it can be returned as a string.
                ob_start();

                // check if is vod or live stream
                // Query scaffolding: meta and taxonomy query arrays.
                $meta_query     =   array();
                $tax_query_array=   array();
                $tax_query      =   array();

                // check if the media is paid or free
                // Decide which post types to query based on free/paid and live/VOD.
                $event_types            =   array();
                $product_type_free_paid =   0;
                if ( isset($attributes['product_type_free_paid']) ){
                    $product_type_free_paid=$attributes['product_type_free_paid'];

                    // Free path: plugin CPTs (live product, or VOD when product_type == 2).
                    if($product_type_free_paid==0){
                        $event_types=array('wpstream_product');

                        if ( isset($attributes['product_type']) && intval($attributes['product_type'])==2){
                            $event_types=array('wpstream_product_vod');
                        }

                    }else{
                        // Paid path: WooCommerce products, filtered by product_type taxonomy.
                        $event_types=array('product');
                        if ( isset($attributes['product_type']) ){
                            // Map the numeric product_type to a WooCommerce term slug.
                            $product_type       =   $attributes['product_type'];
                            $product_type_slug  =   'video_on_demand';
                            if($product_type==1){
                                $product_type_slug ='live_stream';
                            }

                            // Restrict the WP_Query to that product_type term.
                            $tax_query_array =  array(
                                            'taxonomy'     => 'product_type',
                                            'field'        => 'slug',
                                            'terms'        => $product_type_slug  
                                        );
                            
                            $tax_query= array(
                                        'relation'  => 'AND',
                                        $tax_query_array
                                    );
                         
                        }
                    }
                    
                }
                // Optional "see product" label rendered under each free item.
                $see_product='';
                if(isset($attributes['free_label'])){
                    $see_product=$attributes['free_label'];
                }

                // pagination
                // Current page number; the front page uses the 'page' query var instead.
                $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
                if( is_front_page() ){
                    $paged= (get_query_var('page')) ? get_query_var('page') : 1;
                }


                //order
                // Sort order: map the numeric order_by attribute to orderby/order.
                $orderby='ID';
                $order  ='ASC';
                if ( isset($attributes['order_by']) ){
                    $order_by=intval($attributes['order_by']);
                     switch ($order_by) {
                        case 0:
                            $orderby='ID';
                            $order  ='ASC';
                            break;
                        case 1:
                            $orderby='ID';
                            $order  ='DESC';
                            break;
                        case 2:
                            $orderby='title';
                            $order  ='ASC';
                            break;
                         case 3:
                            $orderby='title';
                            $order  ='DESC';
                            break;
                    }
                                     }
                
                // building wp_query arg array
                // Assemble the WP_Query arguments from the values computed above.
                $args = array(
                    'post_type'      => $event_types,
                    'post_status'    => 'publish',
                    'meta_query'     => $meta_query,
                    'posts_per_page' => $media_number,
                    'paged'          => $paged,
                    'orderby'        => $orderby,
                    'order'          => $order,
                    'tax_query'      =>  $tax_query
                );
                
             
             
                // show live events
                // Optional filter: restrict results to the user's currently-live channels.
                if ( isset($attributes['product_show_live']) && $attributes['product_show_live']=='yes'){
                    $live_event_for_user    =    $this->main->wpstream_live_connection->api20_wpstream_request_live_stream_for_user_for_shortcode('shortcode');


                    if( is_array($live_event_for_user) ){
                        // Append 0 so post__in is never empty (which would return all posts).
                        $live_event_for_user[]=0;

                        $args['post__in']=$live_event_for_user;
                    }
                }




                // Run the query and render either a grid or a "no media" message.
                $media_list= new WP_Query($args);
                if($media_list->have_posts()){
                    print '<ul class="wpstream_media_list_wrapper products columns-'.esc_attr($row_number).'" >';
                        // Loop over each matched post.
                        while($media_list->have_posts()):$media_list->the_post();
                            // Free items: render a custom card with thumbnail and title.
                            if($product_type_free_paid==0 ){

                                // Resolve the medium thumbnail, falling back to a default image.
                                $thumb=wp_get_attachment_image_src(get_post_thumbnail_id(),'medium');

                                $thumb_src='';
                                if( isset($thumb[0]) ){
                                    $thumb_src=$thumb[0];
                                }

                                if(($thumb_src)==''){
                                    $thumb_src= plugin_dir_url( dirname( __FILE__ ) ). 'img/default_300.png';
                                }
                                print '<li class="wpstream_product_unit">'
                                .'<a href="'.get_permalink().'" class="product_title" ><div class="product_image" style="background-image:url('.esc_url($thumb_src).')"></div></a>'
                                .'<a href="'.get_permalink().'" class="product_title" >'.get_the_title().'</a>';

                                // Optional "see product" call-to-action link.
                                if($see_product!=''){
                                    print '<a href="'.get_permalink().'"class="see_product">'.$see_product.'</a>';
                                }
                                print '</li>';
                            }else{
                                // Paid items: use WooCommerce's product content template part.
                                wc_get_template_part( 'content', 'product' );
                            }
                        endwhile;
                    print '</ul>';
                }else{
                    // No results for the current query/page.
                    print esc_html__('No media found! ','wpstream');
                }


                // Restore the global post/query state after the custom loop.
                wp_reset_query();
                wp_reset_postdata();

                // Append pagination controls beneath the grid.
                $this->wpstream_pagination($media_list->max_num_pages,$range=2);
                // Capture the buffered markup and return it.
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }
        
        
        
        
        
        
    /*
     *
     *
     * Pagination for media lista
     *
     *
     *
     */

    /**
     * Print a numbered pagination control for the media list.
     *
     * @param int|string $pages Total number of pages; falls back to the main query.
     * @param int        $range How many page links to show either side of the current page.
     */
    function wpstream_pagination($pages = '', $range = 2){

        // Total number of page links visible in the window.
        $showitems = ($range * 2)+1;
        // Current page from the global; default to page 1.
        global $paged;
        if(empty($paged)) $paged = 1;


        // Fall back to the main query's page count when none was passed in.
        if($pages == ''){
            global $wp_query;
            $pages = $wp_query->max_num_pages;
            if(!$pages)
            {
                $pages = 1;
            }
        }

        // Only render pagination when there is more than one page.
        if(1 != $pages){
            print '<ul class="wpstream_pagination ">';
            // Previous-page arrow.
            print '<li class="roundleft"><a href="'.get_pagenum_link($paged - 1).'"> < </a></li>';

            // Emit a link for each page within the visible window.
            for ($i=1; $i <= $pages; $i++)
            {
                // Show this page number only if inside the range window (or few pages total).
                if (1 != $pages &&( !($i >= $paged+$range+1 || $i <= $paged-$range-1) || $pages <= $showitems ))
                {
                    // Mark the current page as active.
                    if ($paged == $i){
                       print '<li class="active"><a href="'.esc_url(get_pagenum_link($i)).'" >'.$i.'</a><li>';
                    }else{
                       print '<li><a href="'.esc_url(get_pagenum_link($i)).'" >'.$i.'</a><li>';
                    }
                }
            }

            // Compute the next-page URL, clamping at the last page.
            $prev_page= get_pagenum_link($paged + 1);
            if ( ($paged +1) > $pages){
               $prev_page= get_pagenum_link($paged );
            }else{
                $prev_page= get_pagenum_link($paged + 1);
            }


            // Next-page arrow.
            print '<li class="roundright"><a href="'.$prev_page.'"> > </a><li>';
            print '</ul>';
        }
    }

        
        
        
        
        
        
        
        
        
        
        
        
        /**
	 * help function for player elementr widget
	 *
	 * Renders the standard video player for a given product id, or resolves the
	 * author's first channel when only a user_id is supplied.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes ('id', 'user_id').
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered player markup.
	 */

        public function wpstream_insert_player_elementor($attributes, $content = null){
                // Normalize incoming attributes with defaults.
                $product_id     =   '';
                $return_string  =   '';
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                        'user_id'                  => 0,
                    ), $attributes) ;


                // Explicit product id, if provided.
                if ( isset($attributes['id']) ){
                    $product_id=$attributes['id'];
                }
                // Optional author id used to look up a channel.
                if ( isset($attributes['user_id']) ){
                    $user_id = intval( $attributes['user_id'] );
                }

                // No product id but a user id: resolve that author's first channel.
                if(intval($product_id)==0 && $user_id!=0 ){
                    $product_id= $this->wpstream_player_retrive_first_id($user_id);
                }



                // Buffer the player output so it can be returned as a string.
                ob_start();
                // Render the player shortcode inside the wrapper markup below.
                ?>
                <div class="wpstream_insert_player_elementor_wrapper">
                    <?php
                    $this->main->wpstream_player->wpstream_video_player_shortcode($product_id);
                    ?>
                </div>
                <?php
                // Capture and return the buffered markup.
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }
        
          /**
	 * help function for player low latency elementor widget
	 *
	 * Same as the standard player helper but renders the low-latency player.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes ('id', 'user_id').
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered low-latency player markup.
	 */

        public function wpstream_insert_player__low_latency_elementor($attributes, $content = null){
                // Normalize incoming attributes with defaults.
                $product_id     =   '';
                $return_string  =   '';
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                         'user_id'                  => 0,
                    ), $attributes) ;


                // Explicit product id, if provided.
                if ( isset($attributes['id']) ){
                    $product_id=$attributes['id'];
                }

                // Optional author id used to look up a channel.
                if ( isset($attributes['user_id']) ){
                    $user_id = intval( $attributes['user_id'] );
                }

                // No product id but a user id: resolve that author's first channel.
                if(intval($product_id)==0 && $user_id!=0){
                    $product_id= $this->wpstream_player_retrive_first_id($user_id);
                }


                // Buffer the low-latency player output and return it.
                ob_start();
                $this->main->wpstream_player->wpstream_video_player_shortcode_low_latency($product_id);
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }



        /**
         * Resolve the first channel/event id belonging to a given author.
         *
         * @param string|int $received_user_id Author user id.
         * @return int|string Post id of the author's first channel, or 0 when none.
         */
        public function wpstream_player_retrive_first_id($received_user_id=''){
            // Free vs paid channel type is a site-wide option.
            $channel_type   =   get_option ('wpstream_user_streaming_channel_type');
            // Look up the author's most relevant event id for that type.
            $product_id     =   $this->wpstream_get_current_event_per_author($received_user_id,$channel_type);
            return $product_id;
        }
        
        
        
         /**
	 * help function for chat elementor widget
	 *
	 * Renders the chat widget for a product and wires up its client connection.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes ('id').
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered chat markup.
	 */

        public function wpstream_insert_chat_elementor($attributes, $content = null){
                // Normalize incoming attributes with defaults.
                $product_id     =   '';
                $return_string  =   '';
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                    ), $attributes) ;


                // Explicit product id, if provided.
                if ( isset($attributes['id']) ){
                    $product_id=$attributes['id'];
                }

                // Open the wrapper, buffer the chat markup, then close the wrapper.
                $return_string.= '<div class="wpstream_plugin_chat_wrapper">';
                ob_start();
                    $this->main->wpstream_player->wpstream_chat_wrapper($product_id);
                    $return_string.= ob_get_contents();
                ob_end_clean();
                $return_string.='</div>';
                // Emit the chat client connection script for this product.
                $this->main->wpstream_player->wpstream_connect_to_chat($product_id);

                return $return_string;
        }
        
        
        /**
        * edited 4.0 
        * 
        * Check if user is allowed to stream
        *
        * @since    3.7
        */
        
        public function wpstream_check_user_can_stream(){
            // Current user for the role/capability checks below.
            $current_user       =   wp_get_current_user();

            // Guests can never stream.
            if( !is_user_logged_in() ){
                return false;
                exit('user not logged in');
            }

            if(current_user_can('administrator')){
                // admins can always brodcast
                return true;
            }

            // Site-configured list of roles that are allowed to stream.
            $extra_roles    =   get_option( 'wpstream_stream_role', true );
            // The user's primary role (first in the roles array).
            $user_role = '';
            if( is_array( $current_user->roles) && count( $current_user->roles ) > 0 ){
                $user_role = $current_user->roles[0];
            }

            // Allow when the user's role is in the configured allow-list.
            if ( is_array($extra_roles) && in_array( $user_role, $extra_roles ) ) {
                return true;
            }

            // Allow when the "regular users may stream" option is enabled.
            if(function_exists('wpstream_get_option') && intval(wpstream_get_option('allow_streaming_regular_users',''))==1 ){
                return true;
            }

            // Otherwise deny.
            return false;
        }
        

         /**
        * Start Streaming wrapper
        *
        * Ensures a channel exists (creating one for front-end streamers when
        * needed), prints the error-modal scaffolding, then renders the start-
        * streaming unit.
        *
        * @since    3.7
        * @param int    $item_id Channel/product id, or 0 to auto-resolve.
        * @param string $type    Streaming unit type/context.
        */
        public function wpstream_live_stream_unit_wrapper($item_id,$type){
            // Coerce to an integer id.
            $item_id = intval($item_id);

            if($item_id == 0){
                //retrive or  create channel for front end streamers
                $item_id=$this->wpstream_retrive_front_end_channel();
            }
            // Modal backdrop and reusable error-notification markup.
            print'<div class="wpstream_modal_background"></div>';
            print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
            <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
            </div>';
            // Delegate the actual start-streaming UI to the admin controller.
            $this->admin->wpstream_live_stream_unit(  $item_id,$type );
        }
        
        
         /**
        * Start Streaming wrapper for wpstream theme
        *
        * Same as wpstream_live_stream_unit_wrapper() but renders the theme's
        * variant of the start-streaming unit.
        *
        * @since    3.7
        * @param int    $item_id Channel/product id, or 0 to auto-resolve.
        * @param string $type    Streaming unit type/context.
        */

        public function wpstream_live_stream_unit_wrapper_for_theme($item_id,$type){
            // Coerce to an integer id.
            $item_id = intval($item_id);

            if($item_id == 0){
                //retrive or  create channel for front end streamers
                $item_id=$this->wpstream_retrive_front_end_channel();
            }
            // Modal backdrop and reusable error-notification markup.
            print'<div class="wpstream_modal_background"></div>';
            print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
            <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
            </div>';
            // Delegate to the admin controller's theme-specific renderer.
            $this->admin->wpstream_live_stream_unit_for_theme(  $item_id,$type );
        }
        
        
        /**
        * retrive channel for front end streaming
        *
        * Returns the current user's front-end channel id, creating a new event
        * when the user does not yet have one.
        *
        * @since    3.7
        * @return int Channel/product id for the current user.
        */
        public function wpstream_retrive_front_end_channel(){

            // Current user plus the site-wide channel type and default price.
            $current_user   = wp_get_current_user();
            $channel_type   = get_option ('wpstream_user_streaming_channel_type');
            $channel_price  = floatval( get_option ('wpstream_user_streaming_default_price') );

            // Look for an existing channel owned by this user.
            $front_end_streamin_channel = $this->wpstream_get_current_event_per_author($current_user->ID,$channel_type);

            // None found: create one on the fly.
            if(intval($front_end_streamin_channel) == 0){
                $front_end_streamin_channel= $this->wpstrea_create_front_end_event($current_user->ID,$current_user->user_login ,$channel_type,$channel_price);
            }
            return $front_end_streamin_channel;

        }
          
        /**
        * create the event from front end
        *
        * Inserts a new channel post (free CPT or paid WooCommerce product),
        * setting price/terms for paid channels, and flags it as a live event.
        *
        * @since    3.7
        * @param int    $userID        Author user id.
        * @param string $userLogin     Author login, used in the channel title.
        * @param string $channel_type  'paid' for a WooCommerce product, else free.
        * @param float  $channel_price Price to set for paid channels.
        * @return int|void New post id, or void when the user may not stream.
        */

        public function wpstrea_create_front_end_event($userID,$userLogin,$channel_type,$channel_price){

           // Refuse to create a channel for users without streaming permission.
           if( !$this->wpstream_check_user_can_stream() ){
               return;
           }

            // Free channels use the plugin CPT; paid channels use WooCommerce products.
            $post_type='wpstream_product';
            if($channel_type=='paid'){
                $post_type='product';
            }

            // Build and insert the channel post.
            $post = array(
                'post_title'	=>  sprintf( esc_html__('%s Channel','wpstream'),$userLogin),
                'post_content'	=>  '',
                'post_type'     =>  $post_type ,
                'post_author'   =>  $userID,
                'post_status'   =>  'publish',
            );
            $post_id =  wp_insert_post($post );
            // For paid channels, configure the WooCommerce product pricing and type.
            if($channel_type=='paid'){
                $product    =   wc_get_product($post_id);
                $price      =   wc_format_decimal($channel_price);

                $product = wc_get_product( $post_id );
                // Set the product active price (regular)
                $product->set_price( $price );
                $product->set_regular_price( $price ); // To be sure
                $product->save();
                // Mark as not-yet-passed and tag it as a live_stream product.
                update_post_meta ($post_id,'event_passed',0);
                wp_set_object_terms( $post_id, 'live_stream', 'product_type' );
            }
            // Flag the post as a live event (product type 1).
            update_post_meta($post_id,'wpstream_product_type',1);

            return $post_id;
        }
        
        
        /**
        * Find the first channel/event id owned by a given author.
        *
        * @param int    $userID       Author user id.
        * @param string $channel_type 'paid' for a WooCommerce product, else free.
        * @return int Post id of the author's first matching channel, or 0.
        */
        public function wpstream_get_current_event_per_author($userID,$channel_type){

            // Free channels are CPTs; paid channels are WooCommerce products.
            $post_type='wpstream_product';
            if($channel_type=='paid'){
                $post_type='product';
            }

            // Query for a single post id owned by this author.
            $args = array(

                'post_type'         =>  $post_type,
                'author'            =>  $userID,
                'posts_per_page'    =>  1,
                'fields'            =>  'ids'
            )
                    ;
            $author_posts = new WP_Query( $args );
            // Use the found id, or 0 when the author has no channel.
            if( $author_posts->have_posts() ) {
                $author_posts->the_post();
                $the_id= get_the_ID();
            }else{
                 $the_id= 0;
            }

            // Restore global post/query state after the custom query.
            wp_reset_query();
            wp_reset_postdata();
            return $the_id;
        }

		/**
		 * Cleanup old logs
		 */
		public function cleanup_logs() {
			// Delegate to the logger, which prunes entries past its retention window.
			$logger = new WPStream_Logger();
			$logger->clear_old_logs();
		}
}
