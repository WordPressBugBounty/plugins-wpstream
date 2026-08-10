<?php
/**
 * Main
 *
 * Entry point that loads the individual Redux options sections for the theme.
 * Currently pulls in the "General" section; add further require_once lines here
 * as more option sections are introduced.
 *
 * @package wpstream-theme
 */

// Require options general.
// Load the General options section from the active theme directory.
require_once get_theme_file_path( '/framework/options/general.php' );
