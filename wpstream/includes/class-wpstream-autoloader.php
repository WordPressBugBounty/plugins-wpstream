<?php
/**
 * Classmap autoloader for the plugin's own classes and interfaces.
 *
 * Naming convention going forward: classes are prefixed `Wpstream_` and live
 * in files named `class-wpstream-{dashed-lowercase-name}.php`. The map below
 * is a static classmap rather than a name-to-path transform because several
 * files deliberately hold multiple declarations (a module's interfaces and
 * adapters live beside its main class) and a transform cannot express that.
 *
 * Adding a class to the plugin = declare it in its file + add one entry here.
 *
 * Deliberately NOT in the map (their existing require flows stay in charge):
 * - WC_Product_Live_Stream / WC_Product_Video_On_Demand — they extend
 *   WC_Product, so loading them while WooCommerce is inactive fatals at
 *   class-link time; they load behind class_exists('WooCommerce') guards.
 * - The Elementor widgets (ElementorWpStream\Widgets\*) — they extend
 *   Elementor\Widget_Base, so Wpstream_Elementor_Widgets (which IS mapped)
 *   requires them only after confirming Elementor is loaded.
 * - Wpstream_Seo and Wpstream_Storage_Migration — both register hooks at
 *   include time, so they must be eagerly required; lazy-loading them would
 *   silently drop those hook registrations.
 * - Function-only files (includes/Helpers/wpstream-*.php, the activation
 *   redirect, integrations/) — autoloaders only ever load class-likes.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Resolves the plugin's class and interface names to their defining files.
 */
final class Wpstream_Autoloader {

	/**
	 * Lowercased class/interface name => file path relative to the plugin root.
	 *
	 * Keys MUST be lowercase: PHP hands the autoloader the name exactly as
	 * typed at the call site, and class names are case-insensitive, so the
	 * lookup lowercases the incoming name before matching.
	 *
	 * @var array<string,string>
	 */
	const CLASSMAP = array(
		// Core orchestration.
		'wpstream'                       => 'includes/class-wpstream.php',
		'wpstream_loader'                => 'includes/class-wpstream-loader.php',
		'wpstream_activator'             => 'includes/class-wpstream-activator.php',
		'wpstream_elementor_widgets'     => 'includes/class-wpstream-elementor-widgets.php',
		'wpstream_deactivator'           => 'includes/class-wpstream-deactivator.php',
		'wpstream_admin'                 => 'admin/class-wpstream-admin.php',
		'wpstream_onboarding'            => 'admin/class-wpstream-onboarding.php',
		'wpstream_live_channel_presentation' => 'includes/class-wpstream-live-channel-presentation.php',
		'wpstream_public'                => 'public/class-wpstream-public.php',
		// Feature services.
		'wpstream_ajax'                  => 'includes/class-wpstream-ajax.php',
		'wpstream_chat_room_binding'     => 'includes/class-wpstream-chat-room-binding.php',
		'wpstream_live_api_connection'   => 'includes/class-wpstream-live-api-connection.php',
		'wpstream_player'                => 'includes/class-wpstream-player.php',
		'wpstream_quota_manager'         => 'includes/class-wpstream-quota-manager.php',
		'wpstream_template_loader'       => 'includes/class-wpstream-templates.php',
		'wpstream_theme_notice'          => 'includes/class-wpstream-theme-notice.php',
		'wpstream_product'               => 'includes/class-wpstream-product.php',
		'wpstream_playback_presentation' => 'includes/class-wpstream-playback-presentation.php',
		// Player helpers.
		'wpstream_playback_session'      => 'includes/player/class-wpstream-playback-session.php',
		'wpstream_channel_state'         => 'includes/player/class-wpstream-channel-state.php',
		// Baker API services.
		'wpstream_user_quota_service'    => 'includes/api/class-wpstream-user-quota-service.php',
		'wpstream_channel_service'       => 'includes/api/class-wpstream-channel-service.php',
		// Logging.
		'wpstream_log_entry'             => 'includes/Helpers/class-wpstream-log-entry.php',
		'wpstream_logger'                => 'includes/Logger/class-wpstream-logger.php',
		// Channel settings module (interfaces + adapters + main class share the file).
		'wpstream_channel_settings_storage_port'            => 'includes/class-wpstream-channel-settings.php',
		'wpstream_channel_settings_baker_port'              => 'includes/class-wpstream-channel-settings.php',
		'wpstream_wordpress_channel_settings_storage'       => 'includes/class-wpstream-channel-settings.php',
		'wpstream_baker_channel_settings_adapter'           => 'includes/class-wpstream-channel-settings.php',
		'wpstream_channel_settings'                         => 'includes/class-wpstream-channel-settings.php',
		// Streaming content creation module.
		'wpstream_streaming_content_provisioning_port'      => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_streaming_content_recording_catalog_port' => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_streaming_content_eligibility_port'       => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_wordpress_streaming_content_eligibility'  => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_streaming_content_creation_lock_port'     => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_wordpress_streaming_content_creation_lock' => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_baker_streaming_content_provisioning'     => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_baker_streaming_content_recording_catalog' => 'includes/class-wpstream-streaming-content-creation.php',
		'wpstream_streaming_content_creation'               => 'includes/class-wpstream-streaming-content-creation.php',
		// DRM key delivery module.
		'wpstream_drm_key_remote_fetch_port'                => 'includes/class-wpstream-drm-key-delivery.php',
		'wpstream_wordpress_drm_key_remote_fetch'           => 'includes/class-wpstream-drm-key-delivery.php',
		'wpstream_drm_key_delivery'                         => 'includes/class-wpstream-drm-key-delivery.php',
		// Media list module.
		'wpstream_media_list_active_catalog_port'           => 'includes/class-wpstream-media-list.php',
		'wpstream_baker_media_list_active_catalog'          => 'includes/class-wpstream-media-list.php',
		'wpstream_media_list'                               => 'includes/class-wpstream-media-list.php',
	);

	/**
	 * Register the autoloader with the spl autoload chain.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load the file that defines $class when the classmap knows it.
	 *
	 * Names outside the map are left for the next autoloader in the chain.
	 *
	 * @param string $class Class or interface name as typed at the call site.
	 * @return void
	 */
	public static function autoload( $class ) {
		// Lowercase the incoming name: class names are case-insensitive.
		$key = strtolower( $class );
		if ( isset( self::CLASSMAP[ $key ] ) ) {
			// The map is relative to the plugin root, one level above includes/.
			require_once dirname( __DIR__ ) . '/' . self::CLASSMAP[ $key ];
		}
	}
}
