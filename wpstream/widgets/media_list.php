<?php
/**
 * Elementor widgets: WpStream Channel List and Video on Demand List.
 *
 * Both lists are the same widget parameterized by Media List kind: one abstract
 * base builds the controls from the Media List module's catalog and delegates
 * rendering to the module; the two concrete classes only name the kind, the
 * title, and the forced legacy product_type. The live-only switch exists only
 * where the catalog says the kind supports it (Channels).
 *
 * Widget names (get_name()) are stored in customers' _elementor_data and must
 * not change.
 *
 * @package    Wpstream
 * @subpackage Wpstream/widgets
 */

namespace ElementorWpStream\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Media List widget; subclasses pick the kind.
 */
abstract class Wpstream_Media_List_Widget extends Widget_Base {

	/**
	 * Media List kind handled by this widget: `live_channel` or `vod`.
	 *
	 * @return string
	 */
	abstract protected function kind();

	/**
	 * Legacy numeric product_type the renderer expects (1 = Live Event, 2 = VOD).
	 *
	 * @return int
	 */
	abstract protected function product_type();

	/**
	 * Every WpStream widget lives under the WpStream panel category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'wpstream' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-play-o';
	}

	/**
	 * Register the editor controls from the Media List catalog.
	 *
	 * @return void
	 */
	protected function register_controls() {
		global $wpstream_plugin;
		$catalog = $wpstream_plugin->media_list->catalog( $this->kind() );

		$this->start_controls_section( 'section_content', array( 'label' => __( 'Content', 'wpstream' ) ) );

		// Free vs. paid filter; numeric keys are what the renderer accepts.
		$this->add_control(
			'product_type_free_paid',
			array(
				'label'   => __( 'Show Free or Paid Media ?', 'wpstream' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 0,
				'options' => array(
					0 => $catalog['choices']['access']['free'],
					1 => $catalog['choices']['access']['paid'],
				),
			)
		);

		// Live-only switch, only for kinds that can be live (Channels).
		if ( $catalog['supports_active_only'] ) {
			$this->add_control(
				'product_show_live',
				array(
					'label'        => __( 'Only show active channels', 'wpstream' ),
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'wpstream' ),
					'label_off'    => __( 'No', 'wpstream' ),
					'default'      => 'no',
					'return_value' => 'yes',
					'description'  => __( 'Only show channels that are live streaming right now.', 'wpstream' ),
				)
			);
		}

		// Grid columns; capped at 4 by the renderer.
		$this->add_control(
			'row_number',
			array(
				'label'       => __( 'Number of Items per row', 'wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'How many items will be displayed per row. Maximum no is 4', 'wpstream' ),
				'default'     => $catalog['defaults']['columns'],
			)
		);

		// Pagination size.
		$this->add_control(
			'media_number',
			array(
				'label'       => __( 'Number of Items per Page', 'wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'How many items will be displayed per page', 'wpstream' ),
				'default'     => $catalog['defaults']['items_per_page'],
			)
		);

		// Call-to-action label on free items.
		$this->add_control(
			'free_label',
			array(
				'label'       => __( 'Link Label for free items', 'wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => $catalog['builder_defaults']['free_label'],
				'description' => __( 'Link Label for free items', 'wpstream' ),
			)
		);

		// Ordering; numeric keys are what the renderer accepts.
		$this->add_control(
			'order_by',
			array(
				'label'   => __( 'Order by', 'wpstream' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 0,
				'options' => array(
					0 => $catalog['choices']['order']['date_asc'],
					1 => $catalog['choices']['order']['date_desc'],
					2 => $catalog['choices']['order']['title_asc'],
					3 => $catalog['choices']['order']['title_desc'],
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Map the editor settings onto the Media List renderer and print its HTML.
	 *
	 * @return void
	 */
	protected function render() {
		global $wpstream_plugin;
		$settings = $this->get_settings_for_display();

		$attributes = array(
			'product_type'           => $this->product_type(),
			'product_type_free_paid' => $settings['product_type_free_paid'],
			'media_number'           => $settings['media_number'],
			'row_number'             => $settings['row_number'],
			'free_label'             => $settings['free_label'],
			'order_by'               => $settings['order_by'],
			// Kinds without the switch are never filtered to live-only.
			'product_show_live'      => isset( $settings['product_show_live'] ) ? $settings['product_show_live'] : 'no',
		);

		echo $wpstream_plugin->media_list->render( $this->kind(), $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer returns escaped markup.
	}
}

/**
 * Channel (live event) list widget.
 */
class Wpstream_Media_List_Channel extends Wpstream_Media_List_Widget {

	/** @inheritDoc */
	protected function kind() {
		return 'live_channel';
	}

	/** @inheritDoc */
	protected function product_type() {
		return 1;
	}

	/** @inheritDoc */
	public function get_name() {
		return 'Wpstream_Media_List_Channel';
	}

	/** @inheritDoc */
	public function get_title() {
		return '<div class="wpstream_elementor_widget_title">' . __( 'WpStream - Channel List', 'wpstream' ) . '</div>';
	}
}

/**
 * Video on demand list widget.
 */
class Wpstream_Media_List_Vod extends Wpstream_Media_List_Widget {

	/** @inheritDoc */
	protected function kind() {
		return 'vod';
	}

	/** @inheritDoc */
	protected function product_type() {
		return 2;
	}

	/** @inheritDoc */
	public function get_name() {
		return 'Wpstream_Media_List_Vod';
	}

	/** @inheritDoc */
	public function get_title() {
		return '<div class="wpstream_elementor_widget_title">' . __( 'WpStream - Video on Demand List', 'wpstream' ) . '</div>';
	}
}
