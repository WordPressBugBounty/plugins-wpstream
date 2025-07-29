<?php
/**
 * Template testimonial
 *
 * @var array $testimonial Testimonial data
 */
?>

<div class="testimonial_wrapper_item d-flex flex-lg-row flex-column">
	<div class="testimonial_icon flex-shrink-0">
		<?php echo wpstream_theme_get_svg_icon( 'quotes.svg' ); ?>
	</div>

	<div class="testimonial_content">
		<div class="item_testimonial_text fst-italic"><?php echo wp_kses_post( $testimonial['testimonial_text'] ); ?></div>

		<div class="testimonial_second_row d-flex align-items-start">
			<div class="testimonal_image flex-shrink-0" style="background-image:url(<?php echo esc_url( $testimonial['testimonial_image']['url'] ); ?>);"></div>
			<div class="testimonial_second_row_details d-flex flex-column">
				<div class="item_testimonial_name"><?php echo esc_html( $testimonial['testimonial_name'] ); ?></div>
				<div class="item_testimonial_job"><?php echo esc_html( $testimonial['testimonial_job'] ); ?></div>
			</div>
		</div>
	</div>

</div>
