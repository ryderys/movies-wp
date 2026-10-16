/**
 * Focused Node checks for trailer modal stop/clear helpers.
 *
 * Run:
 *   node wp-content/themes/streamit-child/inc/tests/trailer-modal-mime-test.mjs
 */

import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import path from 'path';

const require = createRequire(import.meta.url);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const helpers = require('../../assets/js/trailer-modal-mime.js');

let failures = 0;

function assert(cond, label) {
	if (cond) {
		console.log(`  ok  ${label}`);
		return;
	}
	failures += 1;
	console.log(`  FAIL  ${label}`);
}

console.log('trailer modal helper tests\n');

const iframe = {
	src: 'https://www.aparat.com/video/video/embed/videohash/nwlrj1m/vt/frame',
	removeAttribute(name) {
		if (name === 'src') {
			delete this.src;
		}
	},
};

const source = {
	src: 'https://media.asiastarx.ir/v/TOKEN',
	type: 'video/mp4',
	removeAttribute(name) {
		delete this[name];
	},
};

const video = {
	src: 'https://media.asiastarx.ir/v/TOKEN',
	currentTime: 12,
	paused: false,
	pause() {
		this.paused = true;
	},
	load() {
		this.loaded = true;
	},
	querySelector(sel) {
		return sel === 'source' ? source : null;
	},
	removeAttribute(name) {
		delete this[name];
	},
};

const modal = {
	querySelector(sel) {
		if (sel === '#trailerIframe') {
			return iframe;
		}
		if (sel === '#trailerVideo') {
			return video;
		}
		return null;
	},
};

helpers.stopTrailerMedia(modal);

assert(!('src' in iframe) || iframe.src === '', 'iframe src cleared');
assert(video.paused === true, 'video paused');
assert(video.currentTime === 0, 'video currentTime reset');
assert(!('src' in source), 'video source src removed');
assert(!('type' in source), 'video source type removed');
assert(video.loaded === true, 'video.load() called');

const mimeSource = {
	type: '',
	setAttribute(name, value) {
		this[name] = value;
	},
};
const previousQuery = globalThis.document;
globalThis.document = {
	querySelector() {
		return mimeSource;
	},
};
assert(helpers.applyMime('video/x-matroska') === true, 'applyMime sets type');
assert(mimeSource.type === 'video/x-matroska', 'MIME value applied');
globalThis.document = previousQuery;

console.log('');
if (failures > 0) {
	console.log(`FAILED ${failures} assertion(s)`);
	process.exit(1);
}
console.log('All trailer modal helper tests passed.');
