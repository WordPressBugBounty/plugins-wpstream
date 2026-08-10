<?php
/**
 * General
 *
 * Registers the "General" section of the theme's Redux options panel. Holds
 * site-wide layout preferences: the overall container width and the "back to
 * top" button toggle. Loaded from framework/options/main.php.
 *
 * @package wpstream-theme
 */

// The shared Redux option-set name (defined in theme-options.php).
global $wpstream_opt_name;
// Register this section against the theme's Redux option set.
Redux::setSection(
	$wpstream_opt_name,
	array(
		'title'  => esc_html__( 'General', 'hello-wpstream' ),   // Panel section title.
		'id'     => 'general-options',                           // Unique section identifier.
		'desc'   => '',                                          // Optional section description (unused).
		'icon'   => 'el-icon-dashboard el-icon-small',           // Elusive icon shown in the panel nav.
		'fields' => array(

			// Field: pick the max site container width from a preset button set.
			array(
				'id'       => 'wpstream_site_width',
				'type'     => 'button_set',
				'title'    => esc_html__( 'Site Container Width', 'hello-wpstream' ),
				'subtitle' => esc_html__( 'Select website container width.', 'hello-wpstream' ),
				'options'  => array(
					'1210px' => '1170px',
					'1310px' => '1270px',
					'1410px' => '1370px',
					'1480px' => '1440px',
				),
				'default'  => '1210px',
			),
			// Field: on/off switch controlling the "back to top" scroll button.
			array(
				'id'       => 'backtotop',
				'type'     => 'switch',
				'title'    => esc_html__( 'Back to Top', 'hello-wpstream' ),
				'desc'     => '',
				'subtitle' => esc_html__( 'Show back to top button', 'hello-wpstream' ),
				'default'  => 1,
				'on'       => esc_html__( 'Yes', 'hello-wpstream' ),
				'off'      => esc_html__( 'No', 'hello-wpstream' ),
			),


		),
	)
);
