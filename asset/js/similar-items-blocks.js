/**
 * @file
 * Fills every Similar Items block on a page from a single request.
 *
 * A page may carry more than one recommendation block (the sidebar list, the
 * row below the viewer, the floating button), whether while they are being
 * compared or by accident of configuration. Each block fetching for itself
 * would turn one page view into several impressions with different jitter
 * draws, and the usage log would count the page several times. So the blocks
 * only declare themselves; this script makes one request naming all their
 * placements, puts the same list into each, and hands the log metadata to the
 * logging script once, with every block as a place it can be seen from.
 *
 * Contract with the block templates:
 *   data-similar-items            present on every block (control-arm CSS)
 *   data-si-placement             sidebar | strip | floating
 *   data-item-id, data-limit, data-endpoint, data-site-slug
 *   data-si-visibility="manual"   seen when opened, not when on screen
 *   data-si-empty="hide"          hide the block when there is nothing to show
 *   data-si-empty-text            message otherwise
 *   [data-si-list]                where the <li> items go
 *   [data-si-fab-toggle], [data-si-fab-panel], [data-si-fab-close]
 *                                 the floating button's parts
 */
(function () {
  'use strict';

  if (window.__similarItemsBlocksLoaded) {
    return;
  }
  window.__similarItemsBlocksLoaded = true;

  var BLOCK_SELECTOR = '[data-similar-items][data-si-placement]';
  var LOG_SELECTOR = 'script[data-similar-items-log]';

  function each(list, fn) {
    for (var i = 0; i < list.length; i++) {
      fn(list[i], i);
    }
  }

  function emit(el, name, detail) {
    var event;
    try {
      event = new CustomEvent(name, { detail: detail || {} });
    }
    catch (e) {
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(name, false, false, detail || {});
    }
    el.dispatchEvent(event);
  }

  function limitOf(el) {
    var n = parseInt(el.getAttribute('data-limit'), 10);
    return n > 0 ? n : 0;
  }

  function siteSlugOf(el) {
    var slug = el.getAttribute('data-site-slug') || '';
    if (!slug && window.location && window.location.pathname) {
      var m = window.location.pathname.match(/\/s\/([^\/]+)/);
      if (m && m[1]) {
        slug = decodeURIComponent(m[1]);
      }
    }
    return slug;
  }

  function endpointOf(el, slug) {
    var endpoint = el.getAttribute('data-endpoint') || '';
    // The site-scoped endpoint renders the list with the site's theme, so a
    // theme override of the list partial applies.
    if (slug && endpoint.indexOf('/s/') === -1) {
      endpoint = '/s/' + encodeURIComponent(slug) + '/similar-items/recommend';
    }
    return endpoint;
  }

  /**
   * Hand the log metadata to the logging script, or queue it if that script
   * has not run yet (it drains the queue when it starts).
   */
  function registerLog(meta, blocks) {
    var targets = [];
    each(blocks, function (block) {
      targets.push({
        el: block,
        placement: block.getAttribute('data-si-placement') || '',
        mode: block.getAttribute('data-si-visibility') === 'manual' ? 'manual' : 'viewport'
      });
    });
    var api = window.SimilarItemsLog;
    if (api && typeof api.register === 'function') {
      api.register(meta, targets);
    }
    else {
      (window.__similarItemsPending = window.__similarItemsPending || []).push([meta, targets]);
    }
  }

  function showEmpty(block, list) {
    if (block.getAttribute('data-si-empty') === 'hide') {
      block.hidden = true;
      return;
    }
    if (!list) {
      return;
    }
    var text = block.getAttribute('data-si-empty-text') || '';
    list.innerHTML = '';
    if (text) {
      var li = document.createElement('li');
      li.className = 'similar-items__empty';
      li.setAttribute('aria-live', 'polite');
      li.textContent = text;
      list.appendChild(li);
    }
  }

  function distribute(blocks, payload) {
    var html = (payload && typeof payload.html === 'string') ? payload.html : '';
    var meta = (payload && payload.log) ? payload.log : null;

    // The list comes with the log payload as a hidden script in front of it.
    // Strip it: the payload is registered once below, and leaving copies in
    // each block would let the logging script count the page several times.
    var tpl = document.createElement('template');
    tpl.innerHTML = html;
    each(tpl.content.querySelectorAll(LOG_SELECTOR), function (node) {
      if (!meta) {
        try {
          meta = JSON.parse(node.textContent || 'null');
        }
        catch (e) { /* no metadata */ }
      }
      node.parentNode.removeChild(node);
    });

    each(blocks, function (block) {
      var list = block.querySelector('[data-si-list]');
      var fragment = tpl.content.cloneNode(true);
      var items = fragment.querySelectorAll('li.resource');
      var limit = limitOf(block) || items.length;
      for (var k = limit; k < items.length; k++) {
        items[k].parentNode.removeChild(items[k]);
      }
      var count = Math.min(items.length, limit);
      if (list) {
        list.innerHTML = '';
        list.removeAttribute('aria-busy');
        if (count) {
          list.appendChild(fragment);
        }
      }
      if (!count) {
        showEmpty(block, list);
      }
      block.setAttribute('data-si-state', count ? 'ready' : 'empty');
      emit(block, 'similaritems:loaded', { count: count });
    });

    if (meta) {
      registerLog(meta, blocks);
    }
  }

  function load(itemId, blocks) {
    var first = blocks[0];
    var slug = siteSlugOf(first);
    var endpoint = endpointOf(first, slug);
    var limit = 1;
    var placements = [];
    each(blocks, function (block) {
      limit = Math.max(limit, limitOf(block));
      var p = block.getAttribute('data-si-placement') || '';
      if (p && placements.indexOf(p) === -1) {
        placements.push(p);
      }
    });
    if (!endpoint) {
      distribute(blocks, null);
      return;
    }
    var url = endpoint
      + '?id=' + encodeURIComponent(itemId)
      + '&limit=' + encodeURIComponent(limit)
      + (slug ? '&site=' + encodeURIComponent(slug) : '')
      + (placements.length ? '&placement=' + encodeURIComponent(placements.join(',')) : '');
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (j) { distribute(blocks, j); })
      .catch(function () { distribute(blocks, null); });
  }

  /**
   * The floating button: a fixed character that opens a panel.
   *
   * With a mouse the panel follows the pointer: it opens when the pointer
   * rests on the character and closes when it leaves the character and the
   * panel. Touch has no hover, so a tap opens and closes it instead, and the
   * keyboard uses Enter/Space on the button. Escape and a click elsewhere close
   * it in every case.
   *
   * The block is moved to <body> first. A fixed element is positioned against
   * the viewport only while no ancestor creates a containing block (transform,
   * filter, contain), and the region the block was assigned to is outside this
   * script's control.
   */
  // Resting time before a hover opens the panel. A pointer crossing the corner
  // on its way elsewhere should not open it, and an opening counts as the
  // recommendations being seen.
  var HOVER_OPEN_MS = 120;
  // Grace time before leaving closes it, so the pointer can travel from the
  // character to the panel across the gap between them.
  var HOVER_CLOSE_MS = 250;

  function setupFloating(block) {
    var toggle = block.querySelector('[data-si-fab-toggle]');
    var panel = block.querySelector('[data-si-fab-panel]');
    var closer = block.querySelector('[data-si-fab-close]');
    if (!toggle || !panel) {
      return;
    }
    if (block.parentNode !== document.body) {
      document.body.appendChild(block);
    }
    var openTimer = 0;
    var closeTimer = 0;

    function clearTimers() {
      clearTimeout(openTimer);
      clearTimeout(closeTimer);
      openTimer = 0;
      closeTimer = 0;
    }

    function isOpen() {
      return !panel.hidden;
    }

    function open(moveFocus) {
      if (block.getAttribute('data-si-state') !== 'ready' || isOpen()) {
        return;
      }
      panel.hidden = false;
      block.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      // This, not the button being on screen, is what "seen" means for this
      // block: the button is always on screen, the recommendations are not.
      emit(block, 'similaritems:shown');
      // Focus moves into the panel only when it was opened on purpose by tap
      // or keyboard; a hover must not take the focus away from the page.
      if (moveFocus) {
        var focusTarget = closer || panel;
        try {
          focusTarget.focus({ preventScroll: true });
        }
        catch (e) {
          focusTarget.focus();
        }
      }
    }

    function close(restoreFocus) {
      clearTimers();
      if (!isOpen()) {
        return;
      }
      var hadFocus = block.contains(document.activeElement);
      panel.hidden = true;
      block.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      if (restoreFocus && hadFocus) {
        toggle.focus();
      }
    }

    block.addEventListener('pointerenter', function (e) {
      if (e.pointerType !== 'mouse') {
        return;
      }
      clearTimeout(closeTimer);
      closeTimer = 0;
      if (!isOpen() && !openTimer) {
        openTimer = setTimeout(function () {
          openTimer = 0;
          open(false);
        }, HOVER_OPEN_MS);
      }
    });
    block.addEventListener('pointerleave', function (e) {
      if (e.pointerType !== 'mouse') {
        return;
      }
      clearTimeout(openTimer);
      openTimer = 0;
      if (isOpen()) {
        closeTimer = setTimeout(function () {
          closeTimer = 0;
          close(false);
        }, HOVER_CLOSE_MS);
      }
    });

    toggle.addEventListener('click', function (e) {
      // A mouse click on the character opens at once (it may land before the
      // hover delay has run) and never closes: under a mouse, leaving closes.
      if (e.pointerType === 'mouse') {
        clearTimers();
        open(false);
        return;
      }
      // Touch, pen and keyboard (pointerType empty) toggle.
      if (isOpen()) {
        close(false);
      }
      else {
        open(true);
      }
    });
    if (closer) {
      closer.addEventListener('click', function () {
        close(true);
      });
    }
    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.key === 'Esc') && isOpen()) {
        close(true);
      }
    });
    document.addEventListener('pointerdown', function (e) {
      if (isOpen() && !block.contains(e.target)) {
        close(false);
      }
    });

    // Appear only once there is something to open.
    block.addEventListener('similaritems:loaded', function (e) {
      if (e.detail && e.detail.count > 0) {
        block.hidden = false;
        block.classList.add('is-ready');
      }
      else {
        block.hidden = true;
      }
    });
  }

  /**
   * Move a placement preview typed after the fragment into the query string.
   *
   * Item pages often carry a Mirador deep link (#canvas=...). A preview
   * parameter added at the end of such a URL lands in the fragment, which the
   * browser never sends: the server cannot see it, and the viewer reads it as
   * part of the canvas id. Reloading with the parameter moved before the
   * fragment makes it work wherever it was typed. Only a valid placement name
   * is moved, and the fragment is left without it, so this cannot loop.
   *
   * @return {boolean}
   *   TRUE when the page is being reloaded.
   */
  function relocatePreviewParam() {
    var hash = String(window.location.hash || '');
    var m = hash.match(/([#?&])si_placement=(sidebar|strip|floating)(?=&|$)/);
    if (!m || !window.URLSearchParams) {
      return false;
    }
    var params = new URLSearchParams(window.location.search);
    params.set('si_placement', m[2]);
    var rest = hash.replace(m[0], m[1] === '#' ? '#' : '').replace(/^#&/, '#');
    if (rest === '#') {
      rest = '';
    }
    window.location.replace(window.location.pathname + '?' + params.toString() + rest);
    return true;
  }

  function init() {
    if (relocatePreviewParam()) {
      return;
    }
    var groups = {};
    var order = [];
    each(document.querySelectorAll(BLOCK_SELECTOR), function (block) {
      if (block.getAttribute('data-si-bound')) {
        return;
      }
      block.setAttribute('data-si-bound', '1');
      var id = block.getAttribute('data-item-id') || '';
      if (!id) {
        return;
      }
      if (block.getAttribute('data-si-placement') === 'floating') {
        setupFloating(block);
      }
      if (!groups[id]) {
        groups[id] = [];
        order.push(id);
      }
      groups[id].push(block);
    });
    each(order, function (id) {
      var run = function () { load(id, groups[id]); };
      // Let the page paint first; the recommendation is secondary content.
      if (window.requestIdleCallback) {
        window.requestIdleCallback(run, { timeout: 1000 });
      }
      else {
        setTimeout(run, 0);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  }
  else {
    init();
  }
})();
