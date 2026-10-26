<?php
/**
 * Plugin Name:       QA Kit
 * Description:       Developer QA tools for local and staging sites: front-end checks, ACF inspector, AI prompt library and a WP-CLI ACF audit. Does nothing on production.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jimmy
 * License:           GPL-2.0-or-later
 * Text Domain:       qa-kit
 * Update URI:        https://github.com/TrueMarket/qa-kit
 */

defined( 'ABSPATH' ) || exit;

define( 'QA_KIT_VERSION', '0.1.1' );
define( 'QA_KIT_FILE', __FILE__ );
define( 'QA_KIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'QA_KIT_URL', plugin_dir_url( __FILE__ ) );

// GitHub repo the update checker reads releases/tags from.
define( 'QA_KIT_GITHUB_REPO', 'https://github.com/TrueMarket/qa-kit/' );

/**
 * Set up automatic updates from the GitHub repository.
 *
 * Uses the Plugin Update Checker library (Yahnis Elsts) to compare the
 * installed version against the latest GitHub release/tag and surface
 * updates on the WordPress Plugins screen.
 *
 * Runs before the production check below so an inert copy can still be updated.
 */
function qa_kit_init_updater() {
	$library = QA_KIT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! file_exists( $library ) ) {
		return;
	}

	require_once $library;

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		QA_KIT_GITHUB_REPO,
		QA_KIT_FILE,
		'qa-kit'
	);

	// The branch that holds the stable, production-ready code. On the default
	// branch, PUC prefers GitHub releases/tags over raw branch commits.
	$update_checker->setBranch( 'main' );

	// If the GitHub repo is private, a token is required to read releases/tags.
	// Define this in wp-config.php (fine-grained PAT, "Contents: Read-only",
	// scoped to just this repo).
	if ( defined( 'QA_KIT_GITHUB_TOKEN' ) && QA_KIT_GITHUB_TOKEN ) {
		$update_checker->setAuthentication( QA_KIT_GITHUB_TOKEN );
	}

	// If you attach a built .zip to each GitHub release (rather than letting
	// PUC use the auto-generated source archive), uncomment the next line so
	// updates download that asset instead:
	// $update_checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );
}
qa_kit_init_updater();

/**
 * QA Kit only runs outside production.
 *
 * Set WP_ENVIRONMENT_TYPE in wp-config.php on every environment
 * ('local', 'development' or 'staging'). Local by Flywheel sets 'local' for you.
 * If the constant is missing WordPress reports 'production', so QA Kit stays off.
 *
 * Force it on or off with the QA_KIT_ENABLED constant if you need to.
 */
function qa_kit_is_enabled() {
	if ( defined( 'QA_KIT_ENABLED' ) ) {
		return (bool) QA_KIT_ENABLED;
	}
	return 'production' !== wp_get_environment_type();
}

/**
 * Who can use QA Kit. Filter with 'qa_kit_capability'.
 */
function qa_kit_capability() {
	return apply_filters( 'qa_kit_capability', 'manage_options' );
}

if ( ! qa_kit_is_enabled() ) {
	// Installed on production: stay inert and nag admins to remove it.
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>QA Kit</strong> is installed on a production site. It is disabled, but it should be removed before launch.</p></div>';
		}
	);
	return;
}

require_once QA_KIT_DIR . 'includes/class-qa-kit-context.php';
require_once QA_KIT_DIR . 'includes/class-qa-kit-prompts.php';
require_once QA_KIT_DIR . 'includes/class-qa-kit-acf-audit.php';
require_once QA_KIT_DIR . 'includes/class-qa-kit-toolbar.php';
require_once QA_KIT_DIR . 'includes/class-qa-kit-admin.php';

QA_Kit_Toolbar::init();
QA_Kit_Admin::init();
