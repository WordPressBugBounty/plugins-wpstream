<?php
/**
 * Streaming Content Creation Module.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Port used to provision a locally Created Live Channel in the WpStream cloud.
 */
interface Wpstream_Streaming_Content_Provisioning_Port {
	/**
	 * Provision a Live Channel.
	 *
	 * @param int $channel_id Local Channel ID.
	 * @return array Provisioning result.
	 */
	public function provision( $channel_id );
}

/**
 * Port used to verify completed Source Recordings in the connected account.
 */
interface Wpstream_Streaming_Content_Recording_Catalog_Port {
	/**
	 * Whether a completed Source Recording exists.
	 *
	 * @param string $recording_name Recording identity.
	 * @return bool
	 */
	public function contains_completed( $recording_name );
}

/**
 * Port exposing WordPress facts needed by Creation Eligibility.
 */
interface Wpstream_Streaming_Content_Eligibility_Port {
	public function can_stream();
	public function is_admin();
	public function maximum_live_channels( $owner_id );
	public function live_channel_count( $owner_id );
	public function can_create_paid();
}

/**
 * WordPress adapter for Creation Eligibility facts.
 */
class Wpstream_WordPress_Streaming_Content_Eligibility implements Wpstream_Streaming_Content_Eligibility_Port {
	/** @var Wpstream */
	private $main;

	public function __construct( $main ) {
		$this->main = $main;
	}

	public function can_stream() {
		return $this->main && method_exists( $this->main, 'wpstream_check_user_can_stream' ) && $this->main->wpstream_check_user_can_stream();
	}

	public function is_admin() {
		return current_user_can( 'manage_options' );
	}

	public function maximum_live_channels( $owner_id ) {
		// The framework accessor applies the wpstream_max_channels_per_user filter
		// itself; when the framework is not loaded the filter is applied to the fallback.
		if ( function_exists( 'wpstream_return_max_channels_per_user' ) ) {
			return wpstream_return_max_channels_per_user( $owner_id );
		}
		/** This filter is documented in wpstream_return_max_channels_per_user(). */
		return max( 0, intval( apply_filters( 'wpstream_max_channels_per_user', 100, intval( $owner_id ) ) ) );
	}

	public function live_channel_count( $owner_id ) {
		$free = new WP_Query(
			array(
				'post_type'      => 'wpstream_product',
				'post_status'    => 'publish',
				'author'         => intval( $owner_id ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$paid = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'author'         => intval( $owner_id ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => array( 'live_stream' ),
					),
				),
			)
		);

		return intval( $free->found_posts ) + intval( $paid->found_posts );
	}

	public function can_create_paid() {
		return function_exists( 'wpstream_return_user_can_create_paid' ) && (bool) wpstream_return_user_can_create_paid();
	}
}

/**
 * Port serializing creation for one owner.
 */
interface Wpstream_Streaming_Content_Creation_Lock_Port {
	public function acquire( $owner_id );
	public function release( $owner_id );
}

/**
 * WordPress option adapter providing an atomic, expiring per-owner lock.
 */
class Wpstream_WordPress_Streaming_Content_Creation_Lock implements Wpstream_Streaming_Content_Creation_Lock_Port {
	/** Default age in seconds after which an abandoned lock may be taken over; see `wpstream_creation_lock_max_age`. */
	const MAX_AGE = 60;

	public function acquire( $owner_id ) {
		$key = $this->key( $owner_id );
		$now = time();
		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}

		/**
		 * Filter how long a per-owner creation lock may stand before another
		 * creation takes it over, in seconds. Clamped to [1, 3600].
		 *
		 * @since 4.14.0
		 *
		 * @param int $seconds Maximum age; default 60.
		 */
		$max_age = wpstream_tunable( 'wpstream_creation_lock_max_age', self::MAX_AGE, HOUR_IN_SECONDS );

		$started_at = intval( get_option( $key, 0 ) );
		if ( $started_at && ( $now - $started_at ) > $max_age ) {
			delete_option( $key );
			return add_option( $key, $now, '', false );
		}

		return false;
	}

	public function release( $owner_id ) {
		delete_option( $this->key( $owner_id ) );
	}

	private function key( $owner_id ) {
		return 'wpstream_streaming_content_creation_lock_' . intval( $owner_id );
	}
}

/**
 * Baker provisioning adapter delegating transport to the existing live connection.
 */
class Wpstream_Baker_Streaming_Content_Provisioning implements Wpstream_Streaming_Content_Provisioning_Port {
	/** @var Wpstream_Live_Api_Connection */
	private $connection;

	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	public function provision( $channel_id ) {
		return $this->connection->wpstream_create_channel( $channel_id );
	}
}

/**
 * Baker recording-catalog adapter delegating transport to the live connection.
 */
class Wpstream_Baker_Streaming_Content_Recording_Catalog implements Wpstream_Streaming_Content_Recording_Catalog_Port {
	/** @var Wpstream_Live_Api_Connection */
	private $connection;

	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	public function contains_completed( $recording_name ) {
		$recordings = $this->connection->wpstream_get_videos();
		return is_array( $recordings ) && array_key_exists( $recording_name, $recordings );
	}
}

/**
 * Deep Module owning Streaming Content creation policy and ordering.
 */
class Wpstream_Streaming_Content_Creation {
	/** @var Wpstream_Channel_Settings */
	private $channel_settings;

	/** @var Wpstream_Streaming_Content_Provisioning_Port|null */
	private $provisioner;

	/** @var Wpstream_Streaming_Content_Recording_Catalog_Port|null */
	private $recordings;

	/** @var Wpstream_Streaming_Content_Eligibility_Port */
	private $eligibility;

	/** @var Wpstream_Streaming_Content_Creation_Lock_Port */
	private $lock;

	/** @var array<int,bool> IDs currently being published by this Module. */
	private $creating = array();

	/**
	 * @param Wpstream                                             $main Main plugin object.
	 * @param Wpstream_Channel_Settings                            $channel_settings Channel Settings Module.
	 * @param Wpstream_Streaming_Content_Provisioning_Port|null       $provisioner Cloud provisioning adapter.
	 * @param Wpstream_Streaming_Content_Recording_Catalog_Port|null $recordings Completed-recording catalog adapter.
	 * @param Wpstream_Streaming_Content_Eligibility_Port|null       $eligibility WordPress eligibility facts adapter.
	 * @param Wpstream_Streaming_Content_Creation_Lock_Port|null     $lock Per-owner creation lock adapter.
	 */
	public function __construct( $main, $channel_settings, $provisioner = null, $recordings = null, $eligibility = null, $lock = null ) {
		$this->channel_settings = $channel_settings;
		$this->provisioner      = $provisioner;
		$this->recordings       = $recordings;
		$this->eligibility      = $eligibility ? $eligibility : new Wpstream_WordPress_Streaming_Content_Eligibility( $main );
		$this->lock             = $lock ? $lock : new Wpstream_WordPress_Streaming_Content_Creation_Lock();
	}

	/**
	 * Create one fully initialized Streaming Content item.
	 *
	 * @param array $request Creation command.
	 * @return array Creation result.
	 */
	public function create( $request ) {
		/**
		 * Filter the creation command before it is validated.
		 *
		 * Runs before every check, so a callback may complete or rewrite the
		 * request (title conventions, default access) but cannot bypass
		 * validation: actor, owner, eligibility and fields are judged on the
		 * filtered value.
		 *
		 * @since 4.14.0
		 *
		 * @param array $request Creation command (actor_id, owner_id, kind, access, title, price, source_recording).
		 */
		$request = (array) apply_filters( 'wpstream_streaming_content_creation_request', (array) $request );

		$actor_id = isset( $request['actor_id'] ) ? intval( $request['actor_id'] ) : 0;
		$owner_id = isset( $request['owner_id'] ) ? intval( $request['owner_id'] ) : 0;
		$kind     = isset( $request['kind'] ) ? sanitize_key( $request['kind'] ) : '';
		$access   = isset( $request['access'] ) ? sanitize_key( $request['access'] ) : '';
		$title    = isset( $request['title'] ) ? sanitize_text_field( $request['title'] ) : '';

		$is_live = 'live_channel' === $kind;
		$is_vod  = 'vod' === $kind;
		if ( ( ! $is_live && ! $is_vod ) || ! in_array( $access, array( 'free', 'paid' ), true ) ) {
			return $this->fail( 'unsupported_variant', $request );
		}

		if ( ! $actor_id || $actor_id !== get_current_user_id() ) {
			return $this->fail( 'invalid_actor', $request );
		}

		if ( ! $actor_id || $actor_id !== $owner_id || ! get_user_by( 'id', $owner_id ) ) {
			return $this->fail( 'invalid_owner', $request );
		}

		$eligibility_error = $this->eligibility_error( $is_live, $access, $owner_id );
		if ( $eligibility_error ) {
			return $this->fail( $eligibility_error, $request );
		}

		if ( '' === trim( $title ) ) {
			return $this->fail( 'invalid_title', $request );
		}

		if ( ( $is_live && array_key_exists( 'source_recording', $request ) ) || ( 'free' === $access && array_key_exists( 'price', $request ) ) ) {
			return $this->fail( 'invalid_fields', $request );
		}

		$source_recording = '';
		if ( $is_vod ) {
			$source_recording = isset( $request['source_recording'] ) ? sanitize_text_field( $request['source_recording'] ) : '';
			if ( '' === $source_recording ) {
				return $this->fail( 'invalid_source_recording', $request );
			}
			if ( ! $this->recordings || ! $this->recordings->contains_completed( $source_recording ) ) {
				return $this->fail( 'source_recording_unavailable', $request );
			}
		}

		$price = null;
		if ( 'paid' === $access ) {
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
				return $this->fail( 'commerce_unavailable', $request );
			}
			if ( ! array_key_exists( 'price', $request ) || '' === trim( (string) $request['price'] ) || ! is_numeric( $request['price'] ) ) {
				return $this->fail( 'invalid_price', $request );
			}
			$price = (float) $request['price'];
			if ( ! is_finite( $price ) || $price < 0 ) {
				return $this->fail( 'invalid_price', $request );
			}
			$price = wc_format_decimal( $price, wc_get_price_decimals() );
		}

		if ( ! $this->lock->acquire( $owner_id ) ) {
			return $this->fail( 'creation_in_progress', $request );
		}

		try {
			$eligibility_error = $this->eligibility_error( $is_live, $access, $owner_id );
			if ( $eligibility_error ) {
				return $this->fail( $eligibility_error, $request );
			}

			$post_type = 'paid' === $access ? 'product' : ( $is_vod ? 'wpstream_product_vod' : 'wpstream_product' );

			/**
			 * Filter the post the Streaming Content item is stored as.
			 *
			 * `post_status` is the status the item is published into once it is
			 * initialised (default `publish`; e.g. `pending` for editorial
			 * review) — it is always stored as `draft` until then. `post_type`
			 * and `post_author` are fixed by the validated request and are
			 * restored after the filter; `ID` is dropped so the call can only
			 * ever insert, never overwrite an existing post.
			 *
			 * @since 4.14.0
			 *
			 * @param array $postarr wp_insert_post() arguments.
			 * @param array $request The validated creation command.
			 */
			$postarr = (array) apply_filters(
				'wpstream_streaming_content_post_args',
				array(
					'post_title'   => $title,
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => $post_type,
					'post_author'  => $owner_id,
				),
				$request
			);
			$final_status = isset( $postarr['post_status'] ) && in_array( $postarr['post_status'], get_post_stati(), true ) ? $postarr['post_status'] : 'publish';
			unset( $postarr['ID'] );
			$postarr = array_merge( $postarr, array( 'post_status' => 'draft', 'post_type' => $post_type, 'post_author' => $owner_id ) );

			$content_id = wp_insert_post( $postarr, true );

			if ( is_wp_error( $content_id ) ) {
				return $this->fail( 'storage_failed', $request );
			}

			update_post_meta( $content_id, 'wpstream_product_type', $is_live ? 1 : 2 );
			if ( $is_vod && 'free' === $access ) {
				update_post_meta( $content_id, 'wpstream_free_video', $source_recording );
			}
			if ( 'paid' === $access ) {
				$product_type = $is_live ? 'live_stream' : 'video_on_demand';
				$term_result  = wp_set_object_terms( $content_id, $product_type, 'product_type' );
				if ( is_wp_error( $term_result ) ) {
					wp_delete_post( $content_id, true );
					return $this->fail( 'initialization_failed', $request );
				}

				try {
					$product = wc_get_product( $content_id );
					if ( ! $product ) {
						throw new RuntimeException( 'WooCommerce product was not created.' );
					}
					$product->set_price( $price );
					$product->set_regular_price( $price );
					$product->set_virtual( true );
					$product->save();
					update_post_meta( $content_id, 'event_passed', 0 );
					if ( $is_vod ) {
						update_post_meta( $content_id, '_movie_url', $source_recording );
					}
				} catch ( Throwable $exception ) {
					wp_delete_post( $content_id, true );
					return $this->fail( 'initialization_failed', $request );
				}
			}
			if ( $is_live ) {
				$settings_result = $this->channel_settings->apply(
					array(
						'type'       => 'initialize',
						'channel_id' => $content_id,
					)
				);

				if ( empty( $settings_result['success'] ) ) {
					wp_delete_post( $content_id, true );
					return $this->fail( 'initialization_failed', $request );
				}
				update_post_meta( $content_id, 'wpstream_provisioning_status', 'pending' );
			}

			$this->creating[ intval( $content_id ) ] = true;
			try {
				$published = wp_update_post(
					array(
						'ID'          => $content_id,
						'post_status' => $final_status,
					),
					true
				);
			} finally {
				unset( $this->creating[ intval( $content_id ) ] );
			}

			if ( is_wp_error( $published ) ) {
				wp_delete_post( $content_id, true );
				return $this->fail( 'publication_failed', $request );
			}

			$result = array(
				'success'    => true,
				'content_id' => intval( $content_id ),
				'kind'       => $kind,
				'access'     => $access,
			);
		} finally {
			$this->lock->release( $owner_id );
		}

		$this->announce_created( intval( $content_id ), $is_live, $owner_id );

		if ( $is_live ) {
			$provisioning_result          = $this->ensure_provisioned( $content_id );
			$result['provisioning_status'] = $provisioning_result['provisioning_status'];
		} else {
			$result['provisioning_status'] = 'not_applicable';
		}

		return $result;
	}

	/**
	 * Refuse a creation: the single place every refusal passes through.
	 *
	 * @param string $error   Error code (`invalid_title`, `creation_in_progress`, ...).
	 * @param array  $request The creation command as received (after the request filter).
	 * @return array Failure result.
	 */
	private function fail( $error, $request ) {
		/**
		 * Fires when a creation command is refused or could not be completed.
		 *
		 * @since 4.14.0
		 *
		 * @param string $error   Error code.
		 * @param array  $request The creation command.
		 */
		do_action( 'wpstream_streaming_content_creation_failed', $error, $request );

		return array( 'success' => false, 'error' => $error );
	}

	/**
	 * Ensure a Created Live Channel has one persisted cloud identity.
	 *
	 * @param int $content_id Local Live Channel ID.
	 * @return array Provisioning result.
	 */
	public function ensure_provisioned( $content_id ) {
		$content_id = intval( $content_id );
		$post       = get_post( $content_id );
		$is_live    = $post instanceof WP_Post
			&& ( 'wpstream_product' === $post->post_type || ( 'product' === $post->post_type && has_term( 'live_stream', 'product_type', $content_id ) ) );
		if ( ! $is_live ) {
			return array( 'success' => false, 'error' => 'not_live_channel', 'provisioning_status' => 'not_applicable' );
		}

		$remote_channel_id = sanitize_text_field( get_post_meta( $content_id, 'channelId', true ) );
		if ( $remote_channel_id ) {
			update_post_meta( $content_id, '_wpstream_remote_channel_created', 1 );
			$this->mark_provisioned( $content_id );
			return array(
				'success'             => true,
				'content_id'          => $content_id,
				'channel_id'          => $remote_channel_id,
				'provisioning_status' => 'provisioned',
			);
		}

		if ( ! $this->provisioner ) {
			update_post_meta( $content_id, 'wpstream_provisioning_status', 'pending' );
			return array( 'success' => false, 'error' => 'provisioning_unavailable', 'provisioning_status' => 'pending' );
		}

		update_post_meta( $content_id, 'wpstream_provisioning_status', 'pending' );
		try {
			$provisioning_result = $this->provisioner->provision( $content_id );
		} catch ( Throwable $exception ) {
			$provisioning_result = array( 'success' => false );
		}
		$remote_channel_id = is_array( $provisioning_result ) && ! empty( $provisioning_result['success'] ) && ! empty( $provisioning_result['channel_id'] )
			? sanitize_text_field( $provisioning_result['channel_id'] )
			: '';

		if ( ! $remote_channel_id ) {
			update_post_meta( $content_id, 'wpstream_provisioning_status', 'failed' );
			$result = array( 'success' => false, 'error' => 'provisioning_failed', 'provisioning_status' => 'failed' );

			/**
			 * Fires when a Live Channel could not obtain its cloud identity.
			 *
			 * The channel stays published with status `failed`; a later
			 * ensure_provisioned() retries.
			 *
			 * @since 4.14.0
			 *
			 * @param int   $content_id Local Live Channel ID.
			 * @param array $result     The failure result (success, error, provisioning_status).
			 */
			do_action( 'wpstream_channel_provisioning_failed', $content_id, $result );

			return $result;
		}

		update_post_meta( $content_id, 'channelId', $remote_channel_id );
		if ( defined( 'LIVE_PLAYER_URL_PREFIX' ) ) {
			update_post_meta( $content_id, 'embedUrl', LIVE_PLAYER_URL_PREFIX . '?' . http_build_query( array( 'channelId' => $remote_channel_id ) ) );
		}
		update_post_meta( $content_id, '_wpstream_remote_channel_created', 1 );
		$this->mark_provisioned( $content_id );

		return array(
			'success'             => true,
			'content_id'          => $content_id,
			'channel_id'          => $remote_channel_id,
			'provisioning_status' => 'provisioned',
		);
	}

	/**
	 * Announce that Streaming Content has been Created, exactly once per post.
	 *
	 * Both creation paths (the Module's create() and a wp-admin publish
	 * routed through initialize_existing()) call this; a one-time post meta
	 * keeps an unpublish/republish cycle from announcing creation again.
	 *
	 * @param int  $content_id Published Streaming Content post ID.
	 * @param bool $is_live    True for a Live Channel, false for a VOD.
	 * @param int  $owner_id   User ID that owns the post (post_author).
	 */
	private function announce_created( $content_id, $is_live, $owner_id ) {
		if ( get_post_meta( $content_id, '_wpstream_created_announced', true ) ) {
			return;
		}
		update_post_meta( $content_id, '_wpstream_created_announced', 1 );

		if ( $is_live ) {
			/**
			 * Fires once a Live Channel has been Created: the post is published
			 * and fully initialized locally. It is NOT yet Provisioned in the
			 * cloud — listen to `wpstream_channel_provisioned` for that.
			 *
			 * @since 4.14.0
			 *
			 * @param int $channel_id Live Channel post ID.
			 * @param int $owner_id   User ID that owns the channel (post_author).
			 */
			do_action( 'wpstream_channel_created', $content_id, $owner_id );
			return;
		}

		/**
		 * Fires once a VOD has been Created: the post is published and its
		 * source recording is attached. VODs need no cloud provisioning.
		 *
		 * @since 4.14.0
		 *
		 * @param int $vod_id   VOD post ID.
		 * @param int $owner_id User ID that owns the VOD (post_author).
		 */
		do_action( 'wpstream_vod_created', $content_id, $owner_id );
	}

	/**
	 * Persist the `provisioned` Provisioning status and announce the transition.
	 *
	 * The hook fires only on a real transition from `pending`/`failed`, so
	 * repeated ensure_provisioned() calls on a Provisioned channel stay silent.
	 *
	 * @param int $content_id Live Channel post ID.
	 */
	private function mark_provisioned( $content_id ) {
		$previous = get_post_meta( $content_id, 'wpstream_provisioning_status', true );
		update_post_meta( $content_id, 'wpstream_provisioning_status', 'provisioned' );

		// Only `pending` and `failed` are real transitions. An empty status
		// means a channel provisioned before this status existed: backfill
		// the meta silently instead of announcing an old event.
		if ( ! in_array( $previous, array( 'pending', 'failed' ), true ) ) {
			return;
		}

		/**
		 * Fires when a Live Channel becomes Provisioned: its cloud identity
		 * (`channelId` meta) is persisted and it can be started. Also fires
		 * when a previously failed provisioning is retried successfully.
		 *
		 * @since 4.14.0
		 *
		 * @param int $channel_id Live Channel post ID.
		 */
		do_action( 'wpstream_channel_provisioned', $content_id );
	}

	/**
	 * Apply shared initialization policy to manually published Streaming Content.
	 *
	 * @param int $content_id Existing WordPress post ID.
	 * @return array Initialization result.
	 */
	public function initialize_existing( $content_id ) {
		$content_id = intval( $content_id );
		$post       = get_post( $content_id );
		if ( ! $post instanceof WP_Post ) {
			return array( 'success' => false, 'error' => 'invalid_content' );
		}

		$kind   = '';
		$access = '';
		$is_paid_live = 'product' === $post->post_type && has_term( 'live_stream', 'product_type', $content_id );
		$is_paid_vod  = 'product' === $post->post_type && has_term( 'video_on_demand', 'product_type', $content_id );
		if ( $is_paid_live && $is_paid_vod ) {
			error_log( 'WpStream inconsistent existing Streaming Content: ' . $content_id );
			return array( 'success' => false, 'error' => 'inconsistent_existing_content' );
		}
		if ( 'wpstream_product' === $post->post_type ) {
			$kind   = 'live_channel';
			$access = 'free';
		} elseif ( 'wpstream_product_vod' === $post->post_type ) {
			$kind   = 'vod';
			$access = 'free';
		} elseif ( $is_paid_live ) {
			$kind   = 'live_channel';
			$access = 'paid';
		} elseif ( $is_paid_vod ) {
			$kind   = 'vod';
			$access = 'paid';
		}

		if ( ! $kind ) {
			return array( 'success' => false, 'error' => 'unsupported_existing_content' );
		}

		if ( '' === get_post_meta( $content_id, 'wpstream_product_type', true ) ) {
			update_post_meta( $content_id, 'wpstream_product_type', 'live_channel' === $kind ? 1 : 2 );
		}
		if ( 'paid' === $access && '' === get_post_meta( $content_id, 'event_passed', true ) ) {
			update_post_meta( $content_id, 'event_passed', 0 );
		}

		if ( 'live_channel' === $kind ) {
			$customized = get_post_meta( $content_id, 'local_event_options', true );
			$snapshot   = get_post_meta( $content_id, 'wpstream_channel_settings_snapshot', true );
			if ( ! is_array( $customized ) && ! is_array( $snapshot ) ) {
				$settings_result = $this->channel_settings->apply(
					array(
						'type'       => 'initialize',
						'channel_id' => $content_id,
					)
				);
				if ( empty( $settings_result['success'] ) ) {
					return array( 'success' => false, 'error' => 'initialization_failed' );
				}
			}
		}

		$result = array(
			'success'    => true,
			'content_id' => $content_id,
			'kind'       => $kind,
			'access'     => $access,
		);
		if ( isset( $this->creating[ $content_id ] ) ) {
			// create() is publishing this post itself and announces it once
			// the creation lock is released.
			$result['provisioning_status'] = 'live_channel' === $kind ? 'pending' : 'not_applicable';
			return $result;
		}

		$this->announce_created( $content_id, 'live_channel' === $kind, intval( $post->post_author ) );

		if ( 'live_channel' === $kind ) {
			$provisioning                    = $this->ensure_provisioned( $content_id );
			$result['provisioning_status']   = $provisioning['provisioning_status'];
		} else {
			$result['provisioning_status'] = 'not_applicable';
		}

		return $result;
	}

	/**
	 * Return the first Creation Eligibility failure, or an empty string.
	 *
	 * The built-in refusals (streaming permission, channel cap, paid-creation
	 * policy) are final; the filter may only add a refusal on top of them.
	 *
	 * @param bool   $is_live  True for a Live Channel, false for a VOD.
	 * @param string $access   'free' or 'paid'.
	 * @param int    $owner_id Owner of the content to create.
	 * @return string Error code, or '' when creation may proceed.
	 */
	private function eligibility_error( $is_live, $access, $owner_id ) {
		$error = '';
		if ( ! $this->eligibility->can_stream() ) {
			$error = 'not_eligible';
		} elseif ( $is_live && ! $this->eligibility->is_admin() && $this->eligibility->live_channel_count( $owner_id ) >= $this->eligibility->maximum_live_channels( $owner_id ) ) {
			$error = 'channel_limit_reached';
		} elseif ( 'paid' === $access && ! $this->eligibility->is_admin() && ! $this->eligibility->can_create_paid() ) {
			$error = 'paid_creation_not_allowed';
		}

		/**
		 * Filters the Creation Eligibility verdict.
		 *
		 * Only a non-empty return is honoured: a callback may add a refusal
		 * (its own error code) but cannot clear the channel cap or the
		 * paid-creation policy by returning ''.
		 *
		 * @since 4.14.0
		 *
		 * @param string $error    Built-in error code, '' when creation may proceed.
		 * @param bool   $is_live  True for a Live Channel, false for a VOD.
		 * @param string $access   'free' or 'paid'.
		 * @param int    $owner_id Owner of the content to create.
		 */
		$filtered = apply_filters( 'wpstream_creation_eligibility_error', $error, $is_live, $access, $owner_id );

		return ( '' === $error && is_string( $filtered ) && '' !== $filtered ) ? sanitize_key( $filtered ) : $error;
	}
}
