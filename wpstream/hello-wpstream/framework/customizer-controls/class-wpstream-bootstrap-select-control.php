<?php
/**
 * Bootstrap select control
 *
 * Registers a custom Customizer control that renders a Bootstrap "selectpicker"
 * dropdown (with optional live search, placeholder and option groups) for
 * choice-based theme options.
 *
 * @link    https://developer.snapappointments.com/bootstrap-select/
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against re-declaration if this file is included more than once.
if ( ! class_exists( 'Wpstream_Bootstrap_Select' ) ) {
	/**
	 * Custom control for select settings.
	 */
	class Wpstream_Bootstrap_Select extends WP_Customize_Control {
		/**
		 * Control type.
		 *
		 * @var string
		 */
		public $type = 'wpstream_bootstrap_select';
		/**
		 * Add a search input.
		 *
		 * @var bool
		 */
		public $searchable = true;
		/**
		 * Placeholder / title text shown when nothing is selected.
		 *
		 * @var string
		 */
		public $placeholder = '';
		/**
		 * Optional grouped choices, keyed by optgroup label.
		 *
		 * @var array
		 */
		public $optgroup = array();

		/**
		 * Constructor method.
		 *
		 * @param WP_Customize_Manager $manager Customizer manager instance.
		 * @param string               $id      Control ID.
		 * @param array                $args    Additional arguments.
		 */
		public function __construct( $manager, $id, $args = array() ) {
			// Initialize the base control (label, description, settings, etc.).
			parent::__construct( $manager, $id, $args );

			// Enable bootstrap-select's live search box when searchable is on.
			if ( $this->searchable ) {
				$this->input_attrs['data-live-search'] = 'true';
			}

			// Expose the placeholder to bootstrap-select via the title attribute.
			if ( ! empty( $this->placeholder ) ) {
				$this->input_attrs['title'] = $this->placeholder;
			}
		}

		/**
		 * Enqueue control related scripts/styles.
		 *
		 * @return void
		 */
		public function enqueue() {
			// Bootstrap 4 base styles plus the bootstrap-select stylesheet.
			wp_enqueue_style( 'bootstrap-4', get_template_directory_uri().'/css/bootstrap4.css', '', '1.0' );
			wp_enqueue_style( 'wpstream-bootstrap-select', get_template_directory_uri() . '/css/bootstrap-select.min.css' );

			// Popper is a positioning dependency for the Bootstrap dropdown.
			wp_enqueue_script( 'popper-1.12.9',  get_template_directory_uri() . '/js/popper_select.min.js', ['jquery'], '1.0', true );
			// Bootstrap 4 JS depends on Popper and jQuery.
			wp_enqueue_script( 'bootstrap-4', get_template_directory_uri().'/js/bootstrap4.min.js', ['popper-1.12.9', 'jquery'], '1.0', true );
			// The bootstrap-select plugin that turns the <select> into a selectpicker.
			wp_enqueue_script(
				'wpstream-bootstrap-select',
				get_template_directory_uri() . '/js/lib/bootstrap-select.min.js',
				array(
					'jquery',
					'bootstrap-4',
					'popper-1.12.9'
				),
				'',
				true
			);
		}

		/**
		 * Render content for the custom control.
		 *
		 * @return string|void Empty string when there is nothing to render; otherwise prints markup.
		 */
		public function render_content() {
			// Nothing to render if neither flat choices nor grouped choices exist.
			if ( empty( $this->choices ) && empty( $this->optgroup ) ) {
				return '';
			}

			// Build the input id, description id and (conditional) aria-describedby attribute.
			$input_id         = '_customize-input-' . $this->id;
			$description_id   = '_customize-description-' . $this->id;
			$describedby_attr = ( ! empty( $this->description ) ) ? ' aria-describedby="' . esc_attr( $description_id ) . '" ' : '';
			?>

			<!-- Render the control title label only when a label is set. -->
			<?php if ( ! empty( $this->label ) ) : ?>
				<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $this->label ); ?></label>
			<?php endif; ?>

			<!-- Render the helper description only when one is set. -->
			<?php if ( ! empty( $this->description ) ) : ?>
				<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>

			<!-- The select is upgraded to a bootstrap "selectpicker"; $this->link() binds it to the setting. -->
			<select <?php $this->input_attrs(); ?> class="wpstream-bootstrap-select form-control selectpicker" id="<?php echo esc_attr( $input_id ); ?>" <?php echo esc_attr($describedby_attr); ?> <?php $this->link(); ?>>
				<?php
				// Prefer grouped choices when an optgroup map was provided.
				if ( ! empty( $this->optgroup ) ) {
					// Emit one <optgroup> per named group.
					foreach ( $this->optgroup as $group_name => $choices ) {
						echo '<optgroup label="' . esc_attr( $group_name ) . '">';
                            // Emit each option within the group, marking the stored value as selected.
                            foreach ( $choices as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
                            }
						echo '</optgroup>';
					}
				} elseif ( ! empty( $this->choices ) ) {
					// Otherwise emit a flat option list, marking the stored value as selected.
					foreach ( $this->choices as $value => $label ) {
						echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
					}
				}

				?>
			</select>

			<?php
		}
	}
}
