<?php
/**
 * Template testimonial
 *
 * Renders a single testimonial: a quote icon, the italicised testimonial text,
 * and a second row with the author's photo, name, and job title.
 *
 * @var array $testimonial Testimonial data (text, image, name, job).
 */
?>

<!-- Testimonial item: quote icon column beside the content column. -->
<div class="testimonial_wrapper_item d-flex flex-lg-row flex-column">
	<!-- Decorative quote icon. -->
	<div class="testimonial_icon flex-shrink-0">
		<?php echo wpstream_theme_get_svg_icon( 'quotes.svg' ); ?>
	</div>

	<!-- Content column: quote text plus author details. -->
	<div class="testimonial_content">
		<!-- Testimonial body text (sanitized HTML allowed). -->
		<div class="item_testimonial_text fst-italic"><?php echo wp_kses_post( $testimonial['testimonial_text'] ); ?></div>

		<!-- Second row: author photo alongside name/job. -->
		<div class="testimonial_second_row d-flex align-items-start">
			<!-- Author photo rendered as a CSS background image. -->
			<div class="testimonal_image flex-shrink-0" style="background-image:url(<?php echo esc_url( $testimonial['testimonial_image']['url'] ); ?>);"></div>
			<!-- Author name and job title, stacked vertically. -->
			<div class="testimonial_second_row_details d-flex flex-column">
				<div class="item_testimonial_name"><?php echo esc_html( $testimonial['testimonial_name'] ); ?></div>
				<div class="item_testimonial_job"><?php echo esc_html( $testimonial['testimonial_job'] ); ?></div>
			</div>
		</div>
	</div>

</div>
