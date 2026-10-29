/**
 * Scroll Chapters — Typewriter (spec §6.3 B, §7).
 *
 * Types the phrase, holds, deletes back to the prefix, waits the gap,
 * types the next. Starts when the widget is 50% in view; pauses while
 * the tab is hidden. One phrase types once and holds — never deletes.
 * Under reduced motion nothing runs: the server-rendered first phrase
 * stays and the caret is hidden by CSS.
 */
(function () {
	'use strict';

	function Typewriter(el) {
		var data;
		try {
			data = JSON.parse(el.getAttribute('data-sc-tw') || '{}');
		} catch (e) {
			return;
		}
		this.el = el;
		this.typed = el.querySelector('.sc-tw-typed');
		this.phrases = data.phrases || [];
		this.typeSpeed = data.type || 55;
		this.deleteSpeed = data.delete || 30;
		this.hold = data.hold || 1800;
		this.gap = data.gap || 300;
		this.loop = !!data.loop;
		this.timer = null;
		this.started = false;

		if (!this.typed || this.phrases.length === 0) {
			return;
		}
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}

		var self = this;
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting && !self.started) {
					self.started = true;
					io.disconnect();
					self.run(0, 0);
				}
			}, { threshold: 0.5 });
			io.observe(el);
		} else {
			this.started = true;
			this.run(0, 0);
		}

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				self.pause();
			} else if (self.started && self.pausedState) {
				var st = self.pausedState;
				self.pausedState = null;
				self.run(st.phrase, st.len, st.phase);
			}
		});
	}

	Typewriter.prototype.pause = function () {
		if (this.timer !== null) {
			window.clearTimeout(this.timer);
			this.timer = null;
			this.pausedState = { phrase: this.currentPhrase, len: this.currentLen, phase: this.phase };
		}
	};

	/**
	 * The type/delete state machine.
	 *
	 * @param {number} phraseIndex Which phrase.
	 * @param {number} len         Characters currently shown.
	 * @param {string} phase       typing | holding | deleting | gap.
	 */
	Typewriter.prototype.run = function (phraseIndex, len, phase) {
		var self = this;
		phase = phase || 'typing';
		this.currentPhrase = phraseIndex;
		this.currentLen = len;
		this.phase = phase;

		var phrase = this.phrases[phraseIndex];
		var single = this.phrases.length === 1;
		var isLast = phraseIndex === this.phrases.length - 1;

		if (phase === 'typing') {
			if (len <= phrase.length) {
				this.typed.textContent = phrase.slice(0, len);
				this.timer = window.setTimeout(function () {
					self.run(phraseIndex, len + 1, 'typing');
				}, this.typeSpeed);
			} else {
				// Fully typed.
				if (single || (isLast && !this.loop)) {
					return; // Hold forever — no delete (spec edge state).
				}
				this.timer = window.setTimeout(function () {
					self.run(phraseIndex, phrase.length, 'deleting');
				}, this.hold);
			}
			return;
		}

		if (phase === 'deleting') {
			if (len > 0) {
				this.typed.textContent = phrase.slice(0, len - 1);
				this.currentLen = len - 1;
				this.timer = window.setTimeout(function () {
					self.run(phraseIndex, len - 1, 'deleting');
				}, this.deleteSpeed);
			} else {
				this.timer = window.setTimeout(function () {
					var next = phraseIndex + 1;
					if (next >= self.phrases.length) {
						next = 0; // Only reached when looping.
					}
					self.run(next, 0, 'typing');
				}, this.gap);
			}
		}
	};

	function init() {
		document.querySelectorAll('.sc-typewriter').forEach(function (el) {
			new Typewriter(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
