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
		var inlinePreviewMaxImages = 8;
		var inlinePreviewRichMediaSelector = '[data-s9e-mediaembed], iframe, video, audio, object, embed, .inline-attachment, .attachbox, blockquote.twitter-tweet, .twitter-tweet, .twitter-tweet-rendered';

	function getInlinePreviewUrl($placeholder) {
		return $placeholder.attr('data-toptopics-inline-preview-url') || '';
	}

	function getInlineTopicUrl($placeholder) {
		return $placeholder.attr('data-toptopics-topic-url') ||
			$placeholder.closest('li, tr').find('a.topictitle').first().attr('href') ||
			'#';
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

	function getFirstPreviewContent(html) {
		var $parsed = $('<div></div>').append($.parseHTML(html || '', document, true));
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

		function findInlinePreviewImages($content) {
			var images = [];
			var seen = {};

		$content.find('img').each(function() {
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

		function buildInlineImagePreview($placeholder, images) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var topicUrl = getInlineTopicUrl($placeholder);
		var previewClass = 'toptopics-inline-preview toptopics-inline-preview-image toptopics-inline-preview-carousel' +
			(images.length === 1 ? ' toptopics-inline-preview-single-image' : '');
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
			var $preview;
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
			$mediaNode.find('script').remove();

			return $preview.append($mediaNode);
		}

	function buildInlineTextPreview($placeholder, $content, rich) {
		var fadeClass = getInlinePreviewFadeClass($placeholder);
		var className = 'toptopics-inline-preview toptopics-inline-preview-text' + (rich ? ' toptopics-inline-preview-rich' : '');
		var $preview;

		if (fadeClass) {
			className += ' ' + fadeClass;
		}

		$preview = $('<div/>', {
			'class': className
		});
		$preview.html($content.html());

		return $preview;
	}

	function buildInlinePreview($placeholder, html) {
			var $content = getFirstPreviewContent(html);
			var images;
			var $richMedia;

			if (!$content.length || !$.trim($content.text()) && !$content.find('img, ' + inlinePreviewRichMediaSelector).length) {
				return $();
			}

		images = findInlinePreviewImages($content);
		if (images.length) {
			return buildInlineImagePreview($placeholder, images);
		}

			$richMedia = findInlinePreviewRichMedia($content, false);
			if ($richMedia.length) {
				return buildInlineRichMediaPreview($placeholder, $richMedia);
			}

			return buildInlineTextPreview($placeholder, $content, false);
		}

		function normalizeInlineRichPreviews() {
			$('.toptopics-inline-preview-rich').not('.toptopics-inline-preview-media-box').each(function() {
				var $preview = $(this);
				var images = findInlinePreviewImages($preview);
				var $replacement;
				var $richMedia;

				if (images.length) {
					$replacement = buildInlineImagePreview($preview, images);
				} else {
					$richMedia = findInlinePreviewRichMedia($preview, false);
					$replacement = buildInlineRichMediaPreview($preview, $richMedia);
				}

				if ($replacement && $replacement.length) {
					$preview.replaceWith($replacement);
				}
			});
		}

		function normalizePostContentMediaBoxes() {
			var selector = 'img.postimage, ' + inlinePreviewRichMediaSelector;

			$('.postbody .content').find(selector).each(function() {
				var $media = $(this);
				var $target = $media;
				var $link = $media.closest('a');
				var wrapperTag;

				if ($media.closest('.toptopics-inline-preview, .toptopics-post-media-box, .signature').length) {
					return;
				}

				if ($media.is('img') && isIgnoredInlinePreviewImage(getInlinePreviewImageSrc(this))) {
					return;
				}

				if ($link.length && $.trim($link.text()) === '' && $link.find(selector).length === 1) {
					$target = $link;
				}

				wrapperTag = $target.parent().is('p') ? 'span' : 'div';
				$target.wrap('<' + wrapperTag + ' class="toptopics-post-media-box"></' + wrapperTag + '>');
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
			dataType: 'html',
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

	function loadInlineTopicPreview(placeholder) {
		var $placeholder = $(placeholder);
		var url = getInlinePreviewUrl($placeholder);

		if (!url || $placeholder.attr('data-toptopics-inline-preview-loaded') === '1') {
			return;
		}

		$placeholder.attr('data-toptopics-inline-preview-loaded', '1');
		fetchInlinePreview(url).always(function() {
			var $preview = buildInlinePreview($placeholder, inlinePreviewCache[url] || '');

			if ($preview.length) {
				$placeholder.replaceWith($preview);
				return;
			}

			$placeholder.remove();
		});
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
			rootMargin: '450px 0px',
			threshold: 0
		});

		placeholders.each(function() {
			observer.observe(this);
		});
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
			normalizeInlineRichPreviews();
			normalizePostContentMediaBoxes();
			initInlineTopicPreviews();
			$(window).on('resize', syncCategoryForumMenus);
		});
})(jQuery);
