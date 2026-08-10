<?php
/**
 * Elementor widget: "Video Items by Id".
 *
 * Registers a widget that lets an editor hand-pick specific WpStream video
 * items (by post ID) and render them as a grid. The chosen IDs, the number of
 * columns and the card style are collected as Elementor controls, then handed
 * to the shared `wpstream_theme_list_items_by_id_function()` helper (defined in
 * hello-wpstream/elementor/functions/video_functions.php) which runs the query
 * and builds the markup.
 *
 * @package wpstream-theme
 */

// Elementor base class every custom widget extends.
use Elementor\Widget_Base;
// Elementor control-type constants (SELECT, SELECT2, etc.).
use Elementor\Controls_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget that outputs a grid of video items chosen explicitly by post ID.
 */
class WpStream_Theme_List_Items_By_Id extends Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		// Internal, unique identifier Elementor uses to reference this widget.
		return 'WpStream_Theme_List_Items_By_Id';
	}

	/**
	 * Retrieve the Elementor panel categories this widget belongs to.
	 *
	 * @return array Category slugs; groups the widget under the hello-wpstream panel.
	 */
	public function get_categories() {
		// Place the widget in the theme's own "hello-wpstream" widget category.
		return array( 'hello-wpstream' );
	}


	/**
	 * Retrieve the widget title.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		// Human-readable label shown on the widget tile in the editor.
		return __( ' Video Items by Id', 'hello-wpstream' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		// Elementor icon font class shown next to the widget title.
		return 'eicon-post-list';
	}



	/**
	 * Retrieve the list of scripts the widget depended on.
	 *
	 * Used to set scripts dependencies required to run the widget.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return array Widget scripts dependencies.
	 */
	public function get_script_depends() {
		// No extra JS handles are required for this widget.
		return array( '' );
	}

	/**
	 * Flatten a list of {value,label} rows into a value => label map.
	 *
	 * Elementor SELECT/SELECT2 controls expect their options as an associative
	 * array keyed by option value, so this reshapes the theme helper output.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $input Rows shaped like array( array('value'=>.., 'label'=>..), .. ).
	 *
	 * @return array Map of option value => option label.
	 */
	public function elementor_transform( $input ) {
		// Accumulator for the reshaped options.
		$output = array();
		// Only iterate when we actually received an array to transform.
		if ( is_array( $input ) ) {
			// Re-key each row so the option value becomes the array key.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}

	/**
	 * Register control
	 */
	protected function register_controls() {
		// Fetch all selectable video posts, then reshape them for the SELECT2 control.
		$video_array              =   wpstream_return_video_array();
		$video_array_elemetor      = $this->elementor_transform( $video_array );


		// --- Content section: what to show ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Multi-select of the specific video items (by post ID) to render.
		$this->add_control(
			'video_ids',
			[
				'label' => __( 'Select video item', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $video_array_elemetor,
			]
		);


		// How many cards to place per row in the grid (2-6, default 3).
		$this->add_control(
			'items_per_row',
			array(
				'label'   => __( 'No. of items per row', 'hello-wpstream' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options'=>array(
					2=>2,
					3=>3,
					4=>4,
					5=>5,
					6=>6),
				'default' => 3,
			)
		);

		// Which card template to use when rendering each item (type 1 or 2).
		$this->add_control(
			'video_card',
			[
				'label' => __('Video Card Type', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 1,
				'options' =>  array(
					'1' => esc_html__( 'Video Card type 1', 'hello-wpstream' ),
					'2' => esc_html__( 'Video Card type 2', 'hello-wpstream' )
				)
			]
		);


		// Close the content section.
		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $input The input data to be transformed into shortcode.
	 *
	 * @return string The generated shortcode.
	 */
	public function wpstream_send_to_shortcode( $input ) {
		// Comma-separated string built from the selected values.
		$output = '';
		// Only build the list when there is at least one selection.
		if ( !empty($input) ) {
			$num_items = count( $input );
			$i         = 0;

			// Append each value, adding a ", " separator between (but not after) items.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		return $output;
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Reads the saved control values and delegates the query + markup to the
	 * shared list-items helper.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function render() {
		// Pull the editor-configured settings for this widget instance.
		$settings = $this->get_settings_for_display();

		// Map the control values onto the attribute array the helper expects.
		$attributes['video_ids']       = $settings['video_ids'];
		$attributes['items_per_row']   = $settings['items_per_row'];
		$attributes['video_card']      = $settings['video_card'];

		// Delegate to the shared helper, which queries the posts and echoes the grid.
		echo wpstream_theme_list_items_by_id_function( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
