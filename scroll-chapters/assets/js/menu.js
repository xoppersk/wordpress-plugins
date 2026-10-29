/**
 * Scroll Chapters helper 6 — Menu motion (§6.5.6, §7).
 *
 * When the Menu popup opens, its `sc-rise` links rise 24px over 500ms
 * with a 50ms stagger. This script only stamps each link's stagger
 * delay (--sc-d) and flags the open popup; the motion itself is CSS
 * (helpers.css). Under reduced motion the CSS swaps the entrance for
 * a 120ms fade and this script stays out of the way.
 */
(function () {
	'use strict';

	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	function isOpen(modal) {
		var style = window.getComputedStyle(modal);
		return style.display !== 'none' && style.visibility !== 'hidden';
	}

	function evaluate(modal) {
		var links = modal.querySelectorAll('.sc-rise');
		if (links.length === 0) { return; }
		if (isOpen(modal)) {
			links.forEach(function (link, i) {
				link.style.setProperty('--sc-d', (i * 50) + 'ms');
			});
			modal.classList.add('sc-menu-open');
		} else {
			modal.classList.remove('sc-menu-open');
		}
	}

	function evaluateAll() {
		document.querySelectorAll('.elementor-popup-modal').forEach(evaluate);
	}

	function init() {
		// CSS keeps .sc-rise links in their risen-ready state only when
		// this flag is set, so a no-JS page never hides menu links.
		document.documentElement.classList.add('sc-menu-js');
		evaluateAll();
		var observer = new MutationObserver(evaluateAll);
		observer.observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['class', 'style']
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
