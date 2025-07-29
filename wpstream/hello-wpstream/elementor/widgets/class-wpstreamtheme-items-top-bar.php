<?php
/**
 * Recent items class
 *
 * @package wpstream-theme
 */

use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Files\Assets\Svg\Svg_Handler;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
/**
 * Recent items.
 */
class WpStreamTheme_Items_Top_Bar extends \Elementor\Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_name() {
		return 'WpStream Recent items with filters';
	}

	/**
	 * Retrieve categories.
	 */
	public function get_categories() {
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
		return __( 'Item List with Filters', 'hello-wpstream' );
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
		$output = array();
		if ( is_array( $input ) ) {
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}

	/**
	 * Register controls
	 */
	protected function register_controls() {

		$available_tax = wpstream_return_taxonomy_array();
		unset( $available_tax['post_tag'] );
		$available_tax_simple=array();

	

		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

			foreach( $temp_taxonomy_values as $key_id=>$valued_id ){
				$available_tax_simple[$key_id] = $valued_id;
			}


		endforeach;

		$items_type     = array(
			'wpstream_product'     => 'Free to view live channels',
			'wpstream_product_vod' => 'Free to view VOD',
			'wpstream_bundles'     => 'Video Collections',
			'product'              => 'WooCommerce Products',
		);
	

		$pagination_type = array(
			'0' => 'none',
			'1' => 'Load more',
			'2' => 'Numbers',
		);

		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}





		$this->start_controls_section(
			'section_top_var_filters',
			[
				'label' => __('Top Bar Filters', 'hello-wpstream'),
				 'tab'       => Controls_Manager::TAB_CONTENT,
			]
	); 
	 
	 $repeater = new Repeater();

	$repeater->add_control(
			'field_type', [
			'label' => esc_html__('Category Terms', 'hello-wpstream'),
			'type' => Controls_Manager::SELECT2,
			'multiple'=>false,
			'options' => $available_tax_simple,
			'default' => '',
			]
	);
	

	
	$repeater->add_control(
			'field_label', [
			'label' => esc_html__('Form Fields Label', 'hello-wpstream'),
			'type' => Controls_Manager::TEXT,
			'default' => '',
			]
	);

	   
	$repeater->add_control(
			'icon',
			[
					'label' => __( 'Icon', 'hello-wpstream' ),
					'type' => \Elementor\Controls_Manager::ICONS,
					'default' => [
							'value' => 'fas fa-star',
							'library' => 'solid',
					],
			]
	);
		
	
 
	  $this->add_control(
		'form_fields', [
		'type' => Controls_Manager::REPEATER,
		'fields' => $repeater->get_controls(),
		'default' => [
			[
				'_id' => 'name',
				'field_type' => 'property_category',
				'field_label' => esc_html__('Categories', 'hello-wpstream'),
			   
			],
		 
		],
		'title_field' => '{{{ field_label }}}',
			]
	);

	


	$this->end_controls_section();
	










		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'What type of items', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'wpstream_product',
				'options' => $items_type,
			)
		);

		$this->add_control(
			'number',
			array(
				'label'   => __( 'No. of items', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 9,
			)
		);

		
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
					6=>6, ),
				'default' => 3,
			)
		);

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
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Initial Selection', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'category_ids',
			array(
				'label'       => __( 'Select Items from Categories', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['category'],
			)
		);

		$this->add_control(
			'wpstream_category_ids',
			array(
				'label'       => __( 'Select Items from Media Category', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_category'],
			)
		);

		$this->add_control(
			'movie_ratings_ids',
			array(
				'label'       => __( 'Select Items from Movie Ratings', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_movie_rating'],
			)
		);

		$this->add_control(
			'actors_ids',
			array(
				'label'       => __( 'Select Items from Actors', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_actors'],
			)
		);

		$this->end_controls_section();

	
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Controls Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		$this->add_control(
			'controls_main_back_color',
			array(
				'label'     => esc_html__( 'Controls background color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh' => 'background-color: {{VALUE}}',

				),
			)
		);

		$this->add_control(
			'controls_font_color',
			array(
				'label'     => esc_html__( 'Controls Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh' => 'color: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh svg ' => 'fill: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh svg path' => 'fill: {{VALUE}}',

				),
			)
		);

		
	

		$this->add_control(
			'controls_hover_back_color',
			array(

				'label'     => esc_html__( 'Controls hover background color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'controls_hover_font_color',
			array(
				'label'     => esc_html__( 'Controls hover color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh:hover ' => 'color: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh:hover svg' => 'fill: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh:hover svg path' => 'fill: {{VALUE}}',
				),
			)
		);

		
		$this->add_control(
			'controls_selected_back_color',
			array(

				'label'     => esc_html__( 'Selected control background color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh_selected' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'controls_selected_font_color',
			array(
				'label'     => esc_html__( 'Selected control color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .control_tax_sh_selected ' => 'color: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh_selected svg' => 'fill: {{VALUE}}',
					'{{WRAPPER}} .control_tax_sh_selected svg path' => 'fill: {{VALUE}}',
				),
			)
		);
		


		
	$this->add_group_control(
		Group_Control_Typography::get_type(), [
			'name' => 'tab_item_typo',
			'label' => esc_html__('Tab Item Typography', 'hello-wpstream'),
			'global' => [
				'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
	        ],
			'selector' => '{{WRAPPER}} .control_tax_sh' ,
			'fields_options' => [
				// Inner control name
				'font_weight' => [
					// Inner control settings
					'default' => '500',
				],
				'font_family' => [
					'default' => 'Roboto',
				],
				'font_size' => ['default' => ['unit' => 'px', 'size' => 24]],
			],
		]
	);
		


	$this->add_responsive_control(
		'tab_item_margin', [
			'label' => esc_html__('Control Margin ', 'hello-wpstream'),
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => ['px', 'em', '%'],

			'selectors' => [
				'{{WRAPPER}} .control_tax_sh' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);


		$this->add_responsive_control(
		'controls_padding', [
		'label' => esc_html__('Internal Padding', 'hello-wpstream'),
		'type' => Controls_Manager::DIMENSIONS,
		'size_units' => ['px', 'em', '%'],
		'selectors' => [
			'{{WRAPPER}} .control_tax_sh' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
		],
			]
		);

	$this->add_responsive_control(
		'control_border_radius', [
			'label' => esc_html__('Border Radius', 'hello-wpstream'),
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => ['px', '%'],
			'selectors' => [
				'{{WRAPPER}} .control_tax_sh' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$this->add_control(
		'control_alignment',
		[
			'type' => \Elementor\Controls_Manager::CHOOSE,
			'label' => esc_html__( 'Alignment', 'hello-wpstream' ),
			'options' => [
				'left' => [
					'title' => esc_html__( 'Left', 'hello-wpstream' ),
					'icon' => 'eicon-text-align-left',
				],
				'center' => [
					'title' => esc_html__( 'Center', 'hello-wpstream' ),
					'icon' => 'eicon-text-align-center',
				],
				'right' => [
					'title' => esc_html__( 'Right', 'hello-wpstream' ),
					'icon' => 'eicon-text-align-right',
				],
			],
			'default' => 'left',
			'selectors' => [
				'{{WRAPPER}} .control_tax_wrapper' => 'justify-content: {{VALUE}};',
			],
		]
	);


		$this->end_controls_section();
		$this->start_controls_section(
			'pagination_section',
			array(
				'label' => esc_html__( 'Pagination Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
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
		$output = '';
		if ( !empty($input) ) {
			$num_items = count( $input );
			$i         = 0;

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
	 * Render
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		$attributes['form_fields']          =   $settings['form_fields'];

		$attributes['type']                  = $settings['type'];
		$attributes['category_ids']          = $this->wpstream_send_to_shortcode( $settings['category_ids'] );
		$attributes['wpstream_category_ids'] = $this->wpstream_send_to_shortcode( $settings['wpstream_category_ids'] );
		$attributes['movie_ratings_ids']     = $this->wpstream_send_to_shortcode( $settings['movie_ratings_ids'] );
		$attributes['actors_ids']            = $this->wpstream_send_to_shortcode( $settings['actors_ids'] );
		$attributes['number']                = $settings['number'];
		$attributes['rownumber']             = $settings['rownumber'];
		$attributes['sort_by']               = $settings['sort_by'];
		$attributes['video_card']               = $settings['video_card'];

		$attributes['is_elementor']          = true;



		echo wpstream_theme_recent_items_top_bar( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
