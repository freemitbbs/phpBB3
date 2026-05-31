(function () {
	'use strict';

	function cleanTag(value, maxLength) {
		var tag = String(value || '').trim().replace(/^[#＃]+/u, '');
		tag = tag.replace(/[^\p{L}\p{N}_-]+/gu, '').replace(/^[_-]+|[_-]+$/g, '');
		if (tag.length > maxLength) {
			tag = tag.substring(0, maxLength);
		}
		return tag;
	}

	function splitTags(value) {
		return String(value || '').split(/[\s,，、;；]+/u);
	}

	function setupEditor(editor) {
		var input = editor.querySelector('.posttags-input');
		var hidden = editor.querySelector('input[type="hidden"][name="posttags_tags"]');
		var list = editor.querySelector('.posttags-token-list');
		if (!input || !hidden || !list) {
			return;
		}

		var maxTags = parseInt(editor.getAttribute('data-max-tags'), 10) || 20;
		var maxLength = parseInt(editor.getAttribute('data-max-length'), 10) || 50;
		var tags = [];
		var isComposing = false;
		var syncingHidden = false;

		function syncHidden() {
			hidden.value = tags.join(',');
			syncingHidden = true;
			hidden.dispatchEvent(new Event('change', { bubbles: true }));
			syncingHidden = false;
		}

		function hasTag(tag) {
			var clean = tag.toLocaleLowerCase();
			return tags.some(function (existing) {
				return existing.toLocaleLowerCase() === clean;
			});
		}

		function addTag(value) {
			var tag = cleanTag(value, maxLength);
			if (!tag || hasTag(tag) || tags.length >= maxTags) {
				return false;
			}
			tags.push(tag);
			render();
			return true;
		}

		function removeTag(index) {
			tags.splice(index, 1);
			render();
			input.focus();
		}

		function render() {
			while (list.firstChild) {
				list.removeChild(list.firstChild);
			}

			tags.forEach(function (tag, index) {
				var token = document.createElement('span');
				token.className = 'posttags-token';

				var label = document.createElement('span');
				label.textContent = tag;
				token.appendChild(label);

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'posttags-token-remove';
				button.setAttribute('aria-label', (window.phpbb && window.phpbb.lang && window.phpbb.lang.POSTTAGS_REMOVE) || 'Remove tag');
				button.textContent = '×';
				button.addEventListener('click', function () {
					removeTag(index);
				});
				token.appendChild(button);

				list.appendChild(token);
			});

			syncHidden();
		}

		function commitInput(keepLast) {
			var value = input.value;
			var parts = splitTags(value);
			var trailingSeparator = /[\s,，、;；]$/u.test(value);
			var last = '';

			if (keepLast && !trailingSeparator && parts.length > 1) {
				last = parts.pop();
			}

			parts.forEach(addTag);
			input.value = last;
		}

		splitTags(hidden.value).forEach(addTag);
		render();

		hidden.addEventListener('change', function () {
			if (syncingHidden || hidden.value === tags.join(',')) {
				return;
			}

			tags = [];
			splitTags(hidden.value).forEach(addTag);
			render();
		});

		input.addEventListener('compositionstart', function () {
			isComposing = true;
		});

		input.addEventListener('compositionend', function () {
			isComposing = false;
			if (/[\s,，、;；]/u.test(input.value)) {
				commitInput(true);
			}
		});

		input.addEventListener('keydown', function (event) {
			if (isComposing || event.isComposing) {
				return;
			}

			var isSeparator = event.key === ' ' || event.key === 'Enter' || event.key === 'Tab' || event.key === ',';
			if (isSeparator && input.value.trim() !== '') {
				event.preventDefault();
				commitInput(false);
			} else if (event.key === 'Backspace' && input.value === '' && tags.length > 0) {
				removeTag(tags.length - 1);
			}
		});

		input.addEventListener('input', function () {
			if (isComposing) {
				return;
			}

			if (/[\s,，、;；]/u.test(input.value)) {
				commitInput(true);
			}
		});

		input.addEventListener('paste', function () {
			window.setTimeout(function () {
				commitInput(true);
			}, 0);
		});

		var form = editor.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				if (input.value.trim() !== '') {
					commitInput(false);
				}
			});
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.posttags-editor'), setupEditor);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
