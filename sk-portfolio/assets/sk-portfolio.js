/* SK Portfolio FX — interactions. No dependencies. */
(function () {
	'use strict';
	var d = document;
	var cfg = window.SKP || {};
	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	function inEditor() {
		return d.body.classList.contains('elementor-editor-active') ||
			/[?&]elementor-preview=/.test(location.search) ||
			!!(window.elementorFrontend && elementorFrontend.isEditMode && elementorFrontend.isEditMode());
	}

	/* ---------- live local time: any element with [data-sk-clock] ---------- */
	function initClock() {
		var els = d.querySelectorAll('[data-sk-clock]');
		if (!els.length) return;
		var fmt;
		try { fmt = new Intl.DateTimeFormat('en-US', { timeZone: cfg.timezone || 'America/New_York', hour: 'numeric', minute: '2-digit' }); } catch (e) { return; }
		function tick() { var t = fmt.format(new Date()); els.forEach(function (el) { el.textContent = t; }); }
		tick(); setInterval(tick, 15000);
	}

	/* ---------- scroll-reactive marquee: [data-sk-marquee] > .sk-marquee-track > span ---------- */
	var scrollDir = -1, boost = 0, lastY = window.scrollY;
	window.addEventListener('scroll', function () {
		var y = window.scrollY, dy = y - lastY; lastY = y;
		if (dy) { scrollDir = dy > 0 ? -1 : 1; boost = Math.min(14, boost + Math.abs(dy) * 0.08); }
	}, { passive: true });

	function initMarquees() {
		var list = [];
		d.querySelectorAll('[data-sk-marquee]').forEach(function (el) {
			var track = el.querySelector('.sk-marquee-track');
			if (!track || track.dataset.skReady) return;
			var first = track.firstElementChild;
			if (!first) return;
			var copies = Math.max(2, Math.ceil(el.clientWidth / Math.max(1, first.offsetWidth)) + 1);
			for (var i = 1; i < copies; i++) track.appendChild(first.cloneNode(true));
			var set = track.innerHTML; track.innerHTML = set + set;
			track.dataset.skReady = '1';
			list.push({ track: track, x: 0, reverse: el.hasAttribute('data-reverse'), speed: parseFloat(el.dataset.speed || '0.7') });
		});
		if (!list.length || reduce) return;
		(function loop() {
			list.forEach(function (m) {
				var w = m.track.scrollWidth / 2;
				m.x += (m.reverse ? -scrollDir : scrollDir) * (m.speed + boost);
				if (m.x <= -w) m.x += w;
				if (m.x > 0) m.x -= w;
				m.track.style.transform = 'translate3d(' + m.x + 'px,0,0)';
			});
			boost *= 0.93;
			requestAnimationFrame(loop);
		})();
	}

	/* ---------- spinning globe: svg[data-sk-globe] with three <ellipse> meridians ---------- */
	function initGlobes() {
		var globes = d.querySelectorAll('[data-sk-globe]');
		if (!globes.length || reduce) return;
		var t0 = performance.now();
		(function loop(t) {
			var a = ((t - t0) / 1000) * 1.4;
			globes.forEach(function (g) {
				g.querySelectorAll('ellipse').forEach(function (e, k) {
					e.setAttribute('rx', Math.abs(10 * Math.cos(a + k * Math.PI / 3)).toFixed(2));
				});
			});
			requestAnimationFrame(loop);
		})(t0);
	}

	/* ---------- magnetic buttons: .sk-magnetic (widget wrapper) or [data-sk-magnetic] ---------- */
	function initMagnetic() {
		if (reduce || !finePointer) return;
		d.querySelectorAll('.sk-magnetic .elementor-button, [data-sk-magnetic], .sk-magnetic-links a').forEach(function (el) {
			el.addEventListener('mousemove', function (e) {
				var r = el.getBoundingClientRect();
				var x = (e.clientX - r.left - r.width / 2) * 0.28, y = (e.clientY - r.top - r.height / 2) * 0.28;
				el.style.transition = 'transform .12s ease-out';
				el.style.transform = 'translate(' + x + 'px,' + y + 'px)';
			});
			el.addEventListener('mouseleave', function () {
				el.style.transition = 'transform .6s cubic-bezier(.2,1.6,.4,1)';
				el.style.transform = 'translate(0,0)';
			});
		});
	}

	/* ---------- work lists: preview, dimming, filters, list/grid toggle ---------- */
	var preview, previewImg, previewLabel, follower, px = 0, py = 0, tx = 0, ty = 0, fx = 0, fy = 0, running = false;
	function ensureFloating() {
		if (preview) return;
		preview = d.createElement('div'); preview.className = 'sk-preview';
		previewImg = d.createElement('img'); previewImg.alt = '';
		previewLabel = d.createElement('span');
		preview.appendChild(previewImg); preview.appendChild(previewLabel);
		follower = d.createElement('div'); follower.className = 'sk-follower'; follower.textContent = 'View';
		d.body.appendChild(preview); d.body.appendChild(follower);
		window.addEventListener('mousemove', function (e) { tx = e.clientX; ty = e.clientY; }, { passive: true });
	}
	function animateFloating() {
		if (running) return; running = true;
		(function loop() {
			var k = reduce ? 1 : 0.16;
			px += (tx + 28 - px) * k; py += (ty - 130 - py) * k;
			fx += (tx - fx) * (reduce ? 1 : 0.35); fy += (ty - fy) * (reduce ? 1 : 0.35);
			var maxX = window.innerWidth - 360;
			preview.style.transform = 'translate3d(' + Math.min(px, maxX) + 'px,' + py + 'px,0)';
			follower.style.transform = 'translate3d(' + fx + 'px,' + fy + 'px,0)';
			requestAnimationFrame(loop);
		})();
	}

	function initWork() {
		var lists = d.querySelectorAll('.sk-work');
		if (!lists.length) return;
		if (finePointer) { ensureFloating(); animateFloating(); }
		lists.forEach(function (list) {
			var rows = list.querySelectorAll('.sk-work-row');
			rows.forEach(function (row) {
				row.addEventListener('mouseenter', function () {
					if (!preview) return;
					if (list.getAttribute('data-view') === 'grid') { follower.classList.add('is-on'); return; }
					var img = row.querySelector('.sk-work-img');
					var src = img && img.getAttribute('src');
					previewImg.style.display = src ? 'block' : 'none';
					if (src) previewImg.src = src;
					previewLabel.textContent = src ? '' : (row.querySelector('.sk-work-client') || {}).textContent || '';
					preview.classList.add('is-on');
					list.classList.add('is-hovering');
					rows.forEach(function (r) { r.classList.toggle('is-hover', r === row); });
				});
				row.addEventListener('mouseleave', function () {
					if (!preview) return;
					preview.classList.remove('is-on'); follower.classList.remove('is-on');
					list.classList.remove('is-hovering'); row.classList.remove('is-hover');
				});
			});

			var wrap = list.closest('.sk-work-wrap');
			if (!wrap) return;
			wrap.querySelectorAll('.sk-filters button').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var f = btn.getAttribute('data-filter');
					wrap.querySelectorAll('.sk-filters button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
					rows.forEach(function (r) { r.classList.toggle('is-filtered', f !== 'all' && r.getAttribute('data-type') !== f); });
				});
			});
			wrap.querySelectorAll('.sk-views button').forEach(function (btn) {
				btn.addEventListener('click', function () {
					list.setAttribute('data-view', btn.getAttribute('data-view'));
					wrap.querySelectorAll('.sk-views button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
					if (preview) { preview.classList.remove('is-on'); follower.classList.remove('is-on'); }
				});
			});
		});
	}

	/* ---------- page-transition curtain ---------- */
	function initCurtain() {
		var c = d.querySelector('.sk-curtain');
		if (!c || cfg.curtain === false || reduce || inEditor()) { if (c) c.remove(); return; }
		var label = c.querySelector('.sk-curtain-label');
		var KEY = 'skCurtain';
		var incoming = null;
		try { incoming = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}
		if (incoming !== null) {
			label.textContent = incoming;
			c.classList.add('is-covered');
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					c.classList.remove('is-covered'); c.classList.add('is-leave');
					setTimeout(function () { c.classList.remove('is-leave'); }, 900);
				});
			});
		}
		d.addEventListener('click', function (e) {
			var a = e.target.closest('a[href]');
			if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
			if (a.target && a.target !== '_self') return;
			if (a.hasAttribute('download') || a.hasAttribute('data-no-curtain')) return;
			var url;
			try { url = new URL(a.href, location.href); } catch (err) { return; }
			if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
			if (url.pathname === location.pathname && url.hash) return;
			if (/\/wp-admin|\/wp-login|\.(pdf|zip|jpe?g|png|webp)$/i.test(url.pathname)) return;
			e.preventDefault();
			var text = a.getAttribute('data-sk-label') || (a.querySelector('.sk-work-client') || a).textContent.replace(/[\u2192\u2197]/g, '').trim().split('\n')[0].trim();
			label.textContent = text.length > 28 ? '' : text;
			try { sessionStorage.setItem(KEY, label.textContent); } catch (err) {}
			c.classList.add('is-cover');
			setTimeout(function () { location.href = url.href; }, 700);
		});
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) c.classList.remove('is-cover', 'is-covered', 'is-leave');
		});
	}

	function init() {
		initClock(); initMarquees(); initGlobes(); initMagnetic(); initWork(); initCurtain();
	}
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', init); else init();
})();
