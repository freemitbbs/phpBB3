(function ()
{
	'use strict';

	var assetUrlPattern = window.MODERNSMILEY_ASSET_URL_PATTERN || '';
	var fallbackAssetUrlPattern = window.MODERNSMILEY_FALLBACK_ASSET_URL_PATTERN || '';
	var hoverAssetUrlPattern = window.MODERNSMILEY_HOVER_ASSET_URL_PATTERN || '';
	var hoverFallbackAssetUrlPattern = window.MODERNSMILEY_HOVER_FALLBACK_ASSET_URL_PATTERN || '';
	var smileyMap = window.MODERNSMILEY_CODE_MAP || {};

	function buildAssetUrl(pattern, sequence)
	{
		return pattern.replace('{seq}', sequence);
	}

	function applyFallback(img, fallbackUrl)
	{
		if (!fallbackUrl)
		{
			img.onerror = null;
			img.removeAttribute('onerror');
			return;
		}

		img.onerror = function ()
		{
			this.onerror = null;
			this.removeAttribute('onerror');
			this.src = fallbackUrl;
		};
	}

	function bindHover(img)
	{
		if (!img || img.dataset.modernsmileyHoverBound === '1')
		{
			return;
		}

		var hoverSrc = img.getAttribute('data-modernsmiley-hover-src') || '';
		if (!hoverSrc)
		{
			return;
		}

		var staticSrc = img.getAttribute('src') || '';
		var staticFallback = img.getAttribute('data-modernsmiley-static-fallback-src') || '';
		var hoverFallback = img.getAttribute('data-modernsmiley-hover-fallback-src') || '';

		img.addEventListener('mouseenter', function ()
		{
			this.src = hoverSrc;
			applyFallback(this, hoverFallback);
		});

		img.addEventListener('mouseleave', function ()
		{
			this.src = staticSrc;
			applyFallback(this, staticFallback);
		});

		img.dataset.modernsmileyHoverBound = '1';
	}

	function getSmileyCode(img)
	{
		var alt = (img.getAttribute('alt') || '').trim();
		if (alt)
		{
			return alt;
		}

		return (img.getAttribute('title') || '').trim();
	}

	function getSequenceFromSrc(img)
	{
		var src = img.getAttribute('src') || '';
		var match = src.match(/[?&]modernsmiley=([0-9a-f-]+)/i);

		return match ? match[1].toLowerCase() : '';
	}

	function replaceSmiley(img)
	{
		if (!img || img.dataset.modernsmileyApplied === '1')
		{
			return;
		}

		var sequence = getSequenceFromSrc(img);
		if (!sequence)
		{
			var code = getSmileyCode(img);
			sequence = code ? smileyMap[code] : '';
		}

		if (!sequence)
		{
			bindHover(img);
			return;
		}

		var staticFallbackUrl = fallbackAssetUrlPattern ? buildAssetUrl(fallbackAssetUrlPattern, sequence) : '';
		var hoverUrl = hoverAssetUrlPattern ? buildAssetUrl(hoverAssetUrlPattern, sequence) : '';
		var hoverFallbackUrl = hoverFallbackAssetUrlPattern ? buildAssetUrl(hoverFallbackAssetUrlPattern, sequence) : '';

		img.src = buildAssetUrl(assetUrlPattern, sequence);
		applyFallback(img, staticFallbackUrl);
		if (staticFallbackUrl)
		{
			img.setAttribute('data-modernsmiley-static-fallback-src', staticFallbackUrl);
		}
		if (hoverUrl)
		{
			img.setAttribute('data-modernsmiley-hover-src', hoverUrl);
		}
		if (hoverFallbackUrl)
		{
			img.setAttribute('data-modernsmiley-hover-fallback-src', hoverFallbackUrl);
		}
		img.classList.add('emoji');
		img.draggable = false;
		img.dataset.modernsmileyApplied = '1';
		bindHover(img);
	}

	function scan(root)
	{
		if (!root || !root.querySelectorAll)
		{
			return;
		}

		root.querySelectorAll('img.smilies, img[src*="images/smilies/"], img[data-modernsmiley-hover-src]').forEach(replaceSmiley);
	}

	if (document.readyState === 'loading')
	{
		document.addEventListener('DOMContentLoaded', function ()
		{
			scan(document);
		});
	}
	else
	{
		scan(document);
	}

	new MutationObserver(function (mutations)
	{
		mutations.forEach(function (mutation)
		{
			mutation.addedNodes.forEach(function (node)
			{
				if (node.nodeType !== 1)
				{
					return;
				}

				if (node.matches && (node.matches('img.smilies') || node.matches('img[src*="images/smilies/"]') || node.matches('img[data-modernsmiley-hover-src]')))
				{
					replaceSmiley(node);
				}

				scan(node);
			});
		});
	}).observe(document.documentElement, { childList: true, subtree: true });
})();
