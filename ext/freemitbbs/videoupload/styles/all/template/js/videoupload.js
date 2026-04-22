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

	function findMessageTextarea(control) {
		if (control && typeof control.closest === 'function') {
			var localForm = control.closest('form');
			if (localForm && localForm.elements && localForm.elements.message) {
				return localForm.elements.message;
			}
		}

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

	function insertUrlIntoEditor(url, wrapAsAudio, control) {
		var cleanedUrl = String(url || '').trim();
		var text = wrapAsAudio ? ('[audio]' + cleanedUrl + '[/audio]\n') : (cleanedUrl + '\n');
		if (!text.trim()) {
			return false;
		}

		var textarea = findMessageTextarea(control);
		if (!textarea) {
			return false;
		}

		var formName = window.form_name || '';
		var textName = window.text_name || 'message';
		var configuredForm = formName ? document.forms[formName] : null;
		if (typeof window.insert_text === 'function' && configuredForm && configuredForm.elements && configuredForm.elements[textName] === textarea) {
			window.insert_text(text, false);
			return true;
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

	function closestMessageBox(textarea) {
		var node = textarea ? textarea.parentNode : null;
		while (node && node !== document.body) {
			if (node.classList && node.classList.contains('message-box')) {
				return node;
			}
			node = node.parentNode;
		}
		return textarea ? textarea.parentNode : null;
	}

	function findPostimageControl(control) {
		var textarea = findMessageTextarea(control);
		if (!textarea || !textarea.parentNode) {
			return null;
		}

		var messageBox = closestMessageBox(textarea);
		if (!messageBox) {
			return null;
		}

		var siblings = messageBox.children;
		for (var i = 0; i < siblings.length; i++) {
			var node = siblings[i];
			if (!node || node.id === 'videoupload-control' || node.id === 'videoupload-qr-control' || node.id === 'videoupload-row') {
				continue;
			}
			if (node.tagName !== 'DIV') {
				continue;
			}

			var link = node.querySelector('a[role="button"][href="#"]');
			if (link) {
				return node;
			}
		}

		return null;
	}

	function placeBesidePostimage(control) {
		var postimageControl = findPostimageControl(control);
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

	function initUploader(control, button, input, status) {
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
			event.preventDefault();
			if (isUploading) {
				return;
			}
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

						if (!insertUrlIntoEditor(data.url, isAudioUpload(file, data.url), control)) {
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
	}

	function initUploaderByIds(controlId, buttonId, inputId, statusId) {
		var control = document.getElementById(controlId);
		if (!control) {
			return;
		}

		initUploader(
			control,
			document.getElementById(buttonId),
			document.getElementById(inputId),
			document.getElementById(statusId)
		);
	}

	document.addEventListener('DOMContentLoaded', function () {
		initUploaderByIds('videoupload-control', 'videoupload-button', 'videoupload-file', 'videoupload-status');
		initUploaderByIds('videoupload-qr-control', 'videoupload-qr-button', 'videoupload-qr-file', 'videoupload-qr-status');
	});
})();
