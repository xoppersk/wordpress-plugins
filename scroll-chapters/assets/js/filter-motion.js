/**
 * Scroll Chapters helper 1 — Filter motion and count badges (§6.5.1, §7).
 *
 * Watches the Taxonomy Filter's AJAX reload of its Loop Grid:
 *   - leaving cards fade out over 150ms;
 *   - a skeleton of 8 cards shows while loading;
 *   - arriving cards rise 12px over 320ms ease-tube with a 40ms stagger;
 *   - a click mid-transition cancels the queued animation and restarts
 *     from the current DOM, so rapid clicks can't break the sequence;
 *   - each chip gets a count badge (term counts localised from PHP) and
 *     a status line reads "Showing 3 of 12 · Web".
 * Under reduced motion the swap is instant (no fade, rise or skeleton).
 */
(function () {
	'use strict';

	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var counts = (window.scFilterMotion && window.scFilterMotion.counts) || {};
	var totals = (window.scFilterMotion && window.scFilterMotion.totals) || {};

	function chipLabel(chip) {
		var clone = chip.cloneNode(true);
		var badge = clone.querySelector('.sc-chip-count');
		if (badge) { badge.remove(); }
		return clone.textContent.trim();
	}

	function findGrid(filterWidget) {
		var scope = filterWidget.closest('.e-con, .elementor-section, .elementor-container') || document;
		var grid = scope.querySelector('.elementor-widget-loop-grid');
		if (!grid && scope !== document) {
			grid = document.querySelector('.elementor-widget-loop-grid');
		}
		return grid;
	}

	function addBadges(filterWidget) {
		// The "All" chip's count is the taxonomy total, keyed by the
		// taxonomy named in this widget's own data-settings.
		var widgetTaxonomy = null;
		try {
			widgetTaxonomy = JSON.parse(filterWidget.getAttribute('data-settings') || '{}').taxonomy || null;
		} catch (e) { widgetTaxonomy = null; }
		filterWidget.querySelectorAll('.e-filter-item, a, button').forEach(function (chip) {
			if (chip.querySelector('.sc-chip-count')) { return; }
			var slug = chip.getAttribute('data-filter');
			var count = null;
			if ('__all' === slug) {
				if (widgetTaxonomy && Object.prototype.hasOwnProperty.call(totals, widgetTaxonomy)) {
					count = totals[widgetTaxonomy];
				}
			} else if (slug && Object.prototype.hasOwnProperty.call(counts, slug)) {
				count = counts[slug];
			} else {
				// Fall back to matching the chip's label to a term name.
				var label = chipLabel(chip).toLowerCase();
				Object.keys(counts).forEach(function (key) {
					if (counts[key] && counts[key].name && counts[key].name.toLowerCase() === label) {
						count = counts[key].count;
					}
				});
			}
			if (count === null || typeof count === 'object') {
				if (count && typeof count.count === 'number') { count = count.count; } else { return; }
			}
			var badge = document.createElement('span');
			badge.className = 'sc-chip-count';
			badge.textContent = count;
			chip.appendChild(badge);
		});
	}

	function FilterMotion(filterWidget) {
		this.filter = filterWidget;
		this.grid = findGrid(filterWidget);
		if (!this.grid) { return; }
		this.container = this.grid.querySelector('.elementor-loop-container') || this.grid;
		this.total = this.items().length;
		this.skeleton = null;
		this.pendingLabel = null;

		addBadges(filterWidget);
		this.ensureStatus();
		this.setStatus(this.total, this.total, this.activeLabel());

		var self = this;
		filterWidget.addEventListener('click', function (e) {
			var chip = e.target.closest('.e-filter-item, a, button');
			if (!chip || !filterWidget.contains(chip)) { return; }
			self.pendingLabel = chipLabel(chip);
			self.beginLoad();
		});

		var observer = new MutationObserver(function () {
			self.onDomChange();
		});
		// Observe the grid widget element, never the loop container:
		// Elementor's refresh replaces the container node wholesale on
		// every swap, which would orphan an observer attached to it (and
		// with it the status line + enter states). The widget element
		// itself persists across swaps.
		observer.observe(this.grid, { childList: true, subtree: true });
	}

	FilterMotion.prototype.items = function () {
		// Live query from the grid element: the loop container node is
		// replaced on every refresh swap, so a cached container goes stale.
		return Array.prototype.slice.call(this.grid.querySelectorAll('.e-loop-item'));
	};

	FilterMotion.prototype.activeLabel = function () {
		var active = this.filter.querySelector('.e-filter-item[aria-pressed="true"], .e-filter-item.is-active, .e-filter-item.active');
		return active ? chipLabel(active) : 'All';
	};

	FilterMotion.prototype.ensureStatus = function () {
		if (this.statusEl) { return; }
		var el = this.grid.parentElement.querySelector('.sc-filter-status');
		if (!el) {
			el = document.createElement('p');
			el.className = 'sc-filter-status';
			el.setAttribute('aria-live', 'polite');
			this.grid.parentElement.insertBefore(el, this.grid);
		}
		this.statusEl = el;
	};

	FilterMotion.prototype.setStatus = function (shown, total, label) {
		if (!this.statusEl) { return; }
		this.statusEl.textContent = 'Showing ' + shown + ' of ' + total + (label ? ' · ' + label : '');
	};

	/** Cancel any in-flight enter animation and clear loading chrome. */
	FilterMotion.prototype.cancelPending = function () {
		this.items().forEach(function (item) {
			item.classList.remove('sc-enter');
			item.style.removeProperty('--sc-d');
		});
		if (this.skeleton) {
			this.skeleton.remove();
			this.skeleton = null;
		}
		this.grid.classList.remove('sc-filter-leaving');
	};

	FilterMotion.prototype.beginLoad = function () {
		this.cancelPending();
		if (reduced) { return; }
		this.grid.classList.add('sc-filter-leaving');

		var skeleton = document.createElement('div');
		skeleton.className = 'sc-skeleton';
		skeleton.setAttribute('aria-hidden', 'true');
		for (var i = 0; i < 8; i++) {
			var card = document.createElement('div');
			card.className = 'sc-skeleton-card';
			skeleton.appendChild(card);
		}
		this.grid.parentElement.insertBefore(skeleton, this.grid.nextSibling);
		this.skeleton = skeleton;
	};

	FilterMotion.prototype.onDomChange = function () {
		var items = this.items();
		if (items.length === 0) {
			return; // Still loading (or a genuine empty state — see below).
		}
		var self = this;
		window.requestAnimationFrame(function () {
			self.finishLoad(items);
		});
	};

	FilterMotion.prototype.finishLoad = function (items) {
		var wasLoading = !!this.skeleton || this.grid.classList.contains('sc-filter-leaving');
		this.cancelPending();

		if (!reduced && wasLoading) {
			items.forEach(function (item, i) {
				item.style.setProperty('--sc-d', (i * 40) + 'ms');
				item.classList.add('sc-enter');
			});
			window.setTimeout(function () {
				items.forEach(function (item) {
					item.classList.remove('sc-enter');
					item.style.removeProperty('--sc-d');
				});
			}, 320 + items.length * 40 + 120);
		}

		var label = this.pendingLabel || this.activeLabel();
		var total = this.total || items.length;
		this.setStatus(items.length, Math.max(total, items.length), label);
		this.pendingLabel = null;
	};

	function init() {
		document.querySelectorAll('.elementor-widget-taxonomy-filter').forEach(function (widget) {
			new FilterMotion(widget);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
