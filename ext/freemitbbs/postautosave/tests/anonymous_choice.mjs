import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../styles/all/template/js/postautosave.js', import.meta.url), 'utf8');
const values = new Map();
const localStorage = {
	getItem: (key) => values.get(key) ?? null,
	setItem: (key, value) => values.set(key, value),
	removeItem: (key) => values.delete(key)
};

function node(properties = {}) {
	return Object.assign({
		listeners: {}, children: [],
		addEventListener(type, fn) { this.listeners[type] = fn; },
		dispatchEvent(event) { this.listeners[event.type]?.(event); },
		appendChild(child) { this.children.push(child); },
		setAttribute() {},
		insertBefore(child) { this.children.push(child); }
	}, properties);
}

function page(message = '', checked = false) {
	const form = node({
		elements: {
			subject: node({ value: '', type: 'text' }),
			message: node({ value: message, type: 'textarea' }),
			anonymous_post: node({ value: '1', type: 'checkbox', checked })
		},
		getAttribute: (name) => name === 'id' ? 'postform' : './posting.php?mode=post&f=61',
		querySelector(selector) {
			if (selector === '.postautosave-notice') return null;
			if (selector === '#message-box') return this.elements.message;
			return {};
		}
	});
	form.elements.message.parentNode = form;
	const document = { readyState: 'complete', querySelectorAll: () => [form], createElement: () => node() };
	const window = {
		location: { href: 'http://127.0.0.1:8090/posting.php?mode=post&f=61' },
		localStorage, clearTimeout() {}, setTimeout(fn) { fn(); return 1; }
	};
	vm.runInNewContext(source, { window, document, URL, Event: class { constructor(type) { this.type = type; } } });
	return form;
}

const original = page('Sensitive draft text', true);
original.elements.message.dispatchEvent({ type: 'change' });
const restored = page();
assert.equal(restored.elements.message.value, 'Sensitive draft text');
assert.equal(restored.elements.anonymous_post.checked, true, 'restoring text must also restore anonymity');

restored.elements.anonymous_post.checked = false;
restored.elements.anonymous_post.dispatchEvent({ type: 'change' });
const named = page('', true);
assert.equal(named.elements.message.value, 'Sensitive draft text');
assert.equal(named.elements.anonymous_post.checked, false, 'explicit named choice must survive restoration');
assert.equal(named.elements.anonymous_post.value, '1', 'checkbox submission value remains valid');

values.clear();
const empty = page('', true);
empty.elements.anonymous_post.dispatchEvent({ type: 'change' });
assert.equal(values.size, 0, 'checkbox alone is not a text draft');
console.log('Post autosave anonymous-choice regression passed (6 assertions).');
