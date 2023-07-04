<?php
/*
Plugin Name: Custom Login Plugin
Description: Custom Login Plugin with redirect settings, failed attempts counter and AJAX mode for login form.
Author: Herman Zuiakov
Author URI: https://github.com/hermanzuiakov
Version: 1.0
*/

if(!defined('ABSPATH')) {
    die('Do not open in file directory!');
}

// Create the plugin settings page
function custom_login_plugin_settings_page()
{
	add_menu_page(
		'Custom Login Plugin Settings', // Page title
		'Custom Login Plugin Settings', // Menu title
		'manage_options', // Capability required to access the page
		'custom-login-plugin', // Menu slug
		'custom_login_plugin_settings_page_content', // Callback function to render the page content
		'dashicons-admin-generic', // Icon
		99 // Position in the menu
	);
}
add_action('admin_menu', 'custom_login_plugin_settings_page');

// Render the content of the plugin settings page
function custom_login_plugin_settings_page_content()
{
	// Retrieve the redirect URL value from the database
	$redirect_url = isset($_POST['redirect_url']) ? sanitize_text_field($_POST['redirect_url']) : get_option('custom_login_plugin_redirect_url');

	// Retrieve the total failed attempts
	$failed_attempts = get_option('custom_login_plugin_failed_attempts');

	// Retrieve the AJAX mode value for the login form
	$ajax_enabled = ((isset($_POST['ajax_enabled']) ? '1' : '0') ?? get_option('custom_login_plugin_ajax_enabled'));

	// Update the plugin settings
	if (isset($_POST['custom_login_plugin_submit'])) {
		// Verify the nonce
		if (isset($_POST['custom_login_plugin_nonce']) && wp_verify_nonce($_POST['custom_login_plugin_nonce'], 'custom_login_plugin_settings')) {
			// Save the redirect URL in the database
			update_option('custom_login_plugin_redirect_url', $redirect_url);

			// Update the AJAX mode value
			update_option('custom_login_plugin_ajax_enabled', $ajax_enabled);
		}
	}

	// Generate the nonce field
	$nonce = wp_create_nonce('custom_login_plugin_settings');
	?>

    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <form method="post" action="">
			<?php wp_nonce_field('custom_login_plugin_settings', 'custom_login_plugin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Redirect URL</th>
                    <td><input type="text" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Total Failed Attempts</th>
                    <td><?php echo esc_html($failed_attempts); ?></td>
                </tr>
                <tr>
                    <th scope="row">Enable AJAX Mode</th>
                    <td><input type="checkbox" name="ajax_enabled" value="1" <?php checked($ajax_enabled, '1'); ?> /></td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="custom_login_plugin_submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
    </div>

	<?php
}

// Perform the redirect after successful user login
function custom_login_plugin_login_redirect($redirect_to, $request, $user)
{
	// Retrieve the redirect URL from the database
	$redirect_url = get_option('custom_login_plugin_redirect_url');

	// Retrieve the AJAX mode value for the login form
	$ajax_enabled = get_option('custom_login_plugin_ajax_enabled');

	// Check if the user is an administrator and the redirect URL is not empty
	if (is_a($user, 'WP_User') && !empty($redirect_url)) {
		// Check if AJAX mode is enabled and verify the AJAX request nonce
		if ($ajax_enabled == '1' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_REQUEST['custom_login_plugin_nonce']) && wp_verify_nonce($_REQUEST['custom_login_plugin_nonce'], 'custom_login_plugin_ajax')) {
			// If AJAX request, return the redirect URL as JSON response
			$response = array('redirect_url' => $redirect_url);
			wp_send_json($response);
		} else {
			// Redirect to {field_value}
			$redirect_to = $redirect_url;
		}
	}

	return $redirect_to;
}
add_filter('login_redirect', 'custom_login_plugin_login_redirect', 10, 3);

// Increment the failed login attempts counter
function custom_login_plugin_failed_login_attempt($username)
{
	// Retrieve the current value of the failed attempts counter
	$failed_attempts = get_option('custom_login_plugin_failed_attempts');

	// Increment the counter
	$failed_attempts++;

	// Update the counter value in the database
	update_option('custom_login_plugin_failed_attempts', $failed_attempts);
}
add_action('wp_login_failed', 'custom_login_plugin_failed_login_attempt');

// Load styles to the login page
function custom_login_plugin_load_styles() {
	wp_enqueue_style( 'custom-login-styles', plugin_dir_url( __FILE__ ) . '/assets/css/custom-login-styles.css' );
}
add_action( 'login_enqueue_scripts', 'custom_login_plugin_load_styles' );

// Register the plugin options on activation
function custom_login_plugin_activate()
{
	add_option('custom_login_plugin_redirect_url', '');
	add_option('custom_login_plugin_failed_attempts', 0);
	add_option('custom_login_plugin_ajax_enabled', '0');
}
register_activation_hook(__FILE__, 'custom_login_plugin_activate');

// Delete the plugin options on deactivation
function custom_login_plugin_deactivate()
{
	delete_option('custom_login_plugin_redirect_url');
	delete_option('custom_login_plugin_failed_attempts');
	delete_option('custom_login_plugin_ajax_enabled');
}
register_deactivation_hook(__FILE__, 'custom_login_plugin_deactivate');
