(function($) {
	'use strict';

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

	phpbb.addAjaxCallback('toggle_toptopics_dislike', function(data) {
		if (!data || data.error) {
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
		$(window).on('resize', syncCategoryForumMenus);
	});
})(jQuery);
