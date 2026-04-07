<?php
/**
 * Lumination Quiz Generator — Shortcode & Asset Enqueuing
 *
 * Registers the [lumination_quiz] shortcode and enqueues front-end
 * assets only when the shortcode is present on the page.
 *
 * @package    LuminationQuizGenerator
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lumination_Quiz_Generator {

	/**
	 * Default shortcode attributes.
	 *
	 * @var array
	 */
	private static $defaults = array(
		'title'       => 'AI Quiz Generator',
		'description' => 'Upload a document, paste a URL, or enter text to generate an interactive quiz',
		'mode'        => 'auto',
		'button_text' => 'Generate Quiz',
		'heading'     => '',
	);

	/**
	 * Register shortcode, assets hook, and AJAX handlers.
	 */
	public static function init() {
		add_shortcode( 'lumination_quiz', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		Lumination_Quiz_Ajax::register();
	}

	/**
	 * Render the [lumination_quiz] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		if ( ! Lumination_Core_Security::can_submit( 'quiz' ) ) {
			return '<p class="lumination-notice">' .
				esc_html__( 'You do not have permission to use this tool.', 'lumination-quiz-generator' ) . '</p>';
		}

		if ( ! Lumination_Core_API::is_configured() ) {
			return '<p class="lumination-notice">' .
				esc_html__( 'Lumination API is not configured. Please set up your API key in Settings.', 'lumination-quiz-generator' ) . '</p>';
		}

		$atts = shortcode_atts( self::$defaults, $atts, 'lumination_quiz' );

		$lqg_mode        = sanitize_text_field( $atts['mode'] );
		$lqg_title       = sanitize_text_field( $atts['title'] );
		$lqg_description = sanitize_text_field( $atts['description'] );
		$lqg_button_text = sanitize_text_field( $atts['button_text'] );
		$lqg_heading_tag = Lumination_Core_Settings::get_heading_tag( sanitize_text_field( $atts['heading'] ) );

		// Admin defaults.
		$lqg_default_count      = (int) get_option( 'lumination_qg_default_count', 10 );
		$lqg_default_difficulty = sanitize_text_field( get_option( 'lumination_qg_default_difficulty', 'intermediate' ) );

		ob_start();
		include LUMINATION_QG_DIR . 'templates/quiz-ui.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue front-end assets when the shortcode is present.
	 */
	public static function enqueue_assets() {
		if ( ! Lumination_Core_API::is_configured() ) {
			return;
		}

		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'lumination_quiz' ) ) {
			return;
		}

		// ── CSS ──
		wp_enqueue_style(
			'lumination-quiz',
			LUMINATION_QG_URL . 'assets/css/quiz-generator.css',
			array(),
			LUMINATION_QG_VERSION
		);

		$color_css = self::get_color_css();
		if ( $color_css ) {
			wp_add_inline_style( 'lumination-quiz', $color_css );
		}

		// ── Vendor scripts (shared handles, de-duplicated) ──
		if ( ! wp_script_is( 'lumination-marked', 'registered' ) ) {
			wp_register_script(
				'lumination-marked',
				LUMINATION_QG_URL . 'assets/js/vendor/marked.min.js',
				array(),
				LUMINATION_QG_VERSION,
				true
			);
		}
		if ( ! wp_script_is( 'lumination-purify', 'registered' ) ) {
			wp_register_script(
				'lumination-purify',
				LUMINATION_QG_URL . 'assets/js/vendor/purify.min.js',
				array(),
				LUMINATION_QG_VERSION,
				true
			);
		}

		wp_enqueue_script( 'lumination-marked' );
		wp_enqueue_script( 'lumination-purify' );

		// ── MathJax from Core ──
		Lumination_Core_Math::enqueue( 'lumination-quiz' );

		// ── Main script ──
		wp_enqueue_script(
			'lumination-quiz',
			LUMINATION_QG_URL . 'assets/js/quiz-generator.js',
			array( 'lumination-marked', 'lumination-purify', 'lumination-core-math-renderer' ),
			LUMINATION_QG_VERSION,
			true
		);

		wp_localize_script( 'lumination-quiz', 'luminationQuizConfig', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lumination_quiz_nonce' ),
			'i18n'    => array(
				'generating'     => __( 'Processing content...', 'lumination-quiz-generator' ),
				'building'       => __( 'Generating quiz questions...', 'lumination-quiz-generator' ),
				'almostDone'     => __( 'Almost done...', 'lumination-quiz-generator' ),
				'error'          => __( 'Something went wrong. Please try again.', 'lumination-quiz-generator' ),
				'dropFile'       => __( 'Drop a PDF here or click to upload', 'lumination-quiz-generator' ),
				'fileTooBig'     => __( 'File must be under 10 MB.', 'lumination-quiz-generator' ),
				'invalidType'    => __( 'Only PDF files are accepted.', 'lumination-quiz-generator' ),
				'correct'        => __( 'Correct!', 'lumination-quiz-generator' ),
				'incorrect'      => __( 'Incorrect.', 'lumination-quiz-generator' ),
				'nextQuestion'   => __( 'Next Question', 'lumination-quiz-generator' ),
				'seeResults'     => __( 'See Results', 'lumination-quiz-generator' ),
				'retake'         => __( 'Retake Quiz', 'lumination-quiz-generator' ),
				'newQuiz'        => __( 'New Quiz', 'lumination-quiz-generator' ),
				'exportQA'       => __( 'Export Q&A', 'lumination-quiz-generator' ),
				'exportScore'    => __( 'Export Score Report', 'lumination-quiz-generator' ),
				'question'       => __( 'Question', 'lumination-quiz-generator' ),
				'of'             => __( 'of', 'lumination-quiz-generator' ),
				'score'          => __( 'Score', 'lumination-quiz-generator' ),
				'timeUp'         => __( 'Time is up!', 'lumination-quiz-generator' ),
			),
		) );
	}

	/**
	 * Build inline CSS from Core colour settings.
	 *
	 * @return string
	 */
	private static function get_color_css() {
		$primary    = Lumination_Core_Settings::get_color( 'primary' );
		$hover      = Lumination_Core_Settings::get_color( 'primary_hover' );
		$text       = Lumination_Core_Settings::get_color( 'button_text' );
		$background = Lumination_Core_Settings::get_color( 'tool_background' );
		$tool_text  = Lumination_Core_Settings::get_color( 'tool_text' );

		$vars = array();
		if ( $primary ) {
			$vars[] = '--lqg-primary:' . sanitize_hex_color( $primary );
		}
		if ( $hover ) {
			$vars[] = '--lqg-primary-hover:' . sanitize_hex_color( $hover );
		}
		if ( $text ) {
			$vars[] = '--lqg-btn-text:' . sanitize_hex_color( $text );
		}
		if ( $background ) {
			$vars[] = '--lqg-bg:' . sanitize_hex_color( $background );
		}
		if ( $tool_text ) {
			$vars[] = '--lqg-text:' . sanitize_hex_color( $tool_text );
		}

		if ( empty( $vars ) ) {
			return '';
		}

		return '.lqg-quiz{' . implode( ';', $vars ) . '}';
	}
}
