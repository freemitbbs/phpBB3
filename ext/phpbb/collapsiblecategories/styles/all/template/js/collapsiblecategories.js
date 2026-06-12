(function($) { // Avoid conflicts with other libraries

	'use strict';

	var storagePrefix = 'phpbb_collapsiblecategories_';

	// Get the collapsible element (has class .topiclist.forums OR .collapsible)
	$.fn.getCollapsible = function() {
		return this.closest('.forabg').find('.topiclist.forums, .collapsible').eq(0);
	};

	function isHiddenValue(value) {
		return value === true || value === '1' || value === 'true';
	}

	function getCollapseId($button) {
		var href = $button.attr('href') || '',
			match = href.match(/\/collapse\/([^?&#/]+)/);

		return match ? decodeURIComponent(match[1]) : '';
	}

	function getStoredHidden(collapseId) {
		if (!collapseId || !window.localStorage) {
			return null;
		}

		try {
			var value = window.localStorage.getItem(storagePrefix + collapseId);
			return value === null ? null : value === '1';
		} catch (e) {
			return null;
		}
	}

	function setStoredHidden(collapseId, hidden) {
		if (!collapseId || !window.localStorage) {
			return;
		}

		try {
			window.localStorage.setItem(storagePrefix + collapseId, hidden ? '1' : '0');
		} catch (e) {}
	}

	function swapButtonState($button) {
		var oldTitle = $button.attr('title'),
			newTitle = $button.attr('data-title-alt');

		$button
			.attr({
				'title': newTitle,
				'data-title-alt': oldTitle
			})
			.find('i')
			.toggleClass('fa-plus-square fa-minus-square');
	}

	function setCollapseState($button, hidden, animate) {
		var currentHidden = isHiddenValue($button.attr('data-collapse-hidden')),
			$content = $button.getCollapsible();

		if (!$content.length) {
			return;
		}

		if (currentHidden !== hidden) {
			swapButtonState($button);
		}

		$button.attr('data-collapse-hidden', hidden ? '1' : '0');
		$content.stop(true, true);
		if (animate) {
			hidden ? $content.slideUp('fast') : $content.slideDown('fast');
		} else {
			hidden ? $content.hide() : $content.show();
		}
	}

	function persistCollapseState($button) {
		var href = ($button.attr('href') || '').replace(/&amp;/g, '&');
		if (!href) {
			return;
		}

		$.ajax({
			url: href,
			type: 'GET',
			cache: false,
			global: false
		}).fail($.noop);
	}

	function handleCollapseClick(button, event) {
		var $button = $(button),
			collapseId = getCollapseId($button),
			hidden = !isHiddenValue($button.attr('data-collapse-hidden'));

		event.preventDefault();
		event.stopPropagation();
		if (event.stopImmediatePropagation) {
			event.stopImmediatePropagation();
		}

		setCollapseState($button, hidden, true);
		setStoredHidden(collapseId, hidden);
		persistCollapseState($button);
	}

	$('a.collapse-btn').each(function() {
		var $this = $(this),
			collapseId = getCollapseId($this),
			storedHidden = getStoredHidden(collapseId),
			initialHidden = isHiddenValue($this.attr('data-hidden')),
			hidden = storedHidden === null ? initialHidden : storedHidden,
			$content = $this.getCollapsible();

		// Return if no collapsible content could be found
		if (!$content.length) {
			return;
		}

		// Unhide the collapse buttons (makes them JS dependent)
		$this
			.attr({
				'data-ajax': 'false',
				'data-collapse-hidden': initialHidden ? '1' : '0'
			})
			.show();

		setCollapseState($this, hidden, false);
	});

	phpbb.addAjaxCallback('phpbb_collapse', function(res) {
		if (res.success) {
			setCollapseState($(this), !isHiddenValue($(this).attr('data-collapse-hidden')), true);
		}
	});

	document.addEventListener('click', function(event) {
		var button = $(event.target).closest('a.collapse-btn')[0];
		if (button) {
			handleCollapseClick(button, event);
		}
	}, true);

})(jQuery); // Avoid conflicts with other libraries
