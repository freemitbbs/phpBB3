(function () {
	'use strict';

	var root = document.querySelector('.freemitbbs-header-prefs');
	if (!root) {
		return;
	}

	var keys = {
		theme: 'freemitbbs.headerPrefs.theme',
		topicList: 'freemitbbs.headerPrefs.topicList'
	};
	var themes = {
		prosilver_fm: root.getAttribute('data-header-prefs-gold-style-id') || '',
		prosilver_fm_cool: root.getAttribute('data-header-prefs-cool-style-id') || ''
	};
	var validTopicLists = {
		classic: true,
		enhanced: true
	};
	var isAnonymous = root.getAttribute('data-header-prefs-anonymous') === '1';

	function storageGet(key) {
		try {
			return window.localStorage ? window.localStorage.getItem(key) : '';
		} catch (e) {
			return '';
		}
	}

	function storageSet(key, value) {
		try {
			if (window.localStorage && value) {
				window.localStorage.setItem(key, value);
			}
		} catch (e) {
		}
	}

	function normalizeTheme(theme) {
		return Object.prototype.hasOwnProperty.call(themes, theme) && themes[theme] ? theme : '';
	}

	function normalizeTopicList(topicList) {
		return Object.prototype.hasOwnProperty.call(validTopicLists, topicList) ? topicList : '';
	}

	function currentTheme() {
		return normalizeTheme(root.getAttribute('data-header-prefs-current-theme') || '');
	}

	function currentTopicList() {
		return normalizeTopicList(root.getAttribute('data-header-prefs-current-topic-list') || '') || 'classic';
	}

	function currentReturnUrl() {
		return window.location.pathname + window.location.search + window.location.hash;
	}

	function getQueryParam(url, name) {
		var queryStart = url.indexOf('?');
		if (queryStart === -1) {
			return '';
		}

		var hashStart = url.indexOf('#', queryStart);
		var query = url.substring(queryStart + 1, hashStart === -1 ? url.length : hashStart);
		var pairs = query ? query.split('&') : [];
		for (var i = 0; i < pairs.length; i += 1) {
			var pair = pairs[i].split('=');
			if (decodeURIComponent(pair[0].replace(/\+/g, ' ')) === name) {
				return decodeURIComponent((pair[1] || '').replace(/\+/g, ' '));
			}
		}

		return '';
	}

	function setQueryParam(url, name, value) {
		var hash = '';
		var hashIndex = url.indexOf('#');
		if (hashIndex !== -1) {
			hash = url.substring(hashIndex);
			url = url.substring(0, hashIndex);
		}

		var query = '';
		var queryIndex = url.indexOf('?');
		if (queryIndex !== -1) {
			query = url.substring(queryIndex + 1);
			url = url.substring(0, queryIndex);
		}

		var encodedName = encodeURIComponent(name);
		var encodedValue = encodeURIComponent(value);
		var pairs = query ? query.split('&') : [];
		var output = [];
		var replaced = false;

		for (var i = 0; i < pairs.length; i += 1) {
			if (!pairs[i]) {
				continue;
			}

			var pairName = pairs[i].split('=')[0];
			if (decodeURIComponent(pairName.replace(/\+/g, ' ')) === name) {
				if (!replaced) {
					output.push(encodedName + '=' + encodedValue);
					replaced = true;
				}
			} else {
				output.push(pairs[i]);
			}
		}

		if (!replaced) {
			output.push(encodedName + '=' + encodedValue);
		}

		return url + (output.length ? '?' + output.join('&') : '') + hash;
	}

	function updateActiveButtons(theme, topicList) {
		var themeButtons = root.querySelectorAll('[data-header-pref-theme]');
		for (var i = 0; i < themeButtons.length; i += 1) {
			themeButtons[i].classList.toggle('active', themeButtons[i].getAttribute('data-header-pref-theme') === theme);
		}

		var topicButtons = root.querySelectorAll('[data-header-pref-topic-list]');
		for (var j = 0; j < topicButtons.length; j += 1) {
			topicButtons[j].classList.toggle('active', topicButtons[j].getAttribute('data-header-pref-topic-list') === topicList);
		}
	}

	function go(theme, topicList) {
		var url = root.getAttribute('data-header-prefs-url') || '';
		if (!url) {
			return;
		}

		url = setQueryParam(url, 'theme', theme);
		url = setQueryParam(url, 'topic_list', topicList);
		url = setQueryParam(url, 'return', currentReturnUrl());
		url = setQueryParam(url, 'hash', root.getAttribute('data-header-prefs-hash') || '');
		window.location.href = url;
	}

	root.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('.freemitbbs-header-pref-toggle') : event.target;
		if (!button || !root.contains(button)) {
			return;
		}

		var selectedTheme = normalizeTheme(button.getAttribute('data-header-pref-theme') || '') || normalizeTheme(storageGet(keys.theme)) || currentTheme();
		var selectedTopicList = normalizeTopicList(button.getAttribute('data-header-pref-topic-list') || '') || normalizeTopicList(storageGet(keys.topicList)) || currentTopicList();

		if (isAnonymous) {
			storageSet(keys.theme, selectedTheme);
			storageSet(keys.topicList, selectedTopicList);
		}

		updateActiveButtons(selectedTheme, selectedTopicList);
		go(selectedTheme, selectedTopicList);
	});

	if (isAnonymous) {
		var storedTheme = normalizeTheme(storageGet(keys.theme));
		var storedTopicList = normalizeTopicList(storageGet(keys.topicList));
		var nextUrl = currentReturnUrl();
		var changed = false;

		if (storedTheme && themes[storedTheme] && getQueryParam(nextUrl, 'style') !== String(themes[storedTheme])) {
			nextUrl = setQueryParam(nextUrl, 'style', themes[storedTheme]);
			changed = true;
		}

		if (storedTopicList && getQueryParam(nextUrl, 'toptopics_view') !== storedTopicList) {
			nextUrl = setQueryParam(nextUrl, 'toptopics_view', storedTopicList);
			changed = true;
		}

		if (changed && nextUrl !== currentReturnUrl()) {
			window.location.replace(nextUrl);
			return;
		}

		updateActiveButtons(storedTheme || currentTheme(), storedTopicList || currentTopicList());
	}
}());
