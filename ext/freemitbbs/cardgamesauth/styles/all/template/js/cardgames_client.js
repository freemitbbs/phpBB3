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
  selectedHandIndexes: []
};

const els = {
  status: document.querySelector("#connection-status"),
  user: document.querySelector("#user-label"),
  connect: document.querySelector("#connect-button"),
  refreshRooms: document.querySelector("#refresh-rooms-button"),
  rooms: document.querySelector("#rooms-list"),
  title: document.querySelector("#table-title"),
  leave: document.querySelector("#leave-room-button"),
  table: document.querySelector("#table-view"),
  log: document.querySelector("#event-log")
};

els.connect.addEventListener("click", () => {
  void connect();
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
        render();
        return requestRooms();
      })
      .catch(reportError);
  }
});

void init();

async function init() {
  state.bootstrap = await loadBootstrap();
  state.token = queryParam("token") || state.bootstrap.token || "";
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
    state.pending.set(requestId, { resolve, reject });
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
      pending.reject(new Error(message.payload.message || message.payload.code));
      return;
    }
    pending.resolve(message);
    return;
  }

  switch (message.type) {
    case "system.hello":
      setStatus("Server ready");
      break;
    case "lobby.updated":
      state.rooms = message.payload.rooms;
      render();
      break;
    case "room.left":
      state.currentRoomKey = "";
      state.table = null;
      void requestRooms();
      render();
      break;
    case "room.recovered":
      state.currentRoomKey = message.payload.room.roomKey;
      void refreshTable(state.currentRoomKey);
      break;
    case "room.updated":
      if (message.payload.room.roomKey === state.currentRoomKey) {
        void refreshTable(state.currentRoomKey);
      }
      break;
    case "tractor.table.updated":
      state.table = message.payload.table;
      state.currentRoomKey = state.table.room.roomKey;
      syncSelectedHandIndexes();
      render();
      break;
    case "error":
      setStatus(message.payload.message || message.payload.code);
      break;
    default:
      break;
  }
}

function render() {
  els.status.textContent = statusText();
  els.user.textContent = state.user ? state.user.displayName || state.user.username : "";
  els.connect.textContent = state.connected ? "Reconnect" : "Connect";
  renderRooms();
  renderTable();
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
    button.innerHTML = `
      <span><strong>${escapeHtml(room.displayName)}</strong><span>${room.memberCount} online</span></span>
      <span>${room.status}</span>
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

  els.table.innerHTML = `
    <div class="table-grid">
      ${seatNodes}
      <div class="table-center">
        <div>
          <div class="card-mark">T</div>
          <strong>${table.phase.replaceAll("_", " ")}</strong>
          <p>${escapeHtml(trickLabel)}</p>
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
}

function seatHtml(table, seat) {
  const user = seat.user;
  const occupied = Boolean(user);
  const isViewerSeat = table.viewer.seatIndex === seat.seatIndex;
  const meta = occupied
    ? `${seat.connected ? "online" : "offline"}${seat.ready ? " ready" : " not ready"}`
    : "open";
  const actions = seatActionsHtml(table, seat, isViewerSeat);

  return `
    <div class="seat seat-${seat.seatIndex}">
      <div class="seat-name">${occupied ? escapeHtml(user.displayName) : `Seat ${seat.seatIndex + 1}`}</div>
      <div class="seat-meta">${meta}</div>
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
    parts.push(`<button data-action="tractor.makeTrump" type="button" ${inferTrumpPayload(selectedCards, table) ? "" : "disabled"}>Trump</button>`);
  }
  if (discardAction.enabled) {
    parts.push(`<button data-action="tractor.discardBottom" type="button" ${selectedCards.length === discardAction.count ? "" : "disabled"}>Bury</button>`);
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
      <span>${play.cards.map((card) => escapeHtml(card.label)).join(", ")}</span>
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
      <span>${escapeHtml(card.label)}</span>
      ${card.points ? `<small>${card.points}</small>` : ""}
    </button>
  `).join("");

  return `<div class="hand-panel">${nodes}</div>`;
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
  void sendCommand("room.join", { roomKey })
    .then(() => refreshTable(roomKey))
    .catch(reportError);
}

function setStatus(message) {
  els.log.textContent = message;
  render();
}

function reportError(error) {
  setStatus(error.message || "Command failed");
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

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
