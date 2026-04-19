(function($) {
	'use strict';

	function getStorageKey($panel) {
		return $panel.attr('data-storage-key') || 'vse.similartopics.collapsed';
	}

	function setPanelState($panel, collapsed) {
		var $button = $panel.find('.similartopics-collapse-btn').eq(0);
		var $icon = $button.find('i').eq(0);
		var label = collapsed
			? ($button.attr('data-title-expand') || '')
			: ($button.attr('data-title-collapse') || $button.attr('title') || '');

		if (!$button.length) {
			return;
		}

		$panel.toggleClass('is-collapsed', collapsed);
		$button.attr({
			'title': label,
			'aria-label': label,
			'aria-expanded': collapsed ? 'false' : 'true'
		});
		$button.find('.sr-only, .visually-hidden').text(label);
		$icon.toggleClass('fa-plus-square', collapsed);
		$icon.toggleClass('fa-minus-square', !collapsed);
	}

	$(function() {
		$('[data-similartopics-collapsible]').each(function() {
			var $panel = $(this);
			var $button = $panel.find('.similartopics-collapse-btn').eq(0);
			var storageKey = getStorageKey($panel);
			var collapsed = false;

			if (!$button.length) {
				return;
			}

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
					// Keep the in-page toggle working if browser storage is unavailable.
				}
			});
		});
	});
})(jQuery);
