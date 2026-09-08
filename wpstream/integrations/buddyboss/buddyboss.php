<?php
/**
 * BuddyPress / BuddyBoss integration for WpStream.
 *
 * Loaded from integrations.php when BuddyPress (or BuddyBoss) is active. It adds
 * a "Live Video" tab to member profiles where a user can pick one of their free
 * channels and drive its live-stream controls, posts a live-player activity to
 * the timeline from the `wpstream_channel_became_active` hook and closes it on
 * `wpstream_channel_became_inactive`, keeps activity items in sync with the
 * channel's live/ended status, and whitelists the extra HTML/data attributes
 * the player markup needs so BuddyBoss's activity sanitizer does not strip
 * them. One AJAX handler backs the channel picker.
 *
 * @package    Wpstream
 * @subpackage Wpstream/integrations
 */



// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
* remove BuddyB video js files
*
*
*/


// Detaches BuddyBoss Nouveau's video script enqueue (currently not hooked in — see the commented add_action below).
function bp_init_callbackx() {
 // Unhook BuddyBoss Nouveau's video asset enqueue so its JS does not conflict with WpStream's player.
 remove_action( 'bp_nouveau_enqueue_scripts', 'bp_nouveau_video_enqueue_scripts' );

}
// Disabled: the removal above only runs if this line is re-enabled on bp_init.
//add_action( 'bp_init', 'bp_init_callbackx' );






/*
* add wpstream in mennu
*
*
*/


// Register the profile "Live Video" nav tab (priority 11 so it lands after core BuddyPress tabs).
add_action('bp_setup_nav', 'wpstream_buddypress_setup_nav', 11 );




/**
 * Print the title shown at the top of the profile "Live Video" tab.
 *
 * Hooked to bp_template_title inside wpstream_buddyboss_screen_wpstream().
 */
function wpstream_buddyboss_title() {
	esc_html_e( 'My Live Video','wpstream');
}


/**
 * Render the body of the profile "Live Video" tab.
 *
 * Shows the channel picker and live-stream controls only to the profile owner
 * when their role is allowed to stream, then always renders a player preview.
 * Hooked to bp_template_content inside wpstream_buddyboss_screen_wpstream().
 */
function wpstream_buddyboss_content() {
    global $wpstream_plugin;?>
    
    
    <div class="wpstream-bb-profile-wrapper">
      
              
                <?php
                // Viewer id vs. the profile being displayed — used to gate owner-only controls.
                $author_id                  = get_current_user_id();
                $profile_owner              = bp_get_displayed_user();
                // The viewer's own free channels, and the channel they previously selected for this tab.
                $free_events                = wpstream_buddyboss_load_free_events();
                $budyboss_selected_channel  = intval( get_user_meta($profile_owner->id,'wpstream_buddyboss_selected_channel',true) );
                // The viewer may broadcast: the plugin's single streaming-permission
                // rule (roles, regular-users option, wpstream_user_can_stream filter).
                if ( $wpstream_plugin->main->wpstream_check_user_can_stream() ) {


                    // Owner with at least one channel: show the channel selection dropdown.
                    if(count($free_events) !== 0 && $profile_owner->id == $author_id ){
                        print '<h4>'.esc_html__('Select the Channel','wpstream').'</h4>';
                        print wpstream_buddyboss_display_select_free_events($budyboss_selected_channel,$free_events);
                    }


                    // Owner: render the go-live / stop controls for the selected channel.
                    if($profile_owner->id == $author_id ){
                        print '<h4>'.esc_html__('Channel Controls','wpstream').'</h4>';
                        echo  $wpstream_plugin->wpstream_live_stream_unit_wrapper(   $budyboss_selected_channel,'front' );
                    }



                }else{
                    // Role not permitted: tell the owner they cannot stream (silent for other visitors).
                    if($profile_owner->id == $author_id ){
                        esc_html_e('Your account level does not have the permission to do live streaming!','wpstream');
                    }
                }

                // Build the attributes passed to the player shortcode/embed helper.
                $attributes['id']                   =   $budyboss_selected_channel;
                $attributes['user_id']              =   $profile_owner->id ;

                // Owner sees a "Preview" heading above the player.
                if($profile_owner->id == $author_id ){
                    print '<h4>'.esc_html__('Preview','wpstream').'</h4>';
                }


                // Always output the player embed for the selected channel.
                echo  $wpstream_plugin->wpstream_insert_player_elementor($attributes);

       
   
                ?>

                
        
    </div>
<?php
}



/**
 * Build the channel <select> dropdown for the profile tab.
 *
 * @param int   $budyboss_selected_channel Post id of the currently selected channel (marked selected).
 * @param array $free_events               Map of channel post id => channel title.
 * @return string HTML for the <select> element.
 */
function wpstream_buddyboss_display_select_free_events($budyboss_selected_channel,$free_events){
    // Open the select element the JS listens on (#wpstream_buddyboss_select_channel).
    $return_array = '<select id="wpstream_buddyboss_select_channel">';

    // One <option> per channel; pre-select the stored channel.
    foreach ($free_events as $key=>$title){
        $is_selected='';
        if($budyboss_selected_channel==$key){
            $is_selected = ' selected ';
        }
        $return_array.='<option value="'.intval($key).'" '.esc_attr($is_selected).'>'.esc_html($title).'</option>';
    }
    // Close the select and return the assembled markup.
    $return_array .='</select>';
    return $return_array;

}

/**
 * Load the current user's free (native) channels.
 *
 * @return array Map of channel post id => channel title, empty when logged out.
 */
function wpstream_buddyboss_load_free_events(){

    // Guests own no channels.
    $author_id = get_current_user_id();
    if($author_id==0){
        return array();
    }

    // Query all published wpstream_product posts authored by the current user.
    $args_free = array(
        'posts_per_page'    => -1,
        'post_type'         => 'wpstream_product',
        'post_status'       => 'publish',
        'author'            =>  $author_id


    );

    $event_list_free = new WP_Query($args_free);

    // Collect id => title pairs from the loop.
    $return_array=array();
    if( $event_list_free->have_posts() ):
        while ($event_list_free->have_posts()): $event_list_free->the_post();
            $the_id =   get_the_ID();
            $return_array[$the_id] = get_the_title($the_id) ;
        endwhile;
    endif;

    return $return_array;

}

/**
 * Screen callback for the "Live Video" profile tab.
 *
 * Wires the tab's title/content callbacks and loads the BuddyPress member
 * plugin template. Referenced by name ('wpstream_buddyboss_screen_wpstream')
 * from wpstream_buddypress_setup_nav().
 */
function wpstream_buddyboss_screen_wpstream() {
        // Attach the tab title and body renderers.
        add_action( 'bp_template_title', 'wpstream_buddyboss_title' );
        add_action( 'bp_template_content', 'wpstream_buddyboss_content' );

        // Resolve and load the members/single/plugins template shell.
        $template = apply_filters( 'bp_core_template_plugin', 'members/single/plugins' );
        bp_core_load_template( apply_filters( 'bp_stream_filter', $template ) );

}



/**
 * Add the "Live Video" navigation tab to member profiles.
 *
 * Hooked to bp_setup_nav. Skips when there is no displayed/logged-in user.
 */
function wpstream_buddypress_setup_nav() {
	global $bp;
	// Determine user to use.
	if ( bp_displayed_user_domain() ) {
		$user_domain = bp_displayed_user_domain();
	} elseif ( bp_loggedin_user_domain() ) {
		$user_domain = bp_loggedin_user_domain();
	} else {
		// No profile context to attach the tab to.
		return;
	}

	// Slug/URL pieces for the new profile tab.
	$parent_slug = 'wpstream';
	$parent_url  = trailingslashit( $user_domain . $parent_slug );
	$tab_slug    = 'wpstream-menu';

	// parent nav
	$parent_nav = array(
		'name'                => __( 'Live Video', 'wpstream' ),
		'slug'                => $parent_slug,
		'parent_slug'         => $bp->profile->slug,
		'screen_function'     => 'wpstream_buddyboss_screen_' . $parent_slug,
		'default_subnav_slug' => $tab_slug,
		'position'            => 60,
		'item_css_id'         => 'wpstream-bb-nav-' . $parent_slug,
	);


	// Register the top-level profile tab.
    bp_core_new_nav_item( $parent_nav );


}





/*
*
* Filter that runs in timeline - before activity is displayed
*
*/

// Before each activity entry renders, refresh WpStream live-stream activity items.
add_action( 'bp_before_activity_entry', 'wpstream_bb_before_activity_function',99,1 );

/**
 * Update a live-stream activity item just before it is displayed.
 *
 * For activities flagged as WpStream streams: if already marked past, rewrite
 * the content to an "ended" message; otherwise query the channel status and
 * mark it past when the stream has stopped/ended.
 *
 * @param object $activity Current activity object (unused; state read from the global template).
 */
function wpstream_bb_before_activity_function($activity) {
    global $activities_template;
    // Id of the activity currently in the loop.
    $activity_id = $activities_template->activity->id;
    $notes="wpstream_bb_before_activity_function";
    // Only act on activities tagged as a WpStream live stream.
    $is_wpstream_bb_show_id     = bp_activity_get_meta( $activity_id, 'is_wpstream_bb_show_id', true );
    if($is_wpstream_bb_show_id==='yes'){
        // The channel post id backing this activity.
        $wpstream_bb_show_id        = bp_activity_get_meta( $activity_id, 'wpstream_bb_show_id', true );

        // Whether we already recorded this stream as finished.
        $is_wpstream_bb_show_id_past_event     = bp_activity_get_meta( $activity_id, 'is_wpstream_bb_show_id_past_event', true );

        if($is_wpstream_bb_show_id_past_event=='yes'){
            // Past event: replace the content with a static "ended" notice.
            $activities_template->activity->content=  esc_html__( 'My Live Stream has ended. Join me next time! ', 'wpstream' ) ;
        }else{
            // Still potentially live: read the shared channel state (45s cache;
            // Baker is called at most once per channel per cache lifetime, no
            // matter how many members scroll the timeline).
            $state = Wpstream_Channel_State::for_channel( $wpstream_bb_show_id, $notes );

            if ( in_array( $state->status(), array( 'stopped', 'ended', 'stopping' ), true ) ) {
                // we mark the event as a past one
                bp_activity_update_meta( $activity_id, 'is_wpstream_bb_show_id_past_event','yes' );
            }


        }
    }

}


/*
*
* Filter that runs in timeline - after activity is displayed
*
*/

// After each activity entry renders, ensure the player is enqueued and refresh live HLS data.
add_action( 'bp_after_activity_entry', 'wpstream_bb_after_activity_function',99,1 );

/**
 * Enqueue the player and refresh live data for a stream activity after it renders.
 *
 * Loads the video.js + WpStream player scripts, and for a non-past stream that
 * is currently active, stores the latest stream name / HLS key retrieval URL on
 * the channel post and clears the cached stream-name transient.
 *
 * @param object $activity Current activity object (returned unchanged).
 * @return object The same activity object.
 */
function wpstream_bb_after_activity_function($activity) {
    global $activities_template;

    // Make sure the player runtime (scripts + styles) is available on the timeline page.
    wpstream_enqueue_player_assets();

    $notes='';
    $chat_url='';
    // Get the ID of the current activity item
    $activity_id                = $activities_template->activity->id;
    // Only handle activities tagged as WpStream live streams.
    $is_wpstream_bb_show_id     = bp_activity_get_meta( $activity_id, 'is_wpstream_bb_show_id', true );

    if($is_wpstream_bb_show_id==='yes'){
        // Channel post id and past/ended flag for this activity.
        $wpstream_bb_show_id        = bp_activity_get_meta( $activity_id, 'wpstream_bb_show_id', true );

        $is_wpstream_bb_show_id_past_event     = bp_activity_get_meta( $activity_id, 'is_wpstream_bb_show_id_past_event', true );


        if($is_wpstream_bb_show_id_past_event !=='yes'){

            // Warm the shared channel state (45s cache): a cache miss fetches
            // fresh data from Baker, and the state owner itself syncs the
            // stream_name / hls_key_retrieval_url meta the player reads.
            // Player bootstrap runs from the external DOMContentLoaded module.
            Wpstream_Channel_State::for_channel( $wpstream_bb_show_id, 'check from buddy boss timeline' );
        }
    }
    return $activity;
}




/*
*
* Ajax function to select the streaming channel
*
*
*/


// AJAX: store the channel the profile owner picked from the tab dropdown.
add_action( 'wp_ajax_wpstream_buddy_boss_select_channel_function', 'wpstream_buddy_boss_select_channel_function' );


/**
 * AJAX handler: persist the profile owner's selected channel.
 *
 * Verifies the posted channel belongs to the current user, then saves it to the
 * 'wpstream_buddyboss_selected_channel' user meta and returns a JSON {saved:bool} result.
 */
function wpstream_buddy_boss_select_channel_function(){
    // CSRF gate: the integrations JS sends the localized buddy_nonce as 'security'.
    check_ajax_referer( 'wpstream_buddyboss', 'security' );

    // Requested channel id and the current user.
    $show_id    =   intval($_POST['show_id']);
    $userId     =   get_current_user_id();
    $show_data  =   get_post($show_id);

    // Ownership guard: refuse channels the current user does not own.
    if( !$show_data || $show_data->post_author != $userId ){
        exit('You are not allowed to do that.This channel does not belong to you!');
    }

    // Save the selection against the user.
    $update = update_user_meta($userId,'wpstream_buddyboss_selected_channel',$show_id);

    // Return whether the meta update succeeded.
    echo json_encode(
        array(
            'saved'               =>  boolval($update),
        )
    );
    die();


}

/*
*
* Timeline activity: posted server-side when the channel becomes active
*
*/

// Post the "Tune in" activity when a channel's status becomes active, whoever observed it.
add_action( 'wpstream_channel_became_active', 'wpstream_bb_post_live_activity', 10, 1 );

/**
 * Post a BuddyPress activity that embeds the live player for a channel.
 *
 * Runs on `wpstream_channel_became_active`, so it covers browser, OBS and
 * Larix broadcasts alike. The activity is always authored by the channel's
 * post_author: the hook fires inside whichever request observed the
 * transition (the owner's status poll, or an admin managing the channel), so
 * the current user must never be used. The activity is tagged with the
 * channel id and remembered on the channel so the inactive hook can mark it
 * as a past event.
 *
 * @param int $channel_id Channel post id that became active.
 */
function wpstream_bb_post_live_activity( $channel_id ) {
    global $wpstream_plugin;

    // This runs inside the go-live request: bail quietly when the Activity component is off.
    if ( ! function_exists( 'bp_activity_post_update' ) ) {
        return;
    }

    $channel_id = intval( $channel_id );
    $owner_id   = intval( get_post_field( 'post_author', $channel_id ) );
    if ( $owner_id <= 0 ) {
        return;
    }

    // Build the player embed markup for this channel.
    $attributes['id']      = $channel_id;
    $attributes['user_id'] = '';
    $content = $wpstream_plugin->wpstream_insert_player_elementor( $attributes );

    // Link back to the owner's profile "Live Video" tab.
    $profile_link = bp_core_get_user_domain( $owner_id ) . 'wpstream';

    $new_activity = array(
        'content' => '<div class="wpstreaam_bb_see_mee_live"><a href="' . esc_url( $profile_link ) . '">' . esc_html__( 'Tune in to my Live Stream!', 'wpstream' ) . '</a></div>' . $content,
        'user_id' => $owner_id,
    );

    /**
     * Filters the arguments passed to bp_activity_post_update() for the
     * "went live" timeline activity. Return an empty array to suppress
     * the activity for this channel.
     *
     * @since 4.14.0
     *
     * @param array $new_activity Activity args (content, user_id).
     * @param int   $channel_id   Channel post ID that became active.
     */
    $new_activity = apply_filters( 'wpstream_buddyboss_activity_args', $new_activity, $channel_id );
    if ( empty( $new_activity ) ) {
        return;
    }
    // The author is not negotiable: the activity always belongs to the channel owner.
    $new_activity['user_id'] = $owner_id;

    // Publish the activity and tag it so the timeline hooks recognise and refresh it.
    $activity_id = bp_activity_post_update( $new_activity );
    if ( ! $activity_id ) {
        return;
    }
    bp_activity_update_meta( $activity_id, 'wpstream_bb_show_id', $channel_id );
    bp_activity_update_meta( $activity_id, 'is_wpstream_bb_show_id', 'yes' );
    // Remember the current session's activity so the inactive hook can close it.
    update_post_meta( $channel_id, '_wpstream_bb_activity_id', $activity_id );

    // The LIVE card badge reads a site-wide cache: drop it so the badge is right now.
    delete_transient( 'wpstream_bb_get_live_event_for_user' );
}

// Close the session's activity when the channel stops, instead of waiting for a timeline scroll.
add_action( 'wpstream_channel_became_inactive', 'wpstream_bb_end_live_activity', 10, 1 );

/**
 * Mark the channel's current live activity as a past event.
 *
 * Runs on `wpstream_channel_became_inactive`. The timeline hook
 * wpstream_bb_before_activity_function() rewrites past-event activities to
 * the "ended" message at render time, so flagging the meta is all that is
 * needed here. Also drops the LIVE badge cache.
 *
 * @param int $channel_id Channel post id that became inactive.
 */
function wpstream_bb_end_live_activity( $channel_id ) {
    $channel_id  = intval( $channel_id );
    $activity_id = intval( get_post_meta( $channel_id, '_wpstream_bb_activity_id', true ) );

    if ( $activity_id > 0 && function_exists( 'bp_activity_update_meta' ) ) {
        bp_activity_update_meta( $activity_id, 'is_wpstream_bb_show_id_past_event', 'yes' );
        delete_post_meta( $channel_id, '_wpstream_bb_activity_id' );
    }

    delete_transient( 'wpstream_bb_get_live_event_for_user' );
}


/*
*
* Filter to allow video js
*
*/

// Whitelist the player's tags/data-attributes so BuddyBoss activity sanitization keeps them.
add_filter( 'bp_activity_allowed_tags', 'wpstream_bb_activity_allowed_tags_callback', 999, 1 );
/**
 * Extend BuddyPress's allowed activity HTML with the player's elements/attributes.
 *
 * Adds the div/video/source/script/a tags and the many data-* attributes the
 * WpStream player markup relies on so they survive activity content sanitizing.
 *
 * @param array $allow_html_tags Existing allowed-tags map.
 * @return array The augmented allowed-tags map.
 */
function wpstream_bb_activity_allowed_tags_callback( $allow_html_tags ) {
    // Wrapper/player container div plus all player bootstrap data-* attributes.
    $allow_html_tags['div'] = array(
        'class' => array(),
        'id'    => array(),
        'data-now'=>array(),
        'data-me'=>array(),
        'data-product-id'=>array(),
        'data-instance-id'=>array(),
        'data-wpstream-bootstrap'=>array(),
        'data-video-element-id'=>array(),
        'data-title-overlay-element-id'=>array(),
        'data-content-url'=>array(),
        'data-stats-uri'=>array(),
        'data-chat-url'=>array(),
        'data-trailer-url'=>array(),
        'data-autoplay'=>array(),
        'data-muted'=>array(),
        'data-event-id'=>array(),
        'data-play-trailer-button-element-id'=>array(),
        'data-mute-trailer-button-element-id'=>array(),
        'data-unmute-trailer-button-element-id'=>array(),
        'data-play-video-button-element-id'=>array(),
        'playsinline'=>array(),
        'tabindex'=>array(),
        'lang'=>array(),
        'translate'=>array(),
        'role'=>array(),
        'aria-label'=>array(),
    );

    // The <video> element and its player/logo/trailer data-* attributes.
    $allow_html_tags['video'] = array(
        'class' => array(),
        'id'    => array(),
        'playsinline'=>array(),
        'data-autoplay'=>array(),
        'data-muted'=>array(),
        'data-me'=>array(),
        'data-product-id'=>array(),
        'data-instance-id'=>array(),
        'data-wpstream-bootstrap'=>array(),
        'data-video-element-id'=>array(),
        'data-title-overlay-element-id'=>array(),
        'data-video-url'=>array(),
        'data-trailer-url'=>array(),
        'data-captions-url'=>array(),
        'data-play-trailer-button-element-id'=>array(),
        'data-mute-trailer-button-element-id'=>array(),
        'data-unmute-trailer-button-element-id'=>array(),
        'data-play-video-button-element-id'=>array(),
        'data-player-logo-image'=>array(),
        'data-player-logo-position'=>array(),
        'data-player-logo-opacity'=>array(),
        'data-player-logo-width'=>array(),
        'data-player-logo-height'=>array(),
        'data-player-logo-padding'=>array(),
        'controls'=>array(),
        'muted'=>array(),
        'poster'=>array(),

    );


    // <source> for the video element.
    $allow_html_tags['source']=array(
        'src'=>array(),
        'type'=>array(),
    );
    // <script> tag the player embed may include.
    $allow_html_tags['script']=array(
        'src'=>array(),
        'type'=>array(),
    );
    // Anchor tags (e.g. the "tune in" link) with tooltip/aria attributes.
    $allow_html_tags['a']=array(
        'class' => array(),
        'href'  => array(),
        'rel'   => array(),
        'title' => array(),
        'target'=> array(),
        'aria-label'      => array(),
        'class'           => array(),
        'data-bp-tooltip' => array(),
        'id'              => array(),
        'rel'             => array(),
      
    );

   // Return the augmented map for BuddyPress to use when sanitizing activity content.
   return $allow_html_tags;
}


/**
 * Enqueue WPStream player scripts for BuddyPress activity feed
 */
function wpstream_enqueue_buddyboss_scripts() {
	// Check if we're on the activity directory, single activity page, or member activity page
	if (bp_is_activity_component() && (bp_is_activity_directory() || bp_is_single_activity() || bp_is_user_activity())) {
		// Load the full player stack on activity views.
		wpstream_enqueue_player_assets();
	}
}
// Enqueue the player assets on BuddyPress front-end pages.
add_action('bp_enqueue_scripts', 'wpstream_enqueue_buddyboss_scripts', 10);


?>
