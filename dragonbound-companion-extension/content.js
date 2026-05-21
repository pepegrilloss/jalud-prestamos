(function () {
  "use strict";

  if (window.__dbCompanionLoaded) {
    return;
  }

  window.__dbCompanionLoaded = true;

  const STORAGE_KEY = "dbCompanionState";
  const defaultState = {
    notes: "",
    elapsedMs: 0,
    runningSince: null,
    turn: 1,
    panelVisible: true
  };

  const root = document.createElement("div");
  root.id = "db-companion-root";

  const shadow = root.attachShadow({ mode: "open" });

  shadow.innerHTML = `
    <style>
      :host {
        color-scheme: light;
        font-family: Arial, Helvetica, sans-serif;
      }

      .panel {
        width: min(292px, calc(100vw - 32px));
        color: #172033;
        background: #f7f9fc;
        border: 1px solid rgba(14, 30, 55, 0.2);
        border-radius: 8px;
        box-shadow: 0 10px 28px rgba(8, 16, 28, 0.22);
        overflow: hidden;
      }

      .panel[hidden],
      .toggle[hidden] {
        display: none;
      }

      .header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 40px;
        padding: 8px 10px 8px 12px;
        background: #172033;
        color: #ffffff;
      }

      .title {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
      }

      .title strong {
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
      }

      .title span {
        color: rgba(255, 255, 255, 0.72);
        font-size: 11px;
        line-height: 1.2;
      }

      .body {
        display: grid;
        gap: 10px;
        padding: 10px;
      }

      .timer {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 8px;
      }

      .time {
        min-width: 0;
        padding: 8px 10px;
        border: 1px solid rgba(14, 30, 55, 0.14);
        border-radius: 6px;
        background: #ffffff;
        font-family: Consolas, "Courier New", monospace;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0;
        text-align: center;
      }

      .timer-actions,
      .row {
        display: flex;
        align-items: center;
        gap: 6px;
      }

      .timer-actions {
        justify-content: flex-end;
      }

      button {
        min-width: 34px;
        min-height: 32px;
        border: 1px solid rgba(14, 30, 55, 0.18);
        border-radius: 6px;
        background: #ffffff;
        color: #172033;
        cursor: pointer;
        font: 700 13px Arial, Helvetica, sans-serif;
        letter-spacing: 0;
      }

      button:hover {
        background: #edf2f8;
      }

      button:active {
        transform: translateY(1px);
      }

      .primary {
        min-width: 68px;
        background: #226b62;
        border-color: #226b62;
        color: #ffffff;
      }

      .primary:hover {
        background: #1c5c54;
      }

      .icon {
        width: 32px;
        min-width: 32px;
        padding: 0;
      }

      .toggle {
        width: 46px;
        min-width: 46px;
        height: 38px;
        min-height: 38px;
        border-color: rgba(14, 30, 55, 0.22);
        box-shadow: 0 8px 18px rgba(8, 16, 28, 0.2);
      }

      label {
        display: grid;
        gap: 5px;
        color: #33415c;
        font-size: 12px;
        font-weight: 700;
      }

      textarea {
        width: 100%;
        min-height: 88px;
        resize: vertical;
        border: 1px solid rgba(14, 30, 55, 0.16);
        border-radius: 6px;
        padding: 8px;
        background: #ffffff;
        color: #172033;
        font: 13px Arial, Helvetica, sans-serif;
        letter-spacing: 0;
        outline: none;
      }

      textarea:focus {
        border-color: #226b62;
        box-shadow: 0 0 0 2px rgba(34, 107, 98, 0.16);
      }

      .turn {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 8px;
      }

      .turn-value {
        min-width: 0;
        padding: 8px 10px;
        border: 1px solid rgba(14, 30, 55, 0.14);
        border-radius: 6px;
        background: #ffffff;
        color: #172033;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
      }

      .footer {
        display: flex;
        justify-content: space-between;
        gap: 8px;
      }

      .footer button {
        flex: 1;
        min-width: 0;
      }
    </style>

    <button class="toggle" id="db-toggle" type="button" title="Abrir panel">DB</button>

    <section class="panel" id="db-panel" aria-label="DB Companion">
      <div class="header">
        <div class="title">
          <strong>DB Companion</strong>
          <span>Notas y cronometro</span>
        </div>
        <button class="icon" id="db-minimize" type="button" title="Minimizar">_</button>
      </div>

      <div class="body">
        <div class="timer">
          <output class="time" id="db-time">00:00</output>
          <div class="timer-actions">
            <button class="primary" id="db-start" type="button">Iniciar</button>
            <button class="icon" id="db-reset-time" type="button" title="Reiniciar cronometro">R</button>
          </div>
        </div>

        <div class="turn">
          <button class="icon" id="db-turn-down" type="button" title="Turno anterior">-</button>
          <output class="turn-value" id="db-turn">Turno 1</output>
          <button class="icon" id="db-turn-up" type="button" title="Turno siguiente">+</button>
        </div>

        <label>
          Notas
          <textarea id="db-notes" spellcheck="false" placeholder="Anota viento, sala o recordatorios."></textarea>
        </label>

        <div class="footer">
          <button id="db-clear-notes" type="button">Limpiar notas</button>
          <button id="db-reset-all" type="button">Reiniciar todo</button>
        </div>
      </div>
    </section>
  `;

  const storage = {
    get() {
      return new Promise((resolve) => {
        if (
          typeof chrome === "undefined" ||
          !chrome.storage ||
          !chrome.storage.local
        ) {
          resolve({ ...defaultState });
          return;
        }

        chrome.storage.local.get(STORAGE_KEY, (result) => {
          resolve({ ...defaultState, ...(result[STORAGE_KEY] || {}) });
        });
      });
    },
    set(state) {
      if (
        typeof chrome === "undefined" ||
        !chrome.storage ||
        !chrome.storage.local
      ) {
        return;
      }

      chrome.storage.local.set({ [STORAGE_KEY]: state });
    }
  };

  const elements = {
    panel: shadow.getElementById("db-panel"),
    toggle: shadow.getElementById("db-toggle"),
    minimize: shadow.getElementById("db-minimize"),
    time: shadow.getElementById("db-time"),
    start: shadow.getElementById("db-start"),
    resetTime: shadow.getElementById("db-reset-time"),
    turn: shadow.getElementById("db-turn"),
    turnDown: shadow.getElementById("db-turn-down"),
    turnUp: shadow.getElementById("db-turn-up"),
    notes: shadow.getElementById("db-notes"),
    clearNotes: shadow.getElementById("db-clear-notes"),
    resetAll: shadow.getElementById("db-reset-all")
  };

  let state = { ...defaultState };
  let saveTimer = null;

  function scheduleSave() {
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => storage.set(state), 160);
  }

  function getElapsedMs() {
    if (!state.runningSince) {
      return state.elapsedMs;
    }

    return state.elapsedMs + Date.now() - state.runningSince;
  }

  function formatTime(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
      return [hours, minutes, seconds]
        .map((value) => String(value).padStart(2, "0"))
        .join(":");
    }

    return [minutes, seconds]
      .map((value) => String(value).padStart(2, "0"))
      .join(":");
  }

  function render() {
    elements.panel.hidden = !state.panelVisible;
    elements.toggle.hidden = state.panelVisible;
    elements.time.textContent = formatTime(getElapsedMs());
    elements.start.textContent = state.runningSince ? "Pausar" : "Iniciar";
    elements.turn.textContent = `Turno ${state.turn}`;

    if (elements.notes.value !== state.notes) {
      elements.notes.value = state.notes;
    }
  }

  function setPanelVisible(panelVisible) {
    state.panelVisible = panelVisible;
    render();
    scheduleSave();
  }

  function toggleTimer() {
    if (state.runningSince) {
      state.elapsedMs = getElapsedMs();
      state.runningSince = null;
    } else {
      state.runningSince = Date.now();
    }

    render();
    scheduleSave();
  }

  function resetTimer() {
    state.elapsedMs = 0;
    state.runningSince = null;
    render();
    scheduleSave();
  }

  function setTurn(nextTurn) {
    state.turn = Math.max(1, nextTurn);
    render();
    scheduleSave();
  }

  elements.toggle.addEventListener("click", () => setPanelVisible(true));
  elements.minimize.addEventListener("click", () => setPanelVisible(false));
  elements.start.addEventListener("click", toggleTimer);
  elements.resetTime.addEventListener("click", resetTimer);
  elements.turnDown.addEventListener("click", () => setTurn(state.turn - 1));
  elements.turnUp.addEventListener("click", () => setTurn(state.turn + 1));

  elements.notes.addEventListener("input", () => {
    state.notes = elements.notes.value;
    scheduleSave();
  });

  elements.clearNotes.addEventListener("click", () => {
    state.notes = "";
    render();
    scheduleSave();
  });

  elements.resetAll.addEventListener("click", () => {
    state = { ...defaultState };
    render();
    scheduleSave();
  });

  shadow.addEventListener("keydown", (event) => {
    if (event.target === elements.notes) {
      event.stopPropagation();
    }
  });

  document.documentElement.appendChild(root);

  storage.get().then((savedState) => {
    state = savedState;
    render();
    window.setInterval(render, 250);
  });
})();
