<?php
/**
 * Admin field that binds a Better Messages chat room to streaming content.
 *
 * Rooms are normally created on first render by
 * wpstream_create_better_messages_chat(); this metabox lets an operator bind an
 * existing `bpbm-chat` room instead (previously only possible through the
 * Custom Fields panel). The metabox appears only while Better Messages is
 * active, and the save path accepts only a published `bpbm-chat` post id.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @since      4.14.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Chat Room" metabox and its save handler.
 */
class Wpstream_Chat_Room_Binding {

	/** Post meta key holding the bound room id (shared with the theme framework). */
	const META_KEY = 'wpstream_chat_post_id';

	/** Nonce action / field name protecting the save. */
	const NONCE = 'wpstream_chat_room_binding';

	/**
	 * Hook the metabox and the save handler.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Add the metabox to every edit screen that can render chat (channels, VODs,
	 * WooCommerce products) when Better Messages is active.
	 */
	public function register_metabox() {
		if ( ! function_exists( 'better_messages' ) ) {
			return;
		}
		add_meta_box(
			'wpstream-chat-room-binding',
			esc_html__( 'Chat Room', 'wpstream' ),
			array( $this, 'render' ),
			array( 'wpstream_product', 'wpstream_product_vod', 'product' ),
			'side',
			'default'
		);
	}

	/**
	 * Print the room id field with its nonce.
	 *
	 * @param WP_Post $post The channel being edited.
	 */
	public function render( $post ) {
		$room_id = intval( get_post_meta( $post->ID, self::META_KEY, true ) );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<p><label for="wpstream_chat_post_id">' . esc_html__( 'Better Messages room ID', 'wpstream' ) . '</label></p>';
		echo '<input type="number" min="0" class="widefat" id="wpstream_chat_post_id" name="wpstream_chat_post_id" value="' . ( $room_id > 0 ? esc_attr( $room_id ) : '' ) . '">';
		echo '<p class="description">' . esc_html__( 'Leave empty to create a room automatically on first use.', 'wpstream' ) . '</p>';
	}

	/**
	 * Persist the binding: only a published bpbm-chat post id is accepted;
	 * an empty value clears the binding; any other value is ignored.
	 *
	 * @param int $post_id The channel being saved.
	 */
	public function save( $post_id ) {
		// Only the screens that carry the metabox.
		if ( ! in_array( get_post_type( $post_id ), array( 'wpstream_product', 'wpstream_product_vod', 'product' ), true ) ) {
			return;
		}

		// Nonce and capability gates; skip autosaves and unrelated saves.
		if ( ! isset( $_POST['wpstream_chat_post_id'], $_POST[ self::NONCE . '_nonce' ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE )
			|| ! current_user_can( 'edit_post', $post_id )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$room_id = intval( $_POST['wpstream_chat_post_id'] );

		// Empty field: unbind so the next render creates a fresh room.
		if ( 0 === $room_id ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		// Only an existing, published Better Messages room may be bound.
		if ( 'bpbm-chat' !== get_post_type( $room_id ) || 'publish' !== get_post_status( $room_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $room_id );
	}
}
