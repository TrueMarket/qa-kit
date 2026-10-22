/* QA Kit admin page: copy buttons and audit filter. */
(function () {
	'use strict';

	function copy(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.cssText = 'position:fixed;left:-9999px;top:0';
		document.body.appendChild(ta);
		ta.select();
		var ok = document.execCommand('copy');
		ta.remove();
		return ok ? Promise.resolve() : Promise.reject();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-copy]');
		if (!btn) return;
		var source = document.querySelector(btn.getAttribute('data-copy'));
		if (!source) return;
		var label = btn.textContent;
		copy(source.value).then(function () {
			btn.textContent = 'Copied!';
		}, function () {
			btn.textContent = 'Copy failed';
		}).then(function () {
			setTimeout(function () { btn.textContent = label; }, 1800);
		});
	});

	var toggle = document.getElementById('qa-kit-show-used');
	var table = document.querySelector('.qa-kit-audit');
	if (toggle && table) {
		var apply = function () { table.classList.toggle('hide-used', !toggle.checked); };
		toggle.addEventListener('change', apply);
		apply();
	}
})();
