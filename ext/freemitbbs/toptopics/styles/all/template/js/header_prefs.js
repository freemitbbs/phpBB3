(function () {
	'use strict';

	var root = document.querySelector('.freemitbbs-header-prefs');
	if (!root) {
		return;
	}

	var keys = {
		theme: 'freemitbbs.headerPrefs.theme',
		topicList: 'freemitbbs.headerPrefs.topicList',
		homeLayout: 'freemitbbs.headerPrefs.homeLayout'
	};
	var themes = {
		prosilver_fm: root.getAttribute('data-header-prefs-gold-style-id') || '',
		prosilver_fm_cool: root.getAttribute('data-header-prefs-cool-style-id') || '',
		prosilver_se: root.getAttribute('data-header-prefs-gray-style-id') || ''
	};
	var validTopicLists = {
		classic: true,
		enhanced: true,
		compact: true
	};
	var validHomeLayouts = {
		split: true,
		merged: true
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
		if (topicList === 'flat') {
			return 'classic';
		}

		return Object.prototype.hasOwnProperty.call(validTopicLists, topicList) ? topicList : '';
	}

	function normalizeHomeLayout(homeLayout) {
		return Object.prototype.hasOwnProperty.call(validHomeLayouts, homeLayout) ? homeLayout : '';
	}

	function currentTheme() {
		return normalizeTheme(root.getAttribute('data-header-prefs-current-theme') || '');
	}

	function currentTopicList() {
		return normalizeTopicList(root.getAttribute('data-header-prefs-current-topic-list') || '') || 'classic';
	}

	function currentHomeLayout() {
		return normalizeHomeLayout(root.getAttribute('data-header-prefs-current-home-layout') || '') || 'split';
	}

	function currentReturnUrl() {
		return window.location.pathname + window.location.search + window.location.hash;
	}

	function isFormWorkflowPage() {
		var path = window.location.pathname || '';

		return /(?:^|\/)(?:ucp|posting|mcp)\.php$/i.test(path);
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

	function removeQueryParams(url, names) {
		var hash = '';
		var hashIndex = url.indexOf('#');
		var query = '';
		var queryIndex;
		var pairs;
		var output = [];
		var nameMap = {};

		for (var i = 0; i < names.length; i += 1) {
			nameMap[names[i]] = true;
		}

		if (hashIndex !== -1) {
			hash = url.substring(hashIndex);
			url = url.substring(0, hashIndex);
		}

		queryIndex = url.indexOf('?');
		if (queryIndex === -1) {
			return url + hash;
		}

		query = url.substring(queryIndex + 1);
		url = url.substring(0, queryIndex);
		pairs = query ? query.split('&') : [];
		for (var j = 0; j < pairs.length; j += 1) {
			var pairName = pairs[j].split('=')[0];
			if (!nameMap[decodeURIComponent(pairName.replace(/\+/g, ' '))]) {
				output.push(pairs[j]);
			}
		}

		return url + (output.length ? '?' + output.join('&') : '') + hash;
	}

	function updateActiveButtons(theme, topicList, homeLayout) {
		var themeButtons = root.querySelectorAll('[data-header-pref-theme]');
		for (var i = 0; i < themeButtons.length; i += 1) {
			themeButtons[i].classList.toggle('active', themeButtons[i].getAttribute('data-header-pref-theme') === theme);
		}

		var topicButtons = root.querySelectorAll('[data-header-pref-topic-list]');
		for (var j = 0; j < topicButtons.length; j += 1) {
			topicButtons[j].classList.toggle('active', topicButtons[j].getAttribute('data-header-pref-topic-list') === topicList);
		}

		var homeButtons = root.querySelectorAll('[data-header-pref-home-layout]');
		for (var k = 0; k < homeButtons.length; k += 1) {
			homeButtons[k].classList.toggle('active', homeButtons[k].getAttribute('data-header-pref-home-layout') === homeLayout);
		}
	}

	function go(theme, topicList, homeLayout) {
		var url = root.getAttribute('data-header-prefs-url') || '';
		if (!url) {
			return;
		}

		url = setQueryParam(url, 'theme', theme);
		url = setQueryParam(url, 'topic_list', topicList);
		url = setQueryParam(url, 'home_layout', homeLayout);
		url = setQueryParam(url, 'return', currentReturnUrl());
		url = setQueryParam(url, 'hash', root.getAttribute('data-header-prefs-hash') || '');
		window.location.href = url;
	}

	root.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('.freemitbbs-header-pref-toggle') : event.target;
		var clickedTheme;
		var clickedTopicList;
		var clickedHomeLayout;
		var selectedTheme;
		var selectedTopicList;
		var selectedHomeLayout;

		if (!button || !root.contains(button)) {
			return;
		}

		clickedTheme = normalizeTheme(button.getAttribute('data-header-pref-theme') || '');
		clickedTopicList = normalizeTopicList(button.getAttribute('data-header-pref-topic-list') || '');
		clickedHomeLayout = normalizeHomeLayout(button.getAttribute('data-header-pref-home-layout') || '');
		selectedTheme = clickedTheme || (isAnonymous ? normalizeTheme(storageGet(keys.theme)) : currentTheme());
		selectedTopicList = clickedTopicList || (isAnonymous ? normalizeTopicList(storageGet(keys.topicList)) : currentTopicList());
		selectedHomeLayout = clickedHomeLayout || (isAnonymous ? normalizeHomeLayout(storageGet(keys.homeLayout)) : currentHomeLayout());

		if (isAnonymous) {
			storageSet(keys.theme, selectedTheme);
			storageSet(keys.topicList, selectedTopicList);
			storageSet(keys.homeLayout, selectedHomeLayout);
		}

		updateActiveButtons(selectedTheme, selectedTopicList, selectedHomeLayout);
		go(selectedTheme, selectedTopicList, selectedHomeLayout);
	});

	if (!isAnonymous) {
		var cleanUrl = removeQueryParams(currentReturnUrl(), ['style', 'toptopics_view', 'toptopics_home']);
		if (cleanUrl !== currentReturnUrl()) {
			window.location.replace(cleanUrl);
			return;
		}
	}

	if (isAnonymous && !isFormWorkflowPage()) {
		var storedTheme = normalizeTheme(storageGet(keys.theme));
		var storedTopicListRaw = storageGet(keys.topicList);
		var storedTopicList = normalizeTopicList(storedTopicListRaw);
		var storedHomeLayout = normalizeHomeLayout(storageGet(keys.homeLayout));
		var nextUrl = currentReturnUrl();
		var changed = false;

		if (storedTopicListRaw === 'flat') {
			storedTopicList = 'classic';
			storedHomeLayout = storedHomeLayout || 'merged';
			storageSet(keys.topicList, storedTopicList);
			storageSet(keys.homeLayout, storedHomeLayout);
		}

		if (storedTheme && themes[storedTheme] && getQueryParam(nextUrl, 'style') !== String(themes[storedTheme])) {
			nextUrl = setQueryParam(nextUrl, 'style', themes[storedTheme]);
			changed = true;
		}

		if (storedTopicList && getQueryParam(nextUrl, 'toptopics_view') !== storedTopicList) {
			nextUrl = setQueryParam(nextUrl, 'toptopics_view', storedTopicList);
			changed = true;
		}

		if (storedHomeLayout && getQueryParam(nextUrl, 'toptopics_home') !== storedHomeLayout) {
			nextUrl = setQueryParam(nextUrl, 'toptopics_home', storedHomeLayout);
			changed = true;
		}

		if (changed && nextUrl !== currentReturnUrl()) {
			window.location.replace(nextUrl);
			return;
		}

		updateActiveButtons(storedTheme || currentTheme(), storedTopicList || currentTopicList(), storedHomeLayout || currentHomeLayout());
	}
}());
