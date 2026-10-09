(function () {
  // ---- CONFIGURACIÓN ----
  const ERP_BASE = new URL(document.currentScript.src).origin; // ej: https://system.erp.compipro.net
  const API_SETTINGS = ERP_BASE + "/api/marketing/chatbot/settings";
  const API_LEADS = ERP_BASE + "/api/marketing/chatbot/leads";

  // ---- ESTILOS CSS ----
  const style = document.createElement("style");
  style.innerHTML = `
    .cp-chat-btn {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 999999;
      background: #111;
      color: #fff;
      border-radius: 999px;
      padding: 12px 14px;
      font-family: system-ui;
      cursor: pointer;
      box-shadow: 0 10px 30px rgba(0,0,0,.25);
      border: none;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .cp-chat-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 35px rgba(0,0,0,.3);
    }
    .cp-chat-box {
      position: fixed;
      right: 18px;
      bottom: 70px;
      width: 340px;
      max-width: calc(100vw - 36px);
      height: 480px;
      max-height: calc(100vh - 110px);
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      z-index: 999999;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
      font-family: system-ui;
      display: none;
      flex-direction: column;
    }
    .cp-chat-head {
      background: #111;
      color: #fff;
      padding: 12px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }
    .cp-chat-head strong {
      display: block;
      font-size: 14px;
      font-weight: 600;
    }
    .cp-muted {
      font-size: 12px;
      color: #cfcfcf;
      margin: 0;
      margin-top: 2px;
    }
    .cp-chat-body {
      padding: 12px;
      overflow-y: auto;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .cp-chat-foot {
      padding: 10px;
      border-top: 1px solid #eee;
      height: auto;
      display: flex;
      gap: 8px;
      align-items: center;
      flex-shrink: 0;
    }
    .cp-chat-input {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 10px;
      padding: 10px;
      font-family: system-ui;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
    }
    .cp-chat-input:focus {
      border-color: #111;
    }
    .cp-chat-send {
      background: #111;
      color: #fff;
      border: 0;
      border-radius: 10px;
      padding: 10px 12px;
      cursor: pointer;
      font-family: system-ui;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.2s;
    }
    .cp-chat-send:hover {
      background: #333;
    }
    .cp-bubble {
      margin: 8px 0;
      display: flex;
      animation: fadeIn 0.3s ease;
    }
    .cp-bubble.me {
      justify-content: flex-end;
    }
    .cp-bubble .msg {
      max-width: 85%;
      padding: 10px 12px;
      border-radius: 14px;
      font-size: 14px;
      line-height: 1.4;
      word-wrap: break-word;
    }
    .cp-bubble.bot .msg {
      background: #f3f4f6;
      color: #111;
      border-top-left-radius: 6px;
    }
    .cp-bubble.me .msg {
      background: #111;
      color: #fff;
      border-top-right-radius: 6px;
    }
    .cp-x {
      cursor: pointer;
      opacity: 0.9;
      font-size: 18px;
      line-height: 1;
      transition: opacity 0.2s;
    }
    .cp-x:hover {
      opacity: 1;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 480px) {
      .cp-chat-box {
        width: calc(100vw - 20px);
        height: calc(100vh - 80px);
        right: 10px;
        bottom: 60px;
      }
    }
  `;
  document.head.appendChild(style);

  // ---- ELEMENTOS HTML ----
  const btn = document.createElement("button");
  btn.className = "cp-chat-btn";
  btn.textContent = "💬 Chat";
  btn.type = "button";
  btn.setAttribute("aria-label", "Abrir chat");

  const box = document.createElement("div");
  box.className = "cp-chat-box";
  box.innerHTML = `
    <div class="cp-chat-head">
      <div>
        <strong>COMPIPRO</strong>
        <div class="cp-muted">Asistente</div>
      </div>
      <div class="cp-x" title="Cerrar" role="button" tabindex="0">✕</div>
    </div>
    <div class="cp-chat-body"></div>
    <div class="cp-chat-foot">
      <input class="cp-chat-input" type="text" placeholder="Escribe aquí..." />
      <button class="cp-chat-send" type="button">Enviar</button>
    </div>
  `;

  document.body.appendChild(btn);
  document.body.appendChild(box);

  const body = box.querySelector(".cp-chat-body");
  const input = box.querySelector(".cp-chat-input");
  const send = box.querySelector(".cp-chat-send");
  const close = box.querySelector(".cp-x");

  // ---- FUNCIONES AUXILIARES ----
  function addMsg(text, who) {
    const row = document.createElement("div");
    row.className = "cp-bubble " + who;
    row.innerHTML = `<div class="msg"></div>`;
    row.querySelector(".msg").textContent = text;
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
  }

  // ---- MÁQUINA DE ESTADOS ----
  const state = {
    step: 0,
    lead: { name: "", email: "", phone: "", company_name: "", meta: {} },
    greeting: "Hola 👋 Soy el asistente de COMPIPRO. Te haré unas preguntas rápidas para ayudarte.",
    farewell: "¡Gracias! Muy pronto un ejecutivo se pondrá en contacto con Ud.",
    enabled: 1,
  };

  async function loadSettings() {
    try {
      const res = await fetch(API_SETTINGS);
      if (!res.ok) throw new Error("Error loading settings");
      const json = await res.json();
      state.greeting = json.greeting || state.greeting;
      state.farewell = json.farewell || state.farewell;
      state.enabled = (json.enabled ?? 1);
    } catch (e) {
      console.warn("ChatBot: Error loading settings", e);
    }
  }

  function botAskNext() {
    if (state.step === 0) {
      addMsg(state.greeting, "bot");
      addMsg("¿Cuál es tu nombre?", "bot");
      return;
    }
    if (state.step === 1) {
      addMsg("Perfecto. ¿Me dejas tu teléfono o correo?", "bot");
      return;
    }
    if (state.step === 2) {
      addMsg("¿Nombre de tu empresa?", "bot");
      return;
    }
    if (state.step === 3) {
      addMsg(state.farewell, "bot");
      return;
    }
  }

  function parseContact(text) {
    const t = (text || "").trim();
    
    // Detectar email
    const emailMatch = t.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
    if (emailMatch) return { email: emailMatch[0], phone: "" };

    // Detectar teléfono (al menos 6 dígitos)
    const digits = t.replace(/[^0-9+]/g, "");
    if (digits.length >= 6) return { email: "", phone: digits };

    return null;
  }

  async function submitLead() {
    const payload = {
      name: state.lead.name,
      email: state.lead.email || null,
      phone: state.lead.phone || null,
      company_name: state.lead.company_name,
      meta: {
        page_url: window.location.href,
        user_agent: navigator.userAgent,
        created_from: "website_embed",
      }
    };

    try {
      const res = await fetch(API_LEADS, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        let msg = "No pude guardar tu solicitud. Intenta nuevamente.";
        try {
          const j = await res.json();
          if (j && j.message) msg = j.message;
        } catch (e) {}
        addMsg(msg, "bot");
        return;
      }

      // Éxito
      const json = await res.json();
      if (json.ok) {
        // El mensaje de despedida se mostrará en botAskNext()
      }
    } catch (e) {
      console.error("ChatBot: Error submitting lead", e);
      addMsg("Error de conexión. Intenta nuevamente.", "bot");
    }
  }

  async function onSend() {
    const text = input.value.trim();
    if (!text) return;
    
    input.value = "";
    addMsg(text, "me");

    if (state.step === 0) {
      state.lead.name = text;
      state.step = 1;
      botAskNext();
      return;
    }

    if (state.step === 1) {
      const c = parseContact(text);
      if (!c) {
        addMsg("Por favor ingresa un teléfono o un correo válido.", "bot");
        return;
      }
      state.lead.email = c.email;
      state.lead.phone = c.phone;
      state.step = 2;
      botAskNext();
      return;
    }

    if (state.step === 2) {
      state.lead.company_name = text;
      state.step = 3;
      await submitLead();
      botAskNext();
      return;
    }

    // step 3: ya cerró
    addMsg("Si deseas, puedes escribirnos por el formulario web 😊", "bot");
  }

  // ---- EVENT LISTENERS ----
  btn.addEventListener("click", async () => {
    const isOpen = box.style.display === "block";
    box.style.display = isOpen ? "none" : "flex";
    
    if (box.style.display === "flex") {
      body.innerHTML = "";
      await loadSettings();
      
      if (!state.enabled) {
        addMsg("El chat está temporalmente deshabilitado.", "bot");
        return;
      }
      
      state.step = 0;
      state.lead = { name: "", email: "", phone: "", company_name: "", meta: {} };
      botAskNext();
      input.focus();
    }
  });

  close.addEventListener("click", () => {
    box.style.display = "none";
  });

  send.addEventListener("click", onSend);
  
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      onSend();
    }
  });
})();
