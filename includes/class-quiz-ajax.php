<?php
/**
 * Lumination Quiz Generator — AJAX Handlers
 *
 * The AI Tutor /quiz endpoint builds a full quiz from an uploaded document in
 * one asynchronous job. The flow is:
 *   1. handle_create    — package the input as a file, submit the quiz job.
 *   2. handle_status     — polled from the browser until the job completes.
 *   3. handle_questions  — read the finished quiz and return its questions.
 *
 * Text and URL inputs are wrapped as a .txt file so the document-based endpoint
 * can serve every input mode.
 *
 * @package    LuminationQuizGenerator
 * @since      1.0.0
 * @license    GPL-3.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lumination_Quiz_Ajax {

	/**
	 * Maximum context length in characters for text/URL input.
	 */
	const MAX_CONTEXT_LENGTH = 50000;

	/**
	 * Register AJAX actions (logged-in and guest).
	 */
	public static function register() {
		// Step 1: Create quiz from input.
		add_action( 'wp_ajax_lumination_quiz_create',        array( __CLASS__, 'handle_create' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_create', array( __CLASS__, 'handle_create' ) );

		// Step 2: Poll quiz status.
		add_action( 'wp_ajax_lumination_quiz_status',        array( __CLASS__, 'handle_status' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_status', array( __CLASS__, 'handle_status' ) );

		// Step 3: Fetch questions.
		add_action( 'wp_ajax_lumination_quiz_questions',        array( __CLASS__, 'handle_questions' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_questions', array( __CLASS__, 'handle_questions' ) );
	}

	// ─── Step 1: Create Quiz ─────────────────────────────────────────────────

	/**
	 * Package the input as a document and submit a quiz job.
	 *
	 * Accepts text, URL, or base64-encoded PDF plus question_count and difficulty.
	 * Returns quiz_uuid (the job request_id) for polling.
	 */
	public static function handle_create() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		if ( ! Lumination_Core_Security::can_submit( 'quiz' ) ) {
			Lumination_Core_Security::log_event( 'Unauthorized quiz access attempt' );
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'lumination-quiz-generator' ) ) );
		}

		$rate_check = Lumination_Core_Security::check_rate_limit( 'quiz_create', 10, MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$input_mode     = isset( $_POST['input_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['input_mode'] ) ) : '';
		$question_count = isset( $_POST['question_count'] ) ? absint( $_POST['question_count'] ) : 10;
		$difficulty     = isset( $_POST['difficulty'] ) ? sanitize_text_field( wp_unslash( $_POST['difficulty'] ) ) : 'intermediate';

		$allowed_modes = array( 'url', 'text', 'file' );
		if ( ! in_array( $input_mode, $allowed_modes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid input mode.', 'lumination-quiz-generator' ) ) );
		}

		// Clamp question count.
		$question_count = max( 3, min( 30, $question_count ) );

		// Validate difficulty.
		$allowed_difficulties = array( 'easy', 'intermediate', 'advanced' );
		if ( ! in_array( $difficulty, $allowed_difficulties, true ) ) {
			$difficulty = 'intermediate';
		}

		// ── Build the document payload based on input mode ───────────────────

		$file_b64  = '';
		$file_name = 'content.txt';

		switch ( $input_mode ) {
			case 'url':
				$url = isset( $_POST['input_value'] ) ? esc_url_raw( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter a valid URL.', 'lumination-quiz-generator' ) ) );
				}
				$content = self::extract_from_url( $url );
				if ( is_wp_error( $content ) ) {
					wp_send_json_error( array( 'message' => $content->get_error_message() ) );
				}
				$file_b64 = base64_encode( $content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- packaging plain text as a .txt upload, not obfuscation.
				break;

			case 'text':
				$raw_text = isset( $_POST['input_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $raw_text ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter some text.', 'lumination-quiz-generator' ) ) );
				}
				$content  = mb_substr( $raw_text, 0, self::MAX_CONTEXT_LENGTH );
				$file_b64 = base64_encode( $content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- packaging plain text as a .txt upload, not obfuscation.
				break;

			case 'file':
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- base64 data; sanitize_base64() applied below.
				$raw_file = isset( $_POST['input_value'] ) ? $_POST['input_value'] : '';
				$file_b64 = Lumination_Core_Security::sanitize_base64( $raw_file );
				if ( empty( $file_b64 ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file data.', 'lumination-quiz-generator' ) ) );
				}
				$file_name = 'document.pdf';
				break;
		}

		// ── Submit the quiz job ──────────────────────────────────────────────

		$submit = Lumination_Core_API::submit(
			'/quiz',
			array(
				'file_b64'         => $file_b64,
				'file_name'        => $file_name,
				'question_count'   => $question_count,
				'difficulty_level' => $difficulty,
			),
			'lumination-quiz-create'
		);

		if ( is_wp_error( $submit ) ) {
			Lumination_Core_Security::log_event( 'Quiz create error', array( 'error' => $submit->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to create quiz. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		if ( empty( $submit['request_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The API did not accept the request. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		wp_send_json_success( array(
			'quiz_uuid' => $submit['request_id'],
			'status'    => 'queued',
		) );
	}

	// ─── Step 2: Poll Status ─────────────────────────────────────────────────

	/**
	 * Poll quiz build status.
	 */
	public static function handle_status() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		$quiz_uuid = isset( $_POST['quiz_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_uuid'] ) ) : '';

		if ( empty( $quiz_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing quiz reference.', 'lumination-quiz-generator' ) ) );
		}

		$job = Lumination_Core_API::poll( $quiz_uuid );
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to check quiz status.', 'lumination-quiz-generator' ) ) );
		}

		$status = isset( $job['status'] ) ? $job['status'] : 'processing';

		// Map API status to the frontend's expected values.
		if ( 'completed' === $status ) {
			$out = 'succeeded';
		} elseif ( 'failed' === $status ) {
			$out = 'failed';
		} else {
			$out = 'processing';
		}

		wp_send_json_success( array(
			'status' => $out,
			'error'  => ( 'failed' === $status && ! empty( $job['error'] ) ) ? $job['error'] : null,
		) );
	}

	// ─── Step 3: Fetch Questions ─────────────────────────────────────────────

	/**
	 * Fetch generated quiz questions from the finished job.
	 */
	public static function handle_questions() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		$quiz_uuid = isset( $_POST['quiz_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_uuid'] ) ) : '';
		$page_url  = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		if ( empty( $quiz_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing quiz reference.', 'lumination-quiz-generator' ) ) );
		}

		$job = Lumination_Core_API::poll( $quiz_uuid );
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to fetch questions.', 'lumination-quiz-generator' ) ) );
		}

		$questions = isset( $job['result']['questions'] ) ? $job['result']['questions'] : array();

		if ( empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'No questions were generated. Try with more content.', 'lumination-quiz-generator' ) ) );
		}

		// Normalize questions for the frontend.
		$normalized = array();
		foreach ( $questions as $q ) {
			$json = isset( $q['question_json'] ) ? $q['question_json'] : array();

			// Strip letter prefixes from options (e.g., "A) Foo" → "Foo").
			$options       = isset( $json['options'] ) ? $json['options'] : array();
			$clean_options = array();
			foreach ( $options as $opt ) {
				$clean_options[] = preg_replace( '/^[A-Z]\)\s*/', '', $opt );
			}

			$normalized[] = array(
				'uuid'           => isset( $q['uuid'] ) ? $q['uuid'] : '',
				'question'       => isset( $json['question_text'] ) ? $json['question_text'] : '',
				'options'        => $clean_options,
				'correct_answer' => isset( $json['correct_answer'] ) ? $json['correct_answer'] : '',
				'explanation'    => isset( $json['explanation'] ) ? $json['explanation'] : '',
				'hint'           => isset( $json['hint'] ) ? $json['hint'] : '',
				'category'       => isset( $q['category_title'] ) ? $q['category_title'] : '',
			);
		}

		// Log analytics for quiz completion.
		Lumination_Core_Analytics::log_usage(
			'quiz',
			$page_url,
			isset( $job['input_tokens'] ) ? (int) $job['input_tokens'] : 0,
			isset( $job['output_tokens'] ) ? (int) $job['output_tokens'] : 0,
			isset( $job['credits_charged'] ) ? (float) $job['credits_charged'] : 0,
			'quiz'
		);

		wp_send_json_success( array(
			'questions' => $normalized,
		) );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Extract readable text from a URL.
	 *
	 * @param  string $url URL to fetch.
	 * @return string|WP_Error Extracted text or error.
	 */
	private static function extract_from_url( $url ) {
		$response = wp_remote_get( $url, array(
			'timeout'    => 15,
			'user-agent' => 'LuminationQuizGenerator/1.0 (WordPress)',
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'fetch_failed', __( 'Could not fetch the URL. Please check it and try again.', 'lumination-quiz-generator' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return new \WP_Error( 'fetch_failed', sprintf(
				/* translators: %d: HTTP status code */
				__( 'The URL returned an error (HTTP %d).', 'lumination-quiz-generator' ),
				$code
			) );
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return new \WP_Error( 'empty_body', __( 'The URL returned no content.', 'lumination-quiz-generator' ) );
		}

		// Strip non-content tags.
		$html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );
		$html = preg_replace( '#<nav[^>]*>.*?</nav>#is', '', $html );
		$html = preg_replace( '#<header[^>]*>.*?</header>#is', '', $html );
		$html = preg_replace( '#<footer[^>]*>.*?</footer>#is', '', $html );

		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( empty( $text ) ) {
			return new \WP_Error( 'empty_body', __( 'Could not extract any readable text from the URL.', 'lumination-quiz-generator' ) );
		}

		return mb_substr( $text, 0, self::MAX_CONTEXT_LENGTH );
	}
}
