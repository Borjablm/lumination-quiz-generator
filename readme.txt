=== Lumination AI Quiz Generator ===
Contributors: luminationteam
Tags: quiz, ai, education, assessment, generator
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate interactive quizzes from PDFs, URLs, or pasted text. Configurable question count and difficulty, instant scoring, and export to CSV.

== Description ==

Lumination AI Quiz Generator creates interactive quizzes from any content source. Paste a URL, upload a PDF, or type text — and get a scored quiz with configurable difficulty and question count.

**Requires Lumination Core (free)** — install it first.

= Features =

* **Multiple input modes** — URL, text paste, or PDF upload (auto-tabbed interface)
* **Configurable quizzes** — set question count (3–30) and difficulty (easy, medium, hard)
* **Timer support** — optional countdown timer (5, 10, or 15 minutes)
* **Instant scoring** — immediate feedback with explanations per question
* **Hints** — optional hints for each question
* **Export** — download Q&A or score reports as CSV
* **Retake** — retake the same quiz or generate a new one

= Shortcode =

Place `[lumination_quiz_generator]` on any page or post.

= Getting Started =

1. Install and activate **Lumination Core**.
2. Go to **Tools → Lumination → API Configuration** and enter your API key and base URL.
3. Install and activate **Lumination AI Quiz Generator**.
4. Add `[lumination_quiz_generator]` to any page.

== Installation ==

1. Install **Lumination Core** first and configure your API credentials.
2. Upload the `lumination-quiz-generator` folder to `/wp-content/plugins/`.
3. Activate the plugin via the Plugins screen.
4. Add `[lumination_quiz_generator]` to any page or post.

== Frequently Asked Questions ==

= Do I need Lumination Core? =

Yes. The quiz generator requires Lumination Core for API access and analytics. If Core is not active, the plugin shows an admin notice and the shortcode outputs nothing.

= What file types are supported? =

PDF files up to 10 MB.

= Where is my usage data? =

In **Tools → Lumination → Usage Analytics** (provided by Lumination Core).

== Changelog ==

= 1.0.0 =
* Initial release.
* Shortcode `[lumination_quiz_generator]`.
* URL, text, and PDF input modes.
* Configurable question count and difficulty.
* Timer support with countdown display.
* Instant scoring with per-question feedback.
* CSV export for Q&A and score reports.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
