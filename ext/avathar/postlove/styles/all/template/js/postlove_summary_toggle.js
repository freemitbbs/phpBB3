(function($) {
	'use strict';

	function getStorageKey($panel) {
		return $panel.attr('data-storage-key') || 'postlove.summary.collapsed';
	}

	function setPanelState($panel, collapsed) {
		var $content = $panel.find('.postlove-summary-content').eq(0);
		var $button = $panel.find('.postlove-collapse-btn').eq(0);
		var $icon = $button.find('i').eq(0);
		var label = collapsed ? ($button.data('titleExpand') || '') : ($button.data('titleCollapse') || '');

		if (!$content.length || !$button.length) {
			return;
		}

		$content.toggle(!collapsed);
		$button.attr('title', label);
		$button.attr('aria-label', label);
		$button.attr('aria-expanded', collapsed ? 'false' : 'true');
		$button.find('.sr-only').text(label);
		$icon.toggleClass('fa-plus-square', collapsed);
		$icon.toggleClass('fa-minus-square', !collapsed);
	}

	$(function() {
		$('.postlove-summary-panel').each(function() {
			var $panel = $(this);
			var $button = $panel.find('.postlove-collapse-btn').eq(0);
			var storageKey = getStorageKey($panel);
			var collapsed = false;

			if (!$button.length) {
				return;
			}

			$button.data('titleCollapse', $button.attr('title') || '');
			$button.data('titleExpand', $button.attr('data-title-expand') || '');

			try {
				collapsed = window.localStorage.getItem(storageKey) === '1';
			} catch (e) {
				collapsed = false;
			}

			setPanelState($panel, collapsed);
			$button.show();

			$button.on('click', function(event) {
				var nextCollapsed;

				event.preventDefault();
				nextCollapsed = $button.attr('aria-expanded') === 'true';
				setPanelState($panel, nextCollapsed);

				try {
					window.localStorage.setItem(storageKey, nextCollapsed ? '1' : '0');
				} catch (e) {
					// Ignore storage failures and keep the in-page toggle working.
				}
			});
		});
	});
})(jQuery);
