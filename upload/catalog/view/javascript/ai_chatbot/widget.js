/**
 * AI Chatbot racket advisor widget. Vanilla JS, no dependencies. Talks only
 * to this store's own extension/module/ai_chatbot/chat route (same-origin
 * AJAX) — never to the Node service or any LLM API directly, so no secret
 * ever reaches the browser.
 */
(function () {
  'use strict';

  function qs(root, selector) {
    return root.querySelector(selector);
  }

  function getConversationId() {
    var key = 'ai_chatbot_conversation_id';
    try {
      var existing = window.localStorage.getItem(key);
      if (existing) {
        return existing;
      }
      var id = (window.crypto && window.crypto.randomUUID)
        ? window.crypto.randomUUID()
        : 'c-' + Date.now() + '-' + Math.random().toString(16).slice(2);
      window.localStorage.setItem(key, id);
      return id;
    } catch (e) {
      // localStorage unavailable (private mode, etc.) — fall back to a
      // per-page-load id; the conversation just won't persist across reloads.
      return 'c-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }
  }

  function createWidget(root) {
    var chatUrl = root.getAttribute('data-chat-url');
    var welcome = root.getAttribute('data-welcome') || '';
    var conversationId = getConversationId();

    var toggle = qs(root, '.ai-chatbot-toggle');
    var panel = qs(root, '.ai-chatbot-panel');
    var closeBtn = qs(root, '.ai-chatbot-close');
    var messages = qs(root, '.ai-chatbot-messages');
    var form = qs(root, '.ai-chatbot-form');
    var input = qs(root, '.ai-chatbot-input');
    var sendBtn = qs(root, '.ai-chatbot-send');

    var opened = false;
    var welcomed = false;
    var sending = false;

    function appendBubble(role, text) {
      var bubble = document.createElement('div');
      bubble.className = 'ai-chatbot-bubble ai-chatbot-bubble-' + role;
      bubble.textContent = text;
      messages.appendChild(bubble);
      return bubble;
    }

    function appendProductCards(products) {
      if (!products || !products.length) {
        return;
      }
      var wrap = document.createElement('div');
      wrap.className = 'ai-chatbot-products';
      products.forEach(function (p) {
        var card = document.createElement(p.product_url ? 'a' : 'div');
        card.className = 'ai-chatbot-product-card';
        if (p.product_url) {
          card.href = p.product_url;
          card.target = '_blank';
          card.rel = 'noopener';
        }

        var name = document.createElement('span');
        name.className = 'ai-chatbot-product-name';
        name.textContent = p.display_name || '';
        card.appendChild(name);

        if (typeof p.price === 'number') {
          var price = document.createElement('span');
          price.className = 'ai-chatbot-product-price';
          price.textContent = p.price;
          card.appendChild(price);
        }

        wrap.appendChild(card);
      });
      messages.appendChild(wrap);
    }

    function scrollToBottom() {
      messages.scrollTop = messages.scrollHeight;
    }

    function setOpen(next) {
      opened = next;
      panel.hidden = !opened;
      toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
      if (opened) {
        if (!welcomed && welcome) {
          appendBubble('assistant', welcome);
          welcomed = true;
        }
        input.focus();
        scrollToBottom();
      }
    }

    toggle.addEventListener('click', function () {
      setOpen(!opened);
    });
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (sending) {
        return;
      }
      var text = input.value.trim();
      if (!text) {
        return;
      }

      appendBubble('user', text);
      input.value = '';
      scrollToBottom();

      sending = true;
      sendBtn.disabled = true;
      var thinking = appendBubble('assistant', '...');
      scrollToBottom();

      fetch(chatUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, conversation_id: conversationId }),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok) {
              throw new Error((data && data.error) || 'request_failed');
            }
            return data;
          });
        })
        .then(function (data) {
          thinking.textContent = data.reply || '';
          appendProductCards(data.suggested_products);
        })
        .catch(function () {
          thinking.className += ' ai-chatbot-bubble-error';
          thinking.textContent = 'Sorry, something went wrong. Please try again in a moment.';
        })
        .finally(function () {
          sending = false;
          sendBtn.disabled = false;
          scrollToBottom();
        });
    });
  }

  function init() {
    var root = document.getElementById('ai-chatbot');
    if (root) {
      createWidget(root);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
