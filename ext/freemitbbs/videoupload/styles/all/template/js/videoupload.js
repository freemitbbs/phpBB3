(function () {
	'use strict';

	function splitAllowedExts(rawValue) {
		return (rawValue || '.mp4,.ogg,.webm,.weba,.mp3,.m4a,.aac,.wav,.oga,.opus,.flac')
			.split(',')
			.map(function (item) { return item.trim().toLowerCase(); })
			.filter(function (item) { return item.length > 0; });
	}

	function hasAllowedExtension(nameOrUrl, allowedExts) {
		var base = String(nameOrUrl || '').split('#')[0].split('?')[0].toLowerCase();
		return allowedExts.some(function (ext) {
			return base.endsWith(ext);
		});
	}

	function hasAnyExtension(nameOrUrl, extensions) {
		var base = String(nameOrUrl || '').split('#')[0].split('?')[0].toLowerCase();
		return extensions.some(function (ext) {
			return base.endsWith(ext);
		});
	}

	function findMessageTextarea() {
		var formName = window.form_name || 'postform';
		var textName = window.text_name || 'message';
		var form = document.forms[formName];
		if (form && form.elements && form.elements[textName]) {
			return form.elements[textName];
		}
		return document.getElementById('message');
	}

	function isAudioUpload(file, url) {
		var audioOnlyExtensions = ['.weba', '.mp3', '.m4a', '.aac', '.wav', '.oga', '.opus', '.flac'];
		var videoExtensions = ['.mp4', '.webm'];
		var mimeType = file && typeof file.type === 'string' ? file.type.toLowerCase() : '';

		if (hasAnyExtension(url, videoExtensions)) {
			return false;
		}

		if (hasAnyExtension(url, ['.ogg'])) {
			return mimeType === 'audio/ogg' || mimeType === 'audio/vorbis';
		}

		if (hasAnyExtension(url, audioOnlyExtensions)) {
			return true;
		}

		if (mimeType.indexOf('video/') === 0) {
			return false;
		}

		if (mimeType.indexOf('audio/') === 0) {
			return true;
		}

		return false;
	}

	function insertUrlIntoEditor(url, wrapAsAudio) {
		var cleanedUrl = String(url || '').trim();
		var text = wrapAsAudio ? ('[audio]' + cleanedUrl + '[/audio]\n') : (cleanedUrl + '\n');
		if (!text.trim()) {
			return false;
		}

		if (typeof window.insert_text === 'function') {
			window.insert_text(text, false);
			return true;
		}

		var textarea = findMessageTextarea();
		if (!textarea) {
			return false;
		}

		var start = textarea.selectionStart || 0;
		var end = textarea.selectionEnd || 0;
		var currentValue = textarea.value || '';
		textarea.value = currentValue.substring(0, start) + text + currentValue.substring(end);
		var caret = start + text.length;
		textarea.selectionStart = caret;
		textarea.selectionEnd = caret;
		textarea.focus();
		return true;
	}

	function findPostimageControl() {
		var textarea = document.querySelector('#message-box textarea[data-postimg]');
		if (!textarea || !textarea.parentNode) {
			return null;
		}

		var siblings = textarea.parentNode.children;
		for (var i = 0; i < siblings.length; i++) {
			var node = siblings[i];
			if (!node || node.id === 'videoupload-control' || node.id === 'videoupload-row') {
				continue;
			}
			if (node.tagName !== 'DIV') {
				continue;
			}

			var link = node.querySelector('a[role="button"][href="#"]');
			var icon = node.querySelector('img[width="16"][height="16"]');
			if (link && icon) {
				return node;
			}
		}

		return null;
	}

	function placeBesidePostimage(control) {
		var postimageControl = findPostimageControl();
		if (!postimageControl || !postimageControl.parentNode) {
			return false;
		}

		var parent = postimageControl.parentNode;
		var row = document.getElementById('videoupload-row');
		if (!row) {
			row = document.createElement('div');
			row.id = 'videoupload-row';
			row.className = 'videoupload-inline-row';
		}

		if (row.parentNode !== parent) {
			parent.appendChild(row);
		}

		if (postimageControl.parentNode !== row) {
			row.appendChild(postimageControl);
		}
		if (control.parentNode !== row) {
			row.appendChild(control);
		}

		control.classList.add('videoupload-inline');
		return true;
	}

	function placeBesidePostimageWithRetry(control, status) {
		if (placeBesidePostimage(control)) {
			return;
		}

		var attempts = 0;
		var timer = window.setInterval(function () {
			attempts += 1;
			if (placeBesidePostimage(control) || attempts >= 40) {
				window.clearInterval(timer);
				if (attempts >= 40 && status && !status.textContent) {
					status.textContent = '';
				}
			}
		}, 250);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var control = document.getElementById('videoupload-control');
		if (!control) {
			return;
		}

		var button = document.getElementById('videoupload-button');
		var input = document.getElementById('videoupload-file');
		var status = document.getElementById('videoupload-status');
		if (!button || !input || !status) {
			return;
		}

		placeBesidePostimageWithRetry(control, status);

		var allowedExts = splitAllowedExts(control.dataset.allowedExts);
		var maxBytes = parseInt(control.dataset.maxBytes || '0', 10) || 0;
		var uploadUrl = control.dataset.uploadUrl || '';
		var forumId = control.dataset.forumId || '';
		var hash = control.dataset.hash || '';
		var msgUploading = control.dataset.msgUploading || '';
		var msgSuccess = control.dataset.msgSuccess || '';
		var msgExtension = control.dataset.msgExtension || '';
		var msgTooLarge = control.dataset.msgTooLarge || '';
		var msgGeneric = control.dataset.msgGeneric || '';
		var isUploading = false;

		function setStatus(message, kind) {
			status.textContent = message;
			status.classList.remove('is-error', 'is-success', 'is-busy');
			if (kind) {
				status.classList.add(kind);
			}
		}

		function setUploadingState(uploading) {
			isUploading = uploading;
			button.classList.toggle('is-disabled', uploading);
			button.setAttribute('aria-disabled', uploading ? 'true' : 'false');
		}

		button.addEventListener('click', function (event) {
			button.blur();
			if (isUploading) {
				return;
			}
			event.preventDefault();
			input.click();
		});

		input.addEventListener('change', function () {
			if (!input.files || !input.files.length) {
				return;
			}

			var file = input.files[0];
			if (!hasAllowedExtension(file.name, allowedExts)) {
				setStatus(msgExtension, 'is-error');
				input.value = '';
				return;
			}

			if (maxBytes > 0 && file.size > maxBytes) {
				setStatus(msgTooLarge, 'is-error');
				input.value = '';
				return;
			}

			var formData = new FormData();
			formData.append('hash', hash);
			formData.append('forum_id', forumId);
			formData.append('video_file', file);

			setUploadingState(true);
			setStatus(msgUploading, 'is-busy');

			fetch(uploadUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: formData,
			})
				.then(function (response) {
					return response.text().then(function (rawText) {
						var data = {};
						try {
							data = JSON.parse(rawText);
						} catch (e) {
							var stripped = String(rawText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
							if (stripped) {
								data.error = stripped.substring(0, 220);
							}
						}
						data._httpStatus = response.status;
						return data;
					});
				})
				.then(function (data) {
					if (!data || !data.success || !data.url) {
						var message = (data && data.error) ? data.error : msgGeneric;
						if ((!data || !data.error) && data && data._httpStatus) {
							message = msgGeneric + ' (HTTP ' + data._httpStatus + ')';
						}
						setStatus(message, 'is-error');
						return;
					}

						if (!hasAllowedExtension(data.url, allowedExts)) {
							setStatus(msgExtension, 'is-error');
							return;
						}

						if (!insertUrlIntoEditor(data.url, isAudioUpload(file, data.url))) {
							setStatus(msgGeneric, 'is-error');
							return;
						}

					setStatus(msgSuccess, 'is-success');
				})
				.catch(function () {
					setStatus(msgGeneric, 'is-error');
				})
				.finally(function () {
					setUploadingState(false);
					input.value = '';
				});
		});
	});
})();
