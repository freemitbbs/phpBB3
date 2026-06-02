(function () {
	'use strict';

	function splitAllowedExts(rawValue) {
		return (rawValue || '.jpg,.jpeg,.png,.gif,.webp,.avif,.mp4,.mov,.ogg,.webm,.weba,.mp3,.m4a,.aac,.wav,.oga,.opus,.flac')
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

	function formatMessage(message) {
		var result = String(message || '');
		Array.prototype.slice.call(arguments, 1).forEach(function (value, index) {
			var replacement = String(value);
			result = result
				.replace(new RegExp('%' + (index + 1) + '\\$s', 'g'), replacement)
				.replace(/%s/, replacement);
		});
		return result;
	}

	function parseUploadResponse(response) {
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
	}

	function uploadMediaFile(uploadUrl, hash, forumId, file) {
		var formData = new FormData();
		formData.append('hash', hash);
		formData.append('forum_id', forumId);
		formData.append('media_file', file);

		return fetch(uploadUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: formData,
		}).then(parseUploadResponse);
	}

	function isImageUpload(file, url) {
		var imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif'];
		var mimeType = file && typeof file.type === 'string' ? file.type.toLowerCase() : '';

		if (hasAnyExtension(url, imageExtensions)) {
			return true;
		}

		return mimeType.indexOf('image/') === 0;
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
		var videoExtensions = ['.mp4', '.mov', '.webm'];
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

	function insertUrlIntoEditor(url, uploadKind, control) {
		var cleanedUrl = String(url || '').trim();
		var text = cleanedUrl + '\n';
		if (uploadKind === 'image') {
			text = '[img]' + cleanedUrl + '[/img]\n';
		} else if (uploadKind === 'audio') {
			text = '[audio]' + cleanedUrl + '[/audio]\n';
		}
		if (!text.trim()) {
			return false;
		}

		return insertTextIntoEditor(text, control);
	}

	function insertTextIntoEditor(text, control) {
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

	function initUploader(control, button, input, status) {
		if (!button || !input || !status) {
			return;
		}

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

			setUploadingState(true);
			setStatus(msgUploading, 'is-busy');

			uploadMediaFile(uploadUrl, hash, forumId, file)
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

					var uploadKind = isImageUpload(file, data.url) ? 'image' : (isAudioUpload(file, data.url) ? 'audio' : 'video');
					if (!insertUrlIntoEditor(data.url, uploadKind, control)) {
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

	function setControlStatus(status, message, kind) {
		if (!status) {
			return;
		}
		status.textContent = message || '';
		status.classList.remove('is-error', 'is-success', 'is-busy');
		if (kind) {
			status.classList.add(kind);
		}
	}

	function getValidImageFiles(uploader, fileList) {
		var files = Array.prototype.slice.call(fileList || []);
		return files.filter(function (file) {
			return hasAllowedExtension(file.name, uploader.imageExts)
				&& (uploader.maxBytes <= 0 || file.size <= uploader.maxBytes);
		});
	}

	function setMultiUploadingState(uploader, uploading) {
		uploader.isUploading = uploading;
		uploader.button.classList.toggle('is-disabled', uploading);
		uploader.button.setAttribute('aria-disabled', uploading ? 'true' : 'false');
	}

	function uploadSelectedImages(uploader, fileList) {
		if (uploader.isUploading) {
			return;
		}

		var selectedFiles = Array.prototype.slice.call(fileList || []);
		var files = getValidImageFiles(uploader, selectedFiles);
		var skippedCount = selectedFiles.length - files.length;
		if (!files.length) {
			setControlStatus(uploader.status, selectedFiles.length ? uploader.msgImageExtension : uploader.msgMultiEmpty, 'is-error');
			uploader.input.value = '';
			return;
		}

		setMultiUploadingState(uploader, true);
		setControlStatus(uploader.status, formatMessage(uploader.msgMultiUploading, 1, files.length), 'is-busy');

		function finish(successCount, failureCount, bbcode) {
			var message;
			var kind = failureCount ? 'is-error' : 'is-success';
			if (bbcode && !insertTextIntoEditor(bbcode, uploader.control)) {
				message = uploader.msgGeneric;
				kind = 'is-error';
			} else if (failureCount) {
				message = formatMessage(uploader.msgMultiPartial, successCount, failureCount);
			} else {
				message = formatMessage(uploader.msgMultiSuccess, successCount);
			}

			setControlStatus(uploader.status, message, kind);
			setMultiUploadingState(uploader, false);
			uploader.input.value = '';
		}

		function uploadNext(index, successCount, failureCount, bbcode) {
			if (index >= files.length) {
				finish(successCount, failureCount, bbcode);
				return;
			}

			var file = files[index];
			var progressMessage = formatMessage(uploader.msgMultiUploading, index + 1, files.length);
			setControlStatus(uploader.status, progressMessage, 'is-busy');

			uploadMediaFile(uploader.uploadUrl, uploader.hash, uploader.forumId, file)
				.then(function (data) {
					if (!data || !data.success || !data.url) {
						uploadNext(index + 1, successCount, failureCount + 1, bbcode);
						return;
					}

					if (!hasAllowedExtension(data.url, uploader.imageExts)) {
						uploadNext(index + 1, successCount, failureCount + 1, bbcode);
						return;
					}

					uploadNext(index + 1, successCount + 1, failureCount, bbcode + '[img]' + data.url + '[/img]\n');
				})
				.catch(function () {
					uploadNext(index + 1, successCount, failureCount + 1, bbcode);
				});
		}

		uploadNext(0, 0, skippedCount, '');
	}

	function initMultiUploader(control, button, input, status) {
		if (!button || !input || !status) {
			return;
		}

		var uploader = {
			control: control,
			button: button,
			input: input,
			status: status,
			imageExts: splitAllowedExts(control.dataset.imageExts || '.jpg,.jpeg,.png,.gif,.webp,.avif'),
			maxBytes: parseInt(control.dataset.maxBytes || '0', 10) || 0,
			uploadUrl: control.dataset.uploadUrl || '',
			forumId: control.dataset.forumId || '',
			hash: control.dataset.hash || '',
			msgSuccess: control.dataset.msgSuccess || '',
			msgExtension: control.dataset.msgExtension || '',
			msgImageExtension: control.dataset.msgImageExtension || control.dataset.msgExtension || '',
			msgTooLarge: control.dataset.msgTooLarge || '',
			msgGeneric: control.dataset.msgGeneric || '',
			msgMultiEmpty: control.dataset.msgMultiEmpty || '',
			msgMultiUploading: control.dataset.msgMultiUploading || '',
			msgMultiSuccess: control.dataset.msgMultiSuccess || '',
			msgMultiPartial: control.dataset.msgMultiPartial || '',
			isUploading: false
		};

		button.addEventListener('click', function (event) {
			button.blur();
			event.preventDefault();
			if (uploader.isUploading) {
				return;
			}
			input.click();
		});

		input.addEventListener('change', function () {
			if (uploader.isUploading) {
				return;
			}
			uploadSelectedImages(uploader, input.files);
		});
	}

	function initUploaderByIds(controlId, buttonId, inputId, statusId, multiButtonId, multiInputId) {
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
		initMultiUploader(
			control,
			document.getElementById(multiButtonId),
			document.getElementById(multiInputId),
			document.getElementById(statusId)
		);
	}

	document.addEventListener('DOMContentLoaded', function () {
		initUploaderByIds('videoupload-control', 'videoupload-button', 'videoupload-file', 'videoupload-status', 'videoupload-multi-button', 'videoupload-multi-file');
		initUploaderByIds('videoupload-qr-control', 'videoupload-qr-button', 'videoupload-qr-file', 'videoupload-qr-status', 'videoupload-qr-multi-button', 'videoupload-qr-multi-file');
	});
})();
