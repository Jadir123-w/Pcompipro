(function () {
  /* ================================================================
   *  COMPIPRO — Widget Chat MKT-01 (Fase 4 + Uploads)
   *
   *  Chat libre conversacional contra el endpoint de IA, con soporte
   *  para que el usuario adjunte logo, referencia o screenshot.
   *
   *  - Session ID (UUID en sessionStorage)
   *  - Typing indicator mientras espera respuesta
   *  - Timeouts de inactividad (reenganche)
   *  - Soporte para links de catálogo como botones
   *  - Soporte para subida de archivos (paperclip)
   *  - Cierre automático por inactividad o por el LLM
   * ================================================================ */

  // ── Detectar dominio del ERP ──
  var currentScript = document.currentScript || (function () {
    var scripts = document.getElementsByTagName("script");
    return scripts[scripts.length - 1];
  })();

  var ERP_BASE = new URL(currentScript.src).origin;
  var API_MESSAGE  = ERP_BASE + "/api/marketing/chatbot/message";
  var API_SETTINGS = ERP_BASE + "/api/marketing/chatbot/settings";
  var API_UPLOAD   = ERP_BASE + "/api/marketing/chatbot/upload";

  // ── Restricciones de upload (alineadas con backend) ──
  var ALLOWED_UPLOAD_MIMES = [
    "image/jpeg", "image/png", "image/webp", "image/gif", "application/pdf"
  ];
  var DEFAULT_MAX_UPLOAD_MB = 5;

  // ── Config por defecto (se actualiza desde /settings) ──
  var config = {
    enabled: true,
    greeting: "Hola, soy el Asistente Virtual de COMPIPRO.",
    farewell: "Gracias por escribirnos.",
    timeout1: 50,
    timeout2: 150,
    maxUploadMb: DEFAULT_MAX_UPLOAD_MB
  };

  // ── Estado del widget ──
  var sessionId = null;
  var isOpen = false;
  var isSending = false;
  var isClosed = false;
  var timeout1Timer = null;
  var timeout2Timer = null;
  var inactivityClosed = false;
  // Attachments staged: archivos ya subidos al backend pero todavía no enviados con un /message
  // Cada item: { id, original_name, mime_type, size_bytes, human_size, is_image, kind, previewUrl }
  var pendingAttachments = [];

  // ── Estilos ──
  var style = document.createElement("style");
  style.textContent = [
    ".cp-chat-btn{position:fixed;right:18px;bottom:18px;z-index:999999;background:#111;color:#fff;border-radius:999px;padding:12px 22px;font-family:system-ui,sans-serif;cursor:pointer;box-shadow:0 10px 30px rgba(0,0,0,.25);font-size:14px;font-weight:600;transition:transform .2s,box-shadow .2s;user-select:none;}",
    ".cp-chat-btn:hover{transform:scale(1.05);box-shadow:0 12px 35px rgba(0,0,0,.3);}",
    ".cp-chat-box{position:fixed;right:18px;bottom:80px;width:360px;max-width:calc(100vw - 36px);height:520px;max-height:calc(100vh - 110px);background:#fff;border-radius:16px;overflow:hidden;z-index:999999;box-shadow:0 12px 40px rgba(0,0,0,.25);font-family:system-ui,sans-serif;display:none;flex-direction:column;}",
    ".cp-chat-head{background:#111;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}",
    ".cp-chat-head-title{font-weight:700;font-size:15px;}",
    ".cp-chat-head-sub{font-size:11px;color:#aaa;margin-top:2px;}",
    ".cp-chat-body{flex:1;padding:14px;overflow-y:auto;background:#fafafa;}",
    ".cp-chat-foot{padding:8px 12px;border-top:1px solid #eee;display:flex;flex-direction:column;gap:6px;flex-shrink:0;background:#fff;}",
    ".cp-chat-foot-row{display:flex;gap:8px;align-items:center;}",
    ".cp-chat-input{flex:1;border:1px solid #ddd;border-radius:12px;padding:10px 14px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s;}",
    ".cp-chat-input:focus{border-color:#111;}",
    ".cp-chat-input:disabled{background:#f5f5f5;color:#999;}",
    ".cp-chat-send{background:#111;color:#fff;border:0;border-radius:12px;padding:10px 16px;cursor:pointer;font-size:14px;font-weight:600;font-family:inherit;transition:opacity .2s;}",
    ".cp-chat-send:disabled{opacity:.4;cursor:not-allowed;}",
    ".cp-attach-btn{background:#fff;color:#555;border:1px solid #ddd;border-radius:12px;padding:8px 10px;cursor:pointer;font-size:14px;line-height:1;transition:background .15s,border-color .15s;}",
    ".cp-attach-btn:hover{background:#f3f4f6;border-color:#bbb;}",
    ".cp-attach-btn:disabled{opacity:.4;cursor:not-allowed;}",
    ".cp-staged-list{display:flex;flex-wrap:wrap;gap:6px;padding:0 2px;}",
    ".cp-staged-chip{display:inline-flex;align-items:center;gap:6px;background:#eef2ff;color:#1e3a8a;border:1px solid #c7d2fe;border-radius:10px;padding:4px 8px;font-size:12px;max-width:200px;}",
    ".cp-staged-chip img{width:22px;height:22px;border-radius:4px;object-fit:cover;flex-shrink:0;}",
    ".cp-staged-chip .name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}",
    ".cp-staged-chip .rm{cursor:pointer;color:#6b7280;font-size:14px;line-height:1;padding:0 2px;}",
    ".cp-staged-chip .rm:hover{color:#dc2626;}",
    ".cp-bubble{margin:6px 0;display:flex;animation:cpFadeIn .3s ease;}",
    ".cp-bubble.me{justify-content:flex-end;}",
    ".cp-bubble .msg{max-width:82%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.45;word-wrap:break-word;}",
    ".cp-bubble.bot .msg{background:#fff;color:#111;border:1px solid #e5e7eb;border-top-left-radius:4px;}",
    ".cp-bubble.me .msg{background:#111;color:#fff;border-top-right-radius:4px;}",
    ".cp-bubble.me .msg.attachments-only{background:#222;}",
    ".cp-attach-preview{display:flex;flex-direction:column;gap:6px;}",
    ".cp-attach-preview img{max-width:200px;max-height:160px;border-radius:8px;object-fit:contain;background:#000;}",
    ".cp-attach-preview .file{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border-radius:8px;padding:6px 10px;font-size:12px;}",
    ".cp-typing{margin:6px 0;display:flex;}",
    ".cp-typing .dots{background:#fff;border:1px solid #e5e7eb;border-radius:16px;border-top-left-radius:4px;padding:10px 16px;display:flex;gap:4px;align-items:center;}",
    ".cp-typing .dot{width:7px;height:7px;background:#999;border-radius:50%;animation:cpBounce 1.2s infinite;}",
    ".cp-typing .dot:nth-child(2){animation-delay:.2s;}",
    ".cp-typing .dot:nth-child(3){animation-delay:.4s;}",
    ".cp-catalog-btn{display:inline-block;margin-top:6px;padding:8px 14px;background:#f0f0f0;color:#111;border-radius:10px;font-size:13px;text-decoration:none;border:1px solid #ddd;cursor:pointer;transition:background .2s;}",
    ".cp-catalog-btn:hover{background:#e0e0e0;}",
    ".cp-closed-msg{text-align:center;padding:12px;font-size:13px;color:#888;}",
    ".cp-x{cursor:pointer;font-size:18px;opacity:.8;transition:opacity .2s;padding:4px;}",
    ".cp-x:hover{opacity:1;}",
    "@keyframes cpFadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}",
    "@keyframes cpBounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-5px)}}",
    "@media(max-width:480px){.cp-chat-box{right:0;bottom:0;width:100%;max-width:100%;height:100%;max-height:100%;border-radius:0;}.cp-chat-btn{right:12px;bottom:12px;}}"
  ].join("\n");
  document.head.appendChild(style);

  // ── Crear elementos del DOM ──
  var btn = document.createElement("div");
  btn.className = "cp-chat-btn";
  btn.setAttribute("role", "button");
  btn.setAttribute("aria-label", "Abrir chat");
  btn.textContent = "Chat";

  var box = document.createElement("div");
  box.className = "cp-chat-box";
  box.innerHTML = [
    '<div class="cp-chat-head">',
    '  <div>',
    '    <div class="cp-chat-head-title">COMPIPRO</div>',
    '    <div class="cp-chat-head-sub">Asistente Virtual</div>',
    '  </div>',
    '  <div class="cp-x" role="button" aria-label="Cerrar chat" title="Cerrar">&#10005;</div>',
    '</div>',
    '<div class="cp-chat-body"></div>',
    '<div class="cp-chat-foot">',
    '  <div class="cp-staged-list" aria-live="polite"></div>',
    '  <div class="cp-chat-foot-row">',
    '    <button class="cp-attach-btn" type="button" aria-label="Adjuntar archivo" title="Adjuntar archivo (logo, foto)">📎</button>',
    '    <input class="cp-chat-input" placeholder="Escribe tu mensaje..." aria-label="Mensaje" />',
    '    <button class="cp-chat-send" type="button" aria-label="Enviar">Enviar</button>',
    '  </div>',
    '</div>'
  ].join("");

  document.body.appendChild(btn);
  document.body.appendChild(box);

  var bodyEl    = box.querySelector(".cp-chat-body");
  var inputEl   = box.querySelector(".cp-chat-input");
  var sendBtn   = box.querySelector(".cp-chat-send");
  var closeBtn  = box.querySelector(".cp-x");
  var attachBtn = box.querySelector(".cp-attach-btn");
  var stagedListEl = box.querySelector(".cp-staged-list");

  // Hidden file input para abrir el file picker
  var fileInput = document.createElement("input");
  fileInput.type = "file";
  fileInput.accept = ALLOWED_UPLOAD_MIMES.join(",");
  fileInput.style.display = "none";
  document.body.appendChild(fileInput);

  // ── Utilidades ──

  function generateUUID() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      var v = c === "x" ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  function getOrCreateSessionId() {
    var key = "cp_chatbot_session";
    var existing = null;
    try { existing = sessionStorage.getItem(key); } catch (e) {}
    if (existing) return existing;
    var id = generateUUID();
    try { sessionStorage.setItem(key, id); } catch (e) {}
    return id;
  }

  function clearSessionId() {
    try { sessionStorage.removeItem("cp_chatbot_session"); } catch (e) {}
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
    });
  }

  function addMsg(text, who) {
    var row = document.createElement("div");
    row.className = "cp-bubble " + who;
    var msgDiv = document.createElement("div");
    msgDiv.className = "msg";
    msgDiv.textContent = text;
    row.appendChild(msgDiv);
    bodyEl.appendChild(row);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function addAttachmentsBubble(attachments, userText) {
    // Burbuja "me" que muestra los archivos enviados (con texto opcional debajo)
    var row = document.createElement("div");
    row.className = "cp-bubble me";

    var msgDiv = document.createElement("div");
    msgDiv.className = "msg" + (userText ? "" : " attachments-only");

    var preview = document.createElement("div");
    preview.className = "cp-attach-preview";

    attachments.forEach(function (a) {
      if (a.is_image && a.previewUrl) {
        var img = document.createElement("img");
        img.src = a.previewUrl;
        img.alt = a.original_name;
        preview.appendChild(img);
      } else {
        var file = document.createElement("div");
        file.className = "file";
        file.innerHTML = "📄 " + escapeHtml(a.original_name) + " · " + escapeHtml(a.human_size);
        preview.appendChild(file);
      }
    });

    msgDiv.appendChild(preview);

    if (userText) {
      var t = document.createElement("div");
      t.style.marginTop = "6px";
      t.textContent = userText;
      msgDiv.appendChild(t);
    }

    row.appendChild(msgDiv);
    bodyEl.appendChild(row);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function addCatalogButton(line, url) {
    var row = document.createElement("div");
    row.className = "cp-bubble bot";
    var msgDiv = document.createElement("div");
    msgDiv.className = "msg";
    var link = document.createElement("a");
    link.className = "cp-catalog-btn";
    link.href = url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.textContent = "Ver catalogo: " + line;
    msgDiv.appendChild(link);
    row.appendChild(msgDiv);
    bodyEl.appendChild(row);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function showTyping() {
    removeTyping();
    var typing = document.createElement("div");
    typing.className = "cp-typing";
    typing.id = "cp-typing-indicator";
    typing.innerHTML = '<div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>';
    bodyEl.appendChild(typing);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function removeTyping() {
    var existing = document.getElementById("cp-typing-indicator");
    if (existing) existing.parentNode.removeChild(existing);
  }

  function setInputEnabled(enabled) {
    inputEl.disabled = !enabled;
    sendBtn.disabled = !enabled;
    attachBtn.disabled = !enabled;
    if (enabled) inputEl.focus();
  }

  function showClosedMessage() {
    var div = document.createElement("div");
    div.className = "cp-closed-msg";
    div.textContent = "Conversacion finalizada. Cierra y abre el chat para iniciar una nueva.";
    bodyEl.appendChild(div);
    bodyEl.scrollTop = bodyEl.scrollHeight;
    setInputEnabled(false);
    isClosed = true;
  }

  // ── Staged attachments (chips antes de enviar) ──

  function renderStagedList() {
    stagedListEl.innerHTML = "";
    pendingAttachments.forEach(function (a) {
      var chip = document.createElement("span");
      chip.className = "cp-staged-chip";

      if (a.is_image && a.previewUrl) {
        var img = document.createElement("img");
        img.src = a.previewUrl;
        img.alt = a.original_name;
        chip.appendChild(img);
      } else {
        var icon = document.createElement("span");
        icon.textContent = "📄";
        chip.appendChild(icon);
      }

      var name = document.createElement("span");
      name.className = "name";
      name.textContent = a.original_name + " · " + a.human_size;
      name.title = a.original_name;
      chip.appendChild(name);

      var rm = document.createElement("span");
      rm.className = "rm";
      rm.title = "Quitar";
      rm.textContent = "✕";
      rm.addEventListener("click", function () {
        if (a.previewUrl) {
          try { URL.revokeObjectURL(a.previewUrl); } catch (e) {}
        }
        pendingAttachments = pendingAttachments.filter(function (x) { return x.id !== a.id; });
        renderStagedList();
      });
      chip.appendChild(rm);

      stagedListEl.appendChild(chip);
    });
  }

  function clearStaged() {
    pendingAttachments.forEach(function (a) {
      if (a.previewUrl) { try { URL.revokeObjectURL(a.previewUrl); } catch (e) {} }
    });
    pendingAttachments = [];
    renderStagedList();
  }

  // ── Subida de archivo ──

  function inferKindFromMime(mime) {
    // El backend acepta: logo|reference|screenshot|other; el cliente no
    // discrimina, así que enviamos 'other' y la ejecutiva categoriza si quiere.
    return "other";
  }

  function uploadFile(file) {
    if (isClosed || !sessionId) return;

    // Validación cliente (defensa en profundidad — el backend revalida con finfo)
    if (ALLOWED_UPLOAD_MIMES.indexOf(file.type) === -1) {
      addMsg("Tipo de archivo no permitido. Solo imágenes (JPG/PNG/WEBP/GIF) o PDF.", "bot");
      return;
    }
    var maxBytes = (config.maxUploadMb || DEFAULT_MAX_UPLOAD_MB) * 1024 * 1024;
    if (file.size > maxBytes) {
      addMsg("El archivo supera el tamaño máximo de " + (config.maxUploadMb || DEFAULT_MAX_UPLOAD_MB) + " MB.", "bot");
      return;
    }

    // Mostrar typing mientras sube
    showTyping();
    setInputEnabled(false);

    var fd = new FormData();
    fd.append("session_id", sessionId);
    fd.append("file", file);
    fd.append("kind", inferKindFromMime(file.type));

    fetch(API_UPLOAD, {
      method: "POST",
      body: fd
    })
    .then(function (res) {
      return res.json().then(function (data) { return { status: res.status, data: data }; });
    })
    .then(function (resp) {
      removeTyping();
      setInputEnabled(true);

      if (resp.status === 429) {
        addMsg("Demasiadas subidas seguidas. Esperá un momento e intentá de nuevo.", "bot");
        return;
      }
      if (!resp.data || !resp.data.ok) {
        var msg = (resp.data && resp.data.message) || "No pudimos subir el archivo.";
        addMsg(msg, "bot");
        return;
      }

      var att = resp.data.attachment;
      var item = {
        id: att.id,
        original_name: att.original_name,
        mime_type: att.mime_type,
        size_bytes: att.size_bytes,
        human_size: att.human_size,
        is_image: att.is_image,
        kind: att.kind
      };
      // Generar preview local (solo para mostrar al usuario; no se sube de nuevo)
      if (item.is_image) {
        try { item.previewUrl = URL.createObjectURL(file); } catch (e) {}
      }
      pendingAttachments.push(item);
      renderStagedList();
    })
    .catch(function () {
      removeTyping();
      setInputEnabled(true);
      addMsg("Error de conexión al subir el archivo. Intentá de nuevo.", "bot");
    });
  }

  // ── Timeouts de inactividad ──

  function clearTimeouts() {
    if (timeout1Timer) { clearTimeout(timeout1Timer); timeout1Timer = null; }
    if (timeout2Timer) { clearTimeout(timeout2Timer); timeout2Timer = null; }
  }

  function resetInactivityTimers() {
    clearTimeouts();
    if (isClosed || inactivityClosed) return;

    timeout1Timer = setTimeout(function () {
      if (isClosed || inactivityClosed) return;
      sendMessageToAPI("[SISTEMA: El usuario lleva " + config.timeout1 + " segundos sin responder. Genera un mensaje suave de reenganche segun R17 timeout_1.]", true, []);
    }, config.timeout1 * 1000);

    timeout2Timer = setTimeout(function () {
      if (isClosed || inactivityClosed) return;
      sendMessageToAPI("[SISTEMA: El usuario lleva " + config.timeout2 + " segundos sin responder. Genera mensaje de cierre segun R17 timeout_2.]", true, []);
      inactivityClosed = true;
    }, config.timeout2 * 1000);
  }

  // ── Comunicación con API ──

  function sendMessageToAPI(message, isSystem, attachmentIds) {
    if (isSending) return;
    isSending = true;

    if (!isSystem) {
      showTyping();
      setInputEnabled(false);
    }

    var payload = {
      session_id: sessionId,
      message: message,
      page_url: window.location.href
    };

    if (Array.isArray(attachmentIds) && attachmentIds.length > 0) {
      payload.attachment_ids = attachmentIds;
    }

    // Agregar UTMs si están en la URL
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get("utm_source")) payload.utm_source = params.get("utm_source");
      if (params.get("utm_medium")) payload.utm_medium = params.get("utm_medium");
      if (params.get("utm_campaign")) payload.utm_campaign = params.get("utm_campaign");
    } catch (e) {}

    fetch(API_MESSAGE, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      removeTyping();
      isSending = false;

      if (!data.ok) {
        addMsg("Disculpa, hubo un problema. Intenta de nuevo.", "bot");
        setInputEnabled(true);
        return;
      }

      // Mostrar respuesta
      if (data.reply) {
        addMsg(data.reply, "bot");
      }

      // Procesar acciones
      var actions = data.actions || {};

      // Catalogo
      if (actions.show_catalog && actions.show_catalog.url) {
        addCatalogButton(actions.show_catalog.line || "Catalogo", actions.show_catalog.url);
      }

      // Escalado o cierre
      if (actions.conversation_closed || actions.escalated) {
        showClosedMessage();
        clearTimeouts();
        return;
      }

      // Conversación sigue abierta
      setInputEnabled(true);
      resetInactivityTimers();
    })
    .catch(function () {
      removeTyping();
      isSending = false;
      addMsg("Error de conexion. Verifica tu internet e intenta de nuevo.", "bot");
      setInputEnabled(true);
    });
  }

  function sendFirstMessage() {
    isSending = true;
    showTyping();
    setInputEnabled(false);

    var payload = {
      session_id: sessionId,
      message: "init",
      is_first_message: true,
      page_url: window.location.href
    };

    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get("utm_source")) payload.utm_source = params.get("utm_source");
      if (params.get("utm_medium")) payload.utm_medium = params.get("utm_medium");
      if (params.get("utm_campaign")) payload.utm_campaign = params.get("utm_campaign");
    } catch (e) {}

    fetch(API_MESSAGE, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      removeTyping();
      isSending = false;

      if (data.ok && data.reply) {
        addMsg(data.reply, "bot");
      } else {
        addMsg(config.greeting, "bot");
      }

      setInputEnabled(true);
      resetInactivityTimers();
    })
    .catch(function () {
      removeTyping();
      isSending = false;
      addMsg(config.greeting, "bot");
      setInputEnabled(true);
    });
  }

  // ── Cargar settings ──

  function loadSettings() {
    return fetch(API_SETTINGS, { method: "GET" })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.greeting) config.greeting = json.greeting;
        if (json.farewell) config.farewell = json.farewell;
        if (json.timeout_1) config.timeout1 = parseInt(json.timeout_1, 10) || 50;
        if (json.timeout_2) config.timeout2 = parseInt(json.timeout_2, 10) || 150;
        if (json.max_upload_mb) {
          var mb = parseInt(json.max_upload_mb, 10);
          if (mb > 0 && mb <= 50) config.maxUploadMb = mb;
        }
        if (json.enabled === false) config.enabled = false;
      })
      .catch(function () {});
  }

  // ── Eventos del usuario ──

  function onSend() {
    var text = inputEl.value.trim();
    if ((!text && pendingAttachments.length === 0) || isSending || isClosed) return;

    var attachmentIds = pendingAttachments.map(function (a) { return a.id; });
    var attachmentsSnapshot = pendingAttachments.slice(); // copia para render

    inputEl.value = "";
    clearTimeouts();

    if (attachmentsSnapshot.length > 0) {
      addAttachmentsBubble(attachmentsSnapshot, text);
      // Importante: NO llamar a clearStaged() porque revoca los previewUrl que la
      // burbuja recién dibujada está usando. El navegador los liberará al
      // descargar/cerrar la página. Solo limpiamos la lista lógica.
      pendingAttachments = [];
      renderStagedList();
    } else {
      addMsg(text, "me");
    }

    // Si no hay texto, mandamos un placeholder al backend; el orchestrator detecta
    // 'init' o vacío y le indica al LLM que el turno trae solo archivos.
    var messageToSend = text || "init";
    sendMessageToAPI(messageToSend, false, attachmentIds);
  }

  function onAttachClick() {
    if (isClosed || isSending) return;
    fileInput.value = ""; // permite re-seleccionar el mismo archivo
    fileInput.click();
  }

  fileInput.addEventListener("change", function () {
    var files = fileInput.files;
    if (!files || files.length === 0) return;
    // Subimos secuencialmente para no saturar el rate limit (5/min)
    var idx = 0;
    function next() {
      if (idx >= files.length) return;
      uploadFile(files[idx]);
      idx++;
      // Pausa breve entre subidas si vienen varias
      if (idx < files.length) setTimeout(next, 400);
    }
    next();
  });

  // ── Abrir / Cerrar chat ──

  function openChat() {
    box.style.display = "flex";
    isOpen = true;
    bodyEl.innerHTML = "";
    isClosed = false;
    inactivityClosed = false;
    clearTimeouts();
    clearStaged();

    // Nueva sesión cada vez que se abre
    clearSessionId();
    sessionId = getOrCreateSessionId();

    loadSettings().then(function () {
      if (!config.enabled) {
        addMsg("El chat no esta disponible en este momento.", "bot");
        setInputEnabled(false);
        return;
      }
      sendFirstMessage();
    });
  }

  function closeChat() {
    box.style.display = "none";
    isOpen = false;
    clearTimeouts();
    clearStaged();
  }

  btn.addEventListener("click", function () {
    if (isOpen) {
      closeChat();
    } else {
      openChat();
    }
  });

  closeBtn.addEventListener("click", closeChat);
  sendBtn.addEventListener("click", onSend);
  attachBtn.addEventListener("click", onAttachClick);
  inputEl.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      onSend();
    }
  });
})();
