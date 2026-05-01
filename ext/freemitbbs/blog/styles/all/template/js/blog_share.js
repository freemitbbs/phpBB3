(function() {
	'use strict';

	function onReady(callback) {
		if (document.readyState !== 'loading') {
			callback();
			return;
		}

		document.addEventListener('DOMContentLoaded', callback);
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function(resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.left = '-9999px';
			textarea.style.top = '0';
			document.body.appendChild(textarea);
			textarea.focus();
			textarea.select();

			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject(new Error('copy failed'));
				}
			} catch (error) {
				reject(error);
			} finally {
				document.body.removeChild(textarea);
			}
		});
	}

	function rootData(root, name) {
		return root.getAttribute(name) || '';
	}

	function buildShareText(root) {
		var title = rootData(root, 'data-share-title') || document.title;
		var excerpt = rootData(root, 'data-share-excerpt');
		var url = rootData(root, 'data-share-url') || window.location.href;
		var lines = [title];

		if (excerpt) {
			lines.push('', excerpt);
		}

		lines.push('', url);

		return lines.join('\n');
	}

	function showFeedback(root, message, isError) {
		var feedback = root.querySelector('.blog-share-feedback');

		if (!feedback || !message) {
			return;
		}

		feedback.textContent = message;
		if (isError) {
			feedback.classList.add('error');
		} else {
			feedback.classList.remove('error');
		}

		window.clearTimeout(root.blogShareFeedbackTimer);
		root.blogShareFeedbackTimer = window.setTimeout(function() {
			feedback.textContent = '';
			feedback.classList.remove('error');
		}, 6000);
	}

	function isMobileLikeBrowser() {
		return /Android|iPhone|iPad|iPod|HarmonyOS|Mobile/i.test(navigator.userAgent || '');
	}

	function openAppIfUseful(button) {
		var appUrl = button.getAttribute('data-blog-share-app-url') || '';

		if (!appUrl || !isMobileLikeBrowser()) {
			return;
		}

		window.setTimeout(function() {
			window.location.href = appUrl;
		}, 150);
	}

	function tryNativeShare(root, button) {
		var url = rootData(root, 'data-share-url') || window.location.href;
		var title = rootData(root, 'data-share-title') || document.title;
		var excerpt = rootData(root, 'data-share-excerpt');

		if (!navigator.share) {
			openAppIfUseful(button);
			return;
		}

		navigator.share({
			title: title,
			text: excerpt || title,
			url: url
		}).catch(function() {
			// User cancellation is expected; the share text has already been copied.
		});
	}

	function openShareImage(imageUrl) {
		if (!imageUrl) {
			return;
		}

		var opened = window.open(imageUrl, '_blank', 'noopener');

		if (!opened) {
			window.location.href = imageUrl;
		}
	}

	function canTryFileShare() {
		return !!(navigator.share && navigator.canShare && window.fetch && window.File);
	}

	function shareImageFile(root, imageUrl) {
		var title = rootData(root, 'data-share-title') || document.title;
		var excerpt = rootData(root, 'data-share-excerpt');

		if (!imageUrl || !canTryFileShare()) {
			openShareImage(imageUrl);
			return;
		}

		window.fetch(imageUrl, {
			credentials: 'same-origin'
		}).then(function(response) {
			if (!response.ok) {
				throw new Error('image request failed');
			}

			return response.blob();
		}).then(function(blob) {
			var file = new window.File([blob], 'xiaohongshu-share.png', {
				type: blob.type || 'image/png'
			});
			var payload = {
				title: title,
				text: excerpt || title,
				files: [file]
			};

			if (!navigator.canShare(payload)) {
				openShareImage(imageUrl);
				return;
			}

			return navigator.share(payload).catch(function() {
				// User cancellation is expected.
			});
		}).catch(function() {
			openShareImage(imageUrl);
		});
	}

	function handleImageShareClick(root, button) {
		var imageUrl = button.getAttribute('data-share-image-url') || rootData(root, 'data-share-image-url');
		var feedback = button.getAttribute('data-blog-share-feedback') || '';

		copyText(buildShareText(root)).then(function() {
			showFeedback(root, feedback, false);
		}).catch(function() {
			showFeedback(root, rootData(root, 'data-copy-failed'), true);
		});
		shareImageFile(root, imageUrl);
	}

	function handleShareClick(root, button) {
		var platform = button.getAttribute('data-blog-share-platform') || 'link';
		var url = rootData(root, 'data-share-url') || window.location.href;
		var text = platform === 'link' ? url : buildShareText(root);
		var feedback = button.getAttribute('data-blog-share-feedback') || '';

		if (platform === 'xiaohongshu-image') {
			handleImageShareClick(root, button);
			return;
		}

		copyText(text).then(function() {
			showFeedback(root, feedback, false);
			if (platform !== 'link') {
				tryNativeShare(root, button);
			}
		}).catch(function() {
			showFeedback(root, rootData(root, 'data-copy-failed'), true);
			if (platform !== 'link') {
				tryNativeShare(root, button);
			}
		});
	}

	onReady(function() {
		var shareBlocks = document.querySelectorAll('.blog-share');
		var i;

		for (i = 0; i < shareBlocks.length; i++) {
			shareBlocks[i].addEventListener('click', function(event) {
				var target = event.target.nodeType === 1 ? event.target : event.target.parentElement;
				var button = target ? target.closest('[data-blog-share-platform]') : null;

				if (!button || !this.contains(button)) {
					return;
				}

				event.preventDefault();
				handleShareClick(this, button);
			});
		}
	});
})();
