/**
 * @file
 * SimilarItems usage logging (client side).
 *
 * The recommendation endpoint injects a hidden JSON payload
 * (<script type="application/json" data-similar-items-log>) at the top of the
 * rendered list. That makes this script independent of the theme partial: it
 * only needs the payload and the surrounding container.
 *
 * Collected signals:
 *  - view  : the block actually entered the viewport (with time to view),
 *            or the visitor left the page without ever seeing it.
 *  - click : a recommended item was opened, with the dwell time since render
 *            and since the block became visible.
 *
 * A click key is stored in sessionStorage so the next page can tell the server
 * which recommendation led there; this makes the browsing chain exact even when
 * two visits to the same item happen in one session.
 */
(function () {
  'use strict';

  if (window.__similarItemsLogLoaded) {
    return;
  }
  window.__similarItemsLogLoaded = true;

  var STORAGE_KEY = 'similarItems.lastClick';
  var CHAIN_TTL_MS = 10 * 60 * 1000;
  var META_SELECTOR = 'script[data-similar-items-log]';

  /**
   * Post a payload, preferring sendBeacon so it survives navigation.
   */
  function send(endpoint, payload) {
    var body;
    try {
      body = JSON.stringify(payload);
    } catch (e) {
      return;
    }
    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([body], { type: 'text/plain;charset=UTF-8' });
        if (navigator.sendBeacon(endpoint, blob)) {
          return;
        }
      }
    } catch (e) { /* fall through to fetch */ }
    try {
      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json' },
        body: body
      }).catch(function () {});
    } catch (e) { /* logging must never break the page */ }
  }

  /**
   * Read and clear the click key stored by the previous page.
   */
  function takePendingClick() {
    try {
      var raw = window.sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return null;
      }
      window.sessionStorage.removeItem(STORAGE_KEY);
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.k) {
        return null;
      }
      if (Date.now() - (parsed.t || 0) > CHAIN_TTL_MS) {
        return null;
      }
      return parsed.k;
    } catch (e) {
      return null;
    }
  }

  function rememberClick(key) {
    try {
      window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ k: key, t: Date.now() }));
    } catch (e) { /* private mode */ }
  }

  function randomKey() {
    try {
      var buf = new Uint8Array(16);
      window.crypto.getRandomValues(buf);
      return Array.prototype.map.call(buf, function (b) {
        return ('0' + b.toString(16)).slice(-2);
      }).join('');
    } catch (e) {
      var out = '';
      while (out.length < 32) {
        out += Math.floor(Math.random() * 16).toString(16);
      }
      return out.slice(0, 32);
    }
  }

  /**
   * Extract an item id from an href such as /s/site/item/123 or ?id=123.
   */
  function itemIdFromHref(href) {
    if (!href) {
      return 0;
    }
    var m = href.match(/\/(?:item|items)\/(\d+)(?:[/?#]|$)/);
    if (m) {
      return parseInt(m[1], 10);
    }
    m = href.match(/[?&]id=(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
  }

  /**
   * Active recommendation blocks on this page.
   */
  var blocks = [];

  function register(metaEl) {
    if (metaEl.__siLogBound) {
      return;
    }
    metaEl.__siLogBound = true;

    var meta;
    try {
      meta = JSON.parse(metaEl.textContent || '{}');
    } catch (e) {
      return;
    }
    if (!meta || !meta.impression || !meta.endpoint) {
      return;
    }

    var container = metaEl.closest('[data-similar-items]')
      || metaEl.closest('.similar-items')
      || metaEl.parentElement;
    if (!container) {
      return;
    }

    // Control arm "off". The layout stylesheet normally hides the block before
    // it paints; this is the fallback for pages that stylesheet did not reach.
    // The impression is already recorded server side, so nothing more to do.
    if (meta.hide) {
      container.hidden = true;
      container.style.display = 'none';
      return;
    }

    var block = {
      meta: meta,
      container: container,
      renderedAt: Date.now(),
      visibleAt: 0,
      viewSent: false,
      byUrl: {},
      byId: {},
      from: takePendingClick()
    };
    (meta.items || []).forEach(function (it) {
      if (it.url) {
        block.byUrl[it.url] = it;
      }
      block.byId[String(it.id)] = it;
    });
    blocks.push(block);

    observeVisibility(block);
  }

  function sendView(block, visible) {
    if (block.viewSent) {
      return;
    }
    block.viewSent = true;
    var now = Date.now();
    if (visible) {
      block.visibleAt = now;
    }
    send(block.meta.endpoint, {
      type: 'view',
      impression: block.meta.impression,
      key: randomKey(),
      from: block.from || null,
      // The endpoint is fetched by the page itself, so its Referer header is
      // useless for attribution; only the page knows where the visitor came
      // from. Empty means a direct visit.
      ref: String(document.referrer || '').slice(0, 1024),
      visible: visible ? 1 : 0,
      dwell_ms: now - block.renderedAt
    });
    block.from = null;
  }

  function observeVisibility(block) {
    if (!('IntersectionObserver' in window)) {
      sendView(block, true);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (entries[i].isIntersecting) {
          io.disconnect();
          sendView(block, true);
          return;
        }
      }
    }, { threshold: 0.4 });
    io.observe(block.container);
  }

  /**
   * Find the registered block a node belongs to.
   */
  function blockFor(node) {
    for (var i = 0; i < blocks.length; i++) {
      if (blocks[i].container.contains(node)) {
        return blocks[i];
      }
    }
    return null;
  }

  function handleActivation(event) {
    var anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor) {
      return;
    }
    var block = blockFor(anchor);
    if (!block) {
      return;
    }
    var href = anchor.getAttribute('href');
    if (!href || href === '#') {
      return;
    }
    var entry = block.byUrl[href] || block.byId[String(itemIdFromHref(href))] || null;
    var itemId = entry ? entry.id : itemIdFromHref(href);
    if (!itemId) {
      return;
    }
    var now = Date.now();
    var key = randomKey();
    rememberClick(key);
    send(block.meta.endpoint, {
      type: 'click',
      impression: block.meta.impression,
      key: key,
      item_id: itemId,
      visible: block.visibleAt ? 1 : 0,
      dwell_ms: now - block.renderedAt,
      visible_ms: block.visibleAt ? (now - block.visibleAt) : null
    });
  }

  /**
   * Report blocks that were never seen when the visitor leaves the page.
   */
  function flush() {
    for (var i = 0; i < blocks.length; i++) {
      if (!blocks[i].viewSent) {
        sendView(blocks[i], false);
      }
    }
  }

  function scan(root) {
    var nodes = (root || document).querySelectorAll(META_SELECTOR);
    for (var i = 0; i < nodes.length; i++) {
      register(nodes[i]);
    }
  }

  function init() {
    scan(document);
    // The list is injected asynchronously, so watch for it.
    if ('MutationObserver' in window) {
      var mo = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          var added = mutations[i].addedNodes;
          for (var j = 0; j < added.length; j++) {
            var node = added[j];
            if (node.nodeType !== 1) {
              continue;
            }
            if (node.matches && node.matches(META_SELECTOR)) {
              register(node);
            } else if (node.querySelectorAll) {
              scan(node);
            }
          }
        }
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
    }

    document.addEventListener('click', handleActivation, true);
    document.addEventListener('auxclick', handleActivation, true);
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        flush();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
