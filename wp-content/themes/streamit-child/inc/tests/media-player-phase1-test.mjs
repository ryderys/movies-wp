/**
 * Focused Phase 1 progressive-source tests.
 *
 * Run:
 *   node wp-content/themes/streamit-child/inc/tests/media-player-phase1-test.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(testDir, '../../../../..');
const playerSourcePath = path.join(
    repoRoot,
    'wp-content/themes/streamit/static/assets/js/mediaplayer.js'
);
const playerTemplatePath = path.join(
    repoRoot,
    'wp-content/themes/streamit-child/template-parts/media-player/single/single.php'
);
const compiledPlayerPath = path.join(
    repoRoot,
    'wp-content/themes/streamit/static/dist/streamit-theme-mediaplayer.024866de.js'
);
const adapterPath = path.join(
    repoRoot,
    'wp-content/plugins/custom-plugins/movies-wp-media-automation/includes/class-movies-wp-streamit-adapter.php'
);

const playerSource = fs.readFileSync(playerSourcePath, 'utf8');
const playerTemplate = fs.readFileSync(playerTemplatePath, 'utf8');
const compiledPlayer = fs.readFileSync(compiledPlayerPath, 'utf8');
const adapterSource = fs.readFileSync(adapterPath, 'utf8');

const executableSource = playerSource
    .replace(/^import .*;\r?\n/gm, '')
    .replace('export default class MediaPlayer', 'class MediaPlayer')
    .concat('\nmodule.exports = MediaPlayer;\n');

const sandbox = {
    module: { exports: {} },
    exports: {},
    console,
    document: {
        _listeners: new Map(),
        querySelector: () => null,
        querySelectorAll: () => [],
        addEventListener(type, handler) {
            if (!this._listeners.has(type)) this._listeners.set(type, new Set());
            this._listeners.get(type).add(handler);
        },
        removeEventListener(type, handler) {
            this._listeners.get(type)?.delete(handler);
        },
        createElement: () => {
            let text = '';
            return {
                set textContent(value) {
                    text = String(value);
                },
                get innerHTML() {
                    return text
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                },
            };
        },
    },
    window: {},
    setTimeout: (callback) => callback(),
    clearTimeout: () => {},
    post: async () => {},
    Plyr: class {},
};
vm.runInNewContext(executableSource, sandbox, { filename: playerSourcePath });
const MediaPlayer = sandbox.module.exports;

function playerHarness(dataset = {}) {
    const player = Object.create(MediaPlayer.prototype);
    player.playerContainer = { dataset, innerHTML: '' };
    player.activeSourceIndex = 0;
    return player;
}

console.log('dataset compatibility');
{
    const controls = JSON.stringify({
        controls: ['play', 'sources'],
        i18n: { sources: 'Quality' },
    });
    const legacy = playerHarness({
        player_controls: controls,
        current_time: '42',
        post_id: '26',
        post_type: 'movie',
        user_id: '7',
    });
    assert.deepEqual(
        Array.from(legacy.getPlayerConfig().controls),
        ['play', 'sources'],
        'legacy underscore player_controls is accepted'
    );
    assert.equal(legacy.getPlayerData('currentTime', 'current_time'), '42');
    assert.equal(legacy.getPlayerData('postId', 'post_id'), '26');
    assert.equal(legacy.getPlayerData('postType', 'post_type'), 'movie');
    assert.equal(legacy.getPlayerData('userId', 'user_id'), '7');
    assert.equal(legacy.getLocalizedText().sources, 'Quality', 'menu text comes from player controls');

    const modern = playerHarness({ playerControls: controls });
    assert.deepEqual(
        Array.from(modern.getPlayerConfig().controls),
        ['play', 'sources'],
        'camelCase dataset key remains supported'
    );
}

console.log('quality labels and duplicate rows');
{
    const player = playerHarness();
    const rawSources = [
        { quality: '1080p', name: '', content: '<video></video>', is_default: true },
        { quality: '720p', name: '', content: '<video></video>' },
        { quality: '720p', name: '', content: '<video></video>' },
        { quality: '480p', name: 'KNPSK', content: '<video></video>' },
        { quality: '480p', name: 'SS', content: '<video></video>' },
    ];
    const before = JSON.stringify(rawSources);
    const prepared = player.prepareSourcesForDisplay(rawSources);

    assert.equal(prepared[0].displayPrimary, '1080p', 'quality is primary');
    assert.equal(prepared[0].displaySecondary, '', 'empty name stays empty');
    assert.equal(prepared[1].duplicateCount, 2, 'duplicate 720p rows remain present');
    assert.equal(prepared[1].duplicateOrdinal, 1);
    assert.equal(prepared[2].duplicateOrdinal, 2);
    assert.equal(prepared[3].displayPrimary, '480p');
    assert.equal(prepared[3].displaySecondary, 'KNPSK', 'real encoder is secondary');
    assert.equal(prepared[4].displaySecondary, 'SS', 'second real encoder stays distinct');
    assert.equal(JSON.stringify(rawSources), before, 'display preparation never mutates source data');

    const first720 = player.createSourceItem(prepared[1], 1);
    const second720 = player.createSourceItem(prepared[2], 2);
    assert.match(first720, /data-source-index="1"/);
    assert.match(first720, /720p/);
    assert.match(first720, /Source 1/);
    assert.match(second720, /data-source-index="2"/);
    assert.match(second720, /Source 2/);

    const repeatedDefaultQuality = player.prepareSourcesForDisplay([
        { quality: '1080p', name: '', label: '', content: '<video></video>', is_default: true },
        { quality: '1080p', name: '', label: '1080p', content: '<video></video>' },
    ]);
    assert.equal(repeatedDefaultQuality[0].duplicateCount, 2);
    assert.equal(repeatedDefaultQuality[1].duplicateCount, 2);
}

console.log('stable active source index');
{
    const player = playerHarness();
    const buttons = [0, 1, 2].map((index) => ({
        dataset: { sourceIndex: String(index) },
        active: false,
        classList: {
            toggle(_name, active) {
                buttons[index].active = active;
            },
        },
    }));
    sandbox.document.querySelectorAll = () => buttons;
    player.highlightActiveSource(2);
    assert.deepEqual(
        buttons.map((button) => button.active),
        [false, false, true],
        'only the exact source index is active'
    );
    assert.doesNotMatch(
        playerSource,
        /textContent\.trim\(\)\.startsWith/,
        'active highlighting does not inspect display labels'
    );
}

console.log('source menu listener cleanup');
{
    const player = playerHarness();
    const button = { addEventListener: () => {}, contains: () => false };
    const menu = {
        addEventListener: () => {},
        contains: () => false,
        classList: { toggle: () => {}, remove: () => {} },
        querySelectorAll: () => [],
    };
    sandbox.document._listeners.clear();
    player.setupSourcesMenuEvents(menu, button, []);
    player.setupSourcesMenuEvents(menu, button, []);
    assert.equal(
        sandbox.document._listeners.get('click').size,
        1,
        'rebuilding the menu replaces its document click handler'
    );
}

console.log('source switching state and captions');
{
    class PlyrStub {
        constructor() {
            this.currentTime = 0;
            this.paused = true;
            this.volume = 1;
            this.muted = false;
            this.speed = 1;
            this.captions = { active: false };
            this.currentTrack = -1;
            this.played = false;
        }

        once(event, callback) {
            if (event === 'ready') callback();
        }

        play() {
            this.played = true;
            this.paused = false;
            return Promise.resolve();
        }

        toggleCaptions(active) {
            this.captions.active = active;
        }
    }
    sandbox.Plyr = PlyrStub;

    const player = playerHarness({
        player_controls: JSON.stringify({ controls: ['play', 'sources'] }),
        sources: '[]',
    });
    const oldPlayer = {
        currentTime: 321,
        paused: false,
        volume: 0.35,
        muted: true,
        speed: 1.5,
        captions: { active: true },
        currentTrack: 0,
        destroyed: false,
        destroy() {
            this.destroyed = true;
        },
    };
    player.player = oldPlayer;
    player.setupPlayerEvents = () => {};
    player.setupSourcesMenu = () => {};
    player.highlightActiveSource = () => {};

    const sourceHtml = '<video id="streamit_player"><source src="/v/TOKEN"><track kind="captions"></video>';
    player.switchSource({ content: sourceHtml }, 2);

    assert.equal(oldPlayer.destroyed, true);
    assert.equal(player.playerContainer.innerHTML, sourceHtml, 'selected HTML including tracks replaces the old video');
    assert.equal(player.player.currentTime, 321, 'currentTime restored');
    assert.equal(player.player.played, true, 'playing source resumes');
    assert.equal(player.player.volume, 0.35, 'volume restored');
    assert.equal(player.player.muted, true, 'mute restored');
    assert.equal(player.player.speed, 1.5, 'playback rate restored');
    assert.equal(player.player.captions.active, true, 'caption enabled state restored');
    assert.equal(player.player.currentTrack, 0, 'caption track index restored');
    assert.equal(player.activeSourceIndex, 2, 'active source identity is the selected index');

    const pausedPlayer = {
        ...oldPlayer,
        paused: true,
        destroyed: false,
        destroy() {
            this.destroyed = true;
        },
    };
    player.player = pausedPlayer;
    player.switchSource({ content: sourceHtml }, 1);
    assert.equal(player.player.played, false, 'paused source remains paused');
}

console.log('template and persistence guards');
assert.match(playerTemplate, /streamit_child_insert_subtitle_tracks\(\$content, \$subtitle_tracks\)/);
assert.match(playerTemplate, /streamit_child_insert_subtitle_tracks\(\$src_html, \$subtitle_tracks\)/);
assert.match(playerTemplate, /'source_index'\s*=>/);
assert.match(playerTemplate, /\$default_was_added && null !== \$default_identity/);
assert.match(playerTemplate, /'name'\s*=>\s*trim\(\(string\) \(\$s\['name'\]/);
assert.doesNotMatch(playerTemplate, /'name'\s*=>\s*\$label/);
assert.match(compiledPlayer, /Phase 1 progressive quality selector compatibility/);
assert.match(compiledPlayer, /getPlayerData\("playerControls","player_controls"\)/);
assert.match(compiledPlayer, /buttonIndex===sourceIndex/);
assert.doesNotMatch(
    compiledPlayer.slice(compiledPlayer.indexOf('Phase 1 progressive quality selector compatibility')),
    /startsWith\(/,
    'compiled Phase 1 selector does not highlight by label'
);
assert.match(adapterSource, /'_movie_choice'\s*=>\s*'movie_url'/);
assert.match(adapterSource, /'_movie_url_link'\s*=>\s*\$first_video_link/);
assert.doesNotMatch(
    adapterSource,
    /'_source'.*movies_wp_media_signed_url/s,
    'adapter does not persist a signed source URL'
);

console.log('All Phase 1 player assertions passed.');
