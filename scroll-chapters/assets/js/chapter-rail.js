/**
 * Scroll Chapters — Chapter Rail (spec §6.3 A, §7).
 *
 * Pinned mode: vertical scroll progress through the tall wrapper drives
 * the track's translateX. Fallbacks, in order of preference:
 *   - fewer than 3 chapters: server-rendered static row, JS does nothing;
 *   - prefers-reduced-motion: CSS turns the rail into a lit stack;
 *   - mobile with the pin setting off: native scroll-snap row, with the
 *     counter and progress line driven by the track's own scroll.
 * Vanilla JS only. One rAF loop per rail, paused off-screen and when
 * the tab is hidden.
 */
(function () {
	'use strict';

	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function ChapterRail(root) {
		this.root = root;
		this.wrap = root.querySelector('.sc-rail-wrap');
		this.sticky = root.querySelector('.sc-rail-sticky');
		this.track = root.querySelector('.sc-rail-track');
		this.chapters = Array.prototype.slice.call(root.querySelectorAll('.sc-chapter'));
		this.fill = root.querySelector('.sc-rail-progress-fill');
		this.counter = root.querySelector('.sc-rail-counter');
		this.prev = root.querySelector('.sc-rail-prev');
		this.next = root.querySelector('.sc-rail-next');
		this.n = this.chapters.length;
		this.active = 0;
		this.visible = false;
		this.raf = null;
		this.mode = 'pin';

		if (this.n < 3 || !this.wrap || !this.track) {
			return; // Static fallback: nothing to enhance.
		}
		if (reduced.matches) {
			return; // CSS stack, all chapters lit.
		}

		var pinMobile = root.getAttribute('data-sc-pin-mobile') === '1';
		if (window.innerWidth < 768 && !pinMobile) {
			this.mode = 'snap';
			root.classList.add('sc-rail--snap');
			this.initSnap();
			return;
		}

		this.initPin();
	}

	/* ---------------- Pinned mode ---------------- */

	ChapterRail.prototype.initPin = function () {
		var self = this;
		this.root.setAttribute('tabindex', '0');

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				self.visible = entries[0].isIntersecting;
				if (self.visible) {
					self.start();
				} else {
					self.stop();
				}
			}, { threshold: 0 });
			io.observe(this.wrap);
		} else {
			this.visible = true;
			this.start();
		}

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				self.stop();
			} else if (self.visible) {
				self.start();
			}
		});

		if (this.prev) {
			this.prev.addEventListener('click', function () { self.goTo(self.active - 1); });
		}
		if (this.next) {
			this.next.addEventListener('click', function () { self.goTo(self.active + 1); });
		}

		this.root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); self.goTo(self.active + 1); }
			if (e.key === 'ArrowLeft') { e.preventDefault(); self.goTo(self.active - 1); }
		});

		this.initDrag();
	};

	ChapterRail.prototype.start = function () {
		if (this.raf !== null) { return; }
		var self = this;
		var tick = function () {
			self.update();
			self.raf = window.requestAnimationFrame(tick);
		};
		this.raf = window.requestAnimationFrame(tick);
	};

	ChapterRail.prototype.stop = function () {
		if (this.raf !== null) {
			window.cancelAnimationFrame(this.raf);
			this.raf = null;
		}
	};

	ChapterRail.prototype.progress = function () {
		var rect = this.wrap.getBoundingClientRect();
		var scrollable = this.wrap.offsetHeight - window.innerHeight;
		if (scrollable <= 0) { return 0; }
		var p = -rect.top / scrollable;
		return Math.min(1, Math.max(0, p));
	};

	ChapterRail.prototype.update = function () {
		var p = this.progress();
		var trackW = this.track.scrollWidth;
		var viewW = this.sticky.clientWidth;
		var gutter = 48;
		var travel = Math.max(0, trackW - viewW + gutter);
		this.track.style.transform = 'translate3d(' + (-p * travel) + 'px,0,0)';
		this.setProgress(p);
		this.setActive(Math.round(p * (this.n - 1)));
	};

	ChapterRail.prototype.setProgress = function (p) {
		if (this.fill) {
			this.fill.style.width = (p * 100) + '%';
		}
	};

	ChapterRail.prototype.setActive = function (index) {
		index = Math.min(this.n - 1, Math.max(0, index));
		var changed = index !== this.active;
		this.active = index;

		for (var i = 0; i < this.n; i++) {
			this.chapters[i].classList.toggle('is-lit', i <= index);
			this.chapters[i].classList.toggle('is-active', i === index);
		}

		if (changed) {
			var chapter = this.chapters[index];
			chapter.classList.remove('sc-flicker');
			// Restart the flicker animation on the newly lit chapter.
			void chapter.offsetWidth;
			chapter.classList.add('sc-flicker');
		}

		if (this.counter) {
			this.counter.textContent = pad(index + 1) + ' / ' + pad(this.n);
		}
		if (this.prev) { this.prev.disabled = index === 0; }
		if (this.next) { this.next.disabled = index === this.n - 1; }
	};

	ChapterRail.prototype.chapterScrollTop = function (index) {
		var wrapTop = this.wrap.getBoundingClientRect().top + window.scrollY;
		var scrollable = this.wrap.offsetHeight - window.innerHeight;
		if (this.n <= 1 || scrollable <= 0) { return wrapTop; }
		return wrapTop + (index / (this.n - 1)) * scrollable;
	};

	ChapterRail.prototype.goTo = function (index) {
		index = Math.min(this.n - 1, Math.max(0, index));
		window.scrollTo({ top: this.chapterScrollTop(index), behavior: 'smooth' });
	};

	ChapterRail.prototype.initDrag = function () {
		var self = this;
		var dragging = false;
		var startX = 0;
		var startScroll = 0;

		this.track.addEventListener('pointerdown', function (e) {
			if (e.pointerType === 'mouse' && e.button !== 0) { return; }
			dragging = true;
			startX = e.clientX;
			startScroll = window.scrollY;
			self.track.setPointerCapture(e.pointerId);
		});

		this.track.addEventListener('pointermove', function (e) {
			if (!dragging) { return; }
			var scrollable = self.wrap.offsetHeight - window.innerHeight;
			var travel = Math.max(1, self.track.scrollWidth - self.sticky.clientWidth + 48);
			var ratio = scrollable / travel;
			window.scrollTo(0, startScroll - (e.clientX - startX) * ratio);
		});

		var end = function () { dragging = false; };
		this.track.addEventListener('pointerup', end);
		this.track.addEventListener('pointercancel', end);
	};

	/* ---------------- Snap mode (mobile, pin off) ---------------- */

	ChapterRail.prototype.initSnap = function () {
		var self = this;
		this.setActive(0);
		this.track.addEventListener('scroll', function () {
			var max = self.track.scrollWidth - self.track.clientWidth;
			var p = max > 0 ? self.track.scrollLeft / max : 0;
			self.setProgress(p);
			self.setActive(Math.round(p * (self.n - 1)));
		}, { passive: true });

		var scrollToChapter = function (index) {
			index = Math.min(self.n - 1, Math.max(0, index));
			var chapter = self.chapters[index];
			if (chapter) {
				self.track.scrollTo({ left: chapter.offsetLeft, behavior: 'smooth' });
			}
		};
		if (this.prev) {
			this.prev.addEventListener('click', function () { scrollToChapter(self.active - 1); });
		}
		if (this.next) {
			this.next.addEventListener('click', function () { scrollToChapter(self.active + 1); });
		}
	};

	function init() {
		document.querySelectorAll('.sc-rail').forEach(function (root) {
			new ChapterRail(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
