<?php
/**
 * Recent items class
 *
 * Registers the "Video Item List" Elementor widget for the hello-wpstream
 * category. It renders a filterable, paginated grid of items (free live
 * channels, free VOD, video collections/bundles or WooCommerce products) and
 * exposes controls for the item type, count, per-row count, card style, sort
 * order, taxonomy filters, pagination style, an optional filter bar and full
 * color styling. On render it maps every control to shortcode attributes and
 * defers to wpstream_item_list_shortcodes() to produce the markup.
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

/**
 * Recent items.
 *
 * Elementor widget that outputs the theme's filterable video item list.
 */
class WpStreamTheme_Recent_Items extends \Elementor\Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_name() {
		// Unique internal identifier Elementor uses for this widget.
		return 'WpStream Recent items';
	}

	/**
	 * Retrieve categories.
	 *
	 * @return array The Elementor panel categories this widget belongs to.
	 */
	public function get_categories() {
		// Group this widget under the theme's custom Elementor category.
		return array( 'hello-wpstream' );
	}


	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_title() {
		// Human-readable label shown in the Elementor widget panel.
		return __( 'Video Item List', 'hello-wpstream' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_icon() {
		// Icon shown next to the widget name in the Elementor panel.
		return 'eicon-posts-masonry';
	}


	/**
	 * Retrieve the list of scripts the widget depended on.
	 *
	 * Used to set scripts dependencies required to run the widget.
	 *
	 * @return array Widget scripts dependencies.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_script_depends() {
		// No extra script handles are required by this widget.
		return array( '' );
	}

	/**
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @param array $input The input data to transform.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	public function elementor_transform( $input ) {
		// Build a value => label map Elementor SELECT/SELECT2 controls expect.
		$output = array();
		if ( is_array( $input ) ) {
			// Re-key each {value,label} entry into value => label.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}

	/**
	 * Register controls
	 *
	 * Builds all Content, Filters, Pagination, Filter bar and Style controls
	 * for the widget, pre-populating the taxonomy selects with the site's
	 * available terms.
	 */
	protected function register_controls() {

		// Collect the available taxonomies, dropping the generic post_tag.
		$taxonomy_data = array();
		$available_tax = wpstream_return_taxonomy_array();
		unset( $available_tax['post_tag'] );

		// For each taxonomy, generate its term options in Elementor format.
		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

		endforeach;

		// Selectable content types the list can display.
		$items_type     = array(
			'wpstream_product'     => 'Free to view live channels',
			'wpstream_product_vod' => 'Free to view VOD',
			'wpstream_bundles'     => 'Video Collections',
			'product'              => 'WooCommerce Products',
		);
		// Card layout orientation options (unused map kept for reference).
		$alignment_type = array(
			'vertical'   => 'vertical',
			'horizontal' => 'horizontal',
		);

		// Available pagination styles (value => label).
		$pagination_type = array(
			'0' => 'none',
			'1' => 'Load more',
			'2' => 'Numbers',
		);

		// Sort options, pulled from the theme helper when available.
		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}

		// --- Content tab: what to show and how many. ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Control: which content type the list renders.
		$this->add_control(
			'type',
			array(
				'label'   => __( 'What type of items', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'wpstream_product',
				'options' => $items_type,
			)
		);

		// Control: total number of items to display.
		$this->add_control(
			'number',
			array(
				'label'   => __( 'No. of items', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 9,
			)
		);


		// Control: how many items per row (2-6).
		$this->add_control(
			'rownumber',
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
		// Control: which video card design to use (type 1 or 2).
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

		// Control: default sort order for the query.
		$this->add_control(
			'sort_by',
			array(
				'label'   => __( 'Sort By ?', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $sort_options,
			)
		);

		$this->end_controls_section();

		/*
		* Start filters
		*/
		// --- Filters tab: restrict the query by taxonomy terms. ---
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Filters', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Filter: restrict to selected WordPress categories.
		$this->add_control(
			'category_ids',
			array(
				'label'       => __( 'List of categories', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['category'],
			)
		);

		// Filter: restrict to selected WpStream media categories.
		$this->add_control(
			'wpstream_category_ids',
			array(
				'label'       => __( 'List of Media Category', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_category'],
			)
		);

		// Filter: restrict to selected movie ratings.
		$this->add_control(
			'movie_ratings_ids',
			array(
				'label'       => __( 'List of Movie Ratings', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_movie_rating'],
			)
		);

		// Filter: restrict to selected actors.
		$this->add_control(
			'actors_ids',
			array(
				'label'       => __( 'List of Actors', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_actors'],
			)
		);

		$this->end_controls_section();

		/*
		* Start filters
		*/
		// --- Pagination tab: choose how the list paginates. ---
		$this->start_controls_section(
			'paginatio_section',
			array(
				'label' => esc_html__( 'Pagination', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Control: pagination style (none / load more / numbers).
		$this->add_control(
			'pagination_type',
			array(
				'label'   => __( 'What type of pagination', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 2,
				'options' => $pagination_type,
			)
		);

		$this->end_controls_section();

		/*
		* Start filters
		*/
		// --- Filter bar tab: toggle the front-end filter controls. ---
		$this->start_controls_section(
			'filter_bar_section',
			array(
				'label' => esc_html__( 'Filter bar', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Toggle: show/hide the whole filter bar (sets its display value).
		$this->add_control(
			'show_bar',
			array(
				'label'        => esc_html__( 'Show Filter Bar', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'flex',
				'default'      => 'flex',
				'selectors'    => array(
					'{{WRAPPER}}  .wpstream_item_list_filter' => 'display: {{VALUE}};',
				),
			)
		);

		// Toggle: show/hide the item-type dropdown in the filter bar.
		$this->add_control(
			'show_post_type',
			array(
				'label'        => esc_html__( 'Show Item Type', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => 'block',
				'selectors'    => array(
					'{{WRAPPER}}  .wpstream_dropdown_select_post_type' => 'display: {{VALUE}};',
				),
			)
		);

		// Toggle: show/hide the category select in the filter bar.
		$this->add_control(
			'show_bar_category',
			array(
				'label'        => esc_html__( 'Show Category select', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => 'block',
				'selectors'    => array(
					'{{WRAPPER}}  .wpstream_dropdown_select_category' => 'display: {{VALUE}};',
				),
			)
		);

		// Toggle: show/hide the actors select in the filter bar.
		$this->add_control(
			'show_bar_wpstream_actors',
			array(
				'label'        => esc_html__( 'Show Actors select', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => 'block',
				'selectors'    => array(
					'{{WRAPPER}} .wpstream_dropdown_select_wpstream_actors' => 'display: {{VALUE}};',
				),
			)
		);

		// Toggle: show/hide the media-category select in the filter bar.
		$this->add_control(
			'show_bar_wpstream_category',
			array(
				'label'        => esc_html__( 'Show Media Category select', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => 'block',
				'selectors'    => array(
					'{{WRAPPER}} .wpstream_dropdown_select_wpstream_category' => 'display: {{VALUE}};',
				),
			)
		);

		// Toggle: show/hide the movie-rating select in the filter bar.
		$this->add_control(
			'show_bar_wpstream_movie_rating',
			array(
				'label'        => esc_html__( 'Show Movie Rating select', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => '',
				'selectors'    => array(
					'{{WRAPPER}} .wpstream_dropdown_select_wpstream_movie_rating' => 'display: {{VALUE}};',
				),
			)
		);


		// Toggle: show/hide the order-by select in the filter bar.
		$this->add_control(
			'show_bar_wpstream_sort_by',
			array(
				'label'        => esc_html__( 'Show Order By select', 'hello-wpstream' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'hello-wpstream' ),
				'label_off'    => esc_html__( 'No', 'hello-wpstream' ),
				'return_value' => 'block',
				'default'      => 'block',
				'selectors'    => array(
					'{{WRAPPER}} .wpstream_dropdown_sort_by' => 'display: {{VALUE}};',
				),
			)
		);



		// Text: default placeholder label for the item-type dropdown.
		$this->add_control(
			'label_post_types',
			array(
				'label'       => __( 'Default Label for Post Types', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Select Item Type', 'hello-wpstream' ),
			)
		);

		// Text: default placeholder label for the category dropdown.
		$this->add_control(
			'label_category',
			array(
				'label'       => __( 'Default Label for Category Dropdown', 'hello-wpstream' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'separator'   => 'before',
				'default'     => esc_html__( 'Select Category', 'hello-wpstream' ),
			)
		);

		// Text: default placeholder label for the actor dropdown.
		$this->add_control(
			'label_wpstream_actors',
			array(
				'label'       => __( 'Default Label for Actor Dropdown', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Select the Actor', 'hello-wpstream' ),
			)
		);
		// Text: default placeholder label for the media-category dropdown.
		$this->add_control(
			'label_wpstream_category',
			array(
				'label'       => __( 'Default Label for Media Category Dropdown', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Select Media Category', 'hello-wpstream' ),
			)
		);

		// Text: default placeholder label for the movie-rating dropdown.
		$this->add_control(
			'label_wpstream_movie_rating',
			array(
				'label'       => __( 'Default Label for Movie Rating Dropdwn', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Select Movie Rating', 'hello-wpstream' ),
			)
		);

		$this->end_controls_section();






		// --- Style tab: filter-bar dropdown colors. ---
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Filter Bar Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Color: dropdown button background.
		$this->add_control(
			'dropdown_main_back_color',
			array(
				'label'     => esc_html__( 'Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .btn-secondary' => 'background-color: {{VALUE}}',

				),
			)
		);

		// Color: dropdown button text.
		$this->add_control(
			'dropdown_font_color',
			array(
				'label'     => esc_html__( 'Dropdowns Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .btn-secondary' => 'color: {{VALUE}}',

				),
			)
		);


		// Color: dropdown button border (and toggle arrow).
		$this->add_control(
			'dropdown_Border_color',
			array(
				'label'     => esc_html__( 'Dropdowns Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .btn-secondary' 			=> 'border-color: {{VALUE}}',
					'{{WRAPPER}} .dropdown-toggle::after'   => 'background-color: {{VALUE}}'
				),
			)
		);

		// Color: open dropdown menu background.
		$this->add_control(
			'dropdown_menu_back_color',
			array(
				'label'     => esc_html__( 'Dropdowns Menu Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .dropdown-menu' => 'background-color: {{VALUE}}',

				),
			)
		);

		// Color: dropdown menu item text.
		$this->add_control(
			'dropdown_menu_font_color',
			array(
				'label'     => esc_html__( 'Dropdowns Menu Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .dropdown-item' => 'color: {{VALUE}}',

				),
			)
		);

		// Color: dropdown menu item background on hover.
		$this->add_control(
			'dropdown_menu_hover_back_color',
			array(
				'label'     => esc_html__( 'Dropdowns Menu Hover Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .dropdown-item:hover' => 'background-color: {{VALUE}}',

				),
			)
		);

		// Color: dropdown menu item text on hover.
		$this->add_control(
			'dropdown_menu_hover_font_color',
			array(
				'label'     => esc_html__( 'Dropdowns Hover Menu Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .dropdown-item:hover' => 'color: {{VALUE}}',

				),
			)
		);


		$this->end_controls_section();

		// Style for the card details
		// --- Style tab: card text colors. ---
		$this->start_controls_section(
			'card_details_section',
			array(
				'label' => esc_html__( 'Text Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Color: card title text.
		$this->add_control(
			'video_card_title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_video_card_title' => 'color: {{VALUE}}',
				),
			)
		);

		// Color: card categories/tags text.
		$this->add_control(
			'video_card_details_color',
			array(
				'label'     => esc_html__( 'Categories/Tags Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_video_card_card_details' => 'color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();

		// --- Style tab: pagination colors and borders. ---
		$this->start_controls_section(
			'pagination_section',
			array(
				'label' => esc_html__( 'Pagination', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Color: pagination background (load-more button and page links).
		$this->add_control(
			'pagination_main_back_color',
			array(
				'label'     => esc_html__( 'Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_load_more' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link' => 'background-color: {{VALUE}}',


				),
			)
		);

		// Color: pagination text/borders (links, active page, arrow icons).
		$this->add_control(
			'pagination_font_color',
			array(
				'label'     => esc_html__( 'Pagination Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_load_more' => 'color: {{VALUE}};border-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link' => 'color: {{VALUE}}',
					'{{WRAPPER}} .page-item.active .page-link' => 'color: {{VALUE}}',
					'{{WRAPPER}} .page-item' => 'border-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link svg path' => 'fill: {{VALUE}}',
				),
			)
		);

		// Color: pagination background on hover.
		$this->add_control(
			'pagination_hover_back_color',
			array(
				'label'     => esc_html__( 'Pagination Hover Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_load_more:hover' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		// Color: pagination text/borders on hover.
		$this->add_control(
			'pagination_hover_font_color',
			array(
				'label'     => esc_html__( 'Pagination Hover Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_load_more:hover' => 'color: {{VALUE}};border-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link:hover' => 'color: {{VALUE}}',
					'{{WRAPPER}} .page-item:hover' => 'border-color: {{VALUE}}',
					'{{WRAPPER}} .page-item .page-link:hover svg path' => 'fill: {{VALUE}}',
				),
			)
		);

		// Dimensions: pagination page-item border width.
		$this->add_control(
			'pagination_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .page-item' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; border-style: solid;',
				),
			)
		);

		// Color: pagination page-item border.
		$this->add_control(
			'pagination_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .page-item' => 'border-color: {{VALUE}}',
				),
			)
		);



		$this->end_controls_section();
	}


	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @param mixed $input The input data to transform into shortcode.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	public function wpstream_send_to_shortcode( $input ) {
		// Flatten an array of selected values into a comma-separated string.
		$output = '';
		if ( !empty($input) ) {
			// Track position so separators go between items, not after the last.
			$num_items = count( $input );
			$i         = 0;

			// Append each value, adding ", " until the final element.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		// Return the joined string of selected term IDs.
		return $output;
	}

	/**
	 * Render
	 *
	 * Maps the saved control values to shortcode attributes and echoes the
	 * item list produced by wpstream_item_list_shortcodes().
	 */
	protected function render() {
		// Saved control values for this widget instance.
		$settings = $this->get_settings_for_display();
		// Unique id so multiple widgets on a page don't collide.
		$uid ='video_sh_'. wp_unique_id();

		// Content type to query.
		$attributes['type'] = isset($settings['type']) ? $settings['type'] : '';
		// Taxonomy filters: flatten each multi-select into a comma list.
		$attributes['category_ids'] = isset($settings['category_ids']) ? $this->wpstream_send_to_shortcode($settings['category_ids']) : '';
		$attributes['wpstream_category_ids'] = isset($settings['wpstream_category_ids']) ? $this->wpstream_send_to_shortcode($settings['wpstream_category_ids']) : '';
		$attributes['movie_ratings_ids'] = isset($settings['movie_ratings_ids']) ? $this->wpstream_send_to_shortcode($settings['movie_ratings_ids']) : '';
		$attributes['actors_ids'] = isset($settings['actors_ids']) ? $this->wpstream_send_to_shortcode($settings['actors_ids']) : '';
		// Count, per-row count, sort order and pagination style.
		$attributes['number'] = isset($settings['number']) ? $settings['number'] : '';
		$attributes['rownumber'] = isset($settings['rownumber']) ? $settings['rownumber'] : '';
		$attributes['sort_by'] = isset($settings['sort_by']) ? $settings['sort_by'] : '';
		$attributes['pagination_type'] = isset($settings['pagination_type']) ? $settings['pagination_type'] : '';
		// Flag so the shortcode knows it is rendering inside Elementor.
		$attributes['is_elementor'] = true;
		// Chosen card design.
		$attributes['video_card'] = isset($settings['video_card']) ? $settings['video_card'] : '';

		// Filter-bar placeholder labels.
		$attributes['label_post_types'] = isset($settings['label_post_types']) ? $settings['label_post_types'] : '';
		$attributes['label_category'] = isset($settings['label_category']) ? $settings['label_category'] : '';
		$attributes['label_wpstream_actors'] = isset($settings['label_wpstream_actors']) ? $settings['label_wpstream_actors'] : '';
		$attributes['label_wpstream_category'] = isset($settings['label_wpstream_category']) ? $settings['label_wpstream_category'] : '';
		$attributes['label_wpstream_movie_rating'] = isset($settings['label_wpstream_movie_rating']) ? $settings['label_wpstream_movie_rating'] : '';
		// Filter-bar visibility toggles.
		$attributes['show_bar_category'] = isset($settings['show_bar_category']) ? $settings['show_bar_category'] : '';
		$attributes['show_bar_wpstream_actors'] = isset($settings['show_bar_wpstream_actors']) ? $settings['show_bar_wpstream_actors'] : '';
		$attributes['show_bar_wpstream_movie_rating'] = isset($settings['show_bar_wpstream_movie_rating']) ? $settings['show_bar_wpstream_movie_rating'] : '';
		$attributes['show_bar_wpstream_category'] = isset($settings['show_bar_wpstream_category']) ? $settings['show_bar_wpstream_category'] : '';


		// Attach the unique id and hand everything to the list shortcode.
		$attributes['uid']                   = $uid;
		echo wpstream_item_list_shortcodes( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
