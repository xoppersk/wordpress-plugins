/**
 * Scroll Chapters helper 2 — Form error summary (§6.5.2, §7).
 *
 * When an Elementor Pro form submit fails, build the boxed summary at
 * the top of the form:
 *   "N things to fix before this can go to the bench"
 * with one link per invalid field. A link focuses its field; focus
 * moves to the summary itself when it appears. The field-level
 * messages are the form's own — the summary reuses them where it can
 * find them, and falls back to the field label.
 */
(function () {
	'use strict';

	function fieldLabel(field) {
		var group = field.closest('.elementor-field-group');
		if (group) {
			var label = group.querySelector('.elementor-field-label');
			if (label) { return label.textContent.trim(); }
		}
		return field.getAttribute('placeholder') || field.getAttribute('name') || 'Field';
	}

	function fieldErrorText(field) {
		var group = field.closest('.elementor-field-group');
		if (group) {
			var candidates = group.querySelectorAll('.elementor-field-error, .elementor-error-message, .elementor-message, [data-error-message]');
			for (var i = 0; i < candidates.length; i++) {
				var text = candidates[i].textContent.trim();
				if (text) { return text; }
			}
		}
		return fieldLabel(field);
	}

	function invalidFields(form) {
		var found = form.querySelectorAll(
			'input.elementor-error, select.elementor-error, textarea.elementor-error,' +
			'input[aria-invalid="true"], select[aria-invalid="true"], textarea[aria-invalid="true"]'
		);
		var seen = [];
		Array.prototype.forEach.call(found, function (field) {
			if (seen.indexOf(field) === -1 && field.type !== 'hidden') {
				seen.push(field);
			}
		});
		return seen;
	}

	function buildSummary(form) {
		var fields = invalidFields(form);
		var existing = form.querySelector('.sc-form-summary');
		if (fields.length === 0) {
			if (existing) { existing.remove(); }
			return;
		}
		if (existing) {
			/* Since 1.0.7: rebuilding an identical box re-triggers this
			   helper's own MutationObserver (the insert/remove are mutations
			   inside the watched form), which looped the rebuild every
			   ~60 ms and stole focus back to the box — a summary link could
			   never keep focus on its field. If the existing box already
			   lists the same errors, leave it (and focus) alone. */
			var oldLinks = existing.querySelectorAll('a');
			var same = oldLinks.length === fields.length;
			for (var i = 0; same && i < fields.length; i++) {
				if (oldLinks[i].textContent !== fieldErrorText(fields[i])) { same = false; }
			}
			if (same) { return; }
			existing.remove();
		}

		var box = document.createElement('div');
		box.className = 'sc-form-summary';
		box.setAttribute('tabindex', '-1');
		box.setAttribute('role', 'alert');

		var heading = document.createElement('h3');
		heading.className = 'sc-form-summary-title';
		heading.textContent = fields.length === 1
			? '1 thing to fix before this can go to the bench'
			: fields.length + ' things to fix before this can go to the bench';
		box.appendChild(heading);

		var list = document.createElement('ul');
		fields.forEach(function (field) {
			var li = document.createElement('li');
			var link = document.createElement('a');
			link.href = '#';
			link.textContent = fieldErrorText(field);
			link.addEventListener('click', function (e) {
				e.preventDefault();
				field.focus();
				field.scrollIntoView({ block: 'center', behavior: 'smooth' });
			});
			li.appendChild(link);
			list.appendChild(li);
		});
		box.appendChild(list);

		form.insertBefore(box, form.firstChild);
		window.setTimeout(function () { box.focus(); }, 0);
	}

	function watch(form) {
		var debounce = null;
		var observer = new MutationObserver(function () {
			var hasErrorUI = form.querySelector('.elementor-message-danger') ||
				invalidFields(form).length > 0;
			if (!hasErrorUI) { return; }
			window.clearTimeout(debounce);
			debounce = window.setTimeout(function () { buildSummary(form); }, 60);
		});
		observer.observe(form, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['class', 'aria-invalid']
		});

		form.addEventListener('submit', function () {
			var existing = form.querySelector('.sc-form-summary');
			if (existing) { existing.remove(); }
		});
	}

	function init() {
		document.querySelectorAll('.elementor-form').forEach(watch);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
