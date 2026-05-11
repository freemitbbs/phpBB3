const state = {
  bootstrap: {},
  initialized: false,
  token: "",
  user: null,
  ws: null,
  connected: false,
  authenticated: false,
  connecting: false,
  recovering: false,
  connectionEpoch: 0,
  turnTimerId: 0,
  trickReviewTimerId: 0,
  trumpRevealTimerId: 0,
  trickReviewTimerSignature: "",
  trumpRevealTimerSignature: "",
  heartbeatId: 0,
  heartbeatMisses: 0,
  syncPromise: null,
  syncEpoch: 0,
  syncRetryAttempt: 0,
  requestSeq: 0,
  renderFrameId: 0,
  pending: new Map(),
  timedOutPending: new Map(),
  retryRequests: new Map(),
  pendingActions: new Set(),
  pendingRoomMutations: new Set(),
  rooms: [],
  lobbyUsers: [],
  table: null,
  currentRoomKey: "",
  transitionRoomKey: "",
  roomEpoch: 0,
  lastSeenSeq: 0,
  skinProfile: null,
  chatEvents: [],
  chatVersion: 0,
  chatRenderFrameId: 0,
  renderedChatVersion: -1,
  renderedChatRoomKey: "",
  renderedChatKeys: [],
  renderedChatSignatures: new Map(),
  chatScrollFrameId: 0,
  renderedTopbarSignature: "",
  renderedSkinSignature: "",
  renderedDeadlineSignature: "",
  renderedEmojiSignature: "",
  renderedMinesweeperSignature: "",
  renderedLobbyUsersSignature: "",
  renderedPlayLogSignature: "",
  renderedRoomsSignature: "",
  renderedTableSections: new Map(),
  renderedTableSectionSignatures: new Map(),
  requestedChatChannelKey: "",
  turnTimerSignature: "",
  chatDraft: "",
  playLogEntries: [],
  playLogKeys: new Set(),
  playLogRoomKey: "",
  playLogHandId: "",
  playLogExportKey: "",
  playLogExportLoading: false,
  playLogExportError: "",
  emojiTarget: "",
  minesweeper: null,
  minesweeperExpanded: false,
  minesweeperFlagMode: false,
  minesweeperTimerId: 0,
  selectedHandIndexes: [],
  handSignature: "",
  privateHandAvailableWidth: 0,
  privateHandOverlapValue: "",
  privateHandResizeFrameId: 0,
  assetBaseUrl: "",
  audioBaseUrl: "",
  cardStyle: "cardsclassic",
  soundEnabled: false
};

const tableActionCache = new WeakMap();
const tablePauseCache = new WeakMap();
const privateCardSplitCache = new WeakMap();
const audioCache = new Map();
let tableActionsElement = null;
let privateHandElement = null;
let privateHandResizeObserver = null;
const disabledAction = Object.freeze({ enabled: false });
const emptyPrivateCardSplit = Object.freeze({
  hand: Object.freeze([]),
  bottom: Object.freeze([])
});

const scriptUrl = document.currentScript?.src || "";
const defaultSkins = [
  "skin_basicmale.webp",
  "skin_basicfemale.webp",
  "skin_boy_1.webp",
  "skin_girl_1.webp",
  "skin_boy_2.webp",
  "skin_girl_2.webp",
  "skin_boy_3.webp",
  "skin_girl_3.webp"
];
const emojiById = {
  smile: { id: "smile", asset: "goodjob", label: "干得好" },
  laugh: { id: "laugh", asset: "lol", label: "大笑" },
  thumbs_up: { id: "thumbs_up", asset: "youareright", label: "说得对" },
  fire: { id: "fire", asset: "fireworks", label: "烟花" },
  thinking: { id: "thinking", asset: "hurryup", label: "快点" },
  surprise: { id: "surprise", asset: "sorry", label: "抱歉" },
  good_luck: { id: "good_luck", asset: "noproblem", label: "没问题" },
  clap: { id: "clap", asset: "goodjob", label: "鼓掌" },
  sad: { id: "sad", asset: "sorry", label: "难过" },
  angry: { id: "angry", asset: "hurryup", label: "着急" }
};
const emojiCatalog = [
  emojiById.smile,
  emojiById.laugh,
  emojiById.surprise,
  emojiById.good_luck,
  emojiById.thinking,
  emojiById.fire,
  emojiById.thumbs_up
];
const emojiTypes = ["goodjob", "lol", "sorry", "noproblem", "hurryup", "fireworks", "youareright"];
const commandTimeoutMs = 15000;
const lateResponseRetentionMs = 60000;
const retryRequestRetentionMs = 10 * 60 * 1000;
const retryRequestStorageKey = "freemitbbs.cardgames.retryRequests.v1";
const lobbyChatChannelKey = "__lobby__";
const minesweeperRows = 9;
const minesweeperCols = 9;
const minesweeperMineCount = 10;
const heartbeatIntervalMs = 25000;
const heartbeatTimeoutMs = 10000;
const heartbeatMaxMisses = 2;
const syncRetryBaseMs = 500;
const syncRetryMaxMs = 5000;
const logPrefix = "[card-games]";
const retryableCommandTypes = new Set([
  "room.join",
  "room.leave",
  "room.settings.update",
  "seat.claim",
  "seat.release",
  "seat.remove",
  "player.ready",
  "observer.watch",
  "tractor.start",
  "tractor.makeTrump",
  "tractor.discardBottom",
  "tractor.playCards",
  "tractor.autoPlay",
  "chat.send",
  "emoji.send"
]);
const skinLabels = {
  "skin_basicmale": "基础男角色",
  "skin_basicfemale": "基础女角色",
  "skin_boy_1": "少年侠客",
  "skin_girl_1": "少女侠客",
  "skin_boy_2": "书生",
  "skin_girl_2": "仕女",
  "skin_boy_3": "游侠",
  "skin_girl_3": "女游侠"
};
const statusLabels = {
  waiting: "等待中",
  starting: "准备开始",
  playing: "游戏中",
  finished: "已结束",
  cancelled: "已取消",
  abandoned: "已放弃",
  archived: "已归档"
};
const phaseLabels = {
  waiting_for_players: "等待玩家",
  waiting_for_four_ready_players: "等待四名玩家准备",
  making_trump: "亮主",
  burying_bottom: "反主/埋牌",
  playing: "出牌中",
  finished: "已结束",
  trump_not_open: "现在还不能亮主/反主",
  bottom_holder_only: "只有底牌持有者可以埋牌",
  bottom_not_open: "现在还不能埋牌",
  waiting_for_turn: "还没轮到您出牌",
  play_not_open: "现在还不能出牌",
  auto_play_not_open: "自动出牌只能在跟牌时使用",
  trick_reviewing: "请先看完上一墩出牌",
  not_room_owner: "只有房主可以开始下一局",
  start_not_open: "当前牌局正在进行",
  game_paused: "游戏暂停，等待玩家上线、准备或空位补位"
};

function logClientWarn(message, details = null) {
  const sanitized = sanitizeLogDetails(details);
  if (!window.console?.warn) {
    return;
  }
  if (details === null || details === undefined) {
    window.console.warn(`${logPrefix} ${message}`);
    return;
  }
  window.console.warn(`${logPrefix} ${message}`, sanitized);
}

function logClientError(message, error = null, details = null) {
  const sanitizedError = sanitizeLogDetails(error);
  const sanitizedDetails = sanitizeLogDetails(details);
  if (!window.console?.error) {
    return;
  }
  const args = [`${logPrefix} ${message}`];
  if (error !== null && error !== undefined) {
    args.push(sanitizedError);
  }
  if (details !== null && details !== undefined) {
    args.push(sanitizedDetails);
  }
  window.console.error(...args);
}

function sanitizeLogDetails(value, depth = 0) {
  if (value instanceof Error) {
    return {
      name: value.name,
      message: redactLogString(value.message),
      stack: redactLogString(value.stack || "")
    };
  }
  if (typeof value === "string") {
    return redactLogString(value);
  }
  if (value === null || typeof value !== "object") {
    return value;
  }
  if (depth > 3) {
    return "[truncated]";
  }
  if (Array.isArray(value)) {
    return value.slice(0, 20).map((item) => sanitizeLogDetails(item, depth + 1));
  }

  const redacted = {};
  Object.keys(value).slice(0, 50).forEach((key) => {
    if (/token|secret|password|authorization|hash/i.test(key)) {
      redacted[key] = "[redacted]";
      return;
    }
    redacted[key] = sanitizeLogDetails(value[key], depth + 1);
  });
  return redacted;
}

function redactLogString(value) {
  if (!/[?&][^=]*(token|secret|password|authorization|hash)[^=]*=/i.test(value)) {
    return value;
  }

  try {
    const url = new URL(value, window.location.href);
    Array.from(url.searchParams.keys()).forEach((key) => {
      if (/token|secret|password|authorization|hash/i.test(key)) {
        url.searchParams.set(key, "[redacted]");
      }
    });
    return url.toString();
  } catch {
    return value.replace(/([?&][^=]*(?:token|secret|password|authorization|hash)[^=]*=)[^&#]*/gi, "$1[redacted]");
  }
}

const cardSuitLabels = {
  heart: "红桃",
  spade: "黑桃",
  diamond: "方块",
  club: "梅花"
};
const cardSuitSymbols = {
  heart: "♥",
  spade: "♠",
  diamond: "♦",
  club: "♣"
};
const cardRankLabels = ["2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A"];

const els = {
  status: document.querySelector("#connection-status"),
  user: document.querySelector("#user-label"),
  skin: document.querySelector("#skin-select"),
  connect: document.querySelector("#connect-button"),
  sound: document.querySelector("#sound-button"),
  deadlineControls: document.querySelector("#room-deadline-controls"),
  roomsPanel: document.querySelector(".rooms-panel"),
  rooms: document.querySelector("#rooms-list"),
  lobbyContent: document.querySelector(".lobby-content"),
  minesweeperPanel: document.querySelector("#lobby-minesweeper-panel"),
  lobbyUsersPanel: document.querySelector("#lobby-users-panel"),
  tablePanel: document.querySelector(".table-panel"),
  title: document.querySelector("#table-title"),
  leave: document.querySelector("#leave-room-button"),
  table: document.querySelector("#table-view"),
  lobbyChatSlot: document.querySelector("#lobby-chat-slot"),
  emojiPanel: document.querySelector("#emoji-panel"),
  emojiTarget: document.querySelector("#emoji-target-select"),
  emojiDock: document.querySelector("#emoji-dock"),
  chatPanel: document.querySelector("#chat-panel"),
  chatMessages: document.querySelector("#chat-messages"),
  chatForm: document.querySelector("#chat-form"),
  chatInput: document.querySelector("#chat-input"),
  actionStatus: document.querySelector("#action-status")
};

els.connect.addEventListener("click", () => {
  void connect();
});
els.sound.addEventListener("click", () => {
  state.soundEnabled = !state.soundEnabled;
  if (state.soundEnabled) {
    playSound("effect/enter_hall_click.mp3");
  }
  render();
});
els.skin.addEventListener("change", () => {
  void selectSkin(els.skin.value);
});
els.leave.addEventListener("click", () => {
  const roomKey = state.currentRoomKey;
  if (!roomKey) {
    return;
  }
  if (isActionPending("room.leave", roomKey)) {
    setStatus("正在离开房间");
    return;
  }

  const roomEpoch = beginRoomTransition("");
  setStatus("正在离开房间");
  void sendCommand("room.leave", { roomKey, roomEpoch })
    .then(() => {
      if (state.roomEpoch !== roomEpoch) {
        return undefined;
      }
      if (!state.currentRoomKey) {
        return undefined;
      }
      clearRoomState();
      render();
      return requestRooms();
    })
    .catch((error) => {
      if (state.roomEpoch === roomEpoch) {
        state.transitionRoomKey = "";
        void requestCatchup().catch(() => requestRooms());
      }
      reportError(error);
    });
});
els.chatForm.addEventListener("submit", (event) => {
  event.preventDefault();
  void sendChatMessage();
});
els.chatInput.addEventListener("input", () => {
  state.chatDraft = els.chatInput.value;
});
if (els.minesweeperPanel) {
  els.minesweeperPanel.addEventListener("click", handleMinesweeperClick);
  els.minesweeperPanel.addEventListener("contextmenu", handleMinesweeperContextMenu);
}
els.emojiTarget.addEventListener("change", () => {
  state.emojiTarget = els.emojiTarget.value;
});
els.table.addEventListener("click", (event) => {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) {
    return;
  }

  const cardButton = target.closest("[data-card-index]");
  if (cardButton && els.table.contains(cardButton)) {
    if (event.detail > 1) {
      return;
    }
    toggleCardSelection(Number(cardButton.dataset.cardIndex), cardButton);
    return;
  }

  const actionButton = target.closest("[data-action]");
  if (actionButton && els.table.contains(actionButton)) {
    handleTableAction(actionButton.dataset.action, actionButton.dataset.seat, actionButton);
  }
});
els.table.addEventListener("dblclick", (event) => {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) {
    return;
  }

  const cardButton = target.closest(".hand-card[data-card-index]");
  if (!cardButton || !els.table.contains(cardButton)) {
    return;
  }

  event.preventDefault();
  playSingleCardFromDoubleClick(Number(cardButton.dataset.cardIndex), cardButton);
});
els.table.addEventListener("error", (event) => {
  const image = event.target;
  if (!(image instanceof HTMLImageElement)) {
    return;
  }
  if (image.classList.contains("seat-forum-avatar")) {
    const fallback = image.dataset.fallbackSrc || "";
    if (fallback && image.dataset.fallbackUsed !== "true") {
      const failedSrc = image.currentSrc || image.src;
      image.dataset.fallbackUsed = "true";
      image.removeAttribute("data-fallback-src");
      image.classList.remove("seat-forum-avatar");
      image.classList.add("seat-skin", "seat-fallback-skin");
      image.src = fallback;
      logClientWarn("forum avatar failed to load; using game avatar", { src: failedSrc });
    }
    return;
  }
  if (!image.classList.contains("card-face-image")) {
    return;
  }
  image.hidden = true;
  image.closest(".card-face")?.classList.add("card-face-image-missing");
  logClientWarn("card face image failed to load", { src: image.currentSrc || image.src });
}, true);
window.addEventListener("pagehide", () => {
  disconnect();
});
window.addEventListener("pageshow", (event) => {
  if (event.persisted || !isSocketOpen()) {
    void reconnectAfterRestore();
  }
});
window.addEventListener("online", () => {
  if (!isSocketOpen()) {
    void reconnectAfterRestore();
  }
});
window.addEventListener("resize", () => {
  scheduleRender();
});

void init();

async function init() {
  state.bootstrap = await loadBootstrap();
  state.token = queryParam("token") || state.bootstrap.token || "";
  state.assetBaseUrl = normalizeBaseUrl(state.bootstrap.assetBaseUrl || defaultAssetBaseUrl());
  state.audioBaseUrl = normalizeBaseUrl(state.bootstrap.audioBaseUrl || defaultAudioBaseUrl());
  state.cardStyle = state.bootstrap.cardStyle || "cardsclassic";
  restoreRetryRequests();
  state.initialized = true;
  render();
  scheduleChatRender();
  if (state.bootstrap.autoConnect !== false) {
    void connect();
  }
}

async function loadBootstrap() {
  const inline = window.freemitbbsCardGames || {};
  const params = new URLSearchParams(window.location.search);
  const fromQuery = {
    token: params.get("token") || "",
    wsUrl: params.get("ws") || "",
    tokenUrl: params.get("tokenUrl") || "",
    configUrl: params.get("configUrl") || "",
    roundLogUrl: params.get("roundLogUrl") || "",
    tokenHash: params.get("hash") || ""
  };
  if (params.has("connect")) {
    fromQuery.autoConnect = params.get("connect") !== "0";
  }

  if (inline.tokenUrl || inline.wsUrl || fromQuery.token || fromQuery.wsUrl) {
    return { ...inline, ...withoutEmptyValues(fromQuery) };
  }

  try {
    const response = await fetch(inline.configUrl || fromQuery.configUrl || defaultConfigUrl(), { credentials: "same-origin" });
    if (!response.ok) {
      logClientWarn("client config request failed", {
        status: response.status,
        statusText: response.statusText,
        url: response.url
      });
      return fromQuery;
    }
    const config = await response.json();
    return { ...config, ...withoutEmptyValues(fromQuery) };
  } catch (error) {
    logClientWarn("client config bootstrap failed", error);
    return fromQuery;
  }
}

async function connect() {
  if (state.connecting) {
    return;
  }

  disconnect();
  const connectionEpoch = beginConnectionAttempt();
  state.connecting = true;
  state.recovering = true;
  setStatus("正在连接");
  scheduleChatRender();

  try {
    let tokenPayload = null;
    if (state.bootstrap.tokenUrl && state.bootstrap.tokenHash) {
      tokenPayload = await fetchToken();
    } else if (!state.token) {
      tokenPayload = await fetchToken();
    }
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      return;
    }
    if (tokenPayload) {
      applyTokenPayload(tokenPayload);
    }
    const wsUrl = state.bootstrap.wsUrl || defaultWsUrl();
    const ws = new WebSocket(wsUrl);
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      ws.close();
      return;
    }
    state.ws = ws;

    ws.addEventListener("open", () => {
      if (!isCurrentSocket(ws, connectionEpoch)) {
        return;
      }
      state.connected = true;
      state.connecting = false;
      startHeartbeat();
      render();
      scheduleChatRender();
      void authenticate(connectionEpoch);
    });
    ws.addEventListener("message", (event) => {
      if (!isCurrentSocket(ws, connectionEpoch)) {
        return;
      }
      handleMessage(event.data);
    });
    ws.addEventListener("close", (event) => {
      if (!isCurrentSocket(ws, connectionEpoch)) {
        return;
      }
      logClientWarn("websocket closed", {
        code: event.code,
        reason: event.reason || "",
        wasClean: event.wasClean
      });
      state.connectionEpoch += 1;
      state.ws = null;
      state.connected = false;
      state.authenticated = false;
      state.connecting = false;
      state.recovering = false;
      stopHeartbeat();
      rejectPending("连接已断开");
      render();
      scheduleChatRender();
    });
    ws.addEventListener("error", () => {
      if (!isCurrentSocket(ws, connectionEpoch)) {
        return;
      }
      logClientError("websocket error", null, {
        readyState: ws.readyState,
        url: ws.url
      });
      state.connectionEpoch += 1;
      state.ws = null;
      state.connecting = false;
      state.connected = false;
      state.authenticated = false;
      state.recovering = false;
      stopHeartbeat();
      if (ws.readyState < WebSocket.CLOSING) {
        ws.close();
      }
      rejectPending("连接出错");
      setStatus("连接出错");
      scheduleChatRender();
    });
  } catch (error) {
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      return;
    }
    logClientError("connection failed", error);
    state.connecting = false;
    state.recovering = false;
    stopHeartbeat();
    setStatus(error.message || "连接失败");
    scheduleChatRender();
  }
}

function disconnect() {
  const ws = state.ws;
  state.connectionEpoch += 1;
  if (state.ws && state.ws.readyState < WebSocket.CLOSING) {
    state.ws.close();
  }
  stopHeartbeat();
  clearTurnTimer();
  clearTrickReviewTimer();
  clearTrumpRevealTimer();
  state.ws = null;
  state.connected = false;
  state.authenticated = false;
  state.connecting = false;
  state.recovering = false;
  state.syncPromise = null;
  state.syncEpoch = 0;
  state.syncRetryAttempt = 0;
  state.transitionRoomKey = "";
  scheduleChatRender();
  if (ws) {
    rejectPending("连接已断开");
  } else {
    state.pending.forEach(clearPending);
    state.pending.clear();
    clearTimedOutPending();
    state.pendingActions.clear();
    state.pendingRoomMutations.clear();
  }
}

async function reconnectAfterRestore() {
  if (!state.initialized || state.bootstrap.autoConnect === false || state.connecting || isSocketOpen()) {
    return;
  }

  await connect();
}

function isSocketOpen() {
  return state.ws?.readyState === WebSocket.OPEN;
}

function beginConnectionAttempt() {
  state.connectionEpoch += 1;
  return state.connectionEpoch;
}

function isCurrentConnectionAttempt(connectionEpoch) {
  return state.connectionEpoch === connectionEpoch;
}

function isCurrentSocket(ws, connectionEpoch = state.connectionEpoch) {
  return state.connectionEpoch === connectionEpoch && state.ws === ws;
}

function startHeartbeat() {
  stopHeartbeat();
  state.heartbeatMisses = 0;
  state.heartbeatId = window.setInterval(() => {
    void sendHeartbeat();
  }, heartbeatIntervalMs);
}

function stopHeartbeat() {
  if (state.heartbeatId) {
    window.clearInterval(state.heartbeatId);
    state.heartbeatId = 0;
  }
  state.heartbeatMisses = 0;
}

async function sendHeartbeat() {
  if (!isSocketOpen()) {
    return;
  }

  try {
    const response = await sendCommand("system.ping", {
      payload: { clientTime: new Date().toISOString() },
      timeoutMs: heartbeatTimeoutMs,
      applyResponse: false
    });
    if (response.type === "system.pong") {
      state.heartbeatMisses = 0;
    }
  } catch {
    if (!state.ws && !state.connected) {
      return;
    }
    state.heartbeatMisses += 1;
    if (state.heartbeatMisses >= heartbeatMaxMisses) {
      forceReconnect("连接无响应，正在重连");
    }
  }
}

function forceReconnect(message) {
  disconnect();
  if (message) {
    setStatus(message);
  }
  void reconnectAfterRestore();
}

function rejectPending(message) {
  state.pending.forEach((pending, requestId) => {
    clearPending(pending);
    rememberRetryRequest(requestId, pending);
    pending.reject(new Error(message));
  });
  state.pending.clear();
  clearTimedOutPending();
}

function clearPending(pending) {
  if (pending.timeoutId) {
    window.clearTimeout(pending.timeoutId);
  }
  if (pending.pendingKey) {
    state.pendingActions.delete(pending.pendingKey);
  }
  if (pending.pendingMutationKey) {
    state.pendingRoomMutations.delete(pending.pendingMutationKey);
  }
}

function rememberTimedOutPending(requestId, pending) {
  if (!shouldApplyLateResponse(pending)) {
    return;
  }

  clearTimedOutPending(requestId);
  const expiresId = window.setTimeout(() => {
    state.timedOutPending.delete(requestId);
  }, lateResponseRetentionMs);
  state.timedOutPending.set(requestId, {
    type: pending.type,
    roomKey: pending.roomKey,
    roomEpoch: pending.roomEpoch,
    applyResponse: pending.applyResponse,
    retryKey: pending.retryKey,
    expiresId
  });
}

function shouldApplyLateResponse(pending) {
  return pending.roomKey
    || pending.type === "auth.token"
    || pending.type === "system.catchup"
    || pending.type === "lobby.rooms";
}

function clearTimedOutPending(requestId = "") {
  if (requestId) {
    const pending = state.timedOutPending.get(requestId);
    if (pending?.expiresId) {
      window.clearTimeout(pending.expiresId);
    }
    state.timedOutPending.delete(requestId);
    return;
  }

  state.timedOutPending.forEach((pending) => {
    if (pending.expiresId) {
      window.clearTimeout(pending.expiresId);
    }
  });
  state.timedOutPending.clear();
}

function rememberRetryRequest(requestId, pending) {
  if (!pending.retryKey) {
    return;
  }

  trackRetryRequest(pending.retryKey, requestId);
}

function trackRetryRequest(retryKey, requestId, expiresAt = Date.now() + retryRequestRetentionMs) {
  if (!retryKey || !requestId) {
    return;
  }

  clearRetryRequest(retryKey);
  if (!Number.isFinite(expiresAt) || expiresAt <= Date.now()) {
    return;
  }
  const expiresId = window.setTimeout(() => {
    const current = state.retryRequests.get(retryKey);
    if (current?.requestId === requestId) {
      state.retryRequests.delete(retryKey);
      persistRetryRequests();
    }
  }, Math.max(0, expiresAt - Date.now()));
  state.retryRequests.set(retryKey, { requestId, expiresId, expiresAt });
  persistRetryRequests();
}

function clearRetryRequest(retryKey, requestId = "") {
  if (!retryKey) {
    return;
  }

  const retry = state.retryRequests.get(retryKey);
  if (!retry || (requestId && retry.requestId !== requestId)) {
    return;
  }
  if (retry.expiresId) {
    window.clearTimeout(retry.expiresId);
  }
  state.retryRequests.delete(retryKey);
  persistRetryRequests();
}

function restoreRetryRequests() {
  let entries = {};
  try {
    entries = JSON.parse(window.sessionStorage?.getItem(retryRequestStorageKey) || "{}");
  } catch {
    return;
  }
  if (!entries || typeof entries !== "object" || Array.isArray(entries)) {
    return;
  }

  const now = Date.now();
  Object.entries(entries).forEach(([retryKey, retry]) => {
    const requestId = typeof retry?.requestId === "string" ? retry.requestId : "";
    const expiresAt = Number(retry?.expiresAt);
    if (requestId && Number.isFinite(expiresAt) && expiresAt > now) {
      trackRetryRequest(retryKey, requestId, expiresAt);
    }
  });
  persistRetryRequests();
}

function persistRetryRequests() {
  try {
    const now = Date.now();
    const entries = {};
    state.retryRequests.forEach((retry, retryKey) => {
      if (retry?.requestId && Number.isFinite(retry.expiresAt) && retry.expiresAt > now) {
        entries[retryKey] = {
          requestId: retry.requestId,
          expiresAt: retry.expiresAt
        };
      }
    });
    window.sessionStorage?.setItem(retryRequestStorageKey, JSON.stringify(entries));
  } catch {
    // Session storage can be unavailable in private or embedded contexts.
  }
}

function commandKey(type, roomKey = "") {
  return `${type}:${roomKey || ""}`;
}

function roomMutationKey(type, roomKey = "") {
  if (!roomKey || !isRoomMutationCommand(type)) {
    return "";
  }
  return `room:${roomKey}`;
}

function isRoomMutationCommand(type) {
  return type.startsWith("tractor.")
    || type.startsWith("seat.")
    || type === "room.settings.update"
    || type === "player.ready"
    || type === "observer.watch";
}

function isRoomMutationPending(roomKey = "") {
  return Boolean(roomKey) && state.pendingRoomMutations.has(`room:${roomKey}`);
}

function isActionPending(type, roomKey = "") {
  return state.pendingActions.has(commandKey(type, roomKey));
}

function hasRoomTransitionPending() {
  for (const key of state.pendingActions) {
    if (key.startsWith("room.join:") || key.startsWith("room.leave:")) {
      return true;
    }
  }
  return false;
}

function isGameInteractionLocked() {
  return !state.authenticated || state.connecting || state.recovering;
}

function beginRoomTransition(roomKey) {
  state.roomEpoch += 1;
  state.transitionRoomKey = roomKey || "";
  return state.roomEpoch;
}

function clearRoomState(advanceEpoch = true) {
  if (advanceEpoch) {
    state.roomEpoch += 1;
  }
  state.currentRoomKey = "";
  state.transitionRoomKey = "";
  state.table = null;
  clearChatState();
  state.emojiTarget = "";
  state.selectedHandIndexes = [];
  state.handSignature = "";
  resetPlayLog();
  clearTrickReviewTimer();
  clearTrumpRevealTimer();
}

async function fetchToken() {
  const tokenUrl = state.bootstrap.tokenUrl;
  const tokenHash = state.bootstrap.tokenHash;
  if (!tokenUrl || !tokenHash) {
    throw new Error("请从论坛的卡牌游戏入口打开游戏。");
  }

  const body = new URLSearchParams({ hash: tokenHash });
  const response = await fetch(tokenUrl, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      "content-type": "application/x-www-form-urlencoded",
      "x-cardgames-hash": tokenHash
    },
    body
  });
  const payload = await response.json();
  if (!response.ok || !payload.success) {
    logClientWarn("token request failed", {
      status: response.status,
      statusText: response.statusText,
      success: payload.success,
      error: payload.error || ""
    });
    throw new Error(payload.error || "获取游戏令牌失败");
  }
  if (!payload.token) {
    logClientWarn("token response omitted token", {
      status: response.status,
      success: payload.success
    });
    throw new Error("获取游戏令牌失败");
  }

  return payload;
}

function applyTokenPayload(payload) {
  state.bootstrap.wsUrl = payload.wsUrl || state.bootstrap.wsUrl;
  state.user = payload.user || state.user;
  state.token = payload.token;
}

async function authenticate(connectionEpoch = state.connectionEpoch) {
  try {
    const response = await sendCommand("auth.token", { payload: { token: state.token, lastSeenSeq: state.lastSeenSeq } });
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      return;
    }
    state.user = response.payload.user;
    state.authenticated = true;
    state.recovering = true;
    scheduleChatRender();
    void requestSkinProfile().catch(() => undefined);
    await requestServerSyncWithBusyRetry(connectionEpoch);
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      return;
    }
    state.recovering = false;
    setStatus("已连接");
    scheduleChatRender();
  } catch (error) {
    if (!isCurrentConnectionAttempt(connectionEpoch)) {
      return;
    }
    logClientError("authentication failed", error);
    state.recovering = false;
    setStatus(error.message || "认证失败");
    scheduleChatRender();
  }
}

async function requestCatchup() {
  if (!state.authenticated) {
    return;
  }

  await sendCommand("system.catchup", { payload: { lastSeenSeq: state.lastSeenSeq } });
}

async function requestRooms() {
  if (!state.authenticated) {
    return;
  }

  const response = await sendCommand("lobby.rooms", { applyResponse: false });
  state.rooms = response.payload.rooms;
  state.lobbyUsers = lobbyUsersFromPayload(response.payload, state.lobbyUsers);
  if (!state.currentRoomKey) {
    scheduleRender();
  }
}

async function requestServerSyncOnce() {
  try {
    await requestCatchup();
  } catch (error) {
    if (isRoomBusyError(error)) {
      throw error;
    }
    await requestRooms();
    if (!state.currentRoomKey) {
      await requestChatHistory().catch(() => undefined);
    }
  }
}

function requestServerSyncWithBusyRetry(connectionEpoch = state.connectionEpoch) {
  if (state.syncPromise && state.syncEpoch === connectionEpoch) {
    return state.syncPromise;
  }

  state.syncEpoch = connectionEpoch;
  const promise = runServerSyncWithBusyRetry(connectionEpoch)
    .finally(() => {
      if (state.syncPromise === promise) {
        state.syncPromise = null;
      }
    });
  state.syncPromise = promise;
  return promise;
}

async function runServerSyncWithBusyRetry(connectionEpoch) {
  while (isCurrentConnectionAttempt(connectionEpoch) && state.authenticated) {
    try {
      await requestServerSyncOnce();
      state.syncRetryAttempt = 0;
      return;
    } catch (error) {
      if (!isRoomBusyError(error)) {
        throw error;
      }
      state.recovering = true;
      setStatus("正在同步");
      scheduleChatRender();
      await sleep(syncRetryDelay(state.syncRetryAttempt++));
    }
  }
}

function syncRetryDelay(attempt) {
  return Math.min(syncRetryMaxMs, syncRetryBaseMs * (2 ** Math.min(Math.max(0, attempt), 4)));
}

function sleep(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

async function requestSkinProfile() {
  if (!state.authenticated) {
    return;
  }

  const response = await sendCommand("profile.skins", { applyResponse: false });
  applySkinProfile(response.payload);
  render();
}

async function selectSkin(skinId) {
  if (!skinId || !state.authenticated) {
    return;
  }

  try {
    const response = await sendCommand("profile.skin.select", {
      applyResponse: false,
      payload: { skinId }
    });
    applySkinProfile(response.payload);
    if (state.user) {
      state.user.selectedSkinId = response.payload.selectedSkinId;
    }
    render();
  } catch (error) {
    reportError(error);
  }
}

async function refreshTable(roomKey) {
  const roomEpoch = state.roomEpoch;
  const response = await sendCommand("tractor.table", { roomKey, roomEpoch, applyResponse: false });
  if (!response.payload?.table || state.roomEpoch !== roomEpoch || state.currentRoomKey !== roomKey) {
    return;
  }

  applyTable(response.payload.table);
}

function sendCommand(type, options = {}) {
  if (!state.ws || state.ws.readyState !== WebSocket.OPEN) {
    logClientWarn("command rejected because websocket is not open", {
      type,
      roomKey: options.roomKey || "",
      readyState: state.ws?.readyState ?? null
    });
    state.connected = false;
    state.authenticated = false;
    render();
    scheduleChatRender();
    void reconnectAfterRestore();
    return Promise.reject(new Error("连接已断开，正在重连"));
  }

  const retryKey = options.retryable === false ? "" : retryRequestKey(type, options);
  const request = requestForCommand(retryKey);
  const requestId = request.requestId;
  if (state.pending.has(requestId)) {
    return Promise.reject(new Error("上一个操作仍在处理中"));
  }
  const mutationKey = roomMutationKey(type, options.roomKey || "");
  if (mutationKey && state.pendingRoomMutations.has(mutationKey)) {
    return Promise.reject(new Error("上一个操作仍在处理中"));
  }
  clearTimedOutPending(requestId);
  const envelope = {
    v: 1,
    requestId,
    type,
    roomKey: options.roomKey,
    retry: request.retry,
    payload: options.payload || {}
  };
  const pendingKey = options.trackPendingAction === false ? "" : commandKey(type, options.roomKey);
  if (pendingKey) {
    state.pendingActions.add(pendingKey);
  }
  if (mutationKey) {
    state.pendingRoomMutations.add(mutationKey);
  }

  const promise = new Promise((resolve, reject) => {
    const timeoutId = window.setTimeout(() => {
      const pending = state.pending.get(requestId);
      if (!pending) {
        return;
      }
      state.pending.delete(requestId);
      clearPending(pending);
      rememberRetryRequest(requestId, pending);
      rememberTimedOutPending(requestId, pending);
      logClientWarn("command timed out", {
        type: pending.type,
        requestId,
        roomKey: pending.roomKey
      });
      pending.reject(new Error("请求超时，请重试"));
    }, options.timeoutMs || commandTimeoutMs);
    state.pending.set(requestId, {
      resolve,
      reject,
      type,
      roomKey: options.roomKey || "",
      roomEpoch: options.roomEpoch ?? state.roomEpoch,
      applyResponse: options.applyResponse !== false,
      pendingKey,
      pendingMutationKey: mutationKey,
      retryKey,
      timeoutId
    });
  });
  try {
    state.ws.send(JSON.stringify(envelope));
  } catch (error) {
    const pending = state.pending.get(requestId);
    if (pending) {
      state.pending.delete(requestId);
      clearPending(pending);
      rememberRetryRequest(requestId, pending);
    }
    logClientError("command send failed", error, {
      type,
      requestId,
      roomKey: options.roomKey || ""
    });
    return Promise.reject(error);
  }
  return promise;
}

function requestForCommand(retryKey) {
  if (retryKey) {
    const retry = state.retryRequests.get(retryKey);
    if (retry?.requestId) {
      if (Number.isFinite(retry.expiresAt) && retry.expiresAt > Date.now()) {
        trackRetryRequest(retryKey, retry.requestId);
        return {
          requestId: retry.requestId,
          retry: true
        };
      }
      clearRetryRequest(retryKey, retry.requestId);
    }
  }

  const requestId = `web-${Date.now()}-${++state.requestSeq}`;
  if (retryKey) {
    trackRetryRequest(retryKey, requestId);
  }
  return {
    requestId,
    retry: false
  };
}

function retryRequestKey(type, options) {
  if (!retryableCommandTypes.has(type)) {
    return "";
  }

  const roomKey = options.roomKey || "";
  const roomEpoch = options.roomEpoch ?? state.roomEpoch;
  const payload = stableStringify(options.payload || {});
  const stateSignature = retryStateSignature(type, roomKey);
  return [type, roomKey, roomEpoch, stateSignature, payload].join("|");
}

function retryStateSignature(type, roomKey) {
  if (!type.startsWith("tractor.")) {
    return "";
  }
  const table = state.table;
  if (!table || table.room?.roomKey !== roomKey) {
    return "";
  }

  const publicState = table.engine?.public || {};
  const trick = publicState.currentTrick || {};
  return stableStringify({
    roomStateVersion: table.room?.stateVersion,
    handId: publicState.handId || "",
    phase: table.phase || publicState.phase || "",
    completedTrickCount: publicState.completedTrickCount,
    trickNumber: trick.trickNumber,
    nextSeatIndex: trick.nextSeatIndex,
    viewerSeatIndex: table.viewer?.seatIndex
  });
}

function stableStringify(value) {
  if (value === undefined) {
    return "undefined";
  }
  if (value === null || typeof value !== "object") {
    return JSON.stringify(value);
  }
  if (Array.isArray(value)) {
    return `[${value.map(stableStringify).join(",")}]`;
  }

  return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${stableStringify(value[key])}`).join(",")}}`;
}

function handleMessage(raw) {
  let message;
  try {
    message = JSON.parse(raw);
  } catch (error) {
    logClientError("invalid server message", error, {
      length: String(raw || "").length
    });
    setStatus("收到无效的服务器消息");
    return;
  }
  if (Number.isInteger(message.seq)) {
    state.lastSeenSeq = Math.max(state.lastSeenSeq, message.seq);
  }

  if (message.requestId && state.pending.has(message.requestId)) {
    const pending = state.pending.get(message.requestId);
    state.pending.delete(message.requestId);
    clearPending(pending);
    if (message.type === "error") {
      logClientWarn("server command error", serverErrorLogDetails(message, pending));
      applyCommandErrorState(message);
      const error = commandError(message);
      if (isRetryableErrorMessage(message)) {
        rememberRetryRequest(message.requestId, pending);
      } else {
        clearRetryRequest(pending.retryKey, message.requestId);
      }
      pending.reject(error);
      return;
    }
    clearRetryRequest(pending.retryKey, message.requestId);
    if (pending.applyResponse && isCurrentPendingMessage(message, pending)) {
      applyServerMessage(message, pending.type, pending);
    }
    pending.resolve(message);
    return;
  }
  if (message.requestId && state.timedOutPending.has(message.requestId)) {
    handleTimedOutMessage(message);
    return;
  }
  if (message.requestId) {
    logClientWarn("unmatched command response ignored", {
      type: message.type || "",
      requestId: message.requestId,
      roomKey: roomKeyFromMessage(message)
    });
    return;
  }

  applyServerMessage(message);
}

function handleTimedOutMessage(message) {
  const pending = state.timedOutPending.get(message.requestId);
  clearTimedOutPending(message.requestId);
  if (!pending) {
    return;
  }
  if (message.type === "error") {
    logClientWarn("late server command error", serverErrorLogDetails(message, pending));
    if (applyCommandErrorState(message) && !isRoomBusyMessage(message)) {
      setStatus(errorMessage(message));
    }
    if (isRetryableErrorMessage(message)) {
      rememberRetryRequest(message.requestId, pending);
    } else {
      clearRetryRequest(pending.retryKey, message.requestId);
    }
    return;
  }
  clearRetryRequest(pending.retryKey, message.requestId);
  if (pending.applyResponse && isCurrentPendingMessage(message, pending)) {
    applyServerMessage(message, pending.type, pending);
    recoverTimedOutStatus(message, pending);
  }
}

function recoverTimedOutStatus(message, pending) {
  if (pending.type === "auth.token") {
    const connectionEpoch = state.connectionEpoch;
    state.recovering = true;
    render();
    scheduleChatRender();
    void requestServerSyncWithBusyRetry(connectionEpoch)
      .then(() => {
        if (!isCurrentConnectionAttempt(connectionEpoch)) {
          return;
        }
        state.recovering = false;
        setStatus("已连接");
        scheduleChatRender();
      })
      .catch((error) => {
        if (!isCurrentConnectionAttempt(connectionEpoch)) {
          return;
        }
        state.recovering = false;
        scheduleChatRender();
        reportError(error);
      });
    return;
  }

  if (pending.type === "room.join") {
    setStatus("已进入房间");
    return;
  }
  if (pending.type === "room.leave") {
    setStatus("已离开房间");
    return;
  }
  if (pending.type.startsWith("tractor.") && message.payload?.table) {
    setStatus(engineCommandStatus(pending.type, message.payload.table));
    return;
  }
  setStatus("已同步");
}

function isCurrentPendingMessage(message, pending) {
  const roomKey = roomKeyFromMessage(message) || pending.roomKey;
  if (isRoomStatePending(pending) && pending.roomEpoch !== state.roomEpoch) {
    return false;
  }
  if (!roomKey) {
    return true;
  }
  if (pending.type === "room.join") {
    return pending.roomKey === roomKey;
  }
  return isCurrentRoomMessage(roomKey, pending);
}

function isRoomStatePending(pending) {
  return Boolean(pending.roomKey) || pending.type === "system.catchup";
}

function isCurrentRoomMessage(roomKey, context = null) {
  if (!roomKey) {
    return true;
  }
  if (context?.roomEpoch !== undefined && context.roomEpoch !== state.roomEpoch) {
    return false;
  }
  if (context?.type === "room.join") {
    return context.roomKey === roomKey;
  }
  if (context?.type === "room.leave") {
    return state.currentRoomKey === roomKey;
  }
  if (state.currentRoomKey) {
    return state.currentRoomKey === roomKey;
  }
  return !state.transitionRoomKey || state.transitionRoomKey === roomKey;
}

function activeChatScope() {
  return state.currentRoomKey ? "room" : "lobby";
}

function activeChatChannelKey() {
  return state.currentRoomKey || lobbyChatChannelKey;
}

function isCurrentChatMessage(payload, context = null) {
  const scope = payload?.scope || (payload?.roomKey ? "room" : "lobby");
  const channelKey = payload?.channelKey || payload?.roomKey || (scope === "lobby" ? lobbyChatChannelKey : "");
  if (scope === "lobby" || channelKey === lobbyChatChannelKey) {
    return !state.currentRoomKey && !state.transitionRoomKey;
  }
  return isCurrentRoomMessage(payload?.roomKey || channelKey, context);
}

function roomKeyFromMessage(message) {
  return message?.payload?.table?.room?.roomKey
    || message?.payload?.room?.roomKey
    || message?.payload?.roomKey
    || message?.payload?.event?.roomKey
    || message?.roomKey
    || "";
}

function isRetryableErrorMessage(message) {
  return message?.type === "error" && message.payload?.retryable === true;
}

function isRoomBusyMessage(message) {
  return message?.type === "error" && message.payload?.code === "room_busy";
}

function isRoomBusyError(error) {
  return error?.code === "room_busy";
}

function commandError(message) {
  const payload = message?.payload || {};
  const error = new Error(errorMessage(message));
  error.code = payload.code || "";
  error.retryable = payload.retryable === true;
  error.details = payload.details;
  return error;
}

function serverErrorLogDetails(message, pending = null) {
  const payload = message?.payload || {};
  return {
    type: pending?.type || message?.type || "",
    requestId: message?.requestId || "",
    roomKey: pending?.roomKey || roomKeyFromMessage(message),
    code: payload.code || "",
    message: payload.message || "",
    retryable: payload.retryable === true,
    details: payload.details
  };
}

function applyCommandErrorState(message) {
  const payload = message?.payload || {};
  if (isRoomBusyMessage(message)) {
    beginRecoverySync();
    return true;
  }

  if (payload.code !== "tractor_dump_failed") {
    return false;
  }

  const failure = dumpFailureFromPayload(payload);
  if (failure?.mustPlayCards?.length && selectHandCardsByIds(failure.mustPlayCards)) {
    refreshSelectionUi();
    return true;
  }
  return false;
}

function beginRecoverySync(status = "正在同步") {
  const connectionEpoch = state.connectionEpoch;
  const existingSync = state.syncPromise && state.syncEpoch === connectionEpoch;
  state.recovering = true;
  setStatus(status);
  scheduleChatRender();
  if (!state.authenticated || existingSync) {
    return;
  }

  void requestServerSyncWithBusyRetry(connectionEpoch)
    .then(() => {
      if (!isCurrentConnectionAttempt(connectionEpoch)) {
        return;
      }
      state.recovering = false;
      setStatus("已同步");
      scheduleChatRender();
    })
    .catch((error) => {
      if (!isCurrentConnectionAttempt(connectionEpoch)) {
        return;
      }
      state.recovering = false;
      scheduleChatRender();
      reportError(error);
    });
}

function applyServerMessage(message, commandType = "", context = null) {
  switch (message.type) {
    case "system.hello":
      setStatus("服务器已就绪");
      break;
    case "auth.accepted":
      state.user = message.payload.user || state.user;
      state.authenticated = true;
      scheduleChatRender();
      playSound("effect/enter_hall_click.mp3");
      break;
    case "profile.skins":
    case "profile.skin.selected":
      applySkinProfile(message.payload);
      if (state.user && message.payload.selectedSkinId) {
        state.user.selectedSkinId = message.payload.selectedSkinId;
      }
      render();
      break;
    case "system.catchup":
      {
        const payload = message.payload || {};
        const nextRoomKey = payload.table?.room?.roomKey || payload.room?.roomKey || "";
        const changedRoom = Boolean(nextRoomKey && state.currentRoomKey && state.currentRoomKey !== nextRoomKey);
        if (changedRoom) {
          clearChatState();
          state.emojiTarget = "";
          state.selectedHandIndexes = [];
          state.handSignature = "";
        }

        state.rooms = payload.rooms || state.rooms;
        state.lobbyUsers = lobbyUsersFromPayload(payload, state.lobbyUsers);
        if (payload.table) {
          applyTable(payload.table, false);
        } else if (payload.room) {
          const roomKey = payload.room.roomKey;
          enterRoomFromServer(roomKey);
          state.table = null;
          state.emojiTarget = "";
          state.selectedHandIndexes = [];
          state.handSignature = "";
          void refreshTable(roomKey).catch(() => undefined);
        } else {
          clearRoomState(Boolean(state.currentRoomKey || state.table));
        }
        if (Array.isArray(payload.chat)) {
          setChatEvents(payload.chat);
        }
        render();
      }
      break;
    case "lobby.rooms":
      state.rooms = message.payload.rooms || [];
      state.lobbyUsers = lobbyUsersFromPayload(message.payload, state.lobbyUsers);
      if (!state.currentRoomKey) {
        scheduleRender();
      }
      break;
    case "lobby.updated":
      state.rooms = message.payload.rooms;
      state.lobbyUsers = lobbyUsersFromPayload(message.payload, state.lobbyUsers);
      if (!state.currentRoomKey) {
        scheduleRender();
      }
      break;
    case "room.recovering":
      beginRecoverySync("正在同步");
      break;
    case "room.left":
      if (!isCurrentRoomMessage(message.payload.roomKey, context)) {
        return;
      }
      clearRoomState();
      playSound("effect/draw.mp3");
      setStatus("已离开房间");
      void requestRooms();
      void requestChatHistory();
      render();
      break;
    case "room.updated":
      if (!isCurrentRoomMessage(message.payload.room?.roomKey, context)) {
        return;
      }
      mergeRoom(message.payload.room);
      enterRoomFromServer(message.payload.room?.roomKey);
      if (state.table?.room?.roomKey === message.payload.room.roomKey) {
        state.table.room = message.payload.room;
      }
      playCommandSound(commandType);
      scheduleChatRender();
      render();
      break;
    case "room.reset":
      if ((message.payload.roomKey || message.payload.room?.roomKey) === state.currentRoomKey) {
        clearRoomState();
        setStatus("房间已重置");
        void requestChatHistory();
      }
      void requestRooms();
      render();
      break;
    case "room.cancelled":
      if ((message.payload.room?.roomKey || message.payload.roomKey) === state.currentRoomKey) {
        state.roomEpoch += 1;
        state.selectedHandIndexes = [];
        state.handSignature = "";
        setStatus("本局已取消");
      }
      void requestRooms();
      render();
      break;
    case "tractor.table":
    case "tractor.table.updated":
      if (!isCurrentRoomMessage(message.payload.table?.room?.roomKey || message.roomKey, context)) {
        return;
      }
      playTableSound(message.payload.table, commandType);
      applyTable(message.payload.table);
      break;
    case "chat.history":
      if (isCurrentChatMessage(message.payload, context)) {
        if (message.payload.roomKey) {
          enterRoomFromServer(message.payload.roomKey);
        }
        setChatEvents(message.payload.events || []);
      }
      break;
    case "chat.event":
      if (isCurrentChatMessage(message.payload.event, context)) {
        if (message.payload.event?.roomKey) {
          enterRoomFromServer(message.payload.event.roomKey);
        }
        appendChatEvent(message.payload.event);
        if (message.payload.event.kind === "emoji") {
          showEmoji(message.payload.event);
        }
      }
      break;
    case "room.emoji":
    case "chat.emoji":
      showEmoji(message.payload);
      break;
    case "error":
      logClientWarn("server pushed error", serverErrorLogDetails(message, context));
      applyCommandErrorState(message);
      setStatus(errorMessage(message));
      break;
    default:
      logClientWarn("unknown server message ignored", {
        type: message.type || "",
        requestId: message.requestId || "",
        roomKey: roomKeyFromMessage(message)
      });
      break;
  }
}

function applyTable(table, shouldRender = true) {
  if (!table?.room?.roomKey) {
    return;
  }

  state.table = table;
  enterRoomFromServer(table.room.roomKey);
  syncSelectedHandIndexes();
  if (shouldRender) {
    scheduleRender();
  }
}

function lobbyUsersFromPayload(payload, fallback = []) {
  if (Array.isArray(payload?.lobbyUsers)) {
    return payload.lobbyUsers;
  }
  if (Array.isArray(payload?.users)) {
    return payload.users;
  }
  if (Array.isArray(payload?.lobby?.users)) {
    return payload.lobby.users;
  }
  if (Array.isArray(payload?.presence?.users)) {
    return payload.presence.users;
  }
  return fallback;
}

function enterRoomFromServer(roomKey) {
  if (!roomKey) {
    return;
  }

  const previousRoomKey = state.currentRoomKey;
  state.currentRoomKey = roomKey;
  if (state.transitionRoomKey === roomKey) {
    state.transitionRoomKey = "";
  }
  if (previousRoomKey !== roomKey) {
    scheduleChatRender();
  }
}

function render() {
  if (state.renderFrameId) {
    cancelUiFrame(state.renderFrameId);
    state.renderFrameId = 0;
  }
  renderTopbar();
  renderSkinSelect();
  renderRooms();
  renderLobbyMinesweeper();
  renderLobbyUsers();
  renderTable();
}

function scheduleRender() {
  if (state.renderFrameId) {
    return;
  }

  state.renderFrameId = requestUiFrame(() => {
    state.renderFrameId = 0;
    render();
  });
}

function renderChatComponent() {
  if (state.chatRenderFrameId) {
    cancelUiFrame(state.chatRenderFrameId);
    state.chatRenderFrameId = 0;
  }
  ensureChatMounted();
  renderEmojiDock();
  renderChatPanel();
}

function scheduleChatRender() {
  if (state.chatRenderFrameId) {
    return;
  }

  state.chatRenderFrameId = requestUiFrame(() => {
    state.chatRenderFrameId = 0;
    renderChatComponent();
  });
}

function requestUiFrame(callback) {
  return window.requestAnimationFrame
    ? window.requestAnimationFrame(callback)
    : window.setTimeout(callback, 0);
}

function cancelUiFrame(frameId) {
  if (window.cancelAnimationFrame) {
    window.cancelAnimationFrame(frameId);
  } else {
    window.clearTimeout(frameId);
  }
}

function ensureChatMounted() {
  if (!state.currentRoomKey) {
    mountLobbyChatPanel();
    return;
  }

  mountTableSidePanels();
}

function renderTopbar() {
  const status = statusText();
  const user = state.user ? state.user.displayName || state.user.username : "";
  const connect = state.connected ? "重新连接" : "连接";
  const sound = state.soundEnabled ? "声音开" : "声音关";
  const soundPressed = state.soundEnabled ? "true" : "false";
  const signature = [status, user, connect, sound, soundPressed].join("\u001f");
  if (state.renderedTopbarSignature === signature) {
    return;
  }

  state.renderedTopbarSignature = signature;
  els.status.textContent = status;
  els.user.textContent = user;
  els.connect.textContent = connect;
  els.sound.textContent = sound;
  els.sound.setAttribute("aria-pressed", soundPressed);
}

function renderRooms() {
  const insideRoom = Boolean(state.currentRoomKey);
  els.roomsPanel.hidden = insideRoom;
  if (insideRoom) {
    if (state.renderedRoomsSignature !== "hidden") {
      els.rooms.replaceChildren();
      state.renderedRoomsSignature = "hidden";
    }
    return;
  }

  if (!state.rooms.length) {
    const minesweeperRoom = lobbyMinesweeperRoomItem(hasRoomTransitionPending() || Boolean(state.transitionRoomKey));
    const signature = `empty|${minesweeperRoom.signature}`;
    if (state.renderedRoomsSignature === signature) {
      return;
    }
    const empty = document.createElement("div");
    empty.className = "empty-state rooms-empty-state";
    empty.textContent = "尚未加载房间。";
    els.rooms.replaceChildren(empty, minesweeperRoom.node);
    state.renderedRoomsSignature = signature;
    return;
  }

  const roomTransitionPending = hasRoomTransitionPending();
  const interactionLocked = isGameInteractionLocked();
  const currentRoomKey = state.currentRoomKey;
  const roomItems = state.rooms.map(roomListItem);
  const minesweeperRoom = lobbyMinesweeperRoomItem(roomTransitionPending || Boolean(state.transitionRoomKey));
  const signature = [
    interactionLocked ? "1" : "0",
    roomTransitionPending ? "1" : "0",
    state.transitionRoomKey,
    roomItems.map((item) => item.signature).join("\u001e"),
    [...state.pendingActions].filter((key) => key.startsWith("room.join:")).sort().join(","),
    minesweeperRoom.signature
  ].join("|");
  if (state.renderedRoomsSignature === signature) {
    return;
  }
  state.renderedRoomsSignature = signature;
  const roomButtons = roomItems.map((item) => {
    const { room, members, membersText, hasMembers } = item;
    const otherRoomLocked = Boolean(currentRoomKey && room.roomKey !== currentRoomKey);
    const gameLabel = roomGameLabel(room);
    const button = document.createElement("button");
    button.type = "button";
    button.className = "room-button";
    button.setAttribute("aria-current", room.roomKey === currentRoomKey ? "true" : "false");
    button.disabled = interactionLocked
      || !room.enabled
      || otherRoomLocked
      || isActionPending("room.join", room.roomKey)
      || roomTransitionPending
      || Boolean(state.transitionRoomKey && state.transitionRoomKey !== room.roomKey);
    button.innerHTML = `
      <span class="room-summary-row">
        <span class="room-game-icon" aria-hidden="true">${escapeHtml(roomGameIconText(room))}</span>
        <span class="room-main">
          <strong>${escapeHtml(room.displayName)}</strong>
          <span class="room-game-label">${escapeHtml(gameLabel)} · ${Number(room.memberCount || 0)}人</span>
        </span>
        <span class="room-status">${escapeHtml(statusTextForRoom(room.status))}${room.enabled ? "" : " 已关闭"}</span>
      </span>
      <span class="room-users ${hasMembers ? "" : "room-users-empty"}" title="${escapeAttribute(membersText)}">
        ${roomMembersListHtml(room, members)}
      </span>
    `;
    button.addEventListener("click", () => joinRoom(room.roomKey));
    return button;
  });
  roomButtons.push(minesweeperRoom.node);
  els.rooms.replaceChildren(...roomButtons);
}

function roomListItem(room) {
  const members = roomMembersForDisplay(room);
  return {
    room,
    members,
    membersText: roomMembersText(room, members),
    hasMembers: members.length > 0,
    signature: roomSignature(room, members)
  };
}

function roomSignature(room, members = roomMembersForDisplay(room)) {
  return [
    room.roomKey,
    room.displayName,
    room.gameType || "",
    room.gameName || room.gameTitle || room.gameLabel || room.gameDisplayName || "",
    room.status,
    room.enabled ? "1" : "0",
    room.memberCount || 0,
    roomMembersSignature(members),
    room.stateVersion || "",
    room.updatedAt || ""
  ].join("\u001f");
}

function lobbyMinesweeperRoomItem(disabled = false) {
  const game = ensureMinesweeperGame();
  const status = minesweeperStatusText(game);
  const open = state.minesweeperExpanded;
  const remainingSafe = Math.max(0, game.cells.length - game.mineCount - game.revealedCount);
  const detail = game.started
    ? `剩${remainingSafe}格 · 标记${game.flagCount}`
    : "单人";
  const signature = [
    "lobby-minesweeper",
    open ? "1" : "0",
    disabled ? "1" : "0",
    status,
    game.started ? "1" : "0",
    game.finished ? "1" : "0",
    game.won ? "1" : "0",
    game.revealedCount,
    game.flagCount
  ].join("\u001f");
  const button = document.createElement("button");
  button.type = "button";
  button.className = "room-button lobby-mini-game-button";
  button.disabled = disabled;
  button.setAttribute("aria-current", "false");
  button.setAttribute("aria-expanded", open ? "true" : "false");
  button.innerHTML = `
    <span class="room-summary-row">
      <span class="room-game-icon lobby-minesweeper-icon" aria-hidden="true">扫雷</span>
      <span class="room-main">
        <strong>扫雷</strong>
        <span class="room-game-label">大厅小游戏 · ${escapeHtml(detail)}</span>
      </span>
      <span class="room-status">${escapeHtml(open ? "打开" : status)}</span>
    </span>
    <span class="room-users room-users-empty">
      <span class="room-user-row">${escapeHtml(game.won ? "已完成" : game.finished ? "新局可重开" : "点击打开")}</span>
    </span>
  `;
  button.addEventListener("click", () => {
    state.minesweeperExpanded = true;
    state.renderedRoomsSignature = "";
    state.renderedMinesweeperSignature = "";
    renderRooms();
    renderLobbyMinesweeper();
  });
  return { node: button, signature };
}

function renderLobbyMinesweeper() {
  if (!els.minesweeperPanel) {
    return;
  }

  const inLobby = !state.currentRoomKey;
  if (!inLobby && state.minesweeperExpanded) {
    state.minesweeperExpanded = false;
  }
  const expanded = inLobby && state.minesweeperExpanded;
  els.lobbyContent?.classList.toggle("lobby-content-minigame-open", expanded);
  els.minesweeperPanel.hidden = !expanded;
  if (!expanded) {
    stopMinesweeperTimer();
    if (state.renderedMinesweeperSignature !== "hidden") {
      els.minesweeperPanel.replaceChildren();
      state.renderedMinesweeperSignature = "hidden";
    }
    return;
  }

  const game = ensureMinesweeperGame();
  syncMinesweeperTimer();
  const signature = minesweeperSignature(game);
  if (state.renderedMinesweeperSignature === signature) {
    return;
  }

  state.renderedMinesweeperSignature = signature;
  els.minesweeperPanel.innerHTML = minesweeperHtml(game);
}

function ensureMinesweeperGame() {
  if (!state.minesweeper) {
    state.minesweeper = newMinesweeperGame();
  }
  return state.minesweeper;
}

function newMinesweeperGame() {
  const cellCount = minesweeperRows * minesweeperCols;
  return {
    rows: minesweeperRows,
    cols: minesweeperCols,
    mineCount: minesweeperMineCount,
    cells: Array.from({ length: cellCount }, (_, index) => ({
      index,
      mine: false,
      adjacent: 0,
      revealed: false,
      flagged: false
    })),
    minesPlaced: false,
    started: false,
    finished: false,
    won: false,
    explodedIndex: -1,
    revealedCount: 0,
    flagCount: 0,
    startTime: 0,
    elapsedSeconds: 0
  };
}

function resetMinesweeper() {
  stopMinesweeperTimer();
  state.minesweeper = newMinesweeperGame();
  state.minesweeperFlagMode = false;
  state.renderedMinesweeperSignature = "";
  state.renderedRoomsSignature = "";
  renderRooms();
  renderLobbyMinesweeper();
}

function minesweeperHtml(game) {
  const remaining = Math.max(0, game.mineCount - game.flagCount);
  const flagPressed = state.minesweeperFlagMode ? "true" : "false";
  return `
    <div class="lobby-minesweeper-header">
      <div class="lobby-minesweeper-heading">
        <div class="lobby-minesweeper-title">扫雷</div>
        <div class="lobby-minesweeper-status">${escapeHtml(minesweeperStatusText(game))}</div>
      </div>
      <div class="lobby-minesweeper-controls">
        <button type="button" class="minesweeper-flag-toggle" data-minesweeper-action="flag" aria-pressed="${flagPressed}">标记</button>
        <button type="button" data-minesweeper-action="restart">新局</button>
        <button type="button" data-minesweeper-action="collapse">收起</button>
      </div>
    </div>
    <div class="minesweeper-stats">
      <span>雷 ${escapeHtml(remaining)}</span>
      <span>时间 <span data-minesweeper-time>${escapeHtml(formatMinesweeperTime(game.elapsedSeconds))}</span></span>
    </div>
    <div class="minesweeper-board" role="grid" aria-label="扫雷" style="--minesweeper-cols: ${game.cols}">
      ${game.cells.map((cell) => minesweeperCellHtml(game, cell)).join("")}
    </div>
  `;
}

function minesweeperCellHtml(game, cell) {
  const classes = [
    "minesweeper-cell",
    cell.revealed ? "is-revealed" : "is-hidden",
    cell.flagged ? "is-flagged" : "",
    cell.mine && (cell.revealed || game.finished) ? "is-mine" : "",
    cell.index === game.explodedIndex ? "is-exploded" : "",
    cell.revealed && cell.adjacent > 0 ? `mine-n${cell.adjacent}` : ""
  ].filter(Boolean).join(" ");
  const content = cell.flagged && !cell.revealed
    ? "⚑"
    : cell.revealed && cell.mine
      ? "✹"
      : cell.revealed && cell.adjacent > 0
        ? String(cell.adjacent)
        : "";
  const row = Math.floor(cell.index / game.cols) + 1;
  const col = (cell.index % game.cols) + 1;
  return `<button type="button" role="gridcell" class="${classes}" data-minesweeper-index="${cell.index}" aria-label="${escapeAttribute(minesweeperCellLabel(game, cell, row, col))}">${escapeHtml(content)}</button>`;
}

function minesweeperCellLabel(game, cell, row, col) {
  const prefix = `第${row}行第${col}列`;
  if (cell.flagged && !cell.revealed) {
    return `${prefix}，已标记`;
  }
  if (!cell.revealed && !game.finished) {
    return `${prefix}，未打开`;
  }
  if (cell.mine) {
    return `${prefix}，地雷`;
  }
  if (cell.adjacent > 0) {
    return `${prefix}，周围${cell.adjacent}颗雷`;
  }
  return `${prefix}，空`;
}

function minesweeperStatusText(game) {
  if (game.won) {
    return "完成";
  }
  if (game.finished) {
    return "踩雷";
  }
  if (game.started) {
    return "进行中";
  }
  return "新局";
}

function minesweeperSignature(game) {
  return [
    state.minesweeperFlagMode ? "1" : "0",
    game.started ? "1" : "0",
    game.finished ? "1" : "0",
    game.won ? "1" : "0",
    game.explodedIndex,
    game.flagCount,
    game.cells.map((cell) => `${cell.revealed ? "r" : ""}${cell.flagged ? "f" : ""}${cell.mine && game.finished ? "m" : ""}${cell.adjacent}`).join("")
  ].join("\u001f");
}

function handleMinesweeperClick(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target || !els.minesweeperPanel?.contains(target) || state.currentRoomKey) {
    return;
  }

  const actionButton = target.closest("[data-minesweeper-action]");
  if (actionButton && els.minesweeperPanel.contains(actionButton)) {
    event.preventDefault();
    const action = actionButton.dataset.minesweeperAction;
    if (action === "restart") {
      resetMinesweeper();
      return;
    }
    if (action === "collapse") {
      state.minesweeperExpanded = false;
      state.renderedRoomsSignature = "";
      state.renderedMinesweeperSignature = "";
      renderRooms();
      renderLobbyMinesweeper();
      return;
    }
    if (action === "flag") {
      state.minesweeperFlagMode = !state.minesweeperFlagMode;
      state.renderedMinesweeperSignature = "";
      renderLobbyMinesweeper();
      return;
    }
  }

  const cellButton = target.closest("[data-minesweeper-index]");
  if (!cellButton || !els.minesweeperPanel.contains(cellButton)) {
    return;
  }

  event.preventDefault();
  const index = Number(cellButton.dataset.minesweeperIndex);
  if (!Number.isInteger(index)) {
    return;
  }
  if (state.minesweeperFlagMode) {
    toggleMinesweeperFlag(index);
  } else {
    revealMinesweeperCell(index);
  }
  state.renderedRoomsSignature = "";
  state.renderedMinesweeperSignature = "";
  renderRooms();
  renderLobbyMinesweeper();
}

function handleMinesweeperContextMenu(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target || !els.minesweeperPanel?.contains(target) || state.currentRoomKey) {
    return;
  }

  const cellButton = target.closest("[data-minesweeper-index]");
  if (!cellButton || !els.minesweeperPanel.contains(cellButton)) {
    return;
  }

  event.preventDefault();
  const index = Number(cellButton.dataset.minesweeperIndex);
  if (!Number.isInteger(index)) {
    return;
  }
  toggleMinesweeperFlag(index);
  state.renderedRoomsSignature = "";
  state.renderedMinesweeperSignature = "";
  renderRooms();
  renderLobbyMinesweeper();
}

function revealMinesweeperCell(index) {
  const game = ensureMinesweeperGame();
  const cell = game.cells[index];
  if (!cell || game.finished || cell.revealed || cell.flagged) {
    return;
  }

  if (!game.minesPlaced) {
    placeMinesweeperMines(game, index);
  }
  if (!game.started) {
    game.started = true;
    game.startTime = Date.now();
    startMinesweeperTimer();
  }

  if (cell.mine) {
    cell.revealed = true;
    game.finished = true;
    game.won = false;
    game.explodedIndex = index;
    game.cells.forEach((candidate) => {
      if (candidate.mine) {
        candidate.revealed = true;
      }
    });
    updateMinesweeperElapsed();
    stopMinesweeperTimer();
    return;
  }

  revealMinesweeperArea(game, index);
  if (game.revealedCount >= game.cells.length - game.mineCount) {
    finishMinesweeperWin(game);
  }
}

function revealMinesweeperArea(game, startIndex) {
  const queue = [startIndex];
  const queued = new Set(queue);
  while (queue.length) {
    const index = queue.shift();
    const cell = game.cells[index];
    if (!cell || cell.revealed || cell.flagged || cell.mine) {
      continue;
    }

    cell.revealed = true;
    game.revealedCount += 1;
    if (cell.adjacent !== 0) {
      continue;
    }

    minesweeperNeighborIndexes(index, game.rows, game.cols).forEach((neighborIndex) => {
      if (!queued.has(neighborIndex)) {
        queued.add(neighborIndex);
        queue.push(neighborIndex);
      }
    });
  }
}

function toggleMinesweeperFlag(index) {
  const game = ensureMinesweeperGame();
  const cell = game.cells[index];
  if (!cell || game.finished || cell.revealed) {
    return;
  }

  cell.flagged = !cell.flagged;
  game.flagCount += cell.flagged ? 1 : -1;
}

function finishMinesweeperWin(game) {
  game.finished = true;
  game.won = true;
  game.explodedIndex = -1;
  game.flagCount = game.mineCount;
  game.cells.forEach((cell) => {
    cell.flagged = cell.mine;
  });
  updateMinesweeperElapsed();
  stopMinesweeperTimer();
}

function placeMinesweeperMines(game, safeIndex) {
  const preferredSafe = new Set([safeIndex, ...minesweeperNeighborIndexes(safeIndex, game.rows, game.cols)]);
  const candidatesWithoutNeighborhood = game.cells
    .map((cell) => cell.index)
    .filter((index) => !preferredSafe.has(index));
  const candidates = candidatesWithoutNeighborhood.length >= game.mineCount
    ? candidatesWithoutNeighborhood
    : game.cells.map((cell) => cell.index).filter((index) => index !== safeIndex);

  shuffleInPlace(candidates);
  candidates.slice(0, game.mineCount).forEach((index) => {
    game.cells[index].mine = true;
  });

  game.cells.forEach((cell) => {
    cell.adjacent = cell.mine
      ? 0
      : minesweeperNeighborIndexes(cell.index, game.rows, game.cols)
        .filter((neighborIndex) => game.cells[neighborIndex]?.mine)
        .length;
  });
  game.minesPlaced = true;
}

function minesweeperNeighborIndexes(index, rows, cols) {
  const row = Math.floor(index / cols);
  const col = index % cols;
  const neighbors = [];
  for (let nextRow = row - 1; nextRow <= row + 1; nextRow += 1) {
    for (let nextCol = col - 1; nextCol <= col + 1; nextCol += 1) {
      if (nextRow === row && nextCol === col) {
        continue;
      }
      if (nextRow >= 0 && nextRow < rows && nextCol >= 0 && nextCol < cols) {
        neighbors.push(nextRow * cols + nextCol);
      }
    }
  }
  return neighbors;
}

function shuffleInPlace(items) {
  for (let index = items.length - 1; index > 0; index -= 1) {
    const swapIndex = Math.floor(Math.random() * (index + 1));
    [items[index], items[swapIndex]] = [items[swapIndex], items[index]];
  }
}

function syncMinesweeperTimer() {
  const game = state.minesweeper;
  if (!game || state.currentRoomKey || !game.started || game.finished) {
    stopMinesweeperTimer();
    return;
  }
  startMinesweeperTimer();
}

function startMinesweeperTimer() {
  const game = state.minesweeper;
  if (!game || game.finished || state.currentRoomKey) {
    return;
  }
  if (!game.startTime) {
    game.startTime = Date.now() - game.elapsedSeconds * 1000;
  }
  if (state.minesweeperTimerId) {
    return;
  }

  state.minesweeperTimerId = window.setInterval(() => {
    const changed = updateMinesweeperElapsed();
    if (changed) {
      updateMinesweeperElapsedText();
    }
  }, 1000);
}

function stopMinesweeperTimer() {
  const game = state.minesweeper;
  if (game?.started && !game.finished) {
    updateMinesweeperElapsed();
    game.startTime = 0;
  }
  if (!state.minesweeperTimerId) {
    return;
  }

  window.clearInterval(state.minesweeperTimerId);
  state.minesweeperTimerId = 0;
}

function updateMinesweeperElapsed() {
  const game = state.minesweeper;
  if (!game || !game.started || !game.startTime) {
    return false;
  }

  const nextSeconds = Math.max(0, Math.floor((Date.now() - game.startTime) / 1000));
  if (game.elapsedSeconds === nextSeconds) {
    return false;
  }
  game.elapsedSeconds = nextSeconds;
  return true;
}

function updateMinesweeperElapsedText() {
  const timeElement = els.minesweeperPanel?.querySelector("[data-minesweeper-time]");
  if (timeElement && state.minesweeper) {
    timeElement.textContent = formatMinesweeperTime(state.minesweeper.elapsedSeconds);
  }
}

function formatMinesweeperTime(seconds) {
  const safeSeconds = Math.max(0, Number.isFinite(Number(seconds)) ? Math.floor(Number(seconds)) : 0);
  const minutes = Math.floor(safeSeconds / 60);
  const remainder = safeSeconds % 60;
  return `${minutes}:${String(remainder).padStart(2, "0")}`;
}

function renderLobbyUsers() {
  if (!els.lobbyUsersPanel) {
    return;
  }

  const inLobby = !state.currentRoomKey;
  els.lobbyUsersPanel.hidden = !inLobby;
  if (!inLobby) {
    if (state.renderedLobbyUsersSignature !== "hidden") {
      els.lobbyUsersPanel.replaceChildren();
      state.renderedLobbyUsersSignature = "hidden";
    }
    return;
  }

  const users = lobbyUsersForDisplay();
  const signature = users.map(lobbyUserSignature).join("\u001e") || "empty";
  if (state.renderedLobbyUsersSignature === signature) {
    return;
  }

  state.renderedLobbyUsersSignature = signature;
  const title = document.createElement("div");
  title.className = "lobby-users-title";
  title.textContent = `大厅用户 ${users.length}`;

  const list = document.createElement("div");
  list.className = "lobby-users-list";
  if (!users.length) {
    const empty = document.createElement("div");
    empty.className = "lobby-users-empty";
    empty.textContent = "暂无用户";
    list.append(empty);
  } else {
    list.append(...users.map(lobbyUserNode));
  }

  els.lobbyUsersPanel.replaceChildren(title, list);
}

function lobbyUserSignature(user) {
  return [
    user.userId || "",
    user.username || "",
    user.displayName || "",
    user.connected === false ? "0" : "1",
    user.connectionCount || 1
  ].join("\u001f");
}

function lobbyUserNode(user) {
  const item = document.createElement("div");
  item.className = `lobby-user-row ${user.connected === false ? "lobby-user-offline" : ""}`;
  item.title = compactUserName(user);

  const name = document.createElement("span");
  name.className = "lobby-user-name";
  name.textContent = compactUserName(user) || "玩家";

  const detail = document.createElement("span");
  detail.className = "lobby-user-detail";
  detail.textContent = user.connected === false
    ? "离线"
    : Number(user.connectionCount || 1) > 1
      ? `${Number(user.connectionCount)}个窗口`
      : "";

  item.append(name, detail);
  return item;
}

function lobbyUsersForDisplay() {
  const users = normalizeLobbyUsers(state.lobbyUsers);
  if (users.length || !state.authenticated || state.currentRoomKey || !state.user) {
    return users;
  }
  return normalizeLobbyUsers([state.user]);
}

function normalizeLobbyUsers(users) {
  const byUserId = new Map();
  for (const candidate of Array.isArray(users) ? users : []) {
    const user = candidate?.user || candidate;
    const userId = Number(user?.userId);
    const name = compactUserName(user);
    if (!Number.isFinite(userId) || !name) {
      continue;
    }

    const connectionCount = Number(user?.connectionCount || candidate?.connectionCount || 1);
    const normalizedConnectionCount = Number.isFinite(connectionCount) ? connectionCount : 1;
    const existing = byUserId.get(userId);
    if (existing) {
      existing.connectionCount += normalizedConnectionCount;
      existing.connected = existing.connected !== false || user?.connected !== false;
      continue;
    }

    byUserId.set(userId, {
      ...user,
      userId,
      connectionCount: normalizedConnectionCount,
      connected: user?.connected !== false
    });
  }

  return [...byUserId.values()].sort((left, right) => {
    const nameCompare = compactUserName(left).localeCompare(compactUserName(right), "zh-Hans-u-co-pinyin", { sensitivity: "base" });
    return nameCompare !== 0 ? nameCompare : left.userId - right.userId;
  });
}

function roomMembersText(room, members = roomMembersForDisplay(room)) {
  if (!members.length) {
    const count = Number(room?.memberCount || 0);
    return count > 0 ? `${count}人在房间` : "空房";
  }

  return members.map((member) => {
    const offline = member.connected === false ? " 离线" : "";
    return `${member.label} ${compactUserName(member.user)}${offline}`;
  }).join(" · ");
}

function roomMembersListHtml(room, members = roomMembersForDisplay(room)) {
  if (!members.length) {
    return `<span class="room-user-row">${escapeHtml(roomMembersText(room, members))}</span>`;
  }

  return members.map((member) => {
    const offline = member.connected === false ? " 离线" : "";
    return `<span class="room-user-row">${escapeHtml(member.label)} ${escapeHtml(compactUserName(member.user))}${escapeHtml(offline)}</span>`;
  }).join("");
}

function roomMembersSignature(members) {
  return members.map((member) => [
    member.label,
    member.user?.userId || "",
    member.user?.username || "",
    member.user?.displayName || "",
    member.connected === false ? "0" : "1"
  ].join(":")).join(",");
}

function roomMembersForDisplay(room) {
  const members = [];
  const seen = new Set();
  for (const seat of room?.seats || []) {
    if (!seat?.user) {
      continue;
    }
    const userId = Number(seat.user.userId);
    if (Number.isFinite(userId)) {
      seen.add(userId);
    }
    members.push({
      label: seatNumberLabel(seat.seatIndex),
      user: seat.user,
      connected: seat.connected
    });
  }

  for (const observer of room?.observers || []) {
    if (!observer?.user) {
      continue;
    }
    const userId = Number(observer.user.userId);
    if (Number.isFinite(userId) && seen.has(userId)) {
      continue;
    }
    if (Number.isFinite(userId)) {
      seen.add(userId);
    }
    members.push({
      label: Number.isFinite(Number(observer.watchedSeatIndex)) ? `看${seatNumberLabel(observer.watchedSeatIndex)}` : "旁观",
      user: observer.user,
      connected: observer.connected
    });
  }

  return members;
}

function compactUserName(user) {
  return String(user?.username || user?.displayName || "").trim();
}

function renderRoomDeadlineControls(room = currentRoom()) {
  if (!room) {
    els.deadlineControls.hidden = true;
    if (state.renderedDeadlineSignature !== "hidden") {
      els.deadlineControls.replaceChildren();
      state.renderedDeadlineSignature = "hidden";
    }
    return;
  }

  const roomKey = room.roomKey;
  const owner = isRoomOwner(room);
  const pending = isActionPending("room.settings.update", roomKey) || isRoomMutationPending(roomKey);
  const disabled = isGameInteractionLocked() || hasRoomTransitionPending() || Boolean(state.transitionRoomKey) || pending || !owner;
  const discardSeconds = roomDeadlineSeconds(room, "tractorDiscardDeadlineSeconds", "tractor_discard_deadline_seconds");
  const playSeconds = roomDeadlineSeconds(room, "tractorPlayDeadlineSeconds", "tractor_play_deadline_seconds");
  const reviewSeconds = roomReviewSeconds(room);
  const signature = [
    roomKey,
    owner ? "1" : "0",
    disabled ? "1" : "0",
    pending ? "1" : "0",
    discardSeconds,
    playSeconds,
    reviewSeconds
  ].join("|");

  els.deadlineControls.hidden = false;
  if (state.renderedDeadlineSignature === signature) {
    return;
  }
  state.renderedDeadlineSignature = signature;
  els.deadlineControls.innerHTML = `
    <form class="room-deadline-form">
      <span class="room-deadline-title">硬超时</span>
      <label>
        <span>埋牌</span>
        <input name="discard" type="number" min="0" max="600" step="1" inputmode="numeric" value="${discardSeconds}" ${disabled ? "disabled" : ""} />
        <span>秒</span>
      </label>
      <label>
        <span>出牌</span>
        <input name="play" type="number" min="0" max="600" step="1" inputmode="numeric" value="${playSeconds}" ${disabled ? "disabled" : ""} />
        <span>秒</span>
      </label>
      <label>
        <span>本墩回顾</span>
        <input name="review" type="number" min="0" max="30" step="1" inputmode="numeric" value="${reviewSeconds}" ${disabled ? "disabled" : ""} />
        <span>秒</span>
      </label>
      ${owner ? `<button type="submit" ${disabled ? "disabled" : ""}>保存</button>` : ""}
    </form>
  `;

  const form = els.deadlineControls.querySelector("form");
  form?.addEventListener("submit", (event) => {
    event.preventDefault();
    updateRoomDeadlineSettings(roomKey, form);
  });
}

function currentRoom() {
  if (state.currentRoomKey && state.table?.room?.roomKey === state.currentRoomKey) {
    return state.table.room;
  }
  return state.rooms.find((room) => room.roomKey === state.currentRoomKey) || null;
}

function isRoomOwner(room) {
  if (state.table?.room?.roomKey === room.roomKey && state.table.viewer?.owner) {
    return true;
  }

  const ownerUserId = Number(room.owner?.userId);
  const viewerUserId = Number(state.user?.userId);
  return Number.isInteger(ownerUserId) && Number.isInteger(viewerUserId) && ownerUserId === viewerUserId;
}

function roomDeadlineSeconds(room, camelKey, snakeKey) {
  const seconds = Number(room.settings?.[camelKey] ?? room.settings?.[snakeKey] ?? 0);
  return Number.isInteger(seconds) && seconds >= 0 && seconds <= 600 ? seconds : 0;
}

function roomReviewSeconds(room) {
  const seconds = Number(room.settings?.tractorTrickReviewSeconds ?? room.settings?.tractor_trick_review_seconds ?? 5);
  return Number.isInteger(seconds) && seconds >= 0 && seconds <= 30 ? seconds : 5;
}

function deadlineInputSeconds(value) {
  const seconds = Number(value);
  if (!Number.isFinite(seconds)) {
    return 0;
  }
  return Math.max(0, Math.min(600, Math.trunc(seconds)));
}

function reviewInputSeconds(value) {
  const seconds = Number(value);
  if (!Number.isFinite(seconds)) {
    return 5;
  }
  return Math.max(0, Math.min(30, Math.trunc(seconds)));
}

function updateRoomDeadlineSettings(roomKey, form) {
  const room = currentRoom();
  if (!roomKey || !room || room.roomKey !== roomKey || !isRoomOwner(room) || isGameInteractionLocked() || hasRoomTransitionPending() || isRoomMutationPending(roomKey) || isActionPending("room.settings.update", roomKey)) {
    return;
  }

  const discardSeconds = deadlineInputSeconds(form.elements.discard?.value);
  const playSeconds = deadlineInputSeconds(form.elements.play?.value);
  const reviewSeconds = reviewInputSeconds(form.elements.review?.value);
  void sendCommand("room.settings.update", {
    roomKey,
    roomEpoch: state.roomEpoch,
    payload: {
      tractorDiscardDeadlineSeconds: discardSeconds,
      tractorPlayDeadlineSeconds: playSeconds,
      tractorTrickReviewSeconds: reviewSeconds
    }
  })
    .then(() => {
      setStatus("房间设置已更新");
    })
    .catch(reportError);
}

function renderSkinSelect() {
  const profile = state.skinProfile;
  els.skin.hidden = !profile;
  if (!profile) {
    if (state.renderedSkinSignature !== "hidden") {
      els.skin.replaceChildren();
      state.renderedSkinSignature = "hidden";
    }
    return;
  }

  const disabled = isGameInteractionLocked();
  const owned = new Set(profile.ownedSkinIds || []);
  const skins = profile.skins || [];
  const signature = [
    disabled ? "1" : "0",
    profile.selectedSkinId || "",
    skins.map((skin) => [
      skin.skinId,
      skinDisplayName(skin),
      owned.has(skin.skinId) ? "1" : "0"
    ].join("\u001f")).join("\u001e")
  ].join("|");
  if (state.renderedSkinSignature === signature) {
    els.skin.disabled = disabled;
    if (els.skin.value !== (profile.selectedSkinId || "")) {
      els.skin.value = profile.selectedSkinId || "";
    }
    return;
  }

  state.renderedSkinSignature = signature;
  els.skin.disabled = disabled;
  els.skin.replaceChildren(...skins.map((skin) => {
    const option = document.createElement("option");
    option.value = skin.skinId;
    option.textContent = skinDisplayName(skin);
    option.disabled = !owned.has(skin.skinId);
    return option;
  }));
  els.skin.value = profile.selectedSkinId || "";
}

function renderTable() {
  const table = state.table;
  els.leave.hidden = !state.currentRoomKey;
  if (els.tablePanel) {
    els.tablePanel.hidden = !table && !state.currentRoomKey;
  }
  if (!table) {
    clearTurnTimer();
    clearTrickReviewTimer();
    clearTrumpRevealTimer();
    renderRoomDeadlineControls(null);
    els.title.textContent = "大厅";
    if (state.currentRoomKey) {
      renderTableEmptyState();
    } else {
      tableActionsElement = null;
      disconnectPrivateHandOverlapObserver();
      mountLobbyChatPanel();
      if (!els.table.hasChildNodes || els.table.hasChildNodes()) {
        els.table.replaceChildren();
      }
      state.renderedTableSections.clear();
      state.renderedTableSectionSignatures.clear();
      state.renderedPlayLogSignature = "";
    }
    return;
  }

  els.title.textContent = table.room.displayName;
  renderRoomDeadlineControls(table.room);
  const seatsByVisual = new Map((table.room?.seats || []).map((seat) => [visualSeatIndexFor(table, seat.seatIndex), seat]));
  const seatByVisual = (visualSeatIndex) => seatsByVisual.get(visualSeatIndex);
  const seatHtmlByVisual = (visualSeatIndex) => {
    const seat = seatByVisual(visualSeatIndex);
    return seat ? seatHtml(table, seat) : "";
  };
  const observers = table.room.observers.map((observer) => {
    const watched = observer.watchedSeatIndex === undefined ? "" : ` 正在观看${seatLabel(observer.watchedSeatIndex)}`;
    return `<span class="observer-pill">${escapeHtml(observer.user.displayName)}${watched}</span>`;
  }).join("");
  const currentTrick = table.engine?.public?.currentTrick;
  const bottomHolder = bottomHolderLabel(table);
  const bottomHolderTitle = table.phase === "burying_bottom" ? "埋牌者" : "待埋牌者";
  const trumpDeclarer = trumpDeclarerLabel(table);
  const trickLabel = isTrickReviewActive(table)
    ? `第 ${table.review.trickNumber} 墩结束，稍候继续`
    : currentTrick
      ? `第 ${currentTrick.trickNumber} 墩，轮到${seatLabel(currentTrick.nextSeatIndex ?? currentTrick.winnerSeatIndex)}`
      : table.engineReady ? "" : "等待中";
  const statusCornerText = tableStatusCornerText(table, trickLabel);
  const grid = ensureTableShell();
  const seatTop = seatByVisual(2);
  const seatLeft = seatByVisual(3);
  const seatRight = seatByVisual(1);
  const seatBottom = seatByVisual(0);
  const statusInfoSignature = tableStatusInfoSignature(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder);
  const removedTopInfoLeft = updateDirectTableSection(grid, "info-left", ":scope > .table-info-left", "");
  const removedTopInfoRight = updateDirectTableSection(grid, "info-right", ":scope > .table-info-right", "");
  const ownerCornerChanged = updateDirectTableSectionLazy(els.table, "owner-corner", ":scope > .table-owner-corner", roomOwnerLabel(table), () => tableOwnerCornerHtml(table));
  const statusCornerChanged = updateDirectTableSectionLazy(els.table, "status-corner", ":scope > .table-status-corner", statusCornerText, () => tableStatusCornerHtml(statusCornerText));
  const centerChanged = updateDirectTableSectionLazy(grid, "center", ":scope > .table-center", cardDeskSignature(table), () => `<div class="table-center">${cardDeskHtml(table)}</div>`);
  const seatBottomChanged = updateDirectTableSectionLazy(grid, "seat-bottom", ":scope > .seat-0", seatSectionSignature(table, seatBottom), () => seatHtmlByVisual(0));
  const leftSeatSlot = ensureTableChatStack(grid).querySelector('[data-table-seat-slot="left"]');
  const seatLeftChanged = updateTableSectionHtml("seat-left", leftSeatSlot, seatHtmlByVisual(3), seatSectionSignature(table, seatLeft));
  const stackRightChanged = updateDirectTableSectionLazy(grid, "stack-right", ":scope > .table-side-stack-right", [seatSectionSignature(table, seatRight), statusInfoSignature].join("\u001e"), () => `
    <div class="table-side-stack table-side-stack-right">
      <div class="table-side-panel table-side-panel-right" data-table-panel="status">${tableStatusInfoHtml(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder)}</div>
      ${seatHtmlByVisual(1)}
    </div>
  `);
  const tableSectionChanged = [
    removedTopInfoLeft,
    removedTopInfoRight,
    ownerCornerChanged,
    statusCornerChanged,
    updateDirectTableSectionLazy(grid, "seat-top", ":scope > .seat-2", seatSectionSignature(table, seatTop), () => seatHtmlByVisual(2)),
    centerChanged,
    seatLeftChanged,
    stackRightChanged,
    seatBottomChanged,
    updateTableSectionHtml("observers", els.table.querySelector(":scope > .observer-list"), observers)
  ].some(Boolean);
  if (tableSectionChanged || !els.chatPanel?.isConnected) {
    mountTableSidePanels();
  }
  syncTableActions(table);
  syncPrivateHandOverlapObserver();

  els.leave.disabled = false;
  els.leave.title = isViewerInActiveHand(table)
    ? "离开后本局会暂停，其他用户可补位继续。"
    : "";
  if (stackRightChanged) {
    syncTurnTimer();
  }
  syncTrickReviewTimer(table);
  syncTrumpRevealTimer(table);
}

function renderTableEmptyState() {
  const html = `
    <div class="table-grid table-grid-empty">
      <div class="table-side-stack table-side-stack-left" data-table-static="chat-stack">
        <div class="table-side-seat-slot" data-table-seat-slot="left"></div>
        <div class="table-side-panel table-side-panel-left" data-table-panel="chat"></div>
      </div>
      <div class="table-center"><div class="empty-state">正在载入牌桌。</div></div>
    </div>
    <div class="observer-list"></div>
  `;
  const rendered = state.renderedTableSections.get("empty") === html
    && els.table.querySelector(":scope > .table-grid-empty")
    && els.table.querySelector('[data-table-panel="chat"]');

  tableActionsElement = null;
  disconnectPrivateHandOverlapObserver();
  if (!rendered) {
    els.table.innerHTML = html;
    state.renderedTableSections.clear();
    state.renderedTableSectionSignatures.clear();
    state.renderedTableSections.set("empty", html);
    state.renderedPlayLogSignature = "";
  }
  mountTableSidePanels();
}

function ensureTableShell() {
  let grid = els.table.querySelector(":scope > .table-grid");
  const observerList = els.table.querySelector(":scope > .observer-list");
  if (grid && observerList && !grid.classList.contains("table-grid-empty")) {
    ensureTableChatStack(grid);
    state.renderedTableSections.delete("empty");
    return grid;
  }

  tableActionsElement = null;
  disconnectPrivateHandOverlapObserver();
  els.table.innerHTML = `
    <div class="table-grid">
      <div class="table-side-stack table-side-stack-left" data-table-static="chat-stack">
        <div class="table-side-seat-slot" data-table-seat-slot="left"></div>
        <div class="table-side-panel table-side-panel-left" data-table-panel="chat"></div>
      </div>
    </div>
    <div class="observer-list"></div>
  `;
  state.renderedTableSections.clear();
  state.renderedTableSectionSignatures.clear();
  grid = els.table.querySelector(":scope > .table-grid");
  ensureTableChatStack(grid);
  return grid;
}

function ensureTableChatStack(grid) {
  let stack = grid?.querySelector(":scope > .table-side-stack-left");
  if (stack?.querySelector('[data-table-seat-slot="left"]') && stack.querySelector('[data-table-panel="chat"]')) {
    return stack;
  }

  const seatHtml = stack?.querySelector(":scope > .seat-3")?.outerHTML
    || stack?.querySelector('[data-table-seat-slot="left"]')?.innerHTML
    || "";
  const chatPanel = els.chatPanel && stack?.contains(els.chatPanel) ? els.chatPanel : null;
  const emojiPanel = els.emojiPanel && stack?.contains(els.emojiPanel) ? els.emojiPanel : null;
  const next = htmlToElement(`
    <div class="table-side-stack table-side-stack-left" data-table-static="chat-stack">
      <div class="table-side-seat-slot" data-table-seat-slot="left">${seatHtml}</div>
      <div class="table-side-panel table-side-panel-left" data-table-panel="chat"></div>
    </div>
  `);
  if (stack) {
    stack.replaceWith(next);
  } else {
    grid.prepend(next);
  }
  const chatSlot = next.querySelector('[data-table-panel="chat"]');
  if (chatPanel) {
    chatSlot.appendChild(chatPanel);
  }
  if (emojiPanel) {
    chatSlot.appendChild(emojiPanel);
  }
  state.renderedTableSections.delete("stack-left");
  state.renderedTableSectionSignatures.delete("stack-left");
  return next;
}

function updateDirectTableSectionLazy(container, key, selector, signature, buildHtml) {
  const existing = container?.querySelector(selector);
  if (existing && state.renderedTableSectionSignatures.get(key) === signature) {
    return false;
  }

  return updateDirectTableSection(container, key, selector, buildHtml(), signature);
}

function updateDirectTableSection(container, key, selector, html, signature = html) {
  const existing = container?.querySelector(selector);
  if (!html) {
    existing?.remove();
    state.renderedTableSections.delete(key);
    state.renderedTableSectionSignatures.delete(key);
    return Boolean(existing);
  }
  if (existing && state.renderedTableSections.get(key) === html && state.renderedTableSectionSignatures.get(key) === signature) {
    return false;
  }

  const next = htmlToElement(html);
  if (!next) {
    existing?.remove();
    state.renderedTableSections.delete(key);
    state.renderedTableSectionSignatures.delete(key);
    return Boolean(existing);
  }
  if (existing) {
    existing.replaceWith(next);
  } else {
    container.appendChild(next);
  }
  state.renderedTableSections.set(key, html);
  state.renderedTableSectionSignatures.set(key, signature);
  return true;
}

function updateTableSectionHtml(key, element, html, signature = html) {
  if (!element || (state.renderedTableSections.get(key) === html && state.renderedTableSectionSignatures.get(key) === signature)) {
    return false;
  }

  element.innerHTML = html;
  state.renderedTableSections.set(key, html);
  state.renderedTableSectionSignatures.set(key, signature);
  return true;
}

function htmlToElement(html) {
  const template = document.createElement("template");
  template.innerHTML = html.trim();
  return template.content.firstElementChild;
}

function tableInfoLeftSignature(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder) {
  const publicState = table.engine?.public || {};
  return [
    publicState.rank,
    trumpLabel(publicState.trump),
    trumpDeclarer,
    bottomHolderTitle,
    bottomHolder
  ].join("|");
}

function tableInfoRightSignature(table) {
  const publicState = table.engine?.public || {};
  const turn = table.turn || {};
  return [
    finiteNumber(publicState.handSummary?.attackingScore, finiteNumber(publicState.score, 0)),
    publicState.trumpRevealAt || "",
    turn.seatIndex ?? "",
    turn.deadlineAt || "",
    turn.startedAt || "",
    turn.countdownSeconds || "",
    table.review?.until || "",
    activePauseSignature(table)
  ].join("|");
}

function tableStatusInfoSignature(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder) {
  return [
    tableInfoLeftSignature(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder),
    tableInfoRightSignature(table)
  ].join("\u001e");
}

function cardDeskSignature(table) {
  const summary = table.engine?.public?.handSummary;
  return [
    visibleTrickSignature(table),
    summary ? stableStringify(summary) : "",
    activePauseSignature(table),
    table.phase || "",
    actionSignature(action(table, "tractor.start")),
    pendingUiSignature(table.room?.roomKey || "")
  ].join("|");
}

function seatSectionSignature(table, seat) {
  if (!seat) {
    return "missing";
  }

  const user = seat.user || {};
  const privateCards = privateHandCardsForSeat(table, seat.seatIndex).map(({ card }) => card.id ?? card).join(",");
  const bottomCards = bottomPileCardsForSeat(table, seat.seatIndex).map(({ card }) => card.id ?? card).join(",");
  const playerRank = seatPlayerRank(table, seat.seatIndex);
  return [
    table.room?.roomKey || "",
    table.phase || "",
    table.engineReady ? "1" : "0",
    table.engine?.public?.handId || "",
    table.engine?.public?.bottomHolderSeatIndex ?? "",
    table.viewer?.role || "",
    table.viewer?.seatIndex ?? "",
    table.viewer?.watchedSeatIndex ?? "",
    table.viewer?.owner ? "1" : "0",
    table.room?.owner?.userId ?? "",
    seat.seatIndex,
    visualSeatIndexFor(table, seat.seatIndex),
    user.userId ?? "",
    user.displayName || "",
    user.username || "",
    user.avatarUrl || "",
    user.selectedSkinId || user.skinInUse || user.skin || "",
    seat.ready ? "1" : "0",
    seat.connected ? "1" : "0",
    isActiveEngineSeat(table, seat.seatIndex) ? "1" : "0",
    isSeatTurn(table, seat.seatIndex) ? "1" : "0",
    seatSideLabel(table, seat.seatIndex),
    playerRank ?? "",
    privateCards,
    bottomCards,
    trumpCandidateSignature(table, seat),
    seatControlsSignature(table, seat)
  ].join("|");
}

function trumpCandidateSignature(table, seat) {
  if (!isPrivateHandSeat(table, seat.seatIndex)) {
    return "";
  }

  const makeTrumpAction = action(table, "tractor.makeTrump");
  if (!makeTrumpAction.enabled) {
    return "off";
  }

  const publicState = table.engine?.public || {};
  return [
    "on",
    publicState.rank ?? "",
    publicState.trump?.exposure || "none"
  ].join(":");
}

function seatControlsSignature(table, seat) {
  const roomKey = table.room?.roomKey || "";
  const isViewerSeat = Number(table.viewer?.seatIndex) === Number(seat.seatIndex);
  const parts = [
    state.authenticated ? "1" : "0",
    state.connecting ? "1" : "0",
    state.recovering ? "1" : "0",
    state.transitionRoomKey,
    hasRoomTransitionPending() ? "1" : "0",
    isRoomMutationPending(roomKey) ? "1" : "0"
  ];

  if (!seat.user) {
    parts.push(actionSignature(action(table, "seat.claim")));
    parts.push(isActionPending("seat.claim", roomKey) ? "claiming" : "");
  }
  if (isViewerSeat) {
    parts.push(actionSignature(action(table, "player.ready")));
    parts.push(actionSignature(action(table, "seat.release")));
    parts.push(isActionPending("player.ready", roomKey) ? "readying" : "");
    parts.push(isActionPending("seat.release", roomKey) ? "releasing" : "");
  }
  if (seat.user && table.viewer?.role === "observer") {
    parts.push(actionSignature(action(table, "observer.watch")));
    parts.push(isActionPending("observer.watch", roomKey) ? "watching" : "");
  }
  if (seat.user && !seat.connected && table.viewer?.owner) {
    parts.push(actionSignature(action(table, "seat.remove")));
    parts.push(isActionPending("seat.remove", roomKey) ? "removing" : "");
  }

  return parts.join(":");
}

function visibleTrickSignature(table) {
  const trick = visibleTrick(table);
  if (!trick) {
    return "";
  }

  return [
    trick.trickNumber ?? "",
    trick.leaderSeatIndex ?? "",
    trick.nextSeatIndex ?? "",
    trick.winnerSeatIndex ?? "",
    trick.points ?? "",
    trick.pointsAwarded ? "1" : "0",
    (trick.plays || []).map((play) => [
      play.seatIndex,
      play.userId,
      play.points || 0,
      (play.cards || []).map((card) => card.id ?? card).join(",")
    ].join(":")).join(";")
  ].join("|");
}

function activePauseSignature(table) {
  const pause = activePauseDetails(table);
  if (!pause) {
    return "";
  }

  return `${pause.reason}:${(pause.seatIndexes || []).join(",")}`;
}

function actionsSignature(table) {
  return (table.actions || []).map(actionSignature).join(";");
}

function actionSignature(item) {
  if (!item) {
    return "";
  }

  return [
    item.type || "",
    item.enabled ? "1" : "0",
    item.reason || "",
    item.ready === undefined ? "" : item.ready ? "1" : "0",
    item.count ?? "",
    Array.isArray(item.seatIndexes) ? item.seatIndexes.join(",") : ""
  ].join(":");
}

function pendingUiSignature(roomKey) {
  return [
    state.authenticated ? "1" : "0",
    state.connecting ? "1" : "0",
    state.recovering ? "1" : "0",
    state.transitionRoomKey,
    [...state.pendingActions].filter((key) => !roomKey || key.endsWith(`:${roomKey}`)).sort().join(","),
    [...state.pendingRoomMutations].filter((key) => !roomKey || key === `room:${roomKey}`).sort().join(",")
  ].join("|");
}

function mountTableSidePanels() {
  const chatSlot = els.table.querySelector('[data-table-panel="chat"]');
  if (chatSlot) {
    if (els.chatPanel && els.chatPanel.parentElement !== chatSlot) {
      chatSlot.appendChild(els.chatPanel);
      scheduleChatScrollToBottom();
    }
    if (els.emojiPanel && els.emojiPanel.parentElement !== chatSlot) {
      chatSlot.appendChild(els.emojiPanel);
    }
  }
}

function mountLobbyChatPanel() {
  if (els.lobbyChatSlot && els.chatPanel && els.chatPanel.parentElement !== els.lobbyChatSlot) {
    els.lobbyChatSlot.appendChild(els.chatPanel);
    scheduleChatScrollToBottom();
  }
}

function resetPlayLog(roomKey = "") {
  state.playLogEntries = [];
  state.playLogKeys = new Set();
  state.playLogRoomKey = roomKey;
  state.playLogHandId = "";
  state.playLogExportKey = "";
  state.playLogExportLoading = false;
  state.playLogExportError = "";
  state.renderedPlayLogSignature = "";
}

function updatePlayLogFromTable(table) {
  const roomKey = table?.room?.roomKey || "";
  const publicState = table?.engine?.public;
  const handId = publicState?.handId || "";
  if (roomKey !== state.playLogRoomKey || (handId && handId !== state.playLogHandId)) {
    resetPlayLog(roomKey);
    state.playLogHandId = handId;
  }
  if (!publicState) {
    return;
  }

  recordTrickForPlayLog(table, publicState.lastCompletedTrick, true);
  recordTrickForPlayLog(table, publicState.currentTrick, false);
  recordHandSummaryForPlayLog(table);
  ensurePlayLogExport(table);
}

function recordTrickForPlayLog(table, trick, completed) {
  const plays = trick?.plays || [];
  if (!Number.isFinite(Number(trick?.trickNumber)) || !plays.length) {
    return;
  }

  plays.forEach((play) => {
    const cards = play.cards || [];
    const key = [
      state.playLogHandId || table.engine?.public?.handId || table.room?.roomKey || "hand",
      trick.trickNumber,
      play.seatIndex,
      cards.map(playLogCardKey).join("-")
    ].join(":");
    addPlayLogEntry({
      key,
      tone: completed && Number(play.seatIndex) === Number(trick.winnerSeatIndex) ? "winner" : "",
      text: `${seatUserLabel(table, play.seatIndex) || seatLabel(play.seatIndex)}：${cards.map(playLogCardLabel).join(" ")}`
    });
  });

  if (!completed || !Number.isFinite(Number(trick.winnerSeatIndex))) {
    return;
  }

  const points = Number(trick.points || 0);
  const pointText = points > 0 ? `，${points}分` : "";
  addPlayLogEntry({
    key: `${state.playLogHandId || table.room?.roomKey || "hand"}:${trick.trickNumber}:result`,
    tone: trick.pointsAwarded ? "score" : "result",
    text: `第 ${trick.trickNumber} 墩：${seatUserLabel(table, trick.winnerSeatIndex) || seatLabel(trick.winnerSeatIndex)}赢${pointText}`
  });
}

function recordHandSummaryForPlayLog(table) {
  const summary = table?.engine?.public?.handSummary;
  if (!summary) {
    return;
  }

  addPlayLogEntry({
    key: `${state.playLogHandId || table.room?.roomKey || "hand"}:summary`,
    tone: "result",
    text: handOutcomeText(summary, table)
  });
}

function ensurePlayLogExport(table) {
  if (!table?.engine?.public?.handSummary) {
    return;
  }

  const key = playLogExportKeyFor(table);
  if (!key || state.playLogExportKey === key || (state.playLogExportLoading && state.playLogExportKey === key)) {
    return;
  }

  state.playLogExportKey = key;
  state.playLogExportLoading = true;
  state.playLogExportError = "";
  void fetchPlayLogExport(table, key);
}

async function fetchPlayLogExport(table, key) {
  try {
    const url = playLogExportUrl(table);
    if (!url) {
      throw new Error("missing_replay_export_url");
    }

    const response = await fetch(url, {
      credentials: "same-origin",
      headers: {
        "Accept": "application/json"
      }
    });
    if (!response.ok) {
      throw new Error(`replay_export_status_${response.status}`);
    }

    const data = await response.json();
    if (!Array.isArray(data?.events)) {
      throw new Error("invalid_replay_export");
    }
    if (playLogExportKeyFor(state.table) !== key) {
      return;
    }

    importPlayLogExport(data.events, state.table || table, key);
  } catch (error) {
    if (playLogExportKeyFor(state.table) === key) {
      state.playLogExportLoading = false;
      state.playLogExportError = error instanceof Error ? error.message : "replay_export_failed";
      renderPlayLogPanel();
    }
  }
}

function importPlayLogExport(events, table, key) {
  const publicState = table?.engine?.public;
  const roomKey = table?.room?.roomKey || "";
  const handId = publicState?.handId || "";
  resetPlayLog(roomKey);
  state.playLogHandId = handId;

  events.forEach((event) => {
    if ((event.eventType || event.event_type) !== "tractor.cards_played") {
      return;
    }

    const payload = event.payload || event.payloadJson || event.payload_json || {};
    const eventPublicState = payload.public || {};
    if (handId && eventPublicState.handId !== handId) {
      return;
    }

    const eventTable = {
      ...table,
      engine: {
        ...(table.engine || {}),
        public: eventPublicState
      }
    };
    recordTrickForPlayLog(eventTable, eventPublicState.currentTrick, false);
    recordTrickForPlayLog(eventTable, eventPublicState.lastCompletedTrick, true);
  });

  recordHandSummaryForPlayLog(table);
  state.playLogExportKey = key;
  state.playLogExportLoading = false;
  state.playLogExportError = "";
  renderPlayLogPanel();
}

function playLogExportKeyFor(table) {
  const roomKey = table?.room?.roomKey || "";
  const handId = table?.engine?.public?.handId || "";
  return roomKey && handId ? `${roomKey}:${handId}` : "";
}

function playLogExportUrl(table) {
  const base = state.bootstrap.roundLogUrl || state.bootstrap.replayExportUrl || defaultRoundLogUrl();
  const roomKey = table?.room?.roomKey || "";
  const handId = table?.engine?.public?.handId || "";
  if (!base || !roomKey || !handId) {
    return "";
  }

  const url = new URL(base, window.location.href);
  url.searchParams.set("roomKey", roomKey);
  url.searchParams.set("handId", handId);
  return url.toString();
}

function addPlayLogEntry(entry) {
  if (!entry.key) {
    return;
  }
  if (state.playLogKeys.has(entry.key)) {
    const existing = state.playLogEntries.find((item) => item.key === entry.key);
    if (existing && entry.tone) {
      existing.tone = entry.tone;
    }
    return;
  }

  state.playLogKeys.add(entry.key);
  state.playLogEntries.push(entry);
  if (state.playLogEntries.length <= 80) {
    return;
  }

  const removed = state.playLogEntries.splice(0, state.playLogEntries.length - 80);
  removed.forEach((item) => state.playLogKeys.delete(item.key));
}

function playLogCardKey(card) {
  const id = Number(card?.id ?? card);
  return Number.isInteger(id) ? String(id) : cardLabelText(card);
}

function playLogCardLabel(card) {
  const id = Number(card?.id ?? card);
  if (!Number.isInteger(id)) {
    return cardLabelText(card);
  }
  return cardLabelText(card && typeof card === "object" ? card : { id });
}

function renderPlayLogPanel() {
  if (!els.log) {
    return;
  }

  const roomKey = state.currentRoomKey || "";
  els.log.hidden = !roomKey;
  if (!roomKey) {
    els.log.replaceChildren();
    state.renderedPlayLogSignature = "";
    return;
  }

  const exportKey = playLogExportKeyFor(state.table);
  const canShowLog = Boolean(state.table?.engine?.public?.handSummary);
  const exportReady = canShowLog && exportKey && state.playLogExportKey === exportKey && !state.playLogExportLoading && !state.playLogExportError;
  const entries = canShowLog ? state.playLogEntries.slice(-80) : [];
  const signature = [
    roomKey,
    exportKey,
    canShowLog ? "1" : "0",
    exportReady ? "1" : "0",
    state.playLogExportLoading ? "1" : "0",
    state.playLogExportError,
    entries.map((entry) => `${entry.key}\u001f${entry.tone || ""}\u001f${entry.text}`).join("\u001e")
  ].join("|");
  if (state.renderedPlayLogSignature === signature) {
    return;
  }
  state.renderedPlayLogSignature = signature;

  let entryHtml = '<div class="play-log-empty">本局结束后显示</div>';
  if (canShowLog && state.playLogExportLoading) {
    entryHtml = '<div class="play-log-empty">正在加载完整出牌记录...</div>';
  } else if (canShowLog && state.playLogExportError) {
    entryHtml = entries.length
      ? `<div class="play-log-empty" title="${escapeAttribute(state.playLogExportError)}">显示已收到的出牌记录</div>${playLogEntriesHtml(entries)}`
      : `<div class="play-log-empty" title="${escapeAttribute(state.playLogExportError)}">完整出牌记录暂不可用</div>`;
  } else if (exportReady) {
    entryHtml = entries.length ? playLogEntriesHtml(entries) : '<div class="play-log-empty">暂无出牌记录</div>';
  }

  els.log.innerHTML = `
    <div class="play-log-title">出牌记录</div>
    <div class="play-log-list">${entryHtml}</div>
  `;
  const list = els.log.querySelector(".play-log-list");
  if (list) {
    list.scrollTop = list.scrollHeight;
  }
}

function playLogEntriesHtml(entries) {
  return entries.map((entry) => `
    <div class="play-log-entry ${entry.tone ? `play-log-${entry.tone}` : ""}">
      ${escapeHtml(entry.text)}
    </div>
  `).join("");
}

function tableOwnerCornerHtml(table) {
  const owner = roomOwnerLabel(table);
  return owner ? `<div class="table-owner-corner">房主 ${escapeHtml(owner)}</div>` : "";
}

function tableStatusCornerHtml(trickLabel) {
  return trickLabel ? `<div class="table-status-corner">${escapeHtml(trickLabel)}</div>` : "";
}

function tableStatusCornerText(table, trickLabel) {
  return trickLabel || phaseText(table?.phase || table?.engine?.public?.phase);
}

function tableStatusInfoHtml(table, trickLabel, trumpDeclarer, bottomHolderTitle, bottomHolder) {
  const trumpText = trumpLabel(table.engine?.public?.trump);

  return `
    <div class="table-info table-info-status">
      <p class="table-trump-line">级牌 ${escapeHtml(rankLabel(table.engine?.public?.rank))} · ${escapeHtml(trumpText)}</p>
      ${trumpDeclarer ? `<p class="table-declarer-line">亮牌 ${escapeHtml(trumpDeclarer)}</p>` : ""}
      <div class="table-facts">
        ${bottomHolder ? `<span>${escapeHtml(bottomHolderTitle)} ${escapeHtml(bottomHolder)}</span>` : ""}
      </div>
      ${tableScoreboardHtml(table)}
      ${trumpRevealTimerHtml(table)}
      ${turnTimerHtml(table)}
    </div>
  `;
}

function cardDeskHtml(table) {
  const summary = handSummaryHtml(table);
  const startControl = summary ? "" : deskStartControlHtml(table);
  const pauseNotice = summary || startControl ? "" : deskPauseNoticeHtml(table);
  const centerContent = summary || pauseNotice || startControl;
  return `
    <div class="card-desk ${centerContent ? "card-desk-has-summary" : ""}" aria-label="牌桌出牌区">
      ${deskPlayZoneHtml(table, 2, "top")}
      ${deskPlayZoneHtml(table, 3, "left")}
      <div class="desk-center-content" ${centerContent ? "" : 'aria-hidden="true"'}>${centerContent}</div>
      ${deskPlayZoneHtml(table, 1, "right")}
      ${deskPlayZoneHtml(table, 0, "bottom")}
    </div>
  `;
}

function deskPauseNoticeHtml(table) {
  if (!isTablePaused(table)) {
    return "";
  }

  return `<p class="table-paused desk-paused">${escapeHtml(pauseText(table))}</p>`;
}

function deskStartControlHtml(table) {
  if (table.phase !== "waiting_for_players" && table.phase !== "finished") {
    return "";
  }

  const startAction = ownerStartActionHtml(table);
  if (!startAction) {
    return "";
  }

  const waitingText = handSummaryWaitingText(table);
  return `
    <div class="desk-start-panel">
      ${waitingText ? `<div class="hand-summary-waiting">${escapeHtml(waitingText)}</div>` : ""}
      <div class="hand-summary-actions">${startAction}</div>
    </div>
  `;
}

function deskPlayZoneHtml(table, visualSeatIndex, position) {
  const seat = (table.room?.seats || []).find((candidate) => visualSeatIndexFor(table, candidate.seatIndex) === visualSeatIndex);
  const play = seat ? deskPlayForSeat(table, seat.seatIndex) : null;
  const playCards = play?.cards || [];
  const cards = playCards.map((card) => cardFaceHtml(card, "played-card desk-card")).join("");
  const points = Number(play?.points || 0);
  const label = points > 0 ? `本墩得分 ${points}` : "";
  const playerLabel = seat && visualSeatIndex !== 0 ? deskPlayerLabel(seat) : "";
  const cardStyle = `--desk-card-overlap: ${deskCardOverlapPx(playCards.length)}px`;

  return `
    <div class="desk-play desk-play-${position} ${cards ? "desk-play-has-cards" : "desk-play-empty"} ${seat && isSeatTurn(table, seat.seatIndex) ? "desk-play-turn" : ""}" title="${escapeAttribute(label)}">
      ${playerLabel ? `<span class="desk-player-label">${escapeHtml(playerLabel)}</span>` : ""}
      <div class="desk-play-cards" style="${cardStyle}">${cards}</div>
    </div>
  `;
}

function deskPlayerLabel(seat) {
  return seat.user?.username || seat.user?.displayName || seatLabel(seat.seatIndex);
}

function deskPlayForSeat(table, seatIndex) {
  const trick = visibleTrick(table);
  return (trick?.plays || []).find((candidate) => Number(candidate.seatIndex) === Number(seatIndex)) || null;
}

function handCardOverlapPx(count) {
  return overlapPxForCount({
    count,
    cardWidth: handCardWidthPx(),
    availableWidth: handAvailableWidthPx(),
    gap: 0,
    minVisibleWidth: handMinVisibleWidthPx()
  });
}

function syncPrivateHandOverlapObserver() {
  const hand = els.table.querySelector(".seat-0 .seat-hand-private");
  if (hand === privateHandElement) {
    applyPrivateHandOverlap(hand);
    return;
  }

  disconnectPrivateHandOverlapObserver(false);
  if (!hand) {
    return;
  }

  privateHandElement = hand;
  applyPrivateHandOverlap(hand);
  if (typeof ResizeObserver === "undefined") {
    requestUiFrame(() => {
      if (!hand.isConnected || hand !== privateHandElement) {
        return;
      }
      state.privateHandAvailableWidth = Math.max(0, hand.clientWidth - handPaddingWidthPx(hand));
      applyPrivateHandOverlap(hand);
    });
    return;
  }

  privateHandResizeObserver = new ResizeObserver((entries) => {
    const entry = entries[entries.length - 1];
    const width = Number(entry?.contentRect?.width || 0);
    if (width > 0) {
      state.privateHandAvailableWidth = width;
    }
    scheduleApplyPrivateHandOverlap(hand);
  });
  privateHandResizeObserver.observe(hand);
}

function disconnectPrivateHandOverlapObserver(resetWidth = true) {
  if (state.privateHandResizeFrameId) {
    cancelUiFrame(state.privateHandResizeFrameId);
    state.privateHandResizeFrameId = 0;
  }
  privateHandResizeObserver?.disconnect();
  privateHandResizeObserver = null;
  privateHandElement = null;
  state.privateHandOverlapValue = "";
  if (resetWidth) {
    state.privateHandAvailableWidth = 0;
  }
}

function scheduleApplyPrivateHandOverlap(hand) {
  if (state.privateHandResizeFrameId) {
    return;
  }

  state.privateHandResizeFrameId = requestUiFrame(() => {
    state.privateHandResizeFrameId = 0;
    if (!hand.isConnected || hand !== privateHandElement) {
      return;
    }
    applyPrivateHandOverlap(hand);
  });
}

function applyPrivateHandOverlap(hand) {
  if (!hand?.isConnected) {
    return;
  }

  const count = Number(hand.dataset.handCount || 0);
  const overlap = `${handCardOverlapPx(count)}px`;
  if (state.privateHandOverlapValue === overlap) {
    return;
  }
  hand.style.setProperty("--hand-overlap", overlap);
  state.privateHandOverlapValue = overlap;
}

function handPaddingWidthPx(hand) {
  const style = window.getComputedStyle(hand);
  return pxValue(style.paddingLeft) + pxValue(style.paddingRight);
}

function deskCardOverlapPx(count) {
  return overlapPxForCount({
    count,
    cardWidth: deskCardWidthPx(),
    availableWidth: deskAvailableWidthPx(),
    gap: 4,
    minVisibleWidth: 12
  });
}

function overlapPxForCount({ count, cardWidth, availableWidth, gap, minVisibleWidth }) {
  const cardCount = Number(count);
  if (!Number.isFinite(cardCount) || cardCount <= 1) {
    return "0";
  }

  const naturalWidth = (cardCount * cardWidth) + ((cardCount - 1) * gap);
  if (naturalWidth <= availableWidth) {
    return "0";
  }

  const requiredOverlap = (naturalWidth - availableWidth) / (cardCount - 1);
  const maxOverlap = Math.max(0, cardWidth - minVisibleWidth + gap);
  return Math.min(maxOverlap, requiredOverlap + 0.5).toFixed(2);
}

function handCardWidthPx() {
  const width = viewportWidthPx();
  return isCompactLayout() ? 52 : clampNumber(width * 0.052, 46, 68);
}

function deskCardWidthPx() {
  const width = viewportWidthPx();
  return isCompactLayout() ? 42 : clampNumber(width * 0.046, 42, 62);
}

function handAvailableWidthPx() {
  if (state.privateHandAvailableWidth > 0) {
    return Math.max(96, state.privateHandAvailableWidth);
  }

  const width = viewportWidthPx();
  return Math.max(128, isCompactLayout() ? width - 64 : Math.min(width - 220, 920));
}

function handMinVisibleWidthPx() {
  return isCompactLayout() ? 2 : 6;
}

function deskAvailableWidthPx() {
  const width = viewportWidthPx();
  const deskWidth = isCompactLayout() ? Math.min(width - 36, 340) : Math.min(width - 360, 620);
  return Math.max(140, deskWidth * 0.5);
}

function isCompactLayout() {
  return viewportWidthPx() <= 780;
}

function viewportWidthPx() {
  const width = Number(window.innerWidth || document.documentElement?.clientWidth || 1024);
  return Number.isFinite(width) ? Math.max(320, width) : 1024;
}

function clampNumber(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function pxValue(value) {
  const number = Number.parseFloat(value);
  return Number.isFinite(number) ? number : 0;
}

function tableScoreboardHtml(table) {
  const publicState = table.engine?.public;
  if (!publicState) {
    return "";
  }

  const attackingScore = finiteNumber(publicState.handSummary?.attackingScore, finiteNumber(publicState.score, 0));
  return `
    <div class="table-scoreboard" aria-label="当前比分">
      <div class="table-score table-score-attacking">
        <span class="table-score-label">抓分方</span>
        <strong>${escapeHtml(String(attackingScore))}</strong>
      </div>
    </div>
  `;
}

function finiteNumber(value, fallback) {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

function roomOwnerLabel(table) {
  const owner = table?.room?.owner;
  return owner?.displayName || owner?.username || "";
}

function seatHtml(table, seat) {
  const user = seat.user;
  const occupied = Boolean(user);
  const isViewerSeat = table.viewer.seatIndex === seat.seatIndex;
  const isOwner = table.room.owner?.userId && user?.userId === table.room.owner.userId;
  const visualSeatIndex = visualSeatIndexFor(table, seat.seatIndex);
  const replacementSeat = isReplacementSeat(table, seat);
  const side = seatSideLabel(table, seat.seatIndex);
  const baseMeta = replacementSeat
    ? "等待补位"
    : occupied
    ? `${seat.connected ? "在线" : "离线"} · ${seat.ready ? "已准备" : "未准备"}${isOwner ? " · 房主" : ""}`
    : "空位";
  const meta = side ? `${side} · ${baseMeta}` : baseMeta;
  const actions = seatActionsHtml(table, seat, isViewerSeat);
  const seatTurn = isSeatTurn(table, seat.seatIndex);
  const activeSeat = isActiveEngineSeat(table, seat.seatIndex);
  const trick = seatTrickHtml(table, seat.seatIndex);
  const hand = seatHandHtml(table, seat.seatIndex);
  const rank = seatRankHtml(table, seat.seatIndex);
  const tableActions = isViewerSeat ? tableActionsHtml(table) : "";
  const playActions = isViewerSeat
    ? `<div class="seat-play-actions"><div class="table-actions" data-actions-signature="${escapeAttribute(tableActionsRenderSignature(table))}">${tableActions}</div></div>`
    : "";

  return `
    <div class="seat seat-${visualSeatIndex} ${isViewerSeat ? "seat-viewer seat-has-table-actions" : ""} ${seat.connected ? "" : "seat-offline"} ${replacementSeat ? "seat-replacement" : ""} ${seatTurn ? "seat-turn" : ""} ${activeSeat ? "seat-active-hand" : ""}" data-seat-index="${seat.seatIndex}">
      ${seatAvatarHtml(table, seat)}
      <div class="seat-info">
        <div class="seat-label-row">
          <span class="seat-number">${escapeHtml(seatNumberLabel(seat.seatIndex))}</span>
          ${rank}
        </div>
        <div class="seat-name">${occupied ? escapeHtml(user.displayName) : seatLabel(seat.seatIndex)}</div>
      </div>
      <div class="seat-meta">${escapeHtml(meta)}</div>
      <div class="seat-actions">${actions}</div>
      ${trick}
      ${playActions}
      ${hand}
    </div>
  `;
}

function seatRankHtml(table, seatIndex) {
  const rank = seatPlayerRank(table, seatIndex);
  if (rank === undefined) {
    return "";
  }

  const label = `打${rankLabel(rank)}`;
  return `<span class="seat-rank" title="${escapeAttribute(`当前${label}`)}">${escapeHtml(label)}</span>`;
}

function seatPlayerRank(table, seatIndex) {
  const rank = playerForSeat(table.engine?.public || {}, seatIndex)?.rank;
  const numericRank = Number(rank);
  return Number.isInteger(numericRank) ? numericRank : undefined;
}

function seatAvatarHtml(table, seat) {
  const user = seat.user;
  const seatIndex = seat.seatIndex;
  const fallback = skinUrlForSeat(user, seatIndex);
  const avatar = isForumAvatarUrl(user?.avatarUrl)
    ? `<img class="seat-avatar seat-forum-avatar" src="${escapeAttribute(user.avatarUrl)}" data-fallback-src="${escapeAttribute(fallback)}" alt="" loading="lazy" decoding="async" />`
    : `<img class="seat-avatar seat-skin" src="${escapeAttribute(fallback)}" alt="" loading="lazy" decoding="async" />`;

  const watched = Number(table.viewer?.watchedSeatIndex) === Number(seatIndex);
  if (watched) {
    return `
      <span class="seat-avatar-frame" title="正在观看该玩家手牌" aria-label="正在观看该玩家手牌">
        ${avatar}
      </span>
    `;
  }

  if (!canWatchSeat(table, seat)) {
    return avatar;
  }

  return `
    <button class="seat-avatar-button" data-action="observer.watch" data-seat="${seatIndex}" type="button" title="点击观看该玩家手牌" aria-label="观看该玩家手牌">
      ${avatar}
    </button>
  `;
}

function isForumAvatarUrl(url) {
  const value = String(url || "");
  return value !== "" && !/(^|\/)no_avatar(?:_hd)?\.(?:gif|png|jpe?g|webp)(?:[?#].*)?$/i.test(value);
}

function seatSideLabel(table, seatIndex) {
  const publicState = table.engine?.public;
  const player = playerForSeat(publicState, seatIndex);
  const defendingTeam = defendingTeamForHand(publicState);
  if (!player?.team || !defendingTeam) {
    return "";
  }

  return player.team === defendingTeam ? "庄家方" : "抓分方";
}

function defendingTeamForHand(publicState) {
  if (!publicState) {
    return "";
  }
  if (publicState.handSummary?.defendingTeam) {
    return publicState.handSummary.defendingTeam;
  }

  const starterSeatIndex = Number(publicState.starterSeatIndex ?? publicState.dealerSeatIndex);
  return playerForSeat(publicState, starterSeatIndex)?.team || "";
}

function playerForSeat(publicState, seatIndex) {
  const numericSeatIndex = Number(seatIndex);
  if (!publicState || !Number.isInteger(numericSeatIndex)) {
    return null;
  }

  return (publicState.players || []).find((player) => Number(player.seatIndex) === numericSeatIndex) || null;
}

function visualSeatIndexFor(table, seatIndex) {
  const perspectiveSeatIndex = tablePerspectiveSeatIndex(table);
  const seatCount = Math.max(1, table.room.seats.length || table.room.seatCount || 4);
  return modulo(Number(seatIndex) - perspectiveSeatIndex, seatCount);
}

function tablePerspectiveSeatIndex(table) {
  if (Number.isInteger(table.viewer?.seatIndex)) {
    return table.viewer.seatIndex;
  }
  if (Number.isInteger(table.viewer?.watchedSeatIndex)) {
    return table.viewer.watchedSeatIndex;
  }
  return 0;
}

function modulo(value, divisor) {
  return ((value % divisor) + divisor) % divisor;
}

function seatActionsHtml(table, seat, isViewerSeat) {
  const roomKey = table.room.roomKey;
  const disabled = isGameInteractionLocked() || Boolean(state.transitionRoomKey) || hasRoomTransitionPending() || isRoomMutationPending(roomKey);
  if (!seat.user && isSeatClaimable(table, seat.seatIndex)) {
    const replacementSeat = isReplacementSeat(table, seat);
    return `<button data-action="seat.claim" data-seat="${seat.seatIndex}" type="button" ${disabled || isActionPending("seat.claim", roomKey) ? "disabled" : ""} title="${replacementSeat ? "接替该座位继续本手牌" : ""}">${replacementSeat ? "补位" : "坐下"}</button>`;
  }
  if (isViewerSeat) {
    const readyAction = action(table, "player.ready");
    const releaseAction = action(table, "seat.release");
    return `
      <button data-action="player.ready" data-seat="${seat.seatIndex}" type="button" ${readyAction.enabled && !disabled && !isActionPending("player.ready", roomKey) ? "" : "disabled"}>
        ${seat.ready ? "取消准备" : "准备"}
      </button>
      <button data-action="seat.release" data-seat="${seat.seatIndex}" type="button" ${releaseAction.enabled && !disabled && !isActionPending("seat.release", roomKey) ? "" : "disabled"}>离座</button>
    `;
  }
  if (seat.user && !seat.connected && table.viewer?.owner && isSeatRemovable(table, seat.seatIndex)) {
    return `<button data-action="seat.remove" data-seat="${seat.seatIndex}" type="button" ${disabled || isActionPending("seat.remove", roomKey) ? "disabled" : ""} title="移除离线玩家，释放座位">移除</button>`;
  }
  return "";
}

function canWatchSeat(table, seat) {
  if (!seat.user || table.viewer.role !== "observer") {
    return false;
  }

  const roomKey = table.room.roomKey;
  return action(table, "observer.watch").enabled
    && !isGameInteractionLocked()
    && !state.transitionRoomKey
    && !hasRoomTransitionPending()
    && !isRoomMutationPending(roomKey)
    && !isActionPending("observer.watch", roomKey);
}

function skinUrlForSeat(user, seatIndex) {
  const skinName = user?.selectedSkinId || user?.skinInUse || user?.skin || defaultSkins[Math.abs(Number(user?.userId ?? seatIndex)) % defaultSkins.length] || "skin_questionmark.webp";
  const fileName = skinName.includes(".") ? skinName : `${skinName}.webp`;
  return `${state.assetBaseUrl}/tractor/skin/${encodeURIComponent(fileName)}`;
}

function action(table, type) {
  if (!table || !Array.isArray(table.actions)) {
    return disabledAction;
  }

  let actionsByType = tableActionCache.get(table);
  if (!actionsByType) {
    actionsByType = new Map(table.actions.map((item) => [item.type, item]));
    tableActionCache.set(table, actionsByType);
  }
  return actionsByType.get(type) || disabledAction;
}

function isSeatClaimable(table, seatIndex) {
  const claimAction = action(table, "seat.claim");
  if (!claimAction.enabled) {
    return false;
  }
  if (!Array.isArray(claimAction.seatIndexes)) {
    return true;
  }
  return claimAction.seatIndexes.includes(seatIndex);
}

function isSeatRemovable(table, seatIndex) {
  const removeAction = action(table, "seat.remove");
  if (!removeAction.enabled) {
    return false;
  }
  if (!Array.isArray(removeAction.seatIndexes)) {
    return true;
  }
  return removeAction.seatIndexes.includes(seatIndex);
}

function isReplacementSeat(table, seat) {
  return Boolean(!seat.user && table.engineReady && isActiveEngineSeat(table, seat.seatIndex));
}

function isActiveEngineSeat(table, seatIndex) {
  return (table.engine?.public?.players || []).some((player) => player.seatIndex === seatIndex);
}

function isViewerInActiveHand(table) {
  return Number.isInteger(table.viewer?.seatIndex) && isActiveEngineSeat(table, table.viewer.seatIndex);
}

function isTablePaused(table) {
  return Boolean(activePauseDetails(table));
}

function isTrickReviewActive(table) {
  const untilMs = Date.parse(table.review?.until || "");
  return Boolean(table.review?.active && Number.isFinite(untilMs) && untilMs > Date.now());
}

function pauseText(table) {
  const pause = activePauseDetails(table);
  const labels = pause?.seatIndexes?.map((seatIndex) => seatUserLabel(table, seatIndex) || seatLabel(seatIndex)).filter(Boolean) || [];
  const target = labels.length ? labels.join("、") : "玩家";
  if (pause?.reason === "empty_active_seat") {
    return `游戏暂停，等待${target}补位后继续。`;
  }
  if (pause?.reason === "disconnected_active_seat") {
    return `游戏暂停，等待${target}重连或离开。`;
  }
  if (pause?.reason === "unready_active_seat") {
    return `游戏暂停，等待${target}准备。`;
  }

  return "游戏暂停。";
}

function activePauseDetails(table) {
  if (!table || typeof table !== "object") {
    return null;
  }
  if (tablePauseCache.has(table)) {
    return tablePauseCache.get(table);
  }

  let pause = null;
  if (table.pause?.paused) {
    pause = {
      reason: String(table.pause.reason || ""),
      seatIndexes: Array.isArray(table.pause.seatIndexes)
        ? table.pause.seatIndexes.map(Number).filter(Number.isInteger)
        : []
    };
    tablePauseCache.set(table, pause);
    return pause;
  }

  const players = table.engine?.public?.players || [];
  if (!table.engineReady || !players.length) {
    tablePauseCache.set(table, pause);
    return null;
  }

  const seatsByIndex = new Map((table.room?.seats || []).map((seat) => [Number(seat.seatIndex), seat]));
  const emptySeatIndexes = [];
  const disconnectedSeatIndexes = [];
  const unreadySeatIndexes = [];
  for (const player of players) {
    const seatIndex = Number(player.seatIndex);
    const seat = seatsByIndex.get(seatIndex);
    if (!seat?.user) {
      emptySeatIndexes.push(seatIndex);
    } else if (!seat.connected) {
      disconnectedSeatIndexes.push(seatIndex);
    } else if (!seat.ready) {
      unreadySeatIndexes.push(seatIndex);
    }
  }

  if (emptySeatIndexes.length) {
    pause = { reason: "empty_active_seat", seatIndexes: emptySeatIndexes };
  } else if (disconnectedSeatIndexes.length) {
    pause = { reason: "disconnected_active_seat", seatIndexes: disconnectedSeatIndexes };
  } else if (unreadySeatIndexes.length) {
    pause = { reason: "unready_active_seat", seatIndexes: unreadySeatIndexes };
  }
  tablePauseCache.set(table, pause);
  return pause;
}

function tableActionsHtml(table) {
  const makeTrumpAction = action(table, "tractor.makeTrump");
  const discardAction = action(table, "tractor.discardBottom");
  const playAction = action(table, "tractor.playCards");
  const autoPlayAction = action(table, "tractor.autoPlay");
  const selectedCards = selectedHandCards();
  const roomKey = table.room.roomKey;
  const disabled = isGameInteractionLocked() || Boolean(state.transitionRoomKey) || hasRoomTransitionPending() || isRoomMutationPending(roomKey);
  const parts = [];

  if (makeTrumpAction.enabled) {
    const label = trumpActionLabel(table);
    const title = table.phase === "burying_bottom"
      ? "请选择比当前主更强的级牌对子或王对"
      : "请选择一张级牌、级牌对子或王对";
    parts.push(`<button data-action="tractor.makeTrump" type="button" ${!disabled && !isActionPending("tractor.makeTrump", roomKey) && inferTrumpPayload(selectedCards, table) ? "" : "disabled"} title="${escapeAttribute(title)}">${escapeHtml(label)}</button>`);
  }
  if (discardAction.enabled) {
    parts.push(`<button data-action="tractor.discardBottom" type="button" ${!disabled && !isActionPending("tractor.discardBottom", roomKey) && selectedCards.length === discardAction.count ? "" : "disabled"} title="请选择 ${discardAction.count} 张牌">埋牌</button>`);
  }
  if (playAction.enabled) {
    parts.push(`<button class="table-action-play" data-action="tractor.playCards" type="button" ${!disabled && !isActionPending("tractor.playCards", roomKey) ? "" : "disabled"} title="${selectedCards.length > 0 ? "" : "请选择要出的牌"}">出选中的牌</button>`);
  }
  if (autoPlayAction.enabled) {
    parts.push(`<button class="table-action-auto" data-action="tractor.autoPlay" type="button" ${!disabled && !isActionPending("tractor.autoPlay", roomKey) ? "" : "disabled"} title="由服务器选择一组合法跟牌">自动出牌</button>`);
  }

  return parts.join("");
}

function syncTableActions(table) {
  const actions = viewerTableActionsElement();
  if (!actions || !table) {
    return;
  }

  const signature = tableActionsRenderSignature(table);
  if (actions.dataset.actionsSignature === signature) {
    return;
  }

  actions.dataset.actionsSignature = signature;
  actions.innerHTML = tableActionsHtml(table);
}

function viewerTableActionsElement() {
  if (tableActionsElement?.isConnected && els.table.contains(tableActionsElement)) {
    return tableActionsElement;
  }

  tableActionsElement = els.table.querySelector(".seat-viewer .table-actions");
  return tableActionsElement;
}

function tableActionsRenderSignature(table) {
  const roomKey = table.room?.roomKey || "";
  return [
    state.selectedHandIndexes.join(","),
    actionSignature(action(table, "tractor.makeTrump")),
    actionSignature(action(table, "tractor.discardBottom")),
    actionSignature(action(table, "tractor.playCards")),
    actionSignature(action(table, "tractor.autoPlay")),
    state.authenticated ? "1" : "0",
    state.connecting ? "1" : "0",
    state.recovering ? "1" : "0",
    state.transitionRoomKey,
    hasRoomTransitionPending() ? "1" : "0",
    isRoomMutationPending(roomKey) ? "1" : "0",
    isActionPending("tractor.makeTrump", roomKey) ? "1" : "0",
    isActionPending("tractor.discardBottom", roomKey) ? "1" : "0",
    isActionPending("tractor.playCards", roomKey) ? "1" : "0",
    isActionPending("tractor.autoPlay", roomKey) ? "1" : "0"
  ].join("|");
}

function ownerStartActionHtml(table) {
  if (table.phase !== "waiting_for_players" && table.phase !== "finished") {
    return "";
  }

  const startAction = action(table, "tractor.start");
  if (!startAction.enabled && !table.viewer?.owner) {
    return "";
  }

  const roomKey = table.room.roomKey;
  const disabled = isGameInteractionLocked()
    || Boolean(state.transitionRoomKey)
    || hasRoomTransitionPending()
    || isRoomMutationPending(roomKey)
    || isActionPending("tractor.start", roomKey)
    || !startAction.enabled;
  const label = table.phase === "finished" ? "开始下一局" : "发牌";
  const title = !startAction.enabled
    ? tableActionReasonText(table, startAction.reason || "waiting_for_four_ready_players")
    : "";
  return `<button class="table-action-start" data-action="tractor.start" type="button" ${disabled ? "disabled" : ""} title="${escapeAttribute(title)}">${escapeHtml(label)}</button>`;
}

function isSeatTurn(table, seatIndex) {
  const currentSeat = table.turn?.seatIndex ?? table.engine?.public?.currentTrick?.nextSeatIndex;
  return Number(currentSeat) === Number(seatIndex);
}

function isPrivateHandSeat(table, seatIndex) {
  return Number(table.engine?.private?.seatIndex) === Number(seatIndex);
}

function visibleTrick(table) {
  const currentTrick = table.engine?.public?.currentTrick;
  return currentTrick?.plays?.length
    ? currentTrick
    : isTrickReviewActive(table)
      ? table.engine?.public?.lastCompletedTrick
      : currentTrick || null;
}

function seatTrickHtml(table, seatIndex) {
  if (!table.engineReady || !isActiveEngineSeat(table, seatIndex)) {
    return "";
  }

  const bottomCards = bottomPileCardsForSeat(table, seatIndex);
  if (bottomCards.length) {
    return `
      <div class="seat-trick seat-bottom-pile" title="底牌">
        <span class="played-cards bottom-pile-cards">${bottomPileCardsHtml(bottomCards)}</span>
      </div>
    `;
  }

  return "";
}

function seatHandHtml(table, seatIndex) {
  if (!table.engineReady || !isActiveEngineSeat(table, seatIndex)) {
    return "";
  }

  const cards = privateHandCardsForSeat(table, seatIndex);
  if (cards.length) {
    return privateSeatHandHtml(table, cards);
  }

  return "";
}

function privateSeatHandHtml(table, cards) {
  const selected = new Set(state.selectedHandIndexes);
  const trumpCandidates = trumpCandidateIndexes(table, cards);
  const nodes = cards.map(({ card, index }) => `
    <button class="hand-card ${trumpCandidates.has(index) ? "hand-card-trump-candidate" : ""}" data-card-index="${index}" aria-pressed="${selected.has(index) ? "true" : "false"}" type="button" ${trumpCandidates.has(index) ? 'title="可亮牌"' : ""}>
      ${cardFaceHtml(card, "hand-card-face")}
      <span class="hand-card-label">${escapeHtml(cardLabelText(card))}</span>
    </button>
  `).join("");
  const style = [
    `--hand-count: ${Math.max(cards.length, 1)}`,
    `--hand-overlap: ${handCardOverlapPx(cards.length)}px`
  ].join("; ");

  return `<div class="seat-hand seat-hand-private" data-hand-count="${cards.length}" style="${style}">${nodes}</div>`;
}

function trumpCandidateIndexes(table, cards) {
  const makeTrumpAction = action(table, "tractor.makeTrump");
  const rank = table?.engine?.public?.rank;
  if (!makeTrumpAction.enabled || rank === undefined || rank === null) {
    return new Set();
  }

  const currentStrength = trumpExposureStrength(table.engine?.public?.trump?.exposure || "none");
  const countsById = new Map();
  cards.forEach(({ card }) => {
    const id = Number(card?.id);
    if (Number.isInteger(id)) {
      countsById.set(id, (countsById.get(id) || 0) + 1);
    }
  });

  const candidates = new Set();
  cards.forEach(({ card, index }) => {
    if (isTrumpCandidateCard(card, rank, currentStrength, countsById)) {
      candidates.add(index);
    }
  });
  return candidates;
}

function isTrumpCandidateCard(card, rank, currentStrength, countsById) {
  const id = Number(card?.id);
  if (!Number.isInteger(id)) {
    return false;
  }

  const count = countsById.get(id) || 0;
  if (id === 52 || id === 53) {
    const exposure = id === 52 ? "pair_black_joker" : "pair_red_joker";
    return count >= 2 && trumpExposureStrength(exposure) > currentStrength;
  }

  const suit = cardSuitFromCard(card);
  if (!suit || suit === "joker" || cardRankFromCard(card) !== rank) {
    return false;
  }

  return trumpExposureStrength("single_rank") > currentStrength
    || (count >= 2 && trumpExposureStrength("pair_rank") > currentStrength);
}

function privateHandCardsForSeat(table, seatIndex) {
  return privateCardSplitForSeat(table, seatIndex).hand;
}

function bottomPileCardsForSeat(table, seatIndex) {
  return privateCardSplitForSeat(table, seatIndex).bottom;
}

function privateCardSplitForSeat(table, seatIndex) {
  if (!isPrivateHandSeat(table, seatIndex)) {
    return emptyPrivateCardSplit;
  }

  let splitsBySeat = privateCardSplitCache.get(table);
  if (!splitsBySeat) {
    splitsBySeat = new Map();
    privateCardSplitCache.set(table, splitsBySeat);
  }
  const seatKey = Number(seatIndex);
  const cached = splitsBySeat.get(seatKey);
  if (cached) {
    return cached;
  }

  const split = { hand: [], bottom: [] };
  const bottomIndexes = shouldMergeBottomPileIntoHand(table, seatIndex)
    ? new Set()
    : bottomPileIndexSetForSeat(table, seatIndex);
  (table.engine?.private?.cards || []).forEach((card, index) => {
    split[bottomIndexes.has(index) ? "bottom" : "hand"].push({ card, index });
  });
  splitsBySeat.set(seatKey, split);
  return split;
}

function shouldMergeBottomPileIntoHand(table, seatIndex) {
  return table?.phase === "burying_bottom"
    && Number(table.engine?.public?.bottomHolderSeatIndex) === Number(seatIndex);
}

function bottomPileIndexSetForSeat(table, seatIndex) {
  const cards = table.engine?.private?.cards || [];
  if (
    table?.phase !== "burying_bottom"
    || Number(table.engine?.public?.bottomHolderSeatIndex) !== Number(seatIndex)
    || !Array.isArray(cards)
    || cards.length <= 0
  ) {
    return new Set();
  }

  const bottomCards = table.engine?.private?.bottomCards;
  if (Array.isArray(bottomCards) && bottomCards.length > 0) {
    return bottomPileIndexSetByCardIds(cards, bottomCards.map((card) => card?.id ?? card));
  }

  const cardsPerPlayer = Number(table.engine?.public?.cardsPerPlayer);
  if (Number.isInteger(cardsPerPlayer) && cardsPerPlayer > 0 && cards.length > cardsPerPlayer) {
    return new Set(cards.slice(cardsPerPlayer).map((_, offset) => cardsPerPlayer + offset));
  }

  const bottomCount = Number(action(table, "tractor.discardBottom").count || 8);
  if (Number.isInteger(bottomCount) && bottomCount > 0 && cards.length > bottomCount) {
    const bottomStart = cards.length - bottomCount;
    return new Set(cards.slice(bottomStart).map((_, offset) => bottomStart + offset));
  }

  return new Set();
}

function bottomPileIndexSetByCardIds(cards, bottomCardIds) {
  const remainingById = new Map();
  for (const cardId of bottomCardIds) {
    const id = Number(cardId);
    if (!Number.isInteger(id)) {
      continue;
    }
    remainingById.set(id, (remainingById.get(id) || 0) + 1);
  }

  const indexes = new Set();
  cards.forEach((card, index) => {
    const id = Number(card?.id ?? card);
    const remaining = remainingById.get(id) || 0;
    if (remaining <= 0) {
      return;
    }

    indexes.add(index);
    if (remaining === 1) {
      remainingById.delete(id);
    } else {
      remainingById.set(id, remaining - 1);
    }
  });
  return indexes;
}

function bottomPileCardsHtml(cards) {
  const selected = new Set(state.selectedHandIndexes);
  return cards.map(({ card, index }) => `
    <button class="bottom-pile-card" data-card-index="${index}" aria-pressed="${selected.has(index) ? "true" : "false"}" type="button">
      ${cardFaceHtml(card, "played-card bottom-pile-card-face")}
    </button>
  `).join("");
}

function handSummaryHtml(table) {
  const summary = table.engine?.public?.handSummary;
  if (!summary) {
    return "";
  }

  const teams = (summary.teams || []).map((team) => {
    const role = team.team === summary.defendingTeam ? "庄家方" : "抓分方";
    return `
      <div class="hand-summary-team ${team.won ? "hand-summary-winner" : ""}">
        <strong>${escapeHtml(role)}</strong>
        <span>级牌：${escapeHtml(team.rankLabelBefore || rankLabel(team.rankBefore))} → ${escapeHtml(team.rankLabelAfter || rankLabel(team.rankAfter))}（${escapeHtml(rankMoveText(team, summary))}）</span>
      </div>
    `;
  }).join("");
  const bottom = summary.bottomScoreBase > 0 && summary.bottomScoreMultiplier > 0
    ? ` · 扣底 ${summary.bottomScoreBase} x ${summary.bottomScoreMultiplier}`
    : "";
  const outcome = handOutcomeText(summary, table);
  const waitingText = handSummaryWaitingText(table);
  const startAction = ownerStartActionHtml(table);

  return `
    <div class="hand-summary-panel">
      <div class="hand-summary-title">本局结束，等待房主开始下一局</div>
      ${waitingText ? `<div class="hand-summary-waiting">${escapeHtml(waitingText)}</div>` : ""}
      <div class="hand-summary-meta">
        ${escapeHtml(outcome)}${escapeHtml(bottom)}
      </div>
      <div class="hand-summary-teams">${teams}</div>
      ${startAction ? `<div class="hand-summary-actions">${startAction}</div>` : ""}
    </div>
  `;
}

function handSummaryWaitingText(table) {
  const startAction = action(table, "tractor.start");
  if (startAction.enabled) {
    return "";
  }
  if (isTablePaused(table)) {
    return pauseText(table);
  }

  const waitingSeats = unreadyActiveSeatLabels(table);
  if (waitingSeats.length) {
    return `等待${waitingSeats.join("、")}准备`;
  }

  return startAction.reason ? actionReasonText(startAction.reason) : "";
}

function unreadyActiveSeatLabels(table) {
  const activeSeatIndexes = new Set((table.engine?.public?.players || [])
    .map((player) => Number(player.seatIndex))
    .filter(Number.isInteger));
  return (table.room?.seats || [])
    .filter((seat) => {
      const seatIndex = Number(seat.seatIndex);
      return seat.user
        && !seat.ready
        && (!activeSeatIndexes.size || activeSeatIndexes.has(seatIndex));
    })
    .map((seat) => seatUserLabel(table, seat.seatIndex) || seatLabel(seat.seatIndex));
}

function handOutcomeText(summary, table) {
  const nextStarter = seatUserLabel(table, summary.nextStarterSeatIndex);
  const suffix = nextStarter ? `，下局先手 ${nextStarter}` : "";
  return summary.winnerTeam === summary.attackingTeam ? `抓分方上台${suffix}` : `庄家方守庄${suffix}`;
}

function rankMoveText(team, summary) {
  if (summary.resetGame) {
    return "新局重置";
  }
  if (team.rankDelta > 0) {
    return `升${team.rankDelta}级`;
  }
  if (team.rankDelta < 0) {
    return "重置";
  }
  return "不升级";
}

function turnTimerHtml(table) {
  if (isTablePaused(table)) {
    return "";
  }
  if (isTrickReviewActive(table)) {
    return trickReviewTimerHtml(table);
  }

  const turn = table.turn;
  if (!turn?.deadlineAt || !Number.isInteger(turn.seatIndex)) {
    return "";
  }

  const viewerTurn = table.viewer?.seatIndex === turn.seatIndex;
  const actionLabel = table.phase === "burying_bottom" ? "埋牌" : "出牌";
  const label = viewerTurn ? `轮到你${actionLabel}` : `${seatLabel(turn.seatIndex)}${actionLabel}`;
  return `
    <div class="turn-timer" data-viewer-turn="${viewerTurn ? "true" : "false"}" data-turn-deadline="${escapeAttribute(turn.deadlineAt)}" data-turn-started="${escapeAttribute(turn.startedAt || "")}" data-turn-countdown="${escapeAttribute(turn.countdownSeconds || "")}">
      <span class="turn-timer-label">${escapeHtml(label)}</span>
      <span class="turn-timer-time">--:--</span>
      <span class="turn-timer-bar" aria-hidden="true"><span></span></span>
    </div>
  `;
}

function trickReviewTimerHtml(table) {
  const untilMs = Date.parse(table.review?.until || "");
  if (!Number.isFinite(untilMs)) {
    return "";
  }

  const remainingSeconds = Math.max(1, Math.ceil((untilMs - Date.now()) / 1000));
  return `
    <div class="turn-timer trick-review-timer" data-viewer-turn="false" data-turn-deadline="${escapeAttribute(table.review.until)}" data-turn-started="" data-turn-countdown="${escapeAttribute(remainingSeconds)}">
      <span class="turn-timer-label">本墩回顾</span>
      <span class="turn-timer-time">--:--</span>
      <span class="turn-timer-bar" aria-hidden="true"><span></span></span>
    </div>
  `;
}

function trumpRevealTimerHtml(table) {
  const revealAtMs = trumpRevealAtMs(table);
  if (!Number.isFinite(revealAtMs) || table.phase !== "making_trump") {
    return "";
  }

  const remainingSeconds = Math.max(1, Math.ceil((revealAtMs - Date.now()) / 1000));
  const holder = bottomHolderLabel(table);
  const label = holder ? `反主倒计时 · ${holder}稍后埋牌` : "反主倒计时";
  return `
    <div class="turn-timer trump-reveal-timer" data-viewer-turn="false" data-turn-deadline="${escapeAttribute(table.engine.public.trumpRevealAt)}" data-turn-started="" data-turn-countdown="${escapeAttribute(remainingSeconds)}">
      <span class="turn-timer-label">${escapeHtml(label)}</span>
      <span class="turn-timer-time">--:--</span>
      <span class="turn-timer-bar" aria-hidden="true"><span></span></span>
    </div>
  `;
}

function syncTurnTimer(force = false) {
  const timers = Array.from(els.table.querySelectorAll(".turn-timer[data-turn-deadline]"));
  const signature = timers.map((timer) => [
    timer.dataset.turnDeadline || "",
    timer.dataset.turnStarted || "",
    timer.dataset.turnCountdown || "",
    timer.dataset.viewerTurn || ""
  ].join(":")).join("|");
  if (!force && state.turnTimerSignature === signature) {
    return;
  }

  clearTurnTimer(false);
  state.turnTimerSignature = signature;
  if (!timers.length) {
    return;
  }

  timers.forEach(updateTurnTimer);
  state.turnTimerId = window.setInterval(() => {
    timers.forEach(updateTurnTimer);
  }, 1000);
}

function clearTurnTimer(resetSignature = true) {
  if (state.turnTimerId) {
    window.clearInterval(state.turnTimerId);
    state.turnTimerId = 0;
  }
  if (resetSignature) {
    state.turnTimerSignature = "";
  }
}

function syncTrickReviewTimer(table) {
  const roomKey = table.room?.roomKey || state.currentRoomKey;
  const signature = [
    roomKey,
    table.review?.active ? "1" : "0",
    table.review?.until || ""
  ].join("|");
  if (state.trickReviewTimerSignature === signature) {
    return;
  }

  clearTrickReviewTimer(false);
  state.trickReviewTimerSignature = signature;
  const untilMs = Date.parse(table.review?.until || "");
  if (!table.review?.active || !Number.isFinite(untilMs)) {
    return;
  }

  const delay = Math.max(0, untilMs - Date.now()) + 50;
  state.trickReviewTimerId = window.setTimeout(() => {
    state.trickReviewTimerId = 0;
    state.trickReviewTimerSignature = "";
    if (roomKey && state.currentRoomKey === roomKey) {
      void refreshTable(roomKey).catch(() => render());
    } else {
      render();
    }
  }, delay);
}

function clearTrickReviewTimer(resetSignature = true) {
  if (state.trickReviewTimerId) {
    window.clearTimeout(state.trickReviewTimerId);
    state.trickReviewTimerId = 0;
  }
  if (resetSignature) {
    state.trickReviewTimerSignature = "";
  }
}

function syncTrumpRevealTimer(table) {
  const roomKey = table.room?.roomKey || state.currentRoomKey;
  const revealAtMs = trumpRevealAtMs(table);
  const signature = [
    roomKey,
    table.phase || "",
    Number.isFinite(revealAtMs) ? String(revealAtMs) : ""
  ].join("|");
  if (state.trumpRevealTimerSignature === signature) {
    return;
  }

  clearTrumpRevealTimer(false);
  state.trumpRevealTimerSignature = signature;
  if (table.phase !== "making_trump" || !Number.isFinite(revealAtMs)) {
    return;
  }

  const delay = revealAtMs > Date.now()
    ? Math.max(0, revealAtMs - Date.now()) + 80
    : 1000;
  state.trumpRevealTimerId = window.setTimeout(() => {
    state.trumpRevealTimerId = 0;
    state.trumpRevealTimerSignature = "";
    if (roomKey && state.currentRoomKey === roomKey) {
      void refreshTable(roomKey).catch(() => render());
    }
  }, delay);
}

function clearTrumpRevealTimer(resetSignature = true) {
  if (state.trumpRevealTimerId) {
    window.clearTimeout(state.trumpRevealTimerId);
    state.trumpRevealTimerId = 0;
  }
  if (resetSignature) {
    state.trumpRevealTimerSignature = "";
  }
}

function trumpRevealAtMs(table) {
  const value = table?.engine?.public?.trumpRevealAt || "";
  const timestamp = Date.parse(value);
  return Number.isFinite(timestamp) ? timestamp : Number.NaN;
}

function updateTurnTimer(timer) {
  if (!timer.isConnected) {
    clearTurnTimer();
    return;
  }

  const deadlineMs = Date.parse(timer.dataset.turnDeadline || "");
  if (!Number.isFinite(deadlineMs)) {
    clearTurnTimer();
    return;
  }

  const countdownSeconds = Number(timer.dataset.turnCountdown || 0);
  const remainingMs = Math.max(0, deadlineMs - Date.now());
  const totalMs = countdownSeconds > 0 ? countdownSeconds * 1000 : remainingMs;
  const progress = totalMs > 0 ? Math.max(0, Math.min(1, remainingMs / totalMs)) : 0;
  const time = timer.querySelector(".turn-timer-time");
  if (time) {
    time.textContent = durationLabel(remainingMs);
  }
  timer.style.setProperty("--turn-progress", `${Math.round(progress * 1000) / 10}%`);
  timer.classList.toggle("turn-timer-urgent", remainingMs > 0 && remainingMs <= 10000);
  timer.classList.toggle("turn-timer-due", remainingMs <= 0);
}

function durationLabel(milliseconds) {
  const totalSeconds = Math.ceil(Math.max(0, milliseconds) / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
}

function handleTableAction(type, seatValue, button = null) {
  const seatIndex = Number(seatValue);
  const roomKey = state.currentRoomKey;
  const blockedReason = tableActionBlockedReason(type, roomKey);
  if (blockedReason) {
    setStatus(blockedReason);
    return;
  }

  if (type === "seat.claim") {
    markActionButtonPending(button);
    void sendCommand("seat.claim", { roomKey, payload: { seatIndex } }).catch((error) => reportActionError(error, button));
  } else if (type === "seat.release") {
    markActionButtonPending(button);
    void sendCommand("seat.release", { roomKey }).catch((error) => reportActionError(error, button));
  } else if (type === "seat.remove") {
    markActionButtonPending(button);
    void sendCommand("seat.remove", { roomKey, payload: { seatIndex } }).catch((error) => reportActionError(error, button));
  } else if (type === "player.ready") {
    const seat = state.table.room.seats.find((candidate) => candidate.seatIndex === seatIndex);
    if (!seat) {
      return;
    }
    markActionButtonPending(button);
    void sendCommand("player.ready", { roomKey, payload: { ready: !seat.ready } }).catch((error) => reportActionError(error, button));
  } else if (type === "observer.watch") {
    markActionButtonPending(button);
    void sendCommand("observer.watch", { roomKey, payload: { seatIndex } }).catch((error) => reportActionError(error, button));
  } else if (type === "tractor.start") {
    const roomEpoch = state.roomEpoch;
    const previousTable = state.table;
    setStatus("正在发牌...");
    markActionButtonPending(button);
    void sendCommand("tractor.start", { roomKey, payload: {}, roomEpoch })
      .then((response) => {
        if (state.roomEpoch === roomEpoch && state.currentRoomKey === roomKey && response.payload?.table) {
          setStatus(engineCommandStatus(type, response.payload.table, previousTable));
        }
      })
      .catch((error) => reportActionError(error, button));
  } else if (type === "tractor.makeTrump") {
    const payload = inferTrumpPayload(selectedHandCards(), state.table);
    if (payload) {
      setStatus(`正在${trumpActionLabel(state.table)}...`);
      markActionButtonPending(button);
      void sendEngineCommand("tractor.makeTrump", roomKey, payload, button);
    } else {
      setStatus(state.table?.phase === "burying_bottom" ? "请选择更强的级牌对子或王对" : "请选择一张级牌、级牌对子或王对");
    }
  } else if (type === "tractor.discardBottom") {
    const cards = selectedHandCards();
    const discardAction = action(state.table, "tractor.discardBottom");
    if (cards.length !== discardAction.count) {
      setStatus(`请选择 ${discardAction.count} 张牌`);
      return;
    }
    setStatus("正在埋牌...");
    markActionButtonPending(button);
    void sendEngineCommand("tractor.discardBottom", roomKey, { cards: cards.map((card) => card.id) }, button);
  } else if (type === "tractor.playCards") {
    const playAction = action(state.table, "tractor.playCards");
    if (!playAction.enabled) {
      setStatus(tableActionReasonText(state.table, playAction.reason || "play_not_open"));
      return;
    }
    const cards = selectedHandCards();
    if (!cards.length) {
      setStatus("请选择要出的牌");
      return;
    }
    setStatus("正在出牌...");
    markActionButtonPending(button);
    void sendEngineCommand("tractor.playCards", roomKey, { cards: cards.map((card) => card.id) }, button);
  } else if (type === "tractor.autoPlay") {
    const autoPlayAction = action(state.table, "tractor.autoPlay");
    if (!autoPlayAction.enabled) {
      setStatus(tableActionReasonText(state.table, autoPlayAction.reason || "auto_play_not_open"));
      return;
    }
    state.selectedHandIndexes = [];
    setStatus("正在自动出牌...");
    markActionButtonPending(button);
    void sendEngineCommand("tractor.autoPlay", roomKey, {}, button);
  }
}

function markActionButtonPending(button) {
  if (button instanceof HTMLButtonElement) {
    button.disabled = true;
  }
}

function playSingleCardFromDoubleClick(cardIndex, cardButton) {
  const roomKey = state.currentRoomKey;
  const blockedReason = tableActionBlockedReason("tractor.playCards", roomKey);
  if (blockedReason) {
    setStatus(blockedReason);
    return;
  }

  const table = state.table;
  const playAction = action(table, "tractor.playCards");
  if (!playAction.enabled) {
    setStatus(tableActionReasonText(table, playAction.reason || "play_not_open"));
    return;
  }

  const card = table?.engine?.private?.cards?.[cardIndex];
  if (!card) {
    return;
  }

  state.selectedHandIndexes = [cardIndex];
  refreshSelectionUi(cardIndex, cardButton);
  setStatus("正在出牌...");
  const playButton = viewerTableActionsElement()?.querySelector('[data-action="tractor.playCards"]');
  markActionButtonPending(playButton);
  void sendEngineCommand("tractor.playCards", roomKey, { cards: [card.id] }, playButton);
}

function reportActionError(error, button) {
  restoreActionButton(button);
  reportError(error);
}

function restoreActionButton(button) {
  if (button instanceof HTMLButtonElement && button.isConnected) {
    button.disabled = false;
  }
}

function tableActionBlockedReason(type, roomKey) {
  if (!roomKey) {
    return "请选择房间";
  }
  if (!state.authenticated) {
    return "请先连接并认证";
  }
  if (state.connecting || state.recovering) {
    return "正在同步，请稍候";
  }
  if (state.transitionRoomKey || hasRoomTransitionPending()) {
    return "正在切换房间，请稍候";
  }
  if (isRoomMutationPending(roomKey)) {
    return "上一个操作仍在处理中";
  }
  if (isActionPending(type, roomKey)) {
    return "上一个操作仍在处理中";
  }
  return "";
}

async function sendEngineCommand(type, roomKey, payload, button = null) {
  const roomEpoch = state.roomEpoch;
  const previousTable = state.table;
  try {
    const command = sendCommand(type, { roomKey, payload, roomEpoch, applyResponse: false });
    syncTableActions(state.table);
    const response = await command;
    if (state.roomEpoch !== roomEpoch || state.currentRoomKey !== roomKey) {
      return;
    }
    state.selectedHandIndexes = [];
    if (response.payload?.table) {
      playTableSound(response.payload.table, type);
      applyTable(response.payload.table);
      setStatus(engineCommandStatus(type, state.table, previousTable));
    } else {
      await refreshTable(roomKey);
      setStatus("牌桌已更新");
    }
  } catch (error) {
    restoreActionButton(button);
    reportError(error);
    if (type && String(type).startsWith("tractor.") && roomKey && state.currentRoomKey === roomKey) {
      void refreshTable(roomKey).catch(() => undefined);
    }
  }
}

function engineCommandStatus(commandType, table, previousTable = null) {
  const nextSeatIndex = table?.engine?.public?.currentTrick?.nextSeatIndex;
  const isViewerTurn = nextSeatIndex !== undefined && table?.viewer?.seatIndex === nextSeatIndex;

  if (commandType === "tractor.start" && table?.phase === "making_trump") {
    const makeTrumpAction = action(table, "tractor.makeTrump");
    return makeTrumpAction.enabled ? "发牌成功，请选择级牌亮主" : "发牌成功，等待亮主";
  }
  if (commandType === "tractor.makeTrump" && table?.phase === "burying_bottom") {
    const discardAction = action(table, "tractor.discardBottom");
    const label = makeTrumpResultLabel(previousTable);
    return discardAction.enabled ? `${label}成功，请选择 8 张牌埋牌` : `${label}成功，等待埋牌`;
  }
  if (commandType === "tractor.makeTrump" && table?.phase === "making_trump" && Number.isFinite(trumpRevealAtMs(table))) {
    const label = makeTrumpResultLabel(previousTable);
    return `${label}成功，等待反主窗口结束后翻开底牌`;
  }
  if (commandType === "tractor.discardBottom" && table?.phase === "playing") {
    return isViewerTurn ? "埋牌完成，请选择要出的牌" : `埋牌完成，等待${seatLabel(nextSeatIndex)}出牌`;
  }
  if ((commandType === "tractor.playCards" || commandType === "tractor.autoPlay") && table?.phase === "playing") {
    return isViewerTurn ? "请继续出牌" : `等待${seatLabel(nextSeatIndex)}出牌`;
  }
  if (table?.phase === "finished") {
    return "本局结束，等待房主开始下一局";
  }

  return "牌桌已更新";
}

function makeTrumpResultLabel(previousTable) {
  const previousExposure = previousTable?.engine?.public?.trump?.exposure;
  return previousExposure && previousExposure !== "none" ? "反主" : "亮主";
}

function toggleCardSelection(index, button = null) {
  const position = state.selectedHandIndexes.indexOf(index);
  if (position >= 0) {
    state.selectedHandIndexes.splice(position, 1);
  } else {
    state.selectedHandIndexes.push(index);
  }
  refreshSelectionUi(index, button);
}

function refreshSelectionUi(changedIndex = null, changedButton = null) {
  let selected = null;
  const updateButton = (button) => {
    const cardIndex = Number(button.dataset.cardIndex);
    const isSelected = selected ? selected.has(cardIndex) : state.selectedHandIndexes.includes(cardIndex);
    button.setAttribute("aria-pressed", isSelected ? "true" : "false");
  };
  if (changedButton?.isConnected) {
    updateButton(changedButton);
  } else if (Number.isInteger(changedIndex)) {
    selected = new Set(state.selectedHandIndexes);
    els.table.querySelectorAll(`[data-card-index="${changedIndex}"]`).forEach(updateButton);
  } else {
    selected = new Set(state.selectedHandIndexes);
    els.table.querySelectorAll("[data-card-index]").forEach(updateButton);
  }

  syncTableActions(state.table);
}

function selectedHandCards() {
  const cards = state.table?.engine?.private?.cards || [];
  return state.selectedHandIndexes
    .map((index) => cards[index])
    .filter(Boolean);
}

function selectHandCardsByIds(cardIds) {
  const cards = state.table?.engine?.private?.cards || [];
  if (!cards.length || !Array.isArray(cardIds)) {
    return false;
  }

  const usedIndexes = new Set();
  const indexes = [];
  for (const cardId of cardIds) {
    const id = Number(cardId);
    const index = cards.findIndex((card, cardIndex) => !usedIndexes.has(cardIndex) && Number(card?.id) === id);
    if (index < 0) {
      continue;
    }
    usedIndexes.add(index);
    indexes.push(index);
  }

  if (!indexes.length) {
    return false;
  }
  state.selectedHandIndexes = indexes;
  return true;
}

function cardFaceHtml(card, className) {
  const image = cardImageUrl(card);
  const label = cardLabelText(card);
  const imageClass = image ? "" : " card-face-no-image";
  const imageHtml = image
    ? `<img class="card-face-image" src="${escapeAttribute(image)}" alt="${escapeAttribute(label)}" loading="lazy" decoding="async" draggable="false" />`
    : "";

  return `
    <span class="${className} card-face ${cardFaceToneClass(card)}${imageClass}" aria-label="${escapeAttribute(label)}">
      ${imageHtml}
      ${cardSymbolHtml(card)}
    </span>
  `;
}

function cardImageUrl(card) {
  const cardId = Number(card?.id);
  if (!Number.isInteger(cardId) || cardId < 0 || cardId > 54 || !state.assetBaseUrl) {
    return "";
  }

  const uiCardNumber = serverToUiCardNumber(cardId);
  return `${state.assetBaseUrl}/tractor/${encodeURIComponent(state.cardStyle)}/tile${String(uiCardNumber).padStart(3, "0")}.png`;
}

function cardSymbolHtml(card) {
  const id = Number(card?.id);
  if (id === 52 || id === 53) {
    const joker = id === 52 ? "小王" : "大王";
    return `
      <span class="card-face-symbol" aria-hidden="true">
        <span class="card-symbol-rank">${joker}</span>
        <span class="card-symbol-main">JOKER</span>
      </span>
    `;
  }

  const suit = cardSuitFromCard(card);
  const rank = cardRankFromCard(card);
  const symbol = cardSuitSymbols[suit] || "";
  const rankText = cardRankLabels[rank] || "";
  const fallback = cardLabelText(card);
  return `
    <span class="card-face-symbol" aria-hidden="true">
      <span class="card-symbol-rank">${escapeHtml(rankText || fallback)}</span>
      <span class="card-symbol-main">${escapeHtml(symbol || fallback)}</span>
    </span>
  `;
}

function cardFaceToneClass(card) {
  const id = Number(card?.id);
  if (id === 52 || id === 53) {
    return id === 53 ? "card-face-red card-face-joker" : "card-face-black card-face-joker";
  }

  const suit = cardSuitFromCard(card);
  return suit === "heart" || suit === "diamond" ? "card-face-red" : "card-face-black";
}

function cardSuitFromCard(card) {
  const id = Number(card?.id);
  return cardSuitLabels[card?.suit] ? card.suit : cardSuitFromId(id);
}

function cardRankFromCard(card) {
  const id = Number(card?.id);
  const rank = Number(card?.rank);
  return Number.isInteger(rank) ? rank : cardRankFromId(id);
}

function serverToUiCardNumber(cardId) {
  if (cardId >= 0 && cardId < 52) {
    const rank = cardId % 13;
    const rankOffset = rank === 12 ? 0 : rank + 1;
    const suitOffset = cardId < 13
      ? 0
      : cardId < 26
        ? 26
        : cardId < 39
          ? 13
          : 39;
    return suitOffset + rankOffset;
  }
  if (cardId === 52) {
    return 53;
  }
  if (cardId === 53) {
    return 52;
  }
  return cardId;
}

function syncSelectedHandIndexes() {
  const cards = state.table?.engine?.private?.cards || [];
  const signature = cards.map((card) => card?.id ?? "").join(",");
  if (state.handSignature && signature !== state.handSignature) {
    state.selectedHandIndexes = [];
  } else {
    state.selectedHandIndexes = state.selectedHandIndexes.filter((index) => index >= 0 && index < cards.length);
  }
  state.handSignature = signature;
}

function trumpActionLabel(table) {
  return table?.phase === "burying_bottom" ? "反主" : "亮主";
}

function inferTrumpPayload(cards, table) {
  const rank = table?.engine?.public?.rank;
  if (rank === undefined) {
    return null;
  }
  let payload = null;
  const firstCardId = Number(cards[0]?.id);
  const secondCardId = Number(cards[1]?.id);
  const firstCardSuit = cardSuitFromCard(cards[0]);
  const firstCardRank = cardRankFromCard(cards[0]);
  if (cards.length === 1 && firstCardRank === rank && firstCardSuit && firstCardSuit !== "joker") {
    payload = { suit: firstCardSuit, exposure: "single_rank" };
  }
  if (cards.length === 2 && Number.isInteger(firstCardId) && firstCardId === secondCardId) {
    if (firstCardId === 52) {
      payload = { suit: "joker", exposure: "pair_black_joker" };
    } else if (firstCardId === 53) {
      payload = { suit: "joker", exposure: "pair_red_joker" };
    } else if (firstCardRank === rank && firstCardSuit && firstCardSuit !== "joker") {
      payload = { suit: firstCardSuit, exposure: "pair_rank" };
    }
  }

  if (!payload) {
    return null;
  }

  const currentExposure = table?.engine?.public?.trump?.exposure || "none";
  return trumpExposureStrength(payload.exposure) > trumpExposureStrength(currentExposure) ? payload : null;
}

function trumpExposureStrength(exposure) {
  switch (exposure) {
    case "single_rank":
      return 1;
    case "pair_rank":
      return 2;
    case "pair_black_joker":
      return 3;
    case "pair_red_joker":
      return 4;
    default:
      return 0;
  }
}

function joinRoom(roomKey) {
  if (!roomKey || isGameInteractionLocked() || hasRoomTransitionPending() || isRoomMutationPending(state.currentRoomKey) || isActionPending("room.join", roomKey)) {
    return;
  }
  if (state.currentRoomKey) {
    return;
  }

  const switchingRooms = roomKey !== state.currentRoomKey;
  const roomEpoch = switchingRooms ? beginRoomTransition(roomKey) : state.roomEpoch;
  if (switchingRooms) {
    clearChatState();
    state.emojiTarget = "";
    state.selectedHandIndexes = [];
    state.handSignature = "";
    resetPlayLog(roomKey);
    state.table = null;
    state.currentRoomKey = "";
  }
  setStatus("正在进入房间");
  void sendCommand("room.join", { roomKey, roomEpoch })
    .then(() => {
      if (state.roomEpoch === roomEpoch && state.currentRoomKey === roomKey) {
        setStatus("已进入房间");
      }
    })
    .catch((error) => {
      if (state.roomEpoch === roomEpoch) {
        state.transitionRoomKey = "";
        void requestCatchup().catch(() => requestRooms());
      }
      reportError(error);
    });
}

async function requestChatHistory(roomKey = "") {
  if (!state.authenticated) {
    return;
  }

  const channelKey = roomKey || lobbyChatChannelKey;
  state.requestedChatChannelKey = channelKey;
  const response = await sendCommand("chat.history", roomKey ? { roomKey, applyResponse: false } : { applyResponse: false });
  if (channelKey !== activeChatChannelKey() || state.requestedChatChannelKey !== channelKey) {
    return;
  }

  setChatEvents(response.payload.events || []);
}

function applySkinProfile(profile) {
  if (!profile || !Array.isArray(profile.skins)) {
    return;
  }

  state.skinProfile = {
    skins: profile.skins,
    ownedSkinIds: Array.isArray(profile.ownedSkinIds) ? profile.ownedSkinIds : [],
    selectedSkinId: profile.selectedSkinId || ""
  };
}

async function sendChatMessage() {
  const roomKey = state.currentRoomKey;
  const channelKey = activeChatChannelKey();
  const draft = state.chatDraft;
  const text = draft.trim();
  if (!text || isGameInteractionLocked()) {
    return;
  }

  state.chatDraft = "";
  if (els.chatInput.value === draft) {
    els.chatInput.value = "";
  }
  try {
    await sendCommand("chat.send", {
      roomKey: roomKey || undefined,
      payload: { text },
      retryable: false,
      trackPendingAction: false,
      timeoutMs: 5000
    });
  } catch (error) {
    if (activeChatChannelKey() === channelKey && !state.chatDraft && !els.chatInput.value) {
      state.chatDraft = text;
      els.chatInput.value = text;
    }
    reportError(error);
  }
}

function renderChatPanel() {
  const channelKey = activeChatChannelKey();
  els.chatPanel.hidden = false;
  els.chatInput.placeholder = state.currentRoomKey ? "桌内聊天" : "大厅聊天";
  els.chatInput.disabled = isGameInteractionLocked();
  if (els.chatInput.value !== state.chatDraft) {
    els.chatInput.value = state.chatDraft;
  }
  if (state.renderedChatRoomKey !== channelKey) {
    renderChatMessagesFull(channelKey);
  } else if (state.renderedChatVersion !== state.chatVersion) {
    syncChatMessagesIncremental(channelKey);
  }
}

function renderChatMessagesFull(channelKey) {
  const nodes = [];
  const keys = [];
  const signatures = new Map();
  state.chatEvents.forEach((event, index) => {
    const key = chatEventKey(event, index);
    const signature = chatEventSignature(event);
    nodes.push(chatEventNodeWithMeta(event, key, signature));
    keys.push(key);
    signatures.set(key, signature);
  });
  els.chatMessages.replaceChildren(...nodes, chatBottomAnchorNode());
  state.renderedChatRoomKey = channelKey;
  state.renderedChatVersion = state.chatVersion;
  state.renderedChatKeys = keys;
  state.renderedChatSignatures = signatures;
  scheduleChatScrollToBottom();
}

function syncChatMessagesIncremental(channelKey) {
  const previousKeys = state.renderedChatKeys;
  const nextKeys = state.chatEvents.map(chatEventKey);
  if (previousKeys.length === 0 || nextKeys.length === 0 || renderedChatMessageCount() !== previousKeys.length) {
    renderChatMessagesFull(channelKey);
    return;
  }

  if (nextKeys.length === previousKeys.length + 1 && keysEqual(previousKeys, nextKeys.slice(0, previousKeys.length))) {
    appendRenderedChatEvent(state.chatEvents[state.chatEvents.length - 1], state.chatEvents.length - 1, nextKeys[nextKeys.length - 1]);
  } else if (nextKeys.length === previousKeys.length && keysEqual(previousKeys.slice(1), nextKeys.slice(0, nextKeys.length - 1))) {
    els.chatMessages.firstElementChild?.remove();
    appendRenderedChatEvent(state.chatEvents[state.chatEvents.length - 1], state.chatEvents.length - 1, nextKeys[nextKeys.length - 1]);
  } else if (nextKeys.length === previousKeys.length && keysEqual(previousKeys, nextKeys)) {
    updateRenderedChatEvents(nextKeys);
  } else {
    renderChatMessagesFull(channelKey);
    return;
  }

  state.renderedChatVersion = state.chatVersion;
  state.renderedChatKeys = nextKeys;
  pruneRenderedChatSignatures(nextKeys);
  scheduleChatScrollToBottom();
}

function appendRenderedChatEvent(event, index, key) {
  const signature = chatEventSignature(event);
  const anchor = chatBottomAnchorNode();
  els.chatMessages.insertBefore(chatEventNodeWithMeta(event, key, signature), anchor);
  state.renderedChatSignatures.set(key, signature);
}

function updateRenderedChatEvents(keys) {
  keys.forEach((key, index) => {
    const event = state.chatEvents[index];
    const signature = chatEventSignature(event);
    if (state.renderedChatSignatures.get(key) === signature) {
      return;
    }

    const existing = renderedChatMessageAt(index);
    const next = chatEventNodeWithMeta(event, key, signature);
    existing?.replaceWith(next);
    state.renderedChatSignatures.set(key, signature);
  });
}

function renderedChatMessageCount() {
  return els.chatMessages.querySelectorAll(":scope > .chat-message").length;
}

function renderedChatMessageAt(index) {
  return els.chatMessages.querySelectorAll(":scope > .chat-message")[index] || null;
}

function chatBottomAnchorNode() {
  let anchor = els.chatMessages.querySelector(":scope > .chat-bottom-anchor");
  if (!anchor) {
    anchor = document.createElement("div");
    anchor.className = "chat-bottom-anchor";
    anchor.setAttribute("aria-hidden", "true");
  }
  if (anchor.parentElement !== els.chatMessages) {
    els.chatMessages.append(anchor);
  }
  return anchor;
}

function pruneRenderedChatSignatures(keys) {
  const keep = new Set(keys);
  for (const key of state.renderedChatSignatures.keys()) {
    if (!keep.has(key)) {
      state.renderedChatSignatures.delete(key);
    }
  }
}

function keysEqual(left, right) {
  if (left.length !== right.length) {
    return false;
  }

  return left.every((value, index) => value === right[index]);
}

function scheduleChatScrollToBottom() {
  if (state.chatScrollFrameId) {
    return;
  }

  const requestFrame = window.requestAnimationFrame || ((callback) => window.setTimeout(callback, 0));
  state.chatScrollFrameId = requestFrame(() => {
    state.chatScrollFrameId = 0;
    scrollChatMessagesToBottom();
  });
}

function cancelChatScrollToBottom() {
  if (state.chatScrollFrameId) {
    if (window.cancelAnimationFrame) {
      window.cancelAnimationFrame(state.chatScrollFrameId);
    } else {
      window.clearTimeout(state.chatScrollFrameId);
    }
    state.chatScrollFrameId = 0;
  }
}

function scrollChatMessagesToBottom() {
  if (!els.chatMessages?.isConnected || els.chatPanel.hidden) {
    return;
  }

  chatBottomAnchorNode();
  els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
}

function clearChatState(clearDraft = true) {
  state.chatEvents = [];
  if (clearDraft) {
    state.chatDraft = "";
  }
  markChatDirty();
  scheduleChatRender();
}

function setChatEvents(events) {
  state.chatEvents = [];
  events.forEach((event) => appendChatEvent(event, false));
  markChatDirty();
  scheduleChatRender();
}

function appendChatEvent(event, markDirty = true) {
  if (!event || !event.kind) {
    return;
  }

  const index = state.chatEvents.findIndex((candidate) => candidate.eventId && candidate.eventId === event.eventId);
  if (index >= 0) {
    state.chatEvents.splice(index, 1, event);
  } else {
    state.chatEvents.push(event);
  }
  if (state.chatEvents.length > 50) {
    state.chatEvents.splice(0, state.chatEvents.length - 50);
  }
  if (markDirty) {
    markChatDirty();
    scheduleChatRender();
  }
}

function markChatDirty() {
  state.chatVersion += 1;
}

function chatEventNodeWithMeta(event, key, signature) {
  const node = chatEventNode(event);
  node.dataset.chatKey = key;
  node.dataset.chatSignature = signature;
  return node;
}

function chatEventKey(event, index) {
  if (event?.eventId) {
    return `event:${event.eventId}`;
  }
  if (event?.id) {
    return `id:${event.id}`;
  }
  if (Number.isFinite(Number(event?.seq))) {
    return `seq:${Number(event.seq)}`;
  }
  return `fallback:${chatEventSignature(event)}:${index}`;
}

function chatEventSignature(event = {}) {
  return [
    event.kind || "",
    event.createdAt || "",
    event.text || "",
    event.emojiId || "",
    event.emojiType || event.type || "",
    event.emojiIndex ?? event.index ?? "",
    event.targetSeatIndex ?? "",
    event.targetUserId ?? "",
    event.user?.userId ?? "",
    event.user?.username || "",
    event.user?.displayName || ""
  ].join("\u001f");
}

function chatEventNode(event) {
  const item = document.createElement("div");
  item.className = `chat-message chat-${event.kind}`;

  const meta = document.createElement("span");
  meta.className = "chat-meta";
  meta.textContent = `${event.user?.username || event.user?.displayName || "玩家"} ${timeLabel(event.createdAt)}`;

  const body = document.createElement("span");
  body.className = "chat-body";
  if (event.kind === "emoji") {
    appendChatEmoji(body, event);
  } else {
    body.textContent = event.text || "";
  }

  item.append(meta, body);
  return item;
}

function appendChatEmoji(body, event) {
  const emoji = emojiById[event.emojiId] || emojiById.smile;
  const target = emojiTargetLabel(event);
  if (target) {
    const label = document.createElement("span");
    label.className = "chat-emoji-target";
    label.textContent = `向${target}`;
    body.append(label);
  }

  const image = document.createElement("img");
  image.className = "chat-emoji-image";
  image.src = emojiUrl(String(event.emojiType || event.type || emoji.asset), Number(event.emojiIndex ?? event.index ?? 0));
  image.alt = emoji.label;
  image.title = emoji.label;
  image.loading = "lazy";
  image.decoding = "async";
  image.addEventListener("load", () => scheduleChatScrollToBottom(), { once: true });
  body.append(image);
}

function emojiTargetLabel(event) {
  if (Number.isInteger(event.targetSeatIndex)) {
    const seat = state.table?.room?.seats?.find((candidate) => candidate.seatIndex === event.targetSeatIndex);
    return seat?.user?.displayName || seatLabel(event.targetSeatIndex);
  }

  if (Number.isInteger(event.targetUserId)) {
    const seat = state.table?.room?.seats?.find((candidate) => candidate.user?.userId === event.targetUserId);
    const observer = state.table?.room?.observers?.find((candidate) => candidate.user?.userId === event.targetUserId);
    return seat?.user?.displayName || observer?.user?.displayName || `用户 ${event.targetUserId}`;
  }

  return "";
}

function timeLabel(value) {
  const date = value ? new Date(value) : null;
  if (!date || Number.isNaN(date.getTime())) {
    return "";
  }

  return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function renderEmojiDock() {
  els.emojiPanel.hidden = !state.currentRoomKey;
  if (!state.currentRoomKey) {
    if (state.renderedEmojiSignature !== "hidden") {
      els.emojiTarget.replaceChildren();
      els.emojiDock.replaceChildren();
      state.renderedEmojiSignature = "hidden";
    }
    return;
  }

  const options = emojiTargetOptions();
  const interactionLocked = isGameInteractionLocked();
  const emojiPending = isActionPending("emoji.send", state.currentRoomKey);
  const signature = [
    state.currentRoomKey,
    interactionLocked ? "1" : "0",
    emojiPending ? "1" : "0",
    state.emojiTarget,
    options.map((option) => `${option.value}\u001f${option.label}`).join("\u001e")
  ].join("|");
  if (state.renderedEmojiSignature === signature) {
    return;
  }
  state.renderedEmojiSignature = signature;

  renderEmojiTargetOptions(options);
  els.emojiDock.replaceChildren(...emojiCatalog.map((emoji) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "emoji-button";
    button.title = emoji.label;
    button.disabled = interactionLocked || emojiPending;
    button.innerHTML = `<img src="${escapeAttribute(emojiUrl(emoji.asset, 0))}" alt="${escapeAttribute(emoji.label)}" loading="lazy" decoding="async" />`;
    button.addEventListener("click", () => {
      void sendCommand("emoji.send", {
        roomKey: state.currentRoomKey,
        payload: {
          emojiId: emoji.id,
          ...emojiTargetPayload()
        }
      }).catch(reportError);
    });
    return button;
  }));
}

function emojiTargetOptions() {
  const options = [{ value: "", label: "牌桌" }];
  for (const seat of state.table?.room?.seats || []) {
    if (!seat.user) {
      continue;
    }
    options.push({
      value: `seat:${seat.seatIndex}`,
      label: `${seatLabel(seat.seatIndex)}：${seat.user.displayName || seat.user.username}`
    });
  }

  return options;
}

function renderEmojiTargetOptions(options) {
  if (!options.some((option) => option.value === state.emojiTarget)) {
    state.emojiTarget = "";
  }

  els.emojiTarget.replaceChildren(...options.map((option) => {
    const node = document.createElement("option");
    node.value = option.value;
    node.textContent = option.label;
    return node;
  }));
  els.emojiTarget.value = state.emojiTarget;
}

function emojiTargetPayload() {
  if (!state.emojiTarget.startsWith("seat:")) {
    return {};
  }

  const seatIndex = Number(state.emojiTarget.slice("seat:".length));
  return Number.isInteger(seatIndex) ? { targetSeatIndex: seatIndex } : {};
}

function showEmoji(payload = {}) {
  const emoji = emojiById[payload.emojiId];
  const type = String(payload.emojiType || payload.type || emoji?.asset || "goodjob");
  const index = Math.max(0, Math.min(3, Number(payload.emojiIndex ?? payload.index ?? 0)));
  const target = emojiAnimationTarget(payload);
  const stage = document.createElement("div");
  stage.className = target === els.table ? "emoji-pop" : "emoji-pop emoji-pop-seat";
  stage.innerHTML = `<img src="${escapeAttribute(emojiUrl(type, index))}" alt="${escapeAttribute(emoji?.label || "表情")}" decoding="async" />`;
  target.appendChild(stage);
  window.setTimeout(() => stage.remove(), 2200);
}

function emojiAnimationTarget(payload) {
  const seatIndex = emojiTargetSeatIndex(payload);
  if (seatIndex === undefined) {
    return els.table;
  }

  return els.table.querySelector(`[data-seat-index="${seatIndex}"]`) || els.table;
}

function emojiTargetSeatIndex(payload) {
  if (Number.isInteger(payload.targetSeatIndex)) {
    return payload.targetSeatIndex;
  }

  if (Number.isInteger(payload.targetUserId)) {
    const seat = state.table?.room?.seats?.find((candidate) => candidate.user?.userId === payload.targetUserId);
    return seat?.seatIndex;
  }

  return undefined;
}

function emojiUrl(type, index) {
  const normalizedType = emojiTypes.includes(type) ? type : "goodjob";
  return `${state.assetBaseUrl}/tractor/emoji/${normalizedType}${index}.gif`;
}

function playCommandSound(commandType) {
  switch (commandType) {
    case "room.join":
      playSound("effect/enter_room_kongcheng11.mp3");
      break;
    case "seat.claim":
    case "seat.release":
    case "seat.remove":
    case "player.ready":
    case "observer.watch":
      playSound("effect/draw.mp3");
      break;
    case "tractor.start":
      playSound("effect/game_start.mp3");
      break;
    default:
      break;
  }
}

function playTableSound(nextTable, commandType) {
  const previousPhase = state.table?.phase || "";
  const nextPhase = nextTable?.phase || "";
  if (commandType === "tractor.start") {
    playSound("effect/game_start.mp3");
  } else if (commandType === "tractor.makeTrump") {
    playSound("effect/liangpai_m_shelie1.mp3");
  } else if (commandType === "tractor.discardBottom") {
    playSound("effect/drawx.mp3");
  } else if (commandType === "tractor.playCards" || commandType === "tractor.autoPlay") {
    playSound("effect/tie.mp3");
  } else if (nextPhase === "finished" && previousPhase !== "finished") {
    playSound("effect/win.mp3");
  }
}

function playSound(path) {
  if (!state.soundEnabled || !state.audioBaseUrl || !path) {
    return;
  }

  let audio = audioCache.get(path);
  if (!audio) {
    audio = new Audio(`${state.audioBaseUrl}/${path}`);
    audio.preload = "auto";
    audioCache.set(path, audio);
  } else {
    try {
      audio.pause();
      audio.currentTime = 0;
    } catch {
      audioCache.delete(path);
      audio = new Audio(`${state.audioBaseUrl}/${path}`);
      audio.preload = "auto";
      audioCache.set(path, audio);
    }
  }
  audio.volume = 0.55;
  void audio.play().catch(() => {});
}

function mergeRoom(room) {
  if (!room?.roomKey) {
    return;
  }

  const index = state.rooms.findIndex((candidate) => candidate.roomKey === room.roomKey);
  if (index >= 0) {
    state.rooms.splice(index, 1, room);
  } else {
    state.rooms.push(room);
    state.rooms.sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0));
  }
}

function phaseText(phase) {
  const key = String(phase || "waiting_for_players");
  return phaseLabels[key] || "等待中";
}

function actionReasonText(reason) {
  const key = String(reason || "");
  return phaseLabels[key] || serverErrorText(key, "");
}

function tableActionReasonText(table, reason) {
  if (String(reason || "") === "game_paused" && table && isTablePaused(table)) {
    return pauseText(table);
  }

  return actionReasonText(reason);
}

function rankLabel(rank) {
  if (rank === undefined || rank === null) {
    return "-";
  }

  return cardRankLabels[rank] || String(rank);
}

function cardLabelText(card) {
  const id = Number(card?.id);
  if (id === 52) {
    return "小王";
  }
  if (id === 53) {
    return "大王";
  }

  const suit = cardSuitLabels[card?.suit] ? card.suit : cardSuitFromId(id);
  const rank = Number.isInteger(Number(card?.rank)) ? Number(card.rank) : cardRankFromId(id);
  const label = `${cardSuitLabels[suit] || ""}${cardRankLabels[rank] || ""}`;
  return label || card?.label || "";
}

function cardSuitFromId(id) {
  if (!Number.isInteger(id) || id < 0 || id > 51) {
    return "";
  }
  if (id < 13) {
    return "heart";
  }
  if (id < 26) {
    return "spade";
  }
  if (id < 39) {
    return "diamond";
  }
  return "club";
}

function cardRankFromId(id) {
  return Number.isInteger(id) && id >= 0 && id <= 51 ? id % 13 : -1;
}

function trumpLabel(trump) {
  if (!trump || trump.suit === "none") {
    return "无主";
  }

  const suits = {
    heart: "红桃主",
    spade: "黑桃主",
    diamond: "方块主",
    club: "梅花主",
    joker: "王主"
  };
  const label = suits[trump.suit] || "无主";
  const exposure = trumpExposureLabel(trump.exposure);
  return exposure ? `${label}（${exposure}）` : label;
}

function trumpExposureLabel(exposure) {
  const labels = {
    single_rank: "单张",
    pair_rank: "对子",
    pair_black_joker: "小王对",
    pair_red_joker: "大王对"
  };
  return labels[exposure] || "";
}

function setStatus(message) {
  els.actionStatus.textContent = message;
  renderTopbar();
}

function reportError(error) {
  setStatus(error.message || "操作失败");
}

function errorMessage(message) {
  const payload = message?.payload || {};
  if (payload.code === "tractor_dump_failed") {
    return dumpFailureErrorText(payload);
  }
  if (payload.code && String(payload.code).startsWith("tractor_")) {
    return tractorRuleErrorText(payload);
  }
  return serverErrorText(payload.code, payload.message);
}

function tractorRuleErrorText(payload) {
  const base = serverErrorText(payload.code, payload.message);
  const tractor = payload?.details?.tractor;
  if (!tractor || typeof tractor !== "object") {
    return base;
  }

  const parts = [];
  const leadingSuit = effectiveSuitText(tractor.leadingSuit, tractor.trumpSuit);
  const leadingCards = ruleCardsText(tractor.leadingCards, tractor.trumpSuit);
  const selectedCards = ruleCardsText(tractor.selectedCards, tractor.trumpSuit);
  const selectedSuits = Array.isArray(tractor.selectedSuits)
    ? tractor.selectedSuits.map((suit) => effectiveSuitText(suit, tractor.trumpSuit)).filter(Boolean)
    : [];
  const selectedText = [...new Set(selectedSuits)].join("、");
  const heldCount = Number(tractor.leadingSuitHeldCount);

  if (leadingSuit) {
    parts.push(`首家出的是${leadingSuit}${leadingCards ? `（${leadingCards}）` : ""}`);
  } else if (leadingCards) {
    parts.push(`首家出的是${leadingCards}`);
  }
  if (selectedCards) {
    parts.push(`你出的是${selectedCards}`);
  }
  if (selectedText && !selectedCards) {
    parts.push(`你选择的是${selectedText}`);
  }
  if (Number.isFinite(heldCount) && heldCount > 0 && leadingSuit) {
    parts.push(`手里还有 ${heldCount} 张${leadingSuit}必须先跟`);
  }

  return parts.length ? `${base}：${parts.join("，")}。` : base;
}

function ruleCardsText(cards, trumpSuit) {
  if (!Array.isArray(cards)) {
    return "";
  }

  return cards
    .map((card) => {
      if (!card || typeof card !== "object") {
        return "";
      }
      const label = cardLabelText(card) || card.label;
      const effectiveSuit = effectiveSuitText(card.effectiveSuit, trumpSuit);
      const reason = trumpReasonText(card.trumpReason);
      const suffix = effectiveSuit
        ? `按${effectiveSuit}${reason ? `（${reason}）` : ""}处理`
        : "";
      return suffix ? `${label}，${suffix}` : label;
    })
    .filter(Boolean)
    .join("、");
}

function trumpReasonText(reason) {
  const labels = {
    joker: "王",
    rank: "级牌",
    trump_suit: "主花色",
    trump: "主牌"
  };
  return labels[String(reason || "")] || "";
}

function dumpFailureErrorText(payload) {
  const failure = dumpFailureFromPayload(payload);
  const base = serverErrorText(payload.code, payload.message);
  if (!failure) {
    return base;
  }

  const hints = cardIdsLabelText(failure.mustPlayCards);
  const penalty = Number.isFinite(failure.penalty) ? `，罚分 ${failure.penalty}` : "";
  const score = Number.isFinite(failure.score) ? ` 当前分数 ${failure.score}。` : "";
  return hints
    ? `${base}${penalty}。请改出：${hints}。${score}`
    : `${base}${penalty}。${score}`;
}

function dumpFailureFromPayload(payload) {
  const failure = payload?.details?.dumpFailure || payload?.dumpFailure;
  if (!failure || typeof failure !== "object") {
    return null;
  }

  return {
    attemptedCards: numberArray(failure.attemptedCards),
    mustPlayCards: numberArray(failure.mustPlayCards),
    penalty: Number(failure.penalty),
    scoreDelta: Number(failure.scoreDelta),
    score: Number(failure.score)
  };
}

function numberArray(value) {
  return Array.isArray(value)
    ? value.map(Number).filter((item) => Number.isInteger(item))
    : [];
}

function cardIdsLabelText(cardIds) {
  return numberArray(cardIds)
    .map((id) => cardLabelText({ id }))
    .filter(Boolean)
    .join(" ");
}

function effectiveSuitText(suit, trumpSuit) {
  const key = String(suit || "");
  if (!key || key === "none") {
    return "";
  }
  if (key === String(trumpSuit || "")) {
    return "主牌";
  }
  const labels = {
    heart: "红桃",
    spade: "黑桃",
    diamond: "方块",
    club: "梅花",
    joker: "王主"
  };
  return labels[key] || key;
}

function statusText() {
  if (state.connecting) {
    return "正在连接";
  }
  if (state.recovering) {
    return "正在同步";
  }
  if (state.authenticated) {
    return "已认证";
  }
  if (state.connected) {
    return "已连接";
  }
  return "未连接";
}

function statusTextForRoom(status) {
  const key = String(status || "waiting");
  return statusLabels[key] || "未知状态";
}

function roomGameLabel(room) {
  const raw = String(room?.gameName || room?.gameTitle || room?.gameLabel || room?.gameDisplayName || room?.gameType || "").trim();
  if (!raw || raw === "tractor" || raw === "shengji") {
    return "升级80分";
  }
  return raw;
}

function roomGameIconText(room) {
  const label = roomGameLabel(room);
  if (label === "升级" || label === "升级80分") {
    return "升";
  }
  return Array.from(label)[0] || "游";
}

function seatLabel(seatIndex) {
  return seatNumberLabel(seatIndex);
}

function seatNumberLabel(seatIndex) {
  const number = Number(seatIndex) + 1;
  return Number.isFinite(number) ? `${number}号座位` : "";
}

function bottomHolderLabel(table) {
  if (table?.phase !== "burying_bottom" && !(table?.phase === "making_trump" && Number.isFinite(trumpRevealAtMs(table)))) {
    return "";
  }
  return seatUserLabel(table, table.engine?.public?.bottomHolderSeatIndex);
}

function trumpDeclarerLabel(table) {
  const publicState = table?.engine?.public;
  const trump = publicState?.trump;
  if (!trump || !trump.exposure || trump.exposure === "none") {
    return "";
  }

  const seatIndex = firstIntegerValue(
    trump.declarerSeatIndex,
    trump.declaredBySeatIndex,
    trump.exposedBySeatIndex,
    trump.exposureSeatIndex,
    trump.callerSeatIndex,
    trump.makerSeatIndex,
    trump.playerSeatIndex,
    trump.seatIndex,
    publicState.trumpDeclarerSeatIndex,
    publicState.trumpDeclaredBySeatIndex,
    publicState.trumpExposedBySeatIndex,
    publicState.trumpCallerSeatIndex,
    publicState.trumpMakerSeatIndex,
    publicState.trumpSeatIndex
  );
  return seatIndex === undefined ? "" : seatUserLabel(table, seatIndex);
}

function firstIntegerValue(...values) {
  for (const value of values) {
    const number = Number(value);
    if (Number.isInteger(number)) {
      return number;
    }
  }
  return undefined;
}

function seatUserLabel(table, seatIndex) {
  const numericSeatIndex = Number(seatIndex);
  if (!Number.isInteger(numericSeatIndex)) {
    return "";
  }
  const seat = table?.room?.seats?.find((candidate) => candidate.seatIndex === numericSeatIndex);
  return seat?.user?.displayName || seat?.user?.username || seatLabel(numericSeatIndex);
}

function skinDisplayName(skin) {
  return skinLabels[skin.skinId] || skin.displayName || skin.skinId;
}

function serverErrorText(code, message) {
  if (code === "tractor_game_paused") {
    return pausedErrorText();
  }

  const labels = {
    already_authenticated: "连接已经认证",
    auth_failed: "认证失败",
    auth_unavailable: "认证服务暂时不可用",
    bottom_holder_only: "只有底牌持有者可以埋牌",
    bottom_not_open: "现在还不能埋牌",
    chat_message_too_long: "聊天内容过长",
    not_authenticated: "请先连接并认证",
    not_in_room: "请先进入房间",
    not_implemented: "该操作暂未开放",
    not_room_owner: "只有房主可以操作",
    not_tractor_room: "该房间不是拖拉机房间",
    room_not_found: "房间不存在",
    room_closed: "房间已关闭",
    room_not_waiting: "房间不在等待状态",
    room_active: "房间正在游戏中",
    room_busy: "房间正在同步，请稍候",
    room_disabled: "房间已停用",
    room_has_active_game: "房间有进行中的游戏，无法重置",
    room_required: "请选择房间",
    seat_taken: "座位已被占用",
    seat_not_found: "座位不存在",
    seat_not_offline: "只能移除离线玩家",
    already_seated: "您已经入座",
    not_seated: "您尚未入座",
    seated_observer_forbidden: "已入座玩家不能观看其他手牌",
    observer_target_locked: "游戏中不能切换观看座位",
    watched_seat_empty: "只能观看已有玩家的座位",
    skin_not_found: "皮肤不存在",
    skin_not_owned: "您尚未拥有该皮肤",
    token_expired: "登录令牌已过期，请重新进入游戏",
    token_issued_in_future: "登录令牌时间无效，请稍后重试",
    token_not_active: "登录令牌尚未生效，请稍后重试",
    token_replayed: "登录令牌已使用，请重新进入游戏",
    token_too_large: "登录令牌过大",
    invalid_audience: "登录令牌目标无效",
    waiting_for_turn: "还没轮到您出牌",
    play_not_open: "现在还不能出牌",
    trump_not_open: "现在还不能亮主/反主",
    empty_chat_message: "聊天内容不能为空",
    game_persistence_failed: "游戏保存失败",
    game_persistence_unavailable: "游戏保存服务暂时不可用",
    internal_error: "服务器内部错误",
    invalid_action: "无效操作",
    invalid_auth_payload: "认证请求无效",
    invalid_chat_payload: "聊天内容无效",
    invalid_emoji_payload: "表情内容无效",
    invalid_emoji_target: "表情目标无效",
    invalid_issuer: "登录令牌来源无效",
    invalid_json: "消息格式无效",
    invalid_message: "消息格式无效",
    invalid_payload: "请求内容无效",
    invalid_schema: "消息内容无效",
    invalid_seat: "座位无效",
    invalid_skin_payload: "皮肤设置无效",
    invalid_suit: "花色无效",
    invalid_token: "登录令牌无效",
    invalid_token_claims: "登录令牌内容无效",
    invalid_token_header: "登录令牌头无效",
    invalid_trump_exposure: "亮主/反主组合无效",
    message_too_large: "消息太大",
    rate_limited: "操作太频繁，请稍后再试",
    refresh_unavailable: "用户状态暂时无法刷新",
    session_not_active: "游戏会话未激活",
    session_not_found: "游戏会话不存在",
    tractor_bottom_cards_not_held: "埋牌必须从您的手牌中选择",
    tractor_card_count_mismatch: "出牌张数必须与首家一致",
    tractor_cards_not_held: "出牌必须从您的手牌中选择",
    tractor_auto_play_unavailable: "当前不能自动出牌",
    tractor_duplicate_player: "您已经在本局其他座位中",
    tractor_dump_failed: "甩牌失败",
    tractor_dump_not_supported: "暂不支持甩牌",
    tractor_follow_pair_required: "有对子时必须尽量跟对子",
    tractor_follow_suit_required: "有同花色时必须跟同花色",
    tractor_follow_tractor_required: "有拖拉机时必须跟拖拉机",
    tractor_hand_active: "本房间已有进行中的牌局",
    tractor_invalid_lead: "首出必须为同一花色",
    tractor_invalid_recovery_snapshot: "恢复快照无效",
    tractor_invalid_turn_order: "出牌顺序无效",
    tractor_missing_player: "四个座位坐满后才能开始",
    tractor_next_starter_not_found: "无法确定下一墩先手",
    tractor_no_active_hand: "本房间没有进行中的牌局",
    tractor_no_active_trick: "当前没有进行中的一墩牌",
    tractor_not_bottom_holder: "只有底牌持有者可以埋牌",
    tractor_not_burying_bottom: "当前不能埋牌",
    tractor_not_player: "只有入座玩家可以操作",
    tractor_not_playing: "当前不能出牌",
    tractor_not_your_turn: "还没轮到您出牌",
    tractor_player_not_found: "找不到牌局玩家",
    tractor_players_not_ready: "四名玩家都在线并准备后才能开始",
    tractor_requires_four_seats: "拖拉机需要四个座位",
    tractor_snapshot_unavailable: "拖拉机恢复快照不可用",
    tractor_trick_incomplete: "本墩牌尚未完成",
    tractor_trick_reviewing: "请先看完上一墩出牌",
    tractor_trump_cards_not_held: "亮主/反主必须使用您的手牌",
    tractor_trump_closed: "反主窗口已结束，不能再亮主/反主",
    tractor_trump_reveal_pending: "反主窗口还未结束，底牌稍后翻开",
    tractor_trump_too_weak: "反主级别不够",
    unsupported_message: "不支持的消息类型",
    chat_rate_limited: "聊天太频繁，请稍后再试",
    emoji_rate_limited: "表情发送太频繁，请稍后再试"
  };
  return labels[code] || (/[\u4e00-\u9fff]/.test(message || "") ? message : "服务器出错");
}

function pausedErrorText() {
  if (state.table && isTablePaused(state.table)) {
    return pauseText(state.table);
  }

  return "游戏暂停，等待玩家上线、准备或空位补位。";
}

function defaultWsUrl() {
  const scheme = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${scheme}//${window.location.host}/card-games/ws`;
}

function defaultConfigUrl() {
  return new URL("../config", window.location.href).toString();
}

function defaultRoundLogUrl() {
  return new URL("../replay/round-log", window.location.href).toString();
}

function queryParam(name) {
  return new URLSearchParams(window.location.search).get(name) || "";
}

function withoutEmptyValues(input) {
  return Object.fromEntries(Object.entries(input).filter(([, value]) => value !== ""));
}

function normalizeBaseUrl(url) {
  return String(url || "").replace(/\/+$/, "");
}

function defaultAssetBaseUrl() {
  if (!scriptUrl) {
    return "/ext/freemitbbs/cardgamesauth/styles/all/theme/images";
  }

  return new URL("../../theme/images", scriptUrl).toString().replace(/\/+$/, "");
}

function defaultAudioBaseUrl() {
  if (!scriptUrl) {
    return "/ext/freemitbbs/cardgamesauth/styles/all/theme/audio";
  }

  return new URL("../../theme/audio", scriptUrl).toString().replace(/\/+$/, "");
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll("`", "&#096;");
}
