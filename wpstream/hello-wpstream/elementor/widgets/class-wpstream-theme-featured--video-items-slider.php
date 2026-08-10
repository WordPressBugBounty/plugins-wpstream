<?php
/**
 * Elementor widget: "Featured Video Items slider".
 *
 * Registers a carousel widget that shows hand-picked "featured" video items in
 * a Slick slider. Besides the content controls (which items, autoscroll speed,
 * hover-preview and Ken Burns toggles) it exposes a large set of style controls
 * for image sizing, borders, typography, colors, box shadow and the slider
 * arrows. The frontend markup is produced by `wpstream_featured_video_slider()`
 * (see hello-wpstream/elementor/functions/video_functions.php) and, in the
 * editor, an inline script keeps the slider and border preview live.
 *
 * @package wpstream-theme
 */

// Elementor base class every custom widget extends.
use Elementor\Widget_Base;
// Control-type constants (TEXT, SLIDER, COLOR, DIMENSIONS, SWITCHER, ...).
use Elementor\Controls_Manager;
// Repeater control support (imported for parity with sibling widgets).
use Elementor\Repeater;
// Legacy color scheme helper (imported but not directly used here).
use Elementor\Scheme_Color;
// Group control that bundles a full set of typography fields.
use Elementor\Group_Control_Typography;
// Global typography kit reference used as the typography defaults.
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
// Group control that bundles border style/width/color fields.
use Elementor\Group_Control_Border;
// Group control that bundles box-shadow fields.
use Elementor\Group_Control_Box_Shadow;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carousel widget that displays hand-picked featured video items.
 */
class WpStreamTheme_Featured_Video_Items_Slider extends Widget_Base {
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
		return 'WpStreamTheme_Featured_Video_Items_Slider';
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
		return __( 'Featured Video Items slider', 'hello-wpstream' );
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
		// No extra JS handles are declared here; slider JS is printed inline in render().
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
	 * Join a list of values into a single comma-separated string.
	 *
	 * @param array $input Values to join.
	 *
	 * @return string Comma-separated list (no trailing comma).
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
	 * Register all editor controls: content options plus the style tabs.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function register_controls() {

		// Fetch all selectable video posts, then reshape them for the SELECT2 control.
		$video_array              =   wpstream_return_video_array();
		$video_array_elemetor      = $this->elementor_transform( $video_array );

		// --- Content section: which items and how the slider behaves ---
		$this->start_controls_section(
			'content_section', [
				'label' => esc_html__('Content', 'hello-wpstream'),
			]
		);

		// Multi-select of the featured video items (by post ID) to show.
		$this->add_control(
			'video_id',
			[
				'label' => __( 'Select video items', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $video_array_elemetor,
			]
		);




		// Auto-advance interval in milliseconds; 0 disables auto scrolling.
		$this->add_control(
			'autoscroll',
			[
				'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
				'type' => Controls_Manager::TEXT,
				'Label Block',
				'default' => '0',

			]
		);


		// Toggle: play a short video preview when hovering a card.
		$this->add_control(
			'show_video',
			array(
				'label'        => esc_html__( 'Preview Video on mouse hover', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'yes',
				'default'      => 'no',

			)
		);
		// Toggle: apply the slow "Ken Burns" pan/zoom effect to the image.
		$this->add_control(
			'ken_burns_effect',
			[
				'label' => esc_html__( 'Ken Burns Effect (not working with video preview)', 'hello-wpstream' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'On', 'hello-wpstream' ),
				'label_off' => esc_html__( 'Off', 'hello-wpstream' ),
				'return_value' => 'yes',
				'default' => 'no',
				'selectors' => [
					'{{WRAPPER}} .wpstream_featured_video' => '{{VALUE}} == "yes" ? "wpstream-ken-burns-effect" : ""',
				],
			]
		);


		$this->end_controls_section();





		/*
		* -------------------------------------------------------------------------------------------------
		* Start typography section
	   */
		// --- Style tab: Image Settings (height, border radius, border width) ---
		$this->start_controls_section(
			'image_section', [
				'label' => esc_html__('Image Settings', 'hello-wpstream'),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		// Responsive image height per device (desktop default 350px).
		$this->add_responsive_control(
			'item_height',
			array(
				'label'           => esc_html__( 'Image Height', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 100,
						'max' => 900,
					),
				),

				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => 350,
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} .wpstream-featured-video-item-list-slide'  => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream-shortcode-wrapper'  => 'height: {{SIZE}}{{UNIT}};',

					'{{WRAPPER}} .wpstream_featured_video.type-1'  			=> 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_video.type-2 .wpstream_featured_video__image' => 'height: {{SIZE}}{{UNIT}};',

				),
			)
		);

		// Responsive corner radius applied to the image and its cover overlay.
		$this->add_responsive_control(
			'item_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_featured_video__image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_video__image > .wpstream_category_unit_item_cover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_video' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_video > .wpstream_category_unit_item_cover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Responsive border width; also switches the border-style to solid.
		$this->add_responsive_control(
			'item_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_featured_video__image' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_video' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; border-style: solid;',
					'{{WRAPPER}} .wpstream_featured_video > .wpstream_category_unit_item_cover' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Close the Image Settings section.
		$this->end_controls_section();

		// --- Style tab: typography + colors for title, excerpt, meta, buttons, overlay, dots ---
		$this->start_controls_section(
			'typography_section',
			array(
				'label' => esc_html__( 'Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Title typography (font family/size/weight) for the card heading links.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'item_title',
				'label'          => esc_html__( 'Title Typography', 'hello-wpstream' ),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector'       => '{{WRAPPER}} h1 a,{{WRAPPER}} h2 a',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '500',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 24,
						),
					),
				),
			)
		);

		// Excerpt typography for the short description text.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'item_exceprt',
				'label'          => esc_html__( 'Excerpt Typography', 'hello-wpstream' ),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector'       => '{{WRAPPER}} .wpstream_featured_excerpt',

				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						'default' => '400',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 16,
						),
					),
				),
			)
		);

		// Meta typography for the metadata line (e.g. date / category).
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'item_meta',
				'label'          => esc_html__( 'Meta Typography', 'hello-wpstream' ),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector'       => '{{WRAPPER}} .wpstream_featured_meta',

				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '500',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 24,
						),
					),
				),
			)
		);


		// Color controls: title text color (forced with !important).
		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} h1 a' => 'color: {{VALUE}}!important',
					'{{WRAPPER}} h2 a' => 'color: {{VALUE}}!important',
				),
			)
		);
		// Excerpt text color.
		$this->add_control(
			'excerpt_color',
			array(
				'label'     => esc_html__( 'Except Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',

				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_excerpt' => 'color: {{VALUE}}!important',

				),

			)
		);

		// Meta line text color.
		$this->add_control(
			'meta_color',
			array(
				'label'     => esc_html__( 'Meta Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,

				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_meta' => 'color: {{VALUE}}!important',

				),

			)
		);

		// "Play Video" button text color (normal state).
		$this->add_control(
			'play_video_button_text_color',
			array(
				'label'     => esc_html__( 'Play Video Button Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_action .wpstream_video_on_demand_play_video_container' => 'color: {{VALUE}}',
				),
			)
		);

		// "Play Video" button text color on hover.
		$this->add_control(
			'play_video_button_hover_text_color',
			array(
				'label'     => esc_html__( 'Play Video Button Hover Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_action .wpstream_video_on_demand_play_video_container:hover' => 'color: {{VALUE}}',
				),
			)
		);

		// Background color of the semi-transparent overlay drawn over each image.
		$this->add_control(
			'overlay_color',
			array(
				'label'     => esc_html__( 'Image Overlay Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',

				'selectors' => array(
					'{{WRAPPER}} .wpstream_category_unit_item_cover' => 'background-color: {{VALUE}};opacity:1;',

				),


			)
		);

		// Border color for the item image/frame (also read by the PHP/JS radius calc).
		$this->add_control(
			'item_border_color',
			array(
				'label'      => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type'       => Controls_Manager::COLOR,
				'default'    => '',
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_featured_video__image' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .wpstream_featured_video' => 'border-color: {{VALUE}};',
				),
			)
		);

		// Slider pagination: color of the currently-active dot.
		$this->add_control(
			'navigation_active_dots_color',
			array(
				'label'     => esc_html__( 'Active Dots Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-dots > li.slick-active button::before' => 'color: {{VALUE}};',
				),
			)
		);

		// Slider pagination: color of the inactive dots.
		$this->add_control(
			'navigation_dots_color',
			array(
				'label'     => esc_html__( 'Dots Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-dots > li button::before' => 'color: {{VALUE}};',
				),
			)
		);

		// Responsive margin around the card content block (and matching action width).
		$this->add_responsive_control(
			'content_margin', [
				'label' => esc_html__('Content Margin ', 'hello-wpstream'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', 'em', '%'],
				'selectors' => [
					'{{WRAPPER}} .wpstream-shortcode-wrapper .container .container-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream-shortcode-wrapper .container .container-wrapper .wpstream_featured_action' => 'width: calc(100% - {{LEFT}}{{UNIT}} - {{RIGHT}}{{UNIT}});',
				],
			]
		);

		// Close the Style section.
		$this->end_controls_section();

		// Box Shadow options
		// --- Style tab: Box Shadow section ---
		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Box-shadow group control targeting each featured video card.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'label'    => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream_featured_video ',
			)
		);

		// Close the Box Shadow section.
		$this->end_controls_section();



		// --- Style tab: Arrows Style (colors, hover, radius, position, size) ---
		$this->start_controls_section(
			'arrow_section', [
				'label' => esc_html__('Arrows Style', 'hello-wpstream'),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);


		// Arrow background color (normal state).
		$this->add_control(
			'arrows_main_back_color',
			array(
				'label'     => esc_html__( 'Arrows background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow' => 'background-color: {{VALUE}}',

				),
			)
		);

		// Arrow icon color (normal state).
		$this->add_control(
			'arrows_font_color',
			array(
				'label'     => esc_html__( 'Arrows Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow' => 'color: {{VALUE}}',

				),
			)
		);




		// Arrow background color on hover.
		$this->add_control(
			'dropdown_menu_back_color',
			array(

				'label'     => esc_html__( 'Arrows hover background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		// Arrow icon color on hover.
		$this->add_control(
			'dropdown_menu_font_color',
			array(
				'label'     => esc_html__( 'Arrows Hover Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow:hover' => 'color: {{VALUE}}',

				),
			)
		);

		// Responsive corner radius of the arrow buttons.
		$this->add_responsive_control(
			'arrow_border_radius', [
				'label' => esc_html__('Border Radius', 'hello-wpstream'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// Responsive vertical offset (top) of both arrows.
		$this->add_responsive_control(
			'arrow_margin_top', [
				'label' => esc_html__('Arrows Top Margin', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,

				'range' => [
					'px' => [
						'min' => -700,
						'max' => 700,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'top: {{SIZE}}{{UNIT}};',

				],
			]
		);

		// Responsive horizontal inset of the prev (left) and next (right) arrows.
		$this->add_responsive_control(
			'arrow_margin_sides', [
				'label' => esc_html__('Arrows Side Margins', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => -700,
						'max' => 700,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow.slick-prev' => 'left: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .slick-arrow.slick-next' => 'right: {{SIZE}}{{UNIT}};',
				],
			]
		);


		// Responsive diameter of the round arrow button (width and height).
		$this->add_responsive_control(
			'arrow_size', [
				'label' => esc_html__('Arrow Circle Size', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 60,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',

				],
			]
		);
		// Responsive size of the SVG glyph inside the arrow button.
		$this->add_responsive_control(
			'actual_arrow_size', [
				'label' => esc_html__('Arrow Size', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 20,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow svg' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',

				],
			]
		);


		// Close the Arrows Style section.
		$this->end_controls_section();



	}

	/**
	 * Render the widget on the frontend (and its live-preview script in the editor).
	 *
	 * Prints the slider markup via the shared helper, then — only inside the
	 * Elementor editor — outputs an inline script that recomputes the border
	 * preview on change and initialises the Slick carousel.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function render() {
		// Current post is referenced by the included card templates.
		global $post;
		// Saved control values for this widget instance.
		$settings = $this->get_settings_for_display();

		// Unique DOM id so multiple sliders on one page do not clash.
		$slider_id                        = 'featured_video_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		// Build and echo the slider markup from the shared helper.
		print   wpstream_featured_video_slider( $settings,	$slider_id );


		// The block below only runs inside the Elementor editor (live preview helpers).
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<script>
				// Add custom CSS
				// Mirrors the PHP border-radius/box-shadow math so the editor preview
				// updates live as the border radius/width controls change.
				(function($) {
					elementor.channels.editor.on('change', function (model) {
						if (model && model.options && model.options.elementSettingsModel && model.options.elementSettingsModel.attributes) {
							var settings = model.options.elementSettingsModel.attributes;

							// Check if the required settings exist
							if (settings.item_border_radius && settings.item_border_width) {
								var item_border_radius = settings.item_border_radius;
								var item_border_width = settings.item_border_width;

								// Perform the necessary calculations
								var calculated_top = item_border_radius.top - item_border_width.top;
								var calculated_right = item_border_radius.right - item_border_width.right;
								var calculated_bottom = item_border_radius.bottom - item_border_width.bottom;
								var calculated_left = item_border_radius.left - item_border_width.left;

								// Determine the maximum border width
								var max_border_width = Math.max(item_border_width.top, item_border_width.right, item_border_width.bottom, item_border_width.left);

								// Initialize CSS variables
								var style = '';

								// Check if any calculated value is negative
								if (calculated_top < 0 || calculated_right < 0 || calculated_bottom < 0 || calculated_left < 0) {
									style = `box-shadow: 0px 0px 0px ${max_border_width}px ${settings.item_border_color};`;
								} else {
									style = `border-radius: ${calculated_top}${item_border_width.unit} ${calculated_right}${item_border_width.unit} ${calculated_bottom}${item_border_width.unit} ${calculated_left}${item_border_width.unit} !important;`;
								}

								// Apply the style
								$('.wpstream-featured-video-item-list-slider .wpstream_featured_video > .wpstream_category_unit_item_cover').attr('style', style);
							}
						}
					});
				})(jQuery);

				// Initialise each slider instance found in the preview.
				jQuery('.wpstream-featured-video-item-list-slider').each(function () {
					var items = 1;
					// Auto-scroll interval read from the data-auto attribute.
					var auto = parseInt(jQuery(this).attr('data-auto'));
					// Boot the Slick carousel with one slide per view and custom arrows.
					var slick = jQuery(this).slick({
						infinite: true,
						slidesToShow: items,
						slidesToScroll: 1,
						dots: true,
						nextArrow:'<button class="slick-next slick-arrow 333 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.93934 0.93934C1.52513 0.353553 2.47487 0.353553 3.06066 0.93934L11.0607 8.93934C11.6464 9.52513 11.6464 10.4749 11.0607 11.0607L3.06066 19.0607C2.47487 19.6464 1.52513 19.6464 0.93934 19.0607C0.353553 18.4749 0.353553 17.5251 0.93934 16.9393L7.87868 10L0.93934 3.06066C0.353553 2.47487 0.353553 1.52513 0.93934 0.93934Z"/></svg></button>',
						prevArrow:'<button class="slick-prev slick-arrow 222 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.0607 19.0607C10.4749 19.6464 9.52513 19.6464 8.93934 19.0607L0.93934 11.0607C0.353555 10.4749 0.353555 9.52513 0.939341 8.93934L8.93934 0.93934C9.52513 0.353554 10.4749 0.353554 11.0607 0.939341C11.6464 1.52513 11.6464 2.47487 11.0607 3.06066L4.12132 10L11.0607 16.9393C11.6464 17.5251 11.6464 18.4749 11.0607 19.0607Z"/></svg></button>',

						responsive: [
							{
								breakpoint: 1025,
								settings: {
									slidesToShow: 1,
									slidesToScroll: 1
								}
							},
							{
								breakpoint: 480,
								settings: {
									slidesToShow: 1,
									slidesToScroll: 1
								}
							}
						]
					});
					// Flip the slider to right-to-left when the theme is in RTL mode.
					if (  typeof wpstream_theme !== 'undefined' && wpstream_theme.is_rtl === '1') {
						jQuery(this).slick('slickSetOption', 'rtl', true, true);
						jQuery(this).slick('slidesToScroll', '-1');
					}
				});
			</script>
		<?php

		endif;

	}


}
