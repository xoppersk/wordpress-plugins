/**
 * Scroll Chapters — Layered Image Stack (spec §6.3 D, §7).
 *
 * Each layer moves by translateY = (p − 0.5) × depth × travel, where p
 * is the stage's progress through the viewport. Optional cursor tilt
 * adds up to 8px × depth, eased 0.12 per frame; pointer devices only,
 * never on mobile, where travel is also halved. Transforms only.
 * Under reduced motion nothing runs — the composition sits at p = 0.5,
 * which is the server-rendered state.
 */
(function () {
	'use strict';

	function Stack(root) {
		this.root = root;
		this.stage = root.querySelector('.sc-stack-stage');
		this.layers = Array.prototype.slice.call(root.querySelectorAll('.sc-stack-layer'));
		if (!this.stage || this.layers.length < 2) {
			return;
		}
		this.travel = parseFloat(root.getAttribute('data-sc-travel')) || 160;
		this.tiltOn = root.getAttribute('data-sc-tilt') === '1';
		this.raf = null;
		this.visible = false;
		this.tiltX = 0;   // Target tilt, −1..1.
		this.curX = 0;    // Eased tilt.
		this.state = this.layers.map(function () { return { x: 0 }; });

		var self = this;
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				self.visible = entries[0].isIntersecting;
				if (self.visible) {
					self.start();
				} else {
					self.stop();
				}
			}, { threshold: 0 });
			io.observe(this.stage);
		} else {
			this.visible = true;
			this.start();
		}

		if (this.tiltOn && window.matchMedia('(pointer: fine)').matches) {
			this.stage.addEventListener('pointermove', function (e) {
				if (window.innerWidth < 768) {
					self.tiltX = 0;
					return;
				}
				var rect = self.stage.getBoundingClientRect();
				self.tiltX = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
			});
			this.stage.addEventListener('pointerleave', function () {
				self.tiltX = 0;
			});
		}
	}

	Stack.prototype.start = function () {
		if (this.raf !== null) { return; }
		var self = this;
		var tick = function () {
			self.update();
			self.raf = window.requestAnimationFrame(tick);
		};
		this.raf = window.requestAnimationFrame(tick);
	};

	Stack.prototype.stop = function () {
		if (this.raf !== null) {
			window.cancelAnimationFrame(this.raf);
			this.raf = null;
		}
	};

	Stack.prototype.update = function () {
		var rect = this.stage.getBoundingClientRect();
		var vh = window.innerHeight;
		var p = (vh - rect.top) / (vh + rect.height);
		p = Math.min(1, Math.max(0, p));

		var mobile = window.innerWidth < 768;
		var travel = mobile ? this.travel / 2 : this.travel;

		// Ease the shared tilt factor toward its target (0.12 per frame).
		this.curX += (this.tiltX - this.curX) * 0.12;

		for (var i = 0; i < this.layers.length; i++) {
			var layer = this.layers[i];
			var depth = parseFloat(layer.getAttribute('data-depth')) || 0;
			var y = (p - 0.5) * depth * travel;
			var x = (this.tiltOn && !mobile) ? this.curX * 8 * depth : 0;
			layer.style.transform = 'translate3d(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px,0)';
		}
	};

	function init() {
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return; // Static composition at p = 0.5.
		}
		document.querySelectorAll('.sc-stack').forEach(function (root) {
			new Stack(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
