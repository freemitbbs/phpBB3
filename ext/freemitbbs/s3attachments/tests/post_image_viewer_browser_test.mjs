const debugUrl = process.env.CHROME_DEBUG_URL || 'http://127.0.0.1:9223';
const topicUrl = process.env.PHPBB_TOPIC_URL || 'http://127.0.0.1:8090/viewtopic.php?t=2173';

function delay(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

async function findTopicTarget() {
	for (let attempt = 0; attempt < 30; attempt += 1) {
		const targets = await fetch(`${debugUrl}/json`).then((response) => response.json());
		const target = targets.find((candidate) => candidate.type === 'page' && candidate.url === topicUrl);
		if (target) {
			return target;
		}
		await delay(100);
	}

	throw new Error(`Chrome target not found for ${topicUrl}`);
}

function connect(target) {
	return new Promise((resolve, reject) => {
		const socket = new WebSocket(target.webSocketDebuggerUrl);
		let nextId = 1;
		const pending = new Map();

		socket.addEventListener('open', () => {
			resolve({
				call(method, params = {}) {
					return new Promise((callResolve, callReject) => {
						const id = nextId;
						nextId += 1;
						pending.set(id, { resolve: callResolve, reject: callReject });
						socket.send(JSON.stringify({ id, method, params }));
					});
				},
				close() {
					socket.close();
				},
			});
		});
		socket.addEventListener('message', (event) => {
			const message = JSON.parse(event.data);
			if (!message.id || !pending.has(message.id)) {
				return;
			}

			const promise = pending.get(message.id);
			pending.delete(message.id);
			if (message.error) {
				promise.reject(new Error(message.error.message));
				return;
			}
			promise.resolve(message.result);
		});
		socket.addEventListener('error', reject);
	});
}

async function evaluate(client, expression) {
	const result = await client.call('Runtime.evaluate', {
		expression,
		returnByValue: true,
		awaitPromise: true,
	});
	if (result.exceptionDetails) {
		throw new Error(result.exceptionDetails.text || 'Browser evaluation failed');
	}

	return result.result.value;
}

async function waitFor(client, expression, label) {
	for (let attempt = 0; attempt < 50; attempt += 1) {
		if (await evaluate(client, expression)) {
			return;
		}
		await delay(100);
	}

	throw new Error(`Timed out waiting for ${label}`);
}

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

const target = await findTopicTarget();
const client = await connect(target);

try {
	await client.call('Page.reload', { ignoreCache: true });
	await delay(200);
	await waitFor(
		client,
		'document.readyState === "complete" && !!window.freemitbbsPostImageViewer',
		'image viewer initialization',
	);

	const initial = await evaluate(client, `(() => {
		const images = Array.from(document.querySelectorAll('.postbody .content img.postimage'));
		const first = images[0];
		return {
			count: images.length,
			ready: images.filter((image) => image.dataset.imageViewerReady === '1').length,
			url: location.href,
			firstSource: first ? (first.currentSrc || first.src) : '',
			firstLink: first && first.closest('a') ? first.closest('a').href : '',
		};
	})()`);
	assert(initial.count > 0, 'Test topic has no post images');
	assert(initial.ready === initial.count, 'Not all existing post images were decorated');

	const linkedClick = await evaluate(client, `(() => {
		const image = document.querySelector('.postbody .content img.postimage');
		const before = location.href;
		const notCancelled = image.dispatchEvent(new MouseEvent('click', {
			bubbles: true,
			cancelable: true,
			button: 0,
			view: window,
		}));
		const overlay = document.querySelector('.freemitbbs-image-viewer');
		return {
			notCancelled,
			before,
			after: location.href,
			visible: !!overlay && !overlay.hidden,
			original: overlay ? overlay.querySelector('.viewer-open-original').href : '',
			bodyLocked: document.body.classList.contains('freemitbbs-image-viewer-open'),
		};
	})()`);
	assert(linkedClick.notCancelled === false, 'Linked image click was not cancelled');
	assert(linkedClick.before === linkedClick.after, 'Linked image navigated away instead of opening viewer');
	assert(linkedClick.visible, 'Image viewer did not open for a linked post image');
	assert(linkedClick.original === initial.firstSource, 'Original-image link does not use the displayed image URL');
	assert(linkedClick.bodyLocked, 'Page scrolling was not locked while viewer was open');

	await evaluate(client, `document.dispatchEvent(new KeyboardEvent('keydown', {
		key: 'Escape',
		bubbles: true,
		cancelable: true,
	}))`);
	assert(
		await evaluate(client, 'document.querySelector(".freemitbbs-image-viewer").hidden'),
		'Escape did not close the viewer',
	);

	await evaluate(client, `(() => {
		const content = document.querySelector('.postbody .content');
		const image = document.createElement('img');
		image.id = 'viewer-dynamic-image';
		image.alt = 'Dynamic Markdown image';
		image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
			'<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1200">' +
			'<rect width="1600" height="1200" fill="%23457b9d"/></svg>'
		);
		content.appendChild(image);

		const smiley = document.createElement('img');
		smiley.id = 'viewer-smiley';
		smiley.className = 'postimage smilies';
		smiley.src = image.src;
		content.appendChild(smiley);

		const attachbox = document.createElement('div');
		attachbox.className = 'attachbox';
		const attachment = document.createElement('img');
		attachment.id = 'viewer-attachment';
		attachment.className = 'postimage';
		attachment.src = image.src;
		attachment.onclick = function () {
			window.__legacyAttachmentClick = true;
		};
		attachbox.appendChild(attachment);
		document.querySelector('.postbody').appendChild(attachbox);
	})()`);
	await waitFor(
		client,
		'document.querySelector("#viewer-dynamic-image").dataset.imageViewerReady === "1" && document.querySelector("#viewer-attachment").dataset.imageViewerReady === "1"',
		'dynamic image decoration',
	);

	const exclusions = await evaluate(client, `({
		dynamicReady: document.querySelector('#viewer-dynamic-image').dataset.imageViewerReady,
		attachmentReady: document.querySelector('#viewer-attachment').dataset.imageViewerReady,
		smileyReady: document.querySelector('#viewer-smiley').dataset.imageViewerReady || '',
		smileyAccepted: window.freemitbbsPostImageViewer.isPostImage(document.querySelector('#viewer-smiley')),
	})`);
	assert(exclusions.dynamicReady === '1', 'Dynamic Markdown image was not decorated');
	assert(exclusions.attachmentReady === '1', 'Dynamic attachment image was not decorated');
	assert(exclusions.smileyReady === '', 'Smiley was incorrectly decorated');
	assert(exclusions.smileyAccepted === false, 'Smiley was incorrectly accepted by viewer');

	await evaluate(client, `document.querySelector('#viewer-dynamic-image').dispatchEvent(new MouseEvent('click', {
		bubbles: true,
		cancelable: true,
		button: 0,
		view: window,
	}))`);
	await waitFor(
		client,
		'document.querySelector(".freemitbbs-image-viewer-image").naturalWidth === 1600',
		'dynamic image load',
	);
	const beforeZoom = await evaluate(client, `(() => {
		const overlay = document.querySelector('.freemitbbs-image-viewer');
		return {
			visible: !overlay.hidden,
			level: overlay.querySelector('.freemitbbs-image-viewer-level').textContent,
			width: parseInt(overlay.querySelector('.freemitbbs-image-viewer-image').style.width, 10),
		};
	})()`);
	await evaluate(client, 'document.querySelector(".viewer-zoom-in").click()');
	const afterZoom = await evaluate(client, `(() => {
		const overlay = document.querySelector('.freemitbbs-image-viewer');
		return {
			level: overlay.querySelector('.freemitbbs-image-viewer-level').textContent,
			width: parseInt(overlay.querySelector('.freemitbbs-image-viewer-image').style.width, 10),
		};
	})()`);
	assert(beforeZoom.visible, 'Dynamic image did not open in viewer');
	assert(afterZoom.width > beforeZoom.width, 'Zoom-in button did not enlarge the image');
	assert(afterZoom.level !== beforeZoom.level, 'Zoom level did not update');

	await evaluate(client, 'document.querySelector(".viewer-actual-size").click()');
	const actualSize = await evaluate(client, `(() => {
		const overlay = document.querySelector('.freemitbbs-image-viewer');
		const toolbarRect = overlay.querySelector('.freemitbbs-image-viewer-toolbar').getBoundingClientRect();
		return {
			level: overlay.querySelector('.freemitbbs-image-viewer-level').textContent,
			width: parseInt(overlay.querySelector('.freemitbbs-image-viewer-image').style.width, 10),
			position: getComputedStyle(overlay).position,
			zIndex: parseInt(getComputedStyle(overlay).zIndex, 10),
			toolbarInsideViewport: toolbarRect.left >= 0 && toolbarRect.right <= innerWidth && toolbarRect.top >= 0,
		};
	})()`);
	assert(actualSize.level === '100%', '1:1 button did not select actual size');
	assert(actualSize.width === 1600, '1:1 button did not restore the natural image width');
	assert(actualSize.position === 'fixed' && actualSize.zIndex >= 100000, 'Viewer is not above the page stacking layers');
	assert(actualSize.toolbarInsideViewport, 'Viewer toolbar is outside the viewport');

	await evaluate(client, 'window.freemitbbsPostImageViewer.close()');
	await evaluate(client, `document.querySelector('#viewer-attachment').dispatchEvent(new MouseEvent('click', {
		bubbles: true,
		cancelable: true,
		button: 0,
		view: window,
	}))`);
	const attachmentClick = await evaluate(client, `({
		visible: !document.querySelector('.freemitbbs-image-viewer').hidden,
		legacyHandlerCalled: !!window.__legacyAttachmentClick,
	})`);
	assert(attachmentClick.visible, 'Attachment image did not open in viewer');
	assert(!attachmentClick.legacyHandlerCalled, 'Legacy inline attachment click handler also ran');

	console.log(JSON.stringify({
		postImages: initial.count,
		linkedImageStayedOnTopic: true,
		dynamicMarkdownImage: true,
		attachmentImage: true,
		smileyExcluded: true,
		zoomChangedFrom: beforeZoom.level,
		zoomChangedTo: afterZoom.level,
		actualSize: actualSize.level,
		zIndex: actualSize.zIndex,
		escapeClosed: true,
	}, null, 2));
} finally {
	client.close();
}
