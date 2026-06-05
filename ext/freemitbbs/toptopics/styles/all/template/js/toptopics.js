(function($) {
	'use strict';

	var DISLIKE_FADE_CLASSES = 'toptopics-dislike-fade toptopics-dislike-fade-level-1 toptopics-dislike-fade-level-2 toptopics-dislike-fade-level-3 toptopics-dislike-fade-level-4';

	function getActionButton(postId, selector) {
		var $icon = $('#' + selector + 'img_' + postId);
		return $icon.closest('.button');
	}

	function setActionTitle($button, title) {
		var $icon;

		if (!$button.length || !title) {
			return;
		}

		$button.attr('title', title);
		$button.find('.sr-only').text(title);
		$icon = $button.find('[id^="likeimg_"], [id^="dislikeimg_"]').first();
		if ($icon.length) {
			$icon.attr('title', title);
		}
	}

	function setBlockedState($button, blocked) {
		var blockedTitle;
		var enabledTitle;

		if (!$button.length || !$button.is('a')) {
			return;
		}

		if (blocked) {
			if ($button.hasClass('toptopics-blocked')) {
				return;
			}

			$button.data('toptopicsEnabledTitle', $button.attr('title') || '');
			$button.addClass('toptopics-blocked');
			$button.attr('aria-disabled', 'true');
			$button.attr('tabindex', '-1');
			blockedTitle = $button.attr('data-blocked-title') || '';
			if (blockedTitle) {
				setActionTitle($button, blockedTitle);
			}
			return;
		}

		enabledTitle = $button.data('toptopicsEnabledTitle');
		$button.removeClass('toptopics-blocked');
		$button.removeAttr('aria-disabled');
		$button.removeAttr('tabindex');
		if (enabledTitle) {
			setActionTitle($button, enabledTitle);
		}
	}

	function syncReactionButtons(postId) {
		var $likeButton = getActionButton(postId, 'like');
		var $dislikeButton = $('#dislikebtn_' + postId);
		var liked = $('#likeimg_' + postId).hasClass('liked') && $likeButton.length;
		var disliked = $('#dislikeimg_' + postId).hasClass('toptopics-disliked') && $dislikeButton.length;

		if (liked && !disliked) {
			setBlockedState($likeButton, false);
			setBlockedState($dislikeButton, true);
			return;
		}

		if (disliked && !liked) {
			setBlockedState($dislikeButton, false);
			setBlockedState($likeButton, true);
			return;
		}

		setBlockedState($likeButton, false);
		setBlockedState($dislikeButton, false);
	}

	function syncAllReactionButtons() {
		var seen = {};

		$('[id^="likeimg_"], [id^="dislikeimg_"]').each(function() {
			var postId = this.id.replace(/^(?:likeimg_|dislikeimg_)/, '');
			if (!seen[postId]) {
				seen[postId] = true;
				syncReactionButtons(postId);
			}
		});
	}

	function syncCategoryForumMenus() {
		$('.toptopics-category-forum-menu').each(function() {
			var $menu = $(this);
			var $links = $menu.children('.toptopics-category-forum-link').not('.toptopics-category-forum-more');
			var $more = $menu.children('.toptopics-category-forum-more');

			$links.removeClass('toptopics-category-hidden-link');
			$more.removeClass('toptopics-category-forum-more-visible');

			if (!$links.length || !$more.length) {
				return;
			}

			if (this.scrollWidth <= this.clientWidth + 1) {
				return;
			}

			$more.addClass('toptopics-category-forum-more-visible');

			$($links.get().reverse()).each(function() {
				if ($menu[0].scrollWidth <= $menu[0].clientWidth + 1) {
					return false;
				}

				$(this).addClass('toptopics-category-hidden-link');
			});

			if ($menu[0].scrollWidth > $menu[0].clientWidth + 1) {
				$links.addClass('toptopics-category-hidden-link');
			}
		});
	}

	function updateDislikeFade(postId, fadeClass) {
		var $wrapper;
		var $content;

		postId = String(postId || '').replace(/[^\d]/g, '');
		fadeClass = fadeClass || '';
		if (!postId) {
			return;
		}

		$wrapper = $('#toptopics-dislike-fade_' + postId);
		if (!$wrapper.length) {
			if (!fadeClass) {
				return;
			}

			$content = $('#post_content' + postId).children('.content').first();
			if (!$content.length) {
				return;
			}

			$content.wrap('<div id="toptopics-dislike-fade_' + postId + '"></div>');
			$wrapper = $('#toptopics-dislike-fade_' + postId);
		}

		$wrapper.removeClass(DISLIKE_FADE_CLASSES);
		if (fadeClass) {
			$wrapper.addClass(fadeClass);
		}
	}

	function buildDisplayPostHref(postId) {
		var url;

		try {
			url = new URL(window.location.href);
			url.searchParams.set('p', postId);
			url.searchParams.set('view', 'show');
			url.hash = 'p' + postId;
			return url.href;
		} catch (e) {
			return '#p' + postId;
		}
	}

	function displayCollapsedPost(postId) {
		$('#post_content' + postId).show();
		$('#profile' + postId).show();
		$('#post_hidden' + postId).hide();
	}

	function updateCollapsedPost(postId, collapsed, message, displayTitle) {
		var $content;
		var $hidden;
		var $link;

		postId = String(postId || '').replace(/[^\d]/g, '');
		if (!postId) {
			return;
		}

		$hidden = $('#post_hidden' + postId + '.toptopics-post-hidden');
		if (!collapsed) {
			if ($hidden.length) {
				$hidden.remove();
				displayCollapsedPost(postId);
			}
			return;
		}

		$content = $('#post_content' + postId);
		if (!$content.length) {
			return;
		}

		if (!$hidden.length) {
			$hidden = $('<div/>', {
				'class': 'ignore toptopics-post-hidden',
				id: 'post_hidden' + postId
			}).insertBefore($content);
		}

		$link = $('<a/>', {
			'class': 'display_post toptopics-display-post',
			'data-post-id': postId,
			href: buildDisplayPostHref(postId)
		}).text(displayTitle || 'Display post');

		$hidden.empty().text(message || '').append('<br>').append($link).show();
		$content.hide();
		$('#profile' + postId).hide();
		$('#p' + postId).removeClass('online');
	}

	function updateDislikeUI(data) {
		var $icon = $('#dislikeimg_' + data.toggle_post);
		var $count = $('#dislike_' + data.toggle_post);
		var $button = $('#dislikebtn_' + data.toggle_post);

		if (!$icon.length || !$count.length) {
			return;
		}

		if (data.toggle_action === 'add') {
			$icon.removeClass('toptopics-dislike').addClass('toptopics-disliked');
		} else {
			$icon.removeClass('toptopics-disliked').addClass('toptopics-dislike');
		}

		if (typeof data.toggle_count !== 'undefined') {
			$count.text(data.toggle_count);
			if (typeof data.toggle_count_title !== 'undefined') {
				$count.attr('title', data.toggle_count_title);
			}
		}

		if (typeof data.toggle_fade_class !== 'undefined') {
			updateDislikeFade(data.toggle_post, data.toggle_fade_class);
		}

		if (typeof data.toggle_collapse !== 'undefined') {
			updateCollapsedPost(data.toggle_post, data.toggle_collapse, data.toggle_collapse_message, data.toggle_collapse_display_title);
		}

		if (data.toggle_title) {
			$icon.attr('title', data.toggle_title);
			$button.attr('title', data.toggle_title);
			$button.find('.sr-only').text(data.toggle_title);
			$button.data('toptopicsEnabledTitle', data.toggle_title);
		}

		if (data.next_action && $button.length) {
			$button.attr('href', $button.attr('href').replace(/\/toptopics\/(?:add|remove|toggle)\//, '/toptopics/' + data.next_action + '/'));
		}
	}

	function isPostingForm(form) {
		return form && (form.id === 'postform' || form.id === 'qr_postform');
	}

	function isPostSubmitter(submitter, form) {
		if (!submitter) {
			return $(form).find('input[type="submit"][name="post"], button[type="submit"][name="post"]').length > 0;
		}

		return submitter.name === 'post';
	}

	function preserveSubmitter(form, submitter) {
		var hidden;
		var value;

		if (form.querySelector('input[data-freemitbbs-post-submit="1"]')) {
			return;
		}

		value = submitter && typeof submitter.value !== 'undefined' ? submitter.value : '1';
		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'post';
		hidden.value = value;
		hidden.setAttribute('data-freemitbbs-post-submit', '1');
		form.appendChild(hidden);
	}

	function resetPostSubmitGuard(form) {
		if (!isPostingForm(form)) {
			return;
		}

		form.removeAttribute('data-freemitbbs-post-submitting');
		delete form.freemitbbsPostSubmitter;
		$(form)
			.find('input[data-freemitbbs-post-submit="1"]')
			.remove();
		$(form)
			.find('input[type="submit"][name="post"], button[type="submit"][name="post"], .default-submit-action')
			.prop('disabled', false)
			.removeAttr('aria-disabled');
	}

	function resetAllPostSubmitGuards() {
		$('form#postform, form#qr_postform').each(function() {
			resetPostSubmitGuard(this);
		});
	}

	function disablePostSubmitters(form) {
		$(form)
			.find('input[type="submit"][name="post"], button[type="submit"][name="post"], .default-submit-action')
			.prop('disabled', true)
			.attr('aria-disabled', 'true');
	}

	function installPostSubmitGuard() {
		document.addEventListener('click', function(event) {
			var submitter = event.target.closest('input[type="submit"], button[type="submit"]');
			var form;

			if (!submitter) {
				return;
			}

			form = submitter.form;
			if (isPostingForm(form)) {
				form.freemitbbsPostSubmitter = submitter;
			}
		}, true);

		document.addEventListener('submit', function(event) {
			var form = event.target;
			var submitter;

			if (event.defaultPrevented) {
				return;
			}

			if (!isPostingForm(form)) {
				return;
			}

			submitter = event.submitter || form.freemitbbsPostSubmitter || null;
			if (!isPostSubmitter(submitter, form)) {
				return;
			}

			if (form.getAttribute('data-freemitbbs-post-submitting') === '1') {
				event.preventDefault();
				event.stopImmediatePropagation();
				return;
			}

			form.setAttribute('data-freemitbbs-post-submitting', '1');
			preserveSubmitter(form, submitter);
			disablePostSubmitters(form);

			window.setTimeout(function() {
				if (document.contains(form)) {
					resetPostSubmitGuard(form);
				}
			}, 750);
		});

		window.addEventListener('pageshow', resetAllPostSubmitGuards);
	}

		var inlinePreviewCache = {};
		var inlinePreviewRequests = {};
		var inlinePreviewBatchQueue = [];
		var inlinePreviewBatchTimer = null;
		var inlinePreviewBatchDelay = 25;
		var inlinePreviewBatchMaxTopics = 40;
		var inlinePreviewInitIdleTimeout = 700;
		var inlinePreviewObserverRootMargin = '640px 0px 960px 0px';
		var inlinePreviewMaxImages = 8;
		var inlinePreviewTikTokFitHeight = 360;
		var inlinePreviewTextJoiner = '\u3000';
		var inlinePreviewRichMediaSelector = '[data-s9e-mediaembed], iframe, video, audio, object, embed, .inline-attachment, .attachbox, blockquote.twitter-tweet, .twitter-tweet, .twitter-tweet-rendered';

	function isInlinePreviewTwitterMedia($media) {
		return $media.is('[data-s9e-mediaembed="twitter"]')
			|| $media.is('iframe[src*="platform.twitter.com/embed/Tweet"]');
	}

	function isInlinePreviewTikTokMedia($media) {
		return $media.is('[data-s9e-mediaembed="tiktok"]');
	}

	function getInlinePreviewMediaHeight($media) {
		var media = $media.get(0);
		var height = 0;

		if (isInlinePreviewTikTokMedia($media)) {
			return inlinePreviewTikTokFitHeight;
		}

		if (media && media.style && media.style.height) {
			height = parseFloat(media.style.height);
		}

		if (!height && $media.is('iframe[data-s9e-mediaembed="youtube"], iframe[data-s9e-mediaembed="bilibili"]')) {
			height = $media.outerHeight(true) || ((parseFloat($media.outerWidth()) || 0) * 9 / 16) || 0;
		}

		height = height || parseFloat($media.attr('height')) || $media.outerHeight(true) || 0;

		if (!height && isInlinePreviewTwitterMedia($media)) {
			height = 350;
		} else if (!height && isInlinePreviewTikTokMedia($media)) {
			height = 700;
		} else if (!height && $media.is('video')) {
			height = 240;
		}

		return height;
	}

	function getInlinePreviewMediaWidth($media) {
		var media = $media.get(0);
		var width = 0;

		if (media && media.style && media.style.width) {
			width = parseFloat(media.style.width);
		}

		width = width || parseFloat($media.attr('width')) || 0;
		if (!width && isInlinePreviewTwitterMedia($media)) {
			width = 550;
		} else if (!width && isInlinePreviewTikTokMedia($media)) {
			width = 340;
		}

		return width || $media.outerWidth(true) || 0;
	}

	function fitInlinePreviewMedia($preview) {
		$preview.find('.toptopics-inline-preview-media-frame').each(function() {
			var $frame = $(this);
			var $media = $frame.children().first();
			var maxHeight = parseFloat($frame.css('max-height')) || 220;
			var maxWidth = $frame.innerWidth() || parseFloat($frame.css('width')) || 0;
			var mediaHeight = getInlinePreviewMediaHeight($media);
			var mediaWidth = getInlinePreviewMediaWidth($media);
			var fitKey;
			var ratio;

			if (!mediaHeight) {
				return;
			}

			ratio = Math.max(0.1, Math.min(1, maxHeight / mediaHeight, maxWidth && mediaWidth ? maxWidth / mediaWidth : 1));
			fitKey = maxWidth + ':' + mediaWidth + ':' + maxHeight + ':' + mediaHeight + ':' + ratio;

			if ($frame.data('toptopicsInlinePreviewMediaFitKey') === fitKey) {
				return;
			}

			$frame.data('toptopicsInlinePreviewMediaFitKey', fitKey);
			$frame.css({
				height: Math.max(1, Math.ceil(mediaHeight * ratio)) + 'px'
			});

			$media.css({
				height: mediaHeight + 'px',
				'max-height': 'none',
				transform: ratio < 1 ? 'scale(' + ratio + ')' : '',
				'transform-origin': ratio < 1 ? 'top center' : '',
				width: ratio < 1 && mediaWidth ? mediaWidth + 'px' : '',
				'max-width': ratio < 1 && mediaWidth ? 'none' : ''
			});
		});
	}

	function queueInlinePreviewMediaFit($preview) {
		var run;

		if (!$preview || !$preview.length || $preview.data('toptopicsInlinePreviewMediaFitPending')) {
			return;
		}

		$preview.data('toptopicsInlinePreviewMediaFitPending', true);
		run = function() {
			$preview.removeData('toptopicsInlinePreviewMediaFitPending');
			fitInlinePreviewMedia($preview);
		};

		if (window.requestAnimationFrame) {
			window.requestAnimationFrame(run);
		} else {
			setTimeout(run, 16);
		}
	}

	function watchInlinePreviewMediaFit($preview) {
		if (!window.MutationObserver) {
			return;
		}

		$preview.find('.toptopics-inline-preview-media-frame').each(function() {
			var $frame = $(this);
			var media = $frame.children().first().get(0);
			var observer;

			if (!media || $frame.data('toptopicsInlinePreviewMediaObserver')) {
				return;
			}

			observer = new MutationObserver(function() {
				queueInlinePreviewMediaFit($preview);
			});
			observer.observe(media, {
				attributes: true,
				attributeFilter: ['height', 'style', 'width']
			});
			$frame.data('toptopicsInlinePreviewMediaObserver', observer);
		});
	}

	function scheduleInlinePreviewMediaFit($preview) {
		fitInlinePreviewMedia($preview);
		watchInlinePreviewMediaFit($preview);
		setTimeout(function() {
			fitInlinePreviewMedia($preview);
		}, 250);
		setTimeout(function() {
			fitInlinePreviewMedia($preview);
		}, 1000);
	}

	function normalizeInlinePreviewUrl(url) {
		return (url || '').replace(/&amp;/g, '&');
	}

	function getInlinePreviewUrl($placeholder) {
		return normalizeInlinePreviewUrl($placeholder.attr('data-toptopics-inline-preview-url') || '');
	}

	function getInlinePreviewTopicId($placeholder) {
		var topicId = parseInt($placeholder.attr('data-toptopics-inline-preview-topic-id'), 10) || 0;
		var url;
		var match;

		if (topicId > 0) {
			return topicId;
		}

		url = getInlinePreviewUrl($placeholder);
		try {
			match = new URL(url, window.location.href).pathname.match(/\/(?:topicpreview|toptopics\/inline-preview)\/(\d+)$/);
		} catch (e) {
			match = (url || '').match(/\/(?:topicpreview|toptopics\/inline-preview)\/(\d+)(?:[?#]|$)/);
		}

		return match ? parseInt(match[1], 10) || 0 : 0;
	}

	function getInlinePreviewBatchUrl($placeholder) {
		var batchUrl = normalizeInlinePreviewUrl($placeholder.attr('data-toptopics-inline-preview-batch-url') || '');
		var url;
		var parsed;

		if (batchUrl) {
			return batchUrl;
		}

		url = getInlinePreviewUrl($placeholder);
		if (!url) {
			return '';
		}

		try {
			parsed = new URL(url, window.location.href);
			if (/\/topicpreview\/\d+$/.test(parsed.pathname)) {
				parsed.pathname = parsed.pathname.replace(/\/topicpreview\/\d+$/, '/topicpreview/batch');
				return parsed.href;
			}
			if (/\/toptopics\/inline-preview\/\d+$/.test(parsed.pathname)) {
				parsed.pathname = parsed.pathname.replace(/\/toptopics\/inline-preview\/\d+$/, '/toptopics/inline-preview/batch');
				return parsed.href;
			}
			return '';
		} catch (e) {
			return url
				.replace(/\/topicpreview\/\d+((?:[?#].*)?)$/, '/topicpreview/batch$1')
				.replace(/\/toptopics\/inline-preview\/\d+((?:[?#].*)?)$/, '/toptopics/inline-preview/batch$1');
		}
	}

	function buildInlinePreviewBatchRequestUrl(batchUrl, topicIds) {
		var parsed;
		var separator;

		try {
			parsed = new URL(batchUrl, window.location.href);
			parsed.searchParams.set('topic_ids', topicIds.join(','));
			return parsed.href;
		} catch (e) {
			separator = batchUrl.indexOf('?') === -1 ? '?' : '&';
			return batchUrl + separator + 'topic_ids=' + encodeURIComponent(topicIds.join(','));
		}
	}

	function getInlineTopicUrl($placeholder) {
		return $placeholder.attr('data-toptopics-topic-url') ||
			$placeholder.closest('li, tr').find('a.topictitle').first().attr('href') ||
			'#';
	}

	function isInlinePreviewMediaEnabled($placeholder) {
		return $placeholder.attr('data-toptopics-inline-media-preview') !== '0';
	}

	function getInlinePreviewFadeClass($placeholder) {
		var classes = ($placeholder.attr('class') || '').split(/\s+/);
		var fadeClasses = [];

		$.each(classes, function(index, className) {
			if (className.indexOf('toptopics-topic-dislike-fade') === 0) {
				fadeClasses.push(className);
			}
		});

		return fadeClasses.join(' ');
	}

	function isIgnoredInlinePreviewImage(url) {
		return /(?:^https?:\/\/fonts\.gstatic\.com\/s\/e\/notoemoji\/|\/(?:images\/)?smilies\/)/i.test(url || '');
	}

	function isTrustedUploadedImageUrl(url) {
		var parsed;
		var host;
		var currentHost;
		var rootHost;
		var path;

		if (!url) {
			return false;
		}

		try {
			parsed = new URL(url, window.location.href);
		} catch (e) {
			return false;
		}

		if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
			return false;
		}

		path = parsed.pathname || '';
		if (!/\.(?:jpe?g|png|gif|webp|avif)$/i.test(path)) {
			return false;
		}

		host = (parsed.hostname || '').toLowerCase();
		currentHost = (window.location.hostname || '').toLowerCase();
		rootHost = currentHost.replace(/^www\./, '');

		if (host === 'uploads.themitbbs.com') {
			return true;
		}

		if (rootHost && host === 'uploads.' + rootHost) {
			return true;
		}

		return (host === currentHost || host === rootHost || host === 'www.' + rootHost) &&
			/^\/(?:uploads?|videos?)\//i.test(path);
	}

	function createUploadedImageElement(url, alt) {
		return $('<img/>', {
			'class': 'postimage toptopics-uploaded-image',
			src: url,
			alt: alt || '',
			loading: 'lazy'
		}).get(0);
	}

	function removeLiteralImageBbcodeAround(node) {
		var previous = node ? node.previousSibling : null;
		var next = node ? node.nextSibling : null;

		if (previous && previous.nodeType === 3) {
			previous.nodeValue = previous.nodeValue.replace(/\[img\]\s*$/i, '');
		}

		if (next && next.nodeType === 3) {
			next.nodeValue = next.nodeValue.replace(/^\s*\[\/img\]/i, '');
		}
	}

	function collectUploadedImageTextNodes(root, textNodes) {
		var node = root ? root.firstChild : null;
		var tagName;

		while (node) {
			if (node.nodeType === 3) {
				if (/\[img\]/i.test(node.nodeValue || '')) {
					textNodes.push(node);
				}
			} else if (node.nodeType === 1) {
				tagName = (node.tagName || '').toLowerCase();
				if (tagName !== 'a' && tagName !== 'script' && tagName !== 'style' && tagName !== 'textarea') {
					collectUploadedImageTextNodes(node, textNodes);
				}
			}

			node = node.nextSibling;
		}
	}

	function replaceUploadedImageBbcodeTextNode(textNode) {
		var text = textNode.nodeValue || '';
		var pattern = /\[img\]\s*(https?:\/\/[^\s\[]+\.(?:jpe?g|png|gif|webp|avif)(?:[?#][^\s\[]*)?)\s*\[\/img\]/ig;
		var fragment;
		var match;
		var lastIndex = 0;
		var image;

		if (!pattern.test(text)) {
			return;
		}

		pattern.lastIndex = 0;
		fragment = document.createDocumentFragment();
		while ((match = pattern.exec(text)) !== null) {
			if (match.index > lastIndex) {
				fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
			}

			if (isTrustedUploadedImageUrl(match[1])) {
				image = createUploadedImageElement(match[1], '');
				fragment.appendChild(image);
			} else {
				fragment.appendChild(document.createTextNode(match[0]));
			}

			lastIndex = pattern.lastIndex;
		}

		if (lastIndex < text.length) {
			fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
		}

		textNode.parentNode.replaceChild(fragment, textNode);
	}

	function normalizeUploadedImageLinks($root) {
		var textNodes = [];

		if (!$root || !$root.length) {
			return;
		}

		$root.find('a').each(function() {
			var $link = $(this);
			var href = $link.attr('href') || '';
			var image;

			if ($link.find('img').length || !isTrustedUploadedImageUrl(href)) {
				return;
			}

			image = createUploadedImageElement(href, $.trim($link.text()));
			$link.replaceWith(image);
			removeLiteralImageBbcodeAround(image);
		});

		$root.each(function() {
			collectUploadedImageTextNodes(this, textNodes);
		});

		$.each(textNodes, function(index, textNode) {
			replaceUploadedImageBbcodeTextNode(textNode);
		});
	}

	function getFirstPreviewContent(html) {
		var $parsed = $('<div></div>').append($.parseHTML(html || '', document, false));
		var $content = $parsed.find('.topic_preview_first').first();

		if (!$content.length) {
			$content = $parsed.find('.topic_preview_content').first();
		}

		return $content;
	}

		function getInlinePreviewImageSrc(image) {
			return image.getAttribute('src') ||
				image.getAttribute('data-src') ||
				image.getAttribute('data-original') ||
				image.getAttribute('data-lazy-src') ||
				image.getAttribute('data-url') ||
				getFirstInlinePreviewSrcsetUrl(image.getAttribute('srcset') || image.getAttribute('data-srcset') || '') ||
				'';
		}

		function getFirstInlinePreviewSrcsetUrl(srcset) {
			var firstCandidate = $.trim((srcset || '').split(',')[0] || '');
			return firstCandidate ? firstCandidate.split(/\s+/)[0] : '';
		}

	function collectInlinePreviewImages($images, seen) {
		var images = [];

		$images.each(function() {
			var src = getInlinePreviewImageSrc(this);
			if (src && !isIgnoredInlinePreviewImage(src) && !seen[src]) {
				seen[src] = true;
				images.push($(this));
			}

			if (images.length >= inlinePreviewMaxImages) {
				return false;
			}
		});

		return images;
	}

		function findInlinePreviewImages($content) {
			var seen = {};
			var $explicitCandidates = $content.find('.toptopics-preview-image-candidates img');
			var candidateImages = collectInlinePreviewImages($explicitCandidates, seen);

			if (candidateImages.length > 1) {
				return candidateImages;
			}

			seen = {};
			return collectInlinePreviewImages($content.find('img').not('.toptopics-preview-image-candidates img'), seen);
		}

		function findInlinePreviewRichMedia($content, includeImages) {
			var selector = (includeImages ? 'img, ' : '') + inlinePreviewRichMediaSelector;

			return $content.find(selector).filter(function() {
				if (this.tagName && this.tagName.toLowerCase() === 'img') {
					return !isIgnoredInlinePreviewImage(getInlinePreviewImageSrc(this));
				}

				return true;
			}).first();
		}

	function buildInlinePreviewImageElement($image, deferred) {
		var src = getInlinePreviewImageSrc($image.get(0));
		var attrs = {
			alt: $image.attr('alt') || '',
			loading: 'lazy'
		};
		var $previewImage;

		if (deferred) {
			attrs['data-toptopics-src'] = src;
		} else {
			attrs.src = src;
		}

		if ($image.attr('srcset')) {
			attrs[deferred ? 'data-toptopics-srcset' : 'srcset'] = $image.attr('srcset');
		}
		if ($image.attr('sizes')) {
			attrs[deferred ? 'data-toptopics-sizes' : 'sizes'] = $image.attr('sizes');
		}

		$previewImage = $('<img/>', attrs);

		return $previewImage;
	}

	function buildInlinePreviewImagesFromUrls(imageUrls) {
		var images = [];
		var seen = {};

		$.each(imageUrls || [], function(index, url) {
			url = String(url || '');
			if (!url || isIgnoredInlinePreviewImage(url) || seen[url]) {
				return;
			}

			seen[url] = true;
			images.push($('<img/>', {
				src: url,
				alt: '',
				loading: 'lazy'
			}));

			if (images.length >= inlinePreviewMaxImages) {
				return false;
			}
		});

		return images;
	}

		function buildInlineImagePreview($placeholder, images) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var topicUrl = getInlineTopicUrl($placeholder);
		var previewClass = 'toptopics-inline-preview toptopics-inline-preview-image toptopics-inline-preview-carousel' +
			(images.length === 1 ? ' toptopics-inline-preview-single-image' : ' toptopics-inline-preview-multi-image');
		var $preview;
		var $track;
		var $controls;

		$preview = $('<div/>', {
			'class': previewClass + (fadeClass ? ' ' + fadeClass : ''),
			'data-toptopics-carousel-index': '0'
		});
		$track = $('<div/>', {
			'class': 'toptopics-inline-preview-carousel-track'
		});

		$.each(images, function(index, $image) {
			var $slide = $('<a/>', {
				'class': 'toptopics-inline-preview-carousel-slide' + (index === 0 ? ' toptopics-inline-preview-carousel-slide-active' : ''),
				href: topicUrl,
				'aria-hidden': index === 0 ? 'false' : 'true'
			});
			$track.append($slide.append(buildInlinePreviewImageElement($image, index !== 0)));
		});

		$preview.append($track);

		if (images.length > 1) {
			$controls = $('<div/>', {
				'class': 'toptopics-inline-preview-carousel-controls'
			}).append(
				$('<button/>', {
					type: 'button',
					'class': 'toptopics-inline-preview-carousel-button toptopics-inline-preview-carousel-button-prev',
					'data-toptopics-carousel-step': '-1',
					title: 'Previous image',
					'aria-label': 'Previous image'
				}).html('&#8249;'),
				$('<span/>', {
					'class': 'toptopics-inline-preview-carousel-count',
					text: '1 / ' + images.length
				}),
				$('<button/>', {
					type: 'button',
					'class': 'toptopics-inline-preview-carousel-button toptopics-inline-preview-carousel-button-next',
					'data-toptopics-carousel-step': '1',
					title: 'Next image',
					'aria-label': 'Next image'
				}).html('&#8250;')
			);

			$preview.append($controls);
		}

			return $preview;
		}

		function buildInlineRichMediaPreview($placeholder, $media) {
			var fadeClass = getInlinePreviewFadeClass($placeholder);
		var className = 'toptopics-inline-preview toptopics-inline-preview-media-box';
			var frameClass = 'toptopics-inline-preview-media-frame';
			var $preview;
			var $frame;
			var $mediaNode;

			if (!$media.length) {
				return $();
			}

			if ($media.is('img')) {
				return buildInlineImagePreview($placeholder, [$media]);
			}

			if (fadeClass) {
				className += ' ' + fadeClass;
			}

			$preview = $('<div/>', {
				'class': className
			});
			$mediaNode = $media.detach().removeAttr('width').removeAttr('height');
			if ($mediaNode.is('[data-s9e-mediaembed="youtube"], [data-s9e-mediaembed="bilibili"]')) {
				frameClass += ' toptopics-inline-preview-media-frame-youtube';
			} else if (isInlinePreviewTikTokMedia($mediaNode)) {
				frameClass += ' toptopics-inline-preview-media-frame-tiktok';
			}
			$mediaNode.find('script').remove();
			$frame = $('<div/>', {
				'class': frameClass
			});

		return $preview.append($frame.append($mediaNode));
	}

	function buildInlineStructuredMediaPreview($placeholder, $mediaNode) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var className = 'toptopics-inline-preview toptopics-inline-preview-media-box';
		var frameClass = 'toptopics-inline-preview-media-frame';
		var $preview;
		var $frame;

		if (!$mediaNode || !$mediaNode.length) {
			return $();
		}

		if ($mediaNode.is('[data-s9e-mediaembed="youtube"], [data-s9e-mediaembed="bilibili"], .toptopics-inline-preview-youtube-thumb')) {
			frameClass += ' toptopics-inline-preview-media-frame-youtube';
		} else if (isInlinePreviewTikTokMedia($mediaNode)) {
			frameClass += ' toptopics-inline-preview-media-frame-tiktok';
		}

		if (fadeClass) {
			className += ' ' + fadeClass;
		}

		$preview = $('<div/>', {
			'class': className
		});
		$frame = $('<div/>', {
			'class': frameClass
		});

		return $preview.append($frame.append($mediaNode));
	}

	function buildInlineVideoPreview($placeholder, url) {
		if (!url) {
			return $();
		}

		return buildInlineStructuredMediaPreview($placeholder, $('<video/>', {
			src: url,
			preload: 'metadata',
			controls: true,
			playsinline: 'playsinline',
			height: 220
		}));
	}

	function buildInlineYoutubePreview($placeholder, mediaId) {
		var topicUrl = getInlineTopicUrl($placeholder);
		var $thumbnail;

		mediaId = String(mediaId || '');
		if (!/^[A-Za-z0-9_-]{11}$/.test(mediaId)) {
			return $();
		}

		$thumbnail = $('<a/>', {
			'class': 'toptopics-inline-preview-youtube-thumb',
			href: topicUrl,
			'aria-label': 'YouTube video'
		}).append(
			$('<img/>', {
				src: 'https://i.ytimg.com/vi/' + encodeURIComponent(mediaId) + '/mqdefault.jpg',
				alt: '',
				loading: 'lazy'
			}),
			$('<span/>', {
				'class': 'toptopics-inline-preview-youtube-play',
				'aria-hidden': 'true'
			})
		);

		return buildInlineStructuredMediaPreview($placeholder, $thumbnail);
	}

	function buildInlineBilibiliPreview($placeholder, mediaId) {
		mediaId = String(mediaId || '');
		if (!/^BV[0-9A-Za-z]+$/.test(mediaId)) {
			return $();
		}

		return buildInlineStructuredMediaPreview($placeholder, $('<iframe/>', {
			src: 'https://player.bilibili.com/player.html?bvid=' + encodeURIComponent(mediaId) + '&autoplay=0',
			loading: 'lazy',
			frameborder: '0',
			scrolling: 'no',
			allowfullscreen: 'allowfullscreen',
			title: 'Bilibili video',
			width: 640,
			height: 360,
			'data-s9e-mediaembed': 'bilibili'
		}));
	}

	function getInlineTweetId(url) {
		var match = String(url || '').match(/\/status(?:es)?\/(\d+)/i);

		return match ? match[1] : '';
	}

	function buildInlineTweetPreview($placeholder, url, mediaId) {
		mediaId = mediaId || getInlineTweetId(url);
		if (!mediaId) {
			return $();
		}

		return buildInlineStructuredMediaPreview($placeholder, $('<iframe/>', {
			src: 'https://platform.twitter.com/embed/Tweet.html?id=' + encodeURIComponent(mediaId) + '&conversation=none&cards=hidden',
			loading: 'lazy',
			frameborder: '0',
			scrolling: 'no',
			title: 'Tweet',
			width: 550,
			height: 350,
			'data-s9e-mediaembed': 'twitter'
		}));
	}

	function buildInlineTikTokPreview($placeholder, mediaId) {
		mediaId = String(mediaId || '');
		if (!/^\d{6,}$/.test(mediaId)) {
			return $();
		}

		return buildInlineStructuredMediaPreview($placeholder, $('<iframe/>', {
			src: 'https://www.tiktok.com/embed/' + encodeURIComponent(mediaId),
			loading: 'lazy',
			frameborder: '0',
			scrolling: 'no',
			allowfullscreen: 'allowfullscreen',
			title: 'TikTok video',
			width: 340,
			height: 700,
			'data-s9e-mediaembed': 'tiktok'
		}));
	}

	function getInlinePreviewPlainText($content) {
		var $clone = $content.clone();

		$clone.find('script, style, noscript, img, iframe, video, audio, object, embed, [data-s9e-mediaembed], .inline-attachment, .attachbox').remove();
		$clone.find('br').replaceWith(inlinePreviewTextJoiner);
		$clone.find('p, div, li, blockquote, pre, table, tr, h1, h2, h3, h4, h5, h6').each(function() {
			$(this).before(inlinePreviewTextJoiner).after(inlinePreviewTextJoiner);
		});

		return $.trim($clone.text()).replace(/\s+/g, inlinePreviewTextJoiner);
	}

	function normalizeInlinePreviewPlainText(plainText) {
		return $.trim(String(plainText || '')).replace(/\s+/g, inlinePreviewTextJoiner);
	}

	function buildInlineTextPreviewFromText($placeholder, plainText, rich) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var className = 'toptopics-inline-preview toptopics-inline-preview-text' + (rich ? ' toptopics-inline-preview-rich' : '');
		var $preview;

		plainText = normalizeInlinePreviewPlainText(plainText);
		if (!plainText) {
			return $();
		}

		if (fadeClass) {
			className += ' ' + fadeClass;
		}

		$preview = $('<div/>', {
			'class': className
		});
		$preview.text(plainText);

		return $preview;
	}

	function buildInlineTextPreview($placeholder, $content, rich) {
		return buildInlineTextPreviewFromText($placeholder, getInlinePreviewPlainText($content), rich);
	}

	function buildInlineMixedPreview($placeholder, plainText, $mediaPreview) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var $preview;

		plainText = normalizeInlinePreviewPlainText(plainText);
		if (!plainText || !$mediaPreview || !$mediaPreview.length) {
			return $mediaPreview || $();
		}

		$preview = $('<div/>', {
			'class': 'toptopics-inline-preview toptopics-inline-preview-mixed' + (fadeClass ? ' ' + fadeClass : '')
		});

		return $preview.append(
			$('<div/>', {
				'class': 'toptopics-inline-preview-text toptopics-inline-preview-mixed-text',
				text: plainText
			}),
			$('<div/>', {
				'class': 'toptopics-inline-preview-mixed-media'
			}).append($mediaPreview)
		);
	}

	function buildInlinePreviewFromContent($placeholder, $content) {
		var plainText;
		var allowMedia = isInlinePreviewMediaEnabled($placeholder);
		var images;
		var $richMedia;
		var $mediaPreview;

		normalizeUploadedImageLinks($content);
		plainText = getInlinePreviewPlainText($content);

		if (!$content.length || !plainText && (allowMedia ? !$content.find('img, ' + inlinePreviewRichMediaSelector).length : true)) {
			return $();
		}

		if (!allowMedia) {
			return buildInlineTextPreview($placeholder, $content, false);
		}

		images = findInlinePreviewImages($content);
		if (images.length) {
			$mediaPreview = buildInlineImagePreview($placeholder, images);
			return buildInlineMixedPreview($placeholder, plainText, $mediaPreview);
		}

		$richMedia = findInlinePreviewRichMedia($content, false);
		if ($richMedia.length) {
			$mediaPreview = buildInlineRichMediaPreview($placeholder, $richMedia);
			return buildInlineMixedPreview($placeholder, plainText, $mediaPreview);
		}

		return buildInlineTextPreview($placeholder, $content, false);
	}

	function getInlinePreviewContentFromHtml(html) {
		var $content = getFirstPreviewContent(html);

		if (!$content.length) {
			$content = $('<div/>', {
				'class': 'topic_preview_content'
			}).append($.parseHTML(html || '', document, false));
		}

		return $content;
	}

	function buildInlineMediaPreviewFromContent($placeholder, $content) {
		var images;
		var $richMedia;

		if (!$content.length) {
			return $();
		}

		normalizeUploadedImageLinks($content);
		images = findInlinePreviewImages($content);
		if (images.length) {
			return buildInlineImagePreview($placeholder, images);
		}

		$richMedia = findInlinePreviewRichMedia($content, false);
		return $richMedia.length ? buildInlineRichMediaPreview($placeholder, $richMedia) : $();
	}

	function buildInlinePreviewFromData($placeholder, data) {
		var allowMedia = isInlinePreviewMediaEnabled($placeholder);
		var plainText;
		var imageUrls;
		var images;
		var $mediaPreview = $();
		var mediaType;
		var mediaUrl;
		var renderedHtml;

		if (!data || parseInt(data.status, 10) !== 200) {
			return $();
		}

		plainText = normalizeInlinePreviewPlainText(data.plain_text || '');
		imageUrls = $.isArray(data.image_urls) ? data.image_urls : [];
		if (!imageUrls.length && $.isArray(data.media_urls)) {
			imageUrls = data.media_urls;
		}

		if (allowMedia && imageUrls.length) {
			images = buildInlinePreviewImagesFromUrls(imageUrls);
			if (images.length) {
				$mediaPreview = buildInlineImagePreview($placeholder, images);
				return buildInlineMixedPreview($placeholder, plainText, $mediaPreview);
			}
		}

		renderedHtml = String(data.rendered_html || '');
		if (allowMedia && renderedHtml) {
			$mediaPreview = buildInlineMediaPreviewFromContent($placeholder, getInlinePreviewContentFromHtml(renderedHtml));
			if ($mediaPreview.length) {
				return buildInlineMixedPreview($placeholder, plainText, $mediaPreview);
			}
		}

		mediaType = String(data.media_type || '');
		mediaUrl = String(data.media_url || '');
		if (allowMedia && mediaUrl) {
			if (mediaType === 'video') {
				$mediaPreview = buildInlineVideoPreview($placeholder, mediaUrl);
			} else if (mediaType === 'youtube') {
				$mediaPreview = buildInlineYoutubePreview($placeholder, String(data.media_id || ''));
			} else if (mediaType === 'bilibili') {
				$mediaPreview = buildInlineBilibiliPreview($placeholder, String(data.media_id || ''));
			} else if (mediaType === 'tiktok') {
				$mediaPreview = buildInlineTikTokPreview($placeholder, String(data.media_id || ''));
			} else if (mediaType === 'tweet') {
				$mediaPreview = buildInlineTweetPreview($placeholder, mediaUrl, String(data.media_id || ''));
			}

			if ($mediaPreview.length) {
				return buildInlineMixedPreview($placeholder, plainText, $mediaPreview);
			}
		}

		return plainText ? buildInlineTextPreviewFromText($placeholder, plainText, false) : $();
	}

	function parseInlinePreviewPayload(payload) {
		if (payload && typeof payload === 'object') {
			return payload;
		}

		if (typeof payload !== 'string' || !/^\s*\{/.test(payload)) {
			return null;
		}

		try {
			return JSON.parse(payload);
		} catch (e) {
			return null;
		}
	}

	function buildInlinePreview($placeholder, payload) {
			var data = parseInlinePreviewPayload(payload);
			var $content;

			if (data) {
				return buildInlinePreviewFromData($placeholder, data);
			}

			$content = getFirstPreviewContent(payload || '');

			return buildInlinePreviewFromContent($placeholder, $content);
		}

	function isInlinePreviewSideMedia($preview) {
		return $preview.is('.toptopics-inline-preview-image, .toptopics-inline-preview-media-box, .toptopics-inline-preview-mixed');
	}

	function syncInlinePreviewSideMediaState($placeholder, $preview) {
		var $listInner = $placeholder.closest('.list-inner');
		var hasSideMedia;

		if (!$listInner.length) {
			return;
		}

		hasSideMedia = isInlinePreviewSideMedia($preview);
		$listInner
			.toggleClass('toptopics-inline-preview-side-media', hasSideMedia)
			.toggleClass('toptopics-inline-preview-has-mixed-media', hasSideMedia && $preview.is('.toptopics-inline-preview-mixed'));

		syncInlinePreviewMixedExcerptState($listInner);
	}

	function syncInlinePreviewMixedExcerptState($listInner) {
		var $title;
		var title;
		var styles;
		var lineHeight;
		var titleHeight;

		if (!$listInner || !$listInner.length || !$listInner.hasClass('toptopics-inline-preview-has-mixed-media')) {
			if ($listInner && $listInner.length) {
				$listInner.removeClass('toptopics-inline-preview-omit-excerpt');
			}
			return;
		}

		$listInner.removeClass('toptopics-inline-preview-omit-excerpt');
		$title = $listInner.children('a.topictitle').first();
		if (!$title.length) {
			return;
		}

		title = $title.get(0);
		styles = window.getComputedStyle ? window.getComputedStyle(title) : null;
		lineHeight = parseFloat(styles && styles.lineHeight) || parseFloat($title.css('line-height')) || 0;
		titleHeight = title && title.getBoundingClientRect ? title.getBoundingClientRect().height : $title.outerHeight();
		if (!lineHeight || !titleHeight) {
			return;
		}

		$listInner.toggleClass('toptopics-inline-preview-omit-excerpt', titleHeight / lineHeight > 1.35);
	}

	function syncExistingInlinePreviewSideMediaStates() {
		$('.list-inner').each(function() {
			var $listInner = $(this);
			var $preview = $listInner.children('.toptopics-inline-preview-image, .toptopics-inline-preview-media-box, .toptopics-inline-preview-mixed').first();
			var hasSideMedia = $preview.length > 0;

			$listInner
				.toggleClass('toptopics-inline-preview-side-media', hasSideMedia)
				.toggleClass('toptopics-inline-preview-has-mixed-media', hasSideMedia && $preview.is('.toptopics-inline-preview-mixed'));
			syncInlinePreviewMixedExcerptState($listInner);
		});
	}

		function normalizeInlineRichPreviews() {
			$('.toptopics-inline-preview-rich').not('.toptopics-inline-preview-media-box').each(function() {
				var $preview = $(this);
				var $replacement;

				$replacement = buildInlinePreviewFromContent($preview, $preview);

				if ($replacement && $replacement.length) {
					$preview.replaceWith($replacement);
					scheduleInlinePreviewMediaFit($replacement);
				}
			});
		}

		function normalizeInlineTextPreviews() {
			$('.toptopics-inline-preview-text').not('.toptopics-inline-preview-media-box').each(function() {
				var $preview = $(this);
				var plainText;

				plainText = getInlinePreviewPlainText($preview);
				if (!plainText) {
					$preview.remove();
					return;
				}

				$preview
					.removeClass('toptopics-inline-preview-rich')
					.text(plainText);
			});
		}

	function setInlinePreviewCarouselIndex($carousel, index) {
		var $slides = $carousel.find('.toptopics-inline-preview-carousel-slide');
		var count = $slides.length;
		var $activeSlide;

		if (!count) {
			return;
		}

		index = ((index % count) + count) % count;
		$activeSlide = $slides.eq(index);
		loadInlinePreviewCarouselImage($activeSlide);

		$carousel.attr('data-toptopics-carousel-index', index);
		$slides
			.removeClass('toptopics-inline-preview-carousel-slide-active')
			.attr('aria-hidden', 'true')
			.eq(index)
			.addClass('toptopics-inline-preview-carousel-slide-active')
			.attr('aria-hidden', 'false');
		$carousel.find('.toptopics-inline-preview-carousel-count').text((index + 1) + ' / ' + count);
	}

	function loadInlinePreviewCarouselImage($slide) {
		var $image = $slide.find('img').first();
		var src = $image.attr('data-toptopics-src');
		var srcset = $image.attr('data-toptopics-srcset');
		var sizes = $image.attr('data-toptopics-sizes');

		if (!src) {
			return;
		}

		$image.attr('src', src).removeAttr('data-toptopics-src');

		if (srcset) {
			$image.attr('srcset', srcset).removeAttr('data-toptopics-srcset');
		}
		if (sizes) {
			$image.attr('sizes', sizes).removeAttr('data-toptopics-sizes');
		}
	}

	function moveInlinePreviewCarousel(button, step) {
		var $carousel = $(button).closest('.toptopics-inline-preview-carousel');
		var current = parseInt($carousel.attr('data-toptopics-carousel-index'), 10) || 0;
		setInlinePreviewCarouselIndex($carousel, current + step);
	}

	function fetchInlinePreview(url) {
		if (Object.prototype.hasOwnProperty.call(inlinePreviewCache, url)) {
			return $.Deferred().resolve(inlinePreviewCache[url]).promise();
		}

		if (inlinePreviewRequests[url]) {
			return inlinePreviewRequests[url];
		}

		inlinePreviewRequests[url] = $.ajax({
			url: url,
			method: 'GET',
			dataType: 'text',
			cache: true
		}).done(function(response) {
			inlinePreviewCache[url] = response || '';
		}).fail(function() {
			inlinePreviewCache[url] = '';
		}).always(function() {
			delete inlinePreviewRequests[url];
		});

		return inlinePreviewRequests[url];
	}

	function renderLoadedInlineTopicPreview($placeholder, payload) {
		var $preview = buildInlinePreview($placeholder, payload || '');

		if ($preview.length) {
			syncInlinePreviewSideMediaState($placeholder, $preview);
			$placeholder.replaceWith($preview);
			if ($preview.find('.toptopics-inline-preview-media-frame').length) {
				scheduleInlinePreviewMediaFit($preview);
			}
			return;
		}

		$placeholder.closest('.list-inner').removeClass('toptopics-inline-preview-side-media toptopics-inline-preview-has-mixed-media toptopics-inline-preview-omit-excerpt');
		$placeholder.remove();
	}

	function loadInlineTopicPreviewFallback($placeholder, url) {
		fetchInlinePreview(url).always(function() {
			renderLoadedInlineTopicPreview($placeholder, inlinePreviewCache[url] || '');
		});
	}

	function applyInlinePreviewBatchResult(item, result) {
		var payload = result && result.status === 200 ? result : '';

		inlinePreviewCache[item.url] = payload;
		renderLoadedInlineTopicPreview(item.$placeholder, payload);
	}

	function requestInlinePreviewBatch(batchUrl, items) {
		var topicIds = [];
		var placeholdersByTopicId = {};
		var requestUrl;

		$.each(items, function(index, item) {
			if (!placeholdersByTopicId[item.topicId]) {
				placeholdersByTopicId[item.topicId] = [];
				topicIds.push(item.topicId);
			}

			placeholdersByTopicId[item.topicId].push(item);
		});

		requestUrl = buildInlinePreviewBatchRequestUrl(batchUrl, topicIds);
		$.ajax({
			url: requestUrl,
			method: 'GET',
			dataType: 'json',
			cache: true
		}).done(function(response) {
			$.each(placeholdersByTopicId, function(topicId, topicItems) {
				var result = response && response[topicId] ? response[topicId] : null;

				$.each(topicItems, function(index, item) {
					applyInlinePreviewBatchResult(item, result);
				});
			});
		}).fail(function() {
			$.each(items, function(index, item) {
				loadInlineTopicPreviewFallback(item.$placeholder, item.url);
			});
		});
	}

	function flushInlinePreviewBatchQueue() {
		var queue = inlinePreviewBatchQueue;
		var groups = {};

		inlinePreviewBatchQueue = [];
		inlinePreviewBatchTimer = null;

		$.each(queue, function(index, item) {
			var cacheKey = item.batchUrl;

			if (!item.$placeholder.closest('html').length) {
				return;
			}

			if (Object.prototype.hasOwnProperty.call(inlinePreviewCache, item.url)) {
				renderLoadedInlineTopicPreview(item.$placeholder, inlinePreviewCache[item.url] || '');
				return;
			}

			if (!groups[cacheKey]) {
				groups[cacheKey] = [];
			}
			groups[cacheKey].push(item);
		});

		$.each(groups, function(batchUrl, items) {
			for (var start = 0; start < items.length; start += inlinePreviewBatchMaxTopics) {
				requestInlinePreviewBatch(batchUrl, items.slice(start, start + inlinePreviewBatchMaxTopics));
			}
		});
	}

	function queueInlineTopicPreview($placeholder, url, batchUrl, topicId) {
		inlinePreviewBatchQueue.push({
			$placeholder: $placeholder,
			url: url,
			batchUrl: batchUrl,
			topicId: topicId
		});

		if (!inlinePreviewBatchTimer) {
			inlinePreviewBatchTimer = setTimeout(flushInlinePreviewBatchQueue, inlinePreviewBatchDelay);
		}
	}

	function loadInlineTopicPreview(placeholder) {
		var $placeholder = $(placeholder);
		var url = getInlinePreviewUrl($placeholder);
		var batchUrl;
		var topicId;

		if (!url || $placeholder.attr('data-toptopics-inline-preview-loaded') === '1') {
			return;
		}

		$placeholder.attr('data-toptopics-inline-preview-loaded', '1');
		if (Object.prototype.hasOwnProperty.call(inlinePreviewCache, url)) {
			renderLoadedInlineTopicPreview($placeholder, inlinePreviewCache[url] || '');
			return;
		}

		batchUrl = getInlinePreviewBatchUrl($placeholder);
		topicId = getInlinePreviewTopicId($placeholder);
		if (batchUrl && topicId > 0) {
			queueInlineTopicPreview($placeholder, url, batchUrl, topicId);
			return;
		}

		loadInlineTopicPreviewFallback($placeholder, url);
	}

	function initInlineTopicPreviews() {
		var placeholders = $('.toptopics-inline-preview-lazy').not('[data-toptopics-inline-preview-loaded="1"]');
		var observer;

		if (!placeholders.length) {
			return;
		}

		if (!('IntersectionObserver' in window)) {
			placeholders.each(function() {
				loadInlineTopicPreview(this);
			});
			return;
		}

		observer = new IntersectionObserver(function(entries) {
			$.each(entries, function(index, entry) {
				if (!entry.isIntersecting) {
					return;
				}

				observer.unobserve(entry.target);
				loadInlineTopicPreview(entry.target);
			});
		}, {
			root: null,
			rootMargin: inlinePreviewObserverRootMargin,
			threshold: 0
		});

		placeholders.each(function() {
			observer.observe(this);
		});
	}

	function scheduleInlineTopicPreviewsInit() {
		if ('requestIdleCallback' in window) {
			window.requestIdleCallback(initInlineTopicPreviews, {
				timeout: inlinePreviewInitIdleTimeout
			});
			return;
		}

		setTimeout(initInlineTopicPreviews, 150);
	}

	phpbb.addAjaxCallback('toggle_toptopics_dislike', function(data) {
		if (!data || data.error) {
			if (data && data.message && !data.MESSAGE_TITLE && typeof phpbb.alert === 'function') {
				phpbb.alert('', data.message);
			}
			return;
		}

		updateDislikeUI(data);
		syncReactionButtons(data.toggle_post);
	});

	window.freemitbbsToptopicsSyncReactionButtons = syncReactionButtons;

	document.addEventListener('click', function(event) {
		var blockedButton = event.target.closest('a.toptopics-blocked');
		if (blockedButton) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);

	document.addEventListener('click', function(event) {
		var carouselButton = event.target.closest('.toptopics-inline-preview-carousel-button');
		var displayLink = event.target.closest('a.toptopics-display-post');
		if (carouselButton) {
			event.preventDefault();
			event.stopPropagation();
			moveInlinePreviewCarousel(carouselButton, parseInt(carouselButton.getAttribute('data-toptopics-carousel-step'), 10) || 1);
			return;
		}

		if (displayLink) {
			event.preventDefault();
			displayCollapsedPost(displayLink.getAttribute('data-post-id'));
		}
	});

	document.addEventListener('keydown', function(event) {
		var blockedButton = event.target.closest('a.toptopics-blocked');
		var carousel = event.target.closest('.toptopics-inline-preview-carousel');
		if (blockedButton && (event.key === 'Enter' || event.key === ' ')) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}

		if (carousel && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
			event.preventDefault();
			moveInlinePreviewCarousel(carousel, event.key === 'ArrowLeft' ? -1 : 1);
		}
	}, true);

	$(function() {
		$('.post-buttons .toptopics-li').each(function() {
			var $li = $(this);
			var $postloveLi = $li.closest('.post-buttons').find('.postlove-li').first();
			if ($postloveLi.length) {
				$li.insertAfter($postloveLi);
			}
		});

			syncAllReactionButtons();
			syncCategoryForumMenus();
			installPostSubmitGuard();
			normalizeUploadedImageLinks($('.postbody .content, .topic_preview_first, .topic_preview_content, .toptopics-inline-preview-rich'));
			normalizeInlineRichPreviews();
			normalizeInlineTextPreviews();
			syncExistingInlinePreviewSideMediaStates();
			scheduleInlineTopicPreviewsInit();
			$(window).on('resize', function() {
				syncCategoryForumMenus();
				syncExistingInlinePreviewSideMediaStates();
			});
		});
})(jQuery);
