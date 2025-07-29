<?php
/**
 * Class featured category
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured category
 */
class WpStreamTheme_Featured_Category extends Widget_Base {
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
		return 'WpStreamTheme_Featured_Category';
	}

	/**
	 * Get categories
	 */
	public function get_categories() {
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
		return __( ' Featured Category', 'hello-wpstream' );
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
		return 'eicon-info-box';
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
	 * @param array $input The input data to be transformed.
	 *
	 * @return array The transformed output.
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
		$video_array              =   wpstream_return_video_array();
		$video_array_elemetor      = $this->elementor_transform( $video_array );
		
		$featured_video_type =
			array(
				1 => __( 'Type 1', 'hello-wpstream' ),
				2 => __( 'Type 2', 'hello-wpstream' ),
				
			);
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'video_id',
			[
				'label' => __( 'Select video item', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => false,
				'options' => $video_array_elemetor,
			]
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'Design Type', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $featured_video_type,
				'default' => 1,
			)
		);

	
		$this->end_controls_section();

		/*
		 *-------------------------------------------------------------------------------------------------
		 * Start Sizes
		 */

		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Item Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'places_height',
			array(
				'label'           => esc_html__( 'Item Height', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 150,
						'max' => 500,
					),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => 300,
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
					'{{WRAPPER}} .places1.featuredplace' => 'height: {{SIZE}}{{UNIT}}!important;',

				),
			)
		);

		$this->add_responsive_control(
			'item_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .places_wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

				),
			)
		);

		$this->end_controls_section();

		/*
		 *-------------------------------------------------------------------------------------------------
		 * Start Typografy
		 */

		$this->start_controls_section(
			'typography_section',
			array(
				'label' => esc_html__( 'Typography', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_title',
				'label'          => esc_html__( 'Title Typography', 'hello-wpstream' ),
	'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .featured_listing_title',
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
		$this->add_responsive_control(
			'property_title_margin_bottom',
			array(
				'label'           => esc_html__( 'Title Margin Bottom(px)', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => '',
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
					'{{WRAPPER}} .featured_listing_title' => 'margin-bottom: {{SIZE}}{{UNIT}};display:inline-block;',
				),

			)
		);

		$this->add_responsive_control(
			'property_tagline_margin_bottom',
			array(
				'label'           => esc_html__( 'Tagline Margin Bottom(px)', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => '',
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
					'{{WRAPPER}} .category_tagline' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'property_listings_margin_bottom',
			array(
				'label'           => esc_html__( 'Listings Number margin Bottom', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => -200,
						'max' => 200,
					),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => '',
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
					'{{WRAPPER}}  .featured_place_count' => 'top: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .type_3_class  .featured_place_count' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_tagline',
				'label'          => esc_html__( 'Tagline Typography ', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .category_tagline',
				'{{WRAPPER}} .places_label',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '300',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 14,
						),
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_listings',
				'label'          => esc_html__( 'Listings Text Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .featured_place_count',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '300',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 14,
						),
					),
				),
			)
		);

		$this->add_control(
			'tax_title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .featured_listing_title' => 'color: {{VALUE}}!important;',

				),
			)
		);

		$this->add_control(
			'tax_tagline_color',
			array(
				'label'     => esc_html__( 'Tagline Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}  .category_tagline' => 'color: {{VALUE}}!important;',
					'{{WRAPPER}}  .places_label'     => 'color: {{VALUE}}!important;',
				),
			)
		);

		$this->add_control(
			'tax_listings_color',
			array(
				'label'     => esc_html__( 'Listings text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}  .featured_place_count' => 'color: {{VALUE}}',

				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'discover_listings',
				'label'          => esc_html__( 'Discover Text Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .featured_more a',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '300',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 14,
						),
					),
				),
			)
		);

		$this->add_control(
			'discover_color',
			array(
				'label'     => esc_html__( 'Discover text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}  .featured_more a' => 'color: {{VALUE}}!important',
					'{{WRAPPER}}  .featured_more i' => 'color: {{VALUE}}!important',

				),
			)
		);

		$this->add_control(
			'ovarlay_color_back',
			array(
				'label'     => esc_html__( 'Image Overlay Backgorund Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}  .listing-hover-gradient' => 'background: {{VALUE}};opacity: 1;background-image:none;height:100%;',

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
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $input The input data to be processed.
	 *
	 * @return string The generated output HTML.
	 */
	public function wpstream_theme_send_to_shortcode( $input ) {
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

		$attributes['id']            = $settings['video_id'];
		$attributes['type']          = $settings['type'];
	

		echo wpstream_theme_featured_category( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
