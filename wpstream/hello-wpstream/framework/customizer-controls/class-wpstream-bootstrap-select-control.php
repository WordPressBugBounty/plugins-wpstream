<?php
/**
 * Bootstrap select control
 *
 * @link    https://developer.snapappointments.com/bootstrap-select/
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		 * @var string
		 */
		public $placeholder = '';
		/**
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
			parent::__construct( $manager, $id, $args );

			if ( $this->searchable ) {
				$this->input_attrs['data-live-search'] = 'true';
			}

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
			wp_enqueue_style( 'bootstrap-4', get_template_directory_uri().'/css/bootstrap4.css', '', '1.0' );
			wp_enqueue_style( 'wpstream-bootstrap-select', get_template_directory_uri() . '/css/bootstrap-select.min.css' );

			wp_enqueue_script( 'popper-1.12.9',  get_template_directory_uri() . '/js/popper_select.min.js', ['jquery'], '1.0', true );
			wp_enqueue_script( 'bootstrap-4', get_template_directory_uri().'/js/bootstrap4.min.js', ['popper-1.12.9', 'jquery'], '1.0', true );
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
		 */
		public function render_content() {
			if ( empty( $this->choices ) && empty( $this->optgroup ) ) {
				return '';
			}

			$input_id         = '_customize-input-' . $this->id;
			$description_id   = '_customize-description-' . $this->id;
			$describedby_attr = ( ! empty( $this->description ) ) ? ' aria-describedby="' . esc_attr( $description_id ) . '" ' : '';
			?>

			<?php if ( ! empty( $this->label ) ) : ?>
				<label for="<?php echo esc_attr( $input_id ); ?>" class="customize-control-title"><?php echo esc_html( $this->label ); ?></label>
			<?php endif; ?>

			<?php if ( ! empty( $this->description ) ) : ?>
				<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>

			<select <?php $this->input_attrs(); ?> class="wpstream-bootstrap-select form-control selectpicker" id="<?php echo esc_attr( $input_id ); ?>" <?php echo esc_attr($describedby_attr); ?> <?php $this->link(); ?>>
				<?php
				if ( ! empty( $this->optgroup ) ) {
					foreach ( $this->optgroup as $group_name => $choices ) {
						echo '<optgroup label="' . esc_attr( $group_name ) . '">';
                            foreach ( $choices as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $this->value(), $value, false ) . '>' . $label . '</option>';
                            }
						echo '</optgroup>';
					}
				} elseif ( ! empty( $this->choices ) ) {
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
