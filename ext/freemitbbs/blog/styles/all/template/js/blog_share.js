(function() {
	'use strict';

	var SHARE_IMAGE_MIN_SIDE = 120;
	var SHARE_IMAGE_MIN_AREA = 20000;
	var XIAOHONGSHU_TEXT_MAX_LENGTH = 1000;
	var TEXT_CARD_WIDTH = 1080;
	var TEXT_CARD_HEIGHT = 1440;
	var TEXT_CARD_PADDING = 88;
	var TEXT_CARD_BODY_TOP = 210;
	var TEXT_CARD_BODY_BOTTOM = 1260;
	var TEXT_CARD_BODY_FONT_SIZE = 42;
	var TEXT_CARD_LINE_HEIGHT = 64;

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

	function buildShareText(root, options) {
		options = options || {};
		var title = rootData(root, 'data-share-title') || document.title;
		var body = options.fullText ? (rootData(root, 'data-share-full-text') || rootData(root, 'data-share-excerpt')) : rootData(root, 'data-share-excerpt');
		var url = rootData(root, 'data-share-url') || window.location.href;
		var lines = [title];

		if (body) {
			lines.push('', body);
		}

		if (options.includeUrl !== false) {
			lines.push('', url);
		}

		return lines.join('\n');
	}

	function xiaohongshuFullText(root) {
		return (rootData(root, 'data-share-full-text') || rootData(root, 'data-share-excerpt')).trim();
	}

	function buildXiaohongshuSharePlan(root) {
		var fullText = xiaohongshuFullText(root);
		var useTextCards = fullText.length > XIAOHONGSHU_TEXT_MAX_LENGTH;

		return {
			fullText: fullText,
			caption: useTextCards ? '' : fullText,
			useTextCards: useTextCards
		};
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

	function contentImageUrls() {
		var images = document.querySelectorAll('.blog-entry-postbody .content img[src]');
		var seen = {};
		var urls = [];
		var i;
		var url;

		for (i = 0; i < images.length; i++) {
			url = images[i].currentSrc || images[i].src || images[i].getAttribute('src') || '';
			url = url.trim();

			if (url && !seen[url] && isShareContentImage(images[i], url)) {
				seen[url] = true;
				urls.push(url);
			}
		}

		return urls;
	}

	function isShareContentImage(image, url) {
		var className = String(image.className || '').toLowerCase();
		var lowerUrl = String(url || '').toLowerCase();
		var size = shareImageRenderedSize(image);

		if (size.width > 0 && size.height > 0
			&& (Math.max(size.width, size.height) < SHARE_IMAGE_MIN_SIDE || (size.width * size.height) < SHARE_IMAGE_MIN_AREA))
		{
			return false;
		}

		// Keep these as secondary guards. Size is the primary filter above.
		if (/\b(?:emoji|smilies|modernsmiley-emoji)\b/.test(className)) {
			return false;
		}

		if (image.hasAttribute('data-modernsmiley-hover-src')
			|| image.hasAttribute('data-modernsmiley-hover-fallback-src')
			|| image.hasAttribute('data-modernsmiley-static-fallback-src'))
		{
			return false;
		}

		if (lowerUrl.indexOf('/images/smilies/') !== -1
			|| lowerUrl.indexOf('/ext/freemitbbs/modernsmiley/') !== -1
			|| lowerUrl.indexOf('fonts.gstatic.com/s/e/notoemoji/') !== -1
			|| lowerUrl.indexOf('?modernsmiley=') !== -1
			|| lowerUrl.indexOf('&modernsmiley=') !== -1)
		{
			return false;
		}

		return true;
	}

	function shareImageRenderedSize(image) {
		var rect = image.getBoundingClientRect ? image.getBoundingClientRect() : null;
		var width = rect && rect.width ? rect.width : 0;
		var height = rect && rect.height ? rect.height : 0;

		if (width > 0 && height > 0) {
			return {
				width: width,
				height: height
			};
		}

		return {
			width: image.naturalWidth || image.width || parseInt(image.getAttribute('width') || '0', 10) || 0,
			height: image.naturalHeight || image.height || parseInt(image.getAttribute('height') || '0', 10) || 0
		};
	}

	function addUniqueUrl(urls, seen, url) {
		url = (url || '').trim();

		if (url && !seen[url]) {
			seen[url] = true;
			urls.push(url);
		}
	}

	function hasVideoExtension(url) {
		var path = '';

		try {
			path = new URL(url, window.location.href).pathname.toLowerCase();
		} catch (error) {
			path = (url || '').toLowerCase().split('#')[0].split('?')[0];
		}

		return /\.(?:mp4|m4v|mov|webm|ogv|ogg)$/.test(path);
	}

	function contentVideoUrls() {
		var content = document.querySelector('.blog-entry-postbody .content');
		var seen = {};
		var urls = [];
		var nodes;
		var i;

		if (!content) {
			return urls;
		}

		nodes = content.querySelectorAll('video[src], video source[src]');
		for (i = 0; i < nodes.length; i++) {
			addUniqueUrl(urls, seen, nodes[i].src || nodes[i].getAttribute('src') || '');
		}

		nodes = content.querySelectorAll('a[href]');
		for (i = 0; i < nodes.length; i++) {
			if (hasVideoExtension(nodes[i].href || nodes[i].getAttribute('href') || '')) {
				addUniqueUrl(urls, seen, nodes[i].href || nodes[i].getAttribute('href') || '');
			}
		}

		return urls;
	}

	function mediaExtension(type) {
		type = (type || '').toLowerCase();

		if (type.indexOf('jpeg') !== -1 || type.indexOf('jpg') !== -1) {
			return 'jpg';
		}
		if (type.indexOf('webp') !== -1) {
			return 'webp';
		}
		if (type.indexOf('gif') !== -1) {
			return 'gif';
		}
		if (type.indexOf('quicktime') !== -1) {
			return 'mov';
		}
		if (type.indexOf('mp4') !== -1) {
			return 'mp4';
		}
		if (type.indexOf('webm') !== -1) {
			return 'webm';
		}
		if (type.indexOf('ogg') !== -1) {
			return 'ogv';
		}

		return 'png';
	}

	function mediaTypeFromUrl(url) {
		var path = '';

		try {
			path = new URL(url, window.location.href).pathname.toLowerCase();
		} catch (error) {
			path = (url || '').toLowerCase();
		}

		if (/\.(?:jpe?g)$/.test(path)) {
			return 'image/jpeg';
		}
		if (/\.webp$/.test(path)) {
			return 'image/webp';
		}
		if (/\.gif$/.test(path)) {
			return 'image/gif';
		}
		if (/\.png$/.test(path)) {
			return 'image/png';
		}
		if (/\.mp4$|\.m4v$/.test(path)) {
			return 'video/mp4';
		}
		if (/\.mov$/.test(path)) {
			return 'video/quicktime';
		}
		if (/\.webm$/.test(path)) {
			return 'video/webm';
		}
		if (/\.ogv$|\.ogg$/.test(path)) {
			return 'video/ogg';
		}

		return '';
	}

	function mediaTypeForBlob(blob, url, fallbackType) {
		var type = (blob.type || '').toLowerCase();

		if (type.indexOf('image/') === 0 || type.indexOf('video/') === 0) {
			return blob.type;
		}

		return mediaTypeFromUrl(url) || fallbackType;
	}

	function fetchShareMediaFile(url, name, fallbackType) {
		return window.fetch(url, {
			credentials: 'same-origin'
		}).then(function(response) {
			if (!response.ok) {
				throw new Error('image request failed');
			}

			return response.blob();
		}).then(function(blob) {
			var type = mediaTypeForBlob(blob, url, fallbackType);

			return new window.File([blob], name + '.' + mediaExtension(type), {
				type: type
			});
		});
	}

	function fetchOptionalShareImageFile(url, index) {
		return fetchShareMediaFile(url, 'xiaohongshu-image-' + index, 'image/png').catch(function() {
			return null;
		});
	}

	function fetchOptionalShareVideoFile(url, index) {
		return fetchShareMediaFile(url, 'xiaohongshu-video-' + index, mediaTypeFromUrl(url) || 'video/mp4').catch(function() {
			return null;
		});
	}

	function createTextCardFiles(root, text) {
		text = String(text || '').trim();

		if (!text || !window.HTMLCanvasElement || !window.File) {
			return Promise.resolve([]);
		}

		var title = rootData(root, 'data-share-title') || document.title;
		var canvas = document.createElement('canvas');
		var context = canvas.getContext && canvas.getContext('2d');
		var maxWidth = TEXT_CARD_WIDTH - (TEXT_CARD_PADDING * 2);
		var maxLines = Math.floor((TEXT_CARD_BODY_BOTTOM - TEXT_CARD_BODY_TOP) / TEXT_CARD_LINE_HEIGHT);
		var lines;
		var pages = [];
		var files = [];
		var i;

		if (!context) {
			return Promise.resolve([]);
		}

		context.font = TEXT_CARD_BODY_FONT_SIZE + 'px -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans CJK SC", "Microsoft YaHei", sans-serif';
		lines = wrapCanvasText(context, text, maxWidth);
		for (i = 0; i < lines.length; i += maxLines) {
			pages.push(lines.slice(i, i + maxLines));
		}

		if (!pages.length) {
			return Promise.resolve([]);
		}

		return pages.reduce(function(promise, pageLines, index) {
			return promise.then(function() {
				return renderTextCardFile(title, pageLines, index + 1, pages.length).then(function(file) {
					if (file) {
						files.push(file);
					}
				});
			});
		}, Promise.resolve()).then(function() {
			return files;
		});
	}

	function wrapCanvasText(context, text, maxWidth) {
		var paragraphs = String(text || '').replace(/\r/g, '').split(/\n+/);
		var lines = [];
		var paragraph;
		var characters;
		var line;
		var candidate;
		var i;
		var j;

		for (i = 0; i < paragraphs.length; i++) {
			paragraph = paragraphs[i].trim();
			if (!paragraph) {
				continue;
			}

			characters = Array.from(paragraph);
			line = '';

			for (j = 0; j < characters.length; j++) {
				candidate = line + characters[j];
				if (line && context.measureText(candidate).width > maxWidth) {
					lines.push(line.replace(/\s+$/g, ''));
					line = characters[j].replace(/^\s+/g, '');
				} else {
					line = candidate;
				}
			}

			if (line) {
				lines.push(line.replace(/\s+$/g, ''));
			}
		}

		return lines;
	}

	function renderTextCardFile(title, lines, page, totalPages) {
		var canvas = document.createElement('canvas');
		var context = canvas.getContext && canvas.getContext('2d');
		var y = TEXT_CARD_BODY_TOP;
		var i;

		if (!context || !canvas.toBlob) {
			return Promise.resolve(null);
		}

		canvas.width = TEXT_CARD_WIDTH;
		canvas.height = TEXT_CARD_HEIGHT;
		context.fillStyle = '#f8f5ef';
		context.fillRect(0, 0, TEXT_CARD_WIDTH, TEXT_CARD_HEIGHT);
		context.fillStyle = '#ffffff';
		roundRect(context, 54, 54, TEXT_CARD_WIDTH - 108, TEXT_CARD_HEIGHT - 108, 36);
		context.fill();

		context.fillStyle = '#ff2442';
		context.font = '700 34px -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans CJK SC", "Microsoft YaHei", sans-serif';
		context.textBaseline = 'top';
		context.fillText('RED', TEXT_CARD_PADDING + 4, 104);

		context.fillStyle = '#232323';
		context.font = '700 42px -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans CJK SC", "Microsoft YaHei", sans-serif';
		context.fillText(fitCanvasText(context, title, TEXT_CARD_WIDTH - (TEXT_CARD_PADDING * 2) - 110), TEXT_CARD_PADDING + 104, 96);

		context.fillStyle = '#333333';
		context.font = TEXT_CARD_BODY_FONT_SIZE + 'px -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans CJK SC", "Microsoft YaHei", sans-serif';
		for (i = 0; i < lines.length; i++) {
			context.fillText(lines[i], TEXT_CARD_PADDING, y);
			y += TEXT_CARD_LINE_HEIGHT;
		}

		context.fillStyle = '#777777';
		context.font = '26px -apple-system, BlinkMacSystemFont, "PingFang SC", "Noto Sans CJK SC", "Microsoft YaHei", sans-serif';
		context.fillText(page + ' / ' + totalPages, TEXT_CARD_PADDING, 1302);

		return canvasToPngFile(canvas, 'xiaohongshu-text-' + page);
	}

	function roundRect(context, x, y, width, height, radius) {
		context.beginPath();
		context.moveTo(x + radius, y);
		context.lineTo(x + width - radius, y);
		context.quadraticCurveTo(x + width, y, x + width, y + radius);
		context.lineTo(x + width, y + height - radius);
		context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
		context.lineTo(x + radius, y + height);
		context.quadraticCurveTo(x, y + height, x, y + height - radius);
		context.lineTo(x, y + radius);
		context.quadraticCurveTo(x, y, x + radius, y);
		context.closePath();
	}

	function fitCanvasText(context, text, maxWidth) {
		text = String(text || '');

		while (text && context.measureText(text).width > maxWidth) {
			text = text.slice(0, -1);
		}

		return text;
	}

	function canvasToPngFile(canvas, name) {
		return new Promise(function(resolve) {
			canvas.toBlob(function(blob) {
				if (!blob) {
					resolve(null);
					return;
				}

				resolve(new window.File([blob], name + '.png', {
					type: 'image/png'
				}));
			}, 'image/png');
		});
	}

	function shareImageFile(root, imageUrl) {
		var title = rootData(root, 'data-share-title') || document.title;
		var sharePlan = buildXiaohongshuSharePlan(root);
		var videoUrls = contentVideoUrls();
		var text = sharePlan.caption;

		if (!imageUrl || !canTryFileShare()) {
			openShareImage(imageUrl);
			return;
		}

		fetchShareMediaFile(imageUrl, 'xiaohongshu-share', 'image/png').then(function(posterFile) {
			var imageUrls = contentImageUrls();
			var fetches = [];
			var i;

			for (i = 0; i < imageUrls.length; i++) {
				fetches.push(fetchOptionalShareImageFile(imageUrls[i], i + 1));
			}
			for (i = 0; i < videoUrls.length; i++) {
				fetches.push(fetchOptionalShareVideoFile(videoUrls[i], i + 1));
			}

			return Promise.all([
				sharePlan.useTextCards ? createTextCardFiles(root, sharePlan.fullText) : Promise.resolve([]),
				Promise.all(fetches)
			]).then(function(results) {
				var textFiles = results[0];
				var files = results[1];
				var imageFiles = [];
				var videoFiles = [];
				var mediaFiles;

				for (i = 0; i < files.length; i++) {
					if (!files[i]) {
						continue;
					}

					if (files[i].type.indexOf('video/') === 0) {
						videoFiles.push(files[i]);
					} else {
						imageFiles.push(files[i]);
					}
				}

				mediaFiles = [posterFile].concat(textFiles, imageFiles, videoFiles).filter(function(file) {
					return !!file;
				});

				return {
					all: mediaFiles,
					images: [posterFile].concat(textFiles, imageFiles),
					text: [posterFile].concat(textFiles),
					videos: videoFiles
				};
			});
		}).then(function(fileSets) {
			var sets = [fileSets.all];
			var payloads = [];
			var files;
			var i;

			if (fileSets.videos.length) {
				sets.push(fileSets.videos);
			}
			sets.push(fileSets.images);
			if (fileSets.text.length) {
				sets.push(fileSets.text);
			}

			for (i = 0; i < sets.length; i++) {
				files = sets[i];
				if (!files.length) {
					continue;
				}

				payloads.push({
					title: title,
					files: files
				});
				if (text) {
					payloads[payloads.length - 1].text = text;
				}
				payloads.push(
					{
						files: files
					}
				);
			}

			for (i = 0; i < payloads.length; i++) {
				if (navigator.canShare(payloads[i])) {
					return navigator.share(payloads[i]).catch(function() {
						// User cancellation is expected.
					});
				}
			}

			openShareImage(imageUrl);
			return;
		}).catch(function() {
			openShareImage(imageUrl);
		});
	}

	function handleImageShareClick(root, button) {
		var imageUrl = button.getAttribute('data-share-image-url') || rootData(root, 'data-share-image-url');
		var feedback = button.getAttribute('data-blog-share-feedback') || '';
		var sharePlan = buildXiaohongshuSharePlan(root);
		var copyPromise = sharePlan.caption ? copyText(sharePlan.caption) : Promise.resolve();

		copyPromise.then(function() {
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
