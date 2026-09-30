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
 *
 * One impression may be shown in several places on a page (the sidebar list,
 * the row below the viewer, the floating button). similar-items-blocks.js then
 * registers the impression once through window.SimilarItemsLog.register(), with
 * every place listed. The impression counts as seen when the first of them is
 * seen, and each event says which place it came from. A place registered as
 * "manual" is seen when it announces so (the floating panel being opened), not
 * when it is on screen.
 *
 *  - exposure : which ranks reached the screen, item by item (half of an
 *               entry visible). Unlike `view`, which uses 40% of the block's
 *               box and so means different things for blocks of different
 *               heights, this rule is the same for every placement. Sent when
 *               the first item is seen, and again on leaving if more were.
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

  var registered = {};

  /**
   * Start tracking one impression.
   *
   * @param {Object} meta
   *   The metadata the recommendation endpoint returned.
   * @param {Array} targets
   *   Places the list is shown: {el, placement, mode: 'viewport'|'manual'}.
   */
  function registerMeta(meta, targets) {
    if (!meta || !meta.impression || !meta.endpoint || registered[meta.impression]) {
      return;
    }
    var places = [];
    for (var i = 0; i < (targets || []).length; i++) {
      if (targets[i] && targets[i].el) {
        places.push(targets[i]);
      }
    }
    if (!places.length) {
      return;
    }
    registered[meta.impression] = true;

    // Control arm "off". The layout stylesheet normally hides the block before
    // it paints; this is the fallback for pages that stylesheet did not reach.
    // The impression is already recorded server side, so nothing more to do.
    if (meta.hide) {
      places.forEach(function (place) {
        place.el.hidden = true;
        place.el.style.display = 'none';
      });
      return;
    }

    var block = {
      meta: meta,
      places: places,
      renderedAt: Date.now(),
      visibleAt: 0,
      visiblePlacement: null,
      viewSent: false,
      ranksSeen: {},
      ranksSent: 0,
      itemsSeenAt: 0,
      itemsSeenPlacement: null,
      exposureTimer: 0,
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

    places.forEach(function (place) {
      if (place.mode === 'manual') {
        watchShown(block, place);
      }
      else {
        observeVisibility(block, place);
      }
      observeItems(block, place);
    });
  }

  function countKeys(obj) {
    var n = 0;
    for (var k in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, k)) {
        n++;
      }
    }
    return n;
  }

  function sendExposure(block) {
    if (block.exposureTimer) {
      clearTimeout(block.exposureTimer);
      block.exposureTimer = 0;
    }
    var n = countKeys(block.ranksSeen);
    if (!n || n === block.ranksSent) {
      return;
    }
    block.ranksSent = n;
    var ranks = [];
    for (var r in block.ranksSeen) {
      if (Object.prototype.hasOwnProperty.call(block.ranksSeen, r)) {
        ranks.push(parseInt(r, 10));
      }
    }
    send(block.meta.endpoint, {
      type: 'exposure',
      impression: block.meta.impression,
      ranks: ranks,
      placement: block.itemsSeenPlacement,
      ms: block.itemsSeenAt - block.renderedAt
    });
  }

  /**
   * Watch each recommended entry: a rank counts as seen once half of it is on
   * screen. The list is rendered in rank order, so position gives the rank.
   */
  function observeItems(block, place) {
    if (!('IntersectionObserver' in window)) {
      return;
    }
    var entries = place.el.querySelectorAll('li.resource');
    if (!entries.length) {
      return;
    }
    var io = new IntersectionObserver(function (changes) {
      for (var i = 0; i < changes.length; i++) {
        if (!changes[i].isIntersecting) {
          continue;
        }
        var rank = parseInt(changes[i].target.getAttribute('data-si-rank'), 10);
        io.unobserve(changes[i].target);
        if (!rank || block.ranksSeen[rank]) {
          continue;
        }
        block.ranksSeen[rank] = true;
        if (!block.itemsSeenAt) {
          block.itemsSeenAt = Date.now();
          block.itemsSeenPlacement = place.placement || null;
        }
        // Batch entries that appear together (a list scrolled into view).
        if (!block.exposureTimer) {
          block.exposureTimer = setTimeout(function () {
            sendExposure(block);
          }, 400);
        }
      }
    }, { threshold: 0.5 });
    for (var i = 0; i < entries.length; i++) {
      entries[i].setAttribute('data-si-rank', String(i + 1));
      io.observe(entries[i]);
    }
  }

  /**
   * Legacy path: a payload script injected into the page with the list.
   */
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
    var container = metaEl.closest('[data-similar-items]')
      || metaEl.closest('.similar-items')
      || metaEl.parentElement;
    if (!container) {
      return;
    }
    registerMeta(meta, [{
      el: container,
      placement: container.getAttribute('data-si-placement') || '',
      mode: 'viewport'
    }]);
  }

  function sendView(block, visible) {
    if (block.viewSent) {
      return;
    }
    block.viewSent = true;
    var now = Date.now();
    if (visible && !block.visibleAt) {
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
      // Whether the referrer is this same site. Only the browser can judge
      // this reliably: behind a reverse proxy the server does not necessarily
      // see the host the visitor actually used.
      internal: sameOrigin(document.referrer) ? 1 : 0,
      visible: visible ? 1 : 0,
      // Where it was seen. A view written off as never seen has no place.
      placement: visible ? block.visiblePlacement : null,
      dwell_ms: now - block.renderedAt
    });
    block.from = null;
  }

  /**
   * Report that a block reached the screen after it was already written off.
   *
   * Switching tabs fires visibilitychange, which flushes a "not seen" view. The
   * visitor may well come back and scroll to the block, so that first report has
   * to be correctable; otherwise the visibility rate is biased downwards and any
   * later click looks like a click on something never displayed.
   */
  function sendVisibleUpgrade(block) {
    send(block.meta.endpoint, {
      type: 'view',
      impression: block.meta.impression,
      key: randomKey(),
      visible: 1,
      placement: block.visiblePlacement,
      dwell_ms: block.visibleAt - block.renderedAt
    });
  }

  /**
   * The first time any place of the impression is seen.
   */
  function markVisible(block, place) {
    if (block.visibleAt) {
      return;
    }
    // Record the moment regardless of whether a view was already reported,
    // so that a later click carries a correct visible_ms.
    block.visibleAt = Date.now();
    block.visiblePlacement = place.placement || null;
    if (block.viewSent) {
      sendVisibleUpgrade(block);
    } else {
      sendView(block, true);
    }
  }

  /**
   * A place that is seen when it says so (the floating panel being opened).
   */
  function watchShown(block, place) {
    var handler = function () {
      place.el.removeEventListener('similaritems:shown', handler);
      markVisible(block, place);
    };
    place.el.addEventListener('similaritems:shown', handler);
  }

  function observeVisibility(block, place) {
    if (!('IntersectionObserver' in window)) {
      markVisible(block, place);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (!entries[i].isIntersecting) {
          continue;
        }
        io.disconnect();
        markVisible(block, place);
        return;
      }
    }, { threshold: 0.4 });
    io.observe(place.el);
  }

  /**
   * Whether a URL is on this same origin. Empty or unparsable means no.
   */
  function sameOrigin(url) {
    if (!url) {
      return false;
    }
    try {
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  /**
   * Find the registered block a node belongs to.
   */
  function blockFor(node) {
    for (var i = 0; i < blocks.length; i++) {
      for (var j = 0; j < blocks[i].places.length; j++) {
        if (blocks[i].places[j].el.contains(node)) {
          return { block: blocks[i], place: blocks[i].places[j] };
        }
      }
    }
    return null;
  }

  function handleActivation(event) {
    var anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor) {
      return;
    }
    var found = blockFor(anchor);
    if (!found) {
      return;
    }
    var block = found.block;
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
      placement: found.place.placement || null,
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
      sendExposure(blocks[i]);
    }
  }

  function scan(root) {
    var nodes = (root || document).querySelectorAll(META_SELECTOR);
    for (var i = 0; i < nodes.length; i++) {
      register(nodes[i]);
    }
  }

  window.SimilarItemsLog = {
    register: registerMeta
  };

  function init() {
    var pending = window.__similarItemsPending || [];
    window.__similarItemsPending = [];
    for (var p = 0; p < pending.length; p++) {
      registerMeta(pending[p][0], pending[p][1]);
    }
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
