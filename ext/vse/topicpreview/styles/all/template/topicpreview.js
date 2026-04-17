/**
 * jQuery ToolTips for Topic Preview
 * https://github.com/iMattPro/topic_preview
 *
 * Copyright 2013, Matt Friedman
 * Licensed under the GPL Version 2 license.
 * http://www.opensource.org/licenses/GPL-2.0
 */

(function($) { // Avoid conflicts with other libraries

	'use strict';

	$.fn.topicPreview = function(options) {

		var settings = $.extend({
				dir: 'ltr',
				delay: 1000,
				width: 360,
				drift: 15,
				position: { left: 35, top: 25 }
			}, options),
			previewTimeout,
			hideTimeout,
			hoverToken = 0,
			activeTarget = null,
			previewCache = {},
			requestCache = {},
			previewContainer = $('<div id="topic_preview" class="topic_preview_container"></div>').css('width', settings.width).appendTo('body');

		// Do not allow delay times less than 300 ms to prevent tooltip madness
		settings.delay = Math.max(settings.delay, 300);

		var enhancePreviewContent = function(scope) {
			scope.find('.topic_preview_avatar')
				.toggleClass('rtl', (settings.dir === 'rtl'))
				.children('img')
				.brokenImage({})
			;
		};

		var getPreviewNode = function(obj) {
			return obj.closest('li, tr').find('.topic_preview_content').first();
		};

		var buildPreviewUrl = function(topicId) {
			if (!topicId || typeof window.topicPreviewPath === 'undefined') {
				return '';
			}

			return window.topicPreviewPath + topicId;
		};

		var getPreviewUrl = function(obj) {
			var previewNode = getPreviewNode(obj);
			return previewNode.data('topicPreviewUrl') || buildPreviewUrl(previewNode.data('topicPreviewId'));
		};

		var getInlinePreviewContent = function(obj) {
			var previewNode = getPreviewNode(obj);
			return previewNode.length ? (previewNode.html() || '') : '';
		};

		var extractPreviewContent = function(html) {
			var parsed = $('<div></div>').append($.parseHTML(html, document, true));
			var content = parsed.find('.topic_preview_content').first();
			return content.length ? content.html() : html;
		};

		var fetchPreviewContent = function(obj) {
			var url = getPreviewUrl(obj);
			var deferred;
			var inlineContent;

			if (!url) {
				inlineContent = getInlinePreviewContent(obj);
				return $.Deferred().resolve(inlineContent).promise();
			}

			if (Object.prototype.hasOwnProperty.call(previewCache, url)) {
				return $.Deferred().resolve(previewCache[url]).promise();
			}

			if (requestCache[url]) {
				return requestCache[url];
			}

			deferred = $.Deferred();
			requestCache[url] = deferred.promise();

			$.ajax({
				url: url,
				method: 'GET',
				dataType: 'html',
				cache: true
			}).done(function(response) {
				var content = extractPreviewContent(response);
				previewCache[url] = content;
				deferred.resolve(content);
			}).fail(function() {
				previewCache[url] = '';
				deferred.resolve('');
			}).always(function() {
				delete requestCache[url];
			});

			return requestCache[url];
		};

		var showPreviewContainer = function(obj, content) {
			previewContainer.html('<div class="topic_preview_scrollable">' + content + '</div>');
			previewContainer.find('img.postimage').removeClass('postimage');
			enhancePreviewContent(previewContainer);

			var pointerOffset = 8;
			var previewTop = obj.offset().top - previewContainer.outerHeight(true) - pointerOffset;
			previewContainer.toggleClass('invert', !topEdgeDetect(previewTop));
			previewTop = topEdgeDetect(previewTop) ? previewTop : obj.offset().top + settings.position.top;

			previewContainer
				.stop(true, true)
				.css({
					top: previewTop + 'px',
					left: obj.offset().left + settings.position.left + (settings.dir === 'rtl' ? (obj.width() - previewContainer.width()) : 0) + 'px'
				})
				.fadeIn('fast')
			;

			previewContainer
				.off('mouseenter mouseleave')
				.on('mouseenter', function() {
					if (hideTimeout) {
						clearTimeout(hideTimeout);
						hideTimeout = undefined;
					}
				})
				.on('mouseleave', function() {
					hideTopicPreview.call(obj);
				})
			;
		};

		// Display the topic preview tooltip
		var showTopicPreview = function() {
			var obj = $(this);
			var url = getPreviewUrl(obj);
			var inlineContent = '';

			if (!url) {
				inlineContent = getInlinePreviewContent(obj);
			}

			if (!url && !inlineContent) {
				return false;
			}

			// clear any existing timeouts
			if (previewTimeout) {
				clearTimeout(previewTimeout);
				previewTimeout = undefined;
			}
			if (hideTimeout) {
				clearTimeout(hideTimeout);
				hideTimeout = undefined;
			}

			activeTarget = obj.get(0);
			var token = ++hoverToken;

			// remove original titles to prevent overlap
			obj.removeAttr('title')
				.clearTitles('dt')
				.clearTitles('dl')
			;

			previewTimeout = setTimeout(function() {
				previewTimeout = undefined;

				fetchPreviewContent(obj).done(function(content) {
					if (!content || token !== hoverToken || activeTarget !== obj.get(0)) {
						return;
					}

					showPreviewContainer(obj, content);
				});
			}, settings.delay);
		};

		// Hide the topic preview tooltip
		var hideTopicPreview = function() {
			var obj = $(this);
			activeTarget = null;
			hoverToken++;

			// clear any existing timeouts
			if (previewTimeout) {
				clearTimeout(previewTimeout);
				previewTimeout = undefined;
			}

			// Add a small delay before hiding to allow mouse to move to tooltip
			hideTimeout = setTimeout(function() {
				hideTimeout = undefined;

				// Remove topic preview
				previewContainer
					.stop(true, true) // stop any running animations first
					.fadeOut('fast') // hide the topic preview with a fadeout
					.animate({
						top: '-=' + settings.drift + 'px'
					}, {
						duration: 'fast',
						queue: false,
						complete: function() {
							// animation complete
						}
					})
				;
				obj.restoreTitles('dt').restoreTitles('dl'); // reinstate original title attributes
			}, 100); // Small delay to allow mouse movement to tooltip
		};

		// Check if y coordinate is within 50 pixels of the bottom edge of a browser window
		// var bottomEdgeDetect = function(y) {
		// 	return (y >= ($(window).scrollTop() + $(window).height() - 50));
		// };

		// Check if y coordinate is within 50 pixels of the top edge of a browser window
		var topEdgeDetect = function(y) {
			return (y >= ($(window).scrollTop() + 50));
		};

		return this.each(function() {
			$(this)
				.on('mouseenter', showTopicPreview)
				.on('mouseleave', hideTopicPreview)
				.on('click', function() {
					// Remove the topic preview immediately on click as failsafe
					previewContainer.hide();
					activeTarget = null;
					hoverToken++;
					// clear any existing timeouts
					if (previewTimeout) {
						clearTimeout(previewTimeout);
						previewTimeout = undefined;
					}
					if (hideTimeout) {
						clearTimeout(hideTimeout);
						hideTimeout = undefined;
					}
				})
			;
		});
	};

	/*
	 * https://github.com/alexrabarts/jquery-brokenimage
	 * Licensed under the MIT: http://www.opensource.org/licenses/mit-license.php
	 */
	$.extend($.fn, {
		brokenImage: function(options) {
			var defaults = {
				timeout: 3000
			};

			options = $.extend(defaults, options);

			return this.each(function() {
				// Replace the image with a placeholder if:
				// a. loading fails with an error event or;
				// b. loading takes longer than timeout
				var image = this;

				$(image).on('error', function() {
					insertPlaceholder();
				});

				setTimeout(function() {
					// Check if the image failed to load with fallback for older browsers
					var isIncomplete = image.complete !== undefined ? !image.complete : false;
					var hasNoHeight = image.naturalHeight !== undefined ? image.naturalHeight === 0 : image.height === 0;
					if (isIncomplete || hasNoHeight) {
						insertPlaceholder();
					}
				}, options.timeout);

				function insertPlaceholder() {
					$(image).replaceWith('<div class="topic_preview_no_avatar"></div>');
				}
			});
		},
		clearTitles: function(el) {
			return this.each(function() {
				var $obj = $(this).closest(el);
				var title = $obj.attr('title');
				if (typeof title !== typeof undefined && title !== false) {
					$obj.data('title', title).removeAttr('title');
				}
			});
		},
		restoreTitles: function(el) {
			return this.each(function() {
				var $obj = $(this).closest(el);
				$obj.attr('title', $obj.data('title'));
			});
		}
	});

})(jQuery); // Avoid conflicts with other libraries

jQuery(function($) {
	'use strict';

	if (typeof $.fn.topicPreview !== 'function') {
		return;
	}

	if (typeof window.topicPreviewOptions === 'undefined') {
		return;
	}

	var targets = [];

	$('.topic_preview_content').each(function() {
		var title = $(this).closest('li, tr').find('.topictitle').first();
		if (title.length) {
			targets.push(title.get(0));
		}
	});

	if (!targets.length) {
		return;
	}

	$(Array.from(new Set(targets))).topicPreview(window.topicPreviewOptions);
});
