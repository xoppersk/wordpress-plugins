/**
 * Scroll Chapters helper 5 — Header hide on scroll (§6.5.5, §7).
 *
 * Adds `is-hidden` to .klo-header after 200px of scrolling down;
 * removes it on any scroll up. The slide itself (translateY −120%,
 * 240ms ease-tube) lives in helpers.css. Under reduced motion the
 * header always stays visible — this script does nothing at all.
 */
(function () {
	'use strict';

	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	function init() {
		var header = document.querySelector('.klo-header');
		if (!header) { return; }

		var lastY = window.scrollY;
		var ticking = false;

		function update() {
			ticking = false;
			var y = window.scrollY;
			if (y > 200 && y > lastY) {
				header.classList.add('is-hidden');
			} else if (y < lastY || y <= 200) {
				header.classList.remove('is-hidden');
			}
			lastY = y;
		}

		window.addEventListener('scroll', function () {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
