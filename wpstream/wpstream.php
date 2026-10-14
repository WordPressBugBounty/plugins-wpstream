<?php
/**
 * Plugin Name:       WpStream - Live Streaming, Video on Demand, Pay Per View
 * Plugin URI:        http://wpstream.net
 * Description:       WpStream is a platform that allows you to live stream, create Video-on-Demand, and offer Pay-Per-View videos. We provide an affordable and user-friendly way for businesses, non-profits, and public institutions to broadcast their content and monetize their work. 
 * Version:           4.14.2
 * Author:            wpstream
 * Author URI:        http://wpstream.net
 * Text Domain:       wpstream
 * Domain Path:       /languages/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP:      7.1
 */

/**
 * Main plugin bootstrap file for WpStream.
 *
 * WordPress reads the header comment above to list the plugin. This file then
 * defines the plugin's global constants (version, remote API/player/click host
 * URLs, path helpers), wires activation/deactivation hooks, schedules a daily
 * log cleanup cron event, loads the core plugin class plus the Elementor
 * integration, conditionally loads the hello-wpstream theme
 * framework, registers the custom broadcaster-page rewrite endpoint, and finally
 * instantiates and runs the Wpstream class.
 *
 * @package    Wpstream
 */

// If this file is called directly, abort.
// WPINC is defined by WordPress core; its absence means direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}
// Current plugin version (kept in sync with the header "Version" field above).
define('WPSTREAM_PLUGIN_VERSION', '4.14.2');
// Base host for WpStream account/club links.
define('WPSTREAM_CLUBLINK', 'wpstream.net');
// Scheme used when building club links.
define('WPSTREAM_CLUBLINKSSL', 'https');
// URL to the site's plugins directory.
define('WPSTREAM_PLUGIN_URL',  plugins_url() );
// URL to this plugin's own directory.
define('WPSTREAM_PLUGIN_DIR_URL',  plugin_dir_url(__FILE__) );
// Filesystem path to this plugin's directory.
define('WPSTREAM_PLUGIN_PATH',  plugin_dir_path(__FILE__) );
// Plugin basename (folder/file) used for hook identification.
define('WPSTREAM_PLUGIN_BASE',  plugin_basename(__FILE__) );
// Remote WpStream API endpoint; guarded so it can be overridden before load.
if( !defined('WPSTREAM_API' ) ) {
    // Default API host.
    define('WPSTREAM_API', 'https://baker.wpstream.net');
}
// Telemetry/click-tracking host; guarded so it can be overridden.
if ( !defined( 'WPSTREAM_CLICK' ) ) {
	// Default click-tracking host.
	define( 'WPSTREAM_CLICK', 'https://click.wpstream.net' );
}
// Player host; guarded so it can be overridden.
if ( !defined( 'WPSTREAM_PLAYER') ) {
    // Default player host.
    define ( 'WPSTREAM_PLAYER', 'https://player.wpstream.net' );
}
// Prefix for live player URLs; guarded so it can be overridden.
if ( !defined( 'LIVE_PLAYER_URL_PREFIX' ) ) {
	// Default live player URL prefix.
	define('LIVE_PLAYER_URL_PREFIX', 'https://player.wpstream.net/player/live');
}


// Default network timeout (seconds) for remote API requests.
define('WPSTREAM_TIMEOUT_CONST', 20); // in seconds

// Register the classmap autoloader before anything references a plugin class,
// so activation hooks, cron callbacks, and the bootstrap below all resolve.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-autoloader.php';
Wpstream_Autoloader::register();

/**
 * Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
 *
 * All order access in the plugin goes through wc_get_orders() /
 * wc_customer_bought_product(), which are HPOS-safe, so the plugin can be
 * declared compatible with custom order tables. WooCommerce requires this
 * declaration to run inside the before_woocommerce_init hook.
 *
 * @return void
 */
function wpstream_declare_hpos_compatibility() {
	// Guard: FeaturesUtil only exists on WooCommerce 6.9+.
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
// WooCommerce fires this hook early on init; the declaration must happen there.
add_action( 'before_woocommerce_init', 'wpstream_declare_hpos_compatibility' );

/**
 * Cron callback that purges expired WpStream log entries.
 *
 * @return void
 */
function wpstream_cleanup_logs_handler() {
    // Instantiate the logger.
    $logger = new WpStream_Logger();
    // Delete log entries older than the retention window.
    $logger->clear_old_logs();
}
// Run the cleanup handler whenever the scheduled 'wpstream_log_cleanup' event fires.
add_action('wpstream_log_cleanup', 'wpstream_cleanup_logs_handler');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpstream-activator.php
 */
function activate_wpstream() {
	// Load the activator class on demand.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-activator.php';
	// Run the activation routine (creates DB tables, options, etc.).
	Wpstream_Activator::activate();

	// Schedule the daily log-cleanup cron event if it isn't already scheduled.
	if( !wp_next_scheduled( 'wpstream_log_cleanup' ) ) {
		// Register a daily recurring event starting now.
		wp_schedule_event( time(), 'daily', 'wpstream_log_cleanup' );
	}
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpstream-deactivator.php
 */
function deactivate_wpstream() {
	// Load the deactivator class on demand.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-deactivator.php';
	// Run the deactivation routine.
	Wpstream_Deactivator::deactivate();

	// Remove the scheduled daily log-cleanup cron event.
	wp_clear_scheduled_hook( 'wpstream_log_cleanup' );
}

// Register the activation/deactivation callbacks with WordPress.
register_activation_hook( __FILE__, 'activate_wpstream' );
register_deactivation_hook( __FILE__, 'deactivate_wpstream' );

/**
 * One-time cleanup of leftovers from the removed streamify HLS proxy.
 *
 * Older plugin versions shipped a /wpstreamify/ proxy that scheduled a
 * recurring cron event, registered a rewrite rule, and kept a cache directory
 * under uploads. Sites upgrading past its removal still carry all three, so
 * this clears them once and records a marker option so later loads skip it.
 *
 * @return void
 */
function wpstream_cleanup_streamify_leftovers() {
	// Already cleaned on this site — nothing to do.
	if ( get_option( 'wpstream_streamify_removed' ) ) {
		return;
	}

	// Remove the recurring proxy-cache cleanup event the old code scheduled.
	wp_clear_scheduled_hook( 'wpstreamify_cleanup_event' );

	// Regenerate the saved rewrite rules so the stale /wpstreamify/ route
	// (whose query var no longer exists) drops out of them.
	flush_rewrite_rules();

	// Delete the orphaned proxy cache directory and its segment files.
	$wpstream_streamify_cache_dir = WP_CONTENT_DIR . '/uploads/wpstreamify_cache/';
	if ( is_dir( $wpstream_streamify_cache_dir ) ) {
		$wpstream_streamify_files = glob( $wpstream_streamify_cache_dir . '*' );
		foreach ( is_array( $wpstream_streamify_files ) ? $wpstream_streamify_files : array() as $wpstream_streamify_file ) {
			if ( is_file( $wpstream_streamify_file ) ) {
				unlink( $wpstream_streamify_file );
			}
		}
		rmdir( $wpstream_streamify_cache_dir );
	}

	// Mark the cleanup done; autoloaded because it is checked on every load.
	update_option( 'wpstream_streamify_removed', 1 );
}
// Run late on init so every remaining rewrite registration precedes the flush.
add_action( 'init', 'wpstream_cleanup_streamify_leftovers', 99 );

/**
 * One-time rename of the VOD list Elementor widget key in stored page data.
 *
 * Older plugin versions registered the widget under 'class Wpstream_Media_List_Vod'
 * (a stray "class " prefix left in get_name()). The name is now the clean
 * 'Wpstream_Media_List_Vod', so any Elementor page saved with the old key would
 * render the widget as "missing". This rewrites the stored widgetType in every
 * _elementor_data row (pages and revisions alike), clears Elementor's generated
 * CSS so the wrapper class regenerates, and records a marker option so later
 * loads skip it.
 *
 * @return void
 */
function wpstream_fix_vod_widget_name() {
	// Already migrated on this site — nothing to do.
	if ( get_option( 'wpstream_vod_widget_name_fixed' ) ) {
		return;
	}

	global $wpdb;

	// Rewrite the exact quoted JSON pair so user-authored content can never match.
	$wpdb->query(
		"UPDATE {$wpdb->postmeta}
		 SET meta_value = REPLACE( meta_value,
		     '\"widgetType\":\"class Wpstream_Media_List_Vod\"',
		     '\"widgetType\":\"Wpstream_Media_List_Vod\"' )
		 WHERE meta_key = '_elementor_data'
		   AND meta_value LIKE '%class Wpstream_Media_List_Vod%'"
	);

	// Elementor caches per-post CSS containing the old wrapper class; regenerate it.
	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}

	// Mark the migration done; autoloaded because it is checked on every load.
	update_option( 'wpstream_vod_widget_name_fixed', 1 );
}
// Run on init so Elementor (loaded on plugins_loaded) is available for the cache clear.
add_action( 'init', 'wpstream_fix_vod_widget_name', 99 );

/**
 * Wrapper function for wpstream_dashboard_save_channel_data to be called from theme
 * This provides backward compatibility for the theme
 */
function wpstream_dashboard_save_channel_data_plugin() {
    // Reach the global plugin instance created at the bottom of this file.
    global $wpstream_plugin;
    // Only delegate if the plugin and its AJAX handler are available.
    if (isset($wpstream_plugin) && isset($wpstream_plugin->wpstream_ajax)) {
        // Forward the call to the real AJAX save-channel handler.
        $wpstream_plugin->wpstream_ajax->wpstream_dashboard_save_channel_data();
    }
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
// Load the core plugin class (defines i18n, admin, and public hooks).
require plugin_dir_path( __FILE__ ) . 'includes/class-wpstream.php';
// Migrate legacy option, transient and user-meta names once per site.
require plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-storage-migration.php';
// Elementor requirement gate + widget registrar (autoloaded; checks run on plugins_loaded).
new Wpstream_Elementor_Widgets();
// Load third-party integrations bootstrap.
require plugin_dir_path( __FILE__ ) . 'integrations/integrations.php';

/**
 * Load the bundled hello-wpstream theme framework files.
 *
 * Hooked to after_setup_theme so the theme's helper functions, post types,
 * WooCommerce glue, widgets, and (when Elementor is active) Elementor pieces are
 * available. Missing files are logged rather than fatal.
 *
 * @return void
 */
function wpstream_load_theme_functionality() {
	// Absolute path to the bundled hello-wpstream framework directory.
	define ('WPSTREAM_FRAMEWORK_BASE', plugin_dir_path(__FILE__) . 'hello-wpstream' );

	// Framework files to load, relative to WPSTREAM_FRAMEWORK_BASE.
	$core_files = array(
		'/framework/metaboxes.php',
        '/framework/query-functions.php',
		'/framework/wpstream-video-functions.php',
		'/framework/ajax-functions.php',
		'/framework/dashboard-functions.php',
        '/framework/wpstream-help-functions.php',
		'/framework/video-pages-functions.php',
		'/framework/comments-functions.php',
		'/framework/gallery-functions.php',
		'/framework/shortcodes-functions.php',
		'/framework/post-types/main.php',
		'/framework/woocommerce-functions.php',
		'/framework/ajax-upload.php',
		'/framework/email-functions.php',
		'/framework/widgets/class-wpstream-widget-manager.php',
		'/framework/classes/class-wpstream-login-register.php',
		'/framework/classes/class-wpstream_theme-social-login.php',
	);

	// Load core files
	// Include each framework file, or log it if the file is missing.
	foreach ( $core_files as $file ) {
		// Build the absolute path to this framework file.
		$file_path = WPSTREAM_FRAMEWORK_BASE . $file;
		// Include it if present; otherwise record the missing file.
		if ( file_exists( $file_path ) ) {
			// File exists: load it once.
			require_once $file_path;
		} else {
			// File missing: log an error but keep loading the rest.
			error_log( 'Missing required file: ' . $file_path );
		}
	}

	// Load Elementor integration if the plugin is active
	// ELEMENTOR_VERSION is only defined when Elementor is loaded.
	if ( defined( 'ELEMENTOR_VERSION' ) ) {

		// Additional framework files needed only for the Elementor integration.
		$elementor_files = array(
			'/elementor/functions/blog_functions.php',
			'/elementor/wpstream-elementor.php'
		);

		// Include each Elementor file, or log it if missing.
		foreach ( $elementor_files as $file ) {
			// Build the absolute path to this Elementor file.
			$file_path = WPSTREAM_FRAMEWORK_BASE . $file;
			// Include it if present; otherwise record the missing file.
			if ( file_exists( $file_path ) ) {
				// File exists: load it once.
				require_once $file_path;
			} else {
				// File missing: log an error but keep loading the rest.
				error_log( 'Missing required file: ' . $file_path );
			}
		}
	}
}

// Load the theme framework after the active theme is set up.
add_action( 'after_setup_theme', 'wpstream_load_theme_functionality' );

/**
 * Load js for players
 *
 * @return void
 */
// Guard the definition so the theme can define its own version without collision.
if ( ! function_exists( 'wpstream_enqueue_player_assets' ) ) {
	/**
	 * Enqueue the full player stack (scripts + styles) at a player render site.
	 *
	 * Every handle is registered (not enqueued) on wp_enqueue_scripts, so nothing
	 * loads on pages without a player; render sites call this the moment player
	 * markup is emitted. Scripts print in the footer; the two stylesheets print
	 * late as well, which is fine because the player element is built by JS.
	 * videojs-logo / quality-selector / quality-levels arrive as dependencies
	 * of wpstream-player.
	 *
	 * @return void
	 */
	function wpstream_enqueue_player_assets() {
		// Video.js core.
		wp_enqueue_script( 'video.min' );
		// WpStream player wrapper script (drags logo/quality deps with it).
		wp_enqueue_script( 'wpstream-player' );
		// Base Video.js CSS + the WpStream player skin.
		wp_enqueue_style( 'video-js.min' );
		wp_enqueue_style( 'videojs-wpstream-player' );
	}
}

if ( ! function_exists( 'wpstream_get_product_type_name' ) ) {
	/**
	 * Safely read a product's first product_type term name.
	 *
	 * wp_get_post_terms() returns a WP_Error when the taxonomy is unavailable
	 * and an empty array when the product carries no term; naive
	 * $term_list[0]->name reads crash on both under PHP 8. Callers that need the
	 * display label use this accessor; branching logic uses the slug accessor below.
	 *
	 * @param int $product_id Product/post id.
	 * @return string The first term name, or '' when none can be read.
	 */
	function wpstream_get_product_type_name( $product_id ) {
		$term_list = wp_get_post_terms( intval( $product_id ), 'product_type' );

		// WP_Error (missing taxonomy) or no assigned term: report "no type".
		if ( is_wp_error( $term_list ) || empty( $term_list[0]->name ) ) {
			return '';
		}

		return $term_list[0]->name;
	}
}

if ( ! function_exists( 'wpstream_get_product_type_slug' ) ) {
	/**
	 * Return the stable WooCommerce product-type identifier for a post.
	 *
	 * Product-type names are editable display labels, while the slug is the
	 * identifier used by WooCommerce and WpStream branching logic.
	 *
	 * @param int $product_id Product/post id.
	 * @return string The first term slug, or '' when none can be read.
	 */
	function wpstream_get_product_type_slug( $product_id ) {
		$term_list = wp_get_post_terms( intval( $product_id ), 'product_type' );

		if ( is_wp_error( $term_list ) || empty( $term_list[0]->slug ) ) {
			return '';
		}

		return $term_list[0]->slug;
	}
}

if ( ! function_exists( 'wpstream_load_player_js_on_demand' ) ) {
	/**
	 * Enqueue player scripts only when unit cards are configured to use video.
	 *
	 * @return void
	 */
	function wpstream_load_player_js_on_demand()
	{
		// Read the customizer setting that toggles video previews on unit cards.
		$wpstream_unit_card_use_video = get_theme_mod('wpstream_unit_card_use_video');
		// Only enqueue the player assets when that setting is enabled.
		if ($wpstream_unit_card_use_video) {
			// Full player stack (scripts + styles).
			wpstream_enqueue_player_assets();
		}

	}
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    3.0.1
 */

// Expose the plugin instance globally so themes/helpers can reach it.
global $wpstream_plugin;
// Build the core plugin object.
$wpstream_plugin = new Wpstream();
// Register all of the plugin's hooks and start it running.
$wpstream_plugin->run();

// After any core/plugin/theme update completes, run our upgrade cleanup.
add_action( 'upgrader_process_complete', 'wpstream_my_upgrade_function',10, 2);

/**
 * Clear cached API token data after this plugin is updated.
 *
 * @param WP_Upgrader $upgrader_object The upgrader instance (unused).
 * @param array       $options         Details about what was upgraded.
 * @return void
 */
function wpstream_my_upgrade_function( $upgrader_object, $options ) {


    // This plugin's basename, used to detect if it was among the updated plugins.
    $current_plugin_path_name = plugin_basename( __FILE__ );

    // Only act on plugin update operations.
    if ($options['action'] == 'update' && $options['type'] == 'plugin' ) {
        // Guard against a missing/non-array options payload.
        if(is_array($options)):
            // Inspect every plugin that was updated in this batch.
            foreach($options['plugins'] as $each_plugin) {
                // Match against this plugin.
                if ($each_plugin==$current_plugin_path_name) {
                    // Drop the cached API token transient.
                    delete_transient('wpstream_token_api');
                    // Reset the stored token expiry timestamp.
                    update_option('wpstream_token_expire',0);
                    // Blank out the stored current token.
                    update_option('wpstream_current_token',' ');

                }
            }
        endif;
    }


}



// Front-end SEO metadata (Open Graph, Twitter cards, structured data). The file
// registers its own wp_head hook when loaded — see Wpstream_Seo::init().
require plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-seo.php';


/*
*
* Check integrations
*
*/
// Only wire the One Click Demo Import (OCDI) hooks when the hello-wpstream theme is active.
if ( get_template() === 'hello-wpstream' ) {
	// Load the theme-import helper functions referenced by the filters below.
	require_once plugin_dir_path( __FILE__ ) . 'integrations/hello-wpstream/theme-import.php';
	// Register the set of demo import files offered by OCDI.
	add_filter( 'pt-ocdi/import_files', 'wpstream_ocdi_import_files' );
	// Run post-import setup (menus, pages, options) after a demo import.
	add_action( 'pt-ocdi/after_import', 'wpstream_ocdi_after_import_setup' );
	// Customize the intro text shown on the OCDI import screen.
	add_filter( 'pt-ocdi/plugin_intro_text', 'wpstream_ocdi_plugin_intro_text' );
	// Send telemetry when a demo content import is attempted.
	add_action( 'ocdi/before_content_import', 'wpstream_track_ocdi_import_attempt', 10, 1 );
}


// Once all plugins are loaded, evaluate which integrations should run.
add_action( 'plugins_loaded', 'wpstream_check_integrations' );


/*
*
* Redirect on plugin activation
*
*/

// Queue activation intent, then consume it safely on the next admin request.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpstream-activation-redirect.php';



/*
*
* remove the style  selectWoo for the theme  
*
*/
// The existence of wpstream_get_author_id signals the wpstream theme is active.
if ( function_exists( 'wpstream_get_author_id' ) ) {
    // only if wpstream-theme is activated

    remove_filter( 'pre_user_description', 'wp_filter_kses' ); // Removes the filter that applies KSES filtering for user descriptions
    remove_filter( 'term_description', 'wp_kses_data' ); // Removes the filter that applies KSES data filtering for term descriptions

    // Define the Select2 dequeue callback only once (guard against redefinition).
    if ( ! function_exists( 'wsis_dequeue_stylesandscripts_select2' ) ) {
        // Run the dequeue late (priority 100) so it overrides earlier enqueues.
        add_action( 'wp_enqueue_scripts', 'wsis_dequeue_stylesandscripts_select2', 100 );
        /**
         * Remove CSS and/or JS for Select2 used by WooCommerce
         */
        function wsis_dequeue_stylesandscripts_select2() {
            // Only relevant when WooCommerce is present.
            if ( class_exists( 'woocommerce' ) ) {
                // Remove WooCommerce's selectWoo stylesheet.
                wp_dequeue_style( 'selectWoo' );
                wp_deregister_style( 'selectWoo' );

                // Remove WooCommerce's selectWoo script.
                wp_dequeue_script( 'selectWoo' );
                wp_deregister_script( 'selectWoo' );
            }
        }
    }
}

/**
 * Register broadcaster page endpoint
 */
function wpstream_register_broadcaster_endpoint() {
    // Register the query var that flags a broadcaster page request (0 or 1).
    add_rewrite_tag('%broadcaster_page%', '([0-1]{1})');
    // Register the numeric channel-id query var.
    add_rewrite_tag('%channel_id%', '([0-9]+)');

    // Current stored rewrite rules, used to detect if our rule already exists.
    $rewrite_rules = get_option('rewrite_rules');
    // Pretty-URL pattern: /broadcaster-page/{id}/
    $rule_pattern = 'broadcaster-page/([0-9]+)/?$';
    // Internal target that maps the captured id onto our query vars.
    $rule_target = 'index.php?broadcaster_page=1&channel_id=$matches[1]';

    // Add the rewrite rule at the top so it takes precedence.
    add_rewrite_rule($rule_pattern, $rule_target, 'top');
    // Flush rules only when they are missing our pattern (avoids flushing every request).
    if ( !is_array($rewrite_rules) || !key_exists( $rule_pattern, $rewrite_rules ) ) {
        // Rebuild the rewrite rules so the new endpoint works immediately.
        flush_rewrite_rules();
    }
}
// Register the broadcaster endpoint on every init.
add_action('init', 'wpstream_register_broadcaster_endpoint');

/**
 * Load broadcaster template when the custom endpoint is accessed
 */
function wpstream_load_broadcaster_template($template) {
	// When the broadcaster_page query var is set, serve our custom template.
	if (get_query_var('broadcaster_page') == 1) {
		// Return the path to our broadcaster template
		return plugin_dir_path(__FILE__) . 'templates/broadcaster-template.php';
	}
	// Otherwise fall back to the theme's normal template.
	return $template;
}
// Intercept template selection to swap in the broadcaster template when needed.
add_filter('template_include', 'wpstream_load_broadcaster_template');

/**
 * Flush rewrite rules on plugin activation to ensure our endpoint works
 */
function wpstream_flush_rewrite_rules() {
	// Register the endpoint's rewrite tags/rules first.
	wpstream_register_broadcaster_endpoint();
	// Then flush so the new rules are active immediately after activation.
	flush_rewrite_rules();
}
// Ensure rewrite rules are flushed when the plugin is activated.
register_activation_hook(__FILE__, 'wpstream_flush_rewrite_rules');
