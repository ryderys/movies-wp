/**
 * Season-switch request identity and slider reset helpers.
 *
 * Run:
 *   node wp-content/themes/streamit-child/inc/tests/tvshow-season-switch-test.mjs
 */

import { createRequire } from 'module';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const statePath = path.resolve(__dirname, '../../assets/js/tvshow-season-switch-state.js');

const require = createRequire(import.meta.url);
const vm = require('vm');
const fs = require('fs');

const sandbox = { globalThis: {} };
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(statePath, 'utf8'), sandbox);

const api = sandbox.streamitChildSeasonSwitch;
let failures = 0;

function assert(cond, label) {
	if (cond) {
		console.log(`  ok  ${label}`);
		return;
	}
	failures += 1;
	console.log(`  FAIL  ${label}`);
}

console.log('=== TV show season switch ===\n');

console.log('1. Stale responses are ignored after rapid season clicks');
const state = api.createSeasonSwitchState();
const a1 = api.beginSeasonRequest(state, '0');
const a2 = api.beginSeasonRequest(state, '0');
const b1 = api.beginSeasonRequest(state, '1');
assert(a1 === 1 && a2 === 2 && b1 === 1, 'request ids increment per season key');
assert(api.isCurrentSeasonRequest(state, '0', a2), 'newest season-0 request is current');
assert(!api.isCurrentSeasonRequest(state, '0', a1), 'older season-0 request is stale');
assert(api.isCurrentSeasonRequest(state, '1', b1), 'season-1 request is independent');

console.log('\n2. Listener attaches once (no duplicate handlers)');
const target = {
	__streamitChildSeasonSwitch: undefined,
	addEventListener(type, listener, capture) {
		this.type = type;
		this.listener = listener;
		this.capture = capture;
		this.calls = (this.calls || 0) + 1;
	}
};
assert(api.attachSeasonSwitch(target, () => {}) === true, 'first attach succeeds');
assert(api.attachSeasonSwitch(target, () => {}) === false, 'second attach is rejected');
assert(target.calls === 1 && target.capture === true, 'capture-phase listener registered once');

console.log('\n3. Slider reset always unslicks an initialized slider before re-init');
assert(
	JSON.stringify(api.planSliderReset(true)) === JSON.stringify({ unslick: true, init: true }),
	'initialized slider is unslicked then re-inited'
);
assert(
	JSON.stringify(api.planSliderReset(false)) === JSON.stringify({ unslick: false, init: true }),
	'fresh slider still inits'
);

console.log('\n4. Payload parsing');
assert(api.parseEpisodePayload({ success: true, result: [{ block_main_content: '<div>e1</div>' }] }).status === 'ready', 'ready payload');
assert(api.parseEpisodePayload({ success: true, result: [] }).status === 'empty', 'empty result');
assert(api.parseEpisodePayload('not-json').status === 'error', 'malformed JSON is an error');
assert(api.sliderSettingsForCount({ slidesToShow: 5, infinite: true }, 2).infinite === false, 'few slides disable infinite');

console.log('');
if (failures === 0) {
	console.log('All TV show season switch tests PASSED!');
	process.exit(0);
}
console.log(`${failures} TV show season switch test(s) FAILED.`);
process.exit(1);
