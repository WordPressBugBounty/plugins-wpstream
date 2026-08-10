<?php
/**
 * Elementor widget: "Blog Posts by ID".
 *
 * Registers a widget that lets an editor hand-pick specific blog posts and
 * render them as a grid. The chosen post IDs and the number of columns are
 * collected as Elementor controls, then passed to the shared
 * `wpstream_theme_list_blog_by_id_function()` helper which runs the query and
 * builds the markup.
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
 * Widget that outputs a grid of blog posts chosen explicitly by post ID.
 */
class WpStream_Theme_Blog_Post_By_Id extends Widget_Base {
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
		return 'WpStream_Theme_Blog_Post_By_Id';
	}

	/**
	 * Retrieve the Elementor panel categories this widget belongs to.
	 *
	 * @return array Category slugs; groups the widget under the hello-wpstream panel.
	 */
	public function get_categories() {
		// Place the widget in the theme's own "hello-wpstream" widget category.
		return array( 'hello-wpstream');
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
		return __( 'Blog Posts by ID', 'hello-wpstream' );
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
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $input The input data containing the labels and values.
	 *
	 * @return array The transformed output array.
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
	 * Register the widget's editor controls.
	 */
	protected function register_controls() {
		// Fetch all selectable blog posts, then reshape them for the SELECT2 control.
		$blog_array              =   wpstream_return_article_array();
		$blog_array_elemetor      = $this->elementor_transform( $blog_array );
		

		// --- Content section: what to show ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Multi-select of the specific blog posts (by post ID) to render.
		$this->add_control(
			'blog_ids',
			[
				'label' => __( 'Select Blog Posts ', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $blog_array_elemetor,
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
					6=>6, 
				
					),
				'default' => 3,
			)
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
	 * shared blog-by-id helper.
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
		$attributes['blog_ids']     = $settings['blog_ids'];
		$attributes['items_per_row']      = $settings['items_per_row'];

		// Delegate to the shared helper, which queries the posts and echoes the grid.
		echo wpstream_theme_list_blog_by_id_function( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
