/**
 * Scroll Chapters — Marquee Band (spec §6.3 C, §7).
 *
 * The server renders the item list twice (second set aria-hidden).
 * This script measures one set, clones it until the track covers at
 * least twice the viewport width plus one set (short lists), then
 * scrolls at a constant px/s with a rAF loop, wrapping by exactly one
 * set width. Pauses on hover, on keyboard focus inside the band, when
 * off-screen and when the tab is hidden. Under reduced motion nothing
 * runs — CSS wraps the list into a static list and hides the copies.
 */
(function () {
	'use strict';

	function Marquee(band) {
		this.band = band;
		this.track = band.querySelector('.sc-marquee-track');
		this.viewport = band.querySelector('.sc-marquee-viewport');
		if (!this.track || !this.viewport) {
			return;
		}

		this.speed = parseFloat(band.getAttribute('data-sc-speed')) || 70;
		this.direction = band.getAttribute('data-sc-direction') === 'right' ? 1 : -1;

		// The server printed the group twice; half the items are one set.
		var items = this.track.querySelectorAll('.sc-marquee-item');
		this.groupSize = Math.max(1, Math.floor(items.length / 2));
		this.group = Array.prototype.slice.call(this.track.children, 0, this.groupSize);

		this.setWidth = 0;
		this.pos = 0;
		this.last = null;
		this.raf = null;
		this.visible = false;
		this.hover = false;
		this.focus = false;

		this.build();
		this.bind();
		var self = this;
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				self.visible = entries[0].isIntersecting;
				self.sync();
			}, { threshold: 0 });
			io.observe(band);
		} else {
			this.visible = true;
			this.sync();
		}
	}

	/**
	 * Clone the group until the track is at least
	 * 2 × viewport + 1 set wide, then measure one set.
	 */
	Marquee.prototype.build = function () {
		var needed = this.viewport.clientWidth * 2;
		var guard = 0;
		while (this.track.scrollWidth < needed + this.oneSetWidth() && guard < 40) {
			for (var i = 0; i < this.group.length; i++) {
				var clone = this.group[i].cloneNode(true);
				clone.classList.add('sc-marquee-dup');
				clone.setAttribute('aria-hidden', 'true');
				this.track.appendChild(clone);
			}
			guard++;
		}
		this.setWidth = this.oneSetWidth();
	};

	Marquee.prototype.oneSetWidth = function () {
		var w = 0;
		for (var i = 0; i < this.group.length; i++) {
			w += this.group[i].offsetWidth;
		}
		return w;
	};

	Marquee.prototype.bind = function () {
		var self = this;
		this.band.addEventListener('pointerenter', function () { self.hover = true; });
		this.band.addEventListener('pointerleave', function () { self.hover = false; });
		this.band.addEventListener('focusin', function () { self.focus = true; });
		this.band.addEventListener('focusout', function () { self.focus = false; });
		document.addEventListener('visibilitychange', function () { self.sync(); });

		var resizeTimer = null;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				self.build();
			}, 200);
		});
	};

	Marquee.prototype.paused = function () {
		return !this.visible || this.hover || this.focus || document.hidden;
	};

	Marquee.prototype.sync = function () {
		if (this.paused()) {
			if (this.raf !== null) {
				window.cancelAnimationFrame(this.raf);
				this.raf = null;
				this.last = null;
			}
			return;
		}
		if (this.raf === null) {
			var self = this;
			var tick = function (t) {
				self.step(t);
				if (!self.paused()) {
					self.raf = window.requestAnimationFrame(tick);
				} else {
					self.raf = null;
					self.last = null;
				}
			};
			this.raf = window.requestAnimationFrame(tick);
		}
	};

	Marquee.prototype.step = function (t) {
		if (this.last === null) {
			this.last = t;
			return;
		}
		var dt = Math.min(0.1, (t - this.last) / 1000);
		this.last = t;
		if (this.setWidth <= 0) {
			return;
		}
		this.pos += this.speed * dt;
		var offset = this.pos % this.setWidth;
		var x = this.direction < 0 ? -offset : offset - this.setWidth;
		this.track.style.transform = 'translate3d(' + x + 'px,0,0)';
		// Hover/focus can begin between frames; sync() is event-driven,
		// so also re-check inside the loop.
		if (this.hover || this.focus) {
			this.sync();
		}
	};

	function init() {
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return; // Static wrapped list (CSS).
		}
		document.querySelectorAll('.sc-marquee').forEach(function (band) {
			new Marquee(band);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
