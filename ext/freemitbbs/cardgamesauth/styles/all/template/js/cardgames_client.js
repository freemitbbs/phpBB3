const state = {
  bootstrap: {},
  initialized: false,
  token: "",
  user: null,
  ws: null,
  connected: false,
  authenticated: false,
  connecting: false,
  requestSeq: 0,
  pending: new Map(),
  rooms: [],
  table: null,
  currentRoomKey: "",
  skinProfile: null,
  chatEvents: [],
  chatDraft: "",
  emojiTarget: "",
  selectedHandIndexes: [],
  assetBaseUrl: "",
  audioBaseUrl: "",
  cardStyle: "cardsclassic",
  soundEnabled: false
};

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
const emojiCatalog = [
  { id: "smile", asset: "goodjob", label: "微笑" },
  { id: "laugh", asset: "lol", label: "大笑" },
  { id: "thumbs_up", asset: "youareright", label: "点赞" },
  { id: "clap", asset: "goodjob", label: "鼓掌" },
  { id: "fire", asset: "fireworks", label: "烟花" },
  { id: "thinking", asset: "hurryup", label: "思考" },
  { id: "surprise", asset: "sorry", label: "惊讶" },
  { id: "sad", asset: "sorry", label: "难过" },
  { id: "angry", asset: "hurryup", label: "着急" },
  { id: "good_luck", asset: "noproblem", label: "好运" }
];
const emojiTypes = ["goodjob", "hurryup", "sorry", "lol", "noproblem", "fireworks", "youareright"];
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
  burying_bottom: "埋牌",
  playing: "出牌中",
  finished: "已结束",
  trump_not_open: "现在还不能亮主",
  bottom_holder_only: "只有底牌持有者可以埋牌",
  bottom_not_open: "现在还不能埋牌",
  waiting_for_turn: "还没轮到您出牌",
  play_not_open: "现在还不能出牌"
};
const cardSuitLabels = {
  heart: "红桃",
  spade: "黑桃",
  diamond: "方块",
  club: "梅花"
};
const cardRankLabels = ["2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A"];

const els = {
  status: document.querySelector("#connection-status"),
  user: document.querySelector("#user-label"),
  skin: document.querySelector("#skin-select"),
  connect: document.querySelector("#connect-button"),
  sound: document.querySelector("#sound-button"),
  refreshRooms: document.querySelector("#refresh-rooms-button"),
  rooms: document.querySelector("#rooms-list"),
  title: document.querySelector("#table-title"),
  leave: document.querySelector("#leave-room-button"),
  table: document.querySelector("#table-view"),
  emojiPanel: document.querySelector("#emoji-panel"),
  emojiTarget: document.querySelector("#emoji-target-select"),
  emojiDock: document.querySelector("#emoji-dock"),
  chatPanel: document.querySelector("#chat-panel"),
  chatMessages: document.querySelector("#chat-messages"),
  chatForm: document.querySelector("#chat-form"),
  chatInput: document.querySelector("#chat-input"),
  log: document.querySelector("#event-log")
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
els.refreshRooms.addEventListener("click", () => {
  void requestRooms();
});
els.skin.addEventListener("change", () => {
  void selectSkin(els.skin.value);
});
els.leave.addEventListener("click", () => {
  if (state.currentRoomKey) {
    void sendCommand("room.leave", { roomKey: state.currentRoomKey })
      .then(() => {
        state.currentRoomKey = "";
        state.table = null;
        state.chatEvents = [];
        state.emojiTarget = "";
        render();
        return requestRooms();
      })
      .catch(reportError);
  }
});
els.chatForm.addEventListener("submit", (event) => {
  event.preventDefault();
  void sendChatMessage();
});
els.chatInput.addEventListener("input", () => {
  state.chatDraft = els.chatInput.value;
});
els.emojiTarget.addEventListener("change", () => {
  state.emojiTarget = els.emojiTarget.value;
});
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

void init();

async function init() {
  state.bootstrap = await loadBootstrap();
  state.token = queryParam("token") || state.bootstrap.token || "";
  state.assetBaseUrl = normalizeBaseUrl(state.bootstrap.assetBaseUrl || defaultAssetBaseUrl());
  state.audioBaseUrl = normalizeBaseUrl(state.bootstrap.audioBaseUrl || defaultAudioBaseUrl());
  state.cardStyle = state.bootstrap.cardStyle || "cardsclassic";
  state.initialized = true;
  render();
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
    tokenHash: params.get("hash") || "",
    autoConnect: params.get("connect") !== "0"
  };

  if (inline.tokenUrl || inline.wsUrl || fromQuery.token || fromQuery.wsUrl) {
    return { ...inline, ...withoutEmptyValues(fromQuery) };
  }

  try {
    const response = await fetch("/card-games/config", { credentials: "same-origin" });
    if (!response.ok) {
      return fromQuery;
    }
    const config = await response.json();
    return { ...config, ...withoutEmptyValues(fromQuery) };
  } catch {
    return fromQuery;
  }
}

async function connect() {
  if (state.connecting) {
    return;
  }

  disconnect();
  state.connecting = true;
  setStatus("正在连接");

  try {
    if (state.bootstrap.tokenUrl && state.bootstrap.tokenHash) {
      state.token = await fetchToken();
    } else if (!state.token) {
      state.token = await fetchToken();
    }
    const wsUrl = state.bootstrap.wsUrl || defaultWsUrl();
    const ws = new WebSocket(wsUrl);
    state.ws = ws;

    ws.addEventListener("open", () => {
      if (state.ws !== ws) {
        return;
      }
      state.connected = true;
      state.connecting = false;
      render();
      void authenticate();
    });
    ws.addEventListener("message", (event) => {
      if (state.ws !== ws) {
        return;
      }
      handleMessage(event.data);
    });
    ws.addEventListener("close", () => {
      if (state.ws !== ws) {
        return;
      }
      state.ws = null;
      state.connected = false;
      state.authenticated = false;
      state.connecting = false;
      rejectPending("连接已断开");
      render();
    });
    ws.addEventListener("error", () => {
      if (state.ws !== ws) {
        return;
      }
      state.connecting = false;
      setStatus("连接出错");
    });
  } catch (error) {
    state.connecting = false;
    setStatus(error.message || "连接失败");
  }
}

function disconnect() {
  const ws = state.ws;
  if (state.ws && state.ws.readyState < WebSocket.CLOSING) {
    state.ws.close();
  }
  state.ws = null;
  state.connected = false;
  state.authenticated = false;
  state.connecting = false;
  if (ws) {
    rejectPending("连接已断开");
  } else {
    state.pending.clear();
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

function rejectPending(message) {
  state.pending.forEach((pending) => pending.reject(new Error(message)));
  state.pending.clear();
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
    throw new Error(payload.error || "获取游戏令牌失败");
  }

  state.bootstrap.wsUrl = payload.wsUrl || state.bootstrap.wsUrl;
  state.user = payload.user || state.user;
  return payload.token;
}

async function authenticate() {
  try {
    const response = await sendCommand("auth.token", { payload: { token: state.token } });
    state.user = response.payload.user;
    state.authenticated = true;
    await requestSkinProfile();
    await requestRooms();
    setStatus("已连接");
  } catch (error) {
    setStatus(error.message || "认证失败");
  }
}

async function requestRooms() {
  if (!state.authenticated) {
    return;
  }

  const response = await sendCommand("lobby.rooms", {});
  state.rooms = response.payload.rooms;
  render();
}

async function requestSkinProfile() {
  if (!state.authenticated) {
    return;
  }

  const response = await sendCommand("profile.skins", {});
  applySkinProfile(response.payload);
  render();
}

async function selectSkin(skinId) {
  if (!skinId || !state.authenticated) {
    return;
  }

  try {
    const response = await sendCommand("profile.skin.select", {
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
  const response = await sendCommand("tractor.table", { roomKey });
  state.table = response.payload.table;
  state.currentRoomKey = roomKey;
  syncSelectedHandIndexes();
  render();
}

function sendCommand(type, options) {
  if (!state.ws || state.ws.readyState !== WebSocket.OPEN) {
    state.connected = false;
    state.authenticated = false;
    render();
    void reconnectAfterRestore();
    return Promise.reject(new Error("连接已断开，正在重连"));
  }

  const requestId = `web-${Date.now()}-${++state.requestSeq}`;
  const envelope = {
    v: 1,
    requestId,
    type,
    roomKey: options.roomKey,
    payload: options.payload || {}
  };

  const promise = new Promise((resolve, reject) => {
    state.pending.set(requestId, { resolve, reject, type });
  });
  state.ws.send(JSON.stringify(envelope));
  return promise;
}

function handleMessage(raw) {
  let message;
  try {
    message = JSON.parse(raw);
  } catch {
    setStatus("收到无效的服务器消息");
    return;
  }

  if (message.requestId && state.pending.has(message.requestId)) {
    const pending = state.pending.get(message.requestId);
    state.pending.delete(message.requestId);
    if (message.type === "error") {
      pending.reject(new Error(errorMessage(message)));
      return;
    }
    applyServerMessage(message, pending.type);
    pending.resolve(message);
    return;
  }

  applyServerMessage(message);
}

function applyServerMessage(message, commandType = "") {
  switch (message.type) {
    case "system.hello":
      setStatus("服务器已就绪");
      break;
    case "auth.accepted":
      state.user = message.payload.user || state.user;
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
      state.rooms = message.payload.rooms || state.rooms;
      if (Array.isArray(message.payload.chat)) {
        setChatEvents(message.payload.chat);
      }
      if (message.payload.table) {
        state.table = message.payload.table;
        state.currentRoomKey = state.table.room.roomKey;
        syncSelectedHandIndexes();
      } else if (message.payload.room) {
        state.currentRoomKey = message.payload.room.roomKey;
      }
      render();
      break;
    case "lobby.rooms":
      state.rooms = message.payload.rooms || [];
      render();
      break;
    case "lobby.updated":
      state.rooms = message.payload.rooms;
      render();
      break;
    case "room.left":
      state.currentRoomKey = "";
      state.table = null;
      state.chatEvents = [];
      state.emojiTarget = "";
      playSound("effect/draw.mp3");
      void requestRooms();
      render();
      break;
    case "room.recovered":
      state.currentRoomKey = message.payload.room.roomKey;
      void requestChatHistory(state.currentRoomKey);
      void refreshTable(state.currentRoomKey);
      break;
    case "room.updated":
      mergeRoom(message.payload.room);
      if (message.payload.room.roomKey === state.currentRoomKey) {
        void refreshTable(state.currentRoomKey);
      }
      playCommandSound(commandType);
      render();
      break;
    case "room.reset":
    case "room.cancelled":
      if (message.payload.roomKey === state.currentRoomKey) {
        state.table = null;
        state.chatEvents = [];
        state.emojiTarget = "";
      }
      void requestRooms();
      render();
      break;
    case "tractor.table":
    case "tractor.table.updated":
      playTableSound(message.payload.table, commandType);
      state.table = message.payload.table;
      state.currentRoomKey = state.table.room.roomKey;
      syncSelectedHandIndexes();
      render();
      break;
    case "chat.history":
      if (!message.payload.roomKey || message.payload.roomKey === state.currentRoomKey) {
        setChatEvents(message.payload.events || []);
        render();
      }
      break;
    case "chat.event":
      if (!message.payload.event?.roomKey || message.payload.event.roomKey === state.currentRoomKey) {
        appendChatEvent(message.payload.event);
        if (message.payload.event.kind === "emoji") {
          showEmoji(message.payload.event);
        }
        render();
      }
      break;
    case "room.emoji":
    case "chat.emoji":
      showEmoji(message.payload);
      break;
    case "error":
      setStatus(errorMessage(message));
      break;
    default:
      break;
  }
}

function render() {
  els.status.textContent = statusText();
  els.user.textContent = state.user ? state.user.displayName || state.user.username : "";
  els.connect.textContent = state.connected ? "重新连接" : "连接";
  els.sound.textContent = state.soundEnabled ? "声音开" : "声音关";
  els.sound.setAttribute("aria-pressed", state.soundEnabled ? "true" : "false");
  renderSkinSelect();
  renderRooms();
  renderTable();
  renderEmojiDock();
  renderChatPanel();
}

function renderRooms() {
  if (!state.rooms.length) {
    els.rooms.innerHTML = '<div class="empty-state">尚未加载房间。</div>';
    return;
  }

  els.rooms.replaceChildren(...state.rooms.map((room) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "room-button";
    button.setAttribute("aria-current", room.roomKey === state.currentRoomKey ? "true" : "false");
    button.disabled = !room.enabled;
    button.innerHTML = `
      <span><strong>${escapeHtml(room.displayName)}</strong><span>${room.memberCount || 0}人</span></span>
      <span class="room-status">${escapeHtml(statusTextForRoom(room.status))}${room.enabled ? "" : " 已关闭"}</span>
    `;
    button.addEventListener("click", () => joinRoom(room.roomKey));
    return button;
  }));
}

function renderSkinSelect() {
  const profile = state.skinProfile;
  els.skin.hidden = !profile;
  if (!profile) {
    els.skin.replaceChildren();
    return;
  }

  const owned = new Set(profile.ownedSkinIds || []);
  els.skin.replaceChildren(...(profile.skins || []).map((skin) => {
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
  if (!table) {
    els.title.textContent = "大厅";
    els.table.innerHTML = '<div class="empty-state">请选择房间进入牌桌。</div>';
    return;
  }

  els.title.textContent = table.room.displayName;
  const seatNodes = table.room.seats.map((seat) => seatHtml(table, seat)).join("");
  const observers = table.room.observers.map((observer) => {
    const watched = observer.watchedSeatIndex === undefined ? "" : ` 正在观看${seatLabel(observer.watchedSeatIndex)}`;
    return `<span class="observer-pill">${escapeHtml(observer.user.displayName)}${watched}</span>`;
  }).join("");
  const score = table.engine?.public?.score ?? 0;
  const currentTrick = table.engine?.public?.currentTrick;
  const trickLabel = currentTrick
    ? `第 ${currentTrick.trickNumber} 墩，轮到${seatLabel(currentTrick.nextSeatIndex ?? currentTrick.winnerSeatIndex)}`
    : table.engineReady ? `得分 ${score}` : "等待中";
  const leaveAction = action(table, "room.leave");

  els.table.innerHTML = `
    <div class="table-grid">
      ${seatNodes}
      <div class="table-center">
        <div>
          <div class="table-emblem" aria-hidden="true"></div>
          <strong>${escapeHtml(phaseText(table.phase))}</strong>
          <p>${escapeHtml(trickLabel)}</p>
          <div class="table-facts">
            <span>级牌 ${escapeHtml(rankLabel(table.engine?.public?.rank))}</span>
            <span>${escapeHtml(trumpLabel(table.engine?.public?.trump))}</span>
          </div>
          <div class="table-actions">${tableActionsHtml(table)}</div>
        </div>
      </div>
    </div>
    ${trickHtml(table)}
    <div class="observer-list">${observers}</div>
    ${handHtml(table)}
  `;

  els.table.querySelectorAll("[data-action]").forEach((button) => {
    button.addEventListener("click", () => handleTableAction(button.dataset.action, button.dataset.seat));
  });
  els.table.querySelectorAll("[data-card-index]").forEach((button) => {
    button.addEventListener("click", () => toggleCardSelection(Number(button.dataset.cardIndex)));
  });
  els.leave.disabled = !leaveAction.enabled;
  els.leave.title = leaveAction.reason ? actionReasonText(leaveAction.reason) : "";
}

function seatHtml(table, seat) {
  const user = seat.user;
  const occupied = Boolean(user);
  const isViewerSeat = table.viewer.seatIndex === seat.seatIndex;
  const isOwner = table.room.owner?.userId && user?.userId === table.room.owner.userId;
  const visualSeatIndex = visualSeatIndexFor(table, seat.seatIndex);
  const meta = occupied
    ? `${seat.connected ? "在线" : "离线"} · ${seat.ready ? "已准备" : "未准备"}${isOwner ? " · 房主" : ""}`
    : "空位";
  const actions = seatActionsHtml(table, seat, isViewerSeat);
  const avatar = user?.avatarUrl
    ? `<span class="seat-avatar-stack"><img class="seat-skin" src="${escapeAttribute(skinUrlForSeat(user, seat.seatIndex))}" alt="" loading="lazy" /><img class="seat-profile-avatar" src="${escapeAttribute(user.avatarUrl)}" alt="" loading="lazy" /></span>`
    : `<img class="seat-skin" src="${escapeAttribute(skinUrlForSeat(user, seat.seatIndex))}" alt="" loading="lazy" />`;

  return `
    <div class="seat seat-${visualSeatIndex} ${isViewerSeat ? "seat-viewer" : ""} ${seat.connected ? "" : "seat-offline"}" data-seat-index="${seat.seatIndex}">
      ${avatar}
      <div class="seat-name">${occupied ? escapeHtml(user.displayName) : seatLabel(seat.seatIndex)}</div>
      <div class="seat-meta">${escapeHtml(meta)}</div>
      <div class="seat-actions">${actions}</div>
    </div>
  `;
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
  if (!seat.user && action(table, "seat.claim").enabled) {
    return `<button data-action="seat.claim" data-seat="${seat.seatIndex}" type="button">坐下</button>`;
  }
  if (isViewerSeat) {
    const readyAction = action(table, "player.ready");
    const releaseAction = action(table, "seat.release");
    return `
      <button data-action="player.ready" data-seat="${seat.seatIndex}" type="button" ${readyAction.enabled ? "" : "disabled"}>
        ${seat.ready ? "取消准备" : "准备"}
      </button>
      <button data-action="seat.release" data-seat="${seat.seatIndex}" type="button" ${releaseAction.enabled ? "" : "disabled"}>离座</button>
    `;
  }
  if (seat.user && action(table, "observer.watch").enabled && table.viewer.role === "observer") {
    return `<button data-action="observer.watch" data-seat="${seat.seatIndex}" type="button">观看</button>`;
  }
  return "";
}

function skinUrlForSeat(user, seatIndex) {
  const skinName = user?.selectedSkinId || user?.skinInUse || user?.skin || defaultSkins[Math.abs(Number(user?.userId ?? seatIndex)) % defaultSkins.length] || "skin_questionmark.webp";
  const fileName = skinName.includes(".") ? skinName : `${skinName}.webp`;
  return `${state.assetBaseUrl}/tractor/skin/${encodeURIComponent(fileName)}`;
}

function action(table, type) {
  return table.actions.find((item) => item.type === type) || { enabled: false };
}

function tableActionsHtml(table) {
  const startAction = action(table, "tractor.start");
  const makeTrumpAction = action(table, "tractor.makeTrump");
  const discardAction = action(table, "tractor.discardBottom");
  const playAction = action(table, "tractor.playCards");
  const selectedCards = selectedHandCards();
  const parts = [];

  if (startAction.enabled) {
    parts.push('<button data-action="tractor.start" type="button">开始</button>');
  }
  if (makeTrumpAction.enabled) {
    parts.push(`<button data-action="tractor.makeTrump" type="button" ${inferTrumpPayload(selectedCards, table) ? "" : "disabled"} title="请选择一张级牌或一对有效的牌">亮主</button>`);
  }
  if (discardAction.enabled) {
    parts.push(`<button data-action="tractor.discardBottom" type="button" ${selectedCards.length === discardAction.count ? "" : "disabled"} title="请选择 ${discardAction.count} 张牌">埋牌</button>`);
  }
  if (playAction.enabled) {
    parts.push(`<button data-action="tractor.playCards" type="button" ${selectedCards.length > 0 ? "" : "disabled"}>出牌</button>`);
  }

  return parts.join("");
}

function trickHtml(table) {
  const trick = table.engine?.public?.currentTrick || table.engine?.public?.lastCompletedTrick;
  if (!trick || !trick.plays?.length) {
    return "";
  }

  const plays = trick.plays.map((play) => `
    <div class="trick-play">
      <span>${seatLabel(play.seatIndex)}</span>
      <span class="played-cards">${play.cards.map((card) => cardFaceHtml(card, "played-card")).join("")}</span>
    </div>
  `).join("");
  return `<div class="trick-panel">${plays}</div>`;
}

function handHtml(table) {
  const cards = table.engine?.private?.cards || [];
  if (!cards.length) {
    return "";
  }

  const selected = new Set(state.selectedHandIndexes);
  const nodes = cards.map((card, index) => `
    <button class="hand-card" data-card-index="${index}" aria-pressed="${selected.has(index) ? "true" : "false"}" type="button">
      ${cardFaceHtml(card, "hand-card-face")}
      <span class="hand-card-label">${escapeHtml(cardLabelText(card))}</span>
    </button>
  `).join("");

  const columns = Math.max(1, Math.ceil(cards.length / 2));
  return `<div class="hand-panel" style="--hand-count: ${Math.max(cards.length, 1)}; --hand-columns: ${columns}">${nodes}</div>`;
}

function handleTableAction(type, seatValue) {
  const seatIndex = Number(seatValue);
  const roomKey = state.currentRoomKey;
  if (!roomKey) {
    return;
  }

  if (type === "seat.claim") {
    void sendCommand("seat.claim", { roomKey, payload: { seatIndex } }).catch(reportError);
  } else if (type === "seat.release") {
    void sendCommand("seat.release", { roomKey }).catch(reportError);
  } else if (type === "player.ready") {
    const seat = state.table.room.seats.find((candidate) => candidate.seatIndex === seatIndex);
    void sendCommand("player.ready", { roomKey, payload: { ready: !seat.ready } }).catch(reportError);
  } else if (type === "observer.watch") {
    void sendCommand("observer.watch", { roomKey, payload: { seatIndex } }).catch(reportError);
  } else if (type === "tractor.start") {
    setStatus("正在开始...");
    void sendCommand("tractor.start", { roomKey, payload: {} })
      .then(() => refreshTable(roomKey))
      .catch(reportError);
  } else if (type === "tractor.makeTrump") {
    const payload = inferTrumpPayload(selectedHandCards(), state.table);
    if (payload) {
      setStatus("正在亮主...");
      void sendEngineCommand("tractor.makeTrump", roomKey, payload);
    }
  } else if (type === "tractor.discardBottom") {
    setStatus("正在埋牌...");
    void sendEngineCommand("tractor.discardBottom", roomKey, { cards: selectedHandCards().map((card) => card.id) });
  } else if (type === "tractor.playCards") {
    setStatus("正在出牌...");
    void sendEngineCommand("tractor.playCards", roomKey, { cards: selectedHandCards().map((card) => card.id) });
  }
}

async function sendEngineCommand(type, roomKey, payload) {
  try {
    const response = await sendCommand(type, { roomKey, payload });
    state.selectedHandIndexes = [];
    if (response.payload?.table) {
      state.table = response.payload.table;
      syncSelectedHandIndexes();
      render();
      setStatus(engineCommandStatus(type, state.table));
    } else {
      await refreshTable(roomKey);
      setStatus("牌桌已更新");
    }
  } catch (error) {
    reportError(error);
  }
}

function engineCommandStatus(commandType, table) {
  const nextSeatIndex = table?.engine?.public?.currentTrick?.nextSeatIndex;
  const isViewerTurn = nextSeatIndex !== undefined && table?.viewer?.seatIndex === nextSeatIndex;

  if (commandType === "tractor.makeTrump" && table?.phase === "burying_bottom") {
    const discardAction = action(table, "tractor.discardBottom");
    return discardAction.enabled ? "亮主成功，请选择 8 张牌埋牌" : "亮主成功，等待埋牌";
  }
  if (commandType === "tractor.discardBottom" && table?.phase === "playing") {
    return isViewerTurn ? "埋牌完成，请选择要出的牌" : `埋牌完成，等待${seatLabel(nextSeatIndex)}出牌`;
  }
  if (commandType === "tractor.playCards" && table?.phase === "playing") {
    return isViewerTurn ? "请继续出牌" : `等待${seatLabel(nextSeatIndex)}出牌`;
  }
  if (table?.phase === "finished") {
    return "本局结束";
  }

  return "牌桌已更新";
}

function toggleCardSelection(index) {
  const position = state.selectedHandIndexes.indexOf(index);
  if (position >= 0) {
    state.selectedHandIndexes.splice(position, 1);
  } else {
    state.selectedHandIndexes.push(index);
  }
  render();
}

function selectedHandCards() {
  const cards = state.table?.engine?.private?.cards || [];
  return state.selectedHandIndexes
    .map((index) => cards[index])
    .filter(Boolean);
}

function cardFaceHtml(card, className) {
  const image = cardImageUrl(card);
  const label = cardLabelText(card);
  if (!image) {
    return `<span class="${className} card-face-fallback">${escapeHtml(label)}</span>`;
  }

  return `<img class="${className} card-face" src="${escapeAttribute(image)}" alt="${escapeAttribute(label)}" loading="lazy" draggable="false" />`;
}

function cardImageUrl(card) {
  const cardId = Number(card?.id);
  if (!Number.isInteger(cardId) || cardId < 0 || cardId > 54 || !state.assetBaseUrl) {
    return "";
  }

  const uiCardNumber = serverToUiCardNumber(cardId);
  return `${state.assetBaseUrl}/tractor/${encodeURIComponent(state.cardStyle)}/tile${String(uiCardNumber).padStart(3, "0")}.png`;
}

function serverToUiCardNumber(cardId) {
  if (cardId >= 0 && cardId < 13) {
    return cardId < 12 ? cardId + 1 : 0;
  }
  if (cardId >= 13 && cardId < 26) {
    return cardId < 25 ? cardId + 14 : 13;
  }
  if (cardId >= 26 && cardId < 39) {
    return cardId < 38 ? cardId - 12 : 26;
  }
  if (cardId >= 39 && cardId < 52) {
    return cardId < 51 ? cardId + 1 : 39;
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
  state.selectedHandIndexes = state.selectedHandIndexes.filter((index) => index >= 0 && index < cards.length);
}

function inferTrumpPayload(cards, table) {
  const rank = table?.engine?.public?.rank;
  if (rank === undefined) {
    return null;
  }
  if (cards.length === 1 && cards[0].rank === rank && cards[0].suit !== "joker") {
    return { suit: cards[0].suit, exposure: "single_rank" };
  }
  if (cards.length === 2 && cards[0].id === cards[1].id) {
    if (cards[0].id === 52) {
      return { suit: "joker", exposure: "pair_black_joker" };
    }
    if (cards[0].id === 53) {
      return { suit: "joker", exposure: "pair_red_joker" };
    }
    if (cards[0].rank === rank && cards[0].suit !== "joker") {
      return { suit: cards[0].suit, exposure: "pair_rank" };
    }
  }

  return null;
}

function joinRoom(roomKey) {
  if (roomKey !== state.currentRoomKey) {
    state.chatEvents = [];
    state.emojiTarget = "";
  }
  void sendCommand("room.join", { roomKey })
    .then(() => Promise.all([
      refreshTable(roomKey),
      requestChatHistory(roomKey)
    ]))
    .catch(reportError);
}

async function requestChatHistory(roomKey) {
  if (!roomKey || !state.authenticated) {
    return;
  }

  const response = await sendCommand("chat.history", { roomKey });
  setChatEvents(response.payload.events || []);
  render();
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
  const text = state.chatDraft.trim();
  if (!text || !state.currentRoomKey) {
    return;
  }

  try {
    await sendCommand("chat.send", {
      roomKey: state.currentRoomKey,
      payload: { text }
    });
    state.chatDraft = "";
    els.chatInput.value = "";
  } catch (error) {
    reportError(error);
  }
}

function renderChatPanel() {
  els.chatPanel.hidden = !state.currentRoomKey;
  if (!state.currentRoomKey) {
    els.chatMessages.replaceChildren();
    els.chatInput.value = "";
    els.chatInput.disabled = true;
    return;
  }

  els.chatInput.disabled = !state.authenticated;
  els.chatInput.value = state.chatDraft;
  els.chatMessages.replaceChildren(...state.chatEvents.map(chatEventNode));
  els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
}

function setChatEvents(events) {
  state.chatEvents = [];
  events.forEach(appendChatEvent);
}

function appendChatEvent(event) {
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
}

function chatEventNode(event) {
  const item = document.createElement("div");
  item.className = `chat-message chat-${event.kind}`;

  const meta = document.createElement("span");
  meta.className = "chat-meta";
  meta.textContent = `${event.user?.displayName || event.user?.username || "玩家"} ${timeLabel(event.createdAt)}`;

  const body = document.createElement("span");
  body.className = "chat-body";
  if (event.kind === "emoji") {
    const target = emojiTargetLabel(event);
    body.textContent = target ? `向${target}发送了${emojiLabel(event.emojiId)}` : `发送了${emojiLabel(event.emojiId)}`;
  } else {
    body.textContent = event.text || "";
  }

  item.append(meta, body);
  return item;
}

function emojiLabel(emojiId) {
  return emojiCatalog.find((item) => item.id === emojiId)?.label || "表情";
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
    els.emojiTarget.replaceChildren();
    els.emojiDock.replaceChildren();
    return;
  }

  renderEmojiTargetOptions();
  els.emojiDock.replaceChildren(...emojiCatalog.map((emoji) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "emoji-button";
    button.title = emoji.label;
    button.disabled = !state.authenticated;
    button.innerHTML = `<img src="${escapeAttribute(emojiUrl(emoji.asset, 0))}" alt="${escapeAttribute(emoji.label)}" loading="lazy" />`;
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

function renderEmojiTargetOptions() {
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
  const emoji = emojiCatalog.find((item) => item.id === payload.emojiId);
  const type = String(payload.emojiType || payload.type || emoji?.asset || "goodjob");
  const index = Math.max(0, Math.min(3, Number(payload.emojiIndex ?? payload.index ?? 0)));
  const target = emojiAnimationTarget(payload);
  const stage = document.createElement("div");
  stage.className = target === els.table ? "emoji-pop" : "emoji-pop emoji-pop-seat";
  stage.innerHTML = `<img src="${escapeAttribute(emojiUrl(type, index))}" alt="${escapeAttribute(emoji?.label || "表情")}" />`;
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
  } else if (commandType === "tractor.playCards") {
    playSound("effect/tie.mp3");
  } else if (nextPhase === "finished" && previousPhase !== "finished") {
    playSound("effect/win.mp3");
  }
}

function playSound(path) {
  if (!state.soundEnabled || !state.audioBaseUrl || !path) {
    return;
  }

  const audio = new Audio(`${state.audioBaseUrl}/${path}`);
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
  return suits[trump.suit] || "无主";
}

function setStatus(message) {
  els.log.textContent = message;
  render();
}

function reportError(error) {
  setStatus(error.message || "操作失败");
}

function errorMessage(message) {
  const payload = message?.payload || {};
  return serverErrorText(payload.code, payload.message);
}

function statusText() {
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

function seatLabel(seatIndex) {
  return `${Number(seatIndex) + 1}号座位`;
}

function skinDisplayName(skin) {
  return skinLabels[skin.skinId] || skin.displayName || skin.skinId;
}

function serverErrorText(code, message) {
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
    not_room_owner: "只有房主可以开始",
    not_tractor_room: "该房间不是拖拉机房间",
    room_not_found: "房间不存在",
    room_closed: "房间已关闭",
    room_not_waiting: "房间不在等待状态",
    room_active: "房间正在游戏中",
    room_disabled: "房间已停用",
    room_has_active_game: "房间有进行中的游戏，无法重置",
    room_required: "请选择房间",
    seat_taken: "座位已被占用",
    seat_not_found: "座位不存在",
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
    trump_not_open: "现在还不能亮主",
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
    invalid_trump_exposure: "亮主组合无效",
    message_too_large: "消息太大",
    rate_limited: "操作太频繁，请稍后再试",
    refresh_unavailable: "用户状态暂时无法刷新",
    session_not_active: "游戏会话未激活",
    session_not_found: "游戏会话不存在",
    tractor_bottom_cards_not_held: "埋牌必须从您的手牌中选择",
    tractor_card_count_mismatch: "出牌张数必须与首家一致",
    tractor_cards_not_held: "出牌必须从您的手牌中选择",
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
    tractor_trump_cards_not_held: "亮主必须使用您的手牌",
    tractor_trump_closed: "底牌发出后不能亮主",
    tractor_trump_too_weak: "亮主级别不够",
    unsupported_message: "不支持的消息类型",
    chat_rate_limited: "聊天太频繁，请稍后再试",
    emoji_rate_limited: "表情发送太频繁，请稍后再试"
  };
  return labels[code] || (/[\u4e00-\u9fff]/.test(message || "") ? message : "服务器出错");
}

function defaultWsUrl() {
  const scheme = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${scheme}//${window.location.host}/card-games/ws`;
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
