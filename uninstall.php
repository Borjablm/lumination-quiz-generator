<?php
/**
 * Lumination AI Quiz Generator Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes quiz-generator-specific options.
 *
 * Usage data lives in the shared Core table (wp_lumination_usage) and is
 * removed when Lumination Core itself is deleted.
 *
 * @package    LuminationQuizGenerator
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Only run when WordPress itself triggers uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove quiz-generator-specific options.
delete_option( 'lumination_qg_default_count' );
delete_option( 'lumination_qg_default_difficulty' );
