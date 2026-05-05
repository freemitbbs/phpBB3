const state = {
  bootstrap: {},
  token: "",
  user: null,
  ws: null,
  connected: false,
  authenticated: false,
  requestSeq: 0,
  pending: new Map(),
  rooms: [],
  table: null,
  currentRoomKey: "",
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
  { id: "smile", asset: "goodjob", label: "Smile" },
  { id: "laugh", asset: "lol", label: "Laugh" },
  { id: "thumbs_up", asset: "youareright", label: "Thumbs up" },
  { id: "clap", asset: "goodjob", label: "Clap" },
  { id: "fire", asset: "fireworks", label: "Fire" },
  { id: "thinking", asset: "hurryup", label: "Thinking" },
  { id: "surprise", asset: "sorry", label: "Surprise" },
  { id: "sad", asset: "sorry", label: "Sad" },
  { id: "angry", asset: "hurryup", label: "Angry" },
  { id: "good_luck", asset: "noproblem", label: "Good luck" }
];
const emojiTypes = ["goodjob", "hurryup", "sorry", "lol", "noproblem", "fireworks", "youareright"];

const els = {
  status: document.querySelector("#connection-status"),
  user: document.querySelector("#user-label"),
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

void init();

async function init() {
  state.bootstrap = await loadBootstrap();
  state.token = queryParam("token") || state.bootstrap.token || "";
  state.assetBaseUrl = normalizeBaseUrl(state.bootstrap.assetBaseUrl || defaultAssetBaseUrl());
  state.audioBaseUrl = normalizeBaseUrl(state.bootstrap.audioBaseUrl || defaultAudioBaseUrl());
  state.cardStyle = state.bootstrap.cardStyle || "cardsclassic";
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
  disconnect();
  setStatus("Connecting");

  try {
    if (!state.token) {
      state.token = await fetchToken();
    }
    const wsUrl = state.bootstrap.wsUrl || defaultWsUrl();
    const ws = new WebSocket(wsUrl);
    state.ws = ws;

    ws.addEventListener("open", () => {
      state.connected = true;
      render();
      void authenticate();
    });
    ws.addEventListener("message", (event) => {
      handleMessage(event.data);
    });
    ws.addEventListener("close", () => {
      state.connected = false;
      state.authenticated = false;
      state.pending.forEach((pending) => pending.reject(new Error("WebSocket closed")));
      state.pending.clear();
      render();
    });
    ws.addEventListener("error", () => {
      setStatus("Connection error");
    });
  } catch (error) {
    setStatus(error.message || "Connection failed");
  }
}

function disconnect() {
  if (state.ws && state.ws.readyState < WebSocket.CLOSING) {
    state.ws.close();
  }
  state.ws = null;
  state.connected = false;
  state.authenticated = false;
  state.pending.clear();
}

async function fetchToken() {
  const tokenUrl = state.bootstrap.tokenUrl;
  const tokenHash = state.bootstrap.tokenHash;
  if (!tokenUrl || !tokenHash) {
    throw new Error("Open this client from phpBB /card-games, or pass ?token= and ?ws= for local testing.");
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
    throw new Error(payload.error || "Token request failed");
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
    await requestRooms();
    setStatus("Connected");
  } catch (error) {
    setStatus(error.message || "Authentication failed");
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

async function refreshTable(roomKey) {
  const response = await sendCommand("tractor.table", { roomKey });
  state.table = response.payload.table;
  state.currentRoomKey = roomKey;
  syncSelectedHandIndexes();
  render();
}

function sendCommand(type, options) {
  if (!state.ws || state.ws.readyState !== WebSocket.OPEN) {
    return Promise.reject(new Error("WebSocket is not open"));
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
    setStatus("Received invalid JSON");
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
      setStatus("Server ready");
      break;
    case "auth.accepted":
      state.user = message.payload.user || state.user;
      playSound("effect/enter_hall_click.mp3");
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
  els.connect.textContent = state.connected ? "Reconnect" : "Connect";
  els.sound.textContent = state.soundEnabled ? "Sound on" : "Sound off";
  els.sound.setAttribute("aria-pressed", state.soundEnabled ? "true" : "false");
  renderRooms();
  renderTable();
  renderEmojiDock();
  renderChatPanel();
}

function renderRooms() {
  if (!state.rooms.length) {
    els.rooms.innerHTML = '<div class="empty-state">No rooms loaded.</div>';
    return;
  }

  els.rooms.replaceChildren(...state.rooms.map((room) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "room-button";
    button.setAttribute("aria-current", room.roomKey === state.currentRoomKey ? "true" : "false");
    button.disabled = !room.enabled;
    button.innerHTML = `
      <span><strong>${escapeHtml(room.displayName)}</strong><span>${room.memberCount || 0} online</span></span>
      <span class="room-status">${escapeHtml(room.status)}${room.enabled ? "" : " closed"}</span>
    `;
    button.addEventListener("click", () => joinRoom(room.roomKey));
    return button;
  }));
}

function renderTable() {
  const table = state.table;
  els.leave.hidden = !state.currentRoomKey;
  if (!table) {
    els.title.textContent = "Lobby";
    els.table.innerHTML = '<div class="empty-state">Choose a room to enter the table.</div>';
    return;
  }

  els.title.textContent = table.room.displayName;
  const seatNodes = table.room.seats.map((seat) => seatHtml(table, seat)).join("");
  const observers = table.room.observers.map((observer) => {
    const watched = observer.watchedSeatIndex === undefined ? "" : ` watching seat ${observer.watchedSeatIndex + 1}`;
    return `<span class="observer-pill">${escapeHtml(observer.user.displayName)}${watched}</span>`;
  }).join("");
  const score = table.engine?.public?.score ?? 0;
  const currentTrick = table.engine?.public?.currentTrick;
  const trickLabel = currentTrick
    ? `Trick ${currentTrick.trickNumber}, seat ${(currentTrick.nextSeatIndex ?? currentTrick.winnerSeatIndex) + 1}`
    : table.engineReady ? `Score ${score}` : "Waiting";
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
            <span>Rank ${escapeHtml(rankLabel(table.engine?.public?.rank))}</span>
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
  els.leave.title = leaveAction.reason || "";
}

function seatHtml(table, seat) {
  const user = seat.user;
  const occupied = Boolean(user);
  const isViewerSeat = table.viewer.seatIndex === seat.seatIndex;
  const isOwner = table.room.owner?.userId && user?.userId === table.room.owner.userId;
  const meta = occupied
    ? `${seat.connected ? "online" : "offline"} · ${seat.ready ? "ready" : "not ready"}${isOwner ? " · owner" : ""}`
    : "open";
  const actions = seatActionsHtml(table, seat, isViewerSeat);
  const avatar = user?.avatarUrl
    ? `<span class="seat-avatar-stack"><img class="seat-skin" src="${escapeAttribute(skinUrlForSeat(user, seat.seatIndex))}" alt="" loading="lazy" /><img class="seat-profile-avatar" src="${escapeAttribute(user.avatarUrl)}" alt="" loading="lazy" /></span>`
    : `<img class="seat-skin" src="${escapeAttribute(skinUrlForSeat(user, seat.seatIndex))}" alt="" loading="lazy" />`;

  return `
    <div class="seat seat-${seat.seatIndex} ${seat.connected ? "" : "seat-offline"}" data-seat-index="${seat.seatIndex}">
      ${avatar}
      <div class="seat-name">${occupied ? escapeHtml(user.displayName) : `Seat ${seat.seatIndex + 1}`}</div>
      <div class="seat-meta">${escapeHtml(meta)}</div>
      <div class="seat-actions">${actions}</div>
    </div>
  `;
}

function seatActionsHtml(table, seat, isViewerSeat) {
  if (!seat.user && action(table, "seat.claim").enabled) {
    return `<button data-action="seat.claim" data-seat="${seat.seatIndex}" type="button">Sit</button>`;
  }
  if (isViewerSeat) {
    const readyAction = action(table, "player.ready");
    const releaseAction = action(table, "seat.release");
    return `
      <button data-action="player.ready" data-seat="${seat.seatIndex}" type="button" ${readyAction.enabled ? "" : "disabled"}>
        ${seat.ready ? "Unready" : "Ready"}
      </button>
      <button data-action="seat.release" data-seat="${seat.seatIndex}" type="button" ${releaseAction.enabled ? "" : "disabled"}>Stand</button>
    `;
  }
  if (seat.user && action(table, "observer.watch").enabled && table.viewer.role === "observer") {
    return `<button data-action="observer.watch" data-seat="${seat.seatIndex}" type="button">Watch</button>`;
  }
  return "";
}

function skinUrlForSeat(user, seatIndex) {
  const skinName = user?.skinInUse || user?.skin || defaultSkins[Math.abs(Number(user?.userId ?? seatIndex)) % defaultSkins.length] || "skin_questionmark.webp";
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
    parts.push('<button data-action="tractor.start" type="button">Start</button>');
  }
  if (makeTrumpAction.enabled) {
    parts.push(`<button data-action="tractor.makeTrump" type="button" ${inferTrumpPayload(selectedCards, table) ? "" : "disabled"} title="Select one rank card or a valid pair">Trump</button>`);
  }
  if (discardAction.enabled) {
    parts.push(`<button data-action="tractor.discardBottom" type="button" ${selectedCards.length === discardAction.count ? "" : "disabled"} title="Select ${discardAction.count} cards">Bury</button>`);
  }
  if (playAction.enabled) {
    parts.push(`<button data-action="tractor.playCards" type="button" ${selectedCards.length > 0 ? "" : "disabled"}>Play</button>`);
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
      <span>Seat ${play.seatIndex + 1}</span>
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
      <span class="hand-card-label">${escapeHtml(card.label)}</span>
      ${card.points ? `<small>${card.points}</small>` : ""}
    </button>
  `).join("");

  return `<div class="hand-panel" style="--hand-count: ${Math.max(cards.length, 1)}">${nodes}</div>`;
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
    void sendCommand("tractor.start", { roomKey }).catch(reportError);
  } else if (type === "tractor.makeTrump") {
    const payload = inferTrumpPayload(selectedHandCards(), state.table);
    if (payload) {
      void sendEngineCommand("tractor.makeTrump", roomKey, payload);
    }
  } else if (type === "tractor.discardBottom") {
    void sendEngineCommand("tractor.discardBottom", roomKey, { cards: selectedHandCards().map((card) => card.id) });
  } else if (type === "tractor.playCards") {
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
    }
  } catch (error) {
    reportError(error);
  }
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
  if (!image) {
    return `<span class="${className} card-face-fallback">${escapeHtml(card?.label || "")}</span>`;
  }

  return `<img class="${className} card-face" src="${escapeAttribute(image)}" alt="${escapeAttribute(card.label || "")}" loading="lazy" draggable="false" />`;
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
  meta.textContent = `${event.user?.displayName || event.user?.username || "Player"} ${timeLabel(event.createdAt)}`;

  const body = document.createElement("span");
  body.className = "chat-body";
  if (event.kind === "emoji") {
    const target = emojiTargetLabel(event);
    body.textContent = target ? `sent ${emojiLabel(event.emojiId)} to ${target}` : `sent ${emojiLabel(event.emojiId)}`;
  } else {
    body.textContent = event.text || "";
  }

  item.append(meta, body);
  return item;
}

function emojiLabel(emojiId) {
  return emojiCatalog.find((item) => item.id === emojiId)?.label || String(emojiId || "emoji");
}

function emojiTargetLabel(event) {
  if (Number.isInteger(event.targetSeatIndex)) {
    const seat = state.table?.room?.seats?.find((candidate) => candidate.seatIndex === event.targetSeatIndex);
    return seat?.user?.displayName || `Seat ${event.targetSeatIndex + 1}`;
  }

  if (Number.isInteger(event.targetUserId)) {
    const seat = state.table?.room?.seats?.find((candidate) => candidate.user?.userId === event.targetUserId);
    const observer = state.table?.room?.observers?.find((candidate) => candidate.user?.userId === event.targetUserId);
    return seat?.user?.displayName || observer?.user?.displayName || `User ${event.targetUserId}`;
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
  const options = [{ value: "", label: "Table" }];
  for (const seat of state.table?.room?.seats || []) {
    if (!seat.user) {
      continue;
    }
    options.push({
      value: `seat:${seat.seatIndex}`,
      label: `Seat ${seat.seatIndex + 1}: ${seat.user.displayName || seat.user.username}`
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
  stage.innerHTML = `<img src="${escapeAttribute(emojiUrl(type, index))}" alt="${escapeAttribute(type)}" />`;
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
  return String(phase || "waiting_for_players").replaceAll("_", " ");
}

function rankLabel(rank) {
  if (rank === undefined || rank === null) {
    return "-";
  }

  const labels = ["A", "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K"];
  return labels[rank] || String(rank);
}

function trumpLabel(trump) {
  if (!trump || trump.suit === "none") {
    return "No trump";
  }

  const suits = {
    heart: "Heart trump",
    spade: "Spade trump",
    diamond: "Diamond trump",
    club: "Club trump",
    joker: "Joker trump"
  };
  return suits[trump.suit] || "No trump";
}

function setStatus(message) {
  els.log.textContent = message;
  render();
}

function reportError(error) {
  setStatus(error.message || "Command failed");
}

function errorMessage(message) {
  const payload = message?.payload || {};
  return payload.message || payload.code || "Server error";
}

function statusText() {
  if (state.authenticated) {
    return "Authenticated";
  }
  if (state.connected) {
    return "Connected";
  }
  return "Disconnected";
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
