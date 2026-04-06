<?php
/**
 * Lumination Quiz Generator — AJAX Handlers
 *
 * Handles the multi-step quiz flow:
 *   1. Upload content (text/URL/PDF) → process-material → document_uuid
 *   2. Create quiz from document → quiz_uuid
 *   3. Poll quiz build status
 *   4. Fetch generated questions
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
		// Step 1: Process content into a document.
		add_action( 'wp_ajax_lumination_quiz_process',        array( __CLASS__, 'handle_process' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_process', array( __CLASS__, 'handle_process' ) );

		// Step 2: Create quiz from document.
		add_action( 'wp_ajax_lumination_quiz_create',        array( __CLASS__, 'handle_create' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_create', array( __CLASS__, 'handle_create' ) );

		// Step 3: Poll quiz status.
		add_action( 'wp_ajax_lumination_quiz_status',        array( __CLASS__, 'handle_status' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_status', array( __CLASS__, 'handle_status' ) );

		// Step 4: Fetch questions.
		add_action( 'wp_ajax_lumination_quiz_questions',        array( __CLASS__, 'handle_questions' ) );
		add_action( 'wp_ajax_nopriv_lumination_quiz_questions', array( __CLASS__, 'handle_questions' ) );
	}

	// ─── Step 1: Process Content ─────────────────────────────────────────────

	/**
	 * Process input content into a Lumination document.
	 *
	 * Accepts text, URL, or base64-encoded PDF.
	 * Returns document_uuid for use in quiz creation.
	 */
	public static function handle_process() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		if ( ! Lumination_Core_Security::can_submit( 'quiz' ) ) {
			Lumination_Core_Security::log_event( 'Unauthorized quiz access attempt' );
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'lumination-quiz-generator' ) ) );
		}

		$rate_check = Lumination_Core_Security::check_rate_limit( 'quiz_process', 10, MINUTE_IN_SECONDS );
		if ( is_wp_error( $rate_check ) ) {
			wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
		}

		$input_mode = isset( $_POST['input_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['input_mode'] ) ) : '';
		$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		$allowed_modes = array( 'url', 'text', 'file' );
		if ( ! in_array( $input_mode, $allowed_modes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid input mode.', 'lumination-quiz-generator' ) ) );
		}

		$content      = '';
		$content_type = 'text/plain';
		$input_type   = 'text';

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
				$input_type = 'text';
				break;

			case 'text':
				$raw_text = isset( $_POST['input_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['input_value'] ) ) : '';
				if ( empty( $raw_text ) ) {
					wp_send_json_error( array( 'message' => __( 'Please enter some text.', 'lumination-quiz-generator' ) ) );
				}
				$content    = mb_substr( $raw_text, 0, self::MAX_CONTEXT_LENGTH );
				$input_type = 'text';
				break;

			case 'file':
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- base64 data; sanitize_base64() applied below.
				$file_data = isset( $_POST['input_value'] ) ? $_POST['input_value'] : '';
				if ( empty( $file_data ) ) {
					wp_send_json_error( array( 'message' => __( 'No file data provided.', 'lumination-quiz-generator' ) ) );
				}
				$file_data = Lumination_Core_Security::sanitize_base64( $file_data );
				if ( empty( $file_data ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file data.', 'lumination-quiz-generator' ) ) );
				}
				$content      = $file_data;
				$content_type = 'application/pdf';
				$input_type   = 'pdf';
				break;
		}

		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not extract any content from the provided input.', 'lumination-quiz-generator' ) ) );
		}

		// Call process-material endpoint.
		$pm_body = array(
			'items' => array(
				array(
					'content'      => $content,
					'content_type' => $content_type,
				),
			),
			'persist_to_db' => true,
		);

		$result = Lumination_Core_API::request(
			'/lumination-ai/api/v1/process-material',
			$pm_body,
			'lumination-quiz-pm'
		);

		if ( is_wp_error( $result ) ) {
			Lumination_Core_Security::log_event( 'Quiz process-material error', array( 'error' => $result->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to process content. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		// Extract document_id from response.
		$doc_result = isset( $result['results'][0] ) ? $result['results'][0] : null;

		if ( ! $doc_result || empty( $doc_result['success'] ) || empty( $doc_result['document']['document_id'] ) ) {
			$err = isset( $doc_result['error'] ) ? $doc_result['error'] : 'unknown';
			Lumination_Core_Security::log_event( 'Quiz process-material failed', array( 'error' => $err ) );
			wp_send_json_error( array( 'message' => __( 'Failed to process content. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		// Log analytics for the processing step.
		Lumination_Core_Analytics::log_usage(
			'quiz',
			$page_url,
			0,
			0,
			0,
			$input_type
		);

		wp_send_json_success( array(
			'document_uuid' => $doc_result['document']['document_id'],
		) );
	}

	// ─── Step 2: Create Quiz ─────────────────────────────────────────────────

	/**
	 * Create a quiz from a processed document.
	 */
	public static function handle_create() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		if ( ! Lumination_Core_Security::can_submit( 'quiz' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'lumination-quiz-generator' ) ) );
		}

		$document_uuid  = isset( $_POST['document_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['document_uuid'] ) ) : '';
		$question_count = isset( $_POST['question_count'] ) ? absint( $_POST['question_count'] ) : 10;
		$difficulty     = isset( $_POST['difficulty'] ) ? sanitize_text_field( wp_unslash( $_POST['difficulty'] ) ) : 'intermediate';

		if ( empty( $document_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing document reference.', 'lumination-quiz-generator' ) ) );
		}

		// Clamp question count.
		$question_count = max( 3, min( 30, $question_count ) );

		// Validate difficulty.
		$allowed_difficulties = array( 'easy', 'intermediate', 'advanced' );
		if ( ! in_array( $difficulty, $allowed_difficulties, true ) ) {
			$difficulty = 'intermediate';
		}

		$quiz_body = array(
			'document_uuids'   => array( $document_uuid ),
			'language_code'    => 'en',
			'difficulty_level' => $difficulty,
			'question_types'   => array( 'multiple_choice' ),
			'question_count'   => $question_count,
		);

		$result = Lumination_Core_API::request(
			'/lumination-ai/api/v1/features/quizzes',
			$quiz_body,
			'lumination-quiz-create'
		);

		if ( is_wp_error( $result ) ) {
			Lumination_Core_Security::log_event( 'Quiz create error', array( 'error' => $result->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'Failed to create quiz. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		if ( empty( $result['success'] ) || empty( $result['quiz_uuid'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create quiz. Please try again.', 'lumination-quiz-generator' ) ) );
		}

		wp_send_json_success( array(
			'quiz_uuid' => $result['quiz_uuid'],
			'task_id'   => isset( $result['task_id'] ) ? $result['task_id'] : '',
			'status'    => isset( $result['status'] ) ? $result['status'] : 'queued',
		) );
	}

	// ─── Step 3: Poll Status ─────────────────────────────────────────────────

	/**
	 * Poll quiz build status.
	 */
	public static function handle_status() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		$quiz_uuid = isset( $_POST['quiz_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_uuid'] ) ) : '';

		if ( empty( $quiz_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing quiz reference.', 'lumination-quiz-generator' ) ) );
		}

		$result = self::api_get( '/lumination-ai/api/v1/features/quizzes/' . $quiz_uuid );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to check quiz status.', 'lumination-quiz-generator' ) ) );
		}

		$quiz = isset( $result['quiz'] ) ? $result['quiz'] : array();

		wp_send_json_success( array(
			'status'         => isset( $quiz['status'] ) ? $quiz['status'] : 'unknown',
			'question_count' => isset( $quiz['question_count'] ) ? (int) $quiz['question_count'] : 0,
			'error'          => isset( $quiz['error'] ) ? $quiz['error'] : null,
		) );
	}

	// ─── Step 4: Fetch Questions ─────────────────────────────────────────────

	/**
	 * Fetch generated quiz questions.
	 */
	public static function handle_questions() {
		check_ajax_referer( 'lumination_quiz_nonce', 'nonce' );

		$quiz_uuid = isset( $_POST['quiz_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['quiz_uuid'] ) ) : '';
		$page_url  = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		if ( empty( $quiz_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing quiz reference.', 'lumination-quiz-generator' ) ) );
		}

		$result = self::api_get( '/lumination-ai/api/v1/features/quizzes/' . $quiz_uuid . '/questions?limit=200' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to fetch questions.', 'lumination-quiz-generator' ) ) );
		}

		$questions = isset( $result['questions'] ) ? $result['questions'] : array();

		if ( empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'No questions were generated. Try with more content.', 'lumination-quiz-generator' ) ) );
		}

		// Normalize questions for the frontend.
		$normalized = array();
		foreach ( $questions as $q ) {
			$json = isset( $q['question_json'] ) ? $q['question_json'] : array();

			// Strip letter prefixes from options (e.g., "A) Foo" → "Foo").
			$options = isset( $json['options'] ) ? $json['options'] : array();
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
			isset( $result['token_count_input'] ) ? (int) $result['token_count_input'] : 0,
			isset( $result['token_count_output'] ) ? (int) $result['token_count_output'] : 0,
			isset( $result['credits_charged'] ) ? (float) $result['credits_charged'] : 0,
			'quiz'
		);

		wp_send_json_success( array(
			'questions' => $normalized,
		) );
	}

	// ─── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Make a GET request to the Lumination API.
	 *
	 * Core's API class only supports POST. Quiz polling and question
	 * fetching require GET, so this helper mirrors the Core pattern
	 * but uses wp_remote_get instead.
	 *
	 * @param  string $endpoint API path.
	 * @return array|WP_Error Decoded JSON response or error.
	 */
	private static function api_get( $endpoint ) {
		$api_key = get_option( 'lumination_api_key', '' );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'lumination-quiz-generator' ) );
		}

		$url = LUMINATION_API_BASE_URL . $endpoint;

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'X-API-KEY'    => $api_key,
				'X-REQUEST-ID' => 'lumination-quiz-get-' . time(),
			),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'api_request_failed', __( 'Failed to connect to Lumination API.', 'lumination-quiz-generator' ) );
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body_content = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', sprintf(
				/* translators: %d: HTTP status code */
				__( 'API returned error code: %d', 'lumination-quiz-generator' ),
				$code
			) );
		}

		$data = json_decode( $body_content, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON response from API.', 'lumination-quiz-generator' ) );
		}

		return $data;
	}

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

		return mb_substr( $text, 0, self::MAX_CONTEXT_LENGTH );
	}
}
