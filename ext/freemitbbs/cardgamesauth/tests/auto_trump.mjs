import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

const source = readFileSync(new URL('../styles/all/template/js/cardgames_client.js', import.meta.url), 'utf8');
const storageKey = 'freemitbbs.cardgames.autoTrump.v1';

function harness(values = new Map()) {
  const timers = new Map();
  let nextTimer = 0;
  const element = { addEventListener() {}, dataset: {} };
  const context = vm.createContext({
    URL, URLSearchParams, console,
    document: { querySelector: () => element, addEventListener() {} },
    window: {
      addEventListener() {},
      localStorage: {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value)
      },
      setTimeout(callback, delay) {
        timers.set(++nextTimer, { callback, delay });
        return nextTimer;
      },
      clearTimeout: (id) => timers.delete(id)
    }
  });
  // Load the complete client without starting network connections or rendering.
  vm.runInContext(source.replace('void init();', ''), context);
  const run = (expression) => vm.runInContext(expression, context);
  run(`
    const sentCalls = [];
    scheduleSyncTableActions = () => {};
    setStatus = () => {};
    sendEngineCommand = (...args) => { sentCalls.push(args); return Promise.resolve(); };
    function testCard(id) {
      const rank = id < 52 ? id % 13 : id - 39;
      return {
        id, rank,
        suit: id < 52 ? ["heart", "spade", "diamond", "club"][Math.floor(id / 13)] : "joker",
        points: rank === 3 ? 5 : rank === 8 || rank === 11 ? 10 : 0
      };
    }
    function testTable(ids = [0, 1, 2, 4, 5, 6, 7], exposure = "none") {
      return {
        phase: "making_trump",
        room: { roomKey: "tractor-room", gameType: "tractor" },
        viewer: { role: "player", seatIndex: 0 },
        actions: [{ type: "tractor.makeTrump", enabled: true }],
        engine: {
          public: { handId: "hand-1", rank: 0, trump: { suit: "none", exposure } },
          private: { seatIndex: 0, cards: ids.map(testCard) }
        }
      };
    }
    state.authenticated = true;
    state.currentRoomKey = "tractor-room";
    state.table = testTable();
  `);
  return {
    run,
    values,
    timers,
    call: (ids, exposure = 'none') => JSON.parse(run(`JSON.stringify(chooseAutoTrumpCall(${JSON.stringify(ids)}.map(testCard), 0, ${JSON.stringify(exposure)}))`)),
    fire() {
      const id = run('state.autoTrumpTimerId');
      assert.ok(timers.has(id), 'an automatic call must be scheduled');
      const timer = timers.get(id);
      timers.delete(id);
      timer.callback();
    },
    calls: () => JSON.parse(run('JSON.stringify(sentCalls)'))
  };
}

test('uses bot thresholds and chooses the strongest eligible call', () => {
  const h = harness();
  assert.equal(h.call([0, 1, 14, 27, 40]), null);
  assert.equal(h.call([0, 0, 1, 14, 27, 40]), null);
  assert.deepEqual(h.call([0, 1, 2, 4, 5, 6, 7]), { suit: 'heart', exposure: 'single_rank' });
  assert.deepEqual(h.call([0, 0, 1, 2, 4]), { suit: 'heart', exposure: 'pair_rank' });
  assert.deepEqual(h.call([0, 13, 14, 15, 17, 18, 19, 20]), { suit: 'spade', exposure: 'single_rank' });
  assert.deepEqual(h.call([52, 52, 53, 53], 'pair_rank'), { suit: 'joker', exposure: 'pair_red_joker' });
  assert.deepEqual(h.call([52, 52], 'pair_rank'), { suit: 'joker', exposure: 'pair_black_joker' });
  assert.equal(h.call([52, 52], 'pair_black_joker'), null);
  assert.equal(h.call([53, 53], 'pair_red_joker'), null);
});

test('defaults off, remembers the switch, and renders it before any callable cards', () => {
  const h = harness();
  h.run('restoreAutoTrumpPreference(); syncAutoTrump();');
  assert.equal(h.run('state.autoTrumpEnabled'), false);
  assert.equal(h.timers.size, 0);
  h.run('state.table = testTable([]);');
  assert.match(h.run('tableActionsHtml(state.table)'), /自动抢庄：关/);
  h.run('handleTableAction("tractor.toggleAutoTrump");');
  assert.equal(h.values.get(storageKey), 'true');
  assert.match(h.run('tableActionsHtml(state.table)'), /aria-pressed="true"/);
  const restored = harness(h.values);
  restored.run('restoreAutoTrumpPreference();');
  assert.equal(restored.run('state.autoTrumpEnabled'), true);
  h.run('state.table.viewer.role = "observer";');
  assert.doesNotMatch(h.run('tableActionsHtml(state.table)'), /toggleAutoTrump/);
  h.run('state.table.room.gameType = "guandan";');
  assert.doesNotMatch(h.run('tableActionsHtml(state.table)'), /toggleAutoTrump/);
});

test('still works when browser storage is unavailable', () => {
  const h = harness();
  h.run('window.localStorage = null; restoreAutoTrumpPreference(); toggleAutoTrump();');
  assert.equal(h.run('state.autoTrumpEnabled'), true);
  assert.equal(h.timers.size, 1);
});

test('opens from the dealt hand with the bot delay and submits only once', () => {
  const h = harness();
  h.run('state.dealingVisibleCount = 0; toggleAutoTrump();');
  const id = h.run('state.autoTrumpTimerId');
  assert.equal(h.timers.get(id).delay, 3000);
  h.run('syncAutoTrump(); syncAutoTrump();');
  assert.equal(h.run('state.autoTrumpTimerId'), id, 'renders must not restart the delay');
  h.fire();
  assert.deepEqual(h.calls(), [['tractor.makeTrump', 'tractor-room', { suit: 'heart', exposure: 'single_rank' }]]);
  h.run('syncAutoTrump();');
  assert.equal(h.timers.size, 0, 'a failed or uncertain call must not cause a resubmit loop');
});

test('counters near the end of the window and responds to a stronger call', () => {
  const h = harness();
  h.run(`
    state.table = testTable([53, 53], "pair_rank");
    state.table.engine.public.trumpRevealAt = new Date(Date.now() + 12000).toISOString();
    toggleAutoTrump();
  `);
  const delay = h.timers.get(h.run('state.autoTrumpTimerId')).delay;
  assert.ok(delay >= 9900 && delay <= 10000);
  h.run('state.table.engine.public.trump.exposure = "pair_black_joker"; syncAutoTrump();');
  assert.equal(h.timers.size, 1);
  h.fire();
  assert.equal(h.calls()[0][2].exposure, 'pair_red_joker');
});

test('cancels when switched off, disconnected, or leaving the room', () => {
  for (const cancel of ['toggleAutoTrump()', 'disconnect()', 'clearRoomState()']) {
    const h = harness();
    h.run('toggleAutoTrump();');
    const queued = h.timers.get(h.run('state.autoTrumpTimerId')).callback;
    h.run(cancel);
    assert.equal(h.run('state.autoTrumpTimerId'), 0, cancel);
    queued(); // A callback already in the event queue must also be harmless.
    assert.deepEqual(h.calls(), [], cancel);
  }
});

test('rechecks room, phase, connection, pending commands, and deadline before sending', () => {
  for (const change of [
    'state.currentRoomKey = "another-room"',
    'state.table.phase = "playing"',
    'state.table.room.gameType = "guandan"',
    'state.table.viewer.role = "observer"',
    'state.table.engine.private.seatIndex = 1',
    'state.table = { ...state.table, actions: [] }',
    'state.authenticated = false',
    'state.recovering = true',
    'state.transitionRoomKey = "another-room"',
    'state.pendingRoomMutations.add("room:tractor-room")',
    'state.pendingActions.add(commandKey("tractor.makeTrump", "tractor-room"))',
    'state.table.engine.public.trumpRevealAt = new Date(Date.now() - 1).toISOString()',
    'state.table.engine.public.trump.exposure = "pair_red_joker"'
  ]) {
    const h = harness();
    h.run('toggleAutoTrump();');
    h.run(change);
    h.fire();
    assert.deepEqual(h.calls(), [], change);
    assert.equal(h.run('state.autoTrumpTimerId'), 0, change);
  }
});

test('a queued callback cannot bid in a new hand; the next hand gets its own delay', () => {
  const h = harness();
  h.run('toggleAutoTrump(); state.table = testTable(); state.table.engine.public.handId = "hand-2";');
  h.fire();
  assert.deepEqual(h.calls(), []);
  assert.equal(h.timers.get(h.run('state.autoTrumpTimerId')).delay, 3000);
  h.fire();
  assert.equal(h.calls().length, 1);
});

test('a cancelled callback cannot consume a newly scheduled call', () => {
  const h = harness();
  h.run('toggleAutoTrump();');
  const cancelled = h.timers.get(h.run('state.autoTrumpTimerId')).callback;
  h.run('toggleAutoTrump(); toggleAutoTrump();');
  const nextId = h.run('state.autoTrumpTimerId');
  cancelled();
  assert.deepEqual(h.calls(), []);
  assert.equal(h.run('state.autoTrumpTimerId'), nextId);
  h.fire();
  assert.equal(h.calls().length, 1);
});

test('reschedules a counter when a resumed game changes its deadline', () => {
  const h = harness();
  h.run(`
    state.table = testTable([53, 53], "pair_rank");
    state.table.engine.public.trumpRevealAt = new Date(Date.now() + 12000).toISOString();
    toggleAutoTrump();
  `);
  const firstId = h.run('state.autoTrumpTimerId');
  h.run('state.table.engine.public.trumpRevealAt = new Date(Date.now() + 20000).toISOString(); syncAutoTrump();');
  assert.ok(!h.timers.has(firstId));
  const delay = h.timers.get(h.run('state.autoTrumpTimerId')).delay;
  assert.ok(delay >= 17900 && delay <= 18000);
});
