(function () {
  "use strict";

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function buildMessageNode(message) {
    var wrapper = document.createElement("div");
    wrapper.className = "d-flex mb-3 " + (message.mine ? "justify-content-end" : "justify-content-start");
    wrapper.setAttribute("data-message-id", String(message.id));

    var bubbleClass = message.mine ? "messenger-bubble mine" : "messenger-bubble theirs";
    var timeClass = message.mine ? "text-white-50" : "text-muted";

    wrapper.innerHTML =
      '<div class="' + bubbleClass + '">' +
      '<div class="small" style="white-space: pre-wrap;">' + escapeHtml(message.body || "") + "</div>" +
      '<div class="text-end mt-1">' +
      '<small class="' + timeClass + '">' + escapeHtml(message.createdAt || "") + "</small>" +
      "</div>" +
      "</div>";

    if (message.pending) {
      wrapper.style.opacity = "0.75";
      var bubble = wrapper.querySelector(".messenger-bubble");
      if (bubble) {
        bubble.classList.add("border", "border-warning");
      }
    }

    return wrapper;
  }

  function setError(container, text) {
    var errorNode = container.querySelector("[data-message-error]");
    if (!errorNode) {
      return;
    }

    if (!text) {
      errorNode.textContent = "";
      errorNode.classList.add("d-none");
      return;
    }

    errorNode.textContent = text;
    errorNode.classList.remove("d-none");
  }

  function scrollToBottom(threadNode) {
    threadNode.scrollTop = threadNode.scrollHeight;
  }

  function shouldAutoScroll(threadNode) {
    var threshold = 80;
    return threadNode.scrollHeight - threadNode.scrollTop - threadNode.clientHeight <= threshold;
  }

  function truncateText(text, maxLength) {
    var value = String(text || "").trim();
    if (value.length <= maxLength) {
      return value;
    }

    return value.slice(0, maxLength) + "...";
  }

  function normalizePreviewTime(rawTime) {
    if (!rawTime) {
      return "";
    }

    var asDate = new Date(rawTime);
    if (!Number.isNaN(asDate.getTime())) {
      return asDate.toLocaleString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
    }

    return String(rawTime);
  }

    function autoResizeTextarea(textarea) {
      if (!textarea) {
        return;
      }

      textarea.style.height = "46px";
      var maxHeight = 180;
      var targetHeight = Math.min(textarea.scrollHeight, maxHeight);
      textarea.style.height = targetHeight + "px";
      textarea.style.overflowY = textarea.scrollHeight > maxHeight ? "auto" : "hidden";
    }

  function initMessaging(container) {
    var selectedContactId = container.getAttribute("data-selected-contact-id");
    if (!selectedContactId) {
      return;
    }

    var threadNode = container.querySelector("[data-messages-thread]");
    var formNode = container.querySelector("[data-message-form]");
    var inputNode = container.querySelector("[data-message-input]");
    var rewriteButton = container.querySelector("[data-message-rewrite]");
    var rewriteCsrfNode = container.querySelector("[data-rewrite-csrf]");
    var submitButton = container.querySelector("[data-message-submit]");
    var refreshButton = container.querySelector("[data-refresh-thread]");
    var threadTemplate = container.getAttribute("data-thread-url-template") || "";
    var unreadUrl = container.getAttribute("data-unread-url") || "";
    var rewriteUrl = container.getAttribute("data-rewrite-url") || "";
    var currentUserId = parseInt(container.getAttribute("data-current-user-id") || "0", 10);
    var websocketUrl = container.getAttribute("data-websocket-url") || "";
    var pollIntervalRaw = parseInt(container.getAttribute("data-poll-interval") || "4000", 10);
    var pollInterval = Number.isNaN(pollIntervalRaw) ? 4000 : Math.max(1000, pollIntervalRaw);
    var isFetchingThread = false;
    var isFetchingUnread = false;
    var websocketConnected = false;
    var websocket = null;
    var reconnectTimer = null;
    var lastFallbackSyncAt = 0;
    var isSending = false;
    var isRewriting = false;

    if (!threadNode || !formNode || !inputNode || !submitButton || !threadTemplate) {
      return;
    }

    var threadUrl = threadTemplate.replace("__CONTACT_ID__", selectedContactId);
    var knownIds = new Set();
    threadNode.querySelectorAll("[data-message-id]").forEach(function (node) {
      var id = node.getAttribute("data-message-id");
      if (id) {
        knownIds.add(String(id));
      }
    });

    if (knownIds.size > 0) {
      scrollToBottom(threadNode);
    }

    autoResizeTextarea(inputNode);
    inputNode.addEventListener("input", function () {
      autoResizeTextarea(inputNode);
    });

    function appendMessage(message, allowAutoScroll) {
      var id = String(message.id);
      if (knownIds.has(id)) {
        return false;
      }

      var emptyNode = threadNode.querySelector("[data-empty-thread]");
      if (emptyNode) {
        emptyNode.remove();
      }

      var autoScroll = allowAutoScroll && shouldAutoScroll(threadNode);
      var messageNode = buildMessageNode(message);
      threadNode.appendChild(messageNode);
      knownIds.add(id);

      if (autoScroll || message.mine) {
        scrollToBottom(threadNode);
      }

      return true;
    }

    function moveContactToTop(contactId) {
      var node = container.querySelector('[data-contact-id="' + contactId + '"]');
      if (!node || !node.parentElement) {
        return;
      }

      var list = node.parentElement;
      if (list.firstElementChild === node) {
        return;
      }

      list.insertBefore(node, list.firstElementChild);
    }

    function updateContactPreview(contactId, body, createdAt, mine) {
      var previewNode = container.querySelector('[data-contact-preview="' + contactId + '"]');
      var timeNode = container.querySelector('[data-contact-time="' + contactId + '"]');
      var prefix = mine ? "Vous: " : "";
      var preview = truncateText(prefix + String(body || ""), 62);

      if (previewNode) {
        previewNode.textContent = preview || "Aucun message pour le moment.";
      }

      if (timeNode) {
        timeNode.textContent = normalizePreviewTime(createdAt);
      }

      moveContactToTop(contactId);
    }

    function replaceMessageNode(oldId, message) {
      var oldNode = threadNode.querySelector('[data-message-id="' + oldId + '"]');
      if (!oldNode) {
        appendMessage(message, true);
        return;
      }

      if (knownIds.has(String(message.id))) {
        oldNode.remove();
        knownIds.delete(String(oldId));
        return;
      }

      var newNode = buildMessageNode(message);
      oldNode.replaceWith(newNode);
      knownIds.delete(String(oldId));
      knownIds.add(String(message.id));
      scrollToBottom(threadNode);
    }

    function handleRealtimeMessage(message) {
      if (!message || !message.id) {
        return;
      }

      var senderId = parseInt(message.senderId || "0", 10);
      var recipientId = parseInt(message.recipientId || "0", 10);
      if (senderId <= 0 || recipientId <= 0) {
        return;
      }

      var partnerId = senderId === currentUserId ? recipientId : senderId;
      updateContactPreview(partnerId, message.body || "", message.createdAt || "", senderId === currentUserId);

      if (String(partnerId) !== String(selectedContactId)) {
        fetchUnreadCounts();
        return;
      }

      appendMessage({
        id: message.id,
        body: message.body || "",
        mine: senderId === currentUserId,
        createdAt: message.createdAt || "",
        pending: false
      }, true);

      fetchUnreadCounts();
    }

    function connectWebSocket() {
      if (!window.WebSocket || !websocketUrl || currentUserId <= 0) {
        return;
      }

      if (websocket && (websocket.readyState === WebSocket.OPEN || websocket.readyState === WebSocket.CONNECTING)) {
        return;
      }

      websocket = new WebSocket(websocketUrl);

      websocket.addEventListener("open", function () {
        websocketConnected = true;
      });

      websocket.addEventListener("message", function (event) {
        var payload;
        try {
          payload = JSON.parse(event.data);
        } catch (error) {
          return;
        }

        if (!payload || payload.type !== "chat.message.created") {
          return;
        }

        handleRealtimeMessage(payload.message || null);
      });

      websocket.addEventListener("close", function () {
        websocketConnected = false;
        if (reconnectTimer) {
          window.clearTimeout(reconnectTimer);
        }

        reconnectTimer = window.setTimeout(function () {
          connectWebSocket();
        }, 1500);
      });

      websocket.addEventListener("error", function () {
        websocketConnected = false;
      });
    }

    function setSubmitting(isSubmitting) {
      submitButton.disabled = isSubmitting;
      submitButton.innerHTML = isSubmitting
        ? '<i class="fas fa-spinner fa-spin"></i>'
        : '<i class="fas fa-arrow-right"></i>';

      if (rewriteButton) {
        rewriteButton.disabled = isSubmitting || isRewriting;
      }
    }

    function setRewriting(state) {
      isRewriting = state;

      if (!rewriteButton) {
        return;
      }

      rewriteButton.disabled = state || isSending;
      rewriteButton.innerHTML = state
        ? '<i class="fas fa-spinner fa-spin"></i>'
        : '<span aria-hidden="true">✨</span>';
    }

    async function fetchThread() {
      if (isFetchingThread) {
        return;
      }

      isFetchingThread = true;

      try {
        var response = await fetch(threadUrl, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          credentials: "same-origin"
        });

        if (!response.ok) {
          return;
        }

        var payload = await response.json();
        if (!payload || !payload.success || !Array.isArray(payload.messages)) {
          return;
        }

        payload.messages.forEach(function (message) {
          appendMessage(message, true);
        });
      } catch (error) {
        // Keep polling silent to avoid noisy UX on intermittent network.
      } finally {
        isFetchingThread = false;
      }
    }

    async function fetchUnreadCounts() {
      if (!unreadUrl || isFetchingUnread) {
        return;
      }

      isFetchingUnread = true;

      try {
        var response = await fetch(unreadUrl, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          credentials: "same-origin"
        });

        if (!response.ok) {
          return;
        }

        var payload = await response.json();
        if (!payload || !payload.success || !payload.counts) {
          return;
        }

        Object.keys(payload.counts).forEach(function (contactId) {
          var badge = container.querySelector('[data-unread-badge="' + contactId + '"]');
          if (!badge) {
            return;
          }

          var count = parseInt(payload.counts[contactId], 10) || 0;
          badge.textContent = String(count);
          badge.classList.toggle("d-none", count <= 0);
        });
      } catch (error) {
        // Keep unread polling silent.
      } finally {
        isFetchingUnread = false;
      }
    }

    formNode.addEventListener("submit", async function (event) {
      event.preventDefault();
      setError(container, "");

      var body = inputNode.value.trim();
      if (!body) {
        setError(container, "Le message ne peut pas etre vide.");
        return;
      }

      var now = new Date();
      var tempId = "tmp-" + Date.now() + "-" + Math.floor(Math.random() * 10000);
      var optimisticMessage = {
        id: tempId,
        body: body,
        mine: true,
        createdAt: now.toLocaleString("fr-FR", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric",
          hour: "2-digit",
          minute: "2-digit"
        }),
        pending: true
      };

      appendMessage(optimisticMessage, true);
      updateContactPreview(selectedContactId, body, optimisticMessage.createdAt, true);
      var formData = new FormData(formNode);
      formData.set("body", body);
      inputNode.value = "";
      autoResizeTextarea(inputNode);
      setSubmitting(true);
      isSending = true;

      try {
        var response = await fetch(formNode.action, {
          method: "POST",
          body: formData,
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          credentials: "same-origin"
        });

        var payload = await response.json();
        if (!response.ok || !payload.success) {
          var pendingNode = threadNode.querySelector('[data-message-id="' + tempId + '"]');
          if (pendingNode) {
            pendingNode.remove();
            knownIds.delete(String(tempId));
          }
          setError(container, (payload && payload.error) || "Envoi impossible. Veuillez reessayer.");
          inputNode.value = body;
          autoResizeTextarea(inputNode);
          return;
        }

        replaceMessageNode(tempId, payload.message);
        updateContactPreview(selectedContactId, payload.message.body || body, payload.message.createdAt || "", true);
        fetchThread();
        fetchUnreadCounts();
      } catch (error) {
        var optimisticNode = threadNode.querySelector('[data-message-id="' + tempId + '"]');
        if (optimisticNode) {
          optimisticNode.remove();
          knownIds.delete(String(tempId));
        }
        setError(container, "Erreur reseau. Veuillez reessayer.");
        inputNode.value = body;
        autoResizeTextarea(inputNode);
      } finally {
        isSending = false;
        setSubmitting(false);
      }
    });

    if (rewriteButton) {
      rewriteButton.addEventListener("click", async function () {
        if (isSending || isRewriting) {
          return;
        }

        setError(container, "");

        var textToRewrite = inputNode.value.trim();
        if (!textToRewrite) {
          setError(container, "Ecrivez un message avant de lancer la reecriture.");
          return;
        }

        if (!rewriteUrl) {
          setError(container, "La route de reecriture IA est indisponible.");
          return;
        }

        var rewriteCsrf = rewriteCsrfNode ? rewriteCsrfNode.value : "";
        if (!rewriteCsrf) {
          setError(container, "Token de securite manquant pour la reecriture.");
          return;
        }

        setRewriting(true);

        try {
          var rewriteFormData = new FormData();
          rewriteFormData.set("body", textToRewrite);
          rewriteFormData.set("_csrf_token", rewriteCsrf);

          var response = await fetch(rewriteUrl, {
            method: "POST",
            body: rewriteFormData,
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            credentials: "same-origin"
          });

          var payload = await response.json();
          if (!response.ok || !payload.success) {
            setError(container, (payload && payload.error) || "Reecriture IA indisponible.");
            return;
          }

          inputNode.value = (payload.rewritten || "").trim();
          autoResizeTextarea(inputNode);
        } catch (error) {
          setError(container, "Erreur reseau pendant la reecriture.");
        } finally {
          setRewriting(false);
        }
      });
    }

    if (refreshButton) {
      refreshButton.addEventListener("click", function () {
        fetchThread();
        fetchUnreadCounts();
      });
    }

    function pollNow() {
      if (document.hidden) {
        return;
      }

      if (isSending) {
        return;
      }

      if (websocketConnected) {
        var now = Date.now();
        if (now - lastFallbackSyncAt < 30000) {
          return;
        }
        lastFallbackSyncAt = now;
      }

      fetchThread();
      fetchUnreadCounts();
    }

    function startPolling() {
      setInterval(pollNow, pollInterval);
    }

    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) {
        pollNow();
      }
    });

    window.addEventListener("focus", pollNow);

    connectWebSocket();
    pollNow();
    startPolling();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var container = document.querySelector("[data-messaging-root]");
    if (!container) {
      return;
    }

    initMessaging(container);
  });
})();
