(function () {
	'use strict';

	var POST_IMAGE_SELECTOR = '.postbody .content img, .postbody .attachbox img.postimage';
	var ZOOM_STEP = 1.25;
	var MIN_SCALE = 0.1;
	var MAX_SCALE = 8;
	var viewer = null;
	var sourceImage = null;
	var sourceUrl = '';
	var fitScale = 1;
	var currentScale = 1;
	var previousFocus = null;

	function isPostImage(image) {
		if (!image || image.nodeType !== 1 || image.tagName !== 'IMG' || !image.matches(POST_IMAGE_SELECTOR)) {
			return false;
		}

		if (image.classList.contains('smilies') ||
			image.classList.contains('emoji') ||
			image.classList.contains('modernsmiley-emoji') ||
			image.hasAttribute('data-modernsmiley-hover-src') ||
			image.closest('[data-s9e-mediaembed], .post-reactions-container') ||
			image.getAttribute('data-image-viewer') === 'off') {
			return false;
		}

		return !!(image.currentSrc || image.getAttribute('src'));
	}

	function imageFromTarget(target) {
		if (!target || target.nodeType !== 1) {
			return null;
		}

		if (target.tagName === 'IMG') {
			return isPostImage(target) ? target : null;
		}

		if (target.tagName === 'A') {
			var linkedImage = target.querySelector(POST_IMAGE_SELECTOR);
			return isPostImage(linkedImage) ? linkedImage : null;
		}

		return null;
	}

	function decorateImage(image) {
		if (!isPostImage(image) || image.getAttribute('data-image-viewer-ready') === '1') {
			return;
		}

		image.setAttribute('data-image-viewer-ready', '1');
		image.classList.add('freemitbbs-image-zoomable');

		if (!image.closest('a[href]')) {
			image.setAttribute('role', 'button');
			image.setAttribute('tabindex', '0');
			image.setAttribute('aria-label', image.alt ? '查看大图：' + image.alt : '查看大图');
		}
	}

	function decorateImages(root) {
		if (!root || root.nodeType !== 1 && root.nodeType !== 9) {
			return;
		}

		if (root.nodeType === 1 && root.tagName === 'IMG') {
			decorateImage(root);
		}

		Array.prototype.forEach.call(root.querySelectorAll(POST_IMAGE_SELECTOR), decorateImage);
	}

	function button(className, label, text) {
		var element = document.createElement('button');
		element.type = 'button';
		element.className = className;
		element.setAttribute('aria-label', label);
		element.title = label;
		element.textContent = text;
		return element;
	}

	function createViewer() {
		var overlay = document.createElement('div');
		overlay.className = 'freemitbbs-image-viewer';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', '帖子大图');
		overlay.hidden = true;

		var toolbar = document.createElement('div');
		toolbar.className = 'freemitbbs-image-viewer-toolbar';

		var zoomOut = button('freemitbbs-image-viewer-button viewer-zoom-out', '缩小', '−');
		var zoomLevel = document.createElement('span');
		zoomLevel.className = 'freemitbbs-image-viewer-level';
		zoomLevel.setAttribute('aria-live', 'polite');
		var zoomIn = button('freemitbbs-image-viewer-button viewer-zoom-in', '放大', '+');
		var actualSize = button('freemitbbs-image-viewer-button viewer-actual-size', '切换原始大小', '1:1');
		var original = document.createElement('a');
		original.className = 'freemitbbs-image-viewer-button viewer-open-original';
		original.target = '_blank';
		original.rel = 'noopener noreferrer';
		original.textContent = '原图';
		original.setAttribute('aria-label', '在新窗口打开原图');
		original.title = '在新窗口打开原图';
		var close = button('freemitbbs-image-viewer-button viewer-close', '关闭大图', '×');

		toolbar.appendChild(zoomOut);
		toolbar.appendChild(zoomLevel);
		toolbar.appendChild(zoomIn);
		toolbar.appendChild(actualSize);
		toolbar.appendChild(original);
		toolbar.appendChild(close);

		var viewport = document.createElement('div');
		viewport.className = 'freemitbbs-image-viewer-viewport';
		var canvas = document.createElement('div');
		canvas.className = 'freemitbbs-image-viewer-canvas';
		var image = document.createElement('img');
		image.className = 'freemitbbs-image-viewer-image';
		image.alt = '';
		image.draggable = false;
		canvas.appendChild(image);
		viewport.appendChild(canvas);

		overlay.appendChild(viewport);
		overlay.appendChild(toolbar);
		document.body.appendChild(overlay);

		viewer = {
			overlay: overlay,
			toolbar: toolbar,
			viewport: viewport,
			canvas: canvas,
			image: image,
			zoomOut: zoomOut,
			zoomLevel: zoomLevel,
			zoomIn: zoomIn,
			actualSize: actualSize,
			original: original,
			close: close
		};

		zoomOut.addEventListener('click', function () {
			setScale(currentScale / ZOOM_STEP);
		});
		zoomIn.addEventListener('click', function () {
			setScale(currentScale * ZOOM_STEP);
		});
		actualSize.addEventListener('click', toggleActualSize);
		close.addEventListener('click', closeViewer);
		image.addEventListener('click', toggleActualSize);
		image.addEventListener('load', fitImage);
		image.addEventListener('error', function () {
			overlay.classList.remove('is-loading');
			overlay.classList.add('has-error');
			zoomLevel.textContent = '图片加载失败';
		});
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay || event.target === viewport || event.target === canvas) {
				closeViewer();
			}
		});

		return viewer;
	}

	function clampScale(scale) {
		return Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale));
	}

	function setScale(scale) {
		if (!viewer || !viewer.image.naturalWidth) {
			return;
		}

		currentScale = clampScale(scale);
		viewer.image.style.width = Math.round(viewer.image.naturalWidth * currentScale) + 'px';
		viewer.image.style.height = 'auto';
		viewer.zoomLevel.textContent = Math.round(currentScale * 100) + '%';
		viewer.zoomOut.disabled = currentScale <= MIN_SCALE;
		viewer.zoomIn.disabled = currentScale >= MAX_SCALE;
		viewer.image.classList.toggle('is-actual-size', currentScale > fitScale + 0.01);
	}

	function calculateFitScale() {
		if (!viewer || !viewer.image.naturalWidth || !viewer.image.naturalHeight) {
			return 1;
		}

		var availableWidth = Math.max(100, viewer.viewport.clientWidth - 48);
		var availableHeight = Math.max(100, viewer.viewport.clientHeight - 96);

		return Math.min(1, availableWidth / viewer.image.naturalWidth, availableHeight / viewer.image.naturalHeight);
	}

	function fitImage() {
		if (!viewer || viewer.overlay.hidden || !viewer.image.naturalWidth) {
			return;
		}

		viewer.overlay.classList.remove('is-loading', 'has-error');
		fitScale = calculateFitScale();
		setScale(fitScale);
		viewer.viewport.scrollLeft = 0;
		viewer.viewport.scrollTop = 0;
	}

	function toggleActualSize() {
		if (!viewer || !viewer.image.naturalWidth) {
			return;
		}

		setScale(Math.abs(currentScale - 1) < 0.01 ? fitScale : 1);
	}

	function openViewer(image) {
		if (!isPostImage(image)) {
			return;
		}

		if (!viewer) {
			createViewer();
		}

		sourceImage = image;
		sourceUrl = image.currentSrc || image.getAttribute('src') || '';
		if (!sourceUrl) {
			return;
		}

		previousFocus = document.activeElement;
		viewer.overlay.hidden = false;
		viewer.overlay.classList.add('is-loading');
		viewer.overlay.classList.remove('has-error');
		viewer.original.href = sourceUrl;
		viewer.image.alt = image.alt || '';
		viewer.image.removeAttribute('src');
		viewer.image.src = sourceUrl;
		document.body.classList.add('freemitbbs-image-viewer-open');
		viewer.close.focus();

		if (viewer.image.complete && viewer.image.naturalWidth) {
			fitImage();
		}
	}

	function closeViewer() {
		if (!viewer || viewer.overlay.hidden) {
			return;
		}

		viewer.overlay.hidden = true;
		viewer.overlay.classList.remove('is-loading', 'has-error');
		viewer.image.removeAttribute('src');
		viewer.image.style.width = '';
		document.body.classList.remove('freemitbbs-image-viewer-open');

		if (previousFocus && typeof previousFocus.focus === 'function') {
			previousFocus.focus();
		}

		sourceImage = null;
		sourceUrl = '';
		previousFocus = null;
	}

	function handleImageActivation(event) {
		if (event.type === 'click' && (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
			return;
		}

		var image = imageFromTarget(event.target);
		if (!image) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		openViewer(image);
	}

	function handleKeydown(event) {
		if (viewer && !viewer.overlay.hidden) {
			if (event.key === 'Escape') {
				event.preventDefault();
				closeViewer();
				return;
			}
			if (event.key === '+' || event.key === '=') {
				event.preventDefault();
				setScale(currentScale * ZOOM_STEP);
				return;
			}
			if (event.key === '-') {
				event.preventDefault();
				setScale(currentScale / ZOOM_STEP);
				return;
			}
			if (event.key === '0') {
				event.preventDefault();
				fitImage();
			}
			return;
		}

		if ((event.key === 'Enter' || event.key === ' ') && isPostImage(event.target)) {
			handleImageActivation(event);
		}
	}

	function observeNewImages() {
		if (!window.MutationObserver) {
			return;
		}

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				Array.prototype.forEach.call(mutation.addedNodes, function (node) {
					if (node.nodeType === 1) {
						decorateImages(node);
					}
				});
			});
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}

	function init() {
		decorateImages(document);
		document.addEventListener('click', handleImageActivation, true);
		document.addEventListener('keydown', handleKeydown, true);
		window.addEventListener('resize', function () {
			if (viewer && !viewer.overlay.hidden && Math.abs(currentScale - fitScale) < 0.01) {
				fitImage();
			}
		});
		observeNewImages();

		window.freemitbbsPostImageViewer = {
			isPostImage: isPostImage,
			open: openViewer,
			close: closeViewer
		};
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
