/* Portare Unit Converter: display-only text conversion, never form data. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else {
    root.PortareUnitConverter = api;
    var boot = function () {
      if (!root.__pucInstance) root.__pucInstance = api.init(root, root.PUCConfig || {});
    };
    if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
  }
})(typeof window !== 'undefined' ? window : this, function () {
  'use strict';
  var fractions = { '¼': 1 / 4, '½': 1 / 2, '¾': 3 / 4, '⅐': 1 / 7, '⅑': 1 / 9, '⅒': 1 / 10, '⅓': 1 / 3, '⅔': 2 / 3, '⅕': 1 / 5, '⅖': 2 / 5, '⅗': 3 / 5, '⅘': 4 / 5, '⅙': 1 / 6, '⅚': 5 / 6, '⅛': 1 / 8, '⅜': 3 / 8, '⅝': 5 / 8, '⅞': 7 / 8 };
  var glyph = '[' + Object.keys(fractions).join('') + ']';
  // Mixed fractions must precede ranges; 1-1/2 is a single measurement.
  var number = '(?:\\d+[ \\t-]+\\d+/\\d+|\\d+/\\d+|(?:\\d+[ \\t]*)?' + glyph + '|(?:\\d+(?:\\.\\d+)?|\\.\\d+))';
  var separator = '(?:[ \\t]*[x×–—-][ \\t]*|,[ \\t]+(?:and[ \\t]+)?|[ \\t]+and[ \\t]+|[ \\t]+to[ \\t]+)';
  var expression = new RegExp('(' + number + '(?:' + separator + number + ')*)[ \\t]*(inches\\b|inch\\b|in\\.(?!\\w)|in\\b|["″”])', 'gi');
  var excluded = 'script,style,textarea,input,code,pre,noscript,svg,math,[contenteditable],[data-puc-ignore],#wpadminbar,.puc-control,.puc-status,[data-puc-toggle]';

  function precision(value) {
    var n = Number(value);
    return Number.isInteger(n) && n >= 0 && n <= 6 ? n : 1;
  }
  function numeric(raw) {
    var s = raw.trim(), last = s.slice(-1);
    if (fractions[last] !== undefined) return Number(s.slice(0, -1).trim() || 0) + fractions[last];
    var mixed = /^(\d+)[ \t-]+(\d+)\/(\d+)$/.exec(s);
    if (mixed) return Number(mixed[1]) + Number(mixed[2]) / Number(mixed[3]);
    var fraction = /^(\d+)\/(\d+)$/.exec(s);
    return fraction ? Number(fraction[1]) / Number(fraction[2]) : Number(s);
  }
  function convertText(text, decimals) {
    if (typeof text !== 'string' || !text) return text;
    var digits = precision(decimals);
    // Never partially convert feet-and-inches or area/volume notation.
    var protectedSpans = [];
    var feet = new RegExp('\\d+(?:\\.\\d+)?\\s*(?:[\'′]|feet\\b|foot\\b|ft\\b\\.?)\\s*(?:' + number + '\\s*(?:["″”]|inches\\b|inch\\b|in\\b\\.?))?', 'gi');
    var m;
    while ((m = feet.exec(text))) protectedSpans.push([m.index, m.index + m[0].length]);
    return text.replace(expression, function (match, group, unit, offset) {
      var before = text.slice(0, offset), after = text.slice(offset + match.length);
      var prev = before.slice(-1);
      if (/[\w./'′+\-–—¼½¾⅐-⅞]/u.test(prev) || /[“"]$/.test(before)) return match;
      if (protectedSpans.some(function (span) { return offset < span[1] && offset + match.length > span[0]; })) return match;
      if (/^\s*(?:[²³^]|sq\b|square\b|cub(?:ic|ed)\b)/i.test(after)) return match;
      if (/\b(?:square|cubic|sq\.)\s*$/i.test(before)) return match;
      // Bare English "in" remains strict. Quote markers may precede a
      // dimensional suffix or known dimension prose (real catalog labels).
      var ending = /^(?:\s*$|\s*[,.;:!?\)\]\}]|\s*[x×]\s*(?:\d|\.\d))/;
      if (unit.toLowerCase() === 'in' && !ending.test(after)) return match;
      if (/^["″”]$/.test(unit) && !ending.test(after) && !/^\s*(?:[hHlLwWdD]\b|(?:wide|deep|high|long|tall|thick|diameter|width|height|length|depth)\b)/i.test(after)) return match;
      // Do not convert URL fragments, SKU/model tokens, or thousands notation.
      var tokenStart = Math.max(before.lastIndexOf(' '), before.lastIndexOf('\n'), before.lastIndexOf('\t')) + 1;
      var token = before.slice(tokenStart) + match + (after.match(/^\S*/) || [''])[0];
      if (/(?:https?:|www\.|\/|@)/i.test(token.replace(/\d+\/\d+/g, '')) || /^[-\w]*[A-Za-z][-\w]*$/.test(before.slice(tokenStart))) return match;
      if (/\d,$/.test(before)) return match;
      var valid = true;
      var converted = group.replace(new RegExp(number, 'g'), function (raw) {
        var inches = numeric(raw);
        if (!Number.isFinite(inches) || inches < 0 || inches > 1000000) { valid = false; return raw; }
        return String(Number((inches * 2.54).toFixed(digits)));
      });
      return valid ? converted + ' cm' : match;
    });
  }

  function init(win, options) {
    var doc = win.document;
    var config = Object.assign({ decimals: 1, remember: true, defaultUnit: 'in', header: false, footer: false, headerSelector: '', footerSelector: '', position: 'bottom-right' }, options);
    var unit = config.defaultUnit === 'cm' ? 'cm' : 'in';
    var records = new WeakMap(), tracked = new Set(), dirty = new Set();
    var timer = null, destroyed = false, storageBlocked = false, flushCount = 0;
    if (config.remember) {
      try { var saved = win.localStorage.getItem('puc_unit'); if (saved === 'in' || saved === 'cm') unit = saved; }
      catch (_) { storageBlocked = true; }
    }
    var status = doc.createElement('span');
    status.className = 'puc-status'; status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite'); status.setAttribute('aria-atomic', 'true');
    status.setAttribute('data-puc-ignore', ''); doc.body.appendChild(status);
    function query(selector) { try { return selector ? doc.querySelector(selector) : null; } catch (_) { return null; } }
    function button() {
      var el = doc.createElement('button'); el.type = 'button'; el.className = 'puc-control';
      el.setAttribute('data-puc-toggle', '');
      var label = doc.createElement('span'); label.setAttribute('data-puc-label', ''); el.appendChild(label);
      return el;
    }
    function addHeader(target) {
      if (!target || target.querySelector('[data-puc-toggle]')) return;
      var wrapper = doc.createElement(/^(UL|OL)$/.test(target.tagName) ? 'li' : 'span');
      wrapper.className = 'puc-menu-item'; wrapper.appendChild(button()); target.appendChild(wrapper);
    }
    function place() {
      if (config.header) {
        var preferred = query(config.headerSelector);
        var desktop = preferred || query('#header-menu-1 > ul') || query('#header nav ul');
        addHeader(desktop);
        addHeader(query('nav.mobile-menu > ul'));
        // Existing PHP menu buttons stay in their native menu; never move them.
        if (!doc.querySelector('.puc-menu-item [data-puc-toggle]') && !desktop) {
          if (!doc.querySelector('.puc-floating')) {
            var fallback = doc.createElement('div'); fallback.className = 'puc-floating';
            fallback.setAttribute('data-position', config.position); fallback.appendChild(button()); doc.body.appendChild(fallback);
          }
        }
      }
      if (config.footer) {
        var footerControl = doc.querySelector('.puc-footer');
        if (!footerControl) { footerControl = doc.createElement('div'); footerControl.className = 'puc-footer'; footerControl.appendChild(button()); doc.body.appendChild(footerControl); }
        var footer = query(config.footerSelector) || query('#footer') || query('footer');
        if (footer && footer !== footerControl && !footerControl.contains(footer) && footerControl.parentNode !== footer) footer.appendChild(footerControl);
      }
    }
    function sync(announce) {
      doc.querySelectorAll('[data-puc-toggle]').forEach(function (el) {
        el.setAttribute('aria-pressed', String(unit === 'cm'));
        el.setAttribute('aria-label', 'Units: ' + unit + '. Switch to ' + (unit === 'cm' ? 'inches' : 'centimeters'));
        var label = el.querySelector('[data-puc-label]');
        if (label) label.textContent = 'Units: ' + unit;
        else el.textContent = 'Units: ' + unit;
      });
      if (announce || storageBlocked) status.textContent = (unit === 'cm' ? 'Measurements shown in centimeters.' : 'Original inch measurements restored.') + (storageBlocked ? ' Your preference cannot be saved in this browser.' : '');
      status.classList.toggle('puc-status--visible', storageBlocked);
    }
    function eligible(node) { return node.parentElement && !node.parentElement.closest(excluded); }
    function update(node) {
      var record = records.get(node);
      if (record && node.data !== record.current) { record.original = node.data; record.current = node.data; }
      if (!eligible(node)) {
        if (record && node.data === record.current) node.data = record.original;
        records.delete(node); tracked.delete(node); return;
      }
      var original = record ? record.original : node.data;
      var next = unit === 'cm' ? convertText(original, config.decimals) : original;
      if (!record && next === original) return;
      if (!record) { record = { original: original, current: original }; records.set(node, record); tracked.add(node); }
      if (node.data !== next) {
        var option = node.parentElement.closest('option');
        // HTML computes an implicit option value from its label. Pin the exact
        // original value BEFORE changing any child text, preserving submission.
        if (option && !option.hasAttribute('value')) option.setAttribute('value', option.value);
        node.data = next;
      }
      record.current = next;
    }
    function walk(rootNode) {
      if (rootNode.nodeType === 3) { update(rootNode); return; }
      var walker = doc.createTreeWalker(rootNode, win.NodeFilter.SHOW_TEXT);
      var node; while ((node = walker.nextNode())) update(node);
    }
    var observer = new win.MutationObserver(function (mutations) { collect(mutations); schedule(); });
    function collect(mutations) {
      mutations.forEach(function (m) {
        if (m.type === 'characterData' || m.type === 'attributes') dirty.add(m.target);
        else { m.addedNodes.forEach(function (node) { dirty.add(node); }); }
      });
    }
    function observe() { observer.observe(doc.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['contenteditable', 'data-puc-ignore', 'class'] }); }
    function flush(all, announce) {
      if (destroyed) return;
      if (timer !== null) { win.clearTimeout(timer); timer = null; }
      collect(observer.takeRecords()); observer.disconnect();
      try {
        flushCount++;
        place();
        tracked.forEach(function (node) {
          if (!doc.body.contains(node)) {
            var record = records.get(node);
            if (record && node.data === record.current) node.data = record.original;
            tracked.delete(node); records.delete(node);
          } else update(node);
        });
        if (all) walk(doc.body);
        else dirty.forEach(function (node) { if (doc.body.contains(node)) walk(node); });
        dirty.clear(); sync(announce);
      } finally { if (!destroyed) observe(); }
    }
    function schedule() { if (!destroyed && timer === null) timer = win.setTimeout(function () { flush(false, false); }, 0); }
    function setUnit(next, persist) {
      if (next !== 'in' && next !== 'cm') return;
      unit = next;
      if (persist !== false && config.remember) {
        try { win.localStorage.setItem('puc_unit', unit); storageBlocked = false; }
        catch (_) { storageBlocked = true; }
      }
      flush(true, true);
    }
    function onClick(event) {
      var target = event.target.closest && event.target.closest('[data-puc-toggle]');
      if (target) { event.preventDefault(); setUnit(unit === 'cm' ? 'in' : 'cm'); }
    }
    function onStorage(event) { if (config.remember && event.key === 'puc_unit' && (event.newValue === 'cm' || event.newValue === 'in')) setUnit(event.newValue, false); }
    doc.addEventListener('click', onClick); win.addEventListener('storage', onStorage);
    flush(true, false);
    return {
      setUnit: setUnit,
      refresh: function () { flush(true, false); },
      getUnit: function () { return unit; },
      diagnostics: function () { return { trackedNodes: tracked.size, flushes: flushCount, pending: timer !== null }; },
      destroy: function () {
        if (destroyed) return;
        collect(observer.takeRecords()); observer.disconnect(); destroyed = true;
        if (timer !== null) win.clearTimeout(timer);
        tracked.forEach(function (node) { var r = records.get(node); if (r && node.data === r.current) node.data = r.original; });
        tracked.clear(); dirty.clear(); doc.removeEventListener('click', onClick); win.removeEventListener('storage', onStorage); status.remove();
      }
    };
  }
  return { convertText: convertText, init: init };
});
