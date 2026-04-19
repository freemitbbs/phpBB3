(function () {
	'use strict';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function selectedFile(input) {
		return !!(input && ((input.files && input.files.length > 0) || input.value));
	}

	function setStatus(status, message, kind) {
		if (!status) {
			return;
		}

		status.textContent = message || '';
		status.classList.remove('is-busy', 'is-success', 'is-error');
		if (kind) {
			status.classList.add(kind);
		}
	}

	function formatSize(bytes) {
		var units = ['B', 'KB', 'MB', 'GB'];
		var value = parseInt(bytes || '0', 10) || 0;
		var unit = 0;

		while (value >= 1024 && unit < units.length - 1) {
			value = value / 1024;
			unit += 1;
		}

		return (unit === 0 ? value.toFixed(0) : value.toFixed(1)) + ' ' + units[unit];
	}

	function decodeHtml(value) {
		var textarea = document.createElement('textarea');
		textarea.innerHTML = value || '';

		return textarea.value;
	}

	function collectDownloadUrls(fileList) {
		var urls = {};
		var rows = fileList ? fileList.querySelectorAll('.attach-row[data-attach-id]') : [];

		Array.prototype.forEach.call(rows, function (row) {
			var link = row.querySelector('.file-name a');
			if (link) {
				urls[row.getAttribute('data-attach-id')] = link.getAttribute('href');
			}
		});

		return urls;
	}

	function currentAttachmentCount(fileList) {
		return fileList ? fileList.querySelectorAll('.attach-row[data-attach-id]').length : 0;
	}

	function preserveCurrentComments(fileList) {
		var comments = {};
		var rows = fileList ? fileList.querySelectorAll('.attach-row[data-attach-id]') : [];

		Array.prototype.forEach.call(rows, function (row) {
			var textarea = row.querySelector('textarea');
			if (textarea) {
				comments[row.getAttribute('data-attach-id')] = textarea.value;
			}
		});

		return comments;
	}

	function shiftAttachmentBbcodeIndexes() {
		var textarea = document.getElementById('message');
		if (!textarea || textarea.value.indexOf('[attachment=') === -1) {
			return;
		}

		textarea.value = textarea.value.replace(/\[attachment=(\d+)\]/g, function (match, index) {
			return '[attachment=' + (parseInt(index, 10) + 1) + ']';
		});
	}

	function appendHidden(row, index, attachment) {
		Object.keys(attachment).forEach(function (key) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'attachment_data[' + index + '][' + key + ']';
			input.value = attachment[key];
			row.appendChild(input);
		});
	}

	function createAttachmentRow(attachment, index, url, deleteLabel, inlineLabel) {
		var row = document.createElement('tr');
		row.className = 'attach-row';
		row.setAttribute('data-attach-id', attachment.attach_id);

		var nameCell = document.createElement('td');
		nameCell.className = 'attach-name';

		var fileName = document.createElement('span');
		fileName.className = 'file-name ellipsis-text';

		var link = document.createElement('a');
		link.href = url || ('download/file.php?mode=view&id=' + encodeURIComponent(attachment.attach_id));
		link.textContent = attachment.real_filename || '';
		fileName.appendChild(link);
		nameCell.appendChild(fileName);

		var controls = document.createElement('span');
		controls.className = 'attach-controls';

		if (typeof window.attachInline === 'function') {
			var inline = document.createElement('input');
			inline.type = 'button';
			inline.value = inlineLabel;
			inline.className = 'button2 file-inline-bbcode';
			inline.addEventListener('click', function () {
				window.attachInline(index, attachment.real_filename || '');
			});
			controls.appendChild(inline);
			controls.appendChild(document.createTextNode(' '));
		}

		var remove = document.createElement('input');
		remove.type = 'submit';
		remove.name = 'delete_file[' + index + ']';
		remove.value = deleteLabel;
		remove.className = 'button2 file-delete';
		controls.appendChild(remove);
		nameCell.appendChild(controls);

		var clear = document.createElement('span');
		clear.className = 'clear';
		nameCell.appendChild(clear);

		var commentCell = document.createElement('td');
		commentCell.className = 'attach-comment';
		var comment = document.createElement('textarea');
		comment.name = 'comment_list[' + index + ']';
		comment.rows = 1;
		comment.cols = 30;
		comment.className = 'inputbox';
		comment.value = attachment.attach_comment || '';
		commentCell.appendChild(comment);

		var sizeCell = document.createElement('td');
		sizeCell.className = 'attach-filesize';
		var size = document.createElement('span');
		size.className = 'file-size';
		size.textContent = formatSize(attachment.filesize);
		sizeCell.appendChild(size);

		var statusCell = document.createElement('td');
		statusCell.className = 'attach-status';
		var state = document.createElement('span');
		state.className = 'file-status file-uploaded';
		statusCell.appendChild(state);

		row.appendChild(nameCell);
		row.appendChild(commentCell);
		row.appendChild(sizeCell);
		row.appendChild(statusCell);
		appendHidden(row, index, attachment);

		return row;
	}

	function renderAttachments(data, newDownloadUrl) {
		var fileList = document.getElementById('file-list');
		var container = document.getElementById('file-list-container');
		var template = document.getElementById('attach-row-tpl');
		if (!fileList || !container || !template) {
			return;
		}

		var urls = collectDownloadUrls(fileList);
		var comments = preserveCurrentComments(fileList);
		var deleteButton = template.querySelector('.file-delete');
		var inlineButton = template.querySelector('.file-inline-bbcode');
		var deleteLabel = deleteButton ? deleteButton.value : 'Delete file';
		var inlineLabel = inlineButton ? inlineButton.value : 'Place inline';

		Array.prototype.forEach.call(fileList.querySelectorAll('.attach-row[data-attach-id]'), function (row) {
			row.parentNode.removeChild(row);
		});

		template.classList.add('hidden');
		template.style.display = 'none';

		if (data && data.length) {
			container.classList.remove('hidden');
			container.style.display = '';
		}

		data.forEach(function (attachment, index) {
			var attachId = String(attachment.attach_id);
			if (Object.prototype.hasOwnProperty.call(comments, attachId)) {
				attachment.attach_comment = comments[attachId];
			}
			if (index === 0 && newDownloadUrl) {
				urls[attachId] = decodeHtml(newDownloadUrl);
			}
			fileList.appendChild(createAttachmentRow(attachment, index, urls[attachId], deleteLabel, inlineLabel));
		});
	}

	function parseUploadResponse(response) {
		return response.text().then(function (rawText) {
			var data = {};

			try {
				data = JSON.parse(rawText);
			} catch (e) {
				data.error = String(rawText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 220);
			}

			data._httpStatus = response.status;
			return data;
		});
	}

	function initBasicAjax(status) {
		var panel = document.getElementById('attach-panel-basic');
		var fileInput = document.getElementById('fileupload');
		var addFileButton = panel ? panel.querySelector('input[name="add_file"]') : null;
		var form = document.getElementById('postform');
		var fileComment = document.getElementById('filecomment');
		var attachPanel = document.getElementById('attach-panel');
		var uploading = false;

		if (!panel || !fileInput || !addFileButton || !form || !window.fetch || !window.FormData) {
			return;
		}

		function setUploading(uploadingNow) {
			uploading = uploadingNow;
			addFileButton.disabled = uploadingNow;
			fileInput.disabled = uploadingNow;
			if (attachPanel) {
				attachPanel.classList.toggle('s3attachments-uploading', uploadingNow);
			}
			if (uploadingNow) {
				setStatus(status, status.dataset.msgUploading, 'is-busy');
			}
		}

		function uploadSelectedFile() {
			if (uploading) {
				return false;
			}
			if (!selectedFile(fileInput)) {
				return false;
			}

			var previousCount = currentAttachmentCount(document.getElementById('file-list'));
			var formData = new FormData(form);
			formData.append('add_file', addFileButton.value || 'add_file');

			setUploading(true);

			fetch(form.action, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-PHPBB-USING-PLUPLOAD': '1',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: formData
			})
				.then(parseUploadResponse)
				.then(function (data) {
					if (!data || data.error || data.title || !data.data) {
						var message = (data && data.error && data.error.message) || data.error || data.message || status.dataset.msgError;
						if ((!data || !data.error) && data && data._httpStatus >= 400) {
							message = status.dataset.msgError + ' (HTTP ' + data._httpStatus + ')';
						}
						setStatus(status, message, 'is-error');
						return;
					}

					if (data.data.length > previousCount) {
						shiftAttachmentBbcodeIndexes();
					}
					renderAttachments(data.data, data.download_url || '');
					fileInput.value = '';
					if (fileComment) {
						fileComment.value = '';
					}
					setStatus(status, status.dataset.msgSuccess, 'is-success');
				})
				.catch(function () {
					setStatus(status, status.dataset.msgError, 'is-error');
				})
				.finally(function () {
					setUploading(false);
				});

			return false;
		}

		fileInput.addEventListener('change', function () {
			uploadSelectedFile();
		});

		form.addEventListener('submit', function (event) {
			var submitter = event.submitter || document.activeElement;

			if (uploading) {
				event.preventDefault();
				setStatus(status, status.dataset.msgWait, 'is-busy');
				return;
			}

			if (submitter && submitter.name === 'add_file') {
				event.preventDefault();
				uploadSelectedFile();
				return;
			}

			if (submitter && submitter.name === 'post' && selectedFile(fileInput)) {
				event.preventDefault();
				uploadSelectedFile();
			}
		});
	}

	function initPluploadGuard(status) {
		if (!window.phpbb || !window.phpbb.plupload || !window.phpbb.plupload.uploader) {
			return;
		}

		var uploader = window.phpbb.plupload.uploader;
		var addFiles = document.getElementById('add_files');
		var form = document.getElementById('postform');
		var uploading = false;

		function hasActiveFiles() {
			if (!window.plupload || !uploader.files) {
				return uploading;
			}

			return uploader.files.some(function (file) {
				return file.status === window.plupload.QUEUED || file.status === window.plupload.STARTED;
			});
		}

		function setUploading(uploadingNow) {
			uploading = uploadingNow;
			if (addFiles) {
				addFiles.disabled = uploadingNow;
				addFiles.classList.toggle('s3attachments-uploading', uploadingNow);
				addFiles.setAttribute('aria-disabled', uploadingNow ? 'true' : 'false');
			}
			if (uploadingNow) {
				setStatus(status, status.dataset.msgUploading, 'is-busy');
			}
		}

		uploader.bind('FilesAdded', function () {
			setUploading(true);
		});
		uploader.bind('BeforeUpload', function () {
			setUploading(true);
		});
		uploader.bind('UploadComplete', function () {
			setUploading(false);
			setStatus(status, status.dataset.msgSuccess, 'is-success');
		});
		uploader.bind('Error', function () {
			if (!hasActiveFiles()) {
				setUploading(false);
			}
		});

		if (form) {
			form.addEventListener('submit', function (event) {
				var submitter = event.submitter || document.activeElement;
				if (submitter && submitter.name === 'post' && (uploading || hasActiveFiles())) {
					event.preventDefault();
					setStatus(status, status.dataset.msgWait, 'is-busy');
				}
			});
		}
	}

	onReady(function () {
		var status = document.getElementById('s3attachments-status');
		if (!status) {
			return;
		}

		initBasicAjax(status);
		initPluploadGuard(status);
	});
})();
