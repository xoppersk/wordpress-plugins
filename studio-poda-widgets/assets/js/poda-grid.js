/* Studio Poda — Work Grid filtering + MW motion.
 *
 * Port of the design package's proven two-layer pattern
 * (work.html + MOTION-LOG-MW.md): the base layer owns WHICH cards
 * show (it toggles card.hidden from each card's data-cats); the
 * motion layer only animates the change —
 *   outgoing cards fade/shrink out over 150ms (.poda-mw-exiting
 *   keeps them laid out while [hidden] would drop them), incoming
 *   cards wait invisible (.poda-mw-pre) until the exit lands, then
 *   rise + fade in with a 40ms stagger capped at 320ms
 *   (.poda-mw-in); cards that stay visible dip softly
 *   (.poda-mw-in-soft). Only transform/opacity are animated.
 * Rapid clicks: every click first cancels all pending timers and
 * strips every MW class, then rebuilds from the hidden states the
 * base layer has just set — the final state always matches the
 * last chip. prefers-reduced-motion: no classes, no timers;
 * filtering is instant, exactly as the base layer leaves it.
 */
(function () {
	'use strict';

	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
	var EXIT_MS = 150, STAGGER_MS = 40, STAGGER_CAP = 320, IN_MS = 340;

	function initGrid(root) {
		var chips = Array.prototype.slice.call(root.querySelectorAll('.poda-grid__chip'));
		var cards = Array.prototype.slice.call(root.querySelectorAll('.poda-grid__card'));
		if (!chips.length || !cards.length) return;

		var timers = [];
		var prevVisible = cards.map(function (c) { return !c.hidden; });

		function later(fn, ms) { timers.push(window.setTimeout(fn, ms)); }
		function clearTimers() {
			timers.forEach(function (t) { window.clearTimeout(t); });
			timers = [];
		}
		function strip(card) {
			card.classList.remove('poda-mw-pre', 'poda-mw-in', 'poda-mw-in-soft', 'poda-mw-exiting');
			card.style.removeProperty('animation-delay');
		}

		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				/* — base layer: selection + visibility (source of truth) — */
				chips.forEach(function (c) {
					c.classList.remove('is-selected');
					c.setAttribute('aria-pressed', 'false');
				});
				chip.classList.add('is-selected');
				chip.setAttribute('aria-pressed', 'true');
				var f = chip.getAttribute('data-filter');
				cards.forEach(function (card) {
					var cats = (card.getAttribute('data-cats') || '').split(' ');
					card.hidden = !(f === 'all' || cats.indexOf(f) !== -1);
				});

				/* — motion layer: animate the change just made — */
				if (reduce.matches) return;
				clearTimers();
				cards.forEach(strip);
				var incoming = [], outgoing = [], staying = [];
				cards.forEach(function (card, i) {
					var is = !card.hidden;
					if (is && !prevVisible[i]) incoming.push(card);
					else if (!is && prevVisible[i]) outgoing.push(card);
					else if (is) staying.push(card);
					prevVisible[i] = is;
				});
				outgoing.forEach(function (card) { card.classList.add('poda-mw-exiting'); });
				incoming.forEach(function (card) { card.classList.add('poda-mw-pre'); });
				var wait = outgoing.length ? EXIT_MS + 10 : 0;
				later(function () {
					outgoing.forEach(function (card) { card.classList.remove('poda-mw-exiting'); });
					incoming.forEach(function (card, i) {
						card.classList.remove('poda-mw-pre');
						card.style.setProperty('animation-delay',
							Math.min(i * STAGGER_MS, STAGGER_CAP) + 'ms');
						card.classList.add('poda-mw-in');
					});
					staying.forEach(function (card) { card.classList.add('poda-mw-in-soft'); });
					later(function () { cards.forEach(strip); }, STAGGER_CAP + IN_MS + 80);
				}, wait);
			});
		});
	}

	function boot() {
		document.querySelectorAll('.poda-grid').forEach(initGrid);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
