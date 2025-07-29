<?php
/**
 * Theme options
 *
 * @package wpstream-theme
 */

if ( ! class_exists( 'Redux' ) ) {
	return;
}

global $wpstream_opt_name, $country_list, $allowed_html_array;

$allowed_html_array = array(
	'i'    => array(
		'class' => array(),
	),
	'span' => array(
		'class' => array(),
	),
	'a'    => array(
		'href'   => array(),
		'title'  => array(),
		'target' => array(),
	),
);

// This is your option name where all the Redux data is stored.
$wpstream_opt_name = 'wpstream_options';

// This line is only for altering the demo. Can be easily removed.
$wpstream_opt_name = apply_filters( 'redux_demo_opt_name', $wpstream_opt_name );
$redux_path        = ReduxFramework::$_dir;
$redux_url         = ReduxFramework::$_url;
$img_url           = $redux_url . 'assets/img/';
$theme             = wp_get_theme(); // For use with some settings. Not necessary.
$theme_name        = $theme->get( 'Name' );

// ADMIN BAR LINKS -> Setup custom links in the admin bar menu as external items.
$args['admin_bar_links'][] = array(
	'id'    => 'wpstream-support',
	'href'  => 'https://wpstream.net',
	'title' => esc_html__( 'Support', 'hello-wpstream' ),
);

$args['share_icons'][] = array(
	'url'   => '',
	'title' => 'Like us on Facebook',
	'icon'  => 'el el-facebook',
);

$args['share_icons'][] = array(
	'url'   => '',
	'title' => 'Follow us on Twitter',
	'icon'  => 'el el-twitter',
);

Redux::setArgs( $wpstream_opt_name, $args );

// Change the arguments after they've been declared, but before the panel is created.
add_filter( 'redux/options/' . $wpstream_opt_name . '/args', 'change_arguments' );

// If Redux is running as a plugin, this will remove the demo notice and links.
add_action( 'redux/loaded', 'remove_demo' );

/**
 * Filter hook for filtering the args. Good for child themes to override or add to the args array. Can also be used in other functions.
 * */
if ( ! function_exists( 'change_arguments' ) ) {
	/**
	 * Changes the arguments by adding or modifying specific parameters.
	 *
	 * @param array $args The original arguments.
	 * @return array The modified arguments.
	 */
	function change_arguments( $args ) {
		$args['dev_mode'] = false;

		return $args;
	}
}

/**
* Removes the demo link and the notice of integrated demo from the redux-framework plugin
*/
if ( ! function_exists( 'remove_demo' ) ) {
	/**
	 * Remove demo
	 */
	function remove_demo() {
		// Used to hide the demo mode link from the plugin page. Only used when Redux is a plugin.
		if ( class_exists( 'ReduxFrameworkPlugin' ) ) {
			remove_filter(
				'plugin_row_meta',
				array(
					ReduxFrameworkPlugin::instance(),
					'plugin_metalinks',
				),
				null,
				2
			);
			// Used to hide the activation notice informing users of the demo panel. Only used when Redux is a plugin.
			remove_action( 'admin_notices', array( ReduxFrameworkPlugin::instance(), 'admin_notices' ) );
		}
	}
}

if ( ! function_exists( 'wpstream_return_option' ) ) {
	/**
	 * Returns the value of a specified option from the 'wpstream_options' settings.
	 *
	 * @param string $opt_name The name of the option.
	 * @param mixed  $is_default (Optional) The default value to return if the option does not exist.
	 * @return mixed The value of the option if it exists, otherwise the default value.
	 */
	function wpstream_return_option( $opt_name, $is_default = null ) {
		$wpstream_option = get_option( 'wpstream_options' );

		$option_val = isset( $wpstream_option[ $opt_name ] ) ? $wpstream_option[ $opt_name ] : $is_default;
		return $option_val;
	}
}

$country_list = array(
	'US'  => 'United States',
	'CA'  => 'Canada',
	'AU'  => 'Australia',
	'FR'  => 'France',
	'DE'  => 'Germany',
	'IS'  => 'Iceland',
	'IE'  => 'Ireland',
	'IT'  => 'Italy',
	'ES'  => 'Spain',
	'SE'  => 'Sweden',
	'AT'  => 'Austria',
	'BE'  => 'Belgium',
	'FI'  => 'Finland',
	'CZ'  => 'Czech Republic',
	'DK'  => 'Denmark',
	'NO'  => 'Norway',
	'GB'  => 'United Kingdom',
	'CH'  => 'Switzerland',
	'NZ'  => 'New Zealand',
	'RU'  => 'Russian Federation',
	'PT'  => 'Portugal',
	'NL'  => 'Netherlands',
	'IM'  => 'Isle of Man',
	'AF'  => 'Afghanistan',
	'AX'  => 'Aland Islands ',
	'AL'  => 'Albania',
	'DZ'  => 'Algeria',
	'AS'  => 'American Samoa',
	'AD'  => 'Andorra',
	'AO'  => 'Angola',
	'AI'  => 'Anguilla',
	'AQ'  => 'Antarctica',
	'AG'  => 'Antigua and Barbuda',
	'AR'  => 'Argentina',
	'AM'  => 'Armenia',
	'AW'  => 'Aruba',
	'AZ'  => 'Azerbaijan',
	'BS'  => 'Bahamas',
	'BH'  => 'Bahrain',
	'BD'  => 'Bangladesh',
	'BB'  => 'Barbados',
	'BY'  => 'Belarus',
	'BZ'  => 'Belize',
	'BJ'  => 'Benin',
	'BM'  => 'Bermuda',
	'BT'  => 'Bhutan',
	'BO'  => 'Bolivia, Plurinational State of',
	'BQ'  => 'Bonaire, Sint Eustatius and Saba',
	'BA'  => 'Bosnia and Herzegovina',
	'BW'  => 'Botswana',
	'BV'  => 'Bouvet Island',
	'BR'  => 'Brazil',
	'IO'  => 'British Indian Ocean Territory',
	'BN'  => 'Brunei Darussalam',
	'BG'  => 'Bulgaria',
	'BF'  => 'Burkina Faso',
	'BI'  => 'Burundi',
	'KH'  => 'Cambodia',
	'CM'  => 'Cameroon',
	'CV'  => 'Cape Verde',
	'KY'  => 'Cayman Islands',
	'CF'  => 'Central African Republic',
	'TD'  => 'Chad',
	'CL'  => 'Chile',
	'CN'  => 'China',
	'CX'  => 'Christmas Island',
	'CC'  => 'Cocos (Keeling) Islands',
	'CO'  => 'Colombia',
	'KM'  => 'Comoros',
	'CG'  => 'Congo',
	'CD'  => 'Congo, the Democratic Republic of the',
	'CK'  => 'Cook Islands',
	'CR'  => 'Costa Rica',
	'CI'  => 'Cote d\'Ivoire',
	'HR'  => 'Croatia',
	'CU'  => 'Cuba',
	'CW'  => 'Curaçao',
	'CY'  => 'Cyprus',
	'DJ'  => 'Djibouti',
	'DM'  => 'Dominica',
	'DO'  => 'Dominican Republic',
	'EC'  => 'Ecuador',
	'EG'  => 'Egypt',
	'SV'  => 'El Salvador',
	'GQ'  => 'Equatorial Guinea',
	'ER'  => 'Eritrea',
	'EE'  => 'Estonia',
	'ET'  => 'Ethiopia',
	'FK'  => 'Falkland Islands (Malvinas)',
	'FO'  => 'Faroe Islands',
	'FJ'  => 'Fiji',
	'GF'  => 'French Guiana',
	'PF'  => 'French Polynesia',
	'TF'  => 'French Southern Territories',
	'GA'  => 'Gabon',
	'GM'  => 'Gambia',
	'GE'  => 'Georgia',
	'GH'  => 'Ghana',
	'GI'  => 'Gibraltar',
	'GR'  => 'Greece',
	'GL'  => 'Greenland',
	'GD'  => 'Grenada',
	'GP'  => 'Guadeloupe',
	'GU'  => 'Guam',
	'GT'  => 'Guatemala',
	'GG'  => 'Guernsey',
	'GN'  => 'Guinea',
	'GW'  => 'Guinea-Bissau',
	'GY'  => 'Guyana',
	'HT'  => 'Haiti',
	'HM'  => 'Heard Island and McDonald Islands',
	'VA'  => 'Holy See (Vatican City State)',
	'HN'  => 'Honduras',
	'HK'  => 'Hong Kong',
	'HU'  => 'Hungary',
	'IN'  => 'India',
	'ID'  => 'Indonesia',
	'IR'  => 'Iran, Islamic Republic of',
	'IQ'  => 'Iraq',
	'IL'  => 'Israel',
	'JM'  => 'Jamaica',
	'JP'  => 'Japan',
	'JE'  => 'Jersey',
	'JO'  => 'Jordan',
	'KZ'  => 'Kazakhstan',
	'KE'  => 'Kenya',
	'KI'  => 'Kiribati',
	'KP'  => 'Korea, Democratic People\'s Republic of',
	'KR'  => 'Korea, Republic of',
	'KV'  => 'kosovo',
	'KW'  => 'Kuwait',
	'KG'  => 'Kyrgyzstan',
	'LA'  => 'Lao People\'s Democratic Republic',
	'LV'  => 'Latvia',
	'LB'  => 'Lebanon',
	'LS'  => 'Lesotho',
	'LR'  => 'Liberia',
	'LY'  => 'Libyan Arab Jamahiriya',
	'LI'  => 'Liechtenstein',
	'LT'  => 'Lithuania',
	'LU'  => 'Luxembourg',
	'MO'  => 'Macao',
	'MK'  => 'Macedonia',
	'MG'  => 'Madagascar',
	'MW'  => 'Malawi',
	'MY'  => 'Malaysia',
	'MV'  => 'Maldives',
	'ML'  => 'Mali',
	'MT'  => 'Malta',
	'MH'  => 'Marshall Islands',
	'MQ'  => 'Martinique',
	'MR'  => 'Mauritania',
	'MU'  => 'Mauritius',
	'YT'  => 'Mayotte',
	'MX'  => 'Mexico',
	'FM'  => 'Micronesia, Federated States of',
	'MD'  => 'Moldova, Republic of',
	'MC'  => 'Monaco',
	'MN'  => 'Mongolia',
	'ME'  => 'Montenegro',
	'MS'  => 'Montserrat',
	'MA'  => 'Morocco',
	'MZ'  => 'Mozambique',
	'MM'  => 'Myanmar',
	'NA'  => 'Namibia',
	'NR'  => 'Nauru',
	'NP'  => 'Nepal',
	'NC'  => 'New Caledonia',
	'NI'  => 'Nicaragua',
	'NE'  => 'Niger',
	'NG'  => 'Nigeria',
	'NU'  => 'Niue',
	'NF'  => 'Norfolk Island',
	'MP'  => 'Northern Mariana Islands',
	'OM'  => 'Oman',
	'PK'  => 'Pakistan',
	'PW'  => 'Palau',
	'PS'  => 'Palestinian Territory, Occupied',
	'PA'  => 'Panama',
	'PG'  => 'Papua New Guinea',
	'PY'  => 'Paraguay',
	'PE'  => 'Peru',
	'PH'  => 'Philippines',
	'PN'  => 'Pitcairn',
	'PL'  => 'Poland',
	'PR'  => 'Puerto Rico',
	'QA'  => 'Qatar',
	'RE'  => 'Reunion',
	'RO'  => 'Romania',
	'RW'  => 'Rwanda',
	'BL'  => 'Saint Barthélemy',
	'SH'  => 'Saint Helena',
	'KN'  => 'Saint Kitts and Nevis',
	'LC'  => 'Saint Lucia',
	'MF'  => 'Saint Martin (French part)',
	'PM'  => 'Saint Pierre and Miquelon',
	'VC'  => 'Saint Vincent and the Grenadines',
	'WS'  => 'Samoa',
	'SM'  => 'San Marino',
	'ST'  => 'Sao Tome and Principe',
	'SA'  => 'Saudi Arabia',
	'SN'  => 'Senegal',
	'RS'  => 'Serbia',
	'SC'  => 'Seychelles',
	'SL'  => 'Sierra Leone',
	'SG'  => 'Singapore',
	'SX'  => 'Sint Maarten (Dutch part)',
	'SK'  => 'Slovakia',
	'SI'  => 'Slovenia',
	'SB'  => 'Solomon Islands',
	'SO'  => 'Somalia',
	'ZA'  => 'South Africa',
	'GS'  => 'South Georgia and the South Sandwich Islands',
	'LK'  => 'Sri Lanka',
	'SD'  => 'Sudan',
	'SR'  => 'Suriname',
	'SJ'  => 'Svalbard and Jan Mayen',
	'SZ'  => 'Swaziland',
	'SY'  => 'Syrian Arab Republic',
	'TW'  => 'Taiwan, Province of China',
	'TJ'  => 'Tajikistan',
	'TZ'  => 'Tanzania, United Republic of',
	'TH'  => 'Thailand',
	'TL'  => 'Timor-Leste',
	'TG'  => 'Togo',
	'TK'  => 'Tokelau',
	'TO'  => 'Tonga',
	'TT'  => 'Trinidad and Tobago',
	'TN'  => 'Tunisia',
	'TR'  => 'Turkey',
	'TM'  => 'Turkmenistan',
	'TC'  => 'Turks and Caicos Islands',
	'TV'  => 'Tuvalu',
	'UG'  => 'Uganda',
	'UA'  => 'Ukraine',
	'UAE' => 'United Arab Emirates',
	'UM'  => 'United States Minor Outlying Islands',
	'UY'  => 'Uruguay',
	'UZ'  => 'Uzbekistan',
	'VU'  => 'Vanuatu',
	'VE'  => 'Venezuela, Bolivarian Republic of',
	'VN'  => 'Viet Nam',
	'VG'  => 'Virgin Islands, British',
	'VI'  => 'Virgin Islands, U.S.',
	'WF'  => 'Wallis and Futuna',
	'EH'  => 'Western Sahara',
	'YE'  => 'Yemen',
	'ZM'  => 'Zambia',
	'ZW'  => 'Zimbabwe',
);
