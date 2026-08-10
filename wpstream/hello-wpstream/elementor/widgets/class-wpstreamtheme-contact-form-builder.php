<?php
/**
 * Elementor widget: Contact Form Builder.
 *
 * Registers a fully configurable front-end contact form as an Elementor widget
 * for the hello-wpstream theme. The widget lets the editor build an arbitrary
 * list of form fields (name, email, message, address, etc.) via a repeater,
 * style every part of the form (fields, labels, GDPR notice, submit button),
 * and configure the outgoing email subject. The rendered markup mirrors
 * Elementor's native form structure and is submitted over AJAX by an external
 * script (wpstream_elementor_submit_form()).
 *
 * @package    Wpstream
 * @subpackage Wpstream/hello-wpstream/elementor/widgets
 */



use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Group_Control_Border;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Elementor Properties Widget.
 * @since 2.0
 */
class WpStreamTheme_Contact_Form_Builder extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * Retrieve widget name.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'WpStreamTheme_Contact_Form_Builder';
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
		return esc_html__( 'Contact Form Builder', 'hello-wpstream' );
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
		return 'eicon-form-horizontal';
	}

	/**
	 * Retrieve the Elementor categories this widget belongs to.
	 *
	 * @access public
	 *
	 * @return array List with the single 'hello-wpstream' panel category.
	 */
	public function get_categories() {
		// Place the widget under the theme's own "hello-wpstream" panel category.
		return array( 'hello-wpstream' );
	}


	/**
	 * Register the widget's Elementor controls.
	 *
	 * Builds the editor panel: the repeater of form fields, general form
	 * options (input size, labels, GDPR agreement), the submit button section,
	 * the email settings section, and all the Style-tab sections (form, field,
	 * GDPR and button styling).
	 *
	 * @access protected
	 */
	protected function register_controls() {

		// Repeater used to collect the individual form fields the editor adds.
		$repeater = new Repeater();

		// Map of selectable field types -> human-readable labels for the dropdown.
		$form_fields = array(
			'name' => esc_html__( 'Full Name', 'hello-wpstream' ),
			'first_name' => esc_html__( 'First Name', 'hello-wpstream' ),
			'last_name' => esc_html__( 'Last Name', 'hello-wpstream' ),
			'email' => esc_html__( 'Email', 'hello-wpstream' ),
			'mobile' => esc_html__( 'Mobile', 'hello-wpstream' ),
			'phone' => esc_html__( 'Phone', 'hello-wpstream' ),
			'address' => esc_html__( 'Address', 'hello-wpstream' ),
			'message' => esc_html__( 'Message', 'hello-wpstream' ), //textarea
			'country' => esc_html__( 'Country', 'hello-wpstream' ),
			'city' => esc_html__( 'City', 'hello-wpstream' ),
			'state' => esc_html__( 'State', 'hello-wpstream' ),
			'zip' => esc_html__( 'Zip/Postal Code', 'hello-wpstream' ),
		);

		/**
		 * Forms field types.
		 */


		// Repeater control: which field type this row represents (name, email, message, ...).
		$repeater->add_control(
			'field_type',
			[
				'label' => esc_html__( 'Form Fields', 'hello-wpstream' ),
				'type' => Controls_Manager::SELECT,
				'options' => $form_fields,
				'default' => 'text',
			]
		);

		// Repeater control: the visible label printed above/next to this field.
		$repeater->add_control(
			'field_label',
			[
				'label' => esc_html__( 'Form Fields Label', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
			]
		);

		// Repeater control: placeholder text shown inside the empty input.
		$repeater->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Form Fields Placeholder', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => '!in',
							'value' => [
							],
						],
					],
				],
			]
		);

		// Repeater control: toggle marking this field as required on submit.
		$repeater->add_control(
			'required',
			[
				'label' => esc_html__( 'Required', 'hello-wpstream' ),
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'default' => '',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => '!in',
							'value' => [
							],
						],
					],
				],
			]
		);



		// Repeater responsive control: per-field column width (fraction of the row).
		$repeater->add_responsive_control(
			'width',
			[
				'label' => esc_html__( 'Column Width', 'hello-wpstream' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Default', 'hello-wpstream' ),
					'100' => '100%',
					'80' => '80%',
					'75' => '75%',
					'66' => '66%',
					'60' => '60%',
					'50' => '50%',
					'40' => '40%',
					'33' => '33%',
					'25' => '25%',
					'20' => '20%',
				],
				'default' => '100',
			]
		);

		// Repeater control: textarea row count, only shown for the 'message' field type.
		$repeater->add_control(
			'rows',
			[
				'label' => esc_html__( 'Rows', 'hello-wpstream' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 4,
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => 'in',
							'value' => [
								'message'
							],
						],
					],
				],
			]
		);



		// -----------------------------------------------------------------
		// CONTENT tab: "Form Fields" section (the repeater + general options).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_form_fields',
			[
				'label' => esc_html__( 'Form Fields', 'hello-wpstream' ),
			]
		);



		// The repeater control itself, seeded with three default fields
		// (name, email, message). Each row uses the controls defined above.
		$this->add_control(
			'form_fields',
			[
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'_id' => 'name',
						'field_type' => 'name',
						'field_label' => esc_html__( 'Name', 'hello-wpstream' ),
						'placeholder' => esc_html__( 'Name', 'hello-wpstream' ),
						'width' => '100',
					],
					[
						'_id' => 'email',
						'field_type' => 'email',
						'required' => 'true',
						'field_label' => esc_html__( 'Email', 'hello-wpstream' ),
						'placeholder' => esc_html__( 'Email', 'hello-wpstream' ),
						'width' => '100',
					],

					[
						'_id' => 'message',
						'field_type' => 'message',
						'field_label' => esc_html__( 'Message', 'hello-wpstream' ),
						'placeholder' => esc_html__( 'Message', 'hello-wpstream' ),
						'width' => '100',
					],
				],
				'title_field' => '{{{ field_label }}}',
			]
		);

		// Global input size applied to every field (xs..xl -> elementor-size-* class).
		$this->add_control(
			'form_field_input_size',
			[
				'label' => esc_html__( 'Input Size', 'hello-wpstream' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'xs' => esc_html__( 'Extra Small', 'hello-wpstream' ),
					'sm' => esc_html__( 'Small', 'hello-wpstream' ),
					'md' => esc_html__( 'Medium', 'hello-wpstream' ),
					'lg' => esc_html__( 'Large', 'hello-wpstream' ),
					'xl' => esc_html__( 'Extra Large', 'hello-wpstream' ),
				],
				'default' => 'sm',
				'separator' => 'before',
			]
		);

		// Toggle whether field labels are visible or only screen-reader visible.
		$this->add_control(
			'form_field_show_labels',
			[
				'label' => esc_html__( 'Labels', 'hello-wpstream' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'hello-wpstream' ),
				'label_off' => esc_html__( 'Hide', 'hello-wpstream' ),
				'return_value' => 'true',
				'default' => 'true',
				'separator' => 'before',
			]
		);




		// Toggle to show a GDPR consent checkbox below the fields.
		$this->add_control(
			'has_gdpr_agreement',
			[
				'label' => esc_html__( 'GDPR Agreement', 'hello-wpstream' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Show', 'hello-wpstream' ),
				'label_off' => esc_html__( 'Hide', 'hello-wpstream' ),
				'default' => 'false',
			]
		);



		// URL the GDPR consent text links to (e.g. the privacy policy page).
		$this->add_control(
			'link_gdpr_agreement', [
				'label' => __('Gdpr link', 'hello-wpstream'),
				'label_block' => true,
				'default' => '',
				'type' => Controls_Manager::TEXT,
			]
		);
		// The GDPR consent sentence itself (only relevant when the toggle is on).
		$this->add_control(
			'gdpr_text',
			[
				'label' => esc_html__( 'GDPR Agreement Text', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'I consent to the GDPR Terms', 'hello-wpstream' ),
				'description' => '',
				'condition' => [
					'has_gdpr_agreement' => 'yes',
				],
			]
		);

		// End of the "Form Fields" content section.
		$this->end_controls_section();


		/*
		*-------------------------------------------------------------------------------------------------
		* Button settings
		*/


		// -----------------------------------------------------------------
		// CONTENT tab: "Submit Button" section (label, size, width, align, id).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_submit_button',
			[
				'label' => esc_html__( 'Submit Button', 'hello-wpstream' ),
			]
		);

		// Text printed on the submit button.
		$this->add_control(
			'submit_button_text',
			[
				'label' => esc_html__( 'Text', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Send Email', 'hello-wpstream' ),
				'placeholder' => esc_html__( 'Send Email', 'hello-wpstream' ),
			]
		);

		// Button size preset (xs..xl -> elementor-size-* class).
		$this->add_control(
			'submit_button_size',
			[
				'label' => esc_html__( 'Submit Button Size', 'hello-wpstream' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'md',
				'options' => array(
					'xs' => esc_html__( 'Extra Small', 'hello-wpstream' ),
					'sm' => esc_html__( 'Small', 'hello-wpstream' ),
					'md' => esc_html__( 'Medium', 'hello-wpstream' ),
					'lg' => esc_html__( 'Large', 'hello-wpstream' ),
					'xl' => esc_html__( 'Extra Large', 'hello-wpstream' ),
				)
			]
		);

		// Button column width as a percentage of its row.
		$this->add_responsive_control(
			'submit_button_width',
			[
				'label' => esc_html__( 'Submit Button Width', 'hello-wpstream' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Default', 'hello-wpstream' ),
					'100' => '100%',
					'80' => '80%',
					'75' => '75%',
					'66' => '66%',
					'60' => '60%',
					'50' => '50%',
					'40' => '40%',
					'33' => '33%',
					'25' => '25%',
					'20' => '20%',
					'10' => '10%',
				],
				'default' => '100',
			]
		);

		// Button horizontal alignment; drives a prefix_class on the wrapper.
		$this->add_responsive_control(
			'submit_button_align',
			[
				'label' => esc_html__( 'Button Alignment', 'hello-wpstream' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Left', 'hello-wpstream' ),
						'icon' => 'fa fa-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Center', 'hello-wpstream' ),
						'icon' => 'fa fa-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Right', 'hello-wpstream' ),
						'icon' => 'fa fa-align-right',
					],
					'stretch' => [
						'title' => esc_html__( 'Justified', 'hello-wpstream' ),
						'icon' => 'fa fa-align-justify',
					],
				],
				'default' => 'stretch',
				'prefix_class' => 'elementor%s-button-align-',
			]
		);

		// Optional custom HTML id applied to the submit button element.
		$this->add_control(
			'wpstream-theme_submit_button_elementor',
			[
				'label' => esc_html__( 'Button ID', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'label_block' => false,
				'description' => esc_html__( 'Use a unique name without spaces or special characters','hello-wpstream' ),
				'separator' => 'before',

			]
		);

		// End of the "Submit Button" content section.
		$this->end_controls_section();


		/*
		*-------------------------------------------------------------------------------------------------
		* END Button settings
		*/


		/*
		*-------------------------------------------------------------------------------------------------
		* Email settings
		*/
		// -----------------------------------------------------------------
		// CONTENT tab: "Email Settings" section (outgoing subject line).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_email_settings',
			[
				'label' => esc_html__( 'Email Settings', 'hello-wpstream' ),
			]
		);



		// Default subject line "New email from <site name>" used for the notification.
		$email_subject = sprintf( esc_html__( 'New email from "%s" ', 'hello-wpstream' ), get_option( 'blogname' ) );
		// Editable email subject control (render_type 'none' -> no live re-render).
		$this->add_control(
			'email_subject',
			[
				'label' => esc_html__( 'Email Subject', 'hello-wpstream' ),
				'type' => Controls_Manager::TEXT,
				'default' => $email_subject,
				'placeholder' => $email_subject,
				'label_block' => true,
				'render_type' => 'none',
			]
		);



		// End of the "Email Settings" content section.
		$this->end_controls_section();

		/*
		*-------------------------------------------------------------------------------------------------
		* End Button settings
		*/



		// -----------------------------------------------------------------
		// STYLE tab: "Form" section (column/row gaps and label styling).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_form_style',
			[
				'label' => esc_html__( 'Form', 'hello-wpstream' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		// Horizontal gap between form columns (splits padding across field groups).
		$this->add_responsive_control(
			'wpersidence_form_column_gap',
			[
				'label' => esc_html__( 'Form Columns Gap', 'hello-wpstream' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group' => 'padding-right: calc( {{SIZE}}{{UNIT}}/2 ); padding-left: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} 	.elementor-form-fields-wrapper' => 'margin-left: calc( -{{SIZE}}{{UNIT}}/2 ); margin-right: calc( -{{SIZE}}{{UNIT}}/2 );',


				],
			]
		);

		// Vertical gap between form rows (bottom margin on each field group).
		$this->add_responsive_control(
			'wpersidence_form_row_gap',
			[
				'label' => esc_html__( 'Rows Gap', 'hello-wpstream' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
				],
			]
		);

		// Section heading separating the label-styling controls that follow.
		$this->add_control(
			'wpstream-theme_form_heading_label',
			[
				'label' => esc_html__( 'Form Label', 'hello-wpstream' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		// Spacing between a label and its field (RTL-aware padding rules).
		$this->add_responsive_control(
			'wpstream-theme_form_label_spacing',
			[
				'label' => esc_html__( 'Form Label Spacing', 'hello-wpstream' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'size' => 0,
				],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'body.rtl {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-left: {{SIZE}}{{UNIT}};',
					'body {{WRAPPER}} .elementor-labels-above .elementor-field-group > label' => 'padding-bottom: {{SIZE}}{{UNIT}};',
					'body:not(.rtl) {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-right: {{SIZE}}{{UNIT}};',

				],
			]
		);

		// Label text color for all field-group labels.
		$this->add_control(
			'wpstream-theme_form_label_color',
			[
				'label' => esc_html__( 'Label Text Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group > label'  => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-subgroup label' => 'color: {{VALUE}};',
				],

				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);



		// Typography group control for the field labels.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'wpstream-theme_form_label_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group > label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		/*-------------------------------------------------------------------------------------------------
		* End Form  settings
		*/


		/*-------------------------------------------------------------------------------------------------
		*  Form Fields settings
		*/

		// End of the "Form" style section.
		$this->end_controls_section();

		// -----------------------------------------------------------------
		// STYLE tab: "Field Style" section (input text, background, border).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_field_style',
			[
				'label' => esc_html__( 'Field Style', 'hello-wpstream' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		// Text color typed into the fields.
		$this->add_control(
			'wpstream-theme_field_text_color',
			[
				'label' => esc_html__( 'Field Text Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		// Typography group control for the field text.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'wpstream-theme_field_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group .elementor-field, {{WRAPPER}} .elementor-field-subgroup label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		// Background color of the inputs and select boxes.
		$this->add_control(
			'wpstream-theme_field_background_color',
			[
				'label' => esc_html__( 'Field Background Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,

				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'background-color: {{VALUE}};',

				],
				'separator' => 'before',
			]
		);

		// Border color of the inputs and select boxes.
		$this->add_control(
			'wpstream-theme_field_border_color',
			[
				'label' => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,

				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper::before' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-color: {{VALUE}};',


				],
				'separator' => 'before',
			]
		);

		// Per-side border width for the fields.
		$this->add_responsive_control(
			'field_border_width',
			[
				'label' => esc_html__( 'Border Width', 'hello-wpstream' ),
				'type' => Controls_Manager::DIMENSIONS,
				'placeholder' => '1',
				'size_units' => [ 'px' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// Per-corner border radius for the fields.
		$this->add_responsive_control(
			'field_border_radius',
			[
				'label' => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group:not(.elementor-field-type-upload) .elementor-field:not(.elementor-select-wrapper)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// End of the "Field Style" section.
		$this->end_controls_section();

		/*-------------------------------------------------------------------------------------------------
		*  END Form Fields settings
		*/


		/*-------------------------------------------------------------------------------------------------
		*  GDpr Style settings
		*/
		// -----------------------------------------------------------------
		// STYLE tab: "GDPR" section (consent text color and typography).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_gdpr_style',
			[
				'label' => esc_html__( 'GDPR', 'hello-wpstream' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		// Text/link color for the GDPR consent wrapper.
		$this->add_control(
			'field_gdpr_color',
			[
				'label' => esc_html__( 'Text Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .gpr_wrapper' => 'color: {{VALUE}};',
					'{{WRAPPER}} .gpr_wrapper a' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		// Typography group control for the GDPR consent link.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'gdpr_typography',
				'selector' => '{{WRAPPER}} .gpr_wrapper a',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'fields_options' => [
					'font_weight' => [
						'default' => '300',
					],
				],
			]
		);


		// End of the "GDPR" style section.
		$this->end_controls_section();

		/*-------------------------------------------------------------------------------------------------
		*  END GDpr Style settings
		*/
		// -----------------------------------------------------------------
		// STYLE tab: "Button" section (normal/hover state tabs).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'wpstream-theme_area_button_style',
			[
				'label' => esc_html__( 'Button', 'hello-wpstream' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		// Begin the normal/hover tabbed control group for the button.
		$this->start_controls_tabs( 'tabs_button_style' );

		// --- Normal state tab ---
		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label' => esc_html__( 'Normal State', 'hello-wpstream' ),
			]
		);

		// Button background color (normal state).
		$this->add_control(
			'submit_button_background_color',
			array(
				'label' => esc_html__( 'Submit Button Background Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-button' => ' background-color: {{VALUE}};'
				),
			)
		);

		// Button text color (normal state).
		$this->add_control(
			'submit_button_text_color',
			array(
				'label' => esc_html__( 'Submit Button Text Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-button' => 'color: {{VALUE}};',
				),
			)
		);

		// Button opacity (normal state).
		$this->add_control(
			'submit_button_opacity',
			array(
				'label' => esc_html__( 'Submit Button Opacity', 'hello-wpstream' ),
				'type' => Controls_Manager::SLIDER,
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 1,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-button' => 'opacity: {{SIZE}};',
				),
			)
		);

		// Typography group control for the button label.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'submit_button_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .elementor-button',
			]
		);

		// Border group control (type/width) for the button.
		$this->add_group_control(
			Group_Control_Border::get_type(), [
				'name' => 'submit_button_border',
				'selector' => '{{WRAPPER}} .elementor-button',
			]
		);

		// Explicit border color (only when a border style is set).
		$this->add_control(
			'submit_button_border_normal_color',
			array(
				'label' => esc_html__( 'Submit Button Border Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .elementor-button' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'submit_button_border!' => '',
				),
			)
		);

		// Per-corner border radius for the button.
		$this->add_responsive_control(
			'submit_button_border_radius',
			[
				'label' => esc_html__( 'Submit Button Border Radius', 'hello-wpstream' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// Inner padding around the button label.
		$this->add_responsive_control(
			'submit_button_text_padding',
			[
				'label' => esc_html__( 'Submit Button Text Padding', 'hello-wpstream' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		// End of the normal-state tab.
		$this->end_controls_tab();

		// --- Hover state tab ---
		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label' => esc_html__( 'Hover State', 'hello-wpstream' ),
			]
		);

		// Button background color (hover state).
		$this->add_control(
			'submit_button_background_hover_color',
			[
				'label' => esc_html__( 'Submit Button Background Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,

				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		// Button text color (hover state).
		$this->add_control(
			'submit_button_hover_color',
			[
				'label' => esc_html__( 'Submit Button Text Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,

				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		// Button border color (hover state; only when a border is set).
		$this->add_control(
			'submit_button_hover_border_color',
			[
				'label' => esc_html__( 'Submit Button Border Color', 'hello-wpstream' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);



		// End of the hover-state tab.
		$this->end_controls_tab();

		// Close the normal/hover tabbed control group.
		$this->end_controls_tabs();
		/*-------------------------------------------------------------------------------------------------
		*  End Button Style settings
		*/

		// End of the "Button" style section (and of register_controls()).
		$this->end_controls_section();

	}







	/**
	 * Render the widget's front-end HTML.
	 *
	 * Reads the saved settings, resolves the email/recipient values, assembles
	 * the Elementor render attributes for the wrapper/fields/button, and prints
	 * the <form> markup (fields, optional GDPR consent, submit button) plus the
	 * inline script that wires up AJAX submission.
	 *
	 * @access protected
	 */
	protected function render() {
		// Current post is referenced for context (kept global for template parity).
		global $post;
		// Pull the resolved control values for this widget instance.
		$settings = $this->get_settings_for_display();

		// Whitelist of HTML tags allowed inside the GDPR consent text.
		$allowed_html = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'target' => array()
			),
			'strong' => array(),
			'th' => array(),
			'td' => array(),
			'span' => array(),
		);


		// Primary recipient address (falls back to empty when unset).
		$email_to = '';
		if(!empty($settings['email_to'])){
			$email_to = $settings['email_to'] ;
		}

		// Subject line for the notification email.
		$email_subject = '';
		if(!empty($settings['email_subject'])){
			$email_subject = $settings['email_subject'] ;
		}


		// Optional CC recipient.
		$send_copy_to = '';
		if(!empty($settings['send_copy_to'])){
			$send_copy_to = $settings['send_copy_to'] ;
		}

		// Optional BCC recipient.
		$send_ccopy_to = '';
		if(!empty($settings['send_ccopy_to'])){
			$send_ccopy_to = $settings['send_ccopy_to'] ;
		}


		/*
		/	add attributes to html classes
		*/

		// Register the base CSS classes for the wrapper, submit column and button.
		$this->add_render_attribute(
			[
				'wrapper' => [
					'class' => [
						'elementor-form-fields-wrapper',
						'elementor-labels-above',
					],
				],
				'wpstream-theme_submit_wrapper' => [
					'class' => [
						'elementor-field-group',
						'elementor-column',
						'elementor-field-type-submit',
					],
				],
				'button' => [
					'class' => [
						'agent_submit_class_elementor',
						'wpstream_submit_button',

						'elementor-button',
					]
				],
			]
		);

		// Default the submit column to full width when no width was chosen.
		if ( empty( $settings['submit_button_width'] ) ) {
			$settings['submit_button_width'] = '100';
		}
		// Desktop submit column width class.
		$this->add_render_attribute( 'wpstream-theme_submit_wrapper', 'class', 'elementor-col-' . $settings['submit_button_width'] );
		//$this->add_render_attribute( 'wpstream-theme_submit_wrapper', 'class', ' elementor-button-align-' . $settings['submit_button_align'] );

		// Tablet-specific submit column width, if configured.
		if ( ! empty( $settings['submit_button_width_tablet'] ) ) {
			$this->add_render_attribute( 'wpstream-theme_submit_wrapper', 'class', 'elementor-md-' . $settings['submit_button_width_tablet'] );
		}

		// Mobile-specific submit column width, if configured.
		if ( ! empty( $settings['submit_button_width_mobile'] ) ) {
			$this->add_render_attribute( 'wpstream-theme_submit_wrapper', 'class', 'elementor-sm-' . $settings['submit_button_width_mobile'] );
		}

		// Apply the button size class (elementor-size-*).
		if ( ! empty( $settings['submit_button_size'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['submit_button_size'] );
		}

		// Apply the button type class when a button_type is present.
		if ( ! empty( $settings['button_type'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-button-' . $settings['button_type'] );
		}


		// Apply a custom form id when provided.
		if ( ! empty( $settings['form_id'] ) ) {
			$this->add_render_attribute( 'form', 'id', $settings['form_id'] );
		}


		// Apply the custom button id from the Button ID control.
		if ( ! empty( $settings['wpstream-theme_submit_button_elementor'] ) ) {
			$this->add_render_attribute( 'button', 'id', $settings['wpstream-theme_submit_button_elementor'] );
		}

		/*
		/	END add attributes to html classes
		*/
		?>





        <!-- Contact form: submitted via AJAX (see the inline script below). -->
        <form class="elementor-form wpstream_elementor_form"  id="wpstream_elementor_form-<?php echo esc_attr($this->get_id()); ?>" method="post" <?php echo esc_attr($this->get_render_attribute_string( 'form' )); ?>>

            <!-- Container where success/error feedback messages are injected. -->
            <div class="warning wpstream-contact-form-message"></div>

            <!-- Marker flag identifying this as an Elementor-built contact form. -->
            <input name="prop_id" type="hidden"  id="contact_form_elementor" value="1">
            <!-- CSRF nonce verified server-side when the form is submitted. -->
            <input type="hidden" name="contact_ajax_nonce" id="agent_property_ajax_nonce"  value="<?php echo wp_create_nonce( 'ajax-property-contact' );?>" />

            <!-- Email subject carried through to the notification email. -->
            <input type="hidden" id="elementor_email_subject" name="email_suject" value="<?php echo esc_attr($email_subject); ?>" />

            <!-- Fields wrapper: the loop below prints one field group per configured field. -->
            <div <?php echo wp_kses_post($this->get_render_attribute_string( 'wrapper' )); ?> >
				<?php
				// Loop over every configured field and print its markup.
				foreach ( $settings['form_fields'] as $key => $item ) {
					// Propagate the global input size onto each field row.
					$item['form_field_input_size'] = $settings['form_field_input_size'];
					// Build this field's render attributes (classes, name, id, etc.).
					$this->wpstream_theme_render_attributes($key, $item, $settings);

					// Open the field group wrapper div.
					print '<div ' . $this->get_render_attribute_string('field-group' . $key) . '>';
					// Print the label when the field has one.
					if ($item['field_label']) {
						echo '<label ' . $this->get_render_attribute_string('label' . $key) . '>' . $item['field_label'];
						// Append an asterisk for required fields.
						if ($item['required']) {
							echo '*';
						}
						echo '</label>';
					}

					// Print the actual input/textarea for this field.
					$this->wpstream_render_field($item, $key);
					// Close the field group wrapper div.
					print '</div>';
				} ?>

                <!-- Submit column: optional GDPR consent + the submit button. -->
                <div <?php echo esc_attr($this->get_render_attribute_string( 'wpstream-theme_submit_wrapper') ); ?>>

					<?php
					// add gdpr check if is the case
					// Show the GDPR consent checkbox only when the agreement toggle is on.
					if( isset($settings['has_gdpr_agreement']) && $settings['has_gdpr_agreement'] === 'yes') {
						?>

                        <!-- GDPR consent checkbox with a link to the configured policy URL. -->
                        <div class="gpr_wrapper">
                            <input type="checkbox" id="wpstream_agree_gdpr" class="wpstream_agree_gdpr" name="wpstream_agree_gdpr" />
                            <label for="wpstream_agree_gdpr">
                                <a target="_blank" href="<?php echo esc_url($settings['link_gdpr_agreement']);?>">
									<?php echo wp_kses($settings['gdpr_text'],$allowed_html);?>
                                </a>
                            </label>
                        </div>

					<?php } ?>

                    <!-- Submit button; label comes from the Submit Button "Text" control. -->
                    <button type="submit" <?php echo wp_kses_post($this->get_render_attribute_string( 'button' )); ?>>

						<?php if ( ! empty( $settings['submit_button_text'] ) ) : ?>
							<?php echo esc_html($settings['submit_button_text']); ?>
						<?php endif; ?>

                    </button>
                </div>
            </div>
        </form>
        <script>

            // Bind the AJAX submit handler once the DOM is ready.
            jQuery(document).ready(function() {


                // External helper that intercepts submit and posts over AJAX.
                wpstream_elementor_submit_form();
            });
        </script>



		<?php
	}



	/*
	*		Render required
	*/
	/**
	 * Flag a render element as required (adds required="required").
	 *
	 * @param string $element Render-attribute element key to mark as required.
	 */
	private function wpstream_required_attribute( $element ) {
		// Add the HTML required attribute to the given render element.
		$this->add_render_attribute( $element, 'required', 'required' );
	}


	/*
	*		Render fields
	*/

	/**
	 * Print the input control for a single field based on its type.
	 *
	 * @param array      $item Field configuration (type, id, etc.).
	 * @param int|string $key  Loop key used to namespace render attributes.
	 */
	protected function wpstream_render_field($item, $key){

		// 'message' fields render as a textarea.
		if($item['field_type']=='message'){
			// we have textarea
			echo trim($this->wpstream_render_textarea( $item, $key) );
		}else if($item['field_type']=='email'){
			// 'email' fields render as an email input.
			//we have email
			$this->add_render_attribute( 'input' . $key, 'class', 'elementor-field-textual' );
			echo '<input type="email" ' . $this->get_render_attribute_string( 'input' . $key ) . '>';
		}else{
			// Everything else renders as a plain text input.
			$this->add_render_attribute( 'input' . $key, 'class', 'elementor-field-textual' );
			echo '<input type="text" ' . $this->get_render_attribute_string( 'input' . $key ) . '>';
		}


	}


	/*
	*		Render fields attributes
	*/
	/**
	 * Compute and register the render attributes for one field.
	 *
	 * Sets the field-group, input and label classes/ids, and applies required,
	 * width (responsive), placeholder, value and label-visibility attributes.
	 *
	 * @param int|string $key      Loop key used to namespace render attributes.
	 * @param array      $item     Field configuration for this row.
	 * @param array      $settings Full widget settings (for label visibility).
	 */
	protected function wpstream_theme_render_attributes( $key, $item ,$settings ){

		// Base classes/ids for the field group, its input and its label.
		$this->add_render_attribute(
			[
				'field-group' . $key => [
					'class' => [
						'elementor-field-group',
						'elementor-column',
						'form-group',
						'elementor-field-group-' . $item['_id'],
					],
				],
				'input' . $key => [
					'name' 	=> $item['field_type'],
					'id' 		=> 'rentals_contact_builder_'.$item['field_type'],
					'class' => [
						'elementor-field',
						'form-control',
						'elementor-size-' . $item['form_field_input_size'],
					],
				],
				'label' . $key => [
					'for' => 'form-field-' . $item['_id'],
					'class' => 'elementor-field-label',
				],
			]
		);

		// Default the field width to full when none is set.
		if ( empty( $item['width'] ) ) {
			$item['width'] = '100';
		}

		// For required fields, add the required class and the required attribute.
		if ( ! empty( $item['required'] ) ) {
			$class = 'elementor-field-required';
			// Optionally add the "mark required" class (note: $instance is unset here).
			if ( ! empty( $instance['mark_required'] ) ) {
				$class .= ' elementor-mark-required';
			}
			$this->add_render_attribute( 'field-group' . $key, 'class', $class );
			$this->wpstream_required_attribute( 'input' . $key );
		}

		// Desktop column width class for the field group.
		$this->add_render_attribute( 'field-group' . $key, 'class', 'elementor-col-' . $item['width'] );

		// Tablet-specific width, if configured.
		if ( ! empty( $item['width_tablet'] ) ) {
			$this->add_render_attribute( 'field-group' .$key, 'class', 'elementor-md-' . $item['width_tablet'] );
		}

		// Mobile-specific width, if configured.
		if ( ! empty( $item['width_mobile'] ) ) {
			$this->add_render_attribute( 'field-group' . $key, 'class', 'elementor-sm-' . $item['width_mobile'] );
		}

		// Add the placeholder attribute when present.
		if ( ! empty( $item['placeholder'] ) ) {
			$this->add_render_attribute( 'input' . $key, 'placeholder', $item['placeholder'] );
		}

		// Add a pre-filled value when present.
		if ( ! empty( $item['field_value'] ) ) {
			$this->add_render_attribute( 'input' .$key, 'value', $item['field_value'] );
		}

		// Hide labels visually (screen-reader only) when labels are turned off.
		if ( ! $settings['form_field_show_labels'] ) {
			$this->add_render_attribute( 'label' . $key, 'class', 'elementor-screen-only' );
		}



	}



	/*
	/  render textarea
	*/

	/**
	 * Build the <textarea> markup for a 'message' field.
	 *
	 * @param array      $item Field configuration (rows, id, required, etc.).
	 * @param int|string $key  Loop key used to namespace render attributes.
	 *
	 * @return string The rendered textarea HTML.
	 */
	protected function wpstream_render_textarea( $item, $key ) {
		// Register the textarea classes plus name/id/rows attributes.
		$this->add_render_attribute( 'textarea' . $key, [
			'class' => [
				'form-control',
				'elementor-field-textual',
				'elementor-field',
				'elementor-size-' . $item['form_field_input_size'],
			],
			'name' => $item['field_type'],
			'id' => 'form-field-' . $item['_id'],
			'rows' => $item['rows'],
		] );

		// Mark the textarea required when the field requires it.
		if ( $item['required'] ) {
			$this->wpstream_required_attribute( 'textarea' . $key );
		}

		// Add the placeholder attribute when present.
		if ( $item['placeholder'] ) {
			$this->add_render_attribute( 'textarea' . $key, 'placeholder', $item['placeholder'] );
		}



		// Pre-fill the textarea body when a value was supplied.
		$value ='';
		if(!empty( $item['field_value']) ) {
			$value =	$item['field_value'];
		}

		// Return the assembled textarea element.
		return '<textarea '.$this->get_render_attribute_string( 'textarea'.$key ).'>'.$value.'</textarea>';
	}



} //end class
