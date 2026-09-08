<?php
/**
 * Shared Live Channel presentation for wp-admin and broadcaster surfaces.
 *
 * Broadcast lifecycle actions remain owned by the existing AJAX and cloud
 * connection modules. This module owns only presentation and its assets.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Presents Live Channels in wp-admin and on the shared go-live surface.
 */
class Wpstream_Live_Channel_Presentation {

	/** @var Wpstream Main plugin instance. */
	private $main;

	/**
	 * @param Wpstream $main Main plugin instance.
	 */
	public function __construct( $main ) {
		$this->main = $main;
	}

	/**
	 * Render one Live Channel control card.
	 *
	 * @param int    $post_id Live Channel post or product ID.
	 * @param string $context Rendering context: admin, front, or theme.
	 * @return void
	 */
	public function render_channel( $post_id, $context = 'admin' ) {
		$is_theme       = 'theme' === $context;
		$legacy_context = $is_theme ? 'front' : ( 'admin' === $context ? '' : $context );
		$this->render_live_channel( intval( $post_id ), $legacy_context, $is_theme );
	}

	/**
	 * Render the wp-admin All Channels screen.
	 *
	 * @return void
	 */
	public function render_channels_page() {
		if ( ! current_user_can( 'administrator' ) ) {
			esc_html_e( 'Only for administrators', 'wpstream' );
			return;
		}

		$this->wpstream_new_general_set();
	}

	/**
	 * Register Live Channel sidebar metaboxes for the current edit screen.
	 *
	 * @return void
	 */
	public function register_metaboxes() {
		$this->wpstream_startstreaming_sidebar_meta();
	}

	/**
	 * Render the Live Streaming sidebar for the current post.
	 *
	 * @return void
	 */
	public function render_sidebar() {
		$this->wpstream_start_stream_meta();
	}

	/**
	 * Enqueue assets for a Live Channel control surface.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Bundled QR generator (SEC-05): the Larix QR is built locally so the RTMP URL +
		// secret stream key never reach a third-party image service. Registered here too
		// because the admin loads start_streaming.js independently of the public enqueue.
		wp_register_script('wpstream-qrcode-generator', plugin_dir_url( __DIR__ ) . 'public/js/vendor/qrcode-generator.js', array(), '1.4.4', true);
		wp_register_script('wpstream-qr',               plugin_dir_url( __DIR__ ) . 'public/js/wpstream-qr.js', array('wpstream-qrcode-generator'), WPSTREAM_PLUGIN_VERSION, true);

		// Shared public start/stop-streaming script (reused in admin), cache-busted by its file mtime.
		// Depends on wpstream-qr so wpstreamRenderQr() is defined before this runs.
		$modified_start_streaming_file_time = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'public/js/start_streaming.js' ) );
		wp_enqueue_script('wpstream-start-streaming_admin',   plugin_dir_url( __DIR__  ) .'public/js/start_streaming.js', array('wpstream-qr'), $modified_start_streaming_file_time, true);
		// Feature flags (basic-streaming / streaming-hours mode) that tailor the confirm dialogs and warnings.
		$streaming_localization_flags = $this->get_localization_flags();
		wp_localize_script('wpstream-start-streaming_admin', 'wpstream_start_streaming_vars',
		array(
		'admin_url'             =>  get_admin_url(),
		'loading_url'           =>  WPSTREAM_PLUGIN_DIR_URL.'/img/loading.gif',
		'download_mess'         =>  esc_html__('Click to download!','wpstream'),
		'uploading'             =>  esc_html__('We are uploading your file.Do not close this window!','wpstream'),
		'upload_complete2'      =>  esc_html__('Upload Complete! You can upload another file!','wpstream'),
		'not_accepted'          =>  esc_html__('The file is not an accepted video format','wpstream'),
		'upload_complete'       =>  esc_html__('Upload Complete!','wpstream'),
		'no_band'               =>  esc_html__('Not enough streaming data.','wpstream'),
		'no_band_no_store'      =>  esc_html__('Not enough streaming data or storage.','wpstream'),

		'start_streaming_action'=>  esc_html__('TURNING ON','wpstream'),
		'stop_streaming_action' =>  esc_html__('TURNING OFF','wpstream'),
		'start_streaming'       =>  esc_html__('TURN ON','wpstream'),
		'stop_streaming'        =>  esc_html__('TURN OFF','wpstream'),
		'failed_fetching'       =>  esc_html__('Failed to get channel info. Please try again.','wpstream'),
		'turned_on_tooltip'     =>  esc_html__('Channel is now OFF. Click to turn ON.','wpstream'),
		'turned_off_tooltip'    =>  esc_html__('Click to turn channel off. This will interrupt any ongoing broadcast.','wpstream'),
		'turning_on_tooltip'    =>  esc_html__('Turning a channel on may take 1-2 minutes or more. Please be patient.','wpstream'),
		'turning_off_tooltip'   =>  esc_html__('This may take a few minutes.','wpstream'),
		'error1'                =>  esc_html__('You don\'t have enough data to start a new event!','wpstream'),
		'failed_event_creation' =>  esc_html__('Failed to start the channel. Please try again in a few minutes.','wpstream'),
		'channel_turning_on'    =>  esc_html__('Channel is turning on','wpstream'),
		'channel_turning_off'   =>  esc_html__('Channel is turning off','wpstream'),
		'channel_on'            =>  esc_html__('Channel is ON','wpstream'),
		'channel_off'           =>  esc_html__('Channel is OFF','wpstream'),
		'turn_off_confirm' => esc_html__(
		'ARE YOU SURE you\'d like to TURN OFF the channel now? ' . PHP_EOL . PHP_EOL .
		'- Channels TURN OFF automatically after 1 hour of inactivity (no active broadcast).' . PHP_EOL .
		'- Manual TURN OFF is only useful if you require to change the channel settings immediately.' . PHP_EOL .
		'- If your channel is configured with Auto TURN ON, it will turn back on as soon as there is a broadcast.',
		'wpstream'
		),
		'is_basic_streaming'      => $streaming_localization_flags['is_basic_streaming'],
		'use_streaming_hours'     => $streaming_localization_flags['use_streaming_hours'],
		'basic_streaming_warning' => esc_html__(
		'You’ve used all available broadcast or viewer hours.' . PHP_EOL . PHP_EOL .
		'Some live channel features will be limited, including recording, viewer count, browser broadcasting, and content protection.' . PHP_EOL . PHP_EOL .
		'Top up your resources or upgrade your plan to use all features, or choose OK to start anyway.',
		'wpstream'
		),
		'basic_streaming_warning_traffic' => esc_html__(
		'Your account is now in BASIC STREAMING mode.' . PHP_EOL . PHP_EOL .
		'Instead of offloading to the WpStream Cloud, this mode relies on WordPress and hosting resources to process and deliver video. In some WP environments, streaming may be unreliable.' . PHP_EOL .
		'Certain features, such as recording, viewer count, browser broadcasting, and content protection are unavailable.' . PHP_EOL . PHP_EOL .
		'- To take advantage of all features, please choose Cancel and upgrade your plan.' . PHP_EOL .
		'- Otherwise, choose OK to start your channel with these limitations.' . PHP_EOL . PHP_EOL .
		'ARE YOU SURE you want to continue with Basic Streaming?',
		'wpstream'
		),
		'broadcaster_url'   => esc_url( esc_url(home_url('/broadcaster-page/') ) ),
		'is_onboarding'     => isset($_GET['onboard']) && $_GET['onboard'] === 'yes' ? 'yes' : 'no',
		));
	}

	/**
	 * Return cached-only flags used by the start-streaming controller.
	 *
	 * @return array{is_basic_streaming: bool, use_streaming_hours: bool}
	 */
	public function get_localization_flags() {
		return $this->main->quota_manager->get_streaming_ui_flags_from_cache();
	}

	/**
	 * Internal compatibility bridge for legacy Wpstream_Admin render methods.
	 *
	 * @internal
	 * @param string $part Render fragment identifier.
	 * @param array  $args Fragment arguments.
	 * @return mixed
	 */
	public function render_part( $part, $args = array() ) {
		switch ( $part ) {
			case 'social_share':
				return $this->wpstream_social_share( $args[0] );
			case 'theme_channel':
				return $this->render_live_channel( $args[0], $args[1] ?? '', true );
			case 'close_modal_button':
				return $this->wpstream_close_modal_button();
			case 'local_event_options_toggle':
				return $this->wpstream_local_event_options_toggle( $args[0] ?? false );
			case 'basic_stream_mode_message':
				return $this->wpstream_basic_stream_mode_message();
			case 'settings_modal':
				return $this->wpstream_display_modal_seetings( $args[0] );
			case 'share_modal':
				return $this->wpstream_display_modal_share( $args[0] );
			case 'broadcast_modal':
				return $this->wpstream_display_modal_broadcast( $args[0], $args[1], $args[2], $args[3] );
			case 'encoder_table':
				return $this->wpstream_encoder_table();
			case 'encoder_panel':
				return $this->wpstream_encoder_panel( $args[0], $args[1], $args[2], $args[3] ?? false );
		}

		return null;
	}

	/** @return bool Whether the account is in Basic streaming. */
	private function wpstream_is_basic_streaming_mode() {
		return $this->main->quota_manager->is_basic_streaming_mode( null, 'wpstream_is_basic_streaming_mode' );
	}

	/** @return bool Whether the account is metered in streaming hours. */
	private function wpstream_is_use_streaming_hours() {
		$quota = $this->main->quota_manager->get_live_quota_data( 'wpstream_start_channel' );
		return $this->main->quota_manager->uses_streaming_hours( $quota );
	}
        private function wpstream_new_general_set() {

            // Assume no channels until a query proves otherwise.
            $no_channel=1;

            // Presence check for WooCommerce Subscriptions (branch intentionally left empty here).
            if(class_exists ('WC_Subscription')){

            }

            //event_passed
            // Query published PPV products that have not yet "passed" (event_passed != 1) and are of type live_stream/subscription.
            $args = array(
                'posts_per_page'    => -1,
                'post_type'         => 'product',
                'post_status'       => 'publish',
                'meta_query'        =>      array(
                                                array(
                                                        'key'     => 'event_passed',
                                                        'value'   => 1,
                                                        'compare' => '!=',
                                                )
                                            ),

                'tax_query'         => array(
                                        'relation'  => 'AND',
                                        array(
                                            'taxonomy'  =>  'product_type',
                                            'field'     =>  'slug',
                                            'terms'     => array('live_stream','subscription')
                                        )
                                    ),
            );



            // Run the PPV channel query and load the current live-event map + quota/pack data for the user.
            $event_list = new WP_Query($args);
            global $live_event_for_user;
            $live_event_for_user    = $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();
            $pack_details           = $this->main->quota_manager->get_live_quota_data( 'wpstream_new_general_set' );

            // Print the quota/pack summary header for the user.
            $this->main->show_user_data($pack_details);
            if( $event_list->have_posts()){


                // Section header + "create new PPV channel" link.
                print '<div class="pack_details_wrapper_transparent">
                <h3>'.__('Your Pay-Per-View Channel List','wpstream').'</h3>';


                $link_new   =   admin_url('post-new.php?post_type=product').'&new_stream='. rawurlencode('new');

                print '<a href="'.esc_url($link_new).'"  class="wpstream_create_new_product_link">'.esc_html__('Create new Pay-Per-View channel.','wpstream').'</a>';
                print '</div>';

                // Open the grid that will hold each channel card.
                print '<div style="clear: both;"></div><div class="event_list_wrapper">';

                    // Loop every matched PPV product.
                    while ($event_list->have_posts()): $event_list->the_post();

                        // Gather the product id, whether a subscription is flagged as a live event, and its product type.
                        $the_id                     =   get_the_ID();
                        $is_subscription_live_event =   esc_html(get_post_meta($the_id,'_subscript_live_event',true));

                        // Skip plain subscriptions that are not marked as live events.
                        // Stable type slug is '' when the product has no term (never a crash).
                        if( wpstream_get_product_type_slug($the_id)=='subscription' && $is_subscription_live_event=='no'){
                            continue;
                        }

                        // Render the channel card for this product.
                        $this->render_live_channel( $the_id );

                    endwhile;

                // Close the grid; at least one PPV channel exists.
                print'</div>';
                $no_channel=1;
            }else{
                // No PPV channels found.
                $no_channel=0;
            }


            // Nonce hidden input used by the JS "start event" action.
            $ajax_nonce = wp_create_nonce( "wpstream_start_event_nonce" );
            print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';

            // Current user context (user's own live_shows meta collected but not directly used below).
            $current_user       =   wp_get_current_user();
            $allowded_html      =   array();
            $userID             =   $current_user->ID;
            $user_live_streams  =   get_user_meta($userID,'live_shows');


            // Restore the main query after the custom WP_Query loop.
            wp_reset_postdata();




            // free

            // Query all published Free-To-View channels (custom `wpstream_product` post type).
            $args_free = array(
                'posts_per_page'    => -1,
                'post_type'         => 'wpstream_product',
                'post_status'       => 'publish',


            );
            $event_list_free = new WP_Query($args_free);


            if( $event_list_free->have_posts()){
                // Section header + "create new free channel" link.
                print '<div class="pack_details_wrapper_transparent">
                <h3>'.__('Free-To-View Channels','wpstream').'</h3>';

                $link_new = admin_url('post-new.php?post_type=wpstream_product');
                print '<a href="'.esc_url($link_new).'" class="wpstream_create_new_product_link">'.esc_html__('Create new Free-To-View channel.','wpstream').'</a>';
                print '</div>';
                // Open the free-channel grid.
                print '<div style="clear: both;"></div><div class="event_list_wrapper">';

                    // Loop each free channel.
                    while ($event_list_free->have_posts()): $event_list_free->the_post();


                        $the_id =   get_the_ID();

                        // Render only channels whose event has not passed.
                        if( get_post_meta ($the_id,'event_passed',true)!=1){
                            $this->render_live_channel( $the_id );
                        }

                    endwhile;

                // Close the grid and emit the shared modal background + error modal markup.
                print'</div><div class="wpstream_modal_background"></div>';
                print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
                <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
                </div>';
                $no_channel=1;
            }else{
                // No free channels found.
                $no_channel=0;
            }




                // "No channels" call-to-action: build the two create links.
                $link_new_paid = admin_url('post-new.php?post_type=product').'&new_stream='. rawurlencode('new');
                $link_new_free = admin_url('post-new.php?post_type=wpstream_product');
                print '<div class="no_events_warning"> ';
                // Warn when the user has zero PPV channels.
                if($event_list->found_posts==0){
                    print '<div class="no_events_warning_mes">'.__('* You do not have any Pay-Per-View channels!','wpstream').'</div>';
                }
                // Warn when the user has zero free channels.
                if($event_list_free->found_posts==0){
                    print '<div class="no_events_warning_mes">'. __('* You do not have any free channels!','wpstream').'</div>';
                }

                // Offer buttons to create a free or paid channel.
                print '<a href="'.esc_url($link_new_free).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Add new Free-To-View channel ','wpstream').'</a>';
                print '<a href="'.esc_url($link_new_paid).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Add new Pay-Per-View channel ','wpstream').'</a>';

                print '</div>';




        }
        // end   wpstream_new_general_set


        /**
        * Social share
        *
        * Outputs the social-sharing icon row (Facebook, Twitter/X, Pinterest,
        * WhatsApp, LinkedIn, Reddit, email) for a given channel/post. Echoes HTML.
        *
        * @since  3.0.1
        * @param  int $the_id Post/product ID whose permalink and title are shared.
        * @return void
        */
        private function wpstream_social_share($the_id){
                // Match the current request scheme so share URLs are not mixed-content.
                $protocol       =   is_ssl() ? 'https' : 'http';
                // Featured image (used as the Pinterest "media") and the canonical permalink/title.
                $pinterest      =   wp_get_attachment_image_src(get_post_thumbnail_id($the_id), 'full');
                $link           =   esc_url ( get_permalink($the_id) );
                $title          =   get_the_title($the_id);
                // Pre-encode the Twitter status text and the mailto subject/body query.
                $twiter_status  =   urlencode( $title.' '.$link);
                $email_link     =   'subject='.urlencode ( $title ) .'&body='. urlencode( esc_url($link));

                // Pre-build the Facebook sharer URL.
                $facebook_link = esc_html($protocol).'://www.facebook.com/sharer.php?u='. esc_url($link) .'&amp;t='. urlencode(get_the_title());

                // Below: HTML markup for the share bar; each <a> targets one social network's share endpoint.
                ?>
                <div class="wpstream_social_share_wrapper">

                    <a href="<?php print esc_url( $facebook_link); ?>" target="_blank" class="social_facebook wpstream_sharing_social">
                        <span class="dashicons dashicons-facebook-alt"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://twitter.com/intent/tweet?text=<?php echo esc_html($twiter_status); ?>" class="social_tweet wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-twitter"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://pinterest.com/pin/create/button/?url=<?php echo esc_url($link); ?>&amp;media=<?php if (isset( $pinterest[0])){ echo esc_url($pinterest[0]); }?>&amp;description=<?php echo urlencode(get_the_title()); ?>" target="_blank" class="social_pinterest wpstream_sharing_social">
                        <span class="dashicons dashicons-pinterest"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://api.whatsapp.com/send?text=<?php echo urlencode( get_the_title().' '. esc_url( $link )); ?>" class="social_whatsup wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-whatsapp"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(esc_url($link)); ?>" class="social_linkedin wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-linkedin"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>:///www.reddit.com/submit?url==<?php echo urlencode(esc_url($link)); ?>" class="social_linkedin wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-reddit"></span>
                    </a>

                    <a href="mailto:email@email.com?<?php echo trim(esc_html($email_link));?>" data-action="share email"  class="social_email wpstream_sharing_social">
                      <span class="dashicons dashicons-email-alt"></span>
                    </a>

                    <div class="wpstream_modal_explanations"> <?php print esc_html__('Spread the word! To let people know about your channel, click on the corresponding icon and share on the social platforms of your choice. The more the merrier!','wpstream'); ?> </div>
                    <?php
                print '</div>';
        }




        /**
        * Shows event unit card in admin
        *
        * Renders one channel "card" (thumbnail, title, ON/OFF button, and the
        * webcam / external-app / stats / settings / edit / view / share icon
        * controls) plus its settings/share/broadcast modals. Bails with a notice
        * if the user lacks the administrator capability or streaming permission.
        * Echoes HTML.
        *
        * @since  3.0.1
        * @param  int    $the_id   Channel product/post ID.
        * @param  string $is_front Non-empty (e.g. 'front') when rendered on the front end.
        * @return void
        */
        private function render_live_channel( $the_id, $is_front = '', $is_theme = false ) {
            global $live_event_for_user;
            global $wpstream_plugin;
            $current_user       =   wp_get_current_user();

            // Non-admins get a "not allowed" notice in the admin (back-end) context.
            if( !current_user_can('administrator')){
                if($is_front=='' ){
                    print '<div class="event_list_unit">';
                    esc_html_e('You are not allowed to broadcast.','wpstream');
                    print '</div>';
                    return;
                }

            }

            // Enforce the plugin's own streaming-permission gate as well.
            if( !$this->main->wpstream_check_user_can_stream()  ){
                print '<div class="event_list_unit">';
                esc_html_e('You are not allowed to broadcast','wpstream');
                print '</div>';
                return;
            }


            // Flag this card as "live" if the channel currently has an active event.
            $live_class='';
            if(isset($live_event_for_user[$the_id])) {
                $live_class=" wpstream_show_started";
            }


            // Thumbnail: the post's featured image, or the plugin logo as a fallback.
            if(has_post_thumbnail($the_id)){
                $thumb  =   get_the_post_thumbnail_url($the_id,'thumbnail');
            }else{
                $thumb= plugin_dir_url( dirname( __FILE__ ) ). 'img/plugin-logo.png';
            }

            // Default streaming display state (hidden data, empty OBS credentials/stats URL).
            $pending_streaming_class            =   'hide_stream_data';
            $external_software_streaming_class  =   '';
            $obs_uri                            =   '';
            $obs_stream                         =   '';
            $live_data_url                      =   '';

            // On the front end, lazily fetch the live-event map if it was not preloaded.
            if( $live_event_for_user=='' && $is_front=='front' ){
                $live_event_for_user    =    $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();
            }
            // The companion theme uses its labelled-card spinner; other contexts use WordPress's spinner.
            if ( $is_theme ) {
                $spinner_url    = admin_url( 'images/spinner-2x.gif' );
                $channel_status = '';
                $button_status  = '<div class="spinner" style="background-image: url(' . $spinner_url . ')"></div>';
            } else {
                $channel_status = '<div class="spinner" style="visibility: visible"></div>';
                $button_status  = '<div class="spinner" style="visibility: visible"></div>';
            }
            if(is_array($live_event_for_user) && isset($live_event_for_user[$the_id])) {
                // Channel is live: reveal stream data and grab the QoS/stats URL.
                $pending_streaming_class        =   'pending_trigger';
                $live_data_url                  =   get_post_meta($the_id,'qos_url',true);
              //  $channel_status                 =   esc_html__('Channel is on','wpstream');
            } else {
                // Channel is off: show OFF status and a TURN ON button.
                $channel_status                 = esc_html__('Channel is OFF','wpstream');
                $button_status                  = esc_html__('TURN ON','wpstream');
            }

            // Load the channel's server id and broadcasting credentials/URLs.
            $server_id      =   get_post_meta($the_id,'server_id',true);
            $obs_uri        =   get_post_meta($the_id,'obs_uri',true);
            $obs_stream     =   get_post_meta($the_id,'obs_stream',true);
            $webcaster_url  =   get_post_meta($the_id,'webcaster_url',true);
            $rtmp_ip_uri    =   '';

            $ajax_nonce = '';
            if ( $is_theme ) {
                print '<div class="wpstream_theme_event_list_unit event_list_unit '.$live_class.' '.$pending_streaming_class.' event_unit_style_'.esc_attr($is_front).'"  data-show-id="'.intval($the_id).'" data-server-id="'.$server_id.'" data-server-url="'.$rtmp_ip_uri.'">';
            } else {
                $ajax_nonce = wp_create_nonce( 'wpstream_start_event_nonce' );
                print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';
                print '<div class="event_list_unit '.$live_class.' '.$pending_streaming_class.' event_unit_style_'.esc_attr($is_front).'"  data-show-id="'.intval($the_id).'" data-server-id="'.$server_id.'" data-server-url="'.$rtmp_ip_uri.'">';
            }

                // Status line, notification slot, thumbnail and (trimmed) title with the channel ID.
                $status_style = $is_theme && ! $channel_status ? ' style="display: none"' : '';
                print '<div class="wpstream_channel_status"'.$status_style.'>'.$channel_status.'</div>';

                print '<div class="server_notification"></div>';

                if ( $is_theme ) {
                    print '<div class="wpstream_theme_event_thumb_wrapper">';
                }
                print '<div class="event_thumb_wrapper" style="background-image:url('.$thumb.')"></div>';
                if ( $is_theme ) {
                    print '</div>';
                }

                print '<div class="event_title" data-prodid="'.$the_id.'">'.wp_trim_words(get_the_title($the_id),10);


                    /*    print '***</br>'.get_post_meta($the_id,'obs_uri',true) .'***</br>'.
                        get_post_meta($the_id,'obs_stream',true).'***</br>'.
                        get_post_meta($the_id,'broadcast_url',true);
                    */
                    print '<div class="wpstream_channel_item_id">'.esc_html( '#ID' ).' '.$the_id.'</div>';
                print '</div>';


                if ( ! $is_theme ) {
                    // Primary ON/OFF toggle button (carries the start-event nonce).
                    print '<div class="start_event wpstream_button wpstream_tooltip_wrapper" data-show-id="'.$the_id.'" data-nonce="' . esc_attr( $ajax_nonce ) . '"> ' . $button_status;
                        print '<div class="wpstream_tooltip">'.esc_html__('Channel is now OFF. Click to turn ON.','wpstream').'</div>';
                    print '</div>';
                } else {
                    print '<div class="wpstream_theme_control_bar_wrapper">';
                }

                // Column 1: broadcasting entry points (webcam, external app, live stats).
                print '<div class="wpstream_options_col1 wpstream_stream_browser_wrapper">';

                    // Webcam ("go live in browser") icon button.
                    print '<div class="wpstream_inactive_icon start_webcaster wpstream_stream_browser wpstream-button-icon wpstream_tooltip_wrapper"  data-webcaster-url="'.$webcaster_url.'" data-show-id="'.$the_id.'"">';

                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpstream').'</div>';
                        print '<div class="wpstream_tooltip">'.esc_html__('Go live with your webcam','wpstream').'</div>';

                        print '<svg width="41" height="51" viewBox="0 0 41 51" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M40.7646 44.2989C39.8782 41.4216 38.3361 38.7814 36.181 36.4522C35.7274 35.9619 35.1629 35.3518 34.4858 34.8356C34.0448 34.4996 33.4262 34.5281 33.0182 34.9035C29.358 38.2714 25.2628 39.9088 20.4979 39.9088H20.4853C15.7103 39.9059 11.6117 38.264 7.95648 34.889C7.52404 34.4896 6.85874 34.484 6.41953 34.8764C3.30565 37.6569 1.20501 40.9206 0.175434 44.5778C-0.276291 46.1833 0.15652 47.8497 1.33328 49.0353C2.22709 49.9362 3.39869 50.4173 4.61958 50.4173C5.0357 50.4173 5.45813 50.3613 5.87645 50.247C10.147 49.081 14.0772 48.4108 17.8916 48.1983C21.1954 48.014 24.4465 48.1323 27.5541 48.5495C29.9559 48.8718 32.4237 49.3888 35.0996 50.1299C36.823 50.6076 38.459 50.1562 39.7059 48.86C40.9268 47.5901 41.2933 46.0132 40.7651 44.2994L40.7646 44.2989ZM38.0572 47.274C37.3825 47.9754 36.6367 48.1823 35.7092 47.9257C32.9321 47.1565 30.3638 46.6187 27.8578 46.2827C24.6079 45.8465 21.2117 45.723 17.7643 45.9151C13.7874 46.1365 9.70183 46.8319 5.27439 48.041C4.43435 48.2706 3.5673 48.0403 2.9568 47.425C2.35932 46.8227 2.14793 46.0108 2.37712 45.1975C3.21567 42.2171 4.7982 39.6162 7.2018 37.2734C11.0641 40.5378 15.5276 42.193 20.4845 42.196H20.4978C25.4557 42.196 29.9251 40.5386 33.7967 37.2667C34.024 37.4904 34.254 37.7359 34.5032 38.0059C36.4236 40.0816 37.7951 42.4256 38.5799 44.9728C38.8603 45.8811 38.6941 46.6125 38.0581 47.274L38.0572 47.274ZM20.4954 30.7538C13.8088 30.7523 8.54664 25.4128 8.54379 18.6256C8.5412 12.2624 13.9961 6.83353 20.3776 6.84872C27.1094 6.86467 32.4526 12.1652 32.4488 18.8231C32.4447 25.4243 27.1038 30.7547 20.4954 30.7538V30.7538ZM29.4401 18.8383C29.5559 13.9512 25.4358 9.8445 20.5347 9.83671C15.5301 9.82892 11.6488 13.9279 11.5328 18.5667C11.4086 23.5202 15.344 27.7103 20.3381 27.7926C25.5542 27.8783 29.554 23.5758 29.4401 18.8383V18.8383ZM20.4917 25.1342C17.2131 25.2436 14.1541 22.4349 14.1567 18.8924C14.1593 15.2627 16.9642 12.4191 20.5858 12.4662C24.0109 12.5111 26.8269 15.2466 26.8362 18.8471C26.8451 22.4293 23.782 25.2436 20.4917 25.1342V25.1342ZM18.5119 13.9982C17.3006 13.9908 15.6202 15.656 15.6295 16.8543C15.6328 17.3209 15.961 17.661 16.3983 17.6513C17.5973 17.6246 19.2281 16.0169 19.2696 14.8205C19.2881 14.2742 19.0371 14.0016 18.5119 13.9982L18.5119 13.9982ZM20.4964 0.568359C10.4428 0.568359 2.26333 8.74761 2.26333 18.8014C2.26333 28.8551 10.4426 37.0345 20.4964 37.0345C30.5502 37.0345 38.7295 28.8553 38.7295 18.8014C38.7291 8.7478 30.5502 0.568359 20.4964 0.568359V0.568359ZM20.4964 33.3886C12.4528 33.3886 5.90919 26.8449 5.90919 18.8014C5.90919 11.3105 11.585 5.12093 18.8624 4.30631C18.8561 4.36009 18.8528 4.41423 18.8528 4.46912C18.8468 5.37368 19.6112 6.16064 20.4927 6.15846C21.3636 6.15586 22.1283 5.39706 22.142 4.52143C22.1431 4.44874 22.1372 4.3768 22.1279 4.30596C29.4067 5.11929 35.0849 11.3092 35.0849 18.802C35.0842 26.8456 28.5404 33.3892 20.4968 33.3892L20.4964 33.3886Z" fill="black"/>
                        </svg>';
                    if ( $is_theme ) {
                        esc_html_e( 'Webcam', 'wpstream' );
                    }
                    print '</div>';


                    print '<div class="wpstream_inactive_icon wpstream_stream_pro wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"  data-modal="wpstream_broadcast_modal"   data-show-id="'.$the_id.'"">';

                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpstream').'</div>';
                        print '<div class="wpstream_tooltip">'.esc_html__('Go Live with external streaming app','wpstream').'</div>';

                        print '<svg width="51" height="38" viewBox="0 0 51 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M48.1266 13.9634L41.891 17.5343V13.6438C41.891 10.8723 39.6525 8.63384 36.8811 8.63384H33.1504L30.1124 3.62393C28.7268 1.3324 26.2751 0 23.6103 0H10.2863C8.84722 0 7.67471 1.17251 7.67471 2.6116C7.67471 4.05068 8.84722 5.22319 10.2863 5.22319H23.6103C24.4631 5.22319 25.2093 5.64961 25.6888 6.3957L27.0744 8.63413L5.00991 8.63376C2.23843 8.63376 0 10.8722 0 13.6437V32.9901C0 35.7616 2.23843 38 5.00991 38H36.9342C39.7057 38 41.9441 35.7616 41.9441 32.9901V29.0461L48.1797 32.6169C49.2991 33.2564 50.6846 32.4571 50.6846 31.1778L50.6842 15.4022C50.6311 14.123 49.2455 13.3237 48.1261 13.9632L48.1266 13.9634ZM38.1603 32.9368C38.1603 33.6297 37.574 34.1625 36.9345 34.1625L5.01029 34.1629C4.31733 34.1629 3.78458 33.5766 3.78458 32.9372V13.5907C3.78458 12.8978 4.37086 12.365 5.01029 12.365H36.9345C37.6275 12.365 38.1603 12.9513 38.1603 13.5907V32.9368Z" fill="black"/>
                            <path d="M22.4917 21.585H9.70066C8.84784 21.585 8.15527 22.2779 8.15527 23.1304V28.247C8.15527 29.0998 8.84823 29.7923 9.70066 29.7923H22.4917C23.3445 29.7923 24.0371 29.0994 24.0371 28.247V23.1304C24.0371 22.2775 23.3445 21.585 22.4917 21.585Z" fill="black"/>
                        </svg>';
                    if ( $is_theme ) {
                        esc_html_e( 'External App', 'wpstream' );
                    }
                    print '</div>';




                    print '<a href="'.esc_url($live_data_url).'" target="_blank" class="wpstream_inactive_icon wpstream_live_data wpstream_statistics_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'" >';

                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to see live stats.','wpstream').'</div>';
                        print '<div class="wpstream_tooltip">'.esc_html__('Live Statistics','wpstream').'</div>';

                        print'<svg width="50" height="42" viewBox="0 0 50 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M48.9001 9.02344H40.3342C39.7288 9.02344 39.2344 9.51791 39.2344 10.1233V40.4275C39.2344 41.033 39.7288 41.5274 40.3342 41.5274H48.9001C49.5055 41.5274 50 41.0329 50 40.4275V10.1233C50 9.51791 49.5055 9.02344 48.9001 9.02344V9.02344Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M35.821 22.4067H27.2551C26.6497 22.4067 26.1553 22.9012 26.1553 23.5066V40.4277C26.1553 41.0332 26.6497 41.5276 27.2551 41.5276H35.821C36.4264 41.5276 36.9209 41.0332 36.9209 40.4277V23.5066C36.9209 22.9012 36.4264 22.4067 35.821 22.4067V22.4067Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M22.7439 0H14.178C13.5726 0 13.0781 0.494478 13.0781 1.09989V40.4275C13.0781 41.0329 13.5726 41.5274 14.178 41.5274H22.7439C23.3493 41.5274 23.8438 41.0329 23.8438 40.4275V1.09989C23.8438 0.49447 23.3493 0 22.7439 0V0Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.66573 15.2559H1.09987C0.49445 15.2559 -2.24584e-05 15.7512 -2.24584e-05 16.3558V40.4285C-2.24584e-05 41.0331 0.495325 41.5284 1.09987 41.5284H9.66573C10.2703 41.5284 10.7656 41.034 10.7656 40.4285V16.3558C10.7656 15.7503 10.2711 15.2559 9.66573 15.2559V15.2559Z" fill="black"/>
                        </svg>';
                    if ( $is_theme ) {
                        esc_html_e( 'Statistics', 'wpstream' );
                    }
                    print '</a>';
                print '</div>';

                // Column 2: management controls (settings + edit only for back-end admins, then view/share).
                print '<div class="wpstream_options_col2 wpstream_show_settings_wrapper">';
                    if($is_front==''){
                        // Settings modal trigger (disabled while the channel is ON).
                        print '<div class="wpstream_show_settings wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpstream_settings_modal" data-show-id="'.$the_id.'" value="'.esc_html__('Settings','wpstream').'">';

                            print '<div class="wpstream_tooltip_disabled">'.esc_html__('
                            Turn OFF the channel to change its settings.','wpstream').'</div>';
                            print '<div class="wpstream_tooltip">'.esc_html__('Channel Settings','wpstream').'</div>';

                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M49.22 21.8648C48.82 21.6099 45.665 19.7247 44.415 19.15L42.8651 15.4C43.3251 14.1601 44.2099 10.6849 44.3652 10.0798H44.3649C44.4911 9.52035 44.3213 8.93514 43.9149 8.52987L41.47 6.09995C41.0658 5.69189 40.4795 5.52168 39.9201 5.65003C39.4601 5.75501 35.92 6.64994 34.5999 7.15012L30.8499 5.60021C30.3048 4.40012 28.4699 1.32529 28.1351 0.795199V0.79485C27.8337 0.301688 27.298 0.00069643 26.7201 0H23.2798C22.7057 0.000348769 22.1718 0.294715 21.8648 0.779859C21.6098 1.1799 19.7247 4.33487 19.15 5.58487L15.4 7.13478C14.1601 6.67476 10.6849 5.78996 10.0798 5.63469V5.63504C9.52035 5.50878 8.93513 5.67864 8.52987 6.08496L6.09995 8.52988C5.69189 8.9341 5.52168 9.52042 5.65003 10.0798C5.75501 10.5398 6.64994 14.0799 7.15012 15.4L5.60021 19.15C4.40012 19.6951 1.32529 21.53 0.795198 21.8648H0.79485C0.301688 22.1662 0.00069643 22.7019 0 23.2798V26.7149C0.000348751 27.2893 0.294715 27.8233 0.779858 28.1299C1.1799 28.3849 4.33486 30.27 5.58487 30.8448L7.13478 34.5948C6.67475 35.8347 5.78996 39.3099 5.63469 39.915H5.63504C5.50878 40.4747 5.67863 41.06 6.08496 41.4649L8.51487 43.8948V43.8951C8.9191 44.3032 9.50541 44.4731 10.0648 44.3451C10.5248 44.2401 14.0649 43.3451 15.385 42.845L19.135 44.3949C19.6801 45.595 21.515 48.6698 21.8498 49.1999C22.1525 49.6997 22.6956 50.0035 23.2798 50H26.7149C27.2893 49.9996 27.8233 49.7053 28.1299 49.2201C28.3849 48.8201 30.27 45.6651 30.8447 44.4151L34.5947 42.8652C35.8347 43.3252 39.3098 44.21 39.9149 44.3653V44.3649C40.4747 44.4912 41.0599 44.3213 41.4649 43.915L43.8948 41.4851H43.8951C44.3032 41.0809 44.473 40.4945 44.345 39.9352C44.2401 39.4751 43.3451 35.9351 42.8449 34.615L44.3949 30.865C45.5949 30.3198 48.6698 28.485 49.1999 28.1501C49.6997 27.8474 50.0034 27.3044 49.9999 26.7201V23.2799C50.0045 22.7047 49.7087 22.1683 49.2201 21.8649L49.22 21.8648ZM24.9995 35.8845C22.1099 35.882 19.3399 34.7314 17.2985 32.6866C15.2572 30.6414 14.1118 27.8694 14.1146 24.9798C14.1171 22.0903 15.2676 19.3199 17.3125 17.2785C19.3577 15.2372 22.1297 14.0921 25.0193 14.0946C27.9088 14.0974 30.6792 15.2477 32.7205 17.2928C34.7619 19.338 35.907 22.1101 35.9045 24.9996C35.8978 27.8879 34.7462 30.6557 32.7021 32.6964C30.6576 34.7367 27.8876 35.8834 24.9994 35.8845H24.9995Z" fill="black"/>
                                </svg>
                            </div>';


                        // Edit-post link for the channel (admin only).
                        print '<a href="'.get_edit_post_link($the_id).'" class="wpstream_edit_channel wpstream-button-icon wpstream_tooltip_wrapper" target="_blank"  data-show-id="'.$the_id.'"">';
                            print '<div class="wpstream_tooltip">'.esc_html__('Edit Channel','wpstream').'</div>';
                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M44.8203 1.79549C42.4263 -0.598498 38.5591 -0.598498 36.1651 1.79549L4.67494 33.2246C4.55203 33.3475 4.49079 33.4088 4.42954 33.5317C4.42954 33.5317 4.42954 33.5929 4.36829 33.5929C4.30705 33.7159 4.24539 33.7771 4.24539 33.9V33.9613L0.0711091 47.5886C-0.113044 48.264 0.0711092 48.9389 0.500659 49.4301C0.868966 49.7984 1.29852 49.9826 1.78973 49.9826C1.97388 49.9826 2.15804 49.9826 2.34219 49.9213L15.9085 45.747H15.9697C16.0926 45.6858 16.2151 45.6858 16.2768 45.6241C16.2768 45.6241 16.338 45.6241 16.338 45.5629C16.4609 45.5016 16.5834 45.44 16.6451 45.3175L48.0742 13.8884C50.4682 11.4944 50.4682 7.62715 48.0742 5.23316L44.8203 1.79549Z" fill="black"/>
                        </svg>';
                        print '</a>';
                    }

                    // Public "view channel" link (always shown).
                    print '<a href="'.get_permalink($the_id).'" target="_blank" class="wpstream_view_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('View Channel','wpstream').'</div>';
                        print '<svg width="50" height="32" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.6201 15.2828H21.8752C21.8752 14.069 22.8681 13.0207 24.1373 13.0207V9.76562C21.1032 9.76562 18.6201 12.2487 18.6201 15.2828Z" fill="black"/>
                            <path d="M24.855 0C12.9378 0 3.28272 10.9792 0.579292 14.3447C-0.193097 15.2826 -0.193097 16.662 0.579292 17.6553C3.28282 21.0208 12.9378 32 24.855 32C36.7722 32 46.4273 21.0208 49.1307 17.6553C49.9031 16.7174 49.9031 15.338 49.1307 14.3447C46.4275 10.9792 36.7722 0 24.855 0V0ZM24.855 25.8205C19.4482 25.8205 15.0344 21.4067 15.0344 15.9999C15.0344 10.5931 19.4482 6.17927 24.855 6.17927C30.2618 6.17927 34.6756 10.5931 34.6756 15.9999C34.6756 21.4067 30.2618 25.8205 24.855 25.8205Z" fill="black"/>
                        </svg>';
                    if ( $is_theme ) {
                        esc_html_e( 'View', 'wpstream' );
                    }
                    print '</a>';


                    // Share modal trigger.
                    print '<div class="wpstream_share_channel wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpstream_share_modal"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('Share Channel','wpstream').'</div>';
                        print '<svg width="44" height="50" viewBox="0 0 44 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20.0777 20.5087L24.3481 17.9427C26.6432 16.5645 29.4554 16.2443 31.96 17.198C33.443 17.7642 35.1166 17.9363 36.852 17.6105C40.3163 16.9521 43.1162 14.1831 43.8115 10.7248C45.0606 4.51597 39.7751 -0.910703 33.5907 0.12835C30.2924 0.682061 27.5604 3.16211 26.5942 6.36168C26.4405 6.86646 26.3418 7.35835 26.2804 7.84466C25.9482 10.5028 24.2498 12.8044 21.9547 14.1827L19.3458 15.7456C17.1061 17.0869 14.3986 17.1792 11.9494 16.2748C10.9956 15.9241 9.96159 15.7271 8.88507 15.7271C3.39004 15.7271 -0.960663 20.7607 0.184097 26.4527C0.879447 29.911 3.67936 32.68 7.14364 33.3384C8.87902 33.6706 10.5525 33.492 12.0356 32.9259C14.5402 31.9721 17.3461 32.2919 19.6475 33.6706L23.906 36.2241C25.346 37.0855 26.2195 38.6424 26.2195 40.3159V40.3403C26.2195 45.8229 31.2287 50.1611 36.9018 49.0472C40.3602 48.3703 43.1415 45.583 43.8244 42.1246C44.9383 36.451 40.6 31.4423 35.1175 31.4423C34.0406 31.4423 33.007 31.6394 32.0532 31.9901C29.604 32.8944 26.9029 32.8022 24.6627 31.4608L20.0721 28.7038C18.6445 27.8484 17.7706 26.304 17.7706 24.6364V24.5875C17.7642 22.9208 18.6441 21.37 20.0777 20.5085L20.0777 20.5087Z" fill="black"/>
                        </svg>';
                    if ( $is_theme ) {
                        esc_html_e( 'Share', 'wpstream' );
                    }
                    print '</div>';
                print '</div>';

                if ( $is_theme ) {
                    // The companion-theme layout places the ON/OFF control after both option columns.
                    print '<div class="start_event wpstream_button wpstream_tooltip_wrapper" data-show-id="'.$the_id.'"> '.$button_status;
                        print '<div class="wpstream_tooltip">'.esc_html__('Channel is now OFF. Click to turn ON.','wpstream').'</div>';
                    print '</div>';
                    print '</div>';
                }

                // Emit the hidden per-channel modals (settings, share, broadcast) referenced by the triggers above.
                $this->wpstream_display_modal_seetings($the_id);
                $this->wpstream_display_modal_share($the_id);
                $this->wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream);

            // Close the card wrapper.
            print '</div>';

        }

        /*
        *
        * Close modal button
        *
        */
        // Outputs the small "X" element used to dismiss the modal dialogs.
        private function wpstream_close_modal_button(){
            print '<div class="wpstream_close_modal"></div>';
        }

        /**
         * Render the "edit settings for this channel" toggle plus the per-channel
         * event-options block on the product edit screen.
         *
         * When the toggle is off, the site-wide Default Channel Settings apply;
         * when on, the local per-channel options are used. In basic-stream mode a
         * warning is shown and the toggle is disabled. Echoes HTML.
         *
         * @param  bool $is_basic_stream_mode True to render the basic-streaming warning and disable the toggle.
         * @return void
         */
        private function wpstream_local_event_options_toggle( $is_basic_stream_mode = false ) {
			$channel_settings = $this->main->channel_settings->read( get_the_ID() );
			$use_local_event_options_enabled = 'customized' === $channel_settings['mode'];

            // In basic-streaming mode, prepend the limitation notice.
            if ( $is_basic_stream_mode ) {
				$this->wpstream_basic_stream_mode_message();
            }

            // Toggle row: label/description on the left, a switch checkbox on the right.
            print '<div class="wpstream_local_event_options_toggle_wrapper">';
                print '<div class="wpstream_local_event_options_toggle_info">';
                    print '<label for="local_event_options_enabled" class="wpstream_local_event_options_label">'.esc_html__('Edit settings for this channel','wpstream').'</label>';
                    print '<span>'.sprintf(esc_html__('When is OFF, the settings from %s will be applied','wpstream'), '<a href="' . admin_url('admin.php?page=wpstream_settings&tab=default_options') . '" target="_blank">'.esc_html__('Default Channel Settings','wpstream').'</a>').'</span>';
                print '</div>';
                // The switch checkbox reflects the current toggle state and is disabled in basic-stream mode.
                print '<label class="wpstream_switch">';
                    print '<input id="local_event_options_enabled" type="checkbox" class="wpstream_local_event_options_toggle" ' . ($use_local_event_options_enabled === true ? 'checked' : '') . ' ' . ( $is_basic_stream_mode ? 'disabled' : '' ) . '>';
                    print '<span class="wpstream_slider round"></span>';
                print '</label>';
            print '</div>';
        }

		/**
		 * Print the basic-streaming-mode notice shown above the channel settings.
		 *
		 * Wording differs depending on whether the account is out of streaming
		 * hours vs. simply on a basic plan; both variants link to pricing. Echoes HTML.
		 *
		 * @return void
		 */
		private function wpstream_basic_stream_mode_message() {
			print '<div class="basic-mode-notice">';
			// Out-of-hours variant: offer to top up or upgrade.
			if ( $this->wpstream_is_use_streaming_hours() ) {
					print sprintf(
						wp_kses(
							__(
							'Youâ€™ve used all available broadcast or viewer hours. Default channel settings will be used for now. To edit channel settings, <a href="%s" target="_blank">top up</a> your resources or <a href="%s" target="_blank">upgrade</a> your plan.',
							'wpstream'
							 ),
							 array( 'a' => array(
								'href'   => array(),
								'target' => array()
								)
							 )
						),
						esc_url( 'https://wpstream.net/pricing/' ),
						esc_url( 'https://wpstream.net/pricing/' )
					);
			} else {
				// Plain basic-mode variant: offer to upgrade.
				print sprintf(
					wp_kses(
						__(
						'You are currently in Basic Streaming Mode. The default channel settings will be used instead. Please <a href="%s" target="_blank">upgrade</a> your plan to edit the channel settings.',
						'wpstream'
						 ),
						 array(
							'a' => array(
								'href' => array()
							)
						 )
					),
					esc_url( 'https://wpstream.net/pricing/' )
				);
			}
			print '</div>';
		}

        /*
        *
        * Display modal settings
        *
        */
        /**
         * Render the per-channel "Channel Settings" modal (settings toggle plus
         * the streaming option controls). Echoes HTML.
         *
         * @param  int $the_id Channel post/product ID.
         * @return void
         */
        private function wpstream_display_modal_seetings($the_id){
            // Whether the account is in basic-streaming mode (disables/locks some options).
            $is_basic_stream_mode = $this->wpstream_is_basic_streaming_mode();

            // Modal shell with close button and title carrying the channel ID.
            print '<div class="wpstream_modal_form wpstream_settings_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>';
                printf( esc_html__('Channel Settings (#ID %s)','wpstream'),$the_id);
                print '</h3>';

                // The "edit settings for this channel" toggle.
                $this->wpstream_local_event_options_toggle( $is_basic_stream_mode );

				$channel_settings = $this->main->channel_settings->read( $the_id );
				$local_event_options = 'customized' === $channel_settings['mode']
					? $channel_settings['customized']
					: $channel_settings['options'];
				$is_local_event_options_enabled = 'customized' === $channel_settings['mode'];

                // Options that do not apply to the per-channel (local) context.
                $local_array_exclude=array('ses_encrypt','vod_domain_lock','vod_encrypt');

                // Render the option controls; the 4th arg disables them when local options are off.
                print '<div class="wpstream_event_streaming_local">';
					$this->main->admin->user_streaming_global_channel_options(
						'',
						$local_event_options,
						$local_array_exclude,
						!$is_local_event_options_enabled,
						$is_basic_stream_mode
					);
                print '</div>';
            print '</div>';
        }


        /*
        *
        * Display share settings
        *
        */
        /**
         * Render the per-channel "Share your Channel" modal (social-share bar). Echoes HTML.
         *
         * @param  int $the_id Channel post/product ID.
         * @return void
         */
        private function wpstream_display_modal_share($the_id){
            // Modal shell with close button, title and the social-share icon row.
            print '<div class="wpstream_modal_form wpstream_share_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>'.esc_html__('Share your Channel','wpstream').'</h3>';
                $this->wpstream_social_share($the_id);
            print '</div>';
        }


        /*
        *
        * Display broadcast modal
        *
        */

        /**
         * Render the "Go Live with External Streaming App" modal: an encoder
         * picker (OBS, StreamYard, Restream, vMix, Wirecast, XSplit, Larix) plus
         * one settings panel per encoder showing the RTMP server URI and stream
         * key. Echoes HTML.
         *
         * @param  int    $the_id                           Channel post/product ID.
         * @param  string $external_software_streaming_class Extra CSS class flag (unused in body).
         * @param  string $obs_uri                          RTMP server/ingest URI for the channel.
         * @param  string $obs_stream                       RTMP stream key for the channel.
         * @return void
         */
        private function wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream){
            // Modal shell with close button, title and instructions.
            print '<div class="wpstream_modal_form wpstream_broadcast_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>'.esc_html__('Go Live with External Streaming App','wpstream').'</h3>';

                print '<div class="wpstream_modal_explanations">'.esc_html__('Please choose your RTMP encoder/broadcaster','wpstream').'</div>';

                // One definition row per encoder drives both the dropdown and the
                // panels; JS shows the panel whose CSS class matches the option value.
                $encoders = $this->wpstream_encoder_table();
                print '<select class="wpstream_external_broadcast_options">';
                foreach( $encoders as $encoder ){
                    print '<option value="'.esc_attr( $encoder['value'] ).'">'.esc_html( $encoder['label'] ).'</option>';
                }
                print '</select>';

                // Emit every encoder's settings panel; JS toggles the visible one.
                $first_encoder = true;
                foreach( $encoders as $encoder ){
                    $this->wpstream_encoder_panel( $encoder, $obs_uri, $obs_stream, $first_encoder );
                    $first_encoder = false;
                }

            print '</div>';
        }




        /*
        *
        * External encoder definitions + shared panel renderer
        *
        */

        /**
         * Per-encoder definition rows for the external-broadcast modal.
         *
         * Each row drives one dropdown <option> and one settings panel:
         *   - 'value'     the <option> value AND the panel's CSS class â€” a public
         *                 contract with the panel-toggle JS, the E2E tests and the
         *                 companion theme's copy of this modal. Keep the historical
         *                 'wimx' / 'xplit' spellings: renaming breaks the toggle.
         *   - 'label'     the dropdown label.
         *   - 'uri_label' the label of the server/URL field for that encoder.
         *   - 'key_label' the label of the stream-key field (Larix has none).
         *   - 'larix'     true only for Larix: combined RTMP string + QR code
         *                 layout instead of the two server/key fields.
         *   - 'steps'     that encoder's setup instruction lines, kept verbatim.
         *
         * @return array[] Encoder definition rows, in dropdown order.
         */
        private function wpstream_encoder_table(){
            return array(
                array(
                    'value'     => 'wpstream_obs_settings',
                    'label'     => __( 'OBS', 'wpstream' ),
                    'uri_label' => __( 'Server:', 'wpstream' ),
                    'key_label' => __( 'Stream Key:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Click Settings in the OBS Window and then Select Stream.', 'wpstream' ),
                        __( '2. Choose Custom Streaming Server in the Stream Type dropdown menu.', 'wpstream' ),
                        __( '3. In the URL box, type/paste your Server.', 'wpstream' ),
                        __( '4. In the Stream key, type/paste your Stream key.', 'wpstream' ),
                        __( '5. Save changes.Close the Settings window and click on the "Start Streaming" button in the main window of OBS.', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_streamyard_settings',
                    'label'     => __( 'StreamYard', 'wpstream' ),
                    'uri_label' => __( 'RTMP server URL:', 'wpstream' ),
                    'key_label' => __( 'Stream key:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Set up your destination by going to your StreamYard account.', 'wpstream' ),
                        __( '2. Choose "Custom RTMP" and add the RTMP server URL and Stream key from WpStream.', 'wpstream' ),
                        __( '3. Go to "Broadcasts" then "create a broadcast".', 'wpstream' ),
                        __( '4. Enter Live Studio.', 'wpstream' ),
                        __( '5. Adjust all your preferred settings and Click on "Go Live".', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_restream_settings',
                    'label'     => __( 'Restream', 'wpstream' ),
                    'uri_label' => __( 'RTMP URL:', 'wpstream' ),
                    'key_label' => __( 'Stream key:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Go to your Restream account and set up a destination or channel.', 'wpstream' ),
                        __( '2. Choose "Custom RTMP" and add the RTMP URL and Stream Key from WpStream.', 'wpstream' ),
                        __( '3. Click on "Add Channel".', 'wpstream' ),
                        __( '4. Enter Live Studio.', 'wpstream' ),
                        __( '5. Adjust your preferred settings and Go Live.', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_wimx_settings',
                    'label'     => __( 'vMix', 'wpstream' ),
                    'uri_label' => __( 'URL:', 'wpstream' ),
                    'key_label' => __( 'Stream Key:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Click on the gear icon near the stream button on the bottom.', 'wpstream' ),
                        __( '2. Choose a custom RTMP Server in destination.', 'wpstream' ),
                        __( '3. In the URL box, type/paste your URL.', 'wpstream' ),
                        __( '4. In the Stream key, type/paste your Stream key.', 'wpstream' ),
                        __( '5. Save changes. You can start streaming by clicking on the stream button at the bottom of the dashboard.', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_wirecast_settings',
                    'label'     => __( 'Wirecast', 'wpstream' ),
                    'uri_label' => __( 'Address:', 'wpstream' ),
                    'key_label' => __( 'Stream:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Click Output on the top of the screen and then Select Output Settings.', 'wpstream' ),
                        __( '2. In the destination, choose RTMP Server and click OK.', 'wpstream' ),
                        __( '3. In the Address box, type/paste your Address.', 'wpstream' ),
                        __( '4. In the Stream box, type/paste your Stream key.', 'wpstream' ),
                        __( '5. Click on OK to save changes just click the Output on the top of the screen and then Start/Stop Broadcasting.', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_xplit_settings',
                    'label'     => __( 'XSplit', 'wpstream' ),
                    'uri_label' => __( 'RTMP Url:', 'wpstream' ),
                    'key_label' => __( 'Stream Key:', 'wpstream' ),
                    'larix'     => false,
                    'steps'     => array(
                        __( '1. Click Broadcast in the XSplit Window and then click "Set up a new output".', 'wpstream' ),
                        __( '2. Choose Custom RTMP in the Set up a new output dropdown menu.', 'wpstream' ),
                        __( '3. In the URL box, type/paste your RTMP Url.', 'wpstream' ),
                        __( '4. In the Stream key, type/paste your Stream key.', 'wpstream' ),
                        __( '5. Save changes and click on the "Stream" button in the main window.', 'wpstream' ),
                    ),
                ),
                array(
                    'value'     => 'wpstream_larix_settings',
                    'label'     => __( 'Larix Broadcaster', 'wpstream' ),
                    'uri_label' => __( 'RTMP:', 'wpstream' ),
                    'key_label' => '',
                    'larix'     => true,
                    'steps'     => array(
                        __( 'A. Scan the QR code.', 'wpstream' ),
                        __( 'or', 'wpstream' ),
                        __( 'B. Manually configure Larix with the above RTMP', 'wpstream' ),
                    ),
                ),
            );
        }

        /**
         * Render one encoder's settings panel from its definition row.
         *
         * Standard panels show the RTMP server URI and stream key with copy
         * affordances plus that encoder's numbered setup steps; the Larix panel
         * instead shows a combined RTMP value cell and a QR code image (both
         * populated by JS) and, on mobile, a deep-link button that opens the
         * Larix app. Echoes HTML.
         *
         * @param  array  $encoder    Definition row (see wpstream_encoder_table()).
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @param  bool   $visible    Whether this panel starts visible (only the
         *                            first; JS shows/hides via the dropdown).
         * @return void
         */
        private function wpstream_encoder_panel( $encoder, $obs_uri, $obs_stream, $visible = false ){
            // Panel container; the encoder 'value' is also the CSS class the JS toggle targets.
            print '<div class="external_software_streaming '.esc_attr( $encoder['value'] ).'" style="display:'.( $visible ? 'block' : 'none' ).';">';

            if ( ! empty( $encoder['larix'] ) ) {
                // Larix takes a single combined RTMP URL (server + key); the value
                // cell and the QR image are populated by JS, so both start empty.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html( $encoder['uri_label'] ).'</strong>';
                    print '<div class="wpstream_live_uri_text wpstream_larix_rtmp"></div>
                    <div class="copy_live_uri">'.esc_html__( 'copy', 'wpstream' ).'</div>';
                    print '</div>';
                print '</div>';

                // Instructions: scan QR, configure manually, or (mobile only) tap the launch button.
                print ' <div class="wpstream_modal_explanations">';
                    print '<ul>';
                    foreach ( $encoder['steps'] as $step ) {
                        print '<li>'.esc_html( $step ).'</li>';
                    }
                    if ( wp_is_mobile() ) {
                        print '<li>'.esc_html__( 'or', 'wpstream' ).'</li>';
                        print '<li>'.esc_html__( 'C. Click on the button below', 'wpstream' ).'</li>';
                    }
                    print '</ul>';
                print '</div>';

                // QR image (src set by JS) and, on mobile, the Larix deep-link button.
                print '<img class="print_qrcode" src="" />';
                if ( wp_is_mobile() ) {
                    print '<a href="" class="wpstream_start_with_larix_mobile" >'.esc_html__( 'Start Streaming with Larix', 'wpstream' ).'</a>';
                }
            } else {
                // Server URI and stream key, each with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html( $encoder['uri_label'] ).' </strong>';
                    print '<div class="wpstream_live_uri_text">'.esc_html( $obs_uri ).'</div>
                    <div class="copy_live_uri">'.esc_html__( 'copy', 'wpstream' ).'</div>';

                    print '<div class="event_list_stream_key_wrap"><strong>'.esc_html( $encoder['key_label'] ).' </strong>
                    <div class="wpstream_live_key_text">'.esc_html( $obs_stream ).'</div><div class="copy_live_key">'.esc_html__( 'copy', 'wpstream' ).'</div></div>';
                    print '</div>';
                print '</div>';

                // This encoder's numbered setup steps.
                print ' <div class="wpstream_modal_explanations">';
                    print '<ul>';
                    foreach ( $encoder['steps'] as $step ) {
                        print '<li>'.esc_html( $step ).'</li>';
                    }
                    print '</ul>';
                print '</div>';
            }

            print '</div>';
        }

         private function wpstream_startstreaming_sidebar_meta() {
                global $post;
                $term_list                          =   wp_get_post_terms($post->ID, 'product_type');

                // Always add the sidebar controls to the free channel post type.
                add_meta_box(
                    'wpstream-sidebar-meta',
                    esc_html__('Live Streaming',  'wpstream'),
                    array( $this, 'render_sidebar' ),
                    'wpstream_product',
                    'side',
                    'high'
                );

                // For WooCommerce products, add it only for live channels / subscription-live events.
                $is_subscription_live_event =   esc_html(get_post_meta($post->ID,'_subscript_live_event',true));
                if(!is_wp_error( $term_list )){
                    if( isset($term_list[0]->slug) ){
                        if( $term_list[0]->slug=='live_stream' ||  ($term_list[0]->slug=='subscription' && $is_subscription_live_event=='yes' )  ){
                            add_meta_box('wpstream-sidebar-meta',       esc_html__('Live Streaming',  'wpstream'), array( $this, 'render_sidebar' ), 'product', 'side', 'high');
                        }
                    }
                }

        }

        /**
        * edited 4.0
        *
        * Show Streaming controls on sidebar
        *
        * Render the sidebar metabox contents: for published channels, the live
        * stream unit card (plus a basic-streaming flag when out of data) and the
        * error modal; otherwise a "publish first" prompt plus, in the block
        * editor, the script that reloads the editor once Publish completes.
        * Echoes HTML.
        *
        * @since  3.0.1
        * @return void
        */
        private function wpstream_start_stream_meta(){
            // Preload the current live-event map for the card renderer.
            global $live_event_for_user;
            $live_event_for_user    =    $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();

			global $post;
            // Streaming controls only make sense once the channel is published.
            if( get_post_status( $post->ID ) === 'publish' ) {
                // Start-event nonce for the card's ON/OFF button.
                $ajax_nonce = wp_create_nonce( "wpstream_start_event_nonce" );
                print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';

                 // Flag basic-streaming mode when the account has no data left.
                 $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_new_general_set' );
            if( isset($pack_details['available_data_mb'])){
                if ($pack_details['available_data_mb'] <= 0){
                    print '<input type="hidden" id="wpstream_basic_streaming" value="true">';
                }
            }

                // Render the channel card plus the shared modal background and error modal.
                $this->render_channel($post->ID);
                print '<div class="wpstream_modal_background"></div>';
                print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er1</div>
                <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
                </div>';
            } else {
                // Not published yet.
                esc_html_e('To Go Live, please publish your channel first !','wpstream');

                // The block editor never redraws this metabox after Publish, so a
                // small script reloads the editor once the publish has completed
                // and the controls render. The classic editor reloads by itself.
                if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
                    wp_enqueue_script(
                        'wpstream-channel-publish-reload',
                        plugin_dir_url( __DIR__ ) . 'admin/js/wpstream-channel-publish-reload.js',
                        array( 'wp-data', 'wp-editor' ),
                        WPSTREAM_PLUGIN_VERSION,
                        true
                    );
                }
            }

            /**
             * Fires at the end of the Live Streaming sidebar metabox.
             *
             * Callbacks echo their own markup and own its escaping. Ingest
             * credentials are not passed; read them through the connection if
             * the current user may stream this channel.
             *
             * @since 4.14.0
             *
             * @param int     $post_id Live Channel post ID.
             * @param WP_Post $post    The post.
             */
            do_action( 'wpstream_channel_meta_options_after', intval( $post->ID ), $post );
        }
}
