<?php
/**
 * Class featured image
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured article class
 */
class WpStreamTheme_Featured_Article extends Widget_Base {
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
		return 'WpStreamTheme_Featured_Article';
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
		return __( 'Featured Article', 'hello-wpstream' );
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
		return 'eicon-post';
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
	 * Transform input data into an associative array.
	 *
	 * This function takes an array of input data and transforms it into an associative array
	 * where each key corresponds to the value of the 'value' key in the input array, and each
	 * value corresponds to the value of the 'label' key in the input array.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param array $input An array of input data.
	 * @return array An associative array where keys are 'value' values and values are 'label' values.
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
	 * Register control
	 */
	protected function register_controls() {
		$featured_article_type = array(
			1 => esc_html__( 'Type 1', 'hello-wpstream' ),
			2 => esc_html__( 'Type 2', 'hello-wpstream' ),

		);
		$article_array              =   wpstream_return_article_array();
		$article_array_elementor    =   $this->elementor_transform($article_array);


		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'article_id',
			[
				'label' => __( 'Select article', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => false,
				'options' => $article_array_elementor,
			]
		);


		$this->add_control(
			'type',
			array(
				'label'   => __( 'Design Type', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $featured_article_type,
				'default' => 1,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Item Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

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
					'size' => 500,
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
					'{{WRAPPER}} .wpstream_featured_article.type-1 ' => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_article.type-2 .wpstream_featured_article__image ' => 'height: {{SIZE}}{{UNIT}};',

				),
			)
		);

		$this-> add_control(
			'item_border_width',
			array(
				'label'      => esc_html__( 'Border Width', 'hello-wpstream' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_featured_article' => 'border-style: solid; border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'item_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_article' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .wpstream_featured_article > .wpstream_category_unit_item_cover' => 'border-color: {{VALUE}};',
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
					'{{WRAPPER}} .wpstream_featured_article' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_featured_article > .wpstream_category_unit_item_cover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();


		$this->start_controls_section(
			'typography_section',
			array(
				'label' => esc_html__( 'Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

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

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'item_exceprt',
				'label'          => esc_html__( 'Excerpt Typography', 'hello-wpstream' ),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector'       => '{{WRAPPER}} .wpstream_featured_excerpt',
				'condition'       => array(
					'type' => '1',
				),
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '400',
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
						'default' => '400',
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


		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'item_read_more',
				'label'          => esc_html__( 'Read more Typography', 'hello-wpstream' ),
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector'       => '{{WRAPPER}} .wpstream_featured_read_more',
				'condition'       => array(
					'type' => '2',
				),
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
		$this->add_control(
			'excerpt_color',
			array(
				'label'     => esc_html__( 'Except Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',

				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_excerpt' => 'color: {{VALUE}}!important',

				),
				'condition'       => array(
					'type' => '1',
				),
			)
		);

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
		$this->add_control(
			'read_more_color',
			array(
				'label'     => esc_html__( 'Read More Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',

				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_read_more' => 'color: {{VALUE}}!important',

				),
				'condition'       => array(
					'type' => '2',
				),

			)
		);

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

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Button Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_article__link' => 'color: {{VALUE}}',

				),
			)
		);

		$this->add_control(
			'button_text_hover_color',
			array(
				'label'     => esc_html__( 'Button Text Hover Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_featured_article__link:hover' => 'color: {{VALUE}}',

				),
			)
		);

		$this->add_responsive_control(
			'content_margin', [
				'label' => esc_html__('Content Margin ', 'hello-wpstream'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', 'em', '%'],
				'selectors' => [
					'{{WRAPPER}} .wpstream_featured_article' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'label'    => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream_featured_article ',
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
	 *
	 * @access protected
	 */
	protected function render() {
		$settings           = $this->get_settings_for_display();
		$attributes['id']   = $settings['article_id'];
		$attributes['type'] = $settings['type'];
		echo wpstream_featured_article( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
