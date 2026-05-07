(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    // global capture to prevent native form navigation for messenger forms
    // ensures we stop full-page reloads even if the specific messenger
    // container failed to initialize for any reason.
    document.addEventListener('submit', function(e) {
      try {
        var t = e.target;
        if (t && t.matches && t.matches('[data-message-form]')) {
          // only prevent default here; allow bubble-phase handlers to run
          e.preventDefault();
        }
      } catch (err) {
        // ignore
      }
    }, true);
    var container = document.querySelector('[data-messaging-root]');
    if (!container) return;

    var selectedContactId = container.getAttribute('data-selected-contact-id') || '';

    var threadNode = container.querySelector('[data-messages-thread]');
    var formNode = container.querySelector('[data-message-form]');
    var inputNode = container.querySelector('[data-message-input]');
    var rewriteButton = container.querySelector('[data-message-rewrite]');
    var rewriteCsrfNode = container.querySelector('[data-rewrite-csrf]');
    var submitButton = container.querySelector('[data-message-submit]');
    var rewriteUrl = container.getAttribute('data-rewrite-url') || '';
    var threadUrlTemplate = container.getAttribute('data-thread-url-template') || '';
    var unreadUrl = container.getAttribute('data-unread-url') || '';
    var pollInterval = parseInt(container.getAttribute('data-poll-interval') || '2500', 10);
    var websocketUrl = container.getAttribute('data-websocket-url') || '';
    var knownIds = new Set();

    threadNode.querySelectorAll('[data-message-id]').forEach(function(node) {
      var id = node.getAttribute('data-message-id');
      if (id) knownIds.add(id);
    });

    function scrollToBottom() {
      if (threadNode) {
        threadNode.scrollTop = threadNode.scrollHeight;
      }
    }

    function autoResizeTextarea(textarea) {
      if (!textarea) return;
      textarea.style.height = '46px';
      var maxHeight = 180;
      var targetHeight = Math.min(textarea.scrollHeight, maxHeight);
      textarea.style.height = targetHeight + 'px';
      textarea.style.overflowY = textarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
    }

    if (inputNode) {
      autoResizeTextarea(inputNode);
      inputNode.addEventListener('input', function() {
        autoResizeTextarea(inputNode);
      });
    }

    function setError(text) {
      var errorNode = container.querySelector('[data-message-error]');
      if (!errorNode) return;
      if (!text) {
        errorNode.textContent = '';
        errorNode.classList.add('d-none');
        return;
      }
      errorNode.textContent = text;
      errorNode.classList.remove('d-none');
    }

    function escapeHtml(text) {
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function buildMessageNode(message) {
      var wrapper = document.createElement('div');
      wrapper.className = 'd-flex mb-3 ' + (message.mine ? 'justify-content-end' : 'justify-content-start');
      wrapper.setAttribute('data-message-id', String(message.id));

      var bubbleClass = message.mine ? 'messenger-bubble mine' : 'messenger-bubble theirs';
      var timeClass = message.mine ? 'text-white-50' : 'text-muted';

      // include star button similar to server-side template
      var starClass = message.isStarred ? 'fa-star text-warning' : 'fa-star text-muted';
      wrapper.innerHTML =
        '<div class="' + bubbleClass + '">' +
        '<div class="small" style="white-space: pre-wrap;">' + escapeHtml(message.body || '') + '</div>' +
        '<div class="text-end mt-1 d-flex align-items-center justify-content-end gap-2">' +
        '<button type="button" class="btn btn-sm btn-link p-0 text-decoration-none star-btn" data-star-message="' + escapeHtml(String(message.id)) + '" aria-label="Marquer comme favori">' +
        '<i class="fas ' + starClass + '"></i>' +
        '</button>' +
        '<small class="' + timeClass + '">' + escapeHtml(message.createdAt || '') + '</small>' +
        '</div>' +
        '</div>';

      if (message.pending) {
        wrapper.style.opacity = '0.75';
        var bubble = wrapper.querySelector('.messenger-bubble');
        if (bubble) bubble.classList.add('border', 'border-warning');
      }

      // attach star handler for this single node
      var starBtn = wrapper.querySelector('.star-btn');
      if (starBtn) {
        starBtn.addEventListener('click', onStarClick);
      }

      return wrapper;
    }

    function onStarClick(e) {
      if (e && typeof e.preventDefault === 'function') try { e.preventDefault(); } catch (__) {}
      if (e && typeof e.stopPropagation === 'function') try { e.stopPropagation(); } catch (__) {}
      var btn = e && e.currentTarget ? e.currentTarget : (this || null);
      if (!btn) return;
      var id = btn.getAttribute && btn.getAttribute('data-star-message');
      if (!id) return;

      try { btn.disabled = true; } catch (__) {}
      var icon = btn.querySelector ? btn.querySelector('i.fas') : null;
      var originalClass = icon && icon.className ? icon.className : '';

      fetch('/messages/toggle-star/' + encodeURIComponent(id), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      }).then(function(res) {
        return res.json().catch(function() { return {success:false}; });
      }).then(function(payload) {
        if (!payload || !payload.success) {
          if (icon) icon.className = originalClass;
          return;
        }
        if (icon) {
          if (payload.isStarred) {
            icon.className = 'fas fa-star text-warning';
          } else {
            icon.className = 'fas fa-star text-muted';
          }
        }
      }).catch(function() {
        if (icon) icon.className = originalClass;
      }).finally(function() {
        try { btn.disabled = false; } catch (__) {}
      });
    }

    function appendMessage(message, autoScroll) {
      var id = String(message.id);
      if (knownIds.has(id)) return;

      var emptyNode = threadNode.querySelector('[data-empty-thread]');
      if (emptyNode) emptyNode.remove();

      var messageNode = buildMessageNode(message);
      threadNode.appendChild(messageNode);
      knownIds.add(id);

      // after appending, ensure any star buttons are wired
      var starBtn = messageNode.querySelector('.star-btn');
      if (starBtn) starBtn.addEventListener('click', onStarClick);

      if (autoScroll || message.mine) {
        scrollToBottom();
      }
    }

    if (formNode) {
      // named handler so it can be invoked from capture-phase listener
      async function handleFormSubmit(e) {
        try {
          e.preventDefault();
        } catch (ex) {}
        try {
          e.stopPropagation();
        } catch (ex) {}
        setError('');

        var body = inputNode.value.trim();
        if (!body) {
          setError('Le message ne peut pas etre vide.');
          return false;
        }

        var now = new Date();
        var tempId = 'tmp-' + Date.now();
        var optimisticMessage = {
          id: tempId,
          body: body,
          mine: true,
          createdAt: now.toLocaleString('fr-FR'),
          pending: true
        };

        appendMessage(optimisticMessage, true);
        var formData = new FormData(formNode);
        formData.set('body', body);
        inputNode.value = '';
        autoResizeTextarea(inputNode);

        if (submitButton) {
          submitButton.disabled = true;
          submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
          var response = await fetch(formNode.action, {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          });

          var payload = await response.json().catch(function() { return null; });
          if (!response.ok || !payload || !payload.success) {
            var pendingNode = threadNode.querySelector('[data-message-id="' + tempId + '"]');
            if (pendingNode) {
              pendingNode.remove();
              knownIds.delete(tempId);
            }
            inputNode.value = body;
            autoResizeTextarea(inputNode);
            setError((payload && payload.error) || 'Envoi impossible.');
            return false;
          }

          var oldNode = threadNode.querySelector('[data-message-id="' + tempId + '"]');
          if (oldNode) {
            oldNode.remove();
            knownIds.delete(tempId);
          }
          appendMessage(payload.message, true);

        } catch (error) {
          var optimisticNode = threadNode.querySelector('[data-message-id="' + tempId + '"]');
          if (optimisticNode) {
            optimisticNode.remove();
            knownIds.delete(tempId);
          }
          inputNode.value = body;
          autoResizeTextarea(inputNode);
          console.error('Messenger send error:', error);
          setError('Erreur reseau.');
        } finally {
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-arrow-right"></i>';
          }
        }

        return false;
      }

      formNode.addEventListener('submit', handleFormSubmit);

      // make submit button trigger the form's submit event
      if (submitButton) {
        submitButton.addEventListener('click', function(ev) {
          ev.preventDefault();
          ev.stopPropagation();
          if (formNode) {
            var submitEvent = new Event('submit', { bubbles: true, cancelable: true });
            formNode.dispatchEvent(submitEvent);
          }
        });
      }

      // capture-phase listener to intercept native submits before they navigate
      document.addEventListener('submit', function(e) {
        var target = e.target;
        if (target && target.matches && target.matches('[data-message-form]')) {
          e.preventDefault();
          e.stopPropagation();
          // call handler directly
          try { handleFormSubmit.call(target, e); } catch (err) {}
        }
      }, true);
    }

    // --- WebSocket client (optional real-time push) ---
    (function setupWebSocket() {
      if (!websocketUrl) return;
      var reconnectDelay = 1000;
      var ws = null;

      function connect() {
        try {
          ws = new WebSocket(websocketUrl);
        } catch (err) {
          scheduleReconnect();
          return;
        }

        ws.addEventListener('open', function() {
          reconnectDelay = 1000;
        });

        ws.addEventListener('message', function(ev) {
          try {
            var data = JSON.parse(ev.data);
          } catch (e) {
            return;
          }

          // expected formats: {type: 'message', message: {...}} or direct message object
          if (data && data.type === 'message' && data.message) {
            appendMessage(data.message, true);
          } else if (data && data.id) {
            appendMessage(data, true);
          }
        });

        ws.addEventListener('close', function() {
          scheduleReconnect();
        });

        ws.addEventListener('error', function() {
          try { ws.close(); } catch (e) {}
        });
      }

      function scheduleReconnect() {
        setTimeout(function() {
          reconnectDelay = Math.min(30000, reconnectDelay * 1.5);
          connect();
        }, reconnectDelay);
      }

      connect();
    })();

    // Polling to fetch latest thread messages and merge
    async function fetchThread() {
      if (!threadUrlTemplate || !selectedContactId) return;
      var url = threadUrlTemplate.replace('__CONTACT_ID__', encodeURIComponent(selectedContactId));
      try {
        var res = await fetch(url, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        });

        var payload = await res.json().catch(function() { return null; });
        if (!res.ok || !payload || !payload.success) return;

        var messages = payload.messages || [];
        messages.forEach(function(m) {
          appendMessage(m, false);
        });
      } catch (err) {
        // silent
      }
    }

    // attach star handlers for existing DOM nodes
    function attachExistingStarHandlers() {
      threadNode.querySelectorAll('.star-btn').forEach(function(btn) {
        btn.removeEventListener('click', onStarClick);
        btn.addEventListener('click', onStarClick);
      });
    }

    // clear thread
    function clearThread() {
      if (!threadNode) return;
      threadNode.innerHTML = '';
      knownIds.clear();
    }

    // load conversation for a contact id and optionally push history
    async function loadConversation(contactId, pushState) {
      if (!contactId) return;
      selectedContactId = String(contactId);

      // update recipient hidden input
      var recipientInput = container.querySelector('[data-recipient-id]');
      if (recipientInput) recipientInput.value = selectedContactId;

      // update active class on contact list
      container.querySelectorAll('.messenger-contact').forEach(function(el) {
        if (el.getAttribute('data-contact-id') === String(contactId)) {
          el.classList.add('active');
        } else {
          el.classList.remove('active');
        }
      });

      // fetch thread and replace contents
      if (!threadUrlTemplate) return;
      var url = threadUrlTemplate.replace('__CONTACT_ID__', encodeURIComponent(contactId));
      try {
        var res = await fetch(url, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        });

        var payload = await res.json().catch(function() { return null; });
        if (!res.ok || !payload || !payload.success) return;

        clearThread();
        var messages = payload.messages || [];
        messages.forEach(function(m) {
          appendMessage(m, false);
        });

        // hide unread badge for this contact
        var badge = container.querySelector('[data-unread-badge="' + contactId + '"]');
        if (badge) {
          badge.textContent = '0';
          badge.classList.add('d-none');
        }

        // update URL query param without reload
        if (pushState !== false) {
          try {
            var urlObj = new URL(window.location.href);
            urlObj.searchParams.set('with', String(contactId));
            window.history.pushState({}, '', urlObj.pathname + urlObj.search + urlObj.hash);
          } catch (e) {
            // fallback
            window.history.pushState({}, '', '?with=' + encodeURIComponent(contactId));
          }
        }

        scrollToBottom();
      } catch (err) {
        // ignore
      }
    }

    // delegate clicks on contact links to load via AJAX (bubble phase)
    container.addEventListener('click', function(e) {
      var contactEl = e.target.closest('.messenger-contact');
      if (!contactEl) return;
      var contactId = contactEl.getAttribute('data-contact-id');
      if (!contactId) return;
      e.preventDefault();
      e.stopPropagation();
      loadConversation(contactId, true);
    });

    // also intercept in capture phase on document to ensure we prevent navigation
    document.addEventListener('click', function(e) {
      var link = e.target.closest('a.messenger-contact');
      if (!link) return;
      var contactId = link.getAttribute('data-contact-id');
      if (!contactId) return;
      e.preventDefault();
      e.stopPropagation();
      loadConversation(contactId, true);
    }, true);

    // start polling loop
    if (pollInterval > 0) {
      // initial fetch to get any messages that arrived since page render
      fetchThread();
      setInterval(fetchThread, pollInterval);
    }

    // wire existing star buttons
    attachExistingStarHandlers();

    if (rewriteButton && rewriteUrl) {
      rewriteButton.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        setError('');

        var textToRewrite = inputNode.value.trim();
        if (!textToRewrite) {
          setError('Ecrivez un message avant de lancer la reecriture.');
          return;
        }

        var rewriteCsrf = rewriteCsrfNode ? rewriteCsrfNode.value : '';
        if (!rewriteCsrf) {
          setError('Token de securite manquant.');
          return;
        }

        rewriteButton.disabled = true;
        rewriteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
          var rewriteFormData = new FormData();
          rewriteFormData.set('body', textToRewrite);
          rewriteFormData.set('_csrf_token', rewriteCsrf);

          var response = await fetch(rewriteUrl, {
            method: 'POST',
            body: rewriteFormData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          });

          var payload = await response.json();
          if (!response.ok || !payload.success) {
            setError((payload && payload.error) || 'Reecriture IA indisponible.');
            return;
          }

          inputNode.value = (payload.rewritten || '').trim();
          autoResizeTextarea(inputNode);

        } catch (error) {
          console.error('Rewrite request error:', error);
          setError('Erreur reseau pendant la reecriture.');
        } finally {
          rewriteButton.disabled = false;
          rewriteButton.innerHTML = '<span aria-hidden="true">✨</span>';
        }
      });
    }

    scrollToBottom();
  });
})();
