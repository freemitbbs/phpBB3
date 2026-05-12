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
		var displayLink = event.target.closest('a.toptopics-display-post');
		if (displayLink) {
			event.preventDefault();
			displayCollapsedPost(displayLink.getAttribute('data-post-id'));
		}
	});

	document.addEventListener('keydown', function(event) {
		var blockedButton = event.target.closest('a.toptopics-blocked');
		if (blockedButton && (event.key === 'Enter' || event.key === ' ')) {
			event.preventDefault();
			event.stopImmediatePropagation();
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
		$(window).on('resize', syncCategoryForumMenus);
	});
})(jQuery);
