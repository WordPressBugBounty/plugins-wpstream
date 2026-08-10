<?php
/**
 * Template edit account
 *
 * @package wpstream-theme
 */

// Resolve the currently logged-in user whose account this screen edits.
$user = wp_get_current_user();
// Delegate the actual markup to the shared edit-account template ($user is in scope for it).
require WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/dashboard-templates/general-template-edit-account.php';