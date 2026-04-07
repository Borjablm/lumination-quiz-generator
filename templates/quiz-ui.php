<?php
/**
 * Quiz Generator widget template.
 *
 * Variables available (set in Lumination_Quiz_Generator::render_shortcode):
 *   $lqg_mode, $lqg_title, $lqg_description, $lqg_button_text,
 *   $lqg_default_count, $lqg_default_difficulty
 *
 * Locally derived (prefixed per WP standards):
 *   $lumination_qg_is_auto, $lumination_qg_show_url,
 *   $lumination_qg_show_text, $lumination_qg_show_file
 *
 * @package LuminationQuizGenerator
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lumination_qg_is_auto  = ( 'auto' === $lqg_mode );
$lumination_qg_show_url  = $lumination_qg_is_auto || 'url' === $lqg_mode;
$lumination_qg_show_text = $lumination_qg_is_auto || 'text' === $lqg_mode;
$lumination_qg_show_file = $lumination_qg_is_auto || 'file' === $lqg_mode;
?>
<div class="lqg-quiz"
     data-mode="<?php echo esc_attr( $lqg_mode ); ?>"
     data-default-count="<?php echo esc_attr( $lqg_default_count ); ?>"
     data-default-difficulty="<?php echo esc_attr( $lqg_default_difficulty ); ?>">

	<!-- ── Setup Phase ── -->
	<div class="lqg-setup">

		<!-- Header -->
		<div class="lqg-header">
			<?php if ( ! empty( $lqg_title ) ) : ?>
				<h3 class="lqg-title"><?php echo esc_html( $lqg_title ); ?></h3>
			<?php endif; ?>
			<?php if ( ! empty( $lqg_description ) ) : ?>
				<p class="lqg-description"><?php echo esc_html( $lqg_description ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Input section -->
		<div class="lqg-input-section">

			<?php if ( $lumination_qg_is_auto ) : ?>
			<!-- Mode tabs -->
			<div class="lqg-tabs" role="tablist">
				<button class="lqg-tab lqg-tab--active" data-tab="url" role="tab" aria-selected="true">
					<?php esc_html_e( 'URL', 'lumination-quiz-generator' ); ?>
				</button>
				<button class="lqg-tab" data-tab="text" role="tab" aria-selected="false">
					<?php esc_html_e( 'Text', 'lumination-quiz-generator' ); ?>
				</button>
				<button class="lqg-tab" data-tab="file" role="tab" aria-selected="false">
					<?php esc_html_e( 'File', 'lumination-quiz-generator' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<?php if ( $lumination_qg_show_url ) : ?>
			<div class="lqg-tab-panel lqg-tab-panel--url" data-panel="url" role="tabpanel">
				<input type="url"
				       class="lqg-url-input"
				       placeholder="<?php esc_attr_e( 'https://example.com/article', 'lumination-quiz-generator' ); ?>"
				       aria-label="<?php esc_attr_e( 'URL to generate quiz from', 'lumination-quiz-generator' ); ?>" />
			</div>
			<?php endif; ?>

			<?php if ( $lumination_qg_show_text ) : ?>
			<div class="lqg-tab-panel lqg-tab-panel--text<?php echo ( $lumination_qg_is_auto || 'text' !== $lqg_mode ) ? ' lqg-hidden' : ''; ?>"
			     data-panel="text" role="tabpanel">
				<textarea class="lqg-text-input"
				          placeholder="<?php esc_attr_e( 'Paste your study material here...', 'lumination-quiz-generator' ); ?>"
				          rows="6"
				          aria-label="<?php esc_attr_e( 'Text to generate quiz from', 'lumination-quiz-generator' ); ?>"></textarea>
			</div>
			<?php endif; ?>

			<?php if ( $lumination_qg_show_file ) : ?>
			<div class="lqg-tab-panel lqg-tab-panel--file<?php echo ( $lumination_qg_is_auto || 'file' !== $lqg_mode ) ? ' lqg-hidden' : ''; ?>"
			     data-panel="file" role="tabpanel">
				<div class="lqg-drop-zone" tabindex="0" role="button"
				     aria-label="<?php esc_attr_e( 'Drop a PDF here or click to upload', 'lumination-quiz-generator' ); ?>">
					<svg class="lqg-drop-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
						<polyline points="14 2 14 8 20 8"></polyline>
						<line x1="12" y1="18" x2="12" y2="12"></line>
						<line x1="9" y1="15" x2="15" y2="15"></line>
					</svg>
					<p class="lqg-drop-text"><?php esc_html_e( 'Drop a PDF here or click to upload', 'lumination-quiz-generator' ); ?></p>
					<input type="file" class="lqg-file-input" accept=".pdf,application/pdf" hidden />
				</div>
				<p class="lqg-file-name lqg-hidden"></p>
			</div>
			<?php endif; ?>

		</div>

		<!-- Options row -->
		<div class="lqg-options">
			<div class="lqg-option-group">
				<label class="lqg-option-label" for="lqg-count"><?php esc_html_e( 'Questions', 'lumination-quiz-generator' ); ?></label>
				<input type="range" id="lqg-count" class="lqg-count-slider" min="3" max="30"
				       value="<?php echo esc_attr( $lqg_default_count ); ?>" />
				<span class="lqg-count-value"><?php echo esc_html( $lqg_default_count ); ?></span>
			</div>
			<div class="lqg-option-group">
				<label class="lqg-option-label"><?php esc_html_e( 'Difficulty', 'lumination-quiz-generator' ); ?></label>
				<div class="lqg-pill-group lqg-difficulty-pills">
					<button class="lqg-pill<?php echo 'easy' === $lqg_default_difficulty ? ' lqg-pill--active' : ''; ?>" data-value="easy">
						<?php esc_html_e( 'Easy', 'lumination-quiz-generator' ); ?>
					</button>
					<button class="lqg-pill<?php echo 'intermediate' === $lqg_default_difficulty ? ' lqg-pill--active' : ''; ?>" data-value="intermediate">
						<?php esc_html_e( 'Medium', 'lumination-quiz-generator' ); ?>
					</button>
					<button class="lqg-pill<?php echo 'advanced' === $lqg_default_difficulty ? ' lqg-pill--active' : ''; ?>" data-value="advanced">
						<?php esc_html_e( 'Hard', 'lumination-quiz-generator' ); ?>
					</button>
				</div>
			</div>
			<div class="lqg-option-group">
				<label class="lqg-option-label"><?php esc_html_e( 'Timer', 'lumination-quiz-generator' ); ?></label>
				<div class="lqg-pill-group lqg-timer-pills">
					<button class="lqg-pill lqg-pill--active" data-value="0">
						<?php esc_html_e( 'Off', 'lumination-quiz-generator' ); ?>
					</button>
					<button class="lqg-pill" data-value="300">
						<?php esc_html_e( '5 min', 'lumination-quiz-generator' ); ?>
					</button>
					<button class="lqg-pill" data-value="600">
						<?php esc_html_e( '10 min', 'lumination-quiz-generator' ); ?>
					</button>
					<button class="lqg-pill" data-value="900">
						<?php esc_html_e( '15 min', 'lumination-quiz-generator' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Submit -->
		<div class="lqg-submit-section">
			<button class="lqg-submit-btn" disabled>
				<?php echo esc_html( $lqg_button_text ); ?>
			</button>
		</div>

	</div>

	<!-- ── Loading Phase ── -->
	<div class="lqg-loading lqg-hidden">
		<div class="lqg-spinner"></div>
		<p class="lqg-loading-text"><?php esc_html_e( 'Processing content...', 'lumination-quiz-generator' ); ?></p>
		<div class="lqg-progress-bar">
			<div class="lqg-progress-fill"></div>
		</div>
	</div>

	<!-- ── Quiz Phase ── -->
	<div class="lqg-quiz-phase lqg-hidden">

		<!-- Quiz header -->
		<div class="lqg-quiz-header">
			<div class="lqg-quiz-progress">
				<span class="lqg-question-counter"></span>
				<div class="lqg-quiz-progress-bar">
					<div class="lqg-quiz-progress-fill"></div>
				</div>
			</div>
			<div class="lqg-timer-display lqg-hidden">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="12" r="10"></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg>
				<span class="lqg-timer-value"></span>
			</div>
		</div>

		<!-- Question area -->
		<div class="lqg-question-area">
			<p class="lqg-category-label"></p>
			<h4 class="lqg-question-text"></h4>
			<div class="lqg-options-list"></div>
			<div class="lqg-hint lqg-hidden">
				<button class="lqg-hint-btn"><?php esc_html_e( 'Show Hint', 'lumination-quiz-generator' ); ?></button>
				<p class="lqg-hint-text lqg-hidden"></p>
			</div>
		</div>

		<!-- Feedback area -->
		<div class="lqg-feedback lqg-hidden">
			<div class="lqg-feedback-header"></div>
			<p class="lqg-feedback-explanation"></p>
			<button class="lqg-next-btn"></button>
		</div>

	</div>

	<!-- ── Results Phase ── -->
	<div class="lqg-results lqg-hidden">
		<div class="lqg-results-header">
			<h3 class="lqg-results-title"><?php esc_html_e( 'Quiz Complete!', 'lumination-quiz-generator' ); ?></h3>
			<div class="lqg-score-display">
				<span class="lqg-score-value"></span>
				<span class="lqg-score-label"></span>
			</div>
			<div class="lqg-score-bar">
				<div class="lqg-score-fill"></div>
			</div>
		</div>

		<div class="lqg-results-breakdown"></div>

		<div class="lqg-results-actions">
			<button class="lqg-retake-btn"><?php esc_html_e( 'Retake Quiz', 'lumination-quiz-generator' ); ?></button>
			<button class="lqg-new-btn"><?php esc_html_e( 'New Quiz', 'lumination-quiz-generator' ); ?></button>
			<button class="lqg-export-qa-btn"><?php esc_html_e( 'Export Q&A', 'lumination-quiz-generator' ); ?></button>
			<button class="lqg-export-score-btn"><?php esc_html_e( 'Export Score Report', 'lumination-quiz-generator' ); ?></button>
		</div>
	</div>

</div>
