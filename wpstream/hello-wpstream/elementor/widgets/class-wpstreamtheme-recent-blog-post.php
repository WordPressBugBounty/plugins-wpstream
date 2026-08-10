<?php
/**
 * Recent items class
 * Elementor widget ("Blog Post List") that renders a grid of standard WP blog
 * posts with an optional filter bar (category + sort) and pagination.
 * It registers the content, filters, pagination and filter-bar controls plus
 * the filter-bar/pagination colour style sections, then maps the settings to
 * attributes and delegates markup to `wpstream_blog_list_shortcodes()`.
 *
 * @package wpstream-theme
 */

// Elementor base class plus the control and box-shadow helpers used below.
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

/**
 * Recent items.
 */
class WpStreamTheme_Recent_Blog_Post extends \Elementor\Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_name() {
		// Unique machine id Elementor stores this widget under.
		return 'WpStream Blog Post';
	}

	/**
	 * Retrieve categories.
	 */
	public function get_categories() {
		// Show this widget under the custom hello-wpstream panel category.
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
		// Label shown on the widget tile in the editor.
		return esc_html__( 'Blog Post List', 'hello-wpstream' );
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
		// Elementor icon-font class for the widget tile.
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
		// No extra front-end script dependencies for this widget.
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
		// Collects the reshaped value => label pairs.
		$output = array();
		if ( is_array( $input ) ) {
			// Re-key each entry: term value becomes the key, label becomes the value.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		// Return the value => label map (empty when input is not an array).
		return $output;
	}

	/**
	 * Register controls
	 * Builds the Content, Filters, Pagination and Filter Bar sections plus the
	 * Filter Bar Colours and Pagination Colours style sections.
	 */
	protected function register_controls() {

        // Only standard post categories are offered as a filter here.
        $available_tax = array(
			'category'              => array('post')
        );

		// Normalise the category terms into SELECT2 value => label options.
		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			// Raw term choices for this taxonomy.
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			// Normalise to the value => label shape.
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			// Store normalised options back under the taxonomy name.
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

		endforeach;

	

		// Pagination style choices (none / load more / numbers).
		$pagination_type = array(
			'0' => 'none',
			'1' => 'Load more',
			'2' => 'Numbers',
		);

		// Sort-order options from the theme helper (when available).
		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}
    
        // Remove two sort options that do not apply to blog posts.
        unset( $sort_options[7]);
        unset( $sort_options[8]);
    
    
        // === Content section: item count, per-row count, sort ===
        $this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

	

		// Control: total number of items to load.
		$this->add_control(
			'number',
			array(
				'label'   => __( 'No. of items', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 9,
			)
		);

		
		// Control: how many items are visible per row.
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
					6=>6),
				'default' => 3,
			)
		);

		// Control: sort order for the queried posts.
		$this->add_control(
			'sort_by',
			array(
				'label'   => __( 'Sort By ?', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $sort_options,
			)
		);

		// End Content section.
		$this->end_controls_section();

		/*
		* Start filters
		*/
		// === Filters section: restrict posts by category ===
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Filters', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Filter: standard WP categories.
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

		
		

		// End Filters section.
		$this->end_controls_section();

		/*
		* Start filters
		*/
		// === Pagination section: choose the pagination style ===
		$this->start_controls_section(
			'paginatio_section',
			array(
				'label' => esc_html__( 'Pagination', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Control: which pagination style to use.
		$this->add_control(
			'pagination_type',
			array(
				'label'   => __( 'What type of pagination', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 2,
				'options' => $pagination_type,
			)
		);

		// End Pagination section.
		$this->end_controls_section();

		/*
		* Start filters
		*/
		// === Filter Bar section: toggle the front-end filter bar and its parts ===
		$this->start_controls_section(
			'filter_bar_section',
			array(
				'label' => esc_html__( 'Filter bar', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Control: show or hide the whole filter bar.
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

	

		// Control: show or hide the category select.
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
					'{{WRAPPER}}  .wpstream_blog_dropdown_category' => 'display: {{VALUE}};',
				),
			)
		);

		
		// Control: show or hide the order-by select.
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
					'{{WRAPPER}} .wpstream_blog_sort_options' => 'display: {{VALUE}};',
				),
			)
		);


		// Control: default label for the category dropdown.
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

		

		// End Filter Bar section.
		$this->end_controls_section();

		
		// === Style: filter bar (dropdown) colours ===
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Filter Bar Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Style: dropdown background colour.
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

		// Style: dropdown text colour.
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

		
		// Style: dropdown border colour.
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

		// Style: dropdown menu background colour.
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

		// Style: dropdown menu item text colour.
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

		// Style: dropdown menu item hover background colour.
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

		// Style: dropdown menu item hover text colour.
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


		// End Filter Bar Colours section.
		$this->end_controls_section();
		
		
		// === Style: pagination colours and borders ===
		$this->start_controls_section(
			'pagination_section',
			array(
				'label' => esc_html__( 'Pagination Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Style: pagination background colour.
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

		// Style: pagination text colour.
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

		// Style: pagination hover background colour.
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

		// Style: pagination hover text colour.
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



		// End Pagination Colours section.
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
		// Build a comma-separated string from the selected values.
		$output = '';
		// Only process a non-empty array of values.
		if ( !empty($input) && is_array($input) ) {
			// Track the total count so we can avoid a trailing comma.
			$num_items = count( $input );
			$i         = 0;

			// Append each value, comma-separating all but the last.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		// Return the joined "a, b, c" string.
		return $output;
	}

	/**
	 * Render
	 * Maps the editor settings to attributes and prints the blog-post list
	 * markup via `wpstream_blog_list_shortcodes()`.
	 */
	protected function render() {
		// Pull the resolved control values for this widget instance.
		$settings = $this->get_settings_for_display();
		// Unique id so multiple blog lists on a page do not collide.
		$uid ='blog_sh_'. wp_unique_id();

		// Flatten the selected categories into a comma-separated list.
		$attributes['category_ids'] = isset($settings['category_ids']) ? $this->wpstream_send_to_shortcode($settings['category_ids']) : '';

		// Copy the remaining settings through, defaulting missing ones to ''.
		$attributes['number'] = isset($settings['number']) ? $settings['number'] : '';
		$attributes['rownumber'] = isset($settings['rownumber']) ? $settings['rownumber'] : '';
		$attributes['sort_by'] = isset($settings['sort_by']) ? $settings['sort_by'] : '';
		$attributes['pagination_type'] = isset($settings['pagination_type']) ? $settings['pagination_type'] : '';
		$attributes['is_elementor'] = true;
		$attributes['label_category'] = isset($settings['label_category']) ? $settings['label_category'] : '';
		$attributes['show_bar_category'] = isset($settings['show_bar_category']) ? $settings['show_bar_category'] : '';


		// Attach the unique id for the markup helper.
		$attributes['uid']                   = $uid;
	
      // Output the blog-post list markup (helper escapes; phpcs ignore below).
      echo  wpstream_blog_list_shortcodes( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
