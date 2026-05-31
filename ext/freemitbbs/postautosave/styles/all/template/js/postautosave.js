(function () {
	'use strict';

	var STORAGE_PREFIX = 'freemitbbs.postautosave.v1.';
	var MAX_DRAFT_AGE_MS = 14 * 24 * 60 * 60 * 1000;
	var SAVE_DELAY_MS = 300;
	var FIELD_NAMES = ['subject', 'message', 'posttags_tags'];
	var TEXT = {
		available: '发现上次未提交的本地草稿。',
		restored: '已恢复上次未提交的本地草稿。提交成功后可以丢弃本地草稿。',
		restore: '恢复',
		discard: '丢弃本地草稿',
		savedAt: '保存于 ',
		attachments: '附件需要重新选择。'
	};

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	function storageWorks() {
		try {
			var key = STORAGE_PREFIX + 'test';
			window.localStorage.setItem(key, '1');
			window.localStorage.removeItem(key);
			return true;
		} catch (e) {
			return false;
		}
	}

	function getField(form, name) {
		if (!form || !form.elements) {
			return null;
		}

		var field = form.elements[name];
		return field && typeof field.value === 'string' ? field : null;
	}

	function cleanActionKey(form) {
		var action = form.getAttribute('action') || window.location.href;
		var formId = form.getAttribute('id') || 'postform';

		try {
			var url = new URL(action, window.location.href);
			var pairs = [];
			url.hash = '';
			url.searchParams.delete('sid');

			url.searchParams.forEach(function (value, key) {
				pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
			});
			pairs.sort();

			return formId + ':' + url.pathname + '?' + pairs.join('&');
		} catch (e) {
			return formId + ':' + String(action).replace(/([?&])sid=[^&]*/g, '$1sid=');
		}
	}

	function storageKey(form) {
		return STORAGE_PREFIX + cleanActionKey(form);
	}

	function collectFields(form) {
		var fields = {};

		FIELD_NAMES.forEach(function (name) {
			var field = getField(form, name);
			if (field) {
				fields[name] = field.value;
			}
		});

		return fields;
	}

	function hasContent(fields) {
		return Object.keys(fields).some(function (name) {
			return String(fields[name] || '').trim() !== '';
		});
	}

	function fieldsEqual(left, right) {
		return FIELD_NAMES.every(function (name) {
			return String((left && left[name]) || '') === String((right && right[name]) || '');
		});
	}

	function loadDraft(key) {
		try {
			var raw = window.localStorage.getItem(key);
			if (!raw) {
				return null;
			}

			var draft = JSON.parse(raw);
			if (!draft || !draft.fields || !draft.savedAt || Date.now() - draft.savedAt > MAX_DRAFT_AGE_MS) {
				window.localStorage.removeItem(key);
				return null;
			}

			return draft;
		} catch (e) {
			return null;
		}
	}

	function saveDraft(form, key, submitted) {
		var draft = {
			version: 1,
			savedAt: Date.now(),
			fields: collectFields(form)
		};

		if (submitted) {
			draft.submittedAt = Date.now();
		}

		try {
			if (hasContent(draft.fields)) {
				window.localStorage.setItem(key, JSON.stringify(draft));
			} else {
				window.localStorage.removeItem(key);
			}
		} catch (e) {
			// Ignore storage quota or private browsing failures.
		}
	}

	function fireEvent(element, type) {
		var event;

		if (typeof Event === 'function') {
			event = new Event(type, { bubbles: true });
		} else {
			event = document.createEvent('Event');
			event.initEvent(type, true, false);
		}

		element.dispatchEvent(event);
	}

	function applyFields(form, fields) {
		FIELD_NAMES.forEach(function (name) {
			var field = getField(form, name);
			if (field && typeof fields[name] === 'string' && field.value !== fields[name]) {
				field.value = fields[name];
				fireEvent(field, 'input');
				fireEvent(field, 'change');
			}
		});
	}

	function formatSavedAt(savedAt) {
		try {
			return TEXT.savedAt + new Date(savedAt).toLocaleString();
		} catch (e) {
			return '';
		}
	}

	function insertNotice(form, draft, restored, restoreCallback, discardCallback) {
		var oldNotice = form.querySelector('.postautosave-notice');
		if (oldNotice && oldNotice.parentNode) {
			oldNotice.parentNode.removeChild(oldNotice);
		}

		var notice = document.createElement('div');
		notice.className = 'postautosave-notice is-visible' + (restored ? ' is-restored' : '');
		notice.setAttribute('role', 'status');

		var message = document.createElement('span');
		message.className = 'postautosave-message';
		message.textContent = (restored ? TEXT.restored : TEXT.available) + ' ' + formatSavedAt(draft.savedAt) + ' ' + TEXT.attachments;
		notice.appendChild(message);

		var actions = document.createElement('span');
		actions.className = 'postautosave-actions';

		if (!restored) {
			var restore = document.createElement('button');
			restore.type = 'button';
			restore.className = 'button2 postautosave-action';
			restore.textContent = TEXT.restore;
			restore.addEventListener('click', restoreCallback);
			actions.appendChild(restore);
		}

		var discard = document.createElement('button');
		discard.type = 'button';
		discard.className = 'button2 postautosave-action';
		discard.textContent = TEXT.discard;
		discard.addEventListener('click', function () {
			discardCallback();
			if (notice.parentNode) {
				notice.parentNode.removeChild(notice);
			}
		});
		actions.appendChild(discard);
		notice.appendChild(actions);

		var messageBox = form.querySelector('#message-box') || getField(form, 'message');
		if (messageBox && messageBox.parentNode) {
			messageBox.parentNode.insertBefore(notice, messageBox);
		} else {
			form.insertBefore(notice, form.firstChild);
		}
	}

	function setupForm(form) {
		if (!getField(form, 'message') || !form.querySelector('input[name="post"], input[name="preview"]')) {
			return;
		}

		var key = storageKey(form);
		var draft = loadDraft(key);
		var saveTimer = null;

		function scheduleSave() {
			window.clearTimeout(saveTimer);
			saveTimer = window.setTimeout(function () {
				saveDraft(form, key, false);
			}, SAVE_DELAY_MS);
		}

		function discardDraft() {
			try {
				window.localStorage.removeItem(key);
			} catch (e) {
				// Ignore storage failures.
			}
		}

		if (draft && hasContent(draft.fields)) {
			var current = collectFields(form);
			var shouldAutoRestore = !hasContent(current) && !draft.submittedAt;

			if (shouldAutoRestore) {
				applyFields(form, draft.fields);
				insertNotice(form, draft, true, function () {}, discardDraft);
			} else if (!fieldsEqual(current, draft.fields)) {
				insertNotice(form, draft, false, function () {
					applyFields(form, draft.fields);
					saveDraft(form, key, false);
					insertNotice(form, draft, true, function () {}, discardDraft);
				}, discardDraft);
			}
		}

		FIELD_NAMES.forEach(function (name) {
			var field = getField(form, name);
			if (!field) {
				return;
			}

			['input', 'change', 'keyup', 'paste', 'blur'].forEach(function (eventName) {
				field.addEventListener(eventName, scheduleSave);
			});
		});

		form.addEventListener('submit', function () {
			window.clearTimeout(saveTimer);
			saveDraft(form, key, true);
		});
	}

	function boot() {
		if (!storageWorks()) {
			return;
		}

		Array.prototype.forEach.call(document.querySelectorAll('form#postform, form#qr_postform'), setupForm);
	}

	onReady(boot);
}());
