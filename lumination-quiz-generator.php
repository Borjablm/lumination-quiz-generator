<?php
/**
 * Lumination AI Quiz Generator
 *
 * AI-powered quiz generation from documents, URLs, or text.
 * Requires Lumination Core (v1.0.1+) for API access and analytics.
 *
 * @package           LuminationQuizGenerator
 * @author            Lumination Team
 * @license           GPL-3.0-or-later
 * @link              https://lumination.ai
 * @copyright         2026 Lumination Team
 *
 * @wordpress-plugin
 * Plugin Name:       Lumination AI Quiz Generator
 * Description:       Generate interactive quizzes from PDFs, URLs, or pasted text. Configurable question count and difficulty, instant scoring, and export to CSV. Requires Lumination Core.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Lumination Team
 * Author URI:        https://lumination.ai
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       lumination-quiz-generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────

define( 'LUMINATION_QG_VERSION', '1.0.1' );
define( 'LUMINATION_QG_FILE',    __FILE__ );
define( 'LUMINATION_QG_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LUMINATION_QG_URL',     plugin_dir_url( __FILE__ ) );

// ── Auto-update via GitHub releases ──────────────────────────────────────────

require_once LUMINATION_QG_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/Borjablm/lumination-quiz-generator/',
	__FILE__,
	'lumination-quiz-generator'
);

// ── Dependency check + initialisation ────────────────────────────────────────

add_action(
	'plugins_loaded',
	function () {
		$core_ok = function_exists( 'lumination_core' )
				&& defined( 'LUMINATION_CORE_VERSION' )
				&& version_compare( LUMINATION_CORE_VERSION, '1.0.1', '>=' );

		if ( ! $core_ok ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					$msg = sprintf(
						wp_kses(
							/* translators: %s: URL to Plugins admin page */
							__( '<strong>Lumination AI Quiz Generator</strong> requires <strong>Lumination Core</strong> (v1.0.1+) to be installed and active. <a href="%s">Manage plugins &rarr;</a>', 'lumination-quiz-generator' ),
							array(
								'strong' => array(),
								'a'      => array( 'href' => array() ),
							)
						),
						esc_url( admin_url( 'plugins.php' ) )
					);
					echo '<div class="notice notice-error is-dismissible"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
			return;
		}

		// Core confirmed — load classes and register hooks.
		require_once LUMINATION_QG_DIR . 'includes/class-quiz-settings.php';
		require_once LUMINATION_QG_DIR . 'includes/class-quiz-ajax.php';
		require_once LUMINATION_QG_DIR . 'includes/class-quiz-generator.php';

		// Register settings on Core's hook.
		add_action( 'lumination_core_settings_init', array( 'Lumination_Quiz_Settings', 'register_settings' ) );

		// Register admin tab in Core's panel.
		add_action(
			'lumination_core_admin_tabs_init',
			function () {
				Lumination_Core_Settings::register_tab(
					array(
						'id'       => 'quiz-generator',
						'label'    => __( 'Quiz Generator', 'lumination-quiz-generator' ),
						'callback' => array( 'Lumination_Quiz_Settings', 'render_tab' ),
						'priority' => 40,
					)
				);
			}
		);

		// Initialise shortcode and AJAX.
		Lumination_Quiz_Generator::init();
	},
	20 // Priority 20 — after Core (10).
);
