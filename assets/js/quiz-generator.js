/**
 * Lumination Quiz Generator — Front-end JavaScript
 *
 * Handles content input (URL, text, PDF), quiz generation via the
 * async API flow, interactive quiz-taking, scoring, timer, and CSV export.
 *
 * @package LuminationQuizGenerator
 * @since   1.0.0
 */
(function () {
	'use strict';

	var cfg = window.luminationQuizConfig || {};

	document.addEventListener('DOMContentLoaded', function () {
		var widgets = document.querySelectorAll('.lqg-quiz');
		for (var i = 0; i < widgets.length; i++) {
			initWidget(widgets[i]);
		}
	});

	function initWidget(root) {
		var state = {
			mode: root.dataset.mode || 'auto',
			activeTab: root.dataset.mode === 'auto' ? 'url' : root.dataset.mode,
			difficulty: root.dataset.defaultDifficulty || 'intermediate',
			questionCount: parseInt(root.dataset.defaultCount, 10) || 10,
			timerSeconds: 0,
			fileData: null,
			fileName: null,
			isProcessing: false,
			// Quiz state
			questions: [],
			currentIndex: 0,
			answers: [],
			score: 0,
			quizUuid: null,
			timerInterval: null,
			timerRemaining: 0
		};

		var els = {
			setup: root.querySelector('.lqg-setup'),
			loading: root.querySelector('.lqg-loading'),
			loadingText: root.querySelector('.lqg-loading-text'),
			progressFill: root.querySelector('.lqg-progress-fill'),
			quizPhase: root.querySelector('.lqg-quiz-phase'),
			results: root.querySelector('.lqg-results'),
			// Setup elements
			tabs: root.querySelectorAll('.lqg-tab'),
			panels: root.querySelectorAll('.lqg-tab-panel'),
			urlInput: root.querySelector('.lqg-url-input'),
			textInput: root.querySelector('.lqg-text-input'),
			dropZone: root.querySelector('.lqg-drop-zone'),
			fileInput: root.querySelector('.lqg-file-input'),
			fileName: root.querySelector('.lqg-file-name'),
			countSlider: root.querySelector('.lqg-count-slider'),
			countValue: root.querySelector('.lqg-count-value'),
			difficultyPills: root.querySelectorAll('.lqg-difficulty-pills .lqg-pill'),
			timerPills: root.querySelectorAll('.lqg-timer-pills .lqg-pill'),
			submitBtn: root.querySelector('.lqg-submit-btn'),
			// Quiz elements
			questionCounter: root.querySelector('.lqg-question-counter'),
			quizProgressFill: root.querySelector('.lqg-quiz-progress-fill'),
			timerDisplay: root.querySelector('.lqg-timer-display'),
			timerValue: root.querySelector('.lqg-timer-value'),
			categoryLabel: root.querySelector('.lqg-category-label'),
			questionText: root.querySelector('.lqg-question-text'),
			optionsList: root.querySelector('.lqg-options-list'),
			hintArea: root.querySelector('.lqg-hint'),
			hintBtn: root.querySelector('.lqg-hint-btn'),
			hintText: root.querySelector('.lqg-hint-text'),
			feedback: root.querySelector('.lqg-feedback'),
			feedbackHeader: root.querySelector('.lqg-feedback-header'),
			feedbackExplanation: root.querySelector('.lqg-feedback-explanation'),
			nextBtn: root.querySelector('.lqg-next-btn'),
			// Results elements
			scoreValue: root.querySelector('.lqg-score-value'),
			scoreLabel: root.querySelector('.lqg-score-label'),
			scoreFill: root.querySelector('.lqg-score-fill'),
			resultsBreakdown: root.querySelector('.lqg-results-breakdown'),
			retakeBtn: root.querySelector('.lqg-retake-btn'),
			newBtn: root.querySelector('.lqg-new-btn'),
			exportQaBtn: root.querySelector('.lqg-export-qa-btn'),
			exportScoreBtn: root.querySelector('.lqg-export-score-btn')
		};

		setupTabs(state, els);
		setupPills(state, els);
		setupSlider(state, els);
		setupInputListeners(state, els);
		setupFileUpload(state, els);
		setupSubmit(state, els);
		setupResultActions(state, els);
	}

	/* ── Tabs ── */

	function setupTabs(state, els) {
		for (var i = 0; i < els.tabs.length; i++) {
			els.tabs[i].addEventListener('click', function () {
				var tab = this.dataset.tab;
				state.activeTab = tab;
				for (var j = 0; j < els.tabs.length; j++) {
					els.tabs[j].classList.toggle('lqg-tab--active', els.tabs[j].dataset.tab === tab);
					els.tabs[j].setAttribute('aria-selected', els.tabs[j].dataset.tab === tab ? 'true' : 'false');
				}
				for (var k = 0; k < els.panels.length; k++) {
					els.panels[k].classList.toggle('lqg-hidden', els.panels[k].dataset.panel !== tab);
				}
				updateSubmitState(state, els);
			});
		}
	}

	/* ── Pills ── */

	function setupPills(state, els) {
		for (var i = 0; i < els.difficultyPills.length; i++) {
			els.difficultyPills[i].addEventListener('click', function () {
				state.difficulty = this.dataset.value;
				for (var j = 0; j < els.difficultyPills.length; j++) {
					els.difficultyPills[j].classList.toggle('lqg-pill--active', els.difficultyPills[j].dataset.value === state.difficulty);
				}
			});
		}
		for (var i = 0; i < els.timerPills.length; i++) {
			els.timerPills[i].addEventListener('click', function () {
				state.timerSeconds = parseInt(this.dataset.value, 10);
				for (var j = 0; j < els.timerPills.length; j++) {
					els.timerPills[j].classList.toggle('lqg-pill--active', els.timerPills[j].dataset.value === this.dataset.value);
				}
			});
		}
	}

	/* ── Slider ── */

	function setupSlider(state, els) {
		if (!els.countSlider) return;
		els.countSlider.addEventListener('input', function () {
			state.questionCount = parseInt(this.value, 10);
			els.countValue.textContent = this.value;
		});
	}

	/* ── Input Listeners ── */

	function setupInputListeners(state, els) {
		if (els.urlInput) {
			els.urlInput.addEventListener('input', function () { updateSubmitState(state, els); });
		}
		if (els.textInput) {
			els.textInput.addEventListener('input', function () { updateSubmitState(state, els); });
		}
	}

	function updateSubmitState(state, els) {
		var hasInput = false;
		if (state.activeTab === 'url' && els.urlInput) {
			hasInput = els.urlInput.value.trim().length > 0;
		} else if (state.activeTab === 'text' && els.textInput) {
			hasInput = els.textInput.value.trim().length > 0;
		} else if (state.activeTab === 'file') {
			hasInput = !!state.fileData;
		}
		els.submitBtn.disabled = !hasInput || state.isProcessing;
	}

	/* ── File Upload ── */

	function setupFileUpload(state, els) {
		if (!els.dropZone) return;

		els.dropZone.addEventListener('click', function () { els.fileInput.click(); });
		els.dropZone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); els.fileInput.click(); }
		});

		els.dropZone.addEventListener('dragover', function (e) {
			e.preventDefault();
			this.classList.add('lqg-drop-zone--active');
		});
		els.dropZone.addEventListener('dragleave', function () {
			this.classList.remove('lqg-drop-zone--active');
		});
		els.dropZone.addEventListener('drop', function (e) {
			e.preventDefault();
			this.classList.remove('lqg-drop-zone--active');
			if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0], state, els);
		});

		els.fileInput.addEventListener('change', function () {
			if (this.files.length) handleFile(this.files[0], state, els);
		});
	}

	function handleFile(file, state, els) {
		if (file.type !== 'application/pdf') {
			alert(cfg.i18n.invalidType);
			return;
		}
		if (file.size > 10 * 1024 * 1024) {
			alert(cfg.i18n.fileTooBig);
			return;
		}
		state.fileName = file.name;
		var reader = new FileReader();
		reader.onload = function () {
			state.fileData = reader.result.split(',')[1]; // Strip data-URI prefix.
			els.fileName.textContent = file.name;
			els.fileName.classList.remove('lqg-hidden');
			els.dropZone.querySelector('.lqg-drop-text').textContent = file.name;
			updateSubmitState(state, els);
		};
		reader.readAsDataURL(file);
	}

	/* ── Submit & Quiz Flow ── */

	function setupSubmit(state, els) {
		els.submitBtn.addEventListener('click', function () {
			if (state.isProcessing) return;
			startQuizFlow(state, els);
		});
	}

	function startQuizFlow(state, els) {
		state.isProcessing = true;
		els.submitBtn.disabled = true;
		showPhase(els, 'loading');
		setLoadingText(els, cfg.i18n.generating, 10);

		// Step 1: Process content.
		var inputMode = state.activeTab;
		var inputValue = '';
		if (inputMode === 'url') inputValue = els.urlInput.value.trim();
		else if (inputMode === 'text') inputValue = els.textInput.value.trim();
		else if (inputMode === 'file') inputValue = state.fileData;

		var fd = new FormData();
		fd.append('action', 'lumination_quiz_process');
		fd.append('nonce', cfg.nonce);
		fd.append('input_mode', inputMode);
		fd.append('input_value', inputValue);
		fd.append('page_url', window.location.href);

		fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				if (!resp.success) throw new Error(resp.data && resp.data.message ? resp.data.message : cfg.i18n.error);

				setLoadingText(els, cfg.i18n.building, 30);

				// Step 2: Create quiz.
				var fd2 = new FormData();
				fd2.append('action', 'lumination_quiz_create');
				fd2.append('nonce', cfg.nonce);
				fd2.append('document_uuid', resp.data.document_uuid);
				fd2.append('question_count', state.questionCount);
				fd2.append('difficulty', state.difficulty);

				return fetch(cfg.ajaxUrl, { method: 'POST', body: fd2 });
			})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				if (!resp.success) throw new Error(resp.data && resp.data.message ? resp.data.message : cfg.i18n.error);
				state.quizUuid = resp.data.quiz_uuid;

				// Step 3: Poll status.
				return pollQuizStatus(state, els);
			})
			.then(function () {
				setLoadingText(els, cfg.i18n.almostDone, 90);

				// Step 4: Fetch questions.
				var fd3 = new FormData();
				fd3.append('action', 'lumination_quiz_questions');
				fd3.append('nonce', cfg.nonce);
				fd3.append('quiz_uuid', state.quizUuid);
				fd3.append('page_url', window.location.href);

				return fetch(cfg.ajaxUrl, { method: 'POST', body: fd3 });
			})
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				if (!resp.success) throw new Error(resp.data && resp.data.message ? resp.data.message : cfg.i18n.error);
				state.questions = resp.data.questions;
				state.currentIndex = 0;
				state.answers = [];
				state.score = 0;
				state.isProcessing = false;
				startQuiz(state, els);
			})
			.catch(function (err) {
				state.isProcessing = false;
				els.submitBtn.disabled = false;
				showPhase(els, 'setup');
				alert(err.message || cfg.i18n.error);
			});
	}

	function pollQuizStatus(state, els) {
		return new Promise(function (resolve, reject) {
			var attempts = 0;
			var maxAttempts = 60; // 60 × 3s = 3 minutes max.

			function poll() {
				attempts++;
				if (attempts > maxAttempts) {
					reject(new Error('Quiz generation timed out. Please try again.'));
					return;
				}

				var progress = Math.min(30 + (attempts / maxAttempts) * 55, 85);
				setLoadingText(els, cfg.i18n.building, progress);

				var fd = new FormData();
				fd.append('action', 'lumination_quiz_status');
				fd.append('nonce', cfg.nonce);
				fd.append('quiz_uuid', state.quizUuid);

				fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
					.then(function (r) { return r.json(); })
					.then(function (resp) {
						if (!resp.success) { reject(new Error(cfg.i18n.error)); return; }
						var status = resp.data.status;
						if (status === 'succeeded') { resolve(); return; }
						if (status === 'failed') {
							reject(new Error(resp.data.error || 'Quiz generation failed.'));
							return;
						}
						setTimeout(poll, 3000);
					})
					.catch(reject);
			}

			poll();
		});
	}

	/* ── Quiz Interaction ── */

	function startQuiz(state, els) {
		shuffleArray(state.questions);
		showPhase(els, 'quiz');
		if (state.timerSeconds > 0) startTimer(state, els);
		renderQuestion(state, els);
	}

	function renderQuestion(state, els) {
		var q = state.questions[state.currentIndex];
		var total = state.questions.length;
		var num = state.currentIndex + 1;

		els.questionCounter.textContent = cfg.i18n.question + ' ' + num + ' ' + cfg.i18n.of + ' ' + total;
		els.quizProgressFill.style.width = ((num / total) * 100) + '%';
		els.categoryLabel.textContent = q.category || '';
		els.questionText.textContent = q.question;

		// Render options.
		els.optionsList.innerHTML = '';
		var letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
		for (var i = 0; i < q.options.length; i++) {
			var btn = document.createElement('button');
			btn.className = 'lqg-option-btn';
			btn.type = 'button';
			btn.dataset.index = i;
			btn.dataset.letter = letters[i] || '';
			btn.innerHTML = '<span class="lqg-option-letter">' + (letters[i] || '') + '</span>' +
				'<span class="lqg-option-text">' + escapeHtml(q.options[i]) + '</span>';
			btn.addEventListener('click', function () { onSelectAnswer(this, state, els); });
			els.optionsList.appendChild(btn);
		}

		// Hint.
		if (q.hint) {
			els.hintArea.classList.remove('lqg-hidden');
			els.hintText.textContent = q.hint;
			els.hintText.classList.add('lqg-hidden');
			els.hintBtn.textContent = 'Show Hint';
			els.hintBtn.onclick = function () {
				els.hintText.classList.toggle('lqg-hidden');
				els.hintBtn.textContent = els.hintText.classList.contains('lqg-hidden') ? 'Show Hint' : 'Hide Hint';
			};
		} else {
			els.hintArea.classList.add('lqg-hidden');
		}

		// Hide feedback.
		els.feedback.classList.add('lqg-hidden');

		// Render math if present.
		if (window.LuminationMathRenderer) {
			window.LuminationMathRenderer.render(els.questionText, q.question);
		}
	}

	function onSelectAnswer(btn, state, els) {
		if (btn.classList.contains('lqg-option-btn--disabled')) return;

		var q = state.questions[state.currentIndex];
		var selectedLetter = btn.dataset.letter;
		var isCorrect = selectedLetter === q.correct_answer;

		if (isCorrect) state.score++;

		state.answers.push({
			questionIndex: state.currentIndex,
			selected: selectedLetter,
			correct: q.correct_answer,
			isCorrect: isCorrect
		});

		// Disable all options and highlight.
		var buttons = els.optionsList.querySelectorAll('.lqg-option-btn');
		for (var i = 0; i < buttons.length; i++) {
			buttons[i].classList.add('lqg-option-btn--disabled');
			if (buttons[i].dataset.letter === q.correct_answer) {
				buttons[i].classList.add('lqg-option-btn--correct');
			}
			if (buttons[i] === btn && !isCorrect) {
				buttons[i].classList.add('lqg-option-btn--incorrect');
			}
		}

		// Show feedback.
		els.feedback.classList.remove('lqg-hidden');
		els.feedbackHeader.textContent = isCorrect ? cfg.i18n.correct : cfg.i18n.incorrect;
		els.feedbackHeader.className = 'lqg-feedback-header ' + (isCorrect ? 'lqg-feedback--correct' : 'lqg-feedback--incorrect');
		els.feedbackExplanation.textContent = q.explanation || '';

		var isLast = state.currentIndex >= state.questions.length - 1;
		els.nextBtn.textContent = isLast ? cfg.i18n.seeResults : cfg.i18n.nextQuestion;
		els.nextBtn.onclick = function () {
			if (isLast) {
				stopTimer(state);
				showResults(state, els);
			} else {
				state.currentIndex++;
				renderQuestion(state, els);
			}
		};

		if (window.LuminationMathRenderer && q.explanation) {
			window.LuminationMathRenderer.render(els.feedbackExplanation, q.explanation);
		}
	}

	/* ── Timer ── */

	function startTimer(state, els) {
		state.timerRemaining = state.timerSeconds;
		els.timerDisplay.classList.remove('lqg-hidden');
		updateTimerDisplay(state, els);

		state.timerInterval = setInterval(function () {
			state.timerRemaining--;
			updateTimerDisplay(state, els);
			if (state.timerRemaining <= 0) {
				stopTimer(state);
				// Auto-submit remaining unanswered questions.
				while (state.answers.length < state.questions.length) {
					state.answers.push({
						questionIndex: state.answers.length,
						selected: null,
						correct: state.questions[state.answers.length].correct_answer,
						isCorrect: false
					});
				}
				showResults(state, els);
			}
		}, 1000);
	}

	function stopTimer(state) {
		if (state.timerInterval) {
			clearInterval(state.timerInterval);
			state.timerInterval = null;
		}
	}

	function updateTimerDisplay(state, els) {
		var mins = Math.floor(state.timerRemaining / 60);
		var secs = state.timerRemaining % 60;
		els.timerValue.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
		if (state.timerRemaining <= 30) {
			els.timerDisplay.classList.add('lqg-timer--warning');
		}
	}

	/* ── Results ── */

	function showResults(state, els) {
		showPhase(els, 'results');
		var total = state.questions.length;
		var pct = Math.round((state.score / total) * 100);

		els.scoreValue.textContent = pct + '%';
		els.scoreLabel.textContent = state.score + ' / ' + total + ' correct';
		els.scoreFill.style.width = pct + '%';
		els.scoreFill.className = 'lqg-score-fill ' +
			(pct >= 80 ? 'lqg-score--great' : pct >= 50 ? 'lqg-score--ok' : 'lqg-score--poor');

		// Breakdown.
		els.resultsBreakdown.innerHTML = '';
		for (var i = 0; i < state.questions.length; i++) {
			var q = state.questions[i];
			var a = state.answers[i];
			var row = document.createElement('div');
			row.className = 'lqg-result-row ' + (a && a.isCorrect ? 'lqg-result--correct' : 'lqg-result--incorrect');

			var icon = a && a.isCorrect ? '&#10003;' : '&#10007;';
			var selectedText = '';
			if (!a || a.selected === null) {
				selectedText = 'Not answered';
			} else {
				var idx = a.selected.charCodeAt(0) - 65;
				selectedText = q.options[idx] || a.selected;
			}

			row.innerHTML = '<div class="lqg-result-icon">' + icon + '</div>' +
				'<div class="lqg-result-content">' +
					'<p class="lqg-result-question">' + escapeHtml(q.question) + '</p>' +
					'<p class="lqg-result-answer">Your answer: ' + escapeHtml(selectedText) + '</p>' +
					(a && !a.isCorrect ? '<p class="lqg-result-correct">Correct: ' + escapeHtml(q.options[q.correct_answer.charCodeAt(0) - 65] || q.correct_answer) + '</p>' : '') +
				'</div>';
			els.resultsBreakdown.appendChild(row);
		}
	}

	function setupResultActions(state, els) {
		els.retakeBtn.addEventListener('click', function () {
			state.currentIndex = 0;
			state.answers = [];
			state.score = 0;
			startQuiz(state, els);
		});

		els.newBtn.addEventListener('click', function () {
			state.questions = [];
			state.currentIndex = 0;
			state.answers = [];
			state.score = 0;
			state.quizUuid = null;
			state.fileData = null;
			state.fileName = null;
			if (els.urlInput) els.urlInput.value = '';
			if (els.textInput) els.textInput.value = '';
			if (els.fileName) { els.fileName.classList.add('lqg-hidden'); els.fileName.textContent = ''; }
			if (els.dropZone) {
				var dropText = els.dropZone.querySelector('.lqg-drop-text');
				if (dropText) dropText.textContent = cfg.i18n.dropFile;
			}
			showPhase(els, 'setup');
			updateSubmitState(state, els);
		});

		els.exportQaBtn.addEventListener('click', function () { exportQA(state); });
		els.exportScoreBtn.addEventListener('click', function () { exportScore(state); });
	}

	/* ── CSV Export ── */

	function exportQA(state) {
		var rows = [['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer', 'Explanation']];
		for (var i = 0; i < state.questions.length; i++) {
			var q = state.questions[i];
			rows.push([
				q.question,
				q.options[0] || '',
				q.options[1] || '',
				q.options[2] || '',
				q.options[3] || '',
				q.correct_answer,
				q.explanation || ''
			]);
		}
		downloadCSV(rows, 'quiz-questions-and-answers.csv');
	}

	function exportScore(state) {
		var rows = [['Question', 'Correct Answer', 'Your Answer', 'Result']];
		for (var i = 0; i < state.questions.length; i++) {
			var q = state.questions[i];
			var a = state.answers[i];
			var selectedText = '';
			if (!a || a.selected === null) {
				selectedText = 'Not answered';
			} else {
				var idx = a.selected.charCodeAt(0) - 65;
				selectedText = q.options[idx] || a.selected;
			}
			var correctIdx = q.correct_answer.charCodeAt(0) - 65;
			rows.push([
				q.question,
				q.options[correctIdx] || q.correct_answer,
				selectedText,
				a && a.isCorrect ? 'Correct' : 'Incorrect'
			]);
		}
		rows.push([]);
		rows.push(['Total Score', state.score + ' / ' + state.questions.length,
			Math.round((state.score / state.questions.length) * 100) + '%', '']);
		downloadCSV(rows, 'quiz-score-report.csv');
	}

	function downloadCSV(rows, filename) {
		var csv = rows.map(function (row) {
			return row.map(function (cell) {
				var s = String(cell).replace(/"/g, '""');
				return '"' + s + '"';
			}).join(',');
		}).join('\n');

		var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	/* ── Helpers ── */

	function showPhase(els, phase) {
		els.setup.classList.toggle('lqg-hidden', phase !== 'setup');
		els.loading.classList.toggle('lqg-hidden', phase !== 'loading');
		els.quizPhase.classList.toggle('lqg-hidden', phase !== 'quiz');
		els.results.classList.toggle('lqg-hidden', phase !== 'results');
	}

	function setLoadingText(els, text, progress) {
		els.loadingText.textContent = text;
		if (els.progressFill && typeof progress === 'number') {
			els.progressFill.style.width = progress + '%';
		}
	}

	function shuffleArray(arr) {
		for (var i = arr.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var tmp = arr[i];
			arr[i] = arr[j];
			arr[j] = tmp;
		}
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

})();
