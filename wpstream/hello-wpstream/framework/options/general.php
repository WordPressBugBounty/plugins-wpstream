<?php
/**
 * General
 *
 * @package wpstream-theme
 */

global $wpstream_opt_name;
Redux::setSection(
	$wpstream_opt_name,
	array(
		'title'  => esc_html__( 'General', 'hello-wpstream' ),
		'id'     => 'general-options',
		'desc'   => '',
		'icon'   => 'el-icon-dashboard el-icon-small',
		'fields' => array(

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
