/* P03 E-commerce System - vanilla JS frontend integration (SHOP-13).
   Handles WebSocket order-state updates, checkout/payment simulation UI,
   and client-side form validation. */
(function () {
  'use strict';

  var CSRF = (function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  })();

  var WS_URL = window.SHOP_WS_URL || 'ws://127.0.0.1:8081';

  /* ------------------------------------------------------------------ */
  /* Real-time order states (WebSocket + polling fallback)               */
  /* ------------------------------------------------------------------ */

  function applyOrderState(payload) {
    var id = payload.order_id || payload.id;
    var status = payload.status;
    if (!id || !status) return;
    document.querySelectorAll('[data-order-status="' + id + '"]').forEach(function (el) {
      el.textContent = status;
    });
    var row = document.querySelector('[data-order-row="' + id + '"]');
    if (row) {
      row.classList.add('updated');
      setTimeout(function () { row.classList.remove('updated'); }, 1500);
    }
    toast('Order ' + (payload.number || ('#' + id)) + ' → ' + status);
  }

  function connectWS() {
    if (!window.WebSocket) return;
    var ws;
    function open() {
      try {
        ws = new WebSocket(WS_URL);
      } catch (e) { schedule(); return; }
      ws.onopen = function () {
        ws.send(JSON.stringify({ type: 'subscribe', channel: 'orders' }));
      };
      ws.onmessage = function (ev) {
        try {
          var msg = JSON.parse(ev.data);
          if (msg.event === 'order.status_changed' || msg.event === 'order.created') {
            applyOrderState(msg.payload || {});
          }
        } catch (e) { /* ignore malformed frames */ }
      };
      ws.onclose = schedule;
      ws.onerror = schedule;
    }
    function schedule() {
      setTimeout(open, 3000);
    }
    open();
  }

  /* ------------------------------------------------------------------ */
  /* Polling fallback for the order-state dashboard                      */
  /* ------------------------------------------------------------------ */

  function pollOrderStates() {
    fetch('/api/shop/frontend_api_integration', {
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF }
    })
      .then(function (r) { return r.json(); })
      .then(function (body) {
        if (body.success && body.data && body.data.order_states) {
          body.data.order_states.forEach(applyOrderState);
        }
      })
      .catch(function () { /* offline-safe */ });
  }

  /* ------------------------------------------------------------------ */
  /* Checkout: validation + deterministic payment simulation UI          */
  /* ------------------------------------------------------------------ */

  function bindCheckout() {
    var form = document.querySelector('form[data-jscheckout]');
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      var card = form.querySelector('[data-js-card]');
      var cvv = form.querySelector('[name="card_cvv"]');
      var expiry = form.querySelector('[name="card_expiry"]');
      var digits = (card.value || '').replace(/\s+/g, '');
      if (!/^\d{13,19}$/.test(digits)) {
        ev.preventDefault();
        toast('Card number must contain 13-19 digits.');
        card.focus();
        return;
      }
      if (!/^\d{3,4}$/.test(cvv.value || '')) {
        ev.preventDefault();
        toast('CVV must be 3-4 digits.');
        cvv.focus();
        return;
      }
      if (!/^\d{2}\/\d{2}$/.test(expiry.value || '')) {
        ev.preventDefault();
        toast('Expiry must look like 12/29.');
        expiry.focus();
        return;
      }
      // Show deterministic simulation steps while the server processes.
      var steps = form.querySelector('[data-jsstatus]');
      if (steps) {
        steps.hidden = false;
        ['validate', 'processing'].forEach(function (s) {
          var li = steps.querySelector('[data-pstep="' + s + '"]');
          if (li) li.className = 'done';
        });
        var approved = steps.querySelector('[data-pstep="approved"]');
        if (approved) approved.className = 'running';
      }
      form.querySelector('button[type="submit"]').disabled = true;
    });
  }

  /* ------------------------------------------------------------------ */
  /* Lightweight validation for other forms                              */
  /* ------------------------------------------------------------------ */

  function bindValidation(selector, messages) {
    var form = document.querySelector(selector);
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      for (var field in messages) {
        if (!messages.hasOwnProperty(field)) continue;
        var input = form.querySelector('[name="' + field + '"]');
        if (!input) continue;
        var value = (input.value || '').trim();
        if (messages[field] && value === '') {
          ev.preventDefault();
          toast(messages[field]);
          input.focus();
          return;
        }
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Toast                                                               */
  /* ------------------------------------------------------------------ */

  function toast(message) {
    var el = document.createElement('div');
    el.className = 'toast';
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () {
      el.classList.remove('show');
      setTimeout(function () { el.remove(); }, 300);
    }, 2600);
  }

  /* ------------------------------------------------------------------ */
  /* Boot                                                               */
  /* ------------------------------------------------------------------ */

  function boot() {
    connectWS();
    bindCheckout();
    bindValidation('form[data-jsregister]', { name: 'Enter your full name.', email: 'Enter your email.' });
    bindValidation('form[data-jsreview]', { text: 'Write a review before submitting.' });
    bindValidation('form[data-jsproduct]', { name: 'Product name is required.', price: 'Price is required.' });
    if (document.querySelector('[data-order-status]')) {
      pollOrderStates();
      setInterval(pollOrderStates, 5000);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
